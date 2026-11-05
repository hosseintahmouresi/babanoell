/* Bank-to-Babanoell settlement batch management. No acceptor cycle API used. */
jQuery(function ($) {
  $('.mlr-granted-settlement-dashboard').each(function () {
    const $panel = $(this);
    const cfg = $panel.data('bankCycle') || {};
    if (!cfg.ajax_url || !cfg.nonce) return;
    const chosen = new Set();
    const $box = $panel.find('.mlr-bank-cycle-builder');
    const $note = $box.find('.mlr-bank-cycle-message');
    const $date = $box.find('.mlr-bank-cycle-date');
    const $confirm = $box.find('.mlr-bank-cycle-submit');
    $panel.find('thead th').first().html('<label class="mlr-bank-cycle-select-all-label"><input type="checkbox" class="mlr-bank-cycle-select-all" aria-label="انتخاب همه پرونده‌های تسویه‌نشده در فهرست"> انتخاب</label>');
    let busy = false;

    function message(str, error) {
      $note.text(str || '').toggleClass('is-error', !!error).attr('role', 'status');
    }
    function renderCount() {
      $box.find('.mlr-bank-cycle-selected-count').text(chosen.size.toLocaleString('fa-IR'));
      $box.find('.mlr-bank-cycle-selected-ids').text(chosen.size ? ('پرونده‌های انتخاب‌شده: ' + Array.from(chosen).sort((a,b)=>Number(a)-Number(b)).join('، ')) : '');
      $confirm.prop('disabled', busy || chosen.size === 0);
      $panel.find('.mlr-bank-cycle-check').each(function () {
        $(this).prop('checked', chosen.has(String(this.value)));
      });
      const visible = $panel.find('.mlr-bank-cycle-check').toArray();
      const allChecked = visible.length > 0 && visible.every(el => el.checked);
      $panel.find('.mlr-bank-cycle-select-all').prop('checked', allChecked);
    }
    $panel.on('change', '.mlr-bank-cycle-check', function () {
      if (this.checked) chosen.add(String(this.value)); else chosen.delete(String(this.value));
      if (chosen.size > 100) { chosen.delete(String(this.value)); this.checked = false; message('در هر سیکل حداکثر ۱۰۰ پرونده انتخاب کنید.', true); }
      else message('');
      renderCount();
    });
    $panel.on('change', '.mlr-bank-cycle-select-all', function () {
      const mark = this.checked;
      $panel.find('.mlr-bank-cycle-check').each(function () {
        if (mark && chosen.size < 100) { chosen.add(String(this.value)); }
        if (!mark) chosen.delete(String(this.value));
      });
      renderCount();
    });
    $box.on('click', '.mlr-bank-cycle-clear', function () { chosen.clear(); renderCount(); message(''); });
    try {
      if ($.fn.persianDatepicker) {
        $date.persianDatepicker({format:'YYYY/MM/DD', autoClose:true});
      }
    } catch (err) { /* manual Jalali input remains available */ }

    // Existing expert dashboard refreshes its table using AJAX. Reapply selection after refresh.
    const tableBody = $panel.find('#mlr-requests-table-body')[0];
    if (tableBody && window.MutationObserver) {
      const observer = new MutationObserver(function () { renderCount(); });
      observer.observe(tableBody, {childList: true});
    }

    $box.on('click', '.mlr-bank-cycle-submit', function () {
      if (busy || !chosen.size) return;
      const date = $.trim($date.val());
      const notes = $.trim($box.find('.mlr-bank-cycle-note').val());
      if (!date) { message('ابتدا تاریخ تسویه بانک را انتخاب کنید.', true); $date.focus(); return; }
      if (!window.confirm('سیکل تسویه بانک با بابانوئل برای ' + chosen.size + ' پرونده با تاریخ ' + date + ' ثبت شود؟ این ثبت وضعیت تسویه پرونده‌های انتخابی را تغییر می‌دهد.')) return;
      busy = true; renderCount(); message('در حال ثبت امن سیکل تسویه...');
      $.ajax({url:cfg.ajax_url, type:'POST', dataType:'json', data:{
        action:'mlr_create_bank_settlement_cycle', nonce:cfg.nonce,
        request_ids:Array.from(chosen), settlement_date:date, notes:notes
      }}).done(function (res) {
        if (res && res.success && res.data) {
          chosen.clear();
          message('سیکل #' + res.data.cycle_id + ' با موفقیت ثبت شد. مشاهده جزئیات: منوی «سیکل‌های تسویه بانک» در مدیریت.');
          $box.find('.mlr-bank-cycle-note').val('');
          $panel.find('#mlr-filter-btn').trigger('click');
        } else { message(res && res.data && res.data.message ? res.data.message : 'ثبت سیکل موفق نبود.', true); }
      }).fail(function (xhr) {
        let txt = 'ارتباط با سرور برقرار نشد؛ پیش از تلاش مجدد وضعیت سیکل را در مدیریت بررسی کنید.';
        if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) txt = xhr.responseJSON.data.message;
        message(txt, true);
      }).always(function () { busy = false; renderCount(); });
    });
    renderCount();
  });
});
