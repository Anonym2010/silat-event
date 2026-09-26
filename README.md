# TRI SUKMA EVENT

Aplikasi kejuaraan pencak silat untuk pendaftaran peserta dan penilaian Tanding serta Jurus.

## Struktur

- `index.html` dan `styles.css`: halaman informasi acara.
- `backend/`: aplikasi Laravel untuk pendaftaran, pengelolaan pertandingan, dan penilaian.

## Menjalankan aplikasi lokal

Persyaratan: PHP 8.3 atau lebih baru, Composer, Node.js/npm, dan MySQL.

1. Clone repositori, lalu masuk ke folder backend:

   ```powershell
   git clone https://github.com/Anonym2010/silat-event.git
   cd silat-event\backend
   ```

2. Siapkan konfigurasi lokal dan database MySQL bernama `silat_event`:

   ```powershell
   Copy-Item .env.example .env
   ```

   Sesuaikan `DB_*` di `.env` dengan pengaturan MySQL lokal Anda.

3. Pasang dependensi, buat application key, dan siapkan database:

   ```powershell
   composer install
   php artisan key:generate
   php artisan migrate --seed
   npm install
   npm run build
   ```

   Seeder bawaan membuat akun demo dengan password contoh. Gunakan hanya untuk pengembangan lokal; jangan jalankan seeder atau memakai kredensial demo di production. Untuk data uji penilaian, jalankan `php artisan db:seed --class=DemoScoringSeeder` hanya pada database lokal.

4. Jalankan server:

   ```powershell
   php artisan serve
   ```

   Buka `http://127.0.0.1:8000`.

## Pengujian

Dari folder `backend`:

```powershell
php artisan test --compact
```

## Berkontribusi

Buat branch untuk perubahan, jalankan pengujian, lalu ajukan pull request ke branch utama. Pemilik repo dapat mengundang teman melalui **Settings → Collaborators** pada repositori private.

Jangan commit file `.env`, database lokal, log, atau kredensial. Simpan konfigurasi lokal hanya di `.env`.
