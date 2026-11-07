/* Report polling is read-only at the provider. OTP requests and retries stay explicit user actions. */
(function () {
  'use strict';
  var panel = document.querySelector('[data-credit-poll]');
  if (!panel || !window.fetch) return;
  var timer = null, pending = false, attempts = 0, errors = 0, stopped = false;
  var message = panel.querySelector('[data-credit-status]');
  function schedule() {
    if (timer !== null) clearTimeout(timer);
    if (stopped || attempts >= 60 || document.hidden) return;
    timer = setTimeout(poll, Math.min(30000, 5000 * Math.pow(2, errors)));
  }
  function poll() {
    if (pending || stopped || document.hidden) return schedule();
    pending = true; attempts += 1;
    var body = '_csrf=' + encodeURIComponent(panel.getAttribute('data-csrf'))
      + '&verification_id=' + encodeURIComponent(panel.getAttribute('data-verification'));
    fetch(panel.getAttribute('data-poll-url'), {method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body})
      .then(function (response) {
        if (response.status === 401 || response.status === 403 || response.status === 419) stopped = true;
        return response.json();
      }).then(function (data) {
        if (message && typeof data.message === 'string') message.textContent = data.message;
        if (data.ok && data.status === 'verified') { stopped = true; window.location.reload(); return; }
        if (typeof data.code === 'string' && /verification_(expired|subject_changed|superseded)/.test(data.code)) {
          stopped = true; window.location.reload(); return;
        }
        errors = data.ok ? 0 : Math.min(errors + 1, 3);
      }).catch(function () {
        errors = Math.min(errors + 1, 3);
        if (message) message.textContent = 'ارتباط موقتاً برقرار نشد؛ بررسی وضعیت دوباره انجام می‌شود.';
      }).then(function () {
        pending = false;
        if (attempts >= 60 && message) message.textContent = 'گزارش هنوز آماده نیست؛ می‌توانید کمی بعد دوباره وضعیت را بررسی کنید.';
        schedule();
      });
  }
  document.addEventListener('visibilitychange', schedule);
  window.addEventListener('pagehide', function () { stopped = true; if (timer !== null) clearTimeout(timer); });
  window.addEventListener('pageshow', function () { stopped = false; schedule(); });
  schedule();
}());
