(function () {
  // Sortable tables: every header except those marked data-nosort becomes a button.
  document.querySelectorAll('table[data-sortable]').forEach(function (table) {
    var live = document.createElement('div');
    live.className = 'sr-only'; live.setAttribute('role', 'status');
    table.parentNode.insertBefore(live, table);
    table.querySelectorAll('thead th').forEach(function (th, col) {
      if (th.hasAttribute('data-nosort')) return;
      var label = th.textContent.trim();
      var b = document.createElement('button');
      b.type = 'button'; b.className = 'sort'; b.textContent = label;
      th.textContent = ''; th.appendChild(b);
      b.addEventListener('click', function () {
        var dir = th.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
        table.querySelectorAll('thead th').forEach(function (o) { o.removeAttribute('aria-sort'); });
        th.setAttribute('aria-sort', dir);
        var tbody = table.tBodies[0];
        var rows = Array.prototype.slice.call(tbody.rows);
        var val = function (r) { var c = r.cells[col]; return (c.getAttribute('data-sort') || c.textContent).trim(); };
        rows.sort(function (a, b2) {
          var x = val(a), y = val(b2), nx = parseFloat(x), ny = parseFloat(y);
          var r = (!isNaN(nx) && !isNaN(ny) && String(nx) === x && String(ny) === y) ? nx - ny : x.localeCompare(y, undefined, { numeric: true, sensitivity: 'base' });
          return dir === 'ascending' ? r : -r;
        });
        rows.forEach(function (r) { tbody.appendChild(r); });
        live.textContent = 'Sorted by ' + label + ', ' + dir + '.';
      });
    });
  });
  // Confirm before destructive actions.
  document.addEventListener('submit', function (ev) {
    var m = ev.target.getAttribute && ev.target.getAttribute('data-confirm');
    if (m && !window.confirm(m)) ev.preventDefault();
  });
  // Select-all checkbox in a table header.
  document.querySelectorAll('input[data-select-all]').forEach(function (box) {
    box.addEventListener('change', function () {
      box.closest('table').querySelectorAll('tbody input[type=checkbox]').forEach(function (c) { c.checked = box.checked; });
    });
  });
  document.addEventListener('click', function (ev) {
    var m = ev.target.closest && ev.target.closest('[data-confirm-click]');
    if (m && !window.confirm(m.getAttribute('data-confirm-click'))) ev.preventDefault();
  });
  // Formatting toolbar wraps the selected text in the textarea.
  document.querySelectorAll('.toolbar').forEach(function (bar) {
    var ta = document.getElementById(bar.getAttribute('data-for'));
    bar.addEventListener('click', function (ev) {
      var t = ev.target.closest('button[data-wrap]'); if (!t) return;
      var tag = t.getAttribute('data-wrap'), s = ta.selectionStart, e = ta.selectionEnd, sel = ta.value.slice(s, e);
      var ins = tag === 'hr' ? '<hr>' : '<' + tag + '>' + sel + '</' + tag + '>';
      ta.setRangeText(ins, s, e, 'end'); ta.focus();
    });
  });
})();
