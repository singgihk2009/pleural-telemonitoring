import * as SecureStore from 'expo-secure-store';
import * as Crypto from 'expo-crypto';
import { Platform } from 'react-native';
import { openQueue } from './src/patient/offline/storage';
import type { AssessmentStatus } from './src/patient/assessment';
const owner = '11111111-1111-4111-8111-111111111111';
const revision = '22222222-2222-4222-8222-222222222222';
const nextRevision = '44444444-4444-4444-8444-444444444444';
const data = new Map<string,string>();
const originalOS = Platform.OS;
const snapshot: AssessmentStatus = { available: true, can_submit: true, date_wib: '2026-11-08', study_day: 1, submission_id: '33333333-3333-4333-8333-333333333333', submitted: null,
  questionnaire: { version: 'SYNTHETIC_V1', questions: [{ code: 'SYNTHETIC', label: 'SYNTHETIC_QUEUE_TEST', required: true, options: [{ id: 1, label: 'Synthetic option' }] }] } };
beforeEach(() => {
  process.env.EXPO_PUBLIC_API_BASE_URL = 'http://localhost:8000/api/v1';
  data.clear(); jest.clearAllMocks(); Object.defineProperty(Platform, 'OS', { value: 'android', configurable: true });
  jest.mocked(SecureStore.isAvailableAsync).mockResolvedValue(true);
  jest.mocked(SecureStore.getItemAsync).mockImplementation(async key => data.get(key) ?? null);
  jest.mocked(SecureStore.setItemAsync).mockImplementation(async (key,value) => { data.set(key,value); });
  jest.mocked(SecureStore.deleteItemAsync).mockImplementation(async key => { data.delete(key); });
});
afterEach(() => Object.defineProperty(Platform, 'OS', { value: originalOS, configurable: true }));
test('native adapter uses SecureStore exclusively with device-only options and salted nonreversible names', async () => {
  const queue = await openQueue(owner,revision); await queue.initialize(snapshot);
  expect(queue.namespace).not.toContain(owner); expect(queue.namespace).not.toContain(revision);
  expect(queue.namespace).toMatch(/^telenapas\.offline\.item\.[a-f0-9]{64}$/);
  for (const call of jest.mocked(SecureStore.setItemAsync).mock.calls) expect(call[2]).toEqual({ keychainService: 'telenapas.offline.v1', keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY });
  expect(Crypto.getRandomBytesAsync).toHaveBeenCalledWith(32);
});
test('fresh queue instances recover persisted drafts only for the same patient and credential revision', async () => {
  const queue = await openQueue(owner,revision); const item = await queue.initialize(snapshot);
  const reopened = await openQueue(owner,revision); expect(await reopened.read()).toEqual(item);
  const reset = await openQueue(owner,nextRevision); expect(await reset.read()).toBeNull();
  const differentPatient = await openQueue(nextRevision,revision); expect(await differentPatient.read()).toBeNull();
  expect(data.size).toBe(3); // installation salt, encrypted owner index and item; no account-switch erasure.
  expect(reset.lockedPredecessors).toBe(1); expect(differentPatient.lockedPredecessors).toBe(0);
});
test('concurrent initialization shares one installation salt and namespace', async () => {
  const [a,b] = await Promise.all([openQueue(owner,revision),openQueue(owner,revision)]);
  expect(a.namespace).toBe(b.namespace); expect(Crypto.getRandomBytesAsync).toHaveBeenCalledTimes(1);
});
test('unavailable encryption or non-Android runtime fails closed without writing a clinical item', async () => {
  jest.mocked(SecureStore.isAvailableAsync).mockResolvedValue(false);
  await expect(openQueue(owner,revision)).rejects.toThrow(); expect(SecureStore.setItemAsync).not.toHaveBeenCalled();
  Object.defineProperty(Platform, 'OS', { value: 'ios', configurable: true });
  await expect(openQueue(owner,revision)).rejects.toThrow();
});
test('corrupt installation key is retained rather than replaced or silently orphaning items', async () => {
  data.set('telenapas.offline.install.v1','broken');
  await expect(openQueue(owner,revision)).rejects.toThrow();
  expect(SecureStore.deleteItemAsync).not.toHaveBeenCalled(); expect(SecureStore.setItemAsync).not.toHaveBeenCalled();
});
test('failed salt readback refuses to create an item without plaintext fallback', async () => {
  jest.mocked(SecureStore.getItemAsync).mockResolvedValue(null);
  await expect(openQueue(owner,revision)).rejects.toThrow(); expect(SecureStore.setItemAsync).toHaveBeenCalledTimes(1);
});
test('oversized drafts fail before any clinical value is written and no old item is overwritten', async () => {
  const queue = await openQueue(owner,revision);
  const huge = { ...snapshot, questionnaire: { ...snapshot.questionnaire!, questions: [{ ...snapshot.questionnaire!.questions[0], label: 'SYNTHETIC'.repeat(10000) }] } };
  await expect(queue.initialize(huge)).rejects.toThrow(/terlalu besar/); expect(await queue.read()).toBeNull();
  expect(SecureStore.setItemAsync).toHaveBeenCalledTimes(1); // installation key only
});


test('a different API environment cannot load or enumerate the same cloned identity queue', async () => {
  const first = await openQueue(owner,revision); await first.initialize(snapshot);
  process.env.EXPO_PUBLIC_API_BASE_URL = 'https://synthetic.invalid/api/v1';
  const different = await openQueue(owner,revision);
  expect(different.namespace).not.toBe(first.namespace); expect(await different.read()).toBeNull(); expect(different.lockedPredecessors).toBe(0);
});


test('unavailable native randomness refuses a queue instead of using a development random fallback', async () => {
  jest.mocked(Crypto.getRandomBytesAsync).mockRejectedValueOnce(new Error('Synthetic native crypto unavailable'));
  await expect(openQueue(owner,revision)).rejects.toThrow(); expect(SecureStore.setItemAsync).not.toHaveBeenCalled();
});
