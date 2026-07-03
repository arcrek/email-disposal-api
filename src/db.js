import Database from 'better-sqlite3';
import { mkdirSync } from 'node:fs';
import path from 'node:path';

export function getDefaultDataDir() {
  return process.env.DATA_DIR || '/app/data';
}

export function getDatabasePath(dataDir = getDefaultDataDir()) {
  return path.join(dataDir, 'emails.sqlite');
}

export function openDatabase({ dataDir, databasePath } = {}) {
  const resolvedDataDir = dataDir || path.dirname(databasePath || getDatabasePath());
  mkdirSync(resolvedDataDir, { recursive: true });

  const db = new Database(databasePath || getDatabasePath(resolvedDataDir));
  db.pragma('journal_mode = WAL');
  db.pragma('foreign_keys = ON');

  db.exec(`
    CREATE TABLE IF NOT EXISTS emails (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      email TEXT NOT NULL UNIQUE,
      is_locked INTEGER NOT NULL DEFAULT 0,
      locked_at INTEGER NOT NULL DEFAULT 0,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    );
    CREATE INDEX IF NOT EXISTS idx_emails_available ON emails(is_locked, id);
    CREATE INDEX IF NOT EXISTS idx_emails_locked_at ON emails(locked_at);
    CREATE INDEX IF NOT EXISTS idx_emails_created_at ON emails(created_at);
  `);

  return db;
}
