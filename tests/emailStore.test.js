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

  it('adds valid unique emails and ignores invalid or duplicate values', () => {
    const { store } = createTempStore();

    const inserted = store.addEmails([
      'a@example.com',
      'bad-value',
      'a@example.com',
      'b@example.com'
    ]);

    expect(inserted).toBe(2);
    expect(store.getStats()).toMatchObject({ total: 2, available: 2, locked: 0 });
    expect(store.listEmails()).toEqual(['a@example.com', 'b@example.com']);

    store.close();
  });

  it('returns null when no email is available', () => {
    const { store } = createTempStore();

    expect(store.getRandomEmail()).toBeNull();

    store.close();
  });

  it('locks returned emails and prevents active duplicates', () => {
    const { store } = createTempStore();
    store.addEmails(['a@example.com']);

    expect(store.getRandomEmail(100)).toBe('a@example.com');
    expect(store.getRandomEmail(101)).toBeNull();
    expect(store.getStats()).toMatchObject({ total: 1, available: 0, locked: 1 });

    store.close();
  });

  it('releases expired locks before selecting an email', () => {
    const { store } = createTempStore();
    store.addEmails(['a@example.com']);

    expect(store.getRandomEmail(100)).toBe('a@example.com');
    expect(store.getRandomEmail(116)).toBe('a@example.com');

    store.close();
  });

  it('clears locked emails without deleting rows', () => {
    const { store } = createTempStore();
    store.addEmails(['a@example.com', 'b@example.com']);
    store.getRandomEmail(100);

    expect(store.clearAllLocks()).toBe(1);
    expect(store.getStats()).toMatchObject({ total: 2, available: 2, locked: 0 });

    store.close();
  });

  it('deletes all emails', () => {
    const { store } = createTempStore();
    store.addEmails(['a@example.com', 'b@example.com']);

    expect(store.deleteAllEmails()).toBe(2);
    expect(store.getStats()).toMatchObject({ total: 0, available: 0, locked: 0 });

    store.close();
  });
});
