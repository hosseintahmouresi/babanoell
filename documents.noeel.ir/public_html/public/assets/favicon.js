/* Two PNGs embedded in this local script enhance the native GIF/static fallbacks. */
(function () {
  'use strict';
  var link = document.querySelector('link[data-bn-favicon]');
  if (!link || document.documentMode || link.getAttribute('data-ready') === '1') return;
  link.setAttribute('data-ready', '1');

  var urls = ['data:image/png;base64,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', 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAYAAABzenr0AAAH9UlEQVR42rVXbYhcVxl+nnM/52N3skk2CamtxcK2NJFWt6AYhR0aWhJ/tBh6QarSViWWCv7wrzo70giCf6QRjVTagr9m0GpbSTepTjBCRTdNsqYfWhtpadym+zkzO7N7P855/XFnZu9uNtE/nuHCzJxz73nO+z7v8z4X2DQqFVH/y3+9wd6FWk2szZMiYokIM2uvGfamG0jSvDD91s7i0J4jtrJ3RFH37MF9PNubk817AGBNxApIffpvcxN+IT+WxPFliLpAcj7zXLkhABFRAOTlmSt3uE5+qpAv3mK0hu0M4+WZ2SdJfldEFEmTBVyvQwWknrp49QnH9Y47lgMYQRxHC79/Y/HZJAorJDsZEOwBBwAMQluv10lSSPtH+XzxluXFhdV2cznstFtJLlf8ztTM3DhJU6vVrGxagoB66tLcscLQ8PEoDJPW8lLY6XSSJNY7ikOlbyvH/cPpN5o70nsqKrv5IC99dI1/iR+1P3zHUtYerbUAIMG4OFyy263lb91/1+6fNBpiz83VJQgCXavVrJF99z5dLAw90mouxb0DUQQgISKISyPb/ebSwqlXn//pocnJSfSisDECJEVEOHErIjGYtx2XAGKSWiACwFKK7wLA0AQYBIGuNS4Vt+8/+ILv5x5pNZdW+5unzxsczm0tLYbDpZH7Dhx54ghJ02g0NpA1kwIokkaUedJ2HObyBd+2HWfH6C6/3Vw6G3vLpxuNhj0O6KmZufGR3XvfHN01cthog1y+mOtFMoWbZSmhkiQRI+ZrADAxMWGuScF6uVVUtVo1J1+78oCbzz8OwTYK/6g9c+zgx0ZadUC9Pjkpnz7y+Nd9vzi2ttppK9IXkY87tnNYa20kHQSBHu/Fsm1L62S2aWEs2L97JVsVvLbmUxBbVr0IQSWbeAQAOHVp/lHbsp+OwlCyzyUhpLKMmLY4vP3QnbtmswDUZlGpVqtSqTRsEVG1mlgiYgNAn8kQw1qtZp2YFufEtDjTIk5DxL5v/85nojD8i+fnLZC6TwQRAFQAuSq26mwGrjaJigCQarWcTJ45o4KAmmTyypsLzxkdPQZSTpyDXcdDGLkM88o4zGXArL79dkosSlNZm0STNI7rCgX/PDy2s1WpVFRWlLaU2EajYVfL5aR2+p3SqUvzv9m1e/tXRCdrADDWhtQD6iCgrpM6IPXhsbHw5PnZfbbjH1hb7RoIVO/ogMB4nk9RfD4l4aS6rhQDwIkT0065fE/86+l3bxvKFX/lOt5dy4urmspKY3or7JMX525zLFpRFMG1fVsr80kF9X0IisaYZHAwkcTL5bx2a/l9T9tP9/qCvl4KUKmIOnr0nvjF6SufG8oP/8m23Ls6nZVuSqKUWOHK4igpfzWC1y3LntGiL/pe/hkBbo6iNQ1ApYkUyReKrkAincRfKn9iZLler6vNPWEQgYqImgTkUw9+8LDrer8QI153bSUmlH1NBzKAYVoMAmM6nbZO5YdKBKCicWxXxXH0amdt5RsPjN88U6vVrCAI9JbNqN8F73/vvRzp/RikF0VhTCoLgqS3EQEg4hod2AAIs646VlpDBPpiREKArqXcXpQf2rKyN6Rg3rIoIt0kSYSA4kBW12Pgii8EE4EkJBOCCcAEgIZIulKgojAUx3buzfm58787P/vNIKDOeAZuCWBv7+bstqbP5p40OYpKOc7IcGm7XRgqeYXhkjtU2ubmcgUnVV4CBEiy2+nEcRzp0rYdT7104eqXMyBkyypYmFNMzw1IRiQFAlFKAMBTerEZ4vFmc9GnwIAgSR9A2fNz90dRqI0ZuCDLGG26nRVtK/XUyTc+fOXQnfggq4Q2bjCYcV0iWgDgwB07uyR/tsXyH56amf+e5+er3c5KAkIRBIQqjuJkeGSk1G4tPwbw2JkzYgNIbghAJCPpTHkGAD8/B3VielqNtcflH0PpzAEfrNeROAtnfrC2ff9XHce5JUmSBIASCKjIOAwFgsMAjk1MrGvBBg7sGN0jYvp7ZlIg683135df59EXx3V5AvroPUiOvghd34dkYgKqXC4nYkzLsuxB6lI1FBXHMQHcOnVhttD3H9cAWJj7gKIIAbHBtoBQUAJQJkb3GVRpQApAQZVmEpBymclLr139jOP5t0fhmgbWnXSfzCLixirn3lCKsZXtJUBCiQhPvg1LRMwkgH11cHQU/O1b87mTF+fKyrKOG21sA2j222GvSymlYAxbe6LSSrYON3jCqQuzhdBYf7dt5yajYw0BQYhSimJkDpSlvufLWC8AKLiu/xGtEyRxrElu8hlMcoWis9ptv/D5u3c9WKuJFQTUW0ZAqQz2XhlorWHZzm7bsnZLNjS9NWIMwnAtSQ0LuZm/IgLLskiqXwLA6OgZ/tcUcMCD1OIarXVk9EDqBrILgGmk1MCNKkL6S4zExeFtXru1/Ofu3aPPVyqiymUm1/UDxsiGCmDvk4ZeFAAFUqWKmV7C9bQMyoXQYiQpDpe8MFx9H5b7cEBqTN7g1SxMjCjL1iKiRaDX916vhV4L7GVgfVqYVS7afi5n2baLtbB7trO0/OgXPvvRyxURVc28WV0DwLtpL6PZ+e2F4W1WHIVWlkvr3yXj9bAhVoBAJwniKFpJkuRcFEfPHto/8lxKg42vdRsA9HW5uQdr3lX5YqfTzOlIp+KndaqC/T62xU/VKyNQace15hzPvXzw9sKVrNHZavP/66iIqEajYfeSc93xHwF+MZcgjt89AAAAAElFTkSuQmCC'];
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
