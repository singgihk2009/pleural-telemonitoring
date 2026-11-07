import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, AppState, View } from 'react-native';
import { router } from 'expo-router';
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
    void api.current<{ data: AssessmentStatus }>('/patient/assessments/status').then(result => {
      if (active) { setState(result.data); setError(''); }
    }).catch(failure => { if (active) setError(assessmentError(failure)); })
      .finally(() => { if (active) setLoading(false); });
    return () => { active = false; };
  }, [revision]);
  function reload() {
    if (busyRef.current) return;
    pending.current = null; setAttempt(false); setLoading(true);
    setValue(null); setAnswers({}); setFilledBy(null); setStep('vas'); setState(null);
    setRevision(r => r + 1);
  }
  async function submit() {
    if (busyRef.current || patient.offline || !state?.questionnaire || value === null || !filledBy) return;
    busyRef.current = true; setBusy(true); setError('');
    try {
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
      onBack={busy || attempt ? () => {} : step !== 'vas' ? () => setStep(step === 'confirm' ? 'symptoms' : 'vas') : undefined}>
      {!visible ? <Notice>Isian disembunyikan saat aplikasi tidak aktif.</Notice> : <>
        {error ? <Notice>{error}</Notice> : null}
        {loading ? <ActivityIndicator accessibilityLabel="Memuat assessment" /> : success ? <Card>
          <Heading>Isian berhasil tersimpan</Heading>
          <Copy>Hari {success.study_day} · {success.date_wib}</Copy>
          <Copy>Diterima server: {new Date(success.received_at).toLocaleString('id-ID', { timeZone: 'Asia/Jakarta' })} WIB</Copy>
          <Copy>Ini bukti penyimpanan, bukan hasil penilaian klinis. Tidak ada klasifikasi atau peringatan otomatis.</Copy>
          <Button title="Lihat riwayat" onPress={() => router.replace('/history')} />
        </Card> : !state ? <Button title="Muat ulang status" onPress={reload} /> : state.submitted ? <Card>
          <Heading>Isian hari ini sudah tersimpan</Heading>
          <Copy>Hari {state.submitted.study_day} · {state.submitted.date_wib}</Copy>
          <Button title="Lihat riwayat" onPress={() => router.push('/history')} />
        </Card> : !state.available || !questionnaire ? <Notice>Assessment belum tersedia. Instrumen dan jadwal yang disetujui peneliti belum tersedia dari server.</Notice>
          : !state.can_submit ? <Notice>Pengisian tidak tersedia pada hari penelitian ini.</Notice>
          : step === 'vas' ? <>
            <Copy>Hari {state.study_day} · {state.date_wib} WIB</Copy>
            <VasControl value={value} onChange={setValue} />
            <Button title="Lanjut" disabled={value === null} onPress={() => setStep('symptoms')} />
          </> : step === 'symptoms' ? <>
            <Copy>Versi instrumen: {questionnaire.version}</Copy>
            {questionnaire.questions.map(q => <Card key={q.code}>
              <Heading>{q.label}{q.required ? ' *' : ' (opsional)'}</Heading>
              <View style={{ gap: 12 }}>{q.options.map(option => <Button key={option.id}
                title={`${answers[q.code] === option.id ? '✓ ' : ''}${option.label}`}
                secondary={answers[q.code] !== option.id} onPress={() => setAnswers(a => ({ ...a, [q.code]: option.id }))} />)}</View>
            </Card>)}
            <Card><Heading>Diisi oleh *</Heading>
              <Button title={filledBy === 'PASIEN' ? '✓ Pasien' : 'Pasien'} secondary={filledBy !== 'PASIEN'} onPress={() => setFilledBy('PASIEN')} />
              <Button title={filledBy === 'KELUARGA' ? '✓ Keluarga' : 'Keluarga'} secondary={filledBy !== 'KELUARGA'} onPress={() => setFilledBy('KELUARGA')} />
            </Card>
            {!complete && <Copy>Lengkapi semua isian bertanda *.</Copy>}
            <Button title="Periksa isian" disabled={!complete} onPress={() => setStep('confirm')} />
          </> : <Card>
            <Heading>Periksa sebelum mengirim</Heading>
            <Copy>Hari {state.study_day} · {state.date_wib} · VAS {value} mm · {filledBy}</Copy>
            {questionnaire.questions.map(q => <Copy key={q.code}>{q.label}: {q.options.find(o => o.id === answers[q.code])?.label ?? 'Tidak diisi'}</Copy>)}
            <Copy>Jawaban yang sudah tersimpan tidak dapat diedit. Pengiriman ini tidak menghasilkan keputusan klinis.</Copy>
            {attempt && <Notice>Pengiriman sebelumnya mungkin telah tersimpan. Coba lagi memakai identitas dan isian yang sama. Jika layar ditutup atau sesi berakhir, muat status dan riwayat sebelum mengisi kembali.</Notice>}
            <Button title={busy ? 'Mengirim…' : attempt ? 'Coba kirim lagi dengan isian sama' : 'Kirim isian'} disabled={busy || patient.offline} onPress={() => { void submit(); }} />
            <Button title="Ubah isian" secondary disabled={busy || attempt} onPress={() => setStep('symptoms')} />
            {!busy && attempt && <Button title="Muat ulang status" secondary onPress={reload} />}
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
