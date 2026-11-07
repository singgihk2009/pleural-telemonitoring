import { Queue, ScopeChanged, decode, receiptMatches, type IO, type Record as Item } from './src/patient/offline/core';
import type { AssessmentStatus, Assessment } from './src/patient/assessment';
import { ApiError, retryAfter } from './src/patient/api';
import type { Api } from './src/patient/offline/core';
const NOW = Date.parse('2026-11-08T01:00:00.000Z');
const owner = { id: '11111111-1111-4111-8111-111111111111', revision: '22222222-2222-4222-8222-222222222222' };
const snapshot: AssessmentStatus = { available: true, can_submit: true, date_wib: '2026-11-08', study_day: 1,
  submission_id: '33333333-3333-4333-8333-333333333333', submitted: null,
  questionnaire: { version: 'SYNTHETIC_V1', questions: [{ code: 'SYNTHETIC_ITEM', label: 'Synthetic, not clinical', required: true, options: [{ id: 1, label: 'Synthetic option' }] }] } };
function store() {
  let raw: string | null = null;
  const io: IO = { read: jest.fn(async () => raw), write: jest.fn(async value => { raw = value; }), remove: jest.fn(async () => { raw = null; }) };
  return { io, get: () => raw, put: (value: string | null) => { raw = value; } };
}
async function pending(queue: Queue, vas = 0) {
  await queue.initialize(snapshot, NOW);
  await queue.update({ vas_mm: vas, filled_by: 'PASIEN', answers: [{ code: 'SYNTHETIC_ITEM', option_id: 1 }] });
  return queue.confirm();
}
function receipt(body: Item['body']): Assessment {
  return { id: body.submission_id, date_wib: body.date_wib, study_day: 1, vas_mm: body.vas_mm!, type: 'HARIAN',
    filled_by: body.filled_by!, received_at: new Date(NOW + 5000).toISOString(), device_created_at: body.device_created_at,
    version: body.version, answers: body.answers.map(a => ({ ...a, value: 'SYNTHETIC_OPTION' })) };
}
function server(body: Item['body'], override?: (path: string, data?: object) => unknown) {
  const calls: { path: string; data?: object }[] = [];
  const api: Api = async <T>(path: string, data?: object) => {
    calls.push({ path, data }); const changed = await override?.(path, data); if (changed !== undefined) return changed as T;
    if (path === '/auth/me') return { data: { ...owner, kind: 'patient', credential_revision: owner.revision } } as T;
    if (path.endsWith('/status')) return { data: snapshot } as T;
    if (path.includes('?submission_id=')) return { data: [], meta: { total: 0 } } as T;
    return { data: receipt(body), replayed: false } as T;
  };
  return { api, calls, posts: () => calls.filter(call => call.path === '/patient/assessments') };
}
beforeEach(() => { jest.useFakeTimers(); jest.setSystemTime(NOW); });
afterEach(() => jest.useRealTimers());
test.each([0,100])('durable confirmation preserves VAS boundary %s and exact server acknowledgement', async vas => {
  const disk = store(); const queue = new Queue('synthetic-boundary', disk.io); const item = await pending(queue, vas); const http = server(item.body);
  expect((await queue.synchronize(http.api, owner, () => true))?.state).toBe('SYNCED');
  expect(http.posts()[0].data).toEqual(item.body); expect((await queue.read())?.receipt?.vas_mm).toBe(vas);
});
test('DRAFT begins without a VAS and cannot confirm missing/invalid answers or change original date/version', async () => {
  const disk = store(); const queue = new Queue('synthetic-draft', disk.io);
  const item = await queue.initialize(snapshot, NOW); expect(item.body.vas_mm).toBeNull();
  await expect(queue.confirm()).rejects.toThrow(/Lengkapi/);
  await expect(queue.update({ vas_mm: 101, filled_by: 'PASIEN', answers: [] })).rejects.toThrow();
  await expect(queue.update({ vas_mm: 0, filled_by: 'PASIEN', answers: [{ code: 'INVENTED', option_id: 99 }] })).rejects.toThrow();
  expect((await queue.read())?.body).toEqual(item.body);
});
test('does not persist extra token/PIN/identity fields supplied with a server snapshot', async () => {
  const disk = store(); const queue = new Queue('synthetic-no-secret', disk.io);
  await queue.initialize({ ...snapshot, token: 'SYNTHETIC-NOT-A-TOKEN', pin: 'SYNTHETIC-NOT-A-PIN' } as AssessmentStatus);
  expect(disk.get()).not.toContain('SYNTHETIC-NOT-A-TOKEN'); expect(disk.get()).not.toContain('SYNTHETIC-NOT-A-PIN'); expect(disk.get()).not.toContain(owner.id);
});
test('restart after a lost successful response recovers exact history and does not post twice', async () => {
  const disk = store(); let queue = new Queue('synthetic-lost-response', disk.io); const item = await pending(queue); let committed = false;
  const http = server(item.body, path => {
    if (path === '/patient/assessments') { committed = true; throw new ApiError(0, 'synthetic lost response'); }
    if (path.includes('?submission_id=') && committed) return { data: [receipt(item.body)], meta: { total: 1 } };
  });
  expect((await queue.synchronize(http.api, owner, () => true))?.state).toBe('PENDING_SYNC');
  expect((await queue.read())?.body).toEqual(item.body);
  queue = new Queue('synthetic-lost-response', disk.io); jest.setSystemTime(NOW + 10000);
  expect((await queue.synchronize(http.api, owner, () => true))?.state).toBe('SYNCED'); expect(http.posts()).toHaveLength(1);
});
test('interruption before delivery retains original body and later sends once', async () => {
  const disk = store(); const queue = new Queue('synthetic-before-delivery', disk.io); const item = await pending(queue);
  let online = false;
  const http = server(item.body, path => { if (path === '/auth/me' && !online) throw new ApiError(0, 'synthetic offline'); });
  const first = await queue.synchronize(http.api, owner, () => true); expect(first?.uncertain).toBe(false); expect(http.posts()).toHaveLength(0);
  online = true; jest.setSystemTime(NOW + 10000);
  expect((await queue.synchronize(http.api, owner, () => true))?.state).toBe('SYNCED'); expect(http.posts()).toHaveLength(1); expect(http.posts()[0].data).toEqual(item.body);
});
test('durable SYNCING after process death uses the original UUID/body and accepts exact replay', async () => {
  const disk = store(); const queue = new Queue('synthetic-replay', disk.io); const item = await pending(queue);
  disk.put(JSON.stringify({ ...item, state: 'SYNCING', uncertain: true }));
  const http = server(item.body, path => path === '/patient/assessments' ? { data: receipt(item.body), replayed: true } : undefined);
  expect((await new Queue('synthetic-replay', disk.io).synchronize(http.api, owner, () => true))?.state).toBe('SYNCED');
  expect(http.posts()[0].data).toEqual(item.body);
});
test.each(['midnight','version','unapproved','already_submitted'])('%s reconciles without changing or posting the body', async mode => {
  const disk = store(); const queue = new Queue('synthetic-eligibility', disk.io); const item = await pending(queue);
  const changed = { ...snapshot, ...(mode === 'midnight' ? { date_wib: '2026-11-09' } : mode === 'version' ? { questionnaire: { ...snapshot.questionnaire!, version: 'SYNTHETIC_V2' } } : mode === 'unapproved' ? { available: false, questionnaire: null } : { can_submit: false }) };
  const http = server(item.body, path => path.endsWith('/status') ? { data: changed } : undefined);
  const result = await queue.synchronize(http.api, owner, () => true);
  expect(result?.state).toBe('RECONCILIATION_REQUIRED'); expect(result?.body).toEqual(item.body); expect(http.posts()).toHaveLength(0);
});
test('old committed history is acknowledged even after midnight/version expiry', async () => {
  const disk = store(); const queue = new Queue('synthetic-old-receipt', disk.io); const item = await pending(queue);
  const http = server(item.body, path => path.endsWith('/status') ? { data: { ...snapshot, date_wib: '2026-11-09', available: false, questionnaire: null } } : path.includes('?submission_id=') ? { data: [receipt(item.body)], meta: { total: 1 } } : undefined);
  expect((await queue.synchronize(http.api, owner, () => true))?.state).toBe('SYNCED'); expect(http.posts()).toHaveLength(0);
});
test.each(['body_mismatch','missing_marker'])('%s is never presented as success', async mode => {
  const disk = store(); const queue = new Queue('synthetic-invalid-receipt', disk.io); const item = await pending(queue);
  const http = server(item.body, path => path === '/patient/assessments' ? mode === 'body_mismatch' ? { data: { ...receipt(item.body), vas_mm: 99 }, replayed: false } : { data: receipt(item.body) } : undefined);
  expect((await queue.synchronize(http.api, owner, () => true))?.state).toBe('RECONCILIATION_REQUIRED');
  expect(receiptMatches(item.body, { ...receipt(item.body), id: 'ANOTHER-SYNTHETIC-ID' })).toBe(false);
});
test.each([409,422,401,403])('server %s stops safe automatic transmission without dropping data', async status => {
  const disk = store(); const queue = new Queue('synthetic-status', disk.io); const item = await pending(queue);
  const http = server(item.body, path => { if (path === '/patient/assessments') throw new ApiError(status, 'synthetic error'); });
  const result = await queue.synchronize(http.api, owner, () => true);
  expect(result?.state).toBe(status === 409 ? 'RECONCILIATION_REQUIRED' : status === 422 ? 'REJECTED' : 'PENDING_SYNC');
  expect(result?.body).toEqual(item.body); expect(result?.receipt).toBeUndefined();
});
test('429 Retry-After and bounded exponential backoff prevent early/manual retries', async () => {
  const disk = store(); const queue = new Queue('synthetic-backoff', disk.io); const item = await pending(queue);
  const http = server(item.body, path => { if (path === '/patient/assessments') throw new ApiError(429, 'synthetic rate', 60000); });
  const first = await queue.synchronize(http.api, owner, () => true); expect(first?.nextAttemptAt).toBe(NOW + 60000);
  await queue.synchronize(http.api, owner, () => true, true); expect(http.posts()).toHaveLength(1);
  for (let i = 1; i < 5; i++) { jest.setSystemTime((await queue.read())!.nextAttemptAt); await queue.synchronize(http.api, owner, () => true); }
  jest.setSystemTime((await queue.read())!.nextAttemptAt); await queue.synchronize(http.api, owner, () => true); expect(http.posts()).toHaveLength(5);
  expect(retryAfter('60')).toBe(60000); expect(retryAfter('nonsense')).toBe(0);
});
test('two worker instances share one namespace lock and create only one post', async () => {
  const disk = store(); const first = new Queue('synthetic-single-worker', disk.io); const item = await pending(first); const second = new Queue(first.namespace, disk.io); const http = server(item.body);
  await Promise.all([first.synchronize(http.api, owner, () => true), second.synchronize(http.api, owner, () => true)]);
  expect(http.posts()).toHaveLength(1); expect((await second.read())?.state).toBe('SYNCED');
});
test('pending and rejected bodies are immutable, retention quarantine never deletes an unsent item', async () => {
  const disk = store(); const queue = new Queue('synthetic-retention', disk.io); const item = await pending(queue);
  await expect(queue.update({ vas_mm: 1, filled_by: 'KELUARGA', answers: [] })).rejects.toThrow();
  jest.setSystemTime(NOW + 8 * 86400000); const http = server(item.body);
  expect((await queue.synchronize(http.api, owner, () => true))?.reason).toBe('RETENTION_REVIEW'); expect(disk.io.remove).not.toHaveBeenCalled(); expect(http.posts()).toHaveLength(0);
  await expect(queue.discard(false)).rejects.toThrow(); expect(await queue.read()).not.toBeNull();
  await queue.discard(true); expect(await queue.read()).toBeNull();
});
test('account/background scope loss after a committed response preserves uncertain marker and hides old receipt', async () => {
  const disk = store(); const queue = new Queue('synthetic-scope-loss', disk.io); const item = await pending(queue); let current = true;
  const http = server(item.body, path => { if (path === '/patient/assessments') { current = false; return { data: receipt(item.body), replayed: false }; } });
  await expect(queue.synchronize(http.api, owner, () => current)).rejects.toBeInstanceOf(ScopeChanged);
  expect((await queue.read())?.state).toBe('SYNCING'); expect((await queue.read())?.receipt).toBeUndefined();
});
test('write/readback failures and corrupted records fail closed without a post or silent erasure', async () => {
  const disk = store(); const queue = new Queue('synthetic-storage-error', disk.io); const item = await pending(queue); const http = server(item.body);
  const write = disk.io.write;
  disk.io.write = async value => { if (JSON.parse(value).state === 'SYNCING') throw new Error('synthetic storage failure'); await write(value); };
  await expect(queue.synchronize(http.api, owner, () => true)).rejects.toThrow(/Antrean terkunci/); expect(http.posts()).toHaveLength(0);
  const raw = disk.get()!; disk.put('{broken'); await expect(queue.read()).rejects.toThrow(); expect(disk.io.remove).not.toHaveBeenCalled();
  expect(() => decode(JSON.stringify({ ...JSON.parse(raw), body: { ...item.body, pin: 'SYNTHETIC' } }))).toThrow();
});
