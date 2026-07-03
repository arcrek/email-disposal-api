const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

function normalizeEmail(value) {
  if (typeof value !== 'string') return null;
  const email = value.trim();
  return EMAIL_RE.test(email) ? email : null;
}

export function createEmailStore(db, options = {}) {
  const maxLockSeconds = options.maxLockSeconds || 15;

  const insertEmail = db.prepare('INSERT OR IGNORE INTO emails (email) VALUES (?)');
  const addEmailsTransaction = db.transaction((emails) => {
    let inserted = 0;
    for (const value of emails) {
      const email = normalizeEmail(value);
      if (!email) continue;
      const result = insertEmail.run(email);
      inserted += result.changes;
    }
    return inserted;
  });

  const getRandomEmailTransaction = db.transaction((nowSeconds) => {
    clearExpiredLocks(nowSeconds);

    const countRow = db.prepare('SELECT COUNT(*) AS total FROM emails WHERE is_locked = 0').get();
    const total = Number(countRow.total);
    if (total === 0) return null;

    const offset = Math.floor(Math.random() * total);
    const row = db.prepare(`
      SELECT id, email
      FROM emails
      WHERE is_locked = 0
      ORDER BY id
      LIMIT 1 OFFSET ?
    `).get(offset);

    if (!row) return null;

    const result = db.prepare(`
      UPDATE emails
      SET is_locked = 1, locked_at = ?
      WHERE id = ? AND is_locked = 0
    `).run(nowSeconds, row.id);

    return result.changes === 1 ? row.email : null;
  });

  function clearExpiredLocks(nowSeconds = Math.floor(Date.now() / 1000)) {
    const expiredBefore = nowSeconds - maxLockSeconds;
    const result = db.prepare(`
      UPDATE emails
      SET is_locked = 0, locked_at = 0
      WHERE is_locked = 1 AND locked_at < ?
    `).run(expiredBefore);
    return result.changes;
  }

  return {
    addEmails(emails) {
      return addEmailsTransaction(Array.isArray(emails) ? emails : []);
    },

    getRandomEmail(nowSeconds = Math.floor(Date.now() / 1000)) {
      return getRandomEmailTransaction(nowSeconds);
    },

    clearExpiredLocks,

    clearAllLocks() {
      const result = db.prepare(`
        UPDATE emails
        SET is_locked = 0, locked_at = 0
        WHERE is_locked = 1
      `).run();
      return result.changes;
    },

    deleteAllEmails() {
      return db.transaction(() => {
        const row = db.prepare('SELECT COUNT(*) AS total FROM emails').get();
        db.prepare('DELETE FROM emails').run();
        return Number(row.total);
      })();
    },

    listEmails() {
      return db.prepare('SELECT email FROM emails ORDER BY id').all().map((row) => row.email);
    },

    getStats() {
      const row = db.prepare(`
        SELECT
          COUNT(*) AS total,
          COALESCE(SUM(is_locked), 0) AS locked,
          COUNT(*) - COALESCE(SUM(is_locked), 0) AS available
        FROM emails
      `).get();

      return {
        total: Number(row.total),
        locked: Number(row.locked),
        available: Number(row.available),
        approximate: false
      };
    },

    close() {
      db.close();
    }
  };
}
