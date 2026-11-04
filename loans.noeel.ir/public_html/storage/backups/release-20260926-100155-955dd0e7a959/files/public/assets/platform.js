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
