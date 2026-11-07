(()=>{
  'use strict';

  const ready=(fn)=>document.readyState==='loading'
    ?document.addEventListener('DOMContentLoaded',fn,{once:true})
    :fn();

  const latinDigits=(value)=>String(value??'')
    .replace(/[۰-۹]/g,(d)=>String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))
    .replace(/[٠-٩]/g,(d)=>String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)));

  const numeric=(value)=>latinDigits(value).replace(/[٬,\s]/g,'').trim();
  const numberFormat=new Intl.NumberFormat('fa-IR',{maximumFractionDigits:0});

  ready(()=>{
    const menu=document.querySelector('[data-menu-toggle]');
    const sidebar=document.querySelector('[data-panel-sidebar]');
    const overlay=document.querySelector('[data-menu-overlay]');
    const closeMenu=()=>{
      sidebar?.classList.remove('is-open');
      overlay?.classList.remove('is-visible');
      menu?.setAttribute('aria-expanded','false');
    };
    menu?.setAttribute('aria-expanded','false');
    menu?.addEventListener('click',()=>{
      const open=!sidebar?.classList.contains('is-open');
      sidebar?.classList.toggle('is-open');
      overlay?.classList.toggle('is-visible');
      menu.setAttribute('aria-expanded',open?'true':'false');
    });
    overlay?.addEventListener('click',closeMenu);
    document.addEventListener('keydown',(event)=>{ if(event.key==='Escape') closeMenu(); });

    document.querySelectorAll('[data-goftino-open]').forEach((button)=>{
      button.addEventListener('click',()=>{
        let notice=document.getElementById('bn-chat-status');
        if(window.Goftino&&typeof window.Goftino.open==='function'){
          if(notice) notice.hidden=true;
          window.Goftino.open();return;
        }
        if(!notice){
          notice=document.createElement('div');notice.id='bn-chat-status';notice.className='bn-chat-notice';
          notice.setAttribute('role','status');notice.setAttribute('aria-live','polite');document.body.append(notice);
        }
        notice.hidden=false;notice.textContent='چت آنلاین فعلاً در دسترس نیست.';
        button.setAttribute('aria-describedby',notice.id);
      });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((toggle)=>{
      const wrap=toggle.closest('.bn-password-wrap');
      const password=wrap?.querySelector('input[type="password"],input[type="text"]');
      toggle.addEventListener('click',()=>{
        if(!password) return;
        const shouldShow=password.type==='password';
        password.type=shouldShow?'text':'password';
        toggle.textContent=shouldShow?'پنهان':'نمایش';
        toggle.setAttribute('aria-pressed',shouldShow?'true':'false');
      });
    });

    document.querySelectorAll('input[type="file"]').forEach((input)=>{
      const form=input.closest('form');
      const meta=form?.querySelector('[data-file-name]');
      const error=form?.querySelector('[data-file-error]');
      input.addEventListener('change',()=>{
        const file=input.files&&input.files[0]?input.files[0]:null;
        if(meta) meta.textContent=file?file.name:'فایلی انتخاب نشده است';
        if(error){ error.hidden=true; error.textContent=''; }
        const limit=Number(input.dataset.maxBytes||0);
        if(file&&limit>0&&file.size>limit){
          input.value='';
          if(meta) meta.textContent='فایل انتخاب نشده است';
          if(error){ error.textContent=`حجم فایل از سقف مجاز ${numberFormat.format(Math.floor(limit/1048576))} مگابایت بیشتر است.`; error.hidden=false; }
        }
      });
    });

    document.querySelectorAll('form[data-submit-lock="1"]').forEach((form)=>{
      form.addEventListener('submit',(event)=>{
        if(event.defaultPrevented) return;
        const submitter=event.submitter instanceof HTMLButtonElement||event.submitter instanceof HTMLInputElement?event.submitter:null;
        if(submitter?.name){
          let shadow=form.querySelector('input[data-submit-shadow]');
          if(!shadow){ shadow=document.createElement('input'); shadow.type='hidden'; shadow.dataset.submitShadow='1'; form.appendChild(shadow); }
          shadow.name=submitter.name;
          shadow.value=submitter.value;
        }
        const button=submitter instanceof HTMLButtonElement?submitter:form.querySelector('button[type="submit"]');
        if(!button||button.disabled||button.getAttribute('aria-busy')==='true') return;
        button.dataset.originalText=button.textContent||'';
        button.textContent='در حال انجام…';
        button.setAttribute('aria-busy','true');
        button.disabled=true;
      });
    });

    document.querySelectorAll('form[data-intake-wizard]').forEach((form)=>{
      const panes=Array.from(form.querySelectorAll('[data-intake-step]'));
      const nav=form.querySelector('[data-intake-nav]');
      const controls=form.querySelector('[data-intake-controls]');
      if(panes.length!==3||!nav||!controls) return;
      let current=0;
      const review=form.querySelector('[data-intake-review]');
      const refreshReview=()=>{
        if(!review) return;
        const plan=form.elements.namedItem('plan_id');
        const values=[['متقاضی',form.elements.namedItem('full_name')?.value||'تکمیل نشده'],
          ['طرح',plan?.selectedOptions?.[0]?.textContent||'انتخاب نشده'],
          ['مبلغ سفارش',form.elements.namedItem('amount')?.value||'تکمیل نشده'],
          ['تعداد اقساط',form.elements.namedItem('installment_count')?.value||'تکمیل نشده']];
        review.replaceChildren();
        values.forEach(([label,value])=>{const dt=document.createElement('dt');const dd=document.createElement('dd');dt.textContent=label;dd.textContent=value;review.append(dt,dd);});
      };
      const show=(index,focus=false)=>{
        current=Math.max(0,Math.min(2,index));
        panes.forEach((pane,i)=>{pane.hidden=i!==current;});
        nav.querySelectorAll('[data-intake-jump]').forEach((button,i)=>{
          button.classList.toggle('is-current',i===current);
          if(i===current)button.setAttribute('aria-current','step');else button.removeAttribute('aria-current');
        });
        controls.querySelector('[data-intake-prev]').disabled=current===0;
        controls.querySelector('[data-intake-next]').hidden=current===2;
        if(current===2)refreshReview();
        if(focus){const heading=panes[current].querySelector('h2');heading?.setAttribute('tabindex','-1');heading?.focus();}
      };
      const required=['full_name','father_name','certificate_number','national_code','mobile_number','postal_code','residential_address','work_address'];
      required.forEach((name)=>{const field=form.elements.namedItem(name);if(field)field.required=true;});
      const invalidIn=(pane)=>Array.from(pane.querySelectorAll('input,select,textarea')).find((field)=>field.willValidate&&!field.checkValidity());
      const go=(next)=>{const invalid=next>current?invalidIn(panes[current]):null;if(invalid){invalid.reportValidity();return;}show(next,true);};
      nav.querySelectorAll('[data-intake-jump]').forEach((button)=>button.addEventListener('click',()=>go(Number(button.dataset.intakeJump))));
      controls.querySelector('[data-intake-prev]').addEventListener('click',()=>go(current-1));
      controls.querySelector('[data-intake-next]').addEventListener('click',()=>go(current+1));
      form.noValidate=true;
      form.addEventListener('submit',(event)=>{
        if(event.defaultPrevented) return;
        const draft=event.submitter?.value==='draft';
        const candidates=draft?['plan_id','amount','installment_count'].map((name)=>form.elements.namedItem(name)):
          Array.from(form.querySelectorAll('input,select,textarea'));
        const invalid=candidates.find((field)=>field&&field.willValidate&&!field.checkValidity());
        if(invalid){event.preventDefault();const index=panes.findIndex((pane)=>pane.contains(invalid));show(index<0?0:index);invalid.reportValidity();}
      },true);
      nav.hidden=false;controls.hidden=false;show(0);
    });

    // Plan type: keep both field groups in markup, but enable only the selected engine.
    const planType=document.querySelector('[data-plan-type]');
    if(planType){
      const hint=document.querySelector('[data-plan-type-hint]');
      const syncPlanType=(applyDefaults=false)=>{
        const type=planType.value==='leasing'?'leasing':'market';
        document.querySelectorAll('[data-plan-fields]').forEach((group)=>{
          const active=group.getAttribute('data-plan-fields')===type;
          group.hidden=!active;
          group.querySelectorAll('input,select,textarea').forEach((control)=>{ control.disabled=!active; });
        });
        if(applyDefaults){
          try{
            const defaults=JSON.parse(type==='leasing'?(planType.dataset.planDefaultsLeasing||'{}'):(planType.dataset.planDefaultsMarket||'{}'));
            Object.entries(defaults).forEach(([name,value])=>{
              if(name==='requires_bank_validation'){
                const box=document.querySelector('input[type="checkbox"][name="requires_bank_validation"]');
                if(box) box.checked=Boolean(value);
                return;
              }
              const control=document.querySelector(`[name="${name}"]`);
              if(control&&!control.closest('[data-plan-fields]')){
                control.value=Array.isArray(value)?value.join(','):String(value??'');
                control.dispatchEvent(new Event('input',{bubbles:true}));
              }
            });
          }catch(_error){ /* defaults are optional enhancement; server validation remains authoritative */ }
        }
        if(hint) hint.textContent=type==='market'
          ?'اقساط ماهانه با نرخ ثابت طرح بازاری.'
          :'محاسبه لیزینگی با نرخ سود و فاصله سررسید قابل تنظیم.';
      };
      planType.addEventListener('change',()=>syncPlanType(true));
      syncPlanType(false);
    }

    // Financial inputs stay machine-readable; the helper preview makes large values easy to verify.
    document.querySelectorAll('[data-money-input]').forEach((input)=>{
      let preview=input.parentElement?.querySelector('[data-money-preview]');
      if(!preview){
        preview=document.createElement('span');
        preview.className='bn-money-preview';
        preview.dataset.moneyPreview='1';
        input.parentElement?.appendChild(preview);
      }
      const update=()=>{
        const raw=numeric(input.value);
        if(raw!==''&&!Number.isNaN(Number(raw))){
          preview.textContent=`${numberFormat.format(Number(raw))} ریال`;
        }else preview.textContent='';
      };
      input.addEventListener('input',update);
      input.addEventListener('blur',()=>{
        const raw=numeric(input.value);
        if(raw!==''&&!Number.isNaN(Number(raw))) input.value=Number(raw).toLocaleString('en-US',{maximumFractionDigits:0});
        update();
      });
      update();
    });

    const calcPlan=document.querySelector('[data-calculator-plan]');
    if(calcPlan){
      const form=calcPlan.closest('form');
      const interval=form?.querySelector('[data-calculator-interval]');
      const count=form?.querySelector('input[name="installment_count"]');
      const amount=form?.querySelector('input[name="amount"]');
      const hint=form?.querySelector('[data-calculator-plan-hint]');
      const syncCalc=()=>{
        const option=calcPlan.selectedOptions[0];
        if(!option||!option.value){ if(hint) hint.textContent='با انتخاب طرح، محدوده مبلغ و اقساط مجاز نمایش داده می‌شود.'; return; }
        const type=option.dataset.type||'market';
        const installments=(option.dataset.installments||'').split(',').filter(Boolean);
        const min=Number(option.dataset.minAmount||0);
        const max=Number(option.dataset.maxAmount||0);
        const minInterval=Math.max(1,Number(option.dataset.minInterval||1));
        const maxInterval=Math.max(minInterval,Number(option.dataset.maxInterval||1));
        if(amount){ if(min>0) amount.setAttribute('data-min',String(min)); if(max>0) amount.setAttribute('data-max',String(max)); }
        if(count&&installments.length){ count.placeholder=installments.join('، '); }
        if(interval){
          if(type==='market'){ interval.value='1'; interval.readOnly=true; interval.min='1'; interval.max='1'; }
          else { interval.readOnly=false; interval.min=String(minInterval); interval.max=String(maxInterval); if(Number(interval.value)<minInterval||Number(interval.value)>maxInterval) interval.value=String(minInterval); }
        }
        if(hint){
          const range=min&&max?`مبلغ مجاز ${numberFormat.format(min)} تا ${numberFormat.format(max)} ریال. `:'';
          const terms=installments.length?`اقساط فعال: ${installments.map(Number).map((n)=>numberFormat.format(n)).join('، ')}. `:'';
          const gap=type==='leasing'?`فاصله سررسید: ${numberFormat.format(minInterval)} تا ${numberFormat.format(maxInterval)} ماه.`:'فاصله سررسید طرح بازاری یک ماه است.';
          hint.textContent=range+terms+gap;
        }
      };
      calcPlan.addEventListener('change',syncCalc);
      syncCalc();
    }

    document.querySelectorAll('[data-document-review]').forEach((form)=>{
      const status=form.querySelector('[data-review-status]');
      const reason=form.querySelector('[data-review-reason]');
      const sync=()=>{ if(reason) reason.required=status?.value==='rejected'; };
      status?.addEventListener('change',sync); sync();
    });

    document.querySelectorAll('[data-transition-form]').forEach((form)=>{
      const select=form.querySelector('[data-transition-status]');
      const reason=form.querySelector('[data-transition-reason]');
      const reasonMark=form.querySelector('[data-reason-required]');
      const bankField=form.querySelector('[data-bank-user-field]');
      const bankInput=bankField?.querySelector('input');
      const sync=()=>{
        const option=select?.selectedOptions[0];
        const needsReason=option?.dataset.requiresReason==='1';
        const needsBank=option?.dataset.needsBank==='1';
        if(reason){ reason.required=needsReason; }
        if(reasonMark) reasonMark.hidden=!needsReason;
        if(bankField) bankField.hidden=!needsBank;
        if(bankInput) bankInput.required=needsBank;
      };
      select?.addEventListener('change',sync);
      sync();
    });

    document.querySelectorAll('[data-guarantors-form]').forEach((form)=>{
      const container=form.querySelector('[data-guarantors-container]');
      const template=form.querySelector('[data-guarantor-template]');
      const add=form.querySelector('[data-add-guarantor]');
      const reindex=()=>{
        container?.querySelectorAll('[data-guarantor-row]').forEach((row,index)=>{
          row.querySelector('strong')?.replaceChildren(document.createTextNode(`ضامن ${numberFormat.format(index+1)}`));
          row.querySelectorAll('[data-name]').forEach((input)=>{ input.name=`guarantors[${index}][${input.dataset.name}]`; });
          // Existing server-rendered rows have names already; normalize them too.
          row.querySelectorAll('input[name]').forEach((input)=>{
            const match=input.name.match(/\[(full_name|national_code|mobile_number|relation_label)\]$/);
            if(match) input.name=`guarantors[${index}][${match[1]}]`;
          });
        });
      };
      add?.addEventListener('click',()=>{
        if(!container||!template||container.querySelectorAll('[data-guarantor-row]').length>=10) return;
        const fragment=template.content.cloneNode(true);
        container.appendChild(fragment);
        reindex();
      });
      form.addEventListener('click',(event)=>{
        const button=event.target.closest('[data-remove-guarantor]');
        if(!button) return;
        const row=button.closest('[data-guarantor-row]');
        if(!row) return;
        if(container?.querySelectorAll('[data-guarantor-row]').length===1){ row.querySelectorAll('input').forEach((input)=>input.value=''); return; }
        row.remove(); reindex();
      });
      reindex();
    });
  });
})();

/* Noeel 1.5.0 accessibility and navigation behavior, shared by every service. */
(()=>{'use strict';const ready=fn=>document.readyState==='loading'?document.addEventListener('DOMContentLoaded',fn,{once:true}):fn();
ready(()=>{
  let fieldId=0;
  const bindLabels=()=>document.querySelectorAll('.bn-field').forEach((field)=>{
    const label=field.querySelector('label.bn-label'),input=field.querySelector('input:not([type=hidden]),select,textarea');
    if(label&&input&&!label.htmlFor){if(!input.id){do{fieldId++;input.id='noeel-field-'+fieldId;}while(document.querySelectorAll('#'+input.id).length>1);}label.htmlFor=input.id;}
  });
  bindLabels();
  document.querySelectorAll('[data-guarantors-container]').forEach(container=>new MutationObserver(bindLabels).observe(container,{childList:true}));
  document.querySelectorAll('.bn-alert--error').forEach(el=>el.setAttribute('role','alert'));
  document.querySelectorAll('.bn-alert--success').forEach(el=>el.setAttribute('role','status'));
  const sidebar=document.querySelector('[data-panel-sidebar]'),menu=document.querySelector('[data-menu-toggle]'),main=document.querySelector('.bn-panel-main'),overlay=document.querySelector('[data-menu-overlay]');
  if(sidebar&&menu){
    sidebar.id='noeel-sidebar';menu.setAttribute('aria-controls',sidebar.id);let wasOpen=false;
    const close=()=>{sidebar.classList.remove('is-open');overlay?.classList.remove('is-visible');menu.setAttribute('aria-expanded','false');};
    const button=document.createElement('button');button.type='button';button.className='bn-panel-close';button.textContent='بستن منو';button.addEventListener('click',close);sidebar.prepend(button);
    const sync=()=>{const mobile=matchMedia('(max-width:1000px)').matches,open=mobile&&sidebar.classList.contains('is-open');sidebar.inert=mobile&&!open;if(main)main.inert=open;document.body.classList.toggle('bn-menu-open',open);button.hidden=!mobile;
      if(open&&!wasOpen)button.focus();if(!open&&wasOpen)menu.focus();wasOpen=open;
    };
    new MutationObserver(sync).observe(sidebar,{attributes:true,attributeFilter:['class']});addEventListener('resize',sync);sync();
    document.addEventListener('keydown',e=>{if(!wasOpen||e.key!=='Tab')return;const items=[...sidebar.querySelectorAll('a[href],button:not([hidden])')].filter(el=>!el.hasAttribute('disabled'));const first=items[0],last=items.at(-1);if(e.shiftKey&&document.activeElement===first){e.preventDefault();last?.focus();}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first?.focus();}});
  }
});
addEventListener('pageshow',()=>document.querySelectorAll('[data-original-text]').forEach(button=>{button.textContent=button.dataset.originalText;button.disabled=false;button.removeAttribute('aria-busy');}));
})();


/* Noeel UI 1.6.0 — interaction polish */
(()=>{
  'use strict';
  const ready=(fn)=>document.readyState==='loading'?document.addEventListener('DOMContentLoaded',fn,{once:true}):fn();
  ready(()=>{
    const topbar=document.querySelector('.bn-panel-topbar');
    if(topbar){
      const syncTopbar=()=>topbar.classList.toggle('is-scrolled',window.scrollY>8);
      syncTopbar();
      window.addEventListener('scroll',syncTopbar,{passive:true});
    }

    const menu=document.querySelector('[data-menu-toggle]');
    const sidebar=document.querySelector('[data-panel-sidebar]');
    const overlay=document.querySelector('[data-menu-overlay]');
    const close=()=>{
      sidebar?.classList.remove('is-open');
      overlay?.classList.remove('is-visible');
      document.body.classList.remove('bn-menu-open');
      menu?.setAttribute('aria-expanded','false');
    };
    if(menu&&sidebar){
      sidebar.querySelectorAll('a.bn-panel-nav-item').forEach((link)=>link.addEventListener('click',()=>{
        if(window.matchMedia('(max-width:1000px)').matches) close();
      }));
      window.addEventListener('resize',()=>{if(!window.matchMedia('(max-width:1000px)').matches) close();},{passive:true});
    }

    document.querySelectorAll('.bn-panel-nav-item.is-active').forEach((item)=>item.setAttribute('aria-current','page'));
    document.querySelectorAll('.bn-table-wrap').forEach((wrap)=>{
      if(!wrap.hasAttribute('tabindex')) wrap.tabIndex=0;
      if(!wrap.hasAttribute('role')) wrap.setAttribute('role','region');
      if(!wrap.hasAttribute('aria-label')) wrap.setAttribute('aria-label','جدول اطلاعات');
    });

    document.querySelectorAll('.bn-alert').forEach((alert)=>{
      if(!alert.hasAttribute('role')) alert.setAttribute('role',alert.classList.contains('bn-alert--error')?'alert':'status');
    });
  });
})();

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
