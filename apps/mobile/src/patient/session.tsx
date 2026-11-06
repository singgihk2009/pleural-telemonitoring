import { useEffect, useRef, useState, type PropsWithChildren } from 'react';
import { AppState } from 'react-native';
import NetInfo from '@react-native-community/netinfo';
import { vault } from './storage';
import { PatientContext, type State } from './context';
import { ApiError, request, type Profile, type Session } from './api';

export function PatientProvider({ children }: PropsWithChildren) {
  const [state, setState] = useState<State>({
    session: null,
    profile: null,
    ready: false,
    busy: false,
    offline: false,
    message: '',
    acknowledged: false,
  });
  const current = useRef<Session | null>(null);
  const generation = useRef(0);
  const pendingLogin = useRef<number | null>(null);
  // Order mutations so cleanup from an old login cannot erase a newer token.
  const storage = useRef<Promise<unknown>>(Promise.resolve());
  function stored<T>(operation: () => Promise<T>): Promise<T> {
    const result = storage.current.then(operation);
    storage.current = result.catch(() => undefined);
    return result;
  }
  async function revoke(token: string) {
    try {
      await request('/auth/logout', token, {});
    } catch {
      // Best effort while offline; server expiration still bounds the token lifetime.
    }
  }
  async function clear(message: string) {
    generation.current++;
    current.current = null;
    setState((s) => ({
      ...s,
      session: null,
      profile: null,
      acknowledged: false,
      ready: true,
      busy: false,
      message,
    }));
    try {
      await stored(() => vault.clear());
    } catch {
      setState((s) => ({
        ...s,
        message:
          'Penyimpanan aman tidak dapat dibersihkan. Hubungi tim penelitian sebelum memakai perangkat bersama.',
      }));
    }
  }
  async function identity(session: Session): Promise<Profile> {
    if (Date.parse(session.expires) <= Date.now())
      throw new ApiError(401, 'Sesi berakhir. Silakan masuk kembali.');
    const me = await request<{ data: { id: string; kind: string } }>('/auth/me', session.token);
    if (me.data.kind !== 'patient') throw new ApiError(403, 'Akun ini bukan akun pasien.');
    const result = await request<{ data: Profile }>(
      `/participants/${encodeURIComponent(me.data.id)}`,
      session.token,
    );
    if (result.data.id !== me.data.id || result.data.status !== 'AKTIF')
      throw new ApiError(403, 'Akses tidak diizinkan.');
    return {
      id: result.data.id,
      status: result.data.status,
      tanggal_pulang: result.data.tanggal_pulang,
    };
  }
  async function restore() {
    // Foreground/retry must not supersede credential exchange, identity checks or persistence.
    if (pendingLogin.current !== null) return;
    const run = ++generation.current;
    setState((s) => ({ ...s, busy: true }));
    try {
      const session = current.current ?? (await stored(() => vault.read()));
      if (run !== generation.current) return;
      if (!session) {
        setState((s) => ({ ...s, ready: true, busy: false }));
        return;
      }
      const profile = await identity(session);
      if (run !== generation.current) return;
      current.current = session;
      setState((s) => ({ ...s, ready: true, busy: false, session, profile, message: '' }));
    } catch (error) {
      if (run !== generation.current) return;
      if (error instanceof ApiError && error.status === 0) {
        setState((s) => ({ ...s, ready: true, busy: false, message: error.message }));
      } else
        await clear(
          error instanceof ApiError
            ? error.message
            : 'Sesi tidak dapat dipulihkan. Silakan masuk kembali.',
        );
    }
  }
  useEffect(() => {
    void restore();
    const network = NetInfo.addEventListener((info) =>
      setState((s) => ({
        ...s,
        // A failed public-internet probe does not imply the configured API is unreachable.
        offline: info.isConnected === false,
      })),
    );
    const active = AppState.addEventListener('change', (next) => {
      if (next === 'active') void restore();
    });
    const pending = generation;
    return () => {
      pending.current++;
      network();
      active.remove();
    };
    // Mount owns restore; mutable session is kept in current, not a stale closure.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);
  useEffect(() => {
    if (!state.session) return;
    const timer = setTimeout(
      () => {
        void clear('Sesi berakhir. Silakan masuk kembali.');
      },
      Math.max(0, Date.parse(state.session.expires) - Date.now()),
    );
    return () => clearTimeout(timer);
  }, [state.session]);
  async function logout() {
    const token = current.current?.token;
    await clear('');
    if (token)
      try {
        await request('/auth/logout', token, {});
      } catch {
        setState((s) => ({
          ...s,
          message:
            'Keluar dari perangkat. Pencabutan sesi di server belum terkonfirmasi; jangan bagikan perangkat sebelum sesi berakhir atau hubungi peneliti.',
        }));
      }
  }
  async function login(code: string, pin: string) {
    const run = ++generation.current;
    pendingLogin.current = run;
    setState((s) => ({ ...s, busy: true, message: '' }));
    let issued: Session | null = null;
    let committed = false;
    try {
      const response = await request<{
        access_token: string;
        expires_at: string;
        token_type: string;
      }>('/auth/patient/login', undefined, { code: code.trim(), pin });
      issued = { token: response.access_token, expires: response.expires_at };
      if (typeof issued.token !== 'string' || !Number.isFinite(Date.parse(issued.expires)))
        throw new Error('Invalid session');
      if (run !== generation.current) return;
      const profile = await identity(issued);
      if (run !== generation.current) return;
      const session = issued;
      await stored(async () => {
        if (run !== generation.current) return;
        await vault.save(session);
        // This cleanup is part of the same queue item as the write.
        if (run !== generation.current) await vault.clear();
      });
      if (run !== generation.current) return;
      if (Date.parse(issued.expires) <= Date.now())
        throw new ApiError(401, 'Sesi berakhir. Silakan masuk kembali.');
      committed = true;
      current.current = issued;
      setState((s) => ({
        ...s,
        session: issued,
        profile,
        acknowledged: false,
        ready: true,
        busy: false,
        message: '',
      }));
    } catch (error) {
      if (run !== generation.current) return;
      await clear(
        error instanceof ApiError
          ? error.message
          : 'Tidak dapat menyimpan sesi dengan aman. Silakan coba lagi.',
      );
    } finally {
      if (pendingLogin.current === run) pendingLogin.current = null;
      if (issued && !committed) await revoke(issued.token);
    }
  }
  async function api<T>(path: string): Promise<T> {
    const session = current.current;
    if (!session || Date.parse(session.expires) <= Date.now()) {
      await clear('Sesi berakhir. Silakan masuk kembali.');
      throw new ApiError(401, 'Sesi berakhir.');
    }
    try {
      return await request<T>(path, session.token);
    } catch (error) {
      if (error instanceof ApiError && [401, 403].includes(error.status))
        await clear('Sesi berakhir atau akses dicabut. Silakan masuk kembali.');
      throw error;
    }
  }
  return (
    <PatientContext.Provider
      value={{
        ...state,
        login,
        logout,
        retry: restore,
        acknowledge: () => setState((s) => ({ ...s, acknowledged: true })),
        api,
      }}
    >
      {children}
    </PatientContext.Provider>
  );
}
