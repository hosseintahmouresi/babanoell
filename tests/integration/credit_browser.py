#!/usr/bin/env python3
"""Credit completion in Chromium over seven real disposable services; no external provider calls."""
from pathlib import Path
import argparse, hashlib, json, shutil
import base64
from playwright.sync_api import sync_playwright
from stack import Stack
from run import Workflow
from notifications import notification_acceptance

def run(stack, report, output, axe_script=None):
    w=Workflow(stack);w.initialize();w.credit_fixture.mode('ready')
    plan=w.call('loans','/api/v1/plans','POST',{'code':'CREDIT-BROWSER','name':'Synthetic credit browser',
        'type':'market','settings':{'min_amount':1,'max_amount':100000000,'active_installments':[6],
        'fixed_monthly_rate':2,'prepayment_percent':20},'rules':{'requires_bank_validation':True}},expected=201)['plan']
    w.call('loans','/api/v1/plans/'+plan['id']+'/status','POST',{'status':'active'})
    def create():
        return w.call('loans','/api/v1/requests','POST',{'plan_id':plan['id'],'submit':True,
            'applicant_user_id':stack.accounts['applicant']['id'],'full_name':'Synthetic browser applicant',
            'father_name':'Synthetic','certificate_number':'1','national_code':'0013543318',
            'mobile_number':'09121234567','birth_date':'1370/01/01','occupation':'other',
            'residential_address':'Synthetic address','work_address':'Synthetic work','postal_code':'1234567890',
            'requested_amount':1000000,'installment_count':6,'installment_interval':1,
            'first_due_date':stack.first_due,'order_items':[{'name':'Synthetic product','qty':1,'price':1000000}]},expected=201)['request']
    loan=create();case='/credit/requests/'+loan['id'];base=stack.urls['panel']
    def check(ok,name):
        report['checks'].append({'name':name,'passed':bool(ok)})
        if not ok:raise AssertionError(name)
    def accessibility(page,name):
        if axe_script:
            # Audit settled content, not a frame midway through the optional entrance animation.
            page.emulate_media(reduced_motion='reduce')
            page.evaluate('document.fonts.ready')
            page.evaluate(axe_script.read_text())
            violations=page.evaluate("async()=>{const r=await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}});return r.violations.map(v=>({id:v.id,nodes:v.nodes.map(n=>({target:n.target,summary:n.failureSummary}))}))}")
            report.setdefault('accessibility',[]).append({'page':name,'violations':violations})
            check(not violations,'WCAG A/AA checks: '+name)
    def login(page,role):
        page.goto(base+'/login');page.locator('[name=identifier]').fill(stack.accounts[role]['email'])
        page.locator('[name=password]').fill(stack.password)
        with page.expect_navigation():page.locator('[name=password]').press('Enter')
    def submit(page,action):
        with page.expect_navigation():page.locator('form[action="'+case+'/'+action+'"] button').click()
    with sync_playwright() as p:
        browser=p.chromium.launch(executable_path=shutil.which('chromium'),args=['--no-sandbox','--disable-dev-shm-usage'])
        for js in (True,False):
            if not js:loan=create();case='/credit/requests/'+loan['id']
            context=browser.new_context(java_script_enabled=js,viewport={'width':390,'height':844})
            page=context.new_page();page.on('pageerror',lambda e:report['errors'].append(str(e)))
            login(page,'applicant');page.goto(base+'/applicant/requests/'+loan['id'])
            check(page.locator('a[href^="'+case+'"]').count()>0,'Owner credit entry is visible; JS='+str(js))
            page.goto(base+case)
            check(page.locator('a[href="/applicant/requests/'+loan['id']+'"]').count()>0,'Admin-created applicant case returns to the personal workspace')
            response=context.request.post(base+case+'/poll',form={'_csrf':'invalid','verification_id':'a'*26})
            check(response.status==419,'Polling requires the session CSRF token')
            submit(page,'request');check(page.locator('[name=otp]').count()==1,'Explicit request displays OTP entry')
            page.locator('[name=otp]').fill('000000');submit(page,'otp')
            check(page.locator('.bn-alert--error').count()==1,'Incorrect OTP stays on an actionable form')
            if js:w.credit_fixture.mode('processing')
            page.locator('[name=otp]').fill('۱۲۳۴۵۶');submit(page,'otp')
            check(page.locator('[data-credit-poll]').count()==1,'OTP alone stays pending')
            if js:
                w.credit_fixture.mode('ready')
                page.locator('form[action="'+case+'/submit"]').wait_for(timeout=25000)
            else:submit(page,'refresh')
            check(page.locator('form[action="'+case+'/submit"]').count()==1,'Final report enables final submission; JS='+str(js))
            # axe requires browser timers; the no-JS variant exercises the native forms and download separately.
            if js:accessibility(page,'Credit report')
            for width in (1440,390,320):
                page.set_viewport_size({'width':width,'height':844})
                check(page.evaluate('document.body.scrollWidth<=innerWidth+2'),'Report has no page overflow at '+str(width))
            with page.expect_download() as event:page.get_by_role('link',name='دریافت فایل گزارش',exact=True).click()
            download=event.value;download.save_as(output/('credit-'+str(js)+'.json'))
            raw=(output/('credit-'+str(js)+'.json')).read_bytes()
            api=w.call('loans','/api/v1/requests/'+loan['id']+'/credit',role='applicant')
            vid=api['verification_requests'][0]['id']
            expected=w.call('loans','/api/v1/requests/'+loan['id']+'/credit/reports/'+vid,role='applicant')['sha256']
            check(hashlib.sha256(raw).hexdigest()==expected,'Browser download preserves canonical evidence bytes')
            if js:page.screenshot(path=str(output/'credit-mobile.png'),full_page=True)
            submit(page,'submit')
            row=w.call('loans','/api/v1/requests/'+loan['id'],role='applicant')['request']
            check(row['status']=='under_review','Owner completes report and submits; JS='+str(js))
            context.close()
        context=browser.new_context();page=context.new_page();login(page,'acceptor')
        response=page.goto(base+case);check(response.status==403,'Unrelated acceptor cannot view the credit page')
        response=page.goto(base+'/notifications/settings');check(response.status==403,'Notification configuration is not exposed to acceptors')
        context.close()
        paper=create()
        contract=w.call('partners','/api/v1/loan-requests/'+paper['id']+'/contracts','POST',{'contract_kind':'loan_application'},role='contract_expert',expected=201)['contract']
        context=browser.new_context(viewport={'width':390,'height':844});page=context.new_page();page.on('pageerror',lambda e:report['errors'].append(str(e)));login(page,'contract_expert')
        page.goto(base+'/contracts/'+contract['id'])
        scan=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=')
        page.locator('[name=file]').set_input_files({'name':'synthetic-signed-scan.png','mimeType':'image/png','buffer':scan})
        with page.expect_navigation():page.get_by_role('button',name='بارگذاری نسخهٔ امضاشده',exact=True).click()
        check(page.locator('[name=physical_file] option').count()==2,'Contract manager can upload an immutable physical signature scan from Panel')
        accessibility(page,'Physical signature form')
        check(not page.locator('[name=confirmed]').is_checked(),'Physical signature confirmation is explicit and initially unchecked')
        for width in (1440,390,320):
            page.set_viewport_size({'width':width,'height':844});check(page.evaluate('document.body.scrollWidth<=innerWidth+2'),'Physical contract form fits '+str(width))
        value=page.locator('[name=physical_file] option').nth(1).get_attribute('value');page.locator('[name=physical_file]').select_option(value);page.locator('[name=confirmed]').check()
        with page.expect_navigation(timeout=90000):page.get_by_role('button',name='تأیید امضای فیزیکی',exact=True).click()
        check(page.get_by_role('link',name='دریافت نسخهٔ دارای امضای فیزیکی',exact=True).count()==1,'Physical signature and generated certificate remain distinct in Panel')
        with page.expect_download() as event:page.get_by_role('link',name='دریافت نسخهٔ دارای امضای فیزیکی',exact=True).click()
        event.value.save_as(output/'physical-scan.png');check((output/'physical-scan.png').read_bytes()==scan,'Browser receives the exact pinned physical scan through a one-use capability')
        page.screenshot(path=str(output/'physical-contract-mobile.png'),full_page=True)
        page.goto(base+'/contracts/'+contract['id']+'/print');page.evaluate('document.fonts.ready')
        check('Yekan Bakh FaNum' in page.evaluate('getComputedStyle(document.body).fontFamily') and page.evaluate('document.fonts.check(\'13px "Yekan Bakh FaNum"\')'),'Internal contract print uses the exact local Yekan font')
        context.close()
        notification_acceptance(w)
        context=browser.new_context(viewport={'width':390,'height':844});page=context.new_page();page.on('pageerror',lambda e:report['errors'].append(str(e)));login(page,'super_admin')
        for path in ['/notifications','/notifications/deliveries','/notifications/settings']:
            response=page.goto(base+path);check(response.status==200,'Notification page renders: '+path)
            accessibility(page,path)
            for width in (1440,390,320):
                page.set_viewport_size({'width':width,'height':844})
                check(page.evaluate('document.body.scrollWidth<=innerWidth+2'),'Notification page fits '+str(width)+': '+path)
        page.goto(base+'/notifications/deliveries');page.locator('a[href^="#reconcile-"]').first.click()
        check(page.locator('details[open] form[action$="/reconcile"]').count()==1,'Uncertain delivery action opens its reconciliation form')
        accessibility(page,'Uncertain delivery reconciliation')
        check(not page.locator('details[open] [name=confirmed]').is_checked(),'Provider reconciliation requires explicit confirmation')
        response=context.request.post(base+'/notifications/settings',form={'_csrf':'invalid'})
        check(response.status==403,'Template writes require CSRF')
        page.goto(base+'/notifications/settings');page.locator('[name="statuses[under_review][pattern_id]"]').fill('۱۲۳')
        with page.expect_navigation():page.get_by_role('button',name='ذخیره تنظیمات',exact=True).click()
        settings=w.call('loans','/api/v1/admin/notifications/settings')['settings']
        check(settings['loan_statuses']['under_review']['pattern_id']==123 and not settings['loan_statuses']['under_review']['enabled'],'Template form normalizes Persian digits and leaves SMS disabled')
        context.close()
        merchant=w.call('partners','/api/v1/merchants','POST',{'code':'BROWSER-REFERRAL','name':'Synthetic browser referral','owner_user_id':stack.accounts['acceptor']['id'],'status':'active'},expected=201)['merchant']
        invitation=w.call('partners','/api/v1/me/invitation','POST',{},role='acceptor')['invitation']
        context=browser.new_context(viewport={'width':390,'height':844});page=context.new_page();page.on('pageerror',lambda e:report['errors'].append(str(e)))
        page.goto(base+invitation['path']);check(page.url.endswith('/login'),'Guest invitation enters the internal login flow')
        page.locator('[name=identifier]').fill(stack.accounts['applicant']['email']);page.locator('[name=password]').fill(stack.password)
        with page.expect_navigation():page.locator('[name=password]').press('Enter')
        check(page.url.endswith('/applicant/invitation') and page.locator('[name=code]').input_value()==invitation['code'],'Login preserves the intended invitation code')
        with page.expect_navigation():page.get_by_role('button',name='ثبت دعوت و ادامهٔ درخواست',exact=True).click()
        check(page.locator('select[name=merchant_id]').is_disabled() and page.locator('input[type=hidden][name=merchant_id]').input_value()==merchant['id'],'Invitation fixes the selected merchant and keeps a submit-capable hidden value')
        accessibility(page,'Applicant form with a referral lock')
        for width in (1440,390,320):
            page.set_viewport_size({'width':width,'height':844});check(page.evaluate('document.body.scrollWidth<=innerWidth+2'),'Locked applicant form fits '+str(width))
        context.close();browser.close()
    check(not report['errors'],'Credit page has no JavaScript errors')

def main():
    parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--artifacts',type=Path,required=True);parser.add_argument('--axe-script',type=Path);args=parser.parse_args()
    args.artifacts.mkdir(parents=True,exist_ok=True)
    report={'complete':False,'checks':[],'errors':[]};stack=Stack(Path(__file__).resolve().parents[2])
    try:stack.prepare();stack.start();run(stack,report,args.artifacts,args.axe_script);report['complete']=True
    finally:stack.close();(args.artifacts/'report.json').write_text(json.dumps(report,ensure_ascii=False,indent=2))
if __name__=='__main__':main()
