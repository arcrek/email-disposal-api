import { mkdtempSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import { openDatabase } from '../src/db.js';
import { createEmailStore } from '../src/emailStore.js';

const tempDirs = [];

function createTempStore() {
  const dataDir = mkdtempSync(path.join(tmpdir(), 'email-api-'));
  tempDirs.push(dataDir);
  const db = openDatabase({ dataDir });
  const store = createEmailStore(db);
  return { store };
}

afterEach(() => {
  for (const dir of tempDirs.splice(0)) {
    rmSync(dir, { recursive: true, force: true });
  }
});

describe('email store', () => {
  it('initializes an empty SQLite database with zero stats', () => {
    const { store } = createTempStore();

    expect(store.getStats()).toEqual({
      total: 0,
      locked: 0,
      available: 0,
      approximate: false
    });

    store.close();
  });
});
