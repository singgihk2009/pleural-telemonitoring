import * as SecureStore from 'expo-secure-store';
import type { Session } from './api';
const KEY = 'telenapas.patient.session.v1';
export const vault = {
  async read(): Promise<Session | null> {
    const raw = await SecureStore.getItemAsync(KEY);
    if (!raw) return null;
    const data = JSON.parse(raw) as Session;
    if (typeof data.token !== 'string' || !data.token || !Number.isFinite(Date.parse(data.expires)))
      throw new Error('Invalid session');
    return data;
  },
  save: (session: Session) =>
    SecureStore.setItemAsync(KEY, JSON.stringify(session), {
      keychainAccessible: SecureStore.WHEN_UNLOCKED_THIS_DEVICE_ONLY,
    }),
  clear: () => SecureStore.deleteItemAsync(KEY),
};
