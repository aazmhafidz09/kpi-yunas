// Service worker KPI Yunas Studio.
// - Aset statis (CSS, JS, ikon) disimpan agar aplikasi cepat dibuka.
// - Halaman PHP TIDAK disimpan (berisi data pribadi & selalu harus terbaru);
//   bila tidak ada sinyal, ditampilkan halaman offline.
// VERSI dikirim lewat alamat pendaftaran (sw.js?v=...), dihitung otomatis oleh server
// dari isi file aset. Tidak perlu diubah manual.
const VERSI = new URL(self.location.href).searchParams.get('v') || '0';
const CACHE = 'kpi-yunas-' + VERSI;
const ASET = [
  'offline.html',
  'assets/style.css?v=' + VERSI,
  'assets/app.js?v=' + VERSI,
  'assets/checklist.js?v=' + VERSI,
  'assets/ikon/ikon.svg',
  'assets/ikon/ikon-192.png',
  'assets/ikon/ikon-512.png',
  'assets/ikon/apple-touch-icon.png',
  'manifest.json',
];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll(ASET)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k.startsWith('kpi-yunas-') && k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  if (req.method !== 'GET') return; // POST selalu langsung ke server
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  // Navigasi halaman: selalu dari jaringan; offline -> halaman offline.
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).catch(() => caches.match('offline.html')));
    return;
  }

  // Aset statis: ambil dari cache, perbarui di latar belakang.
  if (/\/assets\/|manifest\.json$|offline\.html$/.test(url.pathname)) {
    e.respondWith(
      caches.open(CACHE).then((c) =>
        c.match(req).then((tersimpan) => {
          const segar = fetch(req).then((res) => {
            if (res.ok) c.put(req, res.clone());
            return res;
          }).catch(() => tersimpan);
          return tersimpan || segar;
        })
      )
    );
  }
});
