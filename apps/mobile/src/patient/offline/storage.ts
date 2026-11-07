import * as SecureStore from 'expo-secure-store';
import { Platform } from 'react-native';
import { apiBase } from '../api';
import { Queue, exclusive } from './core';
const options = { keychainService: 'telenapas.offline.v1', keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY };
const saltKey = 'telenapas.offline.install.v1';
export async function openQueue(id: string, revision: string): Promise<Queue> {
  if (Platform.OS !== 'android' || !await SecureStore.isAvailableAsync() || !/^[0-9a-f-]{36}$/i.test(id) || !/^[0-9a-f-]{36}$/i.test(revision))
    throw new Error('Antrean terenkripsi belum tersedia pada perangkat atau kredensial ini.');
  // Lazy loading keeps unsupported/old binaries usable for login, while queue
  // initialization fails closed before any clinical value can be persisted.
  const Crypto = require('expo-crypto') as typeof import('expo-crypto');
  const salt = await exclusive(saltKey, async () => {
    let value = await SecureStore.getItemAsync(saltKey, options);
    if (!value) {
      value = Array.from(await Crypto.getRandomBytesAsync(32), n => n.toString(16).padStart(2,'0')).join('');
      await SecureStore.setItemAsync(saltKey, value, options);
      if (await SecureStore.getItemAsync(saltKey, options) !== value) throw new Error('Kunci instalasi belum tersimpan aman.');
    }
    if (!/^[0-9a-f]{64}$/.test(value)) throw new Error('Kunci instalasi tidak valid.');
    return value;
  });
  const origin = apiBase();
  const digest = (value: string) => Crypto.digestStringAsync(Crypto.CryptoDigestAlgorithm.SHA256, value);
  const ownerHash = await digest(`telenapas.offline.owner.v1\0${salt}\0${origin}\0${id}`);
  const hash = await digest(`telenapas.offline.v1\0${salt}\0${origin}\0${id}\0${revision}`);
  const key = `telenapas.offline.item.${hash}`;
  const manifestKey = `telenapas.offline.owner.${ownerHash}`;
  async function manifest(): Promise<string[]> {
    const raw = await SecureStore.getItemAsync(manifestKey, options);
    if (!raw) return [];
    const value: unknown = JSON.parse(raw);
    if (!Array.isArray(value) || value.length > 32 || value.some(entry => typeof entry !== 'string' || !/^telenapas\.offline\.item\.[0-9a-f]{64}$/.test(entry))) throw new Error('Indeks antrean tidak valid.');
    return value;
  }
  async function saveManifest(keys: string[]): Promise<void> {
    if (keys.length > 32) throw new Error('Kapasitas indeks lokal memerlukan peninjauan.');
    const raw = JSON.stringify(keys);
    await SecureStore.setItemAsync(manifestKey, raw, options);
    if (await SecureStore.getItemAsync(manifestKey, options) !== raw) throw new Error('Indeks aman belum terverifikasi.');
  }
  const prior = await exclusive(manifestKey, manifest);
  return new Queue(key, {
    read: () => SecureStore.getItemAsync(key, options),
    write: async value => {
      // Register before writing a body: even a lost response/write leaves a discoverable
      // locked-predecessor warning after credential rotation, without decrypting its body.
      await exclusive(manifestKey, async () => { const keys = await manifest(); if (!keys.includes(key)) await saveManifest([...keys,key]); });
      await SecureStore.setItemAsync(key, value, options);
    },
    remove: async () => {
      await SecureStore.deleteItemAsync(key, options);
      if (await SecureStore.getItemAsync(key, options) !== null) throw new Error('Penghapusan belum terverifikasi.');
      await exclusive(manifestKey, async () => { await saveManifest((await manifest()).filter(item => item !== key)); });
    },
  }, prior.filter(item => item !== key).length);
}
