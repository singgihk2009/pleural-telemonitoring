import { DailyAssessment, AssessmentHistory } from './DailyAssessment';
export { VasControl } from './VasControl';
import { useEffect, useState, type PropsWithChildren } from 'react';
import { ActivityIndicator, Pressable, StyleSheet, TextInput, View } from 'react-native';
import { Redirect, router } from 'expo-router';
import { usePatient } from './context';
import { studyDay } from './api';
import { Button, Card, Copy, Frame, Heading, Notice } from './ui';
import { colors, styles } from './theme';

function useDay() {
  const { profile } = usePatient();
  const [now, setNow] = useState(() => new Date());
  useEffect(() => {
    const id = setInterval(() => setNow(new Date()), 30000);
    return () => clearInterval(id);
  }, []);
  return profile ? studyDay(profile.tanggal_pulang, now) : null;
}
export function Protected({ children, final = false }: PropsWithChildren<{ final?: boolean }>) {
  const session = usePatient();
  const day = useDay();
  if (!session.ready) return <Loading />;
  if (!session.session) return <Redirect href="/" />;
  if (!session.acknowledged) return <Redirect href="/acknowledgement" />;
  if (day !== null && day > 30 && !final) return <Redirect href="/" />;
  return <>{children}</>;
}
function Loading() {
  return (
    <Frame title="Menyiapkan TeleNapas" back={false}>
      <ActivityIndicator accessibilityLabel="Memeriksa sesi" />
      <Copy>Memeriksa sesi dengan aman…</Copy>
    </Frame>
  );
}

export function EntryScreen() {
  const session = usePatient();
  if (!session.ready) return <Loading />;
  if (!session.session) return <Login />;
  if (!session.acknowledged) return <Redirect href="/acknowledgement" />;
  return <Home />;
}
function Login() {
  const session = usePatient();
  const [code, setCode] = useState('');
  const [pin, setPin] = useState('');
  const [error, setError] = useState('');
  async function submit() {
    if (!code.trim() || code.trim().length > 32 || !/^[0-9]{6}$/.test(pin)) {
      setError('Isi kode responden dan PIN tepat 6 digit.');
      return;
    }
    setError('');
    const secret = pin;
    setPin('');
    await session.login(code, secret);
  }
  return (
    <Frame title="Selamat datang" step="F1 · MASUK" back={false}>
      <Copy style={styles.muted}>Masuk dengan akun yang diberikan tim penelitian.</Copy>
      {session.message ? <Notice>{session.message}</Notice> : null}
      <Card>
        <View>
          <Copy style={styles.label}>Kode responden</Copy>
          <TextInput
            accessibilityLabel="Kode responden"
            autoCapitalize="none"
            autoCorrect={false}
            maxLength={32}
            value={code}
            onChangeText={setCode}
            editable={!session.busy}
            style={styles.input}
            placeholder="Masukkan kode Anda"
          />
        </View>
        <View>
          <Copy style={styles.label}>PIN 6 digit</Copy>
          <TextInput
            accessibilityLabel="PIN 6 digit"
            secureTextEntry
            keyboardType="number-pad"
            maxLength={6}
            value={pin}
            onChangeText={setPin}
            editable={!session.busy}
            style={styles.input}
            placeholder="••••••"
            autoComplete="off"
          />
        </View>
        {error ? (
          <Copy accessibilityRole="alert" style={{ color: colors.red }}>
            {error}
          </Copy>
        ) : null}
        <Button
          title={session.busy ? 'Memeriksa…' : 'Masuk'}
          disabled={session.busy || session.offline}
          onPress={() => {
            void submit();
          }}
        />
        <Copy style={styles.muted}>
          Tidak ada pendaftaran mandiri. Percobaan PIN dibatasi. Akun dibuat peneliti.
        </Copy>
      </Card>
      <Button
        title="Periksa koneksi dan pulihkan sesi"
        secondary
        disabled={session.busy || session.offline}
        onPress={() => {
          void session.retry();
        }}
      />
      <Copy style={styles.muted}>Aplikasi penelitian Rumah Sakit Sidawangi.</Copy>
    </Frame>
  );
}
export function AcknowledgementScreen() {
  const session = usePatient();
  const [checked, setChecked] = useState(false);
  if (!session.ready) return <Loading />;
  if (!session.session) return <Redirect href="/" />;
  if (session.acknowledged) return <Redirect href="/" />;
  return (
    <Frame title="Pernyataan awal" step="F2 · SEBELUM MULAI" back={false}>
      <Card style={{ backgroundColor: '#FFF2CF', borderColor: '#D6AF47' }}>
        <Copy style={{ fontSize: 36, lineHeight: 44, fontWeight: '800', textAlign: 'center' }}>
          !
        </Copy>
        <Heading>
          Aplikasi ini bukan pengganti IGD. Bila sesak berat, segera ke IGD atau telepon 119.
        </Heading>
      </Card>
      <Pressable
        accessibilityRole="checkbox"
        accessibilityLabel="Saya sudah membaca pernyataan ini"
        accessibilityState={{ checked }}
        onPress={() => setChecked(!checked)}
        style={local.check}
      >
        <Copy style={{ fontSize: 24 }}>{checked ? '☑' : '☐'}</Copy>
        <Copy style={{ flex: 1 }}>Saya sudah membaca pernyataan ini</Copy>
      </Pressable>
      <Button title="Lanjut" disabled={!checked} onPress={() => session.acknowledge()} />
      <Copy style={styles.muted}>
        Persetujuan tertulis tetap di kertas. Konfirmasi ini berlaku selama sesi ini; pencatatan
        versi dan waktu di server belum tersedia.
      </Copy>
    </Frame>
  );
}
function Home() {
  const session = usePatient();
  const day = useDay();
  const closed = day !== null && day > 30;
  return (
    <Frame title={closed ? 'Pemantauan selesai' : 'Beranda'} step="PEMANTAUAN ANDA" back={false}>
      <Card style={{ backgroundColor: '#E6EFFA' }}>
        <Heading>
          {day === null
            ? 'Hari penelitian belum tersedia'
            : day < 0
              ? 'Pemantauan belum dimulai'
              : closed
                ? 'Masa pemantauan 30 hari sudah selesai'
                : `Hari ${day} dari 30`}
        </Heading>
        <Copy>
          {closed
            ? 'Isian harian ditutup. Riwayat tersimpan dan formulir akhir dapat dibuka.'
            : 'Hari 0 adalah tanggal pulang. Pergantian hari mengikuti WIB.'}
        </Copy>
      </Card>
      {!closed && (
        <>
          <Button
            title="Isi tingkat sesak hari ini"
            disabled={day === null || day < 1 || day > 30}
            onPress={() => router.push('/daily')}
          />
          <Copy style={styles.muted}>
            Formulir memuat instrumen dan status pengisian yang disetujui dari server.
          </Copy>
          <Notice>
            Buka isian harian untuk memeriksa status server. Riwayat menampilkan isian yang sudah tersimpan.
          </Notice>
          <View style={local.grid}>
            {(
              [
                { name: 'Riwayat', path: '/history', icon: '01' },
                { name: 'Edukasi', path: '/education', icon: '02' },
                { name: 'Lapor kejadian', path: '/event', icon: '03' },
                { name: 'Pesan', path: '/contact', icon: '04' },
              ] as const
            ).map((item) => (
              <Pressable
                key={item.path}
                accessibilityRole="button"
                onPress={() => router.push(item.path)}
                style={local.tile}
              >
                <Copy style={local.tileNumber}>{item.icon}</Copy>
                <Copy style={styles.sectionTitle}>{item.name}</Copy>
                <Copy style={{ color: colors.blue }}>Buka →</Copy>
              </Pressable>
            ))}
          </View>
          <Button
            title="VAS ulang · belum tersedia"
            secondary
            onPress={() => router.push('/repeat')}
          />
        </>
      )}
      {closed && <Button title="Lihat riwayat tersimpan" secondary onPress={() => router.push('/history')} />}
      <Button
        title="Buka formulir akhir"
        secondary
        disabled={day === null || day < 30}
        onPress={() => router.push('/final')}
      />
      <Button
        title="Keluar"
        secondary
        onPress={() => {
          void session.logout();
        }}
      />
    </Frame>
  );
}
export function DailyScreen() {
  return (
    <Protected>
      <DailyAssessment />
    </Protected>
  );
}
const illustrativeSymptoms = [
  ['Sesak saat istirahat', ['Tidak', 'Ya']],
  ['Nyeri dada', ['Ringan', 'Hebat']],
  ['Bibir/ujung jari kebiruan', ['Tidak', 'Ya']],
  ['Penurunan kesadaran, bingung (keluarga)', ['Tidak', 'Ya']],
  ['Batuk darah', ['Tidak', 'Ya']],
  ['Demam', ['Tidak', 'Ya']],
  ['Batuk bertambah', ['Tidak', 'Ya']],
  ['Bekas tusukan nyeri/rembes/bengkak', ['Tidak', 'Ya']],
] as const;
export function SymptomForm() {
  return (
    <>
      <Notice>
        Contoh susunan dari wireframe, bukan daftar gejala final. Pilihan dan pengiriman belum
        diaktifkan; menunggu persetujuan peneliti dan layanan server.
      </Notice>
      <Card>
        {illustrativeSymptoms.map(([label, options]) => (
          <View key={label} style={local.symptom}>
            <Copy style={styles.label}>{label}</Copy>
            <View style={local.options}>
              {options.map((option) => (
                <Button key={option} title={option} secondary disabled />
              ))}
            </View>
          </View>
        ))}
        <Copy>Diisi oleh</Copy>
        <View style={local.options}>
          <Button title="Pasien" secondary disabled />
          <Button title="Keluarga" secondary disabled />
        </View>
        <Button title="Kirim · belum tersedia" disabled />
      </Card>
      <Button title="Tentang umpan balik" secondary onPress={() => router.push('/feedback')} />
    </>
  );
}
export function FeedbackScreen() {
  return (
    <Protected>
      <Frame title="Umpan balik" step="F4 · BELUM TERSEDIA">
        <Card>
          <Heading>Belum ada hasil dari server</Heading>
          <Copy>
            Tidak ada isian yang dikirim. Aplikasi tidak menghitung atau menampilkan hasil klinis
            dari nilai di perangkat.
          </Copy>
          <Copy>
            Umpan balik penelitian hanya dapat tersedia setelah layanan server disetujui dan
            diaktifkan.
          </Copy>
        </Card>
        <Button title="Kembali ke beranda" onPress={() => router.replace('/')} />
      </Frame>
    </Protected>
  );
}
export function RepeatScreen() {
  return (
    <Protected>
      <Frame title="Isi ulang tingkat sesak" step="F6 · BELUM TERSEDIA">
        <Notice>
          Belum tersedia jadwal isi ulang yang terverifikasi dari perawat. Pengingat, formulir ulang
          dan pengiriman belum diaktifkan.
        </Notice>
        <Button title="Mulai isi ulang · belum tersedia" disabled />
      </Frame>
    </Protected>
  );
}
export function ContactScreen() {
  return (
    <Frame title="Hubungi perawat" step="F5 · PESAN DAN TELEPON">
      <Card>
        <Copy style={styles.sectionTitle}>Telepon perawat</Copy>
        <Copy>
          Nomor perawat dari pengaturan penelitian belum tersedia. Gunakan nomor yang diberikan saat
          orientasi.
        </Copy>
        <Button title="Telepon perawat · belum tersedia" disabled />
      </Card>
      <Card>
        <Copy style={styles.sectionTitle}>Pesan</Copy>
        <Copy>
          Belum tersedia layanan pesan. Tidak ada percakapan atau pesan yang dikirim dari layar ini.
        </Copy>
        <TextInput
          accessibilityLabel="Tulis pesan, belum tersedia"
          placeholder="Tulis pesan…"
          editable={false}
          multiline
          style={styles.input}
        />
        <Button title="Kirim pesan · belum tersedia" disabled />
      </Card>
    </Frame>
  );
}
export function HistoryScreen() {
  return <Protected final><AssessmentHistory /></Protected>;
}
const education = [
  'Tanda bahaya',
  'Teknik napas (pursed-lip dan diafragma)',
  'Perawatan bekas tusukan',
  'Kapan harus kontrol',
];
export function EducationScreen() {
  return (
    <Protected>
      <Frame title="Edukasi" step="F7 · MATERI PENELITIAN">
        <Copy style={styles.muted}>
          Susunan materi tersedia tanpa internet. Isi edukasi menunggu materi yang disetujui
          peneliti.
        </Copy>
        {education.map((title, i) => (
          <Card key={title}>
            <Copy style={local.tileNumber}>0{i + 1}</Copy>
            <Copy style={styles.sectionTitle}>{title}</Copy>
            <Copy>Teks, gambar atau video belum tersedia untuk diunduh.</Copy>
          </Card>
        ))}
      </Frame>
    </Protected>
  );
}
export function EventScreen() {
  return (
    <Protected>
      <Frame title="Lapor kejadian" step="F8 · BELUM TERSEDIA">
        <Notice>
          Formulir belum aktif. Tidak ada laporan yang disimpan atau dikirim. Untuk bantuan segera
          gunakan tombol di bawah.
        </Notice>
        <Card>
          <Copy style={styles.sectionTitle}>Apa yang terjadi?</Copy>
          {['Saya ke IGD', 'Saya dirawat', 'Saya ditindak ulang'].map((title) => (
            <Button key={title} title={title} secondary disabled />
          ))}
          {['Tanggal', 'Tempat', 'Alasan'].map((label) => (
            <View key={label}>
              <Copy style={styles.label}>{label}</Copy>
              <TextInput
                accessibilityLabel={`${label}, belum tersedia`}
                editable={false}
                style={styles.input}
              />
            </View>
          ))}
          <Button title="Kirim laporan · belum tersedia" disabled />
          <Copy style={styles.muted}>
            Laporan kelak perlu diverifikasi peneliti dari rekam medis.
          </Copy>
        </Card>
      </Frame>
    </Protected>
  );
}
export function FinalScreen() {
  const day = useDay();
  return (
    <Protected final>
      <Frame title="Penilaian aplikasi" step="F10 · FORMULIR AKHIR">
        <Card>
          <Heading>Health-ITUES</Heading>
          <Copy>
            Item dan versi Indonesia akan diberikan peneliti. Belum ada pertanyaan atau skor yang
            ditampilkan.
          </Copy>
          <Copy>
            {day === null || day < 30
              ? 'Formulir dijadwalkan pada hari ke-30.'
              : 'Anda telah mencapai periode formulir akhir.'}
          </Copy>
          <Button title="Kirim penilaian · belum tersedia" disabled />
        </Card>
      </Frame>
    </Protected>
  );
}
const local = StyleSheet.create({
  check: {
    minHeight: 56,
    padding: 16,
    flexDirection: 'row',
    alignItems: 'center',
    gap: 14,
    borderRadius: 12,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: 'white',
  },
  grid: { flexDirection: 'row', flexWrap: 'wrap', gap: 12 },
  tile: {
    flexGrow: 1,
    flexBasis: '44%',
    minWidth: 140,
    padding: 18,
    borderRadius: 18,
    borderWidth: 1,
    borderColor: colors.border,
    backgroundColor: 'white',
    gap: 12,
    minHeight: 150,
  },
  tileNumber: { color: colors.blue, fontSize: 22, fontWeight: '700', lineHeight: 30 },
  symptom: { borderBottomWidth: 1, borderColor: colors.border, paddingBottom: 16 },
  options: { flexDirection: 'row', flexWrap: 'wrap', gap: 12, justifyContent: 'space-between' },
  emptyChart: {
    minHeight: 160,
    borderLeftWidth: 1,
    borderBottomWidth: 1,
    borderColor: '#8B9FB7',
    alignItems: 'center',
    justifyContent: 'center',
  },
  calendar: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  day: {
    width: 44,
    minHeight: 44,
    borderWidth: 1,
    borderColor: colors.border,
    borderRadius: 8,
    alignItems: 'center',
    justifyContent: 'center',
  },
});
