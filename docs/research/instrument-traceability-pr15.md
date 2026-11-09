# Matriks keterlacakan instrumen TeleNapas — PR15

Tanggal audit: 9 November 2026 WIB. Ini catatan review, **bukan instrumen aktif**.
Permintaan PR15 menyetujui proposal/panduan awal sebagai dasar. Persetujuan dasar
tersebut dicatat tanpa mengarang nomor surat, versi instrumen final, tanda tangan,
atau kalender aktivasi. Tidak ada publikasi/penulisan ke `telenapas_demo` dilakukan.

## Sumber yang benar-benar tersedia

| ID | Sumber / lokasi | Identitas dan keterbatasan |
|---|---|---|
| G | docs/design/TeleNapas_Panduan_Visual_IT.pdf | 15 halaman; turunan spesifikasi14 Okt2026, judul halaman1. SHA256 `0a564e84de7f45acf15d176de4db3167dda7d0f0047cd9d216219d3039516a1b`. Semua halaman dirender/diperiksa; halaman5/6 menjadi sumber utama F3. Wireframe bukan spesifikasi final; halaman1 menyatakan angka/usulan perlu pengaturan/konfirmasi. |
| S | Lampiran TeleNapas_SRS_v1_0_dan_Data_Dictionary_Draft_Review_Peneliti.docx | Versi1.0-draft,3Nov2026; SHA256 `811cc0d5a97def3888354b58b5e173036074acb6b80953310139d10f388bf522`. Bagian A§1,§3–6, bagian B kamus data, §13 lembar persetujuan masih kosong. S§14 menyebut protokol/instrumen final belum dilampirkan. |
| R | docs/research/requirements-v1.0.md | Persetujuan batas VAS absolut dalam permintaan PR02; bukan otorisasi mesin klinis. |
| C | Kode dan OpenAPI yang telah merge, main b2737ca | InstrumentPublicationController, DailyInstrument, AssessmentController, mobile DailyAssessment/VasControl, docs/api/openapi.yaml. Kontrak teknis, bukan sumber persetujuan klinis. |
| U | Permintaan PR15,9Nov2026 | Izin penggunaan proposal/panduan sebagai landasan, workflow review, dan pengujian sintetis; tidak memberikan nomor referensi persetujuan atau jawaban final atas ketidakjelasan di bawah. |
| P | Proposal/protokol asli; panduan sederhana21Okt2026 yang disebut S§14 | **Tidak tersedia** dalam repository/lampiran yang dapat diaudit. Jangan menyatakan isi/sahnya persetujuan yang tidak dilihat. |

## Setiap komponen pengisian

Tidak ada kode mesin, nilai database atau flag required yang diturunkan otomatis
dari teks berikut. Semua delapan baris gejala adalah **kandidat dari sumber**, bukan
katalog pengganti yang siap dipublikasikan. Urutan mengikuti G halaman6.

| ID / komponen | Teks/pilihan yang terlihat | Sumber tepat | Kesesuaian kontrak / keputusan yang tersisa |
|---|---|---|---|
| VAS | 0–100 mm, bilangan bulat; kosong sampai disentuh | G p5 F3 dan catatan; S A§1,§3 F3; B entri_harian.vas_mm | Sudah didukung sebagai `vas_mm` terpisah, required, integer0..100; jangan menambahkannya sebagai skor/gejala pengganti. Sitasi VAS wajib di paket review. |
| Q1 Sesak saat istirahat | Tidak / Ya | G p6 F3, baris1 | Single choice didukung. Kode, pemetaan nilai dan wajib/opsional belum eksplisit. Butuh penetapan peneliti, bukan kode otomatis. |
| Q2 Nyeri dada | Gambar: Ringan / Hebat. Catatan: Tidak / Ringan-sedang / Hebat, ditandai usulan/perlu konfirmasi | G p6 F3, baris2 dan keterangan bawah | **Konflik eksplisit**; jangan memilih salah satu atau menambah pilihan sendiri. Jumlah/teks pilihan, pemetaan dan required perlu persetujuan versi. |
| Q3 Bibir/ujung jari kebiruan | Tidak / Ya | G p6 F3, baris3 | Single choice; kode/nilai/required perlu ditetapkan. Tidak menghasilkan klasifikasi. |
| Q4 Penurunan kesadaran, bingung (keluarga) | Tidak / Ya; catatan menyebut diisi keluarga | G p6 F3, baris4 dan keterangan | Kontrak hanya `filled_by` global PASIEN/KELUARGA, tidak mendukung syarat pengisi per-item atau required bersyarat. Peneliti harus menyetujui definisi yang kompatibel atau mengusulkan perubahan kontrak terpisah. Jangan menghapus syarat diam-diam. |
| Q5 Batuk darah | Tidak / Ya | G p6 F3, baris5 | Kode/nilai/required belum eksplisit; tidak mengaktifkan peringatan. |
| Q6 Demam | Tidak / Ya | G p6 F3, baris6 | Tidak ada ambang suhu dalam form; jangan menambah angka/penilaian. Kode/nilai/required perlu ditetapkan. |
| Q7 Batuk bertambah | Tidak / Ya | G p6 F3, baris7 | Definisi pembanding tidak diperjelas; jangan mengarang periode/baseline. Kode/nilai/required perlu ditetapkan. |
| Q8 Bekas tusukan nyeri/rembes/bengkak | Tidak / Ya, satu baris gabungan | G p6 F3, baris8 | Jangan dipecah menjadi tiga pertanyaan atau aturan OR tanpa keputusan. Kode/nilai/required perlu ditetapkan. |
| Pengisi | Pasien / Keluarga | G p6 bagian bawah F3; S A§3 F3; B entri_harian.diisi_oleh | Enum server PASIEN/KELUARGA global, required. Sumber dicatat pada evidence.validation; tidak menentukan Q4 secara otomatis. |
| Hari0 dan hari pengamatan | Hari0 tanggal pulang; harian hari1–30 | G p3; p5 catatan; p8 penutupan hari31; S A§1,§4; B entri_harian.hari_ke | Didukung; `schedule.first_day/last_day` tetap harus eksplisit pada paket yang disetujui, tidak diisi bawaan oleh UI. Hari31 tidak dibuka. |
| Kalender dan frekuensi | Satu HARIAN per hari kalender WIB; server menetapkan hari_ke | G p5 catatan dan p15 integritas; S A§1,§4 | Kontrak mengunci `Asia/Jakarta`, satu harian/participant/date. Tidak berarti lengkap/patuh secara klinis. |
| Terlambat/offline | G p13 mengusulkan sinkron/anti-ganda dan masih meminta konfirmasi konflik | G p13; S D05 | Kontrak saat ini hanya `same_day_only`; laporan terlambat dikarantina/review, tidak diganti tanggal otomatis. Persetujuan basis tidak mengizinkan late policy baru. |
| UUID, timestamp, versi | UUID stabil, cap waktu perangkat; versi instrumen tersimpan | G p5 catatan,p13,p15; S B entri_harian; C PR11–13 | G menyebut UUID dibuat klien; implementasi existing memberi UUID melalui status server lalu mempertahankannya di queue. Perbedaan teknis didokumentasikan; tidak mengubah generator dalam PR15. Server/fingerprint melindungi replay/provenance. |
| Required/pilihan sah | Pilihan tetap, bukan teks bebas; katalog disetujui | G p6 keterangan; S B jawaban_gejala.kode_gejala/jawaban; C | Sumber tidak menetapkan required tiap item. Paket harus menyertakan boolean, mapping kode/value dan sitasi tiap pertanyaan/flag/pilihan; server memeriksa nilai/katalog aktual. |
| Aktivasi/kedaluwarsa versi | Kalender spesifik belum diberikan | C PR12 API; U tanpa tanggal/nilai | Wajib input peneliti dalam WIB→UTC. Kedaluwarsa publikasi berbeda dari hari30 tiap peserta; tidak diturunkan dari contoh jam/pengingat. |
| Persetujuan versi | Aktor/waktu server + referensi persetujuan eksternal | S§13 kosong; U; C PR12 | Tidak ada referensi yang dapat diisi otomatis. UI meminta referensi nyata dan centang eksplisit. Centang/sitasi tidak memverifikasi tanda tangan/etik secara digital. |

## Validasi teknis yang tetap berlaku

| Aturan | Sumber implementasi | Arti dan batas |
|---|---|---|
| Versi1..40 karakter; kode pertanyaan1..48 uppercase; pilihan value1..32; panjang label300/150;1..50 pertanyaan dan1..20 pilihan | InstrumentPublicationController.publish; OpenAPI InstrumentPublicationInput/PublicationQuestion | Batas penyimpanan/kontrak, bukan definisi klinis. Duplikat kode/value ditolak; tidak menentukan wording. |
| first/last integer1..30 dan terurut, WIB, same_day_only | C DailyInstrument + controller; G/S di atas untuk konteks studi | Mendukung jadwal terbatas; tidak menyetujui jam pemantauan, pengingat atau VAS ulang. |
| expected_current_version exact/null, konfirmasi eksplisit, future activation, expiry>activation | C PR12 publication | CAS diperiksa ulang setelah lock; konflik tidak ditimpa. UI revalidasi current_version sebelum POST dan tetap mengirim snapshot yang ditinjau. |
| Riset/kelompok/status/token/required/opsi/VAS dan satu per hari | AssessmentController + model/constraint + existing policy | Identitas ditetapkan server; pasien kontrol tidak memperoleh kredensial; data immutable dan audit redacted. |
| Instrumen tersedia hanya bila approval/role/effective/expiry/format/katalog sah | DailyInstrument.current | Data tersimpan/future bukan otomatis aktif. Tidak fallback ke versi expired/invalid atau contoh PDF. |
| Respons hilang | Kontrak versi unik dan response metadata existing | Timeout/close/logout bukan pembatalan server. UI tidak otomatis retry POST; periksa versi/aktor/waktu/ref pada daftar semua halaman. Ditemukan bukan bukti bahwa request terakhir yang membuatnya. |

## Hal yang sengaja tidak dijadikan instrumen/aturan aktif

G p6 F4,p9,p10,p14 berisi delta, warna/eskalasi/SLA/notifikasi. U melarang aktivasi
klasifikasi/notifikasi tanpa SOP; PR15 tidak mengeksekusinya. R menyetujui rentang
absolut HIJAU0–39,KUNING40–69,MERAH70–100 secara inklusif sebagai requirement,
bukan engine. Health-ITUES G p8 menyebut teks dari peneliti, bukan item final;
tidak dimasukkan ke daily_assessment. Batas/jam klinis tidak diciptakan.

## Keputusan peneliti yang masih diperlukan sebelum aktivasi nyata

1. Arsipkan proposal/protokol/versi final serta referensi persetujuan yang nyata.
2. Tetapkan setiap label, kode/value mapping, required/optional, dan urutan item.
3. Selesaikan konflik nyeri dada serta kebutuhan item kesadaran khusus keluarga.
4. Tegaskan bentuk item gabungan bekas tusukan dan makna 'batuk bertambah'.
5. Setujui jadwal yang didukung, same-day-only, kalender efektif/expiry versi.
6. Arsipkan matriks/paket sumber lengkap pada approval record yang dirujuk.

Status awal dashboard: **Menunggu kelengkapan/persetujuan**. Tidak ada seed,
startup-publication, INSERT manual, perubahan `telenapas_demo`, atau instrumen
klinis pengganti. Penggunaan panduan sebagai dasar dihormati tanpa menganggap
semua usulan atau kolom kosong telah diputuskan.
