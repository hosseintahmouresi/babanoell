/* Two PNGs embedded in this local script enhance the native GIF/static fallbacks. */
(function () {
  'use strict';
  var link = document.querySelector('link[data-bn-favicon]');
  if (!link || document.documentMode || link.getAttribute('data-ready') === '1') return;
  link.setAttribute('data-ready', '1');

  var urls = ['data:image/png;base64,{{NAVY_PNG}}', 'data:image/png;base64,{{PALE_PNG}}'];
  var motion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  var timer = null;
  var frame = 0;
  var loaded = 0;
  var failed = false;
  var suspended = false;
  var images = [];
  var active = false;

  function stop() {
    if (timer !== null) window.clearInterval(timer);
    timer = null;
  }
  function show(index) {
    if (!active) {
      // Once decoding succeeds, keep one candidate: browsers may otherwise prefer
      // the 16px static ICO over the animated PNG even after its href changes.
      var fallbacks = document.querySelectorAll('link[data-bn-favicon-fallback]');
      for (var n = 0; n < fallbacks.length; n += 1) fallbacks[n].parentNode.removeChild(fallbacks[n]);
      link.removeAttribute('media');
      active = true;
    }
    frame = index;
    link.setAttribute('type', 'image/png');
    // Embedded images avoid even browsers that re-fetch a cached favicon on every change.
    link.setAttribute('href', urls[index]);
  }
  function sync() {
    stop();
    if (suspended || failed || loaded !== 2) return;
    if (motion && motion.matches) { show(0); return; }
    show(0);
    timer = window.setInterval(function () { show(1 - frame); }, 500);
  }

  if (motion) {
    if (motion.addEventListener) motion.addEventListener('change', sync);
    else if (motion.addListener) motion.addListener(sync);
  }
  window.addEventListener('pagehide', function () { suspended = true; stop(); });
  window.addEventListener('pageshow', function () { suspended = false; sync(); });
  sync();
  urls.forEach(function (url) {
    var image = new Image();
    images.push(image);
    image.onload = function () { loaded += 1; sync(); };
    image.onerror = function () { failed = true; stop(); };
    image.src = url;
  });
}());
