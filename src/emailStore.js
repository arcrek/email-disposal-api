export function createEmailStore(db, options = {}) {
  const maxLockSeconds = options.maxLockSeconds || 15;
  void maxLockSeconds;

  return {
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
