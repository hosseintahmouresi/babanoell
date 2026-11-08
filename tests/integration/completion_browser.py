#!/usr/bin/env python3
"""Native detailed report, CSV, account plan selection and completion evidence browser checks."""
import argparse,csv,io,json,shutil
from pathlib import Path
from playwright.sync_api import sync_playwright
from stack import Stack
from run import Workflow
from plan_documents import plan_document_acceptance

def main():
 p=argparse.ArgumentParser();p.add_argument('--artifacts',type=Path,required=True);p.add_argument('--axe-script',type=Path);a=p.parse_args();a.artifacts.mkdir(parents=True,exist_ok=True)
 s=Stack(Path(__file__).resolve().parents[2]);report={'complete':False,'checks':[],'accessibility':[],'errors':[]}
 def check(ok,name):
  report['checks'].append({'name':name,'passed':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True)
  if not ok:raise AssertionError(name)
 def audit(page,label):
  page.evaluate('document.fonts.ready');check(page.evaluate('document.body.scrollWidth<=innerWidth+2'),label+' fits its viewport')
  if a.axe_script:
   page.evaluate(a.axe_script.read_text());v=page.evaluate("async()=>{const r=await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}});return r.violations.map(x=>({id:x.id,impact:x.impact,nodes:x.nodes.map(n=>n.target)}))}")
   report['accessibility'].append({'page':label,'violations':v});check(not v,label+' has no reported WCAG A/AA violations')
 def click(page,name):
  with page.expect_navigation():page.get_by_role('button',name=name,exact=True).click()
 try:
  s.prepare();s.start();w=Workflow(s);w.initialize();loan=plan_document_acceptance(w)
  s.command(s.php_command()+[str(Path(__file__).with_name('fixtures')/'request-reports.php'),str(s.root/'fixture.json'),'seed',loan['id']],'Seed synthetic report browser rows')
  with sync_playwright() as p:
   browser=p.chromium.launch(executable_path=shutil.which('chromium'),args=['--no-sandbox'])
   def context(role):
    c=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce',accept_downloads=True);page=c.new_page();page.on('pageerror',lambda e:report['errors'].append(str(e)));page.goto(s.urls['panel']+'/login');page.locator('[name=identifier]').fill(s.accounts[role]['email']);click(page,'ادامه');page.locator('[name=password]').fill(s.password);click(page,'ورود به حساب');return c,page
   c,page=context('super_admin');base=s.urls['panel'];paths=['/reports/requests','/users/'+s.accounts['acceptor']['id']+'/plans','/loans/plans/'+loan['plan_id']+'/edit']
   for n,path in enumerate(paths):
    for width in (1440,390,320):
     page.set_viewport_size({'width':width,'height':1000});r=page.goto(base+path);check(r.status==200,f'New management page {n} opens at {width}');audit(page,f'Management {n} at {width}')
    page.screenshot(path=str(a.artifacts/f'new-management-{n}-mobile.png'),full_page=True)
   page.goto(base+paths[1]);box=page.locator('[name="disabled_plan_ids[]"]').first;chosen=box.get_attribute('value');box.check();click(page,'ذخیره طرح‌های غیرفعال');check(page.locator('[name="disabled_plan_ids[]"][value="'+chosen+'"]').is_checked(),'Native account selector persists an explicit plan exclusion');page.locator('[name="disabled_plan_ids[]"]').uncheck();click(page,'ذخیره طرح‌های غیرفعال');check(page.locator('[name="disabled_plan_ids[]"]:checked').count()==0,'Clearing the native selector restores the allow-all account policy');c.close()
   c,page=context('applicant');page.goto(base+'/reports/requests');page.locator('[name=q]').fill('RPT219-CASE');page.locator('[name=date_from]').fill('۱۴۰۵/۰۱/۰۱');page.locator('[name=date_to]').fill('1405/01/01');click(page,'نمایش')
   check(not page.locator('.bn-report-advanced').evaluate('(e)=>e.open'),'Advanced report filters start collapsed without an active advanced filter')
   check(page.locator('.bn-report-stats .bn-stat-value--compact').evaluate_all('(nodes)=>nodes.every(e=>e.scrollWidth<=e.clientWidth+1)'), 'Large exact report totals remain readable on one line')
   check(page.locator('tbody tr').count()==3,'Native Tehran date filters return exactly the three owned matching cases');check('۹,۰۰۷,۱۹۹,۲۵۴,۷۴۰,۹۹۳.۲۵' in page.locator('body').inner_text(),'Native report displays the exact large amount without floating point rounding')
   with page.expect_download() as download:page.locator('a[href^="/reports/requests/export.csv"]').click()
   raw=Path(download.value.path()).read_bytes();rows=list(csv.reader(io.StringIO(raw.decode('utf-8-sig'))));check(len(rows)==4 and any('9007199254740993.25' in r for r in rows),'Browser CSV download retains the same filtered rows and exact amount')
   check(any("'=SUM(1,1)" in r for r in rows),'CSV text formula protection survives the native browser download')
   page.locator('[name=settlement_status]').select_option('settled');click(page,'نمایش');check(page.locator('tbody tr').count()==1 and 'RPT219-CASE-1' in page.locator('tbody').inner_text(),'Native settlement filter combines correctly with date and ownership')
   for width in (1440,390,320):
    page.set_viewport_size({'width':width,'height':1000});page.goto(base+'/loans/requests/'+loan['id']+'/documents');audit(page,f'Frozen evidence checklist at {width}');check('Synthetic original instruction readable' in page.locator('body').inner_text(),'Owner sees frozen document guidance');check(page.locator('option[value=completed_check_files]').count()==0,'Disabled completed checks stay out of the upload selector')
   page.screenshot(path=str(a.artifacts/'frozen-documents-mobile.png'),full_page=True);c.close();browser.close()
  check(not report['errors'],'No JavaScript errors in the new workflows');report['complete']=True
 finally:s.close();(a.artifacts/'browser-audit.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
if __name__=='__main__':main()
