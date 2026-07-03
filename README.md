# Email Disposal API

Small Node.js email API with a static admin panel and SQLite persistence.

## Run With Docker

```bash
docker compose up --build
```

Open:

- API: `http://localhost:3000/api/email`
- Admin: `http://localhost:3000/admin/`

The SQLite database is stored at `data/emails.sqlite` through the Compose volume.

## Data

The app creates the SQLite schema automatically.

## API

`GET /api/email`

Success:

```json
{
  "success": true,
  "email": "user@example.com",
  "timestamp": 1234567890
}
```

No emails available:

```json
{
  "success": false,
  "error": "no_emails_available",
  "message": "No available emails at this time",
  "timestamp": 1234567890
}
```

## Admin Actions

- Export all emails
- Import emails from a `.txt` file
- Clear locked emails
- Delete all emails

## Local Development

```bash
npm install
npm test
npm start
```
