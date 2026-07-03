import express from 'express';

export function registerAdminRoutes(app, store, adminDir) {
  app.get('/admin/quick_stats.php', (req, res, next) => {
    try {
      res.set('Cache-Control', 'public, max-age=30');
      return res.json({ success: true, stats: store.getStats() });
    } catch (error) {
      return next(error);
    }
  });

  app.post('/admin/bulk_operations.php', (req, res, next) => {
    try {
      const operation = req.body?.operation;

      if (operation === 'bulk_add') {
        const count = store.addEmails(req.body?.emails || []);
        return res.json({ success: true, count, message: `Added ${count} emails successfully` });
      }

      if (operation === 'clear_locked') {
        const count = store.clearAllLocks();
        return res.json({ success: true, count, message: `Unlocked ${count} emails` });
      }

      if (operation === 'delete_all') {
        const count = store.deleteAllEmails();
        return res.json({ success: true, count, message: `Deleted ${count} emails` });
      }

      return res.status(400).json({ success: false, message: 'Invalid operation' });
    } catch (error) {
      return next(error);
    }
  });

  app.get('/admin/export_emails.php', (req, res, next) => {
    try {
      const filename = new Date().toISOString().slice(0, 10);
      res.set('Content-Type', 'text/plain; charset=utf-8');
      res.set('Content-Disposition', `attachment; filename="emails_${filename}.txt"`);
      return res.send(`${store.listEmails().join('\n')}\n`);
    } catch (error) {
      return next(error);
    }
  });

  app.use('/admin', express.static(adminDir, { index: 'index.html' }));
}
