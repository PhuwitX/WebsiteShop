// ยืนยันก่อนทำรายการที่กดแล้วย้อนไม่ได้ (ใส่ data-confirm="ข้อความ" บนฟอร์ม)
document.addEventListener('submit', function (e) {
  var msg = e.target.getAttribute('data-confirm');
  if (msg && !window.confirm(msg)) e.preventDefault();
});

// แกลเลอรีรูปในหน้ารายละเอียดสินค้า: คลิกรูปเล็กเพื่อสลับรูปใหญ่
document.addEventListener('click', function (e) {
  var btn = e.target.closest('.gallery-thumb');
  if (!btn) return;
  var wrap = btn.closest('[data-gallery]');
  var main = wrap.querySelector('#gallery-main-img');
  main.src = btn.getAttribute('data-src');
  wrap.querySelector('.gallery-main').href = btn.getAttribute('data-src');
  wrap.querySelectorAll('.gallery-thumb').forEach(function (b) { b.classList.remove('active'); });
  btn.classList.add('active');
});
