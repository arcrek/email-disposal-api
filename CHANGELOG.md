# Changelog

## 3.0.0 - Node Docker SQLite Migration

- Replaced the PHP/MySQL/cPanel runtime with a Node.js Express service.
- Added SQLite persistence at `${DATA_DIR:-/app/data}/emails.sqlite`.
- Added Docker and Docker Compose deployment files.
- Preserved compatibility routes:
  - `GET /api/email`
  - `GET /api/email.php`
  - `GET /admin/`
  - `GET /admin/quick_stats.php`
  - `POST /admin/bulk_operations.php`
  - `GET /admin/export_emails.php`
- Kept the static admin panel and added a delete-all-emails maintenance action.
- Removed automatic startup imports from `data/email.txt` and `data/emails.txt`.
- Removed old PHP setup, initialization, and admin endpoint files.

## Notes

The database starts empty unless emails are imported through the admin panel.
