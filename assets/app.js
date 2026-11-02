/* Death Plan Switch
 *
 * The app works without this file. It adds a few conveniences:
 *   - the sliding underline under the current tab
 *   - the dark / light / system choice (assets/theme.js applies it)
 *   - closing the "More" sheet of the phone menu on a tap outside it, or Escape
 *   - asking before a few actions that are awkward to undo
 *   - guessing the timezone in the installer
 *   - noticing the first scheduler run in the installer
 *   - testing the data folder from the browser, when the server cannot
 */
(function () {
  'use strict';

  // "Are you sure?" on elements carrying data-confirm.
  document.addEventListener('click', function (event) {
    var el = event.target.closest('[data-confirm]');
    if (el && !window.confirm(el.getAttribute('data-confirm'))) {
      event.preventDefault();
    }
  });

  // Phone menu: the "More" sheet is a <details>, so it works without script.
  // This only closes it when the visitor taps elsewhere or presses Escape.
  var more = document.querySelector('details.more');
  if (more) {
    document.addEventListener('click', function (event) {
      var target = event.target;
      var sheet = more.querySelector('.more-sheet');
      var inSheet = sheet && sheet.contains(target);
      var onSummary = target.closest && target.closest('details.more > summary');
      if (more.open && !inSheet && !onSummary) { more.open = false; }
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && more.open) { more.open = false; }
    });
  }

  // Theme: dark, light or system. Every control with data-theme-set picks one.
  var picks = document.querySelectorAll('[data-theme-set]');
  function markTheme() {
    var now = window.dpsTheme ? window.dpsTheme.get() : 'dark';
    Array.prototype.forEach.call(picks, function (button) {
      button.setAttribute('aria-pressed', button.getAttribute('data-theme-set') === now ? 'true' : 'false');
    });
  }
  Array.prototype.forEach.call(picks, function (button) {
    button.addEventListener('click', function () {
      if (window.dpsTheme) { window.dpsTheme.set(button.getAttribute('data-theme-set')); }
      markTheme();
    });
  });
  markTheme();

  // The tab strip: one underline that slides to the current tab.
  var strip = document.querySelector('.tab-list');
  var bar = strip ? strip.querySelector('.tab-indicator') : null;
  if (strip && bar) {
    var place = function () {
      var current = strip.querySelector('[aria-current="page"]');
      if (!current) { bar.style.opacity = '0'; return; }
      var from = strip.getBoundingClientRect();
      var box = current.getBoundingClientRect();
      var left = box.left - from.left + strip.scrollLeft;
      strip.classList.add('has-indicator');
      bar.style.opacity = '';
      bar.style.transform = 'translateX(' + left + 'px) scaleX(' + box.width + ')';
    };
    var jump = function () {
      bar.style.transition = 'none';
      place();
      void bar.offsetWidth;
      bar.style.transition = '';
    };
    jump();
    window.addEventListener('resize', place);
    if (document.fonts && document.fonts.ready) { document.fonts.ready.then(jump); }
  }

  // Installer: preselect the visitor's timezone.
  var zone = document.querySelector('select[data-timezone-guess]');
  if (zone) {
    try {
      var guess = Intl.DateTimeFormat().resolvedOptions().timeZone;
      for (var i = 0; i < zone.options.length; i++) {
        if (zone.options[i].value === guess) {
          zone.selectedIndex = i;
          break;
        }
      }
    } catch (e) { /* keep the default */ }
  }

  // Installer: reload when the first scheduler run arrives.
  var poll = document.querySelector('[data-poll]');
  if (poll) {
    var timer = window.setInterval(function () {
      fetch(poll.getAttribute('data-poll'), { cache: 'no-store', credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data && data.ticked) {
            window.clearInterval(timer);
            window.location.reload();
          }
        })
        .catch(function () { /* try again next time */ });
    }, 5000);
  }

  // Data folder test, run from the browser. The form carries the address of
  // a harmless probe file and the text inside it. If the browser can read
  // that text, the folder is exposed.
  function probe(form) {
    var found = form.querySelector('input[name="found"]');
    return fetch(form.getAttribute('data-probe-url'), { cache: 'no-store', credentials: 'omit', redirect: 'follow' })
      .then(function (response) { return response.text(); })
      .then(function (body) {
        found.value = body.indexOf(form.getAttribute('data-probe-secret')) === -1 ? 'protected' : 'exposed';
      })
      .catch(function () {
        found.value = 'unreachable';
      });
  }

  Array.prototype.forEach.call(document.querySelectorAll('form[data-probe-url]'), function (form) {
    if (form.hasAttribute('data-probe-auto')) {
      // Installer: run it straight away and show the result in the checklist.
      probe(form).then(function () {
        var found = form.querySelector('input[name="found"]').value;
        var item = document.getElementById('probe-item');
        var hint = document.getElementById('probe-hint');
        if (!item || !hint) { return; }
        var mark = item.querySelector('.mark');
        if (found === 'protected') {
          item.className = 'is-ok';
          mark.textContent = '✓';
          hint.textContent = 'Checked from your browser: the request was refused.';
        } else if (found === 'exposed') {
          item.className = 'is-todo';
          mark.textContent = '✗';
          hint.textContent = 'Your browser was able to open a test file in the data folder. Block the folder (see the README), then reload this page.';
        } else {
          hint.textContent = 'Neither the server nor your browser could run the test. Check the web address of this page is correct.';
        }
      });
    } else {
      // Settings: run it when the button is pressed, then submit the result.
      form.addEventListener('submit', function (event) {
        var found = form.querySelector('input[name="found"]');
        if (found.value !== '') { return; }
        event.preventDefault();
        probe(form).then(function () { form.submit(); });
      });
    }
  });
})();
