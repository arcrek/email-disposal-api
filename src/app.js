import express from 'express';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { registerAdminRoutes } from './routes/admin.js';
import { registerApiRoutes } from './routes/api.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const defaultAdminDir = path.resolve(__dirname, '..', 'admin');

export function createApp({ store, adminDir = defaultAdminDir } = {}) {
  if (!store) {
    throw new Error('createApp requires a store');
  }

  const app = express();
  app.disable('x-powered-by');
  app.use(express.json({ limit: '1mb' }));

  registerApiRoutes(app, store);
  registerAdminRoutes(app, store, adminDir);

  app.use((error, req, res, next) => {
    console.error(error);
    if (req.path.startsWith('/api/')) {
      return res.status(500).json({
        success: false,
        error: 'system_error',
        message: 'Internal server error',
        timestamp: Math.floor(Date.now() / 1000)
      });
    }
    return res.status(500).json({ success: false, message: 'Operation failed' });
  });

  return app;
}
