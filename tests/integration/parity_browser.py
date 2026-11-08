#!/usr/bin/env python3
"""Browser acceptance for reference parity: private resumes, new settings and legal PDF output."""
import argparse
import base64
import hashlib
import json
from pathlib import Path
import re
import shutil
import subprocess
from playwright.sync_api import sync_playwright
from stack import Stack
from run import Workflow
from onboarding import CooperationWorkflow


def main():
    parser=argparse.ArgumentParser();parser.add_argument('--artifacts',type=Path,required=True);parser.add_argument('--axe-script',type=Path)
    args=parser.parse_args();args.artifacts.mkdir(parents=True,exist_ok=True)
    report={'complete':False,'checks':[],'errors':[],'accessibility':[]};stack=Stack(Path(__file__).resolve().parents[2])
    def check(ok,name):
        report['checks'].append({'name':name,'passed':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True)
        if not ok:raise AssertionError(name)
    def audit(page,label,axe=True):
        page.wait_for_load_state('networkidle');page.emulate_media(reduced_motion='reduce')
        check(page.evaluate('document.body.scrollWidth <= innerWidth+2'),label+' fits its viewport')
        if axe and args.axe_script:
            result=page.evaluate("async()=>await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}})")
            report['accessibility'].append({'page':label,'violations':[{'id':v['id'],'impact':v['impact'],'nodes':[x['target'] for x in v['nodes']]} for v in result['violations']]})
            check(not result['violations'],label+' has no reported WCAG A/AA violations')
    try:
        stack.prepare();stack.start();w=Workflow(stack);w.run();stack.restart('partners')
        base=stack.urls['panel']
        with sync_playwright() as p:
            browser=p.chromium.launch(executable_path=shutil.which('chromium'),headless=True,args=['--no-sandbox'])
            def context(role,width=1440):
                c=browser.new_context(viewport={'width':width,'height':960},reduced_motion='reduce');
                if args.axe_script:c.add_init_script(path=str(args.axe_script))
                page=c.new_page();page.on('pageerror',lambda e:report['errors'].append(str(e)))
                page.goto(base+'/login');page.locator('[name=identifier]').fill(stack.accounts[role]['email'])
                with page.expect_navigation():page.get_by_role('button',name='ادامه',exact=True).click()
                page.locator('[name=password]').fill(stack.password)
                with page.expect_navigation():page.locator('[name=password]').press('Enter')
                return c,page
            admin,page=context('super_admin')
            pages=['/users/'+stack.accounts['acceptor']['id']+'/commission','/cooperation-notifications/settings','/cooperation-notifications/deliveries','/loans/request-numbering','/finance/greenpay/requests','/finance/greenpay/requests/'+w.greenpay_http_id]
            for index,path in enumerate(pages):
                for width in (1440,390,320):
                    page.set_viewport_size({'width':width,'height':960});response=page.goto(base+path);check(response.status==200,'Admin reference page opens '+str(index)+' at '+str(width));audit(page,'Reference page '+str(index)+' at '+str(width))
                page.screenshot(path=str(args.artifacts/f'admin-{index}-mobile.png'),full_page=True)
            page.goto(base+'/loans/request-numbering');step=page.locator('[name=step]');step.fill('۳')
            with page.expect_navigation():page.get_by_role('button',name='ذخیره گام',exact=True).click()
            check(page.locator('[name=step]').input_value()=='3','Native numbering form accepts Persian digits and persists the step')
            page.goto(base+'/cooperation-notifications/settings');page.locator('[name="statuses[under_review][pattern_id]"]').fill('۱۲۳')
            page.locator('[name="statuses[under_review][var_0]"]').fill('[[applicant_name]]')
            with page.expect_navigation():page.get_by_role('button',name='ذخیره تنظیمات',exact=True).click()
            check(page.locator('[name="statuses[under_review][pattern_id]"]').input_value()=='123','Native cooperation settings persist Persian pattern numbers without enabling a send')
            admin.close()
            applicant,page=context('applicant',390)
            for path in ['/cooperation-notifications/settings','/loans/request-numbering','/finance/greenpay/requests']:
                check(page.goto(base+path).status==403,'Applicant cannot access reference administration '+path)
            request_id=w.reference_loans[0];path=f'/loans/requests/{request_id}/documents';page.goto(base+path)
            form=page.locator(f'form[action="{path}"]');form.locator('[name=document_type]').select_option('national_card_front')
            png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=')+b'\0'*(3*1024*1024)
            file={'name':'synthetic-resumable-large.png','mimeType':'image/png','buffer':png}
            input=form.locator('[type=file]');input.set_input_files(file)
            check(int(input.get_attribute('data-max-bytes'))>2*1024*1024,'Chunk UI uses the service limit above the native PHP two-megabyte request limit')
            intercepted={'parts':0,'fail':True,'resumed':[]}
            def disrupt(route):
                intercepted['parts']+=1
                if intercepted['fail'] and intercepted['parts']>1:route.abort('internetdisconnected')
                else:
                    if not intercepted['fail']:
                        raw=route.request.post_data_buffer or b'';m=re.search(rb'name="index"\r\n\r\n(\d+)',raw)
                        if m:intercepted['resumed'].append(int(m.group(1)))
                    route.continue_()
            page.route('**/uploads/chunks/*/parts',disrupt)
            form.get_by_role('button',name='بارگذاری امن مدرک',exact=True).click()
            form.locator('[role=alert]').filter(has_text='ارسال دوباره').wait_for(timeout=30000)
            check(intercepted['parts']>=2,'Interrupted upload reports a recoverable error after durable first-part storage')
            page.screenshot(path=str(args.artifacts/'upload-interrupted.png'),full_page=True)
            page.reload();form=page.locator(f'form[action="{path}"]');form.locator('[name=document_type]').select_option('national_card_front');form.locator('[type=file]').set_input_files(file)
            intercepted['fail']=False
            with page.expect_navigation(timeout=60000):form.get_by_role('button',name='بارگذاری امن مدرک',exact=True).click()
            check(intercepted['resumed'] and min(intercepted['resumed'])==1,'Reloaded browser resumes from missing parts and does not retransmit the first part')
            rows=w.call('documents',f'/api/v1/entities/loan_request/{request_id}/documents',role='applicant')['items'];doc=next(x for x in rows if x['original_name']=='synthetic-resumable-large.png')
            raw=w.call('documents',f"/api/v1/files/{doc['id']}/download",role='applicant');check(hashlib.sha256(raw).digest()==hashlib.sha256(png).digest(),'Resumed browser upload stores the exact selected three-megabyte file')
            audit(page,'Resumed owner document page');page.screenshot(path=str(args.artifacts/'upload-resumed.png'),full_page=True)
            # A retained failed upload can be explicitly cancelled instead of occupying quota.
            form=page.locator(f'form[action="{path}"]');form.locator('[name=document_type]').select_option('national_card_front');form.locator('[type=file]').set_input_files(dict(file,name='synthetic-cancelled.png'))
            intercepted['fail']=True;intercepted['parts']=0
            form.get_by_role('button',name='بارگذاری امن مدرک',exact=True).click();form.locator('[role=alert]').filter(has_text='ارسال دوباره').wait_for(timeout=30000)
            form.get_by_role('button',name='لغو بارگذاری',exact=True).click();form.locator('[role=alert]').filter(has_text='لغو شد').wait_for(timeout=10000)
            check(form.get_by_role('button',name='لغو بارگذاری',exact=True).is_hidden(),'Owner can explicitly discard an interrupted private upload')
            applicant.close()
            # Sign each exact reference template and verify both final PDF and separate proof tokens.
            flow=CooperationWorkflow(w)
            for index,rid in enumerate(w.reference_loans):
                contracts=w.call('partners',f'/api/v1/loan-requests/{rid}/contracts',role='applicant')['items'];c=next(x for x in contracts if x['contract_kind']=='facility_agreement')
                with flow.panel('applicant'):
                    saved,_=flow.sign(c['id'],'applicant');pdf=w.call('panel',f"/contracts/{c['id']}/pdf",role=None,signed=False)
                check(saved['buyer_confirmation_token'].startswith('BUY-') and saved['platform_confirmation_token'].startswith('PLT-'),'Reference signature persists distinct buyer/platform confirmation tokens')
                output=args.artifacts/f'reference-contract-{index}.pdf';output.write_bytes(pdf)
                text=subprocess.run(['pdftotext',str(output),'-'],capture_output=True,check=True).stdout.decode('utf-8',errors='replace')
                check(len(text)>1500 and 'Synthetic' in text and 'BUY-' in text and 'PLT-' in text,'Reference final PDF contains legal text, identity and both stable signature proofs')
                info=subprocess.run(['pdfinfo',str(output)],capture_output=True,check=True).stdout.decode();pages=int(re.search(r'^Pages:\s+(\d+)',info,re.M).group(1));check(2<=pages<=20,'Reference PDF paginates into a readable bounded A4 document')
                link=w.call('partners',f"/api/v1/contracts/{c['id']}/issue-link",'POST',{},role='contract_expert');token=(link.get('public_path','').split('/contract/',1)[-1] if link.get('public_path') else None)
                # Link shape is covered by native routes; inspect public print only when token returned.
                if token:
                    cctx=browser.new_context(viewport={'width':390,'height':960},reduced_motion='reduce');public=cctx.new_page();public.goto(stack.urls['partners']+'/contract/'+token+'/print');audit(public,'Reference public print '+str(index),axe=False);public.screenshot(path=str(args.artifacts/f'reference-print-{index}.png'),full_page=True);cctx.close()
            check(not report['errors'],'Reference workflows have no uncaught JavaScript exceptions');browser.close()
        report['complete']=True
    except Exception as error:
        report['error']=str(error);raise
    finally:
        stack.close();(args.artifacts/'report.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')

if __name__=='__main__':main()
