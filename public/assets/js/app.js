/* CAMPUSPHERE – progressive enhancement: reading and filtering work without JavaScript; dialogs (new records, rejections) need it. */
(function () {
  'use strict';

  // mobile navigation
  var toggle = document.getElementById('menu-toggle');
  var sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () {
      var open = sidebar.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && sidebar.classList.contains('open')) { sidebar.classList.remove('open'); toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); }
    });
  }

  // password reveal + demo account fill (login)
  var pw = document.getElementById('password'), pwt = document.getElementById('pw-toggle');
  if (pw && pwt) {
    pwt.addEventListener('click', function () {
      var show = pw.type === 'password';
      pw.type = show ? 'text' : 'password';
      pwt.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
  }
  document.querySelectorAll('[data-fill]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.getElementById('username').value = b.getAttribute('data-fill');
      document.getElementById('password').value = b.getAttribute('data-pass');
    });
  });

  // <dialog> openers / closers
  document.querySelectorAll('[data-open]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = document.getElementById(b.getAttribute('data-open'));
      if (d && d.showModal) { d.showModal(); }
    });
  });
  document.querySelectorAll('dialog [data-close]').forEach(function (b) {
    b.addEventListener('click', function () { b.closest('dialog').close(); });
  });
  document.querySelectorAll('dialog.modal').forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) { d.close(); } });
    if (d.hasAttribute('data-autoopen') && d.showModal) { d.showModal(); }
  });

  // confirm destructive / irreversible actions
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); } });
  });

  // prevent double submits
  document.querySelectorAll('form').forEach(function (f) {
    f.addEventListener('submit', function () {
      f.querySelectorAll('button[type=submit]').forEach(function (b) { setTimeout(function () { b.disabled = true; }, 0); });
    });
  });

  // auto-submit filter selects
  document.querySelectorAll('select[data-autosubmit]').forEach(function (s) {
    s.addEventListener('change', function () { s.form.submit(); });
  });

  // "mark all present" helper on class attendance sheets
  document.querySelectorAll('[data-set-all]').forEach(function (b) {
    b.addEventListener('click', function () {
      var v = b.getAttribute('data-set-all');
      document.querySelectorAll('select[name^="status["]').forEach(function (s) { s.value = v; });
    });
  });
})();
