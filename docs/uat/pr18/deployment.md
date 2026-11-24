# Runbook administrator — UAT sintetis terisolasi

Target yang diusulkan adalah `/var/www/telenapas-uat`,
`https://uat-telenapas.sintaraniapp.net`, PHP 8.4 dan MySQL 8.4 khusus
`telenapas_synthetic_uat_test`. Jalankan setelah review operator dan persetujuan
akses. Gunakan instance UAT privat atau isolasi setara. Tetapkan DNS, sertifikat
TLS, firewall/VPN, pemilik layanan dan jadwal pengujian sebelum membuka akses.

## Siapkan rilis dan database

1. Siapkan direktori rilis UAT, misalnya
   `/var/www/telenapas-uat/releases/<commit>`, pengguna layanan web UAT, dan
   `/var/www/telenapas-uat/shared` untuk rahasia dan state. Symlink `current`
   menunjuk rilis UAT. Gunakan PHP-FPM UAT tersendiri (contoh socket
   `/run/php/php8.4-fpm-telenapas-uat.sock`) dan beri akses tulis hanya untuk
   storage/cache Laravel UAT. Pastikan port 3338 tersedia pada loopback instance ini.
2. Buat file privat `shared/mysql.env` dengan mode 0600. Isi
   `UAT_MYSQL_PASSWORD` dan `UAT_MYSQL_ROOT_PASSWORD` dengan nilai berbeda dari
   pengelola rahasia. Jalankan dari checkout rilis UAT:

   ```bash
   docker compose --env-file /var/www/telenapas-uat/shared/mysql.env \
     -f deploy/uat/compose.mysql.yaml config --quiet
   docker compose --env-file /var/www/telenapas-uat/shared/mysql.env \
     -f deploy/uat/compose.mysql.yaml up -d --wait --wait-timeout 180
   ```

   Template membuat volume, server dan schema baru milik proyek
   `telenapas-synthetic-uat`, dengan port host hanya di loopback. Verifikasi
   owner dan instance sebelum migrasi. Jangan gunakan volume atau kredensial
   `telenapas_demo`. Jangan tampilkan hasil `docker compose config` tanpa
   `--quiet` karena interpolasi mengandung rahasia.

3. Siapkan file environment privat aplikasi UAT. Tautkan sebagai `apps/api/.env`
   hanya pada rilis UAT. Gunakan kunci Laravel baru untuk UAT, kata sandi runtime
   dari langkah 2, dan cache/auth limiter yang terisolasi. Bila memakai cache file
   dengan beberapa worker, semuanya harus memakai storage UAT yang sama.
   Berikut parameter nonrahasia:

   ```dotenv
   APP_NAME="TeleNapas Synthetic UAT"
   APP_ENV=uat
   APP_DEBUG=false
   APP_URL=https://uat-telenapas.sintaraniapp.net
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3338
   DB_DATABASE=telenapas_synthetic_uat_test
   DB_USERNAME=telenapas_uat
   TELENAPAS_SYNTHETIC_UAT=true
   TELENAPAS_DEMO_ENABLED=false
   AUTH_BLINDED_ASSESSMENT=true
   SESSION_DRIVER=array
   CACHE_STORE=file
   QUEUE_CONNECTION=sync
   LOG_LEVEL=warning
   ```

   Tambahkan `APP_KEY` dan `DB_PASSWORD` melalui pengaturan privat. Pastikan
   pengguna runtime hanya mempunyai hak pada schema UAT. Operator terlebih
   dahulu memeriksa host/port/schema memakai konsol MySQL UAT, lalu menjalankan
   migrasi existing pada **schema baru** menggunakan administrator migrasi UAT.
   Contoh dari `apps/api`, dengan `UAT_SCHEMA_ADMIN` dan
   `UAT_SCHEMA_ADMIN_PASSWORD` diambil melalui sesi privat:

   ```bash
   composer install --no-interaction --prefer-dist --no-dev --optimize-autoloader
   php artisan config:clear
   DB_USERNAME="${UAT_SCHEMA_ADMIN:?}" DB_PASSWORD="${UAT_SCHEMA_ADMIN_PASSWORD:?}" \
     php artisan migrate --force
   php artisan config:cache
   ```

   Jangan menyimpan kredensial administrator dalam file environment runtime dan
   jangan menggunakan `migrate:fresh`, reset atau seeder demo. Pastikan cache
   config dibangun tanpa variabel administrator. Simpan kunci dan storage UAT
   untuk konsistensi data antar-rilis UAT.

## Sajikan dashboard dan API pada origin UAT

Bangun dashboard dari root rilis UAT dengan Node 24 dan pnpm terkunci:

```bash
corepack pnpm install --frozen-lockfile
VITE_API_BASE_URL=https://uat-telenapas.sintaraniapp.net/api/v1 corepack pnpm build
```

Untuk Nginx, pada virtual host TLS UAT tersendiri, gunakan `server_name`
`uat-telenapas.sintaraniapp.net` dan potongan routing berikut. Sesuaikan dengan
mekanisme privat TLS/akses yang telah disetujui. Jangan gunakan lokasi ini di
virtual host development atau production:

```nginx
root /var/www/telenapas-uat/current/apps/dashboard/dist;

location ~ ^/(api/|up$) {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME /var/www/telenapas-uat/current/apps/api/public/index.php;
    fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    fastcgi_pass unix:/run/php/php8.4-fpm-telenapas-uat.sock;
}
location / {
    try_files $uri $uri/ /index.html;
}
```

Origin dashboard dan API sama sehingga allowlist CORS existing tetap cukup.
Validasi konfigurasi web server sebelum memuat **layanan UAT**. Periksa
`/up`, `/api/v1/health` (database connected), dan dashboard dari jaringan UAT.
Gunakan akun sintetis dari [PR #17](../PR-17.md#penyiapan-akun-dan-peserta).
Sebelum publikasi pertama, review versi server memastikan belum ada instrumen
aktif. Impor fixture PR #17 ke formulir atau isi formulir berdasarkan fixture,
lalu tinjau, konfirmasi dan publikasikan secara manual. Penanda uji, guard PR #17,
receipt, expiry dan audit berlaku pada endpoint yang sama.

## APK synthetic UAT dan checklist smoke

Profil EAS `synthetic-uat` memakai HTTPS UAT di atas, nama
**TeleNapas Synthetic UAT**, package `id.telenapas.patient.syntheticuat` dan
scheme `telenapas-synthetic-uat`. App config menolak URL lainnya pada profil
ini. Package berbeda dari APK `id.telenapas.patient` dan
`id.telenapas.patient.uat`. Verifikasi konfigurasi sebelum meminta build:

```bash
cd apps/mobile
APP_VARIANT=synthetic-uat EXPO_PUBLIC_API_BASE_URL=https://uat-telenapas.sintaraniapp.net/api/v1 \
  corepack pnpm exec expo config --type public
```

Setelah otorisasi akun EAS/signing/kuota, dari direktori yang sama gunakan
`npx eas-cli@latest build --platform android --profile synthetic-uat`.
Konfirmasi package/key, bagikan APK hanya ke tim UAT, dan jangan jalankan
`eas submit`. Pastikan logo/nama membedakan APK dan endpointnya bisa dijangkau
secara privat. Jalankan [skenario PR #17](../PR-17.md#skenario-perangkat) pada
APK, termasuk TalkBack dan font besar. Simpan bukti tangkapan layar yang sudah
dimasker bersama commit, versi, dan waktu WIB.

Jika rilis UAT bermasalah, hentikan akses UAT, arahkan `current` ke rilis UAT
terakhir yang kompatibel setelah review dan verifikasi kesiapan lagi. Bila
perlu biarkan instrumen sintetis kedaluwarsa. Pertahankan receipt, data, dan
audit. Jangan rollback atau membersihkan database existing untuk memperbaiki
rilis UI. PR ini tidak mengubah konfigurasi server atau menjalankan deployment.
