#!/usr/bin/env python3
"""Smart login/registration UI with a signed local SMS receiver and no external requests."""
import argparse,contextlib,json,shutil
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright
from stack import Stack
from run import Workflow
from smart_auth import SmartAuthFixture

def main():
 p=argparse.ArgumentParser();p.add_argument('--artifacts',type=Path,required=True);p.add_argument('--axe-script',type=Path);a=p.parse_args();a.artifacts.mkdir(parents=True,exist_ok=True)
 report={'complete':False,'checks':[],'accessibility':[],'errors':[]};s=Stack(Path(__file__).resolve().parents[2])
 def check(ok,name):
  report['checks'].append({'name':name,'passed':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True)
  if not ok:raise AssertionError(name)
 def audit(page,label,axe=True):
  page.evaluate('document.fonts.ready');check(page.evaluate('document.body.scrollWidth<=innerWidth+2'),label+' fits the viewport')
  check('Yekan Bakh FaNum' in page.evaluate('getComputedStyle(document.body).fontFamily'),label+' uses the local Yekan font')
  if a.axe_script and axe:
   page.evaluate(a.axe_script.read_text());v=page.evaluate("async()=>{const r=await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}});return r.violations.map(x=>({id:x.id,impact:x.impact,nodes:x.nodes.map(n=>n.target)}))}")
   report['accessibility'].append({'page':label,'violations':v});check(not v,label+' has no reported WCAG A/AA violations')
 def click(page,name):
  with page.expect_navigation():page.get_by_role('button',name=name,exact=True).click()
 try:
  s.prepare();s.start();w=Workflow(s);w.initialize()
  with contextlib.closing(SmartAuthFixture(w)) as f:
   setting=w.call('identity','/api/v1/admin/auth-settings')['settings'];w.call('identity','/api/v1/admin/auth-settings','POST',dict(setting,sms_pattern_id=123))
   with sync_playwright() as p:
    browser=p.chromium.launch(executable_path=shutil.which('chromium'),args=['--no-sandbox'])
    for index,(width,js) in enumerate([(1440,True),(390,True),(320,True),(390,False)]):
     c=browser.new_context(viewport={'width':width,'height':1000},java_script_enabled=js,reduced_motion='reduce');c.route('**/*',lambda r:r.continue_() if urlsplit(r.request.url).hostname=='127.0.0.1' else r.abort());page=c.new_page();page.on('pageerror',lambda e:report['errors'].append(str(e)));base=s.urls['panel'];page.goto(base+'/login')
     audit(page,f'Identify {width} JS={js}',js);check(page.locator('input:not([type=hidden])').count()==1,'First stage has one visible input')
     page.locator('[name=identifier]').fill('browser.smart'+str(index));click(page,'ادامه');audit(page,f'Registration {width} JS={js}',js)
     page.locator('[name=display_name]').fill('Synthetic browser applicant');page.locator('[name=mobile]').fill('0938777711'+str(index));click(page,'تأیید اطلاعات و دریافت کد');audit(page,f'OTP {width} JS={js}',js)
     check(page.locator('[name=code]').get_attribute('autocomplete')=='one-time-code','OTP supports mobile code autofill');check(page.locator('[name=challenge_id]').count()==0,'Challenge stays in the server session')
     page.screenshot(path=str(a.artifacts/f'otp-{width}-js-{js}.png'),full_page=True)
     page.locator('[name=code]').fill('000000');click(page,'تأیید و ورود');check(page.locator('[role=alert]').count()==1 and page.locator('[name=code]').count()==1,'Wrong code remains on the same accessible OTP step')
     page.locator('[name=code]').fill(f.code());click(page,'تأیید و ورود');check(not page.url.endswith('/login'),'Verified passwordless signup enters the personal dashboard')
     c.close()
    # Existing password account, native settings, and back navigation.
    c=browser.new_context(viewport={'width':390,'height':1000},reduced_motion='reduce');page=c.new_page();page.goto(s.urls['panel']+'/login');page.locator('[name=identifier]').fill(s.accounts['super_admin']['email']);click(page,'ادامه');audit(page,'Existing password stage')
    check(page.locator('[name=code]').count()==0 and page.locator('[name=password]').count()==1,'Existing password account receives only its password step');page.locator('[name=password]').fill(s.password);click(page,'ورود به حساب');page.goto(s.urls['panel']+'/system/auth-settings');audit(page,'Native auth settings');page.locator('[name=sms_pattern_id]').fill('۴۵۶');click(page,'ذخیره تنظیمات');check(page.locator('[name=sms_pattern_id]').input_value()=='456','Native settings persist the OTP pattern without sending an SMS');c.close();browser.close()
  check(not report['errors'],'No browser JavaScript errors');report['complete']=True
 finally:s.close();(a.artifacts/'browser-audit.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
if __name__=='__main__':main()
