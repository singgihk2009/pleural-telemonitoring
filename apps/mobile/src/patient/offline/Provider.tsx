import { OfflineContext } from './context';
import { useEffect, useRef, useState, useCallback, type PropsWithChildren } from 'react';
import { AppState } from 'react-native';
import { usePatient } from '../context';
import { request } from '../api';
import { openQueue } from './storage';
import { ScopeChanged, type Queue, type Record, type Api } from './core';

export function OfflineProvider({ children }: PropsWithChildren) {
  const patient = usePatient();
  const id = patient.profile?.id; const revision = patient.profile?.credential_revision;
  const available = !!(patient.session && id && revision);
  const scope = available ? `${id}:${revision}:${patient.session!.token}` : 'locked'; // Memory only; never persisted.
  const scopeRef = useRef(scope);
  const [active, setActive] = useState(AppState.currentState === 'active');
  const activeRef = useRef(active);
  const [loaded, setLoaded] = useState<{ scope: string; queue?: Queue; record?: Record | null; error?: string } | null>(null);
  const [writing, setWriting] = useState(0); const writers = useRef(0);
  const [syncing, setSyncing] = useState(false); const running = useRef(false);
  const queue = loaded?.scope === scope ? loaded.queue : undefined;
  const record = loaded?.scope === scope ? loaded.record ?? null : null;
  const error = loaded?.scope === scope ? loaded.error ?? '' : '';
  useEffect(() => { scopeRef.current = scope; }, [scope]);
  useEffect(() => {
    const listener = AppState.addEventListener('change', state => { activeRef.current = state === 'active'; setActive(activeRef.current); });
    return () => { activeRef.current = false; listener.remove(); };
  }, []);
  useEffect(() => {
    if (!available || !id || !revision) return;
    let live = true;
    void openQueue(id, revision).then(async store => ({ store, value: await store.read() })).then(({ store, value }) => {
      if (live) setLoaded({ scope, queue: store, record: value });
    }).catch(() => { if (live) setLoaded({ scope, error: 'Antrean terenkripsi belum dapat dibuka. Data tidak dihapus; pengiriman dihentikan.' }); });
    return () => { live = false; };
  }, [available, id, revision, scope]);
  async function change(operation: (store: Queue) => Promise<Record | undefined>): Promise<Record | undefined> {
    if (!queue || scopeRef.current !== scope) return;
    writers.current++; setWriting(writers.current);
    try {
      const value = await operation(queue);
      if (scopeRef.current === scope) setLoaded({ scope, queue, record: value });
      return value;
    } catch {
      if (scopeRef.current === scope) setLoaded(previous => ({ ...previous!, scope, queue, error: 'Penyimpanan aman belum terverifikasi. Isian tidak dikirim. Jangan buang data sebelum rekonsiliasi.' }));
      return undefined;
    } finally { writers.current--; setWriting(writers.current); }
  }
  const synchronize = useCallback(async (manual = false) => {
    if (!queue || !id || !revision || !patient.session || !patient.acknowledged || patient.offline || !active || running.current || writers.current || error) return;
    running.current = true; setSyncing(true);
    const captured = scope; const token = patient.session.token;
    const current = () => scopeRef.current === captured && activeRef.current;
    // Always bind to the captured token. A new login can never send an old body's data.
    const api: Api = (path, body) => { if (!current()) return Promise.reject(new ScopeChanged()); return request(path, token, body); };
    try {
      const value = await queue.synchronize(api, { id, revision }, current, manual);
      if (current()) {
        setLoaded({ scope, queue, record: value });
        if (value?.reason === 'AUTH_REQUIRED') await patient.logout();
      }
    } catch (failure) {
      if (current() && !(failure instanceof ScopeChanged)) setLoaded(previous => ({ ...previous!, scope, queue, error: 'Antrean terkunci untuk pemeriksaan penyimpanan atau sesi. Data lokal dipertahankan.' }));
    } finally { running.current = false; setSyncing(false); }
  }, [queue, id, revision, patient, active, error, scope]);
  const localState = record?.state; const attempts = record?.attempts ?? 0;
  const nextAttempt = record?.nextAttemptAt; const reason = record?.reason;
  useEffect(() => {
    if (!active || !localState || !['PENDING_SYNC','SYNCING'].includes(localState) || attempts >= 5 || reason === 'AUTH_REQUIRED') return;
    void synchronize();
    const timer = setInterval(() => { void synchronize(); }, 5000);
    return () => clearInterval(timer);
  }, [active, localState, attempts, nextAttempt, reason, synchronize]);
  return <OfflineContext.Provider value={{ available, enabled: !!queue, loading: available && loaded?.scope !== scope,
    scope, record, lockedPredecessors: queue?.lockedPredecessors ?? 0, error, syncing, writing: writing > 0,
    initialize: snapshot => change(store => store.initialize(snapshot)),
    update: body => change(store => store.update(body)), confirm: () => change(store => store.confirm()),
    discardDraft: async submissionId => {
      if (!queue || scopeRef.current !== scope) return false;
      writers.current++; setWriting(writers.current);
      try {
        await queue.discardDraft(true, submissionId);
        if (scopeRef.current !== scope) return false;
        setLoaded({ scope, queue, record: null });
        return true;
      } catch {
        if (scopeRef.current === scope) setLoaded(previous => ({ ...previous!, error: 'Pembuangan draf belum terkonfirmasi atau status berubah. Periksa rekonsiliasi; data tidak dianggap terhapus.' }));
        return false;
      } finally { writers.current--; setWriting(writers.current); }
    },
    discard: async confirmed => {
      if (!queue || scopeRef.current !== scope) return;
      try { await queue.discard(confirmed); if (scopeRef.current === scope) setLoaded({ scope, queue, record: null }); }
      catch { if (scopeRef.current === scope) setLoaded(previous => ({ ...previous!, error: 'Pembuangan lokal belum terkonfirmasi. Data tidak dianggap terhapus.' })); }
    }, synchronize,
  }}>{children}</OfflineContext.Provider>;
}
