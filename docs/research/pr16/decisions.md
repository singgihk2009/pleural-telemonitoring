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
