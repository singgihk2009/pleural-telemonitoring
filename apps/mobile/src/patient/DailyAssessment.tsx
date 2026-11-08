import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Alert, AppState, View } from 'react-native';
import { router } from 'expo-router';
import { stateText, reasonText } from './offline/labels';
import { useOfflineQueue } from './offline/context';
import { usePatient } from './context';
import { Button, Card, Copy, Frame, Heading, Notice } from './ui';
import { VasControl } from './VasControl';
import { styles } from './theme';
import { assessmentError, type Assessment, type AssessmentStatus, type Submission } from './assessment';

export function DailyAssessment() {
  const patient = usePatient();
  return <DailyForm key={patient.session?.token ?? 'signed-out'} />;
}
function DailyForm() {
  const patient = usePatient();
  const durable = useOfflineQueue();
  const form = useRef<{ value: number | null; filledBy: 'PASIEN' | 'KELUARGA' | null; answers: Record<string, number> }>({ value: null, filledBy: null, answers: {} });
  const api = useRef(patient.api);
  useEffect(() => { api.current = patient.api; }, [patient.api]);
  const mounted = useRef(true);
  const busyRef = useRef(false);
  const pending = useRef<Submission | null>(null);
  const [attempt, setAttempt] = useState(false);
  const [state, setState] = useState<AssessmentStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [value, setValue] = useState<number | null>(null);
  const [answers, setAnswers] = useState<Record<string, number>>({});
  const [filledBy, setFilledBy] = useState<'PASIEN' | 'KELUARGA' | null>(null);
  const [step, setStep] = useState<'vas' | 'symptoms' | 'confirm'>('vas');
  const [success, setSuccess] = useState<Assessment | null>(null);
  const [revision, setRevision] = useState(0);
  const [visible, setVisible] = useState(AppState.currentState !== 'background');
  useEffect(() => {
    mounted.current = true;
    const listener = AppState.addEventListener('change', s => setVisible(s === 'active'));
    return () => { mounted.current = false; pending.current = null; listener.remove(); };
  }, []);
  useEffect(() => {
    let active = true;
    if (durable?.loading) return;
    if (durable?.available && !durable.enabled) {
      setLoading(false); setError(durable.error || 'Penyimpanan terenkripsi belum tersedia; pengiriman dihentikan.');
      return;
    }
    const saved = durable?.record;
    if (saved) {
      const fields = { value: saved.body.vas_mm, filledBy: saved.body.filled_by,
        answers: Object.fromEntries(saved.body.answers.map(a => [a.code, a.option_id])) };
      form.current = fields; setValue(fields.value); setFilledBy(fields.filledBy); setAnswers(fields.answers);
      setState(saved.snapshot); setLoading(false);
      if (saved.state === 'SYNCED' && saved.receipt) setSuccess(saved.receipt);
      else { setSuccess(null); setAttempt(saved.state !== 'DRAFT'); if (saved.state !== 'DRAFT') setStep('confirm'); }
      return;
    }
    void api.current<{ data: AssessmentStatus }>('/patient/assessments/status').then(async result => {
      if (!active) return;
      if (durable?.enabled && result.data.available && result.data.can_submit) {
        const saved = await durable.initialize(result.data);
        if (!saved || !active) return;
        setState(saved.snapshot);
      } else setState(result.data);
      if (active) setError('');
    }).catch(failure => { if (active) setError(assessmentError(failure)); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
    // Record body updates are serialized by the queue; only restore at lifecycle/state boundaries.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [revision, durable?.loading, durable?.enabled, durable?.scope, durable?.record?.state, durable?.record?.body.submission_id]);
  function change(patch: Partial<typeof form.current>) {
    const next = { ...form.current, ...patch }; form.current = next;
    setValue(next.value); setFilledBy(next.filledBy); setAnswers(next.answers);
    if (durable?.enabled) void durable.update({ vas_mm: next.value, filled_by: next.filledBy,
      answers: Object.entries(next.answers).map(([code, option_id]) => ({ code, option_id })) });
  }
  function discardDraft() {
    const saved = durable?.record;
    if (!saved || saved.state !== 'DRAFT' || durable.writing || durable.syncing || busyRef.current) return;
    const id = saved.body.submission_id;
    Alert.alert('Buang draf yang belum dikirim?', `Draf tanggal ${saved.body.date_wib} WIB belum diantrekan. Membuangnya menghilangkan jawaban lokal yang belum dikirim dan tidak dapat dipulihkan. Setelah pembuangan terverifikasi, aplikasi memeriksa tanggal, instrumen, dan kelayakan terbaru dari server.`, [
      { text: 'Batal', style: 'cancel' }, { text: 'Buang draf', style: 'destructive', onPress: () => {
        void durable.discardDraft(id).then(removed => {
          if (!removed || !mounted.current) return;
          form.current = { value: null, filledBy: null, answers: {} };
          pending.current = null; setSuccess(null); setAttempt(false); setValue(null); setAnswers({}); setFilledBy(null);
          setState(null); setStep('vas'); setError(''); setLoading(true); setRevision(r => r + 1);
        });
      } },
    ]);
  }
  function discard() {
    Alert.alert('Buang isian lokal?', 'Isian yang belum tersinkron tidak dapat dipulihkan. Ini tidak membatalkan permintaan yang mungkin sudah tersimpan di server. Periksa riwayat terlebih dahulu.', [
      { text: 'Batal', style: 'cancel' }, { text: 'Buang lokal', style: 'destructive', onPress: () => {
        void durable?.discard(true).then(() => { setSuccess(null); setAttempt(false); setValue(null); setAnswers({}); setFilledBy(null); setState(null); setStep('vas'); setLoading(true); setRevision(r => r + 1); });
      } },
    ]);
  }
  function reload() {
    if (busyRef.current) return;
    if (durable?.record && durable.record.state !== 'DRAFT') { void durable.synchronize(true); return; }
    pending.current = null; setAttempt(false); setLoading(true);
    setValue(null); setAnswers({}); setFilledBy(null); setStep('vas'); setState(null);
    setRevision(r => r + 1);
  }
  async function submit() {
    if (busyRef.current || (patient.offline && !durable?.enabled) || !state?.questionnaire || value === null || !filledBy) return;
    busyRef.current = true; setBusy(true); setError('');
    try {
      if (durable?.enabled) {
        const queued = await durable.confirm();
        if (queued) { setAttempt(true); await durable.synchronize(true); }
        return;
      }
      // A stable UUID and immutable body survive timeouts while this screen remains mounted.
      pending.current ??= {
        submission_id: state.submission_id, version: state.questionnaire.version,
        date_wib: state.date_wib, vas_mm: value, filled_by: filledBy,
        device_created_at: new Date().toISOString(),
        answers: Object.entries(answers).map(([code, option_id]) => ({ code, option_id })),
      };
      setAttempt(true);
      const result = await api.current<{ data: Assessment; replayed: boolean }>('/patient/assessments', pending.current);
      if (mounted.current) {
        setSuccess(result.data); setValue(null); setAnswers({}); setFilledBy(null); pending.current = null; setAttempt(false);
      }
    } catch (failure) {
      if (mounted.current) setError(assessmentError(failure));
    } finally {
      busyRef.current = false;
      if (mounted.current) setBusy(false);
    }
  }
  const questionnaire = state?.questionnaire;
  const complete = filledBy !== null && questionnaire?.questions.every(q => !q.required || answers[q.code] !== undefined);
  return (
    <Frame title={success ? 'Terkirim' : step === 'vas' ? 'Isi harian' : step === 'confirm' ? 'Konfirmasi isian' : 'Gejala hari ini'}
      step="F3 · ISIAN HARIAN"
      onBack={durable?.enabled && attempt ? () => router.replace('/') : busy || attempt ? () => {} : step !== 'vas' ? () => setStep(step === 'confirm' ? 'symptoms' : 'vas') : undefined}>
      {!visible ? <Notice>Isian disembunyikan saat aplikasi tidak aktif.</Notice> : <>
        {(durable?.error || error) ? <Notice>{durable?.error || error}</Notice> : null}
        {!!durable?.lockedPredecessors && <Notice>Ada salinan lokal dari kredensial sebelumnya yang tetap terkunci. Periksa riwayat server dan kebijakan pemulihan bersama tim penelitian. Tidak ada isian lama yang dihapus otomatis.</Notice>}
        {durable && !durable.available && <Notice>Server atau kredensial ini belum mendukung antrean terenkripsi. Gunakan hanya saat online; isian belum tersimpan sampai ada tanda terima server.</Notice>}
        {durable?.enabled && <Notice>{durable.writing ? 'Menyimpan perubahan ke penyimpanan aman. Tunggu sebelum menutup aplikasi.' : !durable.record ? 'Penyimpanan terenkripsi siap. Belum ada draf lokal.' : durable.record.state === 'SYNCED' ? 'Tanda terima server diverifikasi.' : 'Draf/antrean disimpan terenkripsi pada perangkat. Belum tersimpan di server sampai ada tanda terima. Validitas versi dan tanggal diperiksa saat sinkronisasi.'}</Notice>}
        {durable?.record?.state === 'DRAFT' && <Card>
          <Copy>Draf belum dikirim · tanggal {durable.record.body.date_wib} WIB.</Copy>
          <Copy>Jika tanggal telah berganti, buang draf dengan konfirmasi untuk memeriksa pengisian hari ini. Jawaban lokal yang belum dikirim akan hilang; tanggal dan identitas draf tidak diubah otomatis.</Copy>
          <Button title="Buang draf belum dikirim…" secondary disabled={busy || durable.writing || durable.syncing} onPress={discardDraft} />
        </Card>}
        {durable?.record && !['DRAFT','SYNCED'].includes(durable.record.state) && <Card>
          <Copy>{stateText[durable.record.state]}. {reasonText[durable.record.reason ?? ''] ?? ''}</Copy>
          <Copy>Permintaan lama tidak diubah. Isian terlambat/versi berubah perlu rekonsiliasi, bukan perubahan tanggal otomatis.</Copy>
          {durable.record.nextAttemptAt > 0 && <Copy>Percobaan berikutnya paling cepat {new Date(durable.record.nextAttemptAt).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })} WIB. Setelah lima percobaan otomatis, periksa kembali secara manual.</Copy>}
          <Button title="Periksa server / sinkronkan" secondary disabled={patient.offline || durable.syncing || durable.writing} onPress={() => { void durable.synchronize(true); }} />
          <Button title="Buang isian lokal…" secondary disabled={durable.syncing || durable.writing} onPress={discard} />
        </Card>}
        {loading ? <ActivityIndicator accessibilityLabel="Memuat assessment" /> : success ? <Card>
          <Heading>Isian berhasil tersimpan</Heading>
          <Copy>Hari {success.study_day} · {success.date_wib}</Copy>
          <Copy>Diterima server: {new Date(success.received_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })} WIB</Copy>
          <Copy>Ini bukti penyimpanan, bukan hasil penilaian klinis. Tidak ada klasifikasi atau peringatan otomatis.</Copy>
          <Button title="Lihat riwayat" onPress={() => router.replace('/history')} />
          {durable?.enabled && <Button title="Mulai isian baru / periksa tanggal server" secondary onPress={discard} />}
        </Card> : !state ? <Button title="Muat ulang status" onPress={reload} /> : state.submitted ? <Card>
          <Heading>Isian hari ini sudah tersimpan</Heading>
          <Copy>Hari {state.submitted.study_day} · {state.submitted.date_wib}</Copy>
          <Button title="Lihat riwayat" onPress={() => router.push('/history')} />
        </Card> : !state.available || !questionnaire ? <Notice>Assessment belum tersedia. Instrumen dan jadwal yang disetujui peneliti belum tersedia dari server.</Notice>
          : !state.can_submit ? <Notice>Pengisian tidak tersedia pada hari penelitian ini.</Notice>
          : step === 'vas' ? <>
            <Copy>Hari {state.study_day} · {state.date_wib} WIB</Copy>
            <VasControl value={value} onChange={value => change({ value })} />
            <Button title="Lanjut" disabled={value === null} onPress={() => setStep('symptoms')} />
          </> : step === 'symptoms' ? <>
            <Copy>Versi instrumen: {questionnaire.version}</Copy>
            {questionnaire.questions.map(q => <Card key={q.code}>
              <Heading>{q.label}{q.required ? ' *' : ' (opsional)'}</Heading>
              <View style={{ gap: 12 }}>{q.options.map(option => <Button key={option.id}
                title={`${answers[q.code] === option.id ? '✓ ' : ''}${option.label}`}
                secondary={answers[q.code] !== option.id} onPress={() => change({ answers: { ...form.current.answers, [q.code]: option.id } })} />)}</View>
            </Card>)}
            <Card><Heading>Diisi oleh *</Heading>
              <Button title={filledBy === 'PASIEN' ? '✓ Pasien' : 'Pasien'} secondary={filledBy !== 'PASIEN'} onPress={() => change({ filledBy: 'PASIEN' })} />
              <Button title={filledBy === 'KELUARGA' ? '✓ Keluarga' : 'Keluarga'} secondary={filledBy !== 'KELUARGA'} onPress={() => change({ filledBy: 'KELUARGA' })} />
            </Card>
            {!complete && <Copy>Lengkapi semua isian bertanda *.</Copy>}
            <Button title="Periksa isian" disabled={!complete || durable?.writing || !!durable?.error} onPress={() => setStep('confirm')} />
          </> : <Card>
            <Heading>Periksa sebelum mengirim</Heading>
            <Copy>Hari {state.study_day} · {state.date_wib} · VAS {value} mm · {filledBy}</Copy>
            {questionnaire.questions.map(q => <Copy key={q.code}>{q.label}: {q.options.find(o => o.id === answers[q.code])?.label ?? 'Tidak diisi'}</Copy>)}
            <Copy>Jawaban yang sudah tersimpan tidak dapat diedit. Pengiriman ini tidak menghasilkan keputusan klinis.</Copy>
            {attempt && !durable?.enabled && <Notice>Pengiriman sebelumnya mungkin telah tersimpan. Coba lagi memakai identitas dan isian yang sama. Jika layar ditutup atau sesi berakhir, muat status dan riwayat sebelum mengisi kembali.</Notice>}
            <Button title={durable?.enabled ? 'Simpan aman dan antrekan isian' : busy ? 'Mengirim…' : attempt ? 'Coba kirim lagi dengan isian sama' : 'Kirim isian'} disabled={busy || durable?.syncing || durable?.writing || !!durable?.error || (patient.offline && !durable?.enabled) || (durable?.enabled && attempt)} onPress={() => { void submit(); }} />
            <Button title="Ubah isian" secondary disabled={busy || attempt} onPress={() => setStep('symptoms')} />
            {!busy && attempt && !durable?.enabled && <Button title="Muat ulang status" secondary onPress={reload} />}
          </Card>}
        {busy && <Notice>Menunggu server. Jangan tutup layar. Menutup aplikasi tidak membatalkan penyimpanan server.</Notice>}
      </>}
    </Frame>
  );
}

export function AssessmentHistory() {
  const patient = usePatient();
  return <History key={patient.session?.token ?? 'signed-out'} />;
}
function History() {
  const patient = usePatient();
  const api = useRef(patient.api);
  useEffect(() => { api.current = patient.api; }, [patient.api]);
  const [page, setPage] = useState(1);
  const [retry, setRetry] = useState(0);
  const loadKey = `${page}:${retry}`;
  const [loaded, setLoaded] = useState<{ key: string; result?: { data: Assessment[]; meta: { last_page: number } }; error?: string } | null>(null);
  const result = loaded?.key === loadKey ? loaded.result : null;
  const error = loaded?.key === loadKey ? loaded.error : '';
  useEffect(() => {
    let active = true;
    void api.current<{ data: Assessment[]; meta: { last_page: number } }>(`/patient/assessments?page=${page}`)
      .then(data => { if (active) setLoaded({ key: loadKey, result: data }); })
      .catch(failure => { if (active) setLoaded({ key: loadKey, error: assessmentError(failure) }); });
    return () => { active = false; };
  }, [page, loadKey]);
  return <Frame title="Riwayat 30 hari" step="F9 · RIWAYAT">
    {error ? <><Notice>{error}</Notice><Button title="Coba muat riwayat" onPress={() => setRetry(r => r + 1)} /></>
      : !result ? <ActivityIndicator accessibilityLabel="Memuat riwayat" /> : <>
        {result.data.length === 0 && <Notice>Belum ada isian tersimpan.</Notice>}
        {result.data.map(entry => <Card key={entry.id}>
          <Heading>Hari {entry.study_day} · {entry.date_wib}</Heading>
          <Copy>VAS {entry.vas_mm} mm · {entry.filled_by}</Copy>
          <Copy style={styles.muted}>Diterima {new Date(entry.received_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })} WIB</Copy>
          <Copy>Versi: {entry.version ?? 'Catatan sebelumnya'}</Copy>
          {entry.answers.map(answer => <Copy key={answer.code}>{answer.code}: {answer.value}</Copy>)}
        </Card>)}
        <Button title="Halaman sebelumnya" secondary disabled={page <= 1} onPress={() => setPage(p => p - 1)} />
        <Button title="Halaman berikutnya" secondary disabled={page >= result.meta.last_page} onPress={() => setPage(p => p + 1)} />
        <Copy>Tidak ada klasifikasi atau skor yang dihitung.</Copy>
      </>}
  </Frame>;
}
