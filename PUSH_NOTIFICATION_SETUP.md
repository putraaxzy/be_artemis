# Push Notification Setup Guide

## 1. Generate VAPID Keys (Automatic)

Run command berikut untuk auto-generate VAPID keys:

```bash
php artisan vapid:keys
```

Command ini akan:
- Generate VAPID public dan private keys otomatis
- Update `.env` file dengan keys yang sudah generated
- Menampilkan keys di terminal untuk referensi

## 2. Migration Database

Setelah generate keys, jalankan migration:

```bash
php artisan migrate
```

Ini akan membuat tabel `push_subscriptions` untuk menyimpan subscription data dari client.

## 3. Environment Variables

Pastikan `.env` file sudah memiliki:

```env
VAPID_PUBLIC_KEY="..."
VAPID_PRIVATE_KEY="..."
```

Jika ingin generate ulang, cukup jalankan `php artisan vapid:keys` lagi.

## How Push Notifications Work

### Backend Flow:
1. Guru membuat tugas baru
2. Backend otomatis mengirim push notification ke semua siswa di kelas target
3. Notification dikirim ke service worker di client

### Frontend Flow:
1. Frontend request permission untuk notifikasi saat user pertama kali login
2. User approve permission
3. Frontend register service worker dan subscribe ke push notification
4. Subscription detail (endpoint, keys) dikirim ke backend
5. Saat ada tugas baru, backend mengirim push notification ke subscription endpoint
6. Service worker menerima notification dan menampilkan ke user

## API Endpoints

### Notification Endpoints:

```
POST /api/notifications/subscribe
- Subscribe user ke push notification
- Body: { endpoint, auth_key, p256dh_key }

POST /api/notifications/unsubscribe
- Unsubscribe user dari push notification
- Body: { endpoint }

GET /api/notifications/vapid-key
- Get VAPID public key untuk frontend

GET /api/notifications/subscriptions-count
- Get jumlah active subscriptions user

POST /api/notifications/test
- Send test notification (development)
```

## Service Worker

Service worker file terletak di: `public/service-worker.js`

Handles:
- Push notification events
- Notification clicks
- Background sync

## Architecture

```
Backend (Laravel):
├── PushNotificationService (business logic)
│   ├── sendToUser()
│   ├── sendToClass()
│   ├── sendToMultipleClasses()
│   └── sendToTargetStudents()
├── NotificationController (API endpoints)
├── TugasController (trigger notification saat tugas baru)
└── PushSubscription Model (store subscription data)

Frontend (React):
├── PushNotificationService (client-side logic)
├── usePushNotification (React hook)
├── NotificationPermissionDialog (request permission)
├── NotificationBell (UI component)
├── NotificationSettings (settings panel)
└── service-worker.js (handle push events)
```

## Testing

### Test dari Backend:

```bash
# Pastikan sudah subscribe dari frontend
curl -X POST http://localhost:8000/api/notifications/test \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Test dari Frontend:

Di NotificationSettings component, klik "Kirim Test Notifikasi" button.

## Troubleshooting

### "Browser tidak mendukung notifikasi"
- Gunakan browser modern (Chrome, Firefox, Edge)
- Service Worker hanya bekerja di HTTPS (production) atau localhost (development)

### "Notification permission denied"
- User perlu approve permission saat dialog muncul
- Jika sudah denied, buka browser settings dan change permission untuk situs ini

### "Push subscription failed"
- Pastikan backend memiliki VAPID keys yang valid
- Cek browser console untuk error details
- Pastikan endpoint URL valid

## Performance Notes

- Push subscriptions di-cache di database
- Invalid subscriptions (410 responses) otomatis dihapus
- Background sync untuk retry failed notifications (optional)
- TTL 24 jam untuk undelivered notifications

## Security

- VAPID keys adalah proof bahwa backend authorized untuk mengirim push notifications
- Private key hanya disimpan di backend (.env)
- Public key di-share ke frontend untuk validation
- Setiap push request signed dengan private key
