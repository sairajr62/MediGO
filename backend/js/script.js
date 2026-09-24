/* ============================================================
   MediCheck — client-side helpers
   (Server-side validation is authoritative; this is UX sugar.)
   ============================================================ */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {

    // ---- Mobile navigation toggle ----
    var toggle = document.getElementById('navToggle');
    var links  = document.getElementById('navLinks');
    if (toggle && links) {
      toggle.addEventListener('click', function () {
        links.classList.toggle('open');
      });
    }

    // ---- Dismissible alerts ----
    document.querySelectorAll('.alert [data-dismiss], .alert .alert-close').forEach(function (el) {
      el.addEventListener('click', function () {
        var alert = el.closest('.alert');
        if (alert) { alert.remove(); }
      });
    });
    document.querySelectorAll('.alert-close').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var a = btn.closest('.alert');
        if (a) { a.remove(); }
      });
    });

    // ---- Password visibility toggles ----
    document.querySelectorAll('.toggle-password').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var input = document.getElementById(btn.dataset.target);
        if (!input) { return; }
        if (input.type === 'password') {
          input.type = 'text';
          btn.textContent = 'Hide';
        } else {
          input.type = 'password';
          btn.textContent = 'Show';
        }
      });
    });

    // ---- Confirm before submitting destructive forms ----
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
      form.addEventListener('submit', function (ev) {
        if (!window.confirm(form.getAttribute('data-confirm'))) {
          ev.preventDefault();
        }
      });
    });

    // ---- Show/hide pharmacy fields on register ----
    var roleSelect = document.getElementById('roleSelect');
    var pharmFields = document.getElementById('pharmacyFields');
    if (roleSelect && pharmFields) {
      var sync = function () {
        pharmFields.style.display = roleSelect.value === 'pharmacy' ? '' : 'none';
      };
      roleSelect.addEventListener('change', sync);
      sync();
    }

    // ---- Register form: confirm password match ----
    var regForm = document.getElementById('registerForm');
    if (regForm) {
      regForm.addEventListener('submit', function (ev) {
        var pw = document.getElementById('password');
        var cf = document.getElementById('confirm_password');
        if (pw && cf && pw.value !== cf.value) {
          ev.preventDefault();
          alert('Password and confirmation do not match.');
        }
      });
    }

    // ---- Reserve form: clamp quantity to available stock ----
    var reserveForm = document.getElementById('reserveForm');
    if (reserveForm) {
      var max = parseInt(reserveForm.getAttribute('data-max'), 10) || 1;
      var qty = document.getElementById('reserveQty');
      reserveForm.addEventListener('submit', function (ev) {
        var v = parseInt(qty.value, 10);
        if (isNaN(v) || v < 1) {
          ev.preventDefault();
          alert('Please enter a quantity of at least 1.');
        } else if (v > max) {
          ev.preventDefault();
          alert('Only ' + max + ' unit(s) are available.');
        }
      });
    }

  });
})();
