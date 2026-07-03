import { createApp } from './app.js';
import { openDatabase } from './db.js';
import { createEmailStore } from './emailStore.js';

const port = Number(process.env.PORT || 3000);
const db = openDatabase();
const store = createEmailStore(db);
const app = createApp({ store });

const server = app.listen(port, () => {
  console.log(`Email API listening on port ${port}`);
});

function shutdown() {
  server.close(() => {
    store.close();
    process.exit(0);
  });
}

process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
