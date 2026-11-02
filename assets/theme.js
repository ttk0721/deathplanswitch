/* Death Plan Switch: the colour theme.
 *
 * Loaded in <head> without defer, so the theme is set before the first paint.
 * The choice is "dark" (the default), "light" or "system". It is kept in
 * localStorage; the light theme is :root[data-motyw="jasny"].
 */
(function () {
  'use strict';
  var KEY = 'dps-theme';
  var root = document.documentElement;
  var memory = 'dark';
  var query = window.matchMedia ? window.matchMedia('(prefers-color-scheme: light)') : null;

  function choice() {
    try {
      var value = window.localStorage.getItem(KEY);
      return value === 'light' || value === 'system' ? value : 'dark';
    } catch (e) {
      return memory;
    }
  }

  function apply() {
    var picked = choice();
    var light = picked === 'light' || (picked === 'system' && query !== null && query.matches);
    if (light) {
      root.setAttribute('data-motyw', 'jasny');
    } else {
      root.removeAttribute('data-motyw');
    }
    root.setAttribute('data-wybor', picked);
    var colour = document.querySelector('meta[name="theme-color"]');
    if (colour) { colour.setAttribute('content', light ? '#ebe9ef' : '#14161c'); }
  }

  apply();
  if (query !== null && query.addEventListener) { query.addEventListener('change', apply); }
  window.addEventListener('storage', apply);

  window.dpsTheme = {
    get: choice,
    set: function (value) {
      memory = value;
      try {
        if (value === 'dark') { window.localStorage.removeItem(KEY); } else { window.localStorage.setItem(KEY, value); }
      } catch (e) { /* private mode: the choice lasts until the page closes */ }
      apply();
    },
  };
})();
