// Interaksi grid checklist: klik sel untuk berganti nilai, klik tanggal untuk mengisi satu kolom.
(function () {
  const form = document.getElementById('form-checklist');
  if (!form) return;
  let berubah = false;
  const tanda = document.getElementById('belum-simpan');

  function setSel(td, v) {
    const input = td.querySelector('input');
    input.value = v;
    td.classList.remove('v', 'v0', 'v1');
    td.classList.add('v' + v);
    td.lastChild.nodeType === 3 ? (td.lastChild.textContent = v === '1' ? '✓' : v === '0' ? '✗' : '')
      : td.appendChild(document.createTextNode(v === '1' ? '✓' : v === '0' ? '✗' : ''));
  }

  function tandai() {
    berubah = true;
    if (tanda) tanda.hidden = false;
  }

  form.addEventListener('click', function (e) {
    const td = e.target.closest('td.sel');
    if (td) {
      const v = td.querySelector('input').value;
      setSel(td, v === '' ? '1' : v === '1' ? '0' : '');
      tandai();
      return;
    }
    const th = e.target.closest('th.hari');
    if (th && !e.target.closest('a')) {
      const hari = th.dataset.hari;
      const sel = form.querySelectorAll('td.sel[data-hari="' + hari + '"]');
      const semuaSatu = Array.from(sel).every(td => td.querySelector('input').value === '1');
      sel.forEach(td => setSel(td, semuaSatu ? '' : '1'));
      tandai();
    }
  });

  form.addEventListener('change', tandai);
  form.addEventListener('submit', () => { berubah = false; });
  window.addEventListener('beforeunload', function (e) {
    if (berubah) { e.preventDefault(); e.returnValue = ''; }
  });
})();
