/* Resumable private document uploads. Native multipart forms remain usable without JavaScript. */
(() => {
  'use strict';
  if (!window.fetch || !window.crypto?.subtle || !window.File?.prototype.slice || !window.Blob?.prototype.arrayBuffer || !window.TextEncoder) return;
  const wait = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
  const hex = (bytes) => [...new Uint8Array(bytes)].map((x) => x.toString(16).padStart(2, '0')).join('');
  const digest = async (value) => hex(await crypto.subtle.digest('SHA-256', value));
  function target(form) {
    const path = new URL(form.action, location.href).pathname;
    let m = path.match(/^\/loans\/requests\/([a-f0-9]{26})\/documents$/);
    if (m) return {entity_type: 'loan_request', entity_id: m[1], returnTo: path};
    m = path.match(/^\/loans\/requests\/([a-f0-9]{26})\/guarantors\/([a-f0-9]{26})\/documents$/);
    if (m) return {entity_type: 'loan_guarantor', entity_id: m[2], returnTo: `/loans/requests/${m[1]}/documents`};
    m = path.match(/^\/partners\/cooperation\/([a-f0-9]{26})\/documents$/);
    if (m) return {entity_type: 'cooperation_request', entity_id: m[1], returnTo: `/partners/cooperation/${m[1]}`};
    m = path.match(/^\/contracts\/([a-f0-9]{26})\/physical-document$/);
    return m ? {entity_type: 'contract', entity_id: m[1], returnTo: `/contracts/${m[1]}`} : null;
  }
  async function request(path, csrf, body, multipart = false) {
    for (let attempt = 0; ; attempt++) {
      try {
        const response = await fetch(path, {method: body ? 'POST' : 'GET', credentials: 'same-origin',
          headers: multipart ? {'Accept': 'application/json'} : {'Accept': 'application/json', 'Content-Type': 'application/json'},
          body: body ? (multipart ? body : JSON.stringify({...body, _csrf: csrf})) : undefined});
        let payload;
        try { payload = await response.json(); }
        catch (_) { const error = new Error(response.redirected && new URL(response.url).pathname === '/login' ? 'نشست شما پایان یافته است؛ دوباره وارد سامانه شوید.' : 'پاسخ سرویس کامل نشد؛ دوباره تلاش کنید.'); error.status = response.redirected ? 401 : (response.status >= 400 ? response.status : 502); throw error; }
        if (!response.ok || payload.ok === false) {
          const error = new Error(payload.error?.message || 'بارگذاری کامل نشد.');
          error.status = response.status; throw error;
        }
        return payload.data;
      } catch (error) {
        if (attempt >= 2 || (error.status && error.status < 500)) throw error;
        await wait(800 * (attempt + 1));
      }
    }
  }
  function storage(key, value) {
    try { if (value === undefined) return localStorage.getItem(key); if (value === null) localStorage.removeItem(key); else localStorage.setItem(key, value); } catch (_) { return null; }
  }
  function initialize() {
    document.querySelectorAll('form[enctype="multipart/form-data"]').forEach((form) => {
      const destination = target(form), input = form.querySelector('input[type="file"][name="file"]');
      if (!destination || !input || form.dataset.chunkUpload) return;
      form.dataset.chunkUpload = '1';
      if (input.dataset.chunkMaxBytes) input.dataset.maxBytes = input.dataset.chunkMaxBytes;
      const notice = document.createElement('div'); notice.className = 'bn-field bn-field--full';
      const status = document.createElement('p'); status.className = 'bn-helper'; status.setAttribute('role', 'status');
      status.textContent = 'در صورت قطع ارتباط، فایل را دوباره انتخاب و ارسال کنید تا بارگذاری از قطعه‌های دریافت‌شده ادامه یابد.';
      const progress = document.createElement('progress'); progress.max = 100; progress.value = 0; progress.hidden = true;
      progress.setAttribute('aria-label', 'پیشرفت بارگذاری مدرک');
      const cancel = document.createElement('button'); cancel.type = 'button'; cancel.className = 'bn-btn bn-btn--secondary bn-btn--small'; cancel.textContent = 'لغو بارگذاری'; cancel.hidden = true;
      notice.append(status, progress, cancel); form.append(notice);
      let busy = false, cancelRequested = false, activeSession = null;
      cancel.addEventListener('click', async () => {
        if (busy) { cancelRequested = true; cancel.disabled = true; status.textContent = 'در حال لغو بارگذاری…'; return; }
        if (!activeSession) return;
        cancel.disabled = true;
        try { await request('/uploads/chunks/' + activeSession.id + '/abort', activeSession.csrf, {}); storage(activeSession.key, null); activeSession = null; progress.hidden = true; cancel.hidden = true; status.textContent = 'بارگذاری لغو شد.'; }
        catch (error) { status.textContent = error.message || 'لغو کامل نشد؛ دوباره تلاش کنید.'; }
        finally { cancel.disabled = false; }
      });
      // Capture before the common submit-lock listener; validation still runs natively.
      form.addEventListener('submit', async (event) => {
        if (event.defaultPrevented || !input.files?.length) return;
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        const file = input.files[0], csrf = String(new FormData(form).get('_csrf') || '');
        const fields = {};
        for (const name of ['document_type', 'visibility', 'owner_user_id', 'relation_code']) {
          const value = new FormData(form).get(name); if (value !== null) fields[name] = String(value);
        }
        if (destination.entity_type === 'contract') fields.document_type = 'signed_contract';
        busy = true; cancelRequested = false; cancel.disabled = false; status.setAttribute('role', 'status'); form.setAttribute('aria-busy', 'true');
        const button = event.submitter; if (button) button.disabled = true;
        progress.hidden = false; status.textContent = 'در حال بررسی فایل…';
        let rememberKey = '', upload = null;
        try {
          if (file.size > 100 * 1024 * 1024) throw new Error('اندازه فایل بیش از محدودهٔ بارگذاری است.');
          const checksum = await digest(await file.arrayBuffer());
          rememberKey = 'bn-upload-' + await digest(new TextEncoder().encode(JSON.stringify([destination.entity_type, destination.entity_id, fields, file.name, file.size, checksum])));
          let remembered = null; try { remembered = JSON.parse(storage(rememberKey) || 'null'); } catch (_) {}
          if (remembered?.id && /^[a-f0-9]{26}$/.test(remembered.id)) {
            try { upload = (await request('/uploads/chunks/' + remembered.id, csrf)).upload; }
            catch (error) { if ([403, 404, 410, 422].includes(error.status)) { storage(rememberKey, null); remembered = null; } else throw error; }
          }
          if (!upload) {
            const clientKey = /^[a-f0-9]{64}$/.test(remembered?.client_key || '') ? remembered.client_key : hex(crypto.getRandomValues(new Uint8Array(32)));
            storage(rememberKey, JSON.stringify({client_key: clientKey}));
            upload = (await request('/uploads/chunks', csrf, {entity_type: destination.entity_type, entity_id: destination.entity_id,
              fields, file_name: file.name, total_bytes: file.size, checksum_sha256: checksum, client_key: clientKey})).upload;
            storage(rememberKey, JSON.stringify({client_key: clientKey, id: upload.id}));
          }
          activeSession = {id: upload.id, key: rememberKey, csrf}; cancel.hidden = upload.status === 'complete';
          if (upload.status !== 'complete') {
            const received = new Set(upload.received_parts);
            for (let index = 0; index < upload.total_chunks; index++) {
              if (cancelRequested) throw new Error('بارگذاری لغو شد.');
              if (!received.has(index)) {
                const part = new FormData(); part.set('_csrf', csrf); part.set('index', String(index));
                part.set('file', file.slice(index * upload.chunk_size, (index + 1) * upload.chunk_size), 'part.bin');
                await request('/uploads/chunks/' + upload.id + '/parts', csrf, part, true); received.add(index);
              }
              progress.value = Math.round(received.size * 100 / upload.total_chunks);
              status.textContent = `بارگذاری ${progress.value.toLocaleString('fa-IR')}٪`;
            }
            if (cancelRequested) throw new Error('بارگذاری لغو شد.');
            status.textContent = 'در حال بررسی صحت و ثبت مدرک…';
            await request('/uploads/chunks/' + upload.id + '/complete', csrf, {});
          }
          storage(rememberKey, null); status.textContent = 'مدرک ثبت شد.'; location.assign(destination.returnTo);
        } catch (error) {
          if (cancelRequested && activeSession) {
            try { await request('/uploads/chunks/' + activeSession.id + '/abort', csrf, {}); storage(rememberKey, null); activeSession = null; cancel.hidden = true; progress.hidden = true; status.textContent = 'بارگذاری لغو شد.'; }
            catch (abortError) { status.textContent = abortError.message || 'لغو کامل نشد؛ دوباره تلاش کنید.'; }
          } else status.textContent = (error.message || 'ارتباط قطع شد.') + ' با ارسال دوبارهٔ همین فایل، ادامهٔ بارگذاری بررسی می‌شود.';
          status.setAttribute('role', 'alert');
          if ([409, 410].includes(error.status) && rememberKey) storage(rememberKey, null);
        } finally { busy = false; cancel.disabled = false; form.removeAttribute('aria-busy'); if (button) button.disabled = false; }
      }, true);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize); else initialize();
})();
