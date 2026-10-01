# Tanda Tangan Elektronik Pustakawan v1.0.0

Plugin SLiMS untuk pengesahan/verifikasi internal dokumen PDF menggunakan identitas akun pustakawan yang login, QR Code, ID verifikasi, waktu tanda tangan, dan hash SHA-256.

> Catatan: fitur ini adalah pengesahan/verifikasi dokumen internal. Ini bukan Tanda Tangan Elektronik Tersertifikasi PSrE.

## Instalasi
1. Ekstrak folder `tte_pustakawan` ke folder `plugins/` SLiMS.
2. Dari terminal masuk ke `plugins/tte_pustakawan` lalu jalankan `composer install --no-dev` untuk memasang TCPDF + FPDI.
3. Aktifkan plugin dari menu Sistem > Plugin. Migration otomatis membuat tabel `tte_documents`.
4. Pastikan folder `storage/original` dan `storage/signed` dapat ditulis oleh web server.

## Alur
- Pustakawan login menggunakan akun SLiMS masing-masing.
- Pilih Sistem > Tanda Tangan Elektronik > Tanda Tangani Dokumen.
- Isi metadata dan unggah PDF.
- Pilih halaman dan geser posisi QR pada simulasi halaman.
- Klik Tandatangani Dokumen.
- PDF final mendapat QR, nama akun, waktu, dan ID verifikasi.
- QR mengarah ke `index.php?p=tte-validation&code=...` pada OPAC.
- Halaman OPAC menampilkan metadata, status, penandatangan, waktu, dan SHA-256.

## Routing AJAX SLiMS
Seluruh form/action mempertahankan `mod` dan `id`. Upload PDF memakai FormData AJAX agar admin shell tidak berubah menjadi halaman `plugin_container.php` polos.

## Tampilan
<img width="1351" height="600" alt="image" src="https://github.com/user-attachments/assets/e63cd32a-9427-4748-b771-62fe3eefc61d" />
<img width="1344" height="640" alt="image" src="https://github.com/user-attachments/assets/712ffef4-8aeb-49e5-b0c1-9d3406f7f521" />
<img width="864" height="529" alt="image" src="https://github.com/user-attachments/assets/8d5eaf95-99f4-4cbb-97d5-748850332c1f" />



