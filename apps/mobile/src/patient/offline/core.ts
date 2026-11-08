import type { Assessment, AssessmentStatus, Submission } from '../assessment';
import { ApiError } from '../api';

export type LocalState = 'DRAFT' | 'PENDING_SYNC' | 'SYNCING' | 'SYNCED' | 'RECONCILIATION_REQUIRED' | 'REJECTED';
export type DraftBody = Omit<Submission, 'vas_mm' | 'filled_by'> & { vas_mm: number | null; filled_by: Submission['filled_by'] | null };
export type Record = {
  schema: 1; state: LocalState; snapshot: AssessmentStatus; body: DraftBody;
  createdAt: number; attempts: number; nextAttemptAt: number; uncertain: boolean;
  reason?: string; receipt?: Assessment;
};
export type IO = { read(): Promise<string | null>; write(value: string): Promise<void>; remove(): Promise<void> };
export type Api = <T>(path: string, body?: object) => Promise<T>;
export class ScopeChanged extends Error {}
const locks = new Map<string, Promise<unknown>>();
export async function exclusive<T>(key: string, task: () => Promise<T>): Promise<T> {
  const prior = locks.get(key) ?? Promise.resolve();
  const run = prior.catch(() => {}).then(task);
  locks.set(key, run);
  try { return await run; } finally { if (locks.get(key) === run) locks.delete(key); }
}
const uuid = (value: unknown): value is string => typeof value === 'string' && /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(value);
function check(value: unknown, message = 'Penyimpanan antrean tidak valid. Jangan hapus sebelum rekonsiliasi.'): asserts value {
  if (!value) throw new Error(message);
}
export function decode(raw: string): Record {
  const record = JSON.parse(raw) as Record;
  check(Object.keys(record).every(key => ['schema','state','snapshot','body','createdAt','attempts','nextAttemptAt','uncertain','reason','receipt'].includes(key)));
  check(record.schema === 1 && ['DRAFT','PENDING_SYNC','SYNCING','SYNCED','RECONCILIATION_REQUIRED','REJECTED'].includes(record.state));
  check(record.body && uuid(record.body.submission_id) && typeof record.body.version === 'string');
  check(/^\d{4}-\d{2}-\d{2}$/.test(record.body.date_wib) && Number.isFinite(Date.parse(record.body.device_created_at)));
  check(record.snapshot && Object.keys(record.snapshot).every(key => ['available','can_submit','submission_id','date_wib','study_day','questionnaire','submitted'].includes(key)));
  check(record.snapshot.available === true && record.snapshot.can_submit === true && record.snapshot.submitted === null && record.body.date_wib === record.snapshot.date_wib);
  check(record.snapshot?.questionnaire?.version === record.body.version && record.snapshot.submission_id === record.body.submission_id);
  check(Array.isArray(record.snapshot.questionnaire.questions) && record.snapshot.questionnaire.questions.length > 0 && Array.isArray(record.body.answers));
  check(Object.keys(record.snapshot.questionnaire).every(key => ['version','questions'].includes(key)));
  for (const question of record.snapshot.questionnaire.questions) {
    check(Object.keys(question).every(key => ['code','label','required','options'].includes(key)) && typeof question.code === 'string' && typeof question.label === 'string' && typeof question.required === 'boolean' && Array.isArray(question.options));
    for (const option of question.options) check(Object.keys(option).every(key => ['id','label'].includes(key)) && Number.isSafeInteger(option.id) && typeof option.label === 'string');
  }
  check(typeof record.uncertain === 'boolean');
  check(Number.isFinite(record.createdAt) && Number.isInteger(record.attempts) && record.attempts >= 0 && Number.isFinite(record.nextAttemptAt));
  check(record.body.vas_mm === null || (Number.isInteger(record.body.vas_mm) && record.body.vas_mm >= 0 && record.body.vas_mm <= 100));
  check(record.body.filled_by === null || ['PASIEN','KELUARGA'].includes(record.body.filled_by));
  check(!('token' in record.body) && !('pin' in record.body) && Object.keys(record.body).every(key => ['submission_id','version','date_wib','vas_mm','filled_by','device_created_at','answers'].includes(key)));
  const codes = new Set<string>();
  for (const answer of record.body.answers) {
    check(typeof answer.code === 'string' && !codes.has(answer.code) && Number.isSafeInteger(answer.option_id)); codes.add(answer.code);
    const question = record.snapshot.questionnaire.questions.find(q => q.code === answer.code);
    check(question?.options.some(option => option.id === answer.option_id));
  }
  if (record.state !== 'DRAFT') check(record.body.vas_mm !== null && record.body.filled_by !== null);
  if (record.state === 'SYNCED') {
    check(record.receipt && receiptMatches(record.body, record.receipt));
    check(Object.keys(record.receipt).every(key => ['id','date_wib','study_day','vas_mm','type','filled_by','received_at','device_created_at','version','answers'].includes(key)));
  }
  return record;
}
export function receiptMatches(body: DraftBody, entry: Assessment): boolean {
  const answers = (items: { code: string; option_id: number }[]) => JSON.stringify(items.map(a => [a.code,a.option_id]).sort((a,b) => String(a[0]).localeCompare(String(b[0]))));
  return !!entry && entry.id === body.submission_id && entry.type === 'HARIAN' && entry.date_wib === body.date_wib && entry.version === body.version &&
    Number.isInteger(entry.study_day) && entry.study_day >= 1 && entry.study_day <= 30 && typeof entry.received_at === 'string' && entry.vas_mm === body.vas_mm && entry.filled_by === body.filled_by && Number.isFinite(Date.parse(entry.received_at)) &&
    Date.parse(entry.device_created_at) === Date.parse(body.device_created_at) && Array.isArray(entry.answers) && answers(entry.answers) === answers(body.answers);
}
function canonicalReceipt(entry: Assessment): Assessment {
  return { id: entry.id, date_wib: entry.date_wib, study_day: entry.study_day, vas_mm: entry.vas_mm,
    type: entry.type, filled_by: entry.filled_by, received_at: entry.received_at, device_created_at: entry.device_created_at,
    version: entry.version, answers: entry.answers.map(answer => ({ code: answer.code, option_id: answer.option_id, value: answer.value })) };
}
/** One outstanding item per verified identity/revision; no background/multiprocess workers. */
export class Queue {
  constructor(readonly namespace: string, private io: IO, readonly lockedPredecessors = 0) {}
  private async readUnsafe(): Promise<Record | null> { const raw = await this.io.read(); return raw === null ? null : decode(raw); }
  private async save(record: Record): Promise<Record> {
    const raw = JSON.stringify(record); decode(raw);
    // Conservative UTF-8 upper bound; reject oversized payloads, never use plaintext fallback.
    check(raw.length * 3 <= 16384, 'Isian terlalu besar untuk antrean terenkripsi. Pengiriman dihentikan.');
    await this.io.write(raw);
    check(await this.io.read() === raw, 'Penyimpanan aman belum terverifikasi. Tidak ada isian dikirim.');
    return record;
  }
  read(): Promise<Record | null> { return exclusive(this.namespace, () => this.readUnsafe()); }
  initialize(snapshot: AssessmentStatus, now = Date.now()): Promise<Record> {
    return exclusive(this.namespace, async () => {
      const old = await this.readUnsafe(); if (old) return old;
      check(snapshot.available && snapshot.can_submit && snapshot.questionnaire && uuid(snapshot.submission_id));
      const canonical: AssessmentStatus = { available: snapshot.available, can_submit: snapshot.can_submit,
        submission_id: snapshot.submission_id, date_wib: snapshot.date_wib, study_day: snapshot.study_day, submitted: null,
        questionnaire: { version: snapshot.questionnaire.version, questions: snapshot.questionnaire.questions.map(q => ({
          code: q.code, label: q.label, required: q.required, options: q.options.map(o => ({ id: o.id, label: o.label })),
        })) } };
      return this.save({ schema: 1, state: 'DRAFT', snapshot: canonical, body: {
        submission_id: snapshot.submission_id, version: snapshot.questionnaire.version, date_wib: snapshot.date_wib,
        device_created_at: new Date(now).toISOString(), vas_mm: null, filled_by: null, answers: [],
      }, createdAt: now, attempts: 0, nextAttemptAt: 0, uncertain: false });
    });
  }
  update(patch: Pick<DraftBody,'vas_mm' | 'filled_by' | 'answers'>): Promise<Record> {
    return exclusive(this.namespace, async () => {
      const record = await this.readUnsafe(); check(record?.state === 'DRAFT', 'Isian yang sudah diantrekan tidak boleh diubah.');
      // Never accept identity/date/version/timestamp edits, even through a stale component callback.
      return this.save({ ...record, body: { ...record.body, vas_mm: patch.vas_mm, filled_by: patch.filled_by, answers: patch.answers } });
    });
  }
  confirm(): Promise<Record> {
    return exclusive(this.namespace, async () => {
      const record = await this.readUnsafe(); check(record?.state === 'DRAFT');
      check(record.body.vas_mm !== null && record.body.filled_by !== null && record.snapshot.questionnaire?.questions.every(q => !q.required || record.body.answers.some(a => a.code === q.code)), 'Lengkapi semua isian wajib.');
      return this.save({ ...record, state: 'PENDING_SYNC', attempts: 0, nextAttemptAt: 0 });
    });
  }
  discard(confirmed: boolean): Promise<void> {
    return exclusive(this.namespace, async () => { check(confirmed, 'Pembuangan memerlukan konfirmasi.'); await this.io.remove(); check(await this.io.read() === null, 'Penghapusan aman belum terkonfirmasi.'); });
  }
  discardDraft(confirmed: boolean, submissionId: string): Promise<void> {
    return exclusive(this.namespace, async () => {
      check(confirmed, 'Pembuangan draf memerlukan konfirmasi.');
      const record = await this.readUnsafe();
      check(record?.state === 'DRAFT' && record.body.submission_id === submissionId,
        'Draf berubah atau mungkin telah dikirim. Periksa rekonsiliasi; data tidak dibuang.');
      await this.io.remove();
      check(await this.io.read() === null, 'Penghapusan draf belum terkonfirmasi.');
    });
  }
  async synchronize(api: Api, owner: { id: string; revision: string }, current: () => boolean, manual = false, now = Date.now()): Promise<Record | null> {
    return exclusive(this.namespace, async () => {
      let record = await this.readUnsafe();
      if (!record || ['DRAFT','SYNCED'].includes(record.state)) return record;
      const quarantine = ['REJECTED','RECONCILIATION_REQUIRED'].includes(record.state) ? record.state : null;
      // Quarantine is read-only even after a failed manual GET, restart or re-login.
      if (quarantine && !manual) return record;
      if (!current()) throw new ScopeChanged();
      if (record.nextAttemptAt > now || (!manual && (record.attempts >= 5 || record.state === 'RECONCILIATION_REQUIRED'))) return record;
      const guard = () => { if (!current()) throw new ScopeChanged(); };
      const retain = async (state: LocalState, reason: string) => { guard(); record = await this.save({ ...record!, state, reason }); return record; };
      try {
        const identity = await api<{ data: { id: string; kind: string; credential_revision?: string } }>('/auth/me'); guard();
        check(identity.data.kind === 'patient' && identity.data.id === owner.id && identity.data.credential_revision === owner.revision, 'Identitas sesi berubah.');
        const status = await api<{ data: AssessmentStatus }>('/patient/assessments/status'); guard();
        const history = await api<{ data: Assessment[]; meta: { total: number } }>(`/patient/assessments?submission_id=${encodeURIComponent(record.body.submission_id)}`); guard();
        const found = history.data.find(entry => entry.id === record!.body.submission_id);
        if (found) {
          if (!receiptMatches(record.body, found)) return retain('RECONCILIATION_REQUIRED', 'RECEIPT_MISMATCH');
          return this.save({ ...record, state: 'SYNCED', receipt: canonicalReceipt(found), reason: 'SERVER_HISTORY', nextAttemptAt: 0 });
        }
        // Never redate, replace a body or activate late submission to make an item eligible.
        if (now - record.createdAt > 7 * 86400000) return retain('RECONCILIATION_REQUIRED', 'RETENTION_REVIEW');
        if (!status.data.available || !status.data.can_submit || status.data.date_wib !== record.body.date_wib || status.data.questionnaire?.version !== record.body.version)
          return retain('RECONCILIATION_REQUIRED', 'VERSION_DATE_OR_STATUS_CHANGED');
        if (['RECONCILIATION_REQUIRED','REJECTED'].includes(record.state)) return record; // Manual reconciliation reads only.
        record = await this.save({ ...record, state: 'SYNCING', uncertain: true, attempts: manual && record.attempts >= 5 ? 1 : record.attempts + 1 }); guard();
        const response = await api<{ data: Assessment; replayed: boolean }>('/patient/assessments', record.body); guard();
        if (typeof response.replayed !== 'boolean' || !receiptMatches(record.body, response.data)) return retain('RECONCILIATION_REQUIRED', 'RECEIPT_MISMATCH');
        return this.save({ ...record, state: 'SYNCED', receipt: canonicalReceipt(response.data), reason: 'SERVER_RECEIPT', nextAttemptAt: 0 });
      } catch (error) {
        if (error instanceof ScopeChanged || !current()) throw new ScopeChanged();
        // Preserve an uncertain SYNCING marker on storage failure; never send around a failed write.
        const status = error instanceof ApiError ? error.status : -1;
        if (status === 409) return retain(quarantine ?? 'RECONCILIATION_REQUIRED', 'CONFLICT_CHECK_HISTORY');
        if (status === 422) return retain(quarantine ?? 'REJECTED', 'VALIDATION_REVIEW');
        if (status === 401 || status === 403) return retain(quarantine ?? 'PENDING_SYNC', 'AUTH_REQUIRED');
        if (status === 0 || status === 429 || status >= 500) {
          const attempts = Math.max(1, record.attempts + (record.state === 'SYNCING' ? 0 : 1));
          const delay = Math.max(Math.min(300000, 2000 * 2 ** Math.min(attempts, 8)), error instanceof ApiError ? error.retryAfterMs : 0);
          return this.save({ ...record, state: quarantine ?? 'PENDING_SYNC', attempts, nextAttemptAt: Math.max(now, Date.now()) + delay, reason: quarantine ? 'RECONCILIATION_READ_FAILED' : 'DELIVERY_UNCERTAIN' });
        }
        throw new Error('Antrean terkunci: penyimpanan atau identitas belum terverifikasi. Data tidak dihapus.');
      }
    });
  }
}
