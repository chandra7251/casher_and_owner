# Panduan Print Bridge Android

Dokumen ini untuk owner cafe yang menjual atau memasang aplikasi pada tablet Android dan printer thermal 58mm.

## Prasyarat

- Tablet Android dengan Chrome atau WebView modern.
- Printer thermal 58mm yang mendukung ESC/POS melalui USB OTG atau koneksi bridge lokal.
- Kabel USB OTG yang sesuai.
- URL aplikasi POS yang dapat diakses tablet.
- Token bridge unik untuk perangkat tersebut.

## Konfigurasi server

1. Salin `PRINT_BRIDGE_TOKEN` pada `.env` production.
2. Gunakan token acak panjang, minimal 32 karakter.
3. Jalankan `php artisan config:cache`.
4. Pastikan aplikasi memakai HTTPS.
5. Jangan masukkan token ke frontend, URL, atau repository.

## Alur bridge

1. Bridge mengirim `GET /api/print-jobs/next` dengan header:

```http
Authorization: Bearer <PRINT_BRIDGE_TOKEN>
Accept: application/json
```

2. Jika `data` bernilai `null`, tunggu lalu polling ulang.
3. Jika job tersedia, server mengubah status menjadi `printing` dan mengirim `receipt_snapshot`.
4. Decode `receipt_snapshot.escpos_base64` menjadi bytes ESC/POS.
5. Kirim bytes ke printer 58mm.
6. Beri hasil ke server:

```http
PATCH /api/print-jobs/{id}
Authorization: Bearer <PRINT_BRIDGE_TOKEN>
Content-Type: application/json

{"status":"printed"}
```

7. Saat printer gagal:

```json
{"status":"failed","failure_reason":"Printer tidak terhubung."}
```

## Aturan operasional

- Jangan mengulang `PATCH` job yang sudah `printed`.
- Jangan membuat pembayaran baru saat printer gagal.
- Kasir dapat retry job `failed` dari workspace.
- Payment tetap `paid` meski printer gagal.
- Polling awal: setiap 2–5 detik; backoff saat tidak ada job.
- Satu tablet memakai satu token bridge.

## Checklist pemasangan

- [ ] Set `PRINT_BRIDGE_TOKEN`.
- [ ] Cache konfigurasi server.
- [ ] Install bridge pada tablet.
- [ ] Beri izin USB saat diminta Android.
- [ ] Pilih printer thermal 58mm.
- [ ] Uji koneksi USB OTG.
- [ ] Buat satu order nominal kecil.
- [ ] Pastikan receipt keluar dan job menjadi `printed`.
- [ ] Cabut printer, bayar order uji kedua, pastikan job menjadi `failed`.
- [ ] Sambungkan printer, retry job dari workspace kasir.
- [ ] Simpan model printer, versi Android, dan hasil uji untuk support.

## Catatan kompatibilitas

ESC/POS raster logo bergantung pada dukungan printer dan driver bridge. Jika logo tidak tercetak, gunakan konfigurasi bridge tanpa logo; detail order, pembayaran, timestamp, dan total tetap dikirim dalam snapshot.