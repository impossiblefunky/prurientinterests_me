(function () {
  // Phone menu: the main links fold behind a button on small screens (search stays visible).
  var header = document.querySelector('.site-header'), btn = header && header.querySelector('.menu-toggle');
  if (!btn) return;
  var nav = document.getElementById('main-nav');
  btn.hidden = false;
  header.classList.add('has-menu');
  var set = function (open) { header.classList.toggle('open', open); btn.setAttribute('aria-expanded', open ? 'true' : 'false'); };
  btn.addEventListener('click', function () { set(btn.getAttribute('aria-expanded') !== 'true'); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && btn.getAttribute('aria-expanded') === 'true') { set(false); btn.focus(); }
  });
  nav.addEventListener('click', function (e) { if (e.target.closest('a')) set(false); });
})();
(function () {
  // Reading options on story pages: paper mode and larger text, remembered on this device.
  var tools = document.querySelector('.reading-tools');
  if (!tools) return;
  var root = document.documentElement;
  var store = function (k, v) { try { v === undefined ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch (e) {} };
  var read = function (k) { try { return localStorage.getItem(k); } catch (e) { return null; } };
  tools.hidden = false;
  tools.querySelectorAll('button[data-toggle]').forEach(function (b) {
    var name = b.getAttribute('data-toggle'), key = 'pi-' + name;
    var apply = function (on) { root.classList.toggle(name === 'paper' ? 'paper' : 'large', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); };
    apply(read(key) === '1');
    b.addEventListener('click', function () { var on = b.getAttribute('aria-pressed') !== 'true'; apply(on); store(key, on ? '1' : undefined); });
  });
})();

(function () {
  // Gallery viewer: click a picture to see it large, with previous / next, Esc to close.
  var links = Array.prototype.slice.call(document.querySelectorAll('.gallery a[href^="/uploads/"]'));
  if (!links.length || typeof HTMLDialogElement === 'undefined') return;
  var dlg = document.createElement('dialog');
  dlg.className = 'viewer';
  dlg.setAttribute('aria-label', 'Picture viewer');
  dlg.innerHTML = '<div class="viewer-bar"><span class="viewer-count" aria-live="polite"></span><button type="button" class="btn viewer-close">Close</button></div>' +
    '<div class="viewer-stage"><button type="button" class="btn viewer-prev" aria-label="Previous picture">&larr;</button><figure><img alt=""><figcaption></figcaption></figure><button type="button" class="btn viewer-next" aria-label="Next picture">&rarr;</button></div>';
  document.body.appendChild(dlg);
  var img = dlg.querySelector('img'), cap = dlg.querySelector('figcaption'), count = dlg.querySelector('.viewer-count');
  var i = 0, opener = null;
  function show(n) {
    i = (n + links.length) % links.length;
    var a = links[i], thumb = a.querySelector('img'), fig = a.closest('figure'), fc = fig && fig.querySelector('figcaption');
    img.src = a.getAttribute('href');
    img.alt = thumb ? thumb.getAttribute('alt') || '' : '';
    cap.textContent = fc ? fc.textContent.replace(/\s+/g, ' ').replace(/\(opens in a new tab\)/, '').trim() : '';
    count.textContent = (i + 1) + ' of ' + links.length;
    [i - 1, i + 1].forEach(function (k) { var p = new Image(); p.src = links[(k + links.length) % links.length].getAttribute('href'); });
  }
  links.forEach(function (a, n) {
    a.addEventListener('click', function (ev) {
      if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.button === 1) return;
      ev.preventDefault();
      opener = a;
      show(n);
      dlg.showModal();
    });
  });
  dlg.querySelector('.viewer-prev').addEventListener('click', function () { show(i - 1); });
  dlg.querySelector('.viewer-next').addEventListener('click', function () { show(i + 1); });
  dlg.querySelector('.viewer-close').addEventListener('click', function () { dlg.close(); });
  dlg.addEventListener('close', function () { img.removeAttribute('src'); if (opener) opener.focus(); });
  dlg.addEventListener('click', function (ev) { if (ev.target === dlg || ev.target.classList.contains('viewer-stage')) dlg.close(); });
  dlg.addEventListener('keydown', function (ev) {
    if (ev.key === 'ArrowLeft') { ev.preventDefault(); show(i - 1); }
    if (ev.key === 'ArrowRight') { ev.preventDefault(); show(i + 1); }
  });
  var x0 = null;
  dlg.addEventListener('touchstart', function (ev) { x0 = ev.touches[0].clientX; }, { passive: true });
  dlg.addEventListener('touchend', function (ev) {
    if (x0 === null) return;
    var dx = ev.changedTouches[0].clientX - x0; x0 = null;
    if (Math.abs(dx) > 50) show(dx > 0 ? i - 1 : i + 1);
  });
})();
