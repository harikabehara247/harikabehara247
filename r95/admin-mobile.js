/* H View admin: phone helpers (r95). Turns list tables into labelled cards on small screens and registers the
   H View Admin app (its own install, separate from the website app). */
(function () {
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () { navigator.serviceWorker.register('/admin/sw.js', { scope: '/admin/' }).catch(function () {}); });
  }
  var MAIN = /^(title|name|customer|client|subject|number|order|contact|article|page|person|member|email|from|who|lead|design|product|wish)$/i;
  function txt(el) { return (el.textContent || '').replace(/\s+/g, ' ').trim(); }
  function cardify(t) {
    if (t.classList.contains('hv-cards') || t.closest('.hv-no-cards') || /cal/i.test(t.className)) return;
    var head = t.querySelector('thead tr'), firstRow = t.querySelector('tr');
    if (!head && firstRow && firstRow.querySelector('th') && !firstRow.querySelector('td')) { head = firstRow; head.classList.add('hv-head'); }
    if (!head) return;
    var labels = Array.prototype.map.call(head.children, txt);
    if (labels.length < 3) return;
    var mainIdx = -1;
    labels.forEach(function (l, i) { if (mainIdx < 0 && MAIN.test(l)) mainIdx = i; });
    Array.prototype.forEach.call(t.querySelectorAll('tbody tr, tr'), function (tr) {
      if (tr === head || tr.parentNode.tagName === 'THEAD') return;
      var cells = tr.children, col = 0, mi = mainIdx;
      if (mi < 0) { for (var k = 0; k < cells.length; k++) { if (!cells[k].querySelector('input[type=checkbox]') && txt(cells[k])) { mi = k; break; } } }
      for (var j = 0; j < cells.length; j++) {
        var c = cells[j], l = labels[col] || '', span = parseInt(c.getAttribute('colspan') || '1', 10);
        var onlyBox = c.querySelector('input[type=checkbox]') && !txt(c);
        var btns = c.querySelectorAll('a, button, form').length;
        if (onlyBox) c.classList.add('hv-check');
        else if (col === mi && span === 1) c.classList.add('hv-main');
        else if (/^actions?$/i.test(l) || (l === '' && btns >= 1)) c.classList.add('hv-act');
        else if (l) c.setAttribute('data-l', l);
        if (!onlyBox && !txt(c) && !c.querySelector('img, svg, input, select, button, a')) c.classList.add('hv-empty');
        col += span;
      }
    });
    t.classList.add('hv-cards');
  }
  function run() {
    if (window.innerWidth > 760) return;
    Array.prototype.forEach.call(document.querySelectorAll('table.adm-table, table.bl-t, .adm-main table.table, .adm-main table.data'), cardify);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run); else run();
  window.addEventListener('resize', function () { clearTimeout(window.__hvmt); window.__hvmt = setTimeout(run, 200); });
  /* lists that load later (Billing preview, AJAX tables) */
  if ('MutationObserver' in window) { var mo = new MutationObserver(function () { clearTimeout(window.__hvmo); window.__hvmo = setTimeout(run, 150); }); document.addEventListener('DOMContentLoaded', function () { var m = document.querySelector('.adm-main'); if (m) mo.observe(m, { childList: true, subtree: true }); }); }
})();
