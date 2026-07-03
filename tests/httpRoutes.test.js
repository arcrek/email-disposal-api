import { mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import EventEmitter from 'node:events';
import { Readable } from 'node:stream';
import { afterEach, describe, expect, it } from 'vitest';
import httpMocks from 'node-mocks-http';
import { createApp } from '../src/app.js';
import { openDatabase } from '../src/db.js';
import { createEmailStore } from '../src/emailStore.js';

const tempDirs = [];

function createTestApp() {
  const dataDir = mkdtempSync(path.join(tmpdir(), 'email-api-http-'));
  tempDirs.push(dataDir);
  const db = openDatabase({ dataDir });
  const store = createEmailStore(db);
  const app = createApp({ store, adminDir: path.resolve('admin') });
  return { app, store };
}

async function request(app, method, url, body) {
  const { req, res } = httpMocks.createMocks({
    method,
    url,
    headers: body === undefined ? {} : { 'content-type': 'application/json' },
    body
  }, {
    eventEmitter: EventEmitter
  });

  await new Promise((resolve) => {
    res.on('end', resolve);
    app.handle(req, res);
  });

  return {
    statusCode: res.statusCode,
    headers: res._getHeaders(),
    payload: res._getData()
  };
}

async function requestRawJson(app, method, url, body) {
  const payload = JSON.stringify(body);
  const req = Readable.from([Buffer.from(payload)]);
  Object.assign(req, {
    method,
    url,
    originalUrl: url,
    headers: {
      'content-type': 'application/json',
      'content-length': String(Buffer.byteLength(payload))
    },
    socket: {}
  });
  const res = httpMocks.createResponse({ eventEmitter: EventEmitter, req });

  await new Promise((resolve) => {
    res.on('end', resolve);
    app.handle(req, res);
  });

  return {
    statusCode: res.statusCode,
    headers: res._getHeaders(),
    payload: res._getData()
  };
}

function parseJson(response) {
  return JSON.parse(response.payload);
}

afterEach(() => {
  for (const dir of tempDirs.splice(0)) {
    rmSync(dir, { recursive: true, force: true });
  }
});

describe('http routes', () => {
  it('returns 429 from /api/email when no emails exist', async () => {
    const { app, store } = createTestApp();

    const response = await request(app, 'GET', '/api/email');

    expect(response.statusCode).toBe(429);
    expect(parseJson(response)).toMatchObject({
      success: false,
      error: 'no_emails_available'
    });

    store.close();
  });

  it('serves /api/email.php as a compatibility alias', async () => {
    const { app, store } = createTestApp();
    store.addEmails(['a@example.com']);

    const response = await request(app, 'GET', '/api/email.php');

    expect(response.statusCode).toBe(200);
    expect(parseJson(response)).toMatchObject({
      success: true,
      email: 'a@example.com'
    });

    store.close();
  });

  it('supports admin stats, import, clear locks, delete all, and export', async () => {
    const { app, store } = createTestApp();

    const importResponse = await request(app, 'POST', '/admin/bulk_operations.php', {
      operation: 'bulk_add',
      emails: ['a@example.com', 'b@example.com', 'bad']
    });
    expect(importResponse.statusCode).toBe(200);
    expect(parseJson(importResponse)).toMatchObject({ success: true, count: 2 });

    const statsResponse = await request(app, 'GET', '/admin/quick_stats.php');
    expect(statsResponse.statusCode).toBe(200);
    expect(parseJson(statsResponse).stats).toMatchObject({ total: 2, available: 2, locked: 0 });

    const emailResponse = await request(app, 'GET', '/api/email');
    expect(emailResponse.statusCode).toBe(200);

    const clearResponse = await request(app, 'POST', '/admin/bulk_operations.php', {
      operation: 'clear_locked'
    });
    expect(clearResponse.statusCode).toBe(200);
    expect(parseJson(clearResponse)).toMatchObject({ success: true, count: 1 });

    const exportResponse = await request(app, 'GET', '/admin/export_emails.php');
    expect(exportResponse.statusCode).toBe(200);
    expect(exportResponse.headers['content-type']).toMatch(/text\/plain/);
    expect(exportResponse.payload.trim().split('\n')).toEqual(['a@example.com', 'b@example.com']);

    const deleteResponse = await request(app, 'POST', '/admin/bulk_operations.php', {
      operation: 'delete_all'
    });
    expect(deleteResponse.statusCode).toBe(200);
    expect(parseJson(deleteResponse)).toMatchObject({ success: true, count: 2 });

    expect(store.getStats()).toMatchObject({ total: 0, available: 0, locked: 0 });

    store.close();
  });

  it('accepts admin bulk import requests larger than 1mb', async () => {
    const { app, store } = createTestApp();
    const emails = Array.from({ length: 45_000 }, (_, index) => `user${index}@example.com`);

    const response = await requestRawJson(app, 'POST', '/admin/bulk_operations.php', {
      operation: 'bulk_add',
      emails
    });

    expect(response.statusCode).toBe(200);
    expect(parseJson(response)).toMatchObject({ success: true, count: emails.length });
    expect(store.getStats()).toMatchObject({ total: emails.length });

    store.close();
  });

  it('serves admin UI with delete all emails action', async () => {
    const { app, store } = createTestApp();

    const response = await request(app, 'GET', '/admin/');
    const adminHtml = readFileSync(path.resolve('admin/index.html'), 'utf8');

    expect(response.statusCode).toBe(200);
    expect(adminHtml).toContain('Delete All Emails');
    expect(adminHtml).toContain('deleteAllEmails()');

    store.close();
  });
});
