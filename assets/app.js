// Skrip umum aplikasi KPI Yunas: PWA, pemasangan, dan pengiriman isian harian yang tahan sinyal putus.
(function () {
  'use strict';

  // ---------- Service worker ----------
  // Versi diambil dari ?v= pada tag <script> ini (dihitung otomatis oleh server dari isi file).
  // URL sw.js yang berbeda per versi membuat browser memasang service worker baru secara otomatis.
  const VERSI = (document.currentScript && new URL(document.currentScript.src).searchParams.get('v')) || '0';
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('sw.js?v=' + encodeURIComponent(VERSI)).catch(() => { /* mis. bukan HTTPS: aplikasi tetap jalan biasa */ });
    });
  }

  // ---------- Tombol "Pasang aplikasi" ----------
  let promptPasang = null;
  const sudahTerpasang = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    promptPasang = e;
    document.querySelectorAll('.tombol-install').forEach((b) => { b.hidden = false; });
  });
  document.addEventListener('click', async (e) => {
    const b = e.target.closest('.tombol-install');
    if (!b || !promptPasang) return;
    promptPasang.prompt();
    await promptPasang.userChoice;
    promptPasang = null;
    document.querySelectorAll('.tombol-install').forEach((x) => { x.hidden = true; });
  });
  window.addEventListener('appinstalled', () => {
    document.querySelectorAll('.tombol-install').forEach((x) => { x.hidden = true; });
  });

  document.addEventListener('DOMContentLoaded', () => {
    // Petunjuk iPhone/iPad (Safari tidak punya tombol pasang otomatis).
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
    document.querySelectorAll('[data-petunjuk-ios]').forEach((el) => { el.hidden = !(ios && !sudahTerpasang); });
    document.querySelectorAll('[data-sudah-terpasang]').forEach((el) => { el.hidden = !sudahTerpasang; });

    // Tutup menu "Lainnya" saat mengetuk di luar.
    document.addEventListener('click', (e) => {
      document.querySelectorAll('details.nav-lainnya[open]').forEach((d) => { if (!d.contains(e.target)) d.open = false; });
    });

    siapkanFormHarian();
    kirimAntrian();
  });

  // ---------- Form Isi Harian ----------
  function siapkanFormHarian() {
    const form = document.querySelector('form.form-harian');
    if (!form) return;
    const progres = form.querySelector('[data-progres]');
    const kartu = Array.from(form.querySelectorAll('fieldset.butir-kartu'));

    function perbarui() {
      let terjawab = 0;
      kartu.forEach((k) => {
        const ada = !!k.querySelector('input[type=radio]:checked');
        k.classList.toggle('terjawab', ada);
        if (ada) terjawab++;
      });
      if (progres) progres.textContent = terjawab + '/' + kartu.length + ' terjawab';
    }
    form.addEventListener('change', perbarui);
    perbarui();

    const semua = form.querySelector('[data-semua]');
    if (semua) semua.addEventListener('click', () => {
      kartu.forEach((k) => { const r = k.querySelector('input[value="1"]'); if (r && !r.disabled) r.checked = true; });
      perbarui();
    });

    if (!form.dataset.fetch) return;
    form.addEventListener('submit', async (e) => {
      // Lompat ke butir pertama yang belum dijawab.
      const kosong = kartu.find((k) => !k.querySelector('input[type=radio]:checked') && !k.disabled);
      if (kosong) {
        e.preventDefault();
        kosong.scrollIntoView({ behavior: 'smooth', block: 'center' });
        kosong.classList.add('kedip');
        setTimeout(() => kosong.classList.remove('kedip'), 1200);
        tampilPesan(form, 'Masih ada butir yang belum dijawab.', 'galat');
        return;
      }
      e.preventDefault();
      const tombol = form.querySelector('button.besar');
      if (tombol) { tombol.disabled = true; tombol.dataset.teks = tombol.textContent; tombol.textContent = 'Mengirim…'; }
      const data = Array.from(new FormData(form).entries());
      try {
        const hasil = await kirim(form.getAttribute('action') || location.href, data);
        if (hasil.ok) { location.href = hasil.redirect || location.href; return; }
        tampilPesan(form, hasil.pesan || 'Gagal menyimpan.', 'galat');
      } catch (err) {
        // Tidak ada sinyal: simpan di HP, kirim otomatis saat online.
        simpanAntrian({ url: form.getAttribute('action') || location.href, data, waktu: Date.now() });
        tampilPesan(form, 'Tidak ada sinyal. Isian disimpan di HP dan akan dikirim otomatis saat online.', 'peringatan');
      }
      if (tombol) { tombol.disabled = false; tombol.textContent = tombol.dataset.teks; }
    });
  }

  function tampilPesan(form, teks, jenis) {
    const el = form.querySelector('[data-pesan]');
    if (!el) { alert(teks); return; }
    el.textContent = teks;
    el.className = 'pesan-form flash ' + jenis;
    el.hidden = false;
  }

  /**
   * Kirim POST sebagai fetch. Bila sesi sudah kedaluwarsa (token CSRF ditolak), ambil token baru
   * dari halaman yang sama (login otomatis lewat "ingat saya") lalu coba sekali lagi.
   * Melempar error hanya bila jaringan gagal.
   */
  async function kirim(url, data, ulang = true) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'X-Requested-With': 'fetch' },
      body: new URLSearchParams(data),
      credentials: 'same-origin',
    });
    if (res.status === 400 && res.headers.get('X-CSRF-Gagal') && ulang) {
      const halaman = await fetch(url, { credentials: 'same-origin' });
      if (halaman.redirected && /login\.php/.test(halaman.url)) return { ok: false, pesan: 'Sesi habis. Silakan login ulang, lalu kirim lagi.' };
      const token = new DOMParser().parseFromString(await halaman.text(), 'text/html').querySelector('input[name=_csrf]');
      if (!token) return { ok: false, pesan: 'Sesi habis. Muat ulang halaman.' };
      const baru = data.map(([k, v]) => [k, k === '_csrf' ? token.value : v]);
      return kirim(url, baru, false);
    }
    const jenis = res.headers.get('Content-Type') || '';
    if (jenis.includes('application/json')) return res.json();
    if (res.redirected && /login\.php/.test(res.url)) return { ok: false, pesan: 'Sesi habis. Silakan login ulang.' };
    return { ok: res.ok, redirect: res.url };
  }

  // ---------- Antrian kirim (disimpan di HP saat offline) ----------
  const KUNCI = 'kpi-yunas-antrian';
  function bacaAntrian() { try { return JSON.parse(localStorage.getItem(KUNCI) || '[]'); } catch (e) { return []; } }
  function tulisAntrian(a) { try { localStorage.setItem(KUNCI, JSON.stringify(a)); } catch (e) { /* penyimpanan penuh/diblokir */ } }
  function simpanAntrian(item) {
    // Satu isian per URL (pegawai + tanggal): yang terbaru menggantikan yang lama.
    tulisAntrian(bacaAntrian().filter((x) => x.url !== item.url).concat([item]));
    tampilBanner();
  }

  async function kirimAntrian() {
    const antrian = bacaAntrian();
    if (!antrian.length || !navigator.onLine) { tampilBanner(); return; }
    const sisa = [];
    const ditolak = [];
    let terkirim = 0;
    for (const item of antrian) {
      try {
        const h = await kirim(item.url, item.data);
        if (h.ok) terkirim++;
        else if (/Sesi habis/.test(h.pesan || '')) sisa.push(Object.assign(item, { galat: h.pesan })); // coba lagi setelah login
        else ditolak.push(h.pesan || 'ditolak server'); // mis. tanggal sudah lewat batas: tidak dicoba lagi
      } catch (e) { sisa.push(item); }
    }
    tulisAntrian(sisa);
    tampilBanner(terkirim);
    if (ditolak.length) alert('Isian tertunda tidak bisa dikirim: ' + ditolak.join('; '));
    if (terkirim && document.querySelector('form.form-harian')) setTimeout(() => location.reload(), 1200);
  }
  window.addEventListener('online', kirimAntrian);

  function tampilBanner(terkirim) {
    const antrian = bacaAntrian();
    let el = document.getElementById('banner-antrian');
    if (!antrian.length && !terkirim) { if (el) el.remove(); return; }
    if (!el) {
      el = document.createElement('div');
      el.id = 'banner-antrian';
      const main = document.querySelector('main');
      if (main) main.prepend(el);
    }
    if (terkirim && !antrian.length) {
      el.className = 'flash ok';
      el.textContent = terkirim + ' isian yang tertunda berhasil dikirim.';
    } else {
      const galat = antrian.find((x) => x.galat);
      el.className = 'flash peringatan';
      el.textContent = antrian.length + ' isian belum terkirim' + (galat ? ': ' + galat.galat : ' — akan dikirim otomatis saat online.');
    }
  }
})();
