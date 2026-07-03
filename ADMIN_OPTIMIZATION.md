# Admin Panel Notes

The admin panel is intentionally small and static. Express serves `admin/index.html`, `admin/script.js`, and `admin/style.css`, while compatibility endpoints keep the previous `.php` URL names working.

## Current Actions

- Export all emails as a text file.
- Import valid emails from a `.txt` file.
- Clear locked emails.
- Delete all emails.

## Runtime Behavior

- Statistics are loaded from `GET /admin/quick_stats.php`.
- Bulk maintenance actions use `POST /admin/bulk_operations.php`.
- Export uses `GET /admin/export_emails.php`.
- The browser parses import files before posting, and the server validates emails again before inserting into SQLite.

## Storage

SQLite is the only database. The default database path is:

```text
${DATA_DIR:-/app/data}/emails.sqlite
```

Docker Compose mounts `./data:/app/data` so the database survives container restarts.
