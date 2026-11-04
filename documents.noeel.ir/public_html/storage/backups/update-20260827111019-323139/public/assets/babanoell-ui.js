(()=>{
  'use strict';

  const ready=(fn)=>document.readyState==='loading'
    ?document.addEventListener('DOMContentLoaded',fn,{once:true})
    :fn();

  ready(()=>{
    document.querySelectorAll('input[type="file"]').forEach((input)=>{
      const form=input.closest('form');
      const meta=form?.querySelector('[data-file-name]');
      input.addEventListener('change',()=>{
        if(!meta) return;
        meta.textContent=input.files&&input.files[0]
          ?input.files[0].name
          :'فایلی انتخاب نشده است';
      });
    });

    document.querySelectorAll('form[data-submit-lock="1"]').forEach((form)=>{
      form.addEventListener('submit',()=>{
        const button=form.querySelector('button[type="submit"]');
        if(!button||button.disabled||button.getAttribute('aria-busy')==='true') return;
        button.dataset.originalText=button.textContent||'';
        button.textContent='در حال انجام…';
        button.setAttribute('aria-busy','true');
        button.disabled=true;
      });
    });
  });
})();
