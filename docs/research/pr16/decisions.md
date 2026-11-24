# Keputusan peneliti — belum ada pengesahan final yang dapat diverifikasi

Jawaban harus merujuk dokumen/versi/halaman atau catatan keputusan yang nyata.
Jangan menjawab dengan fixture, contoh UI, atau kata “disetujui” tanpa mapping.

| ID | Pertanyaan yang memerlukan keputusan/bukti | Dampak terhadap kesiapan |
|---|---|---|
| D16-01 | Di mana proposal asli beserta lampiran instrumen yang disetujui, revisi dan bukti persetujuannya? Apakah dua panduan ini hanya turunan? | Review proposal asli BLOCKED;tidak mengarang nomor etik/tanda tangan. |
| D16-02 | Apa wording final,urutan,kode mesin,dan wajib/opsional untuk masing-masing8gejala? Mohon review Q1–Q8 serta mapping teknis TIDAK/YA kandidat. | Seluruh required tetap null,kode/value belum final. Mengisi semua false/true bukan keputusan sah. |
| D16-03 | Nyeri dada memakai pilihan yang mana: Ringan/Hebat atau Tidak/Ringan-sedang/Hebat,atau definisi lain dalam proposal? | options Q2 kosong;tidak memilih sendiri. Jika opsi lain,harus sumber persetujuan baru yang jelas. |
| D16-04 | Item penurunan kesadaran wajib diisi keluarga? Bila pasien mengisi,apakah tidak ditanyakan,opsional atau seluruh form harus oleh keluarga? | Kontrak global filled_by tidak dapat mengekspresikan author per-item/required bersyarat;perlu keputusan kompatibel atau PR kontrak terpisah. |
| D16-05 | Definisi “batuk bertambah” dibanding kapan/apa? Apa makna satu jawaban untuk nyeri/rembes/bengkak;tetap gabungan atau dipisah? Adakah definisi pengamatan demam? | Jangan menambah periode,operator klinis,pemisahan item atau ambang suhu tanpa sumber. |
| D16-06 | Setujui kebijakan kiriman offline lintas tengah malam/perubahan versi: same_day_only yang kini didukung atau kebutuhan lain? | late_policy null. Perubahan butuh PR terpisah;quarantine tidak boleh dikendurkan hanya agar publishable. |
| D16-07 | Apa versi instrumen final,referensi persetujuan aktual,kalender mulai dan expiry? Siapa yang mengonfirmasi melalui akun PENELITI_UTAMA sah? | version null;tidak ada reference/time palsu. expected_current_version dibaca saat review,publikasi manual eksplisit. |
| D16-08 | Kapan formulir akhir boleh dibuka/ditutup: hari30 atau setelahnya? Apa final outcome/Health-ITUES Bahasa Indonesia dan aturan missing/scoring? | Di luar daily instrument;tidak mengubah placeholder atau membuat20item dari ringkasan. |
| D16-09 | Bagaimana mengarsipkan paket final/evidence,mapping kode dan bukti persetujuan di bawah reference yang dikirim? | Existing API menyimpan ref/aktor/time/rawdefinition/options;metadata evidence PR15 hanyaRAM. Arsip eksternal diperlukan untuk provenance sumber. |

D16-01–07 dan09 adalah blocker instrumen daily yang siap dipublikasikan. D16-08
blocker formulir akhir tersendiri, bukan alasan menyisipkannya ke daily catalog.
SOP warna/delta/alerts/notifikasi,Health-ITUES scoring,readmission dan eskalasi
tetap terpisah; persetujuan daily tidak otomatis mengotorisasi modul itu.

Setelah ada jawaban, catat keputusan tanpa PHI, perbarui matriks dan buat kandidat
revisi. Uji kontrak dan pengalaman perangkat dengan sumber yang sudah final.
Publikasi hanya melalui endpoint/UI existing dengan CAS+konfirmasi; jangan INSERT
langsung atau memutasi publikasi/entri lama. Peneliti melakukan aktivasi terpisah
setelah review PR dan deployment yang berwenang; tugas ini tidak melakukan keduanya.

## Checklist untuk rapat peneliti PR-18

Catatan PR-18 menerima persetujuan desain aplikasi. Proposal asli dan daftar
keputusan instrumen final belum ada dalam dokumen yang dapat diperiksa. Isi
jawaban di catatan review yang dirujuk, dengan versi dan halaman. Status semua
baris di bawah ini masih **menunggu**.

| Yang dilihat pasien atau operator | Mohon peneliti konfirmasi | Rujukan |
|---|---|---|
| Dokumen pokok instrumen | Lampirkan proposal asli, lampiran dan versi yang disahkan, lalu tunjukkan apakah panduan teknis mengikuti versi itu. | D16-01 |
| 1. Sesak saat istirahat | Konfirmasi teks, posisi, pilihan, dan apakah wajib. | D16-02 |
| 2. Nyeri dada | Konfirmasi set pilihan. Gambar menampilkan Ringan/Hebat; catatan Visual hlm. 6 mengusulkan Tidak/Ringan-sedang/Hebat. Tentukan juga status wajib/opsional. | D16-02–03 |
| 3. Bibir atau ujung jari kebiruan | Konfirmasi teks, pilihan dan apakah wajib. | D16-02 |
| 4. Penurunan kesadaran atau bingung | Konfirmasi pilihan dan aturan bila pasien mengisi sendiri. Form sekarang mengetahui pengisi seluruh form. | D16-02, D16-04 |
| 5. Batuk darah | Konfirmasi teks, pilihan dan apakah wajib. | D16-02 |
| 6. Demam | Konfirmasi teks, pilihan, wajib/opsional, dan definisi jika ada di sumber. | D16-02, D16-05 |
| 7. Batuk bertambah | Konfirmasi pembanding waktu atau kondisi serta teks, pilihan dan status wajib. | D16-02, D16-05 |
| 8. Bekas tusukan nyeri, rembes, atau bengkak | Konfirmasi satu pertanyaan gabungan atau terpisah dan cara menjawabnya. | D16-02, D16-05 |
| VAS dan jadwal | Konfirmasi sumber VAS, hari pengisian, tanggal WIB, perlakuan kiriman offline lewat tengah malam, dan kapan instrumen mulai/berakhir. | D16-06–07 |
| Bukti publikasi | Sediakan versi, referensi sah, pemetaan kode teknis, kewenangan publikasi dan lokasi arsip. | D16-07, D16-09 |
| Form akhir, hasil dan notifikasi | Konfirmasi dalam review terpisah untuk Health-ITUES, outcome, klasifikasi dan SOP tindak lanjut. | D16-08 |

Hasil keputusan harus cocok dengan wording, urutan, dan pilihan yang disahkan.
Tabel ini merujuk kandidat PR #16, yang tetap tidak dapat diimpor sebagai paket
valid. JSON final baru disiapkan setelah keputusan Daily Assessment dan bukti
sumber lengkap. Review editor, publikasi manual, dan rollout tetap terpisah.
