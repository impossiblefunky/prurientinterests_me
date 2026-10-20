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
