# JS Docker SQLite Migration Design

## Summary

Migrate the existing cPanel-oriented PHP email API to a small JavaScript service that deploys with Docker and uses SQLite for persistence. The implementation will use Express to keep the service familiar and direct, preserve the current API/admin route contracts, and reuse the existing static admin UI with minimal changes.

The SQLite database will be persisted through the existing `data/` directory. The app will create its schema on startup, but it will not automatically import `data/email.txt` or `data/emails.txt`. Email data enters the system through the admin import flow.

## Goals

- Replace the PHP/MySQL/cPanel runtime with a Node.js service.
- Use SQLite as the only database.
- Provide Docker and Docker Compose deployment.
- Preserve these public routes and response shapes:
  - `GET /api/email`
  - `GET /api/email.php`
  - `GET /admin/`
  - `GET /admin/quick_stats.php`
  - `POST /admin/bulk_operations.php`
  - `GET /admin/export_emails.php`
- Keep the current admin workflow: stats, import, export, clear locked emails.
- Add an admin "delete all emails" maintenance action.
- Keep the project simple and easy to run locally.

## Non-Goals

- No cPanel setup flow.
- No MySQL support.
- No automatic data import during startup.
- No full admin frontend rebuild.
- No authentication changes in this migration.

## Architecture

The new service will be a Node.js application with Express. Static assets in `admin/` will be served by Express, and the previous PHP endpoint names will be implemented as Express routes for compatibility. The database layer will be a small module responsible for opening SQLite, applying schema initialization, and exposing email operations.

Proposed structure:

```text
src/
  app.js
  server.js
  db.js
  emailStore.js
  routes/
    api.js
    admin.js
admin/
  index.html
  script.js
  style.css
data/
  emails.sqlite
Dockerfile
docker-compose.yml
package.json
```

The `src/app.js` module will create the Express app for both tests and runtime. `src/server.js` will bind to the configured port. `src/db.js` will own the SQLite connection and schema creation. `src/emailStore.js` will contain the core email selection, lock, stats, import, export, and delete operations.

## SQLite Schema

SQLite database path:

```text
${DATA_DIR:-/app/data}/emails.sqlite
```

Primary table:

```sql
CREATE TABLE IF NOT EXISTS emails (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  is_locked INTEGER NOT NULL DEFAULT 0,
  locked_at INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

Indexes:

```sql
CREATE INDEX IF NOT EXISTS idx_emails_available ON emails(is_locked, id);
CREATE INDEX IF NOT EXISTS idx_emails_locked_at ON emails(locked_at);
CREATE INDEX IF NOT EXISTS idx_emails_created_at ON emails(created_at);
```

`is_locked` will be stored as `0` or `1`. `locked_at` will be a Unix timestamp in seconds.

## Data Flow

### Get Random Email

`GET /api/email` will:

1. Clear expired locks where `locked_at < now - 15`.
2. Select one unlocked email using a randomized offset.
3. Update that row to locked inside a SQLite transaction.
4. Return the existing success JSON shape:

```json
{
  "success": true,
  "email": "user@example.com",
  "timestamp": 1234567890
}
```

If no unlocked emails exist, the route returns HTTP 429 with the existing error shape.

SQLite has one writer at a time, so the lock update must be transactional. The design does not require perfect statistical randomness; it needs fast enough random distribution and no duplicate active locks under normal concurrent traffic.

### Import Emails

Admin import will continue to parse a `.txt` file client-side and post valid emails to:

```http
POST /admin/bulk_operations.php
```

with:

```json
{
  "operation": "bulk_add",
  "emails": ["a@example.com", "b@example.com"]
}
```

The server validates emails again, inserts with duplicate protection, and returns the count of newly inserted rows.

### Export Emails

`GET /admin/export_emails.php` will stream all emails in ID order as plain text, one email per line, with a download filename matching the current behavior.

### Clear Locked Emails

`operation: "clear_locked"` will set `is_locked = 0` and `locked_at = 0` for locked rows and return the number of affected emails.

### Delete All Emails

`operation: "delete_all"` will delete all rows from `emails` inside one transaction and return the number of deleted emails. The admin UI will add a distinct maintenance button and require browser confirmation before sending the request.

## Routes

### API

- `GET /api/email`: random locked email.
- `GET /api/email.php`: compatibility alias for direct requests to the old PHP file path.

### Admin

- `GET /admin/`: static admin UI.
- `GET /admin/quick_stats.php`: statistics JSON.
- `POST /admin/bulk_operations.php`: `bulk_add`, `clear_locked`, `delete_all`.
- `GET /admin/export_emails.php`: plain text export.

The old admin route names ending in `.php` are retained as HTTP compatibility routes only. No PHP runtime remains.

## Error Handling

API errors will retain the existing general JSON shape:

```json
{
  "success": false,
  "error": "system_error",
  "message": "Internal server error",
  "timestamp": 1234567890
}
```

Admin errors will return:

```json
{
  "success": false,
  "message": "Operation failed"
}
```

Detailed internal errors should be logged to stderr for Docker logs. Responses should avoid exposing stack traces in production.

## Docker Deployment

The Docker image will use Node.js 20. The container will listen on `PORT`, defaulting to `3000`.

`docker-compose.yml` will mount the repository `data/` directory into the container:

```yaml
volumes:
  - ./data:/app/data
```

This keeps `emails.sqlite` persistent across container restarts and makes backup straightforward.

Expected commands:

```bash
docker compose up --build
curl http://localhost:3000/api/email
```

The first request will return 429 until emails are imported through the admin panel.

## Testing

Use a JavaScript test runner against the Express app and an isolated temporary SQLite database.

Required coverage:

- schema initializes on an empty database;
- stats returns zero counts for an empty database;
- import inserts valid emails and ignores duplicates;
- `GET /api/email` returns 429 when empty;
- `GET /api/email` locks returned emails;
- expired locks are released;
- clear locked emails unlocks rows;
- delete all emails removes all rows;
- export returns one email per line;
- preserved admin route names respond as expected.

Verification should include:

```bash
npm test
npm run lint
docker compose build
```

If local Docker is unavailable, record that limitation and still run the Node test suite.
