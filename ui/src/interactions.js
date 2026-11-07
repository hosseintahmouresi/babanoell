/* Progressive enhancement only: server permissions, validation and forms stay authoritative. */
(() => {
  'use strict';
  const ready = (fn) => document.readyState === 'loading'
    ? document.addEventListener('DOMContentLoaded', fn, {once: true}) : fn();
  const digits = (value) => new Intl.NumberFormat('fa-IR', {maximumFractionDigits: 0}).format(value);
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const icon = (name) => {
    const element = document.createElement('i');
    element.className = `bn-icon bn-icon--${name}`;
    element.setAttribute('aria-hidden', 'true');
    return element;
  };

  ready(() => {
    // Small entrance cues never conceal content, including when JS or observers fail.
    const candidates = [...document.querySelectorAll('.bn-panel-content > .bn-section,.bn-stat,.bn-action-card')];
    if (!reduced.matches && 'IntersectionObserver' in window) {
      const observer = new IntersectionObserver((entries) => entries.forEach(({isIntersecting, target}) => {
        if (!isIntersecting) return;
        target.classList.add('bn-revealed');
        observer.unobserve(target);
      }), {threshold: 0.04});
      candidates.forEach((element, index) => {
        element.style.setProperty('--bn-stagger', `${Math.min(index % 4, 3) * 35}ms`);
        observer.observe(element);
      });
      reduced.addEventListener('change', () => {
        if (reduced.matches) observer.disconnect();
      });
    }

    let id = 0;
    const identify = (element, prefix) => {
      if (!element.id) {
        do { id += 1; element.id = `${prefix}-${id}`; }
        while (document.querySelectorAll(`#${CSS.escape(element.id)}`).length > 1);
      }
      return element.id;
    };
    const describe = (input, description) => {
      const ids = new Set((input.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
      ids.add(identify(description, 'bn-description'));
      input.setAttribute('aria-describedby', [...ids].join(' '));
    };
    const fieldError = (input) => {
      if (!(input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement)) return;
      if (!input.willValidate || input.validity.valid) return;
      const field = input.closest('.bn-field') || input.parentElement;
      if (!field) return;
      const key = identify(input, 'bn-control');
      let error = document.getElementById(`${key}-error`);
      if (!error) {
        error = document.createElement('span'); error.id = `${key}-error`;
        error.className = 'bn-field-error'; error.setAttribute('aria-live', 'polite');
        field.append(error);
      }
      const validity = input.validity;
      error.textContent = validity.valueMissing ? 'این بخش را تکمیل کنید.'
        : validity.typeMismatch ? 'مقدار واردشده را با قالب درست وارد کنید.'
        : validity.tooLong ? 'متن واردشده از حد مجاز طولانی‌تر است.'
        : validity.rangeUnderflow ? `مقدار باید حداقل ${digits(Number(input.min))} باشد.`
        : validity.rangeOverflow ? `مقدار باید حداکثر ${digits(Number(input.max))} باشد.`
        : validity.patternMismatch ? 'قالب مقدار واردشده درست نیست؛ راهنمای این بخش را بررسی کنید.'
        : 'مقدار این بخش را بررسی و اصلاح کنید.';
      input.setAttribute('aria-invalid', 'true'); describe(input, error);
    };
    document.addEventListener('invalid', (event) => fieldError(event.target), true);
    document.addEventListener('input', (event) => {
      const input = event.target;
      if (!input.validity || !input.validity.valid || input.getAttribute('aria-invalid') !== 'true') return;
      input.removeAttribute('aria-invalid');
      const error = document.getElementById(`${input.id}-error`);
      if (error) error.remove();
      const descriptions = (input.getAttribute('aria-describedby') || '').split(/\s+/).filter((entry) => entry !== `${input.id}-error`);
      if (descriptions.length) input.setAttribute('aria-describedby', descriptions.join(' '));
      else input.removeAttribute('aria-describedby');
    });
    document.querySelectorAll('.bn-field').forEach((field) => {
      const input = field.querySelector('input:not([type=hidden]),textarea,select');
      const helper = field.querySelector('.bn-helper,.bn-field-hint');
      if (input && helper) describe(input, helper);
    });

    // Tables keep their semantics; keyboard users receive a focused scroll region only if needed.
    document.querySelectorAll('.bn-table-wrap').forEach((wrap) => {
      const hint = document.createElement('span');
      hint.className = 'bn-scroll-hint'; hint.hidden = true;
      hint.append(icon('arrow-left'), document.createTextNode('برای دیدن ادامه جدول، به طرفین حرکت کنید.'));
      wrap.after(hint);
      const update = () => {
        const overflow = wrap.scrollWidth > wrap.clientWidth + 2;
        wrap.classList.toggle('is-overflowing', overflow); hint.hidden = !overflow;
        if (overflow) { wrap.tabIndex = 0; describe(wrap, hint); }
        else { wrap.removeAttribute('tabindex'); wrap.removeAttribute('aria-describedby'); }
      };
      if ('ResizeObserver' in window) new ResizeObserver(update).observe(wrap);
      update();
    });

    // Dragging a file selects it locally, never uploads it until the user submits the form.
    document.querySelectorAll('.bn-upload-box').forEach((box) => {
      const input = box.querySelector('input[type=file]'); if (!input) return;
      let depth = 0;
      box.addEventListener('dragenter', (event) => { event.preventDefault(); depth += 1; box.classList.add('is-dragging'); });
      box.addEventListener('dragover', (event) => event.preventDefault());
      box.addEventListener('dragleave', () => { depth -= 1; if (depth <= 0) box.classList.remove('is-dragging'); });
      box.addEventListener('drop', (event) => {
        event.preventDefault(); depth = 0; box.classList.remove('is-dragging');
        if (input.disabled || !event.dataTransfer?.files.length) return;
        const transfer = new DataTransfer();
        [...event.dataTransfer.files].slice(0, input.multiple ? undefined : 1).forEach((file) => transfer.items.add(file));
        input.files = transfer.files; input.dispatchEvent(new Event('change', {bubbles: true}));
      });
    });

    // Applicant entry is one normal form with a three-stage, accessible enhancement.
    document.querySelectorAll('form[data-guided-form]').forEach((form) => {
      const panes = [...form.querySelectorAll('[data-guided-step]')];
      const actions = form.querySelector('.bn-form-actions');
      if (panes.length < 2 || !actions) return;
      let current = 0;
      const nav = document.createElement('nav'); nav.className = 'bn-guided-nav'; nav.setAttribute('aria-label', 'مراحل ثبت درخواست');
      const controls = document.createElement('div'); controls.className = 'bn-guided-controls';
      const back = document.createElement('button'); back.type = 'button'; back.className = 'bn-btn bn-btn--secondary'; back.append(icon('arrow-right'), document.createTextNode('مرحله قبل'));
      const next = document.createElement('button'); next.type = 'button'; next.className = 'bn-btn bn-btn--primary'; next.append(document.createTextNode('ادامه'), icon('arrow-left'));
      const progress = document.createElement('span'); progress.className = 'bn-guided-status'; progress.setAttribute('role', 'status');
      const finalButton = actions.querySelector('button[value=submit]');
      const invalidIn = (pane) => [...pane.querySelectorAll('input,select,textarea')].find((field) => field.willValidate && !field.checkValidity());
      const show = (index, focus = false) => {
        current = Math.max(0, Math.min(panes.length - 1, index));
        panes.forEach((pane, n) => { pane.hidden = n !== current; });
        [...nav.children].forEach((button, n) => {
          if (n === current) button.setAttribute('aria-current', 'step'); else button.removeAttribute('aria-current');
          button.classList.toggle('is-complete', n < current);
        });
        back.disabled = current === 0; next.hidden = current === panes.length - 1;
        if (finalButton) finalButton.hidden = current !== panes.length - 1;
        progress.textContent = `مرحله ${digits(current + 1)} از ${digits(panes.length)}`;
        if (focus) {
          const heading = panes[current].querySelector('h2');
          heading?.setAttribute('tabindex', '-1'); heading?.focus({preventScroll: true});
          nav.scrollIntoView({block: 'start', behavior: reduced.matches ? 'instant' : 'smooth'});
        }
      };
      const go = (index) => {
        if (index > current) {
          for (let n = 0; n < index; n += 1) {
            const invalid = invalidIn(panes[n]);
            if (invalid) { show(n, true); fieldError(invalid); invalid.focus({preventScroll: true}); return; }
          }
        }
        show(index, true);
      };
      panes.forEach((pane, n) => {
        const button = document.createElement('button'); button.type = 'button';
        const number = document.createElement('span'); number.className = 'bn-guided-index'; number.textContent = digits(n + 1);
        button.append(number, document.createTextNode(pane.dataset.title || `مرحله ${digits(n + 1)}`));
        button.addEventListener('click', () => go(n)); nav.append(button);
      });
      controls.append(back, progress, next); form.prepend(nav); actions.before(controls);
      back.addEventListener('click', () => go(current - 1)); next.addEventListener('click', () => go(current + 1));
      form.noValidate = true;
      form.addEventListener('submit', (event) => {
        const draft = event.submitter?.value === 'draft';
        const fields = draft ? ['plan_id', 'amount', 'installment_count'].map((name) => form.elements.namedItem(name))
          : [...form.querySelectorAll('input,select,textarea')];
        const invalid = fields.find((field) => field?.willValidate && !field.checkValidity());
        if (invalid) {
          event.preventDefault(); const index = panes.findIndex((pane) => pane.contains(invalid));
          show(index < 0 ? current : index, true); fieldError(invalid); invalid.focus({preventScroll: true});
        } else if (!draft && current !== panes.length - 1) { event.preventDefault(); go(current + 1); }
      }, true);
      show(0);
      // Keep server errors visible and reopen the first field with an invalid value after a failed POST.
      if (form.dataset.serverError === '1') show(panes.length - 1);
    });

    document.querySelectorAll('[data-assignment-form]').forEach((form) => {
      const mode = form.elements.namedItem('acceptor_assignment_mode');
      const merchant = form.elements.namedItem('merchant_id');
      const market = [...form.querySelectorAll('[data-marketplace-fields]')];
      const summary = form.elements.namedItem('request_public_summary');
      const sync = () => {
        const open = mode?.value === 'open_acceptor_marketplace';
        if (merchant) { merchant.disabled = open; merchant.closest('.bn-field').hidden = open; }
        if (summary) { summary.disabled = !open; summary.closest('.bn-field').hidden = !open; }
        market.forEach((group) => {
          group.hidden = !open;
          group.querySelectorAll('input,select,textarea').forEach((input) => { input.disabled = !open; });
        });
      };
      mode?.addEventListener('change', sync); sync();
    });

    // Navigation remains native. No page is hidden and no artificial delay is introduced.
    document.addEventListener('click', (event) => {
      const link = event.target.closest('a[href]');
      if (!link || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey
        || link.target || link.hasAttribute('download')) return;
      const url = new URL(link.href, location.href);
      if (url.pathname === location.pathname && url.hash.startsWith('#transfer-')) {
        const details = document.getElementById(url.hash.slice(1));
        if (details instanceof HTMLDetailsElement) {
          details.open = true; details.querySelector('summary')?.focus({preventScroll: true});
        }
      }
      if (url.origin === location.origin && url.pathname !== location.pathname && !url.pathname.includes('/ui-asset.php')) document.body.classList.add('bn-navigating');
    });
  });
  addEventListener('pageshow', () => document.body.classList.remove('bn-navigating'));
})();
