#!/usr/bin/env python3
"""Exercise real role pages, responsive layouts, local fonts and UI behavior in Chromium.

Creates a disposable seven-service stack unless --state points to an already running
synthetic Stack fixture. Screenshots and reports contain synthetic data only.
"""
import json,re,sys,hashlib,urllib.request,urllib.error,atexit,argparse,shutil
from stack import Stack
from run import Workflow
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright
parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--artifacts',type=Path,required=True)
parser.add_argument('--state',type=Path,help='private state of an existing disposable fixture; omit for a fresh stack')
parser.add_argument('--axe-script',type=Path,help='optional local axe-core 4.10.3 axe.min.js')
parser.add_argument('--chromium',default=shutil.which('chromium') or shutil.which('google-chrome'))
args=parser.parse_args()
if not args.chromium:parser.error('Install Chromium or pass --chromium')
work=args.artifacts;work.mkdir(parents=True,exist_ok=True)
owned_stack=None
if args.state:
 state=json.loads(args.state.read_text())
else:
 owned_stack=Stack(Path(__file__).resolve().parents[2]);atexit.register(owned_stack.close)
 owned_stack.prepare();owned_stack.start();Workflow(owned_stack).run();owned_stack.restart('partners')
 # Referral binding has its own browser acceptance; keep the original free-selection UI controls covered here.
 owned_stack.command(owned_stack.php_command()+[str(Path(__file__).with_name('fixtures')/'referral-state.php'),str(owned_stack.root/'fixture.json'),'expire-lock',owned_stack.accounts['applicant']['id']],'Reset synthetic referral window for general UI audit')
 state={'urls':owned_stack.urls,'accounts':owned_stack.accounts,'password':owned_stack.password,'root':str(owned_stack.root)}
base=state['urls']['panel'];report={'pages':[],'interactions':[],'errors':[]};shots=work/'screenshots';shots.mkdir(exist_ok=True)
roles={
 'super_admin':['/dashboard','/loans','/loans/plans','/loans/plans/new','/loans/calculator','/loans/requests','/loans/requests/new','/loans/tasks','/loans/tasks/report','/loans/tasks/settings','/documents','/partners','/partners/merchants','/partners/merchants/new','/partners/cooperation','/partners/cooperation/new','/partners/cooperation/form-settings','/partners/marketplace','/partners/marketplace/settings','/finance','/finance/bank-cycles','/finance/cycles','/finance/receipts','/finance/reconciliation','/finance/greenpay','/users','/users/new','/reports','/reports/history','/system/health','/system/settings','/system/updates','/bank'],
 'applicant':['/applicant','/applicant/calculator','/applicant/requests','/applicant/requests/new','/partners/cooperation','/partners/cooperation/new'],
 'acceptor':['/acceptor','/acceptor/calculator','/acceptor/requests','/acceptor/requests/new','/partners/marketplace'],
 'operations_expert':['/dashboard','/loans/tasks','/loans/requests','/partners/cooperation','/documents'],
 'bank_expert':['/bank','/loans/tasks'],
 'finance_expert':['/finance','/finance/bank-cycles','/finance/cycles','/finance/receipts','/finance/reconciliation','/documents'],
 'contract_expert':['/dashboard','/partners/cooperation'],
 'admin':['/dashboard','/loans/requests','/partners/cooperation','/finance']}
atexit.register(lambda: (work/'browser-audit.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)))
def check(condition,label):
 report['interactions'].append({'name':label,'passed':bool(condition)})
 if not condition:report['errors'].append(label)
# An unknown asset path cannot select a file outside the gateway allowlist.
for service,url in state['urls'].items():
 for name in ['YekanBakhFaNum-Regular.woff','YekanBakhFaNum-SemiBold.woff','YekanBakhFaNum-Bold.woff','noeel-icons.woff2']:
  response=urllib.request.build_opener(urllib.request.ProxyHandler({})).open(url+'/ui-asset.php?file='+name)
  body=response.read();expected=(Path(__file__).resolve().parents[2]/'ui/fonts'/name).read_bytes()
  check(body==expected,service+': local '+name+' bytes match the packaged font')
 try:
  urllib.request.build_opener(urllib.request.ProxyHandler({})).open(url+'/ui-asset.php?file=../config/local.env.php')
  check(False,service+': gateway rejects traversal')
 except urllib.error.HTTPError as error:check(error.code==404,service+': gateway rejects traversal')

def login(page,role,email=None):
 page.goto(base+'/login',wait_until='load')
 page.locator('[name=identifier]').fill(email or state['accounts'][role]['email']);page.locator('[name=password]').fill(state['password']);
 with page.expect_navigation(wait_until='load'):page.locator('[name=password]').press('Enter')
 if page.url.endswith('/login'):raise RuntimeError('Login failed: '+role)
def visit(page,role,path,viewport,axe=False,shot=False):
 page.set_viewport_size(viewport)
 response=page.goto(base+path,wait_until='load');page.evaluate('document.fonts.ready')
 data=page.evaluate('''() => ({width:innerWidth,body:document.body.scrollWidth,font:getComputedStyle(document.body).fontFamily,title:document.title,emoji:/[\\u{1F300}-\\u{1FAFF}]/u.test(document.body.innerText),overflows:[...document.querySelectorAll('input:not([type=hidden]),select,textarea')].filter(e=>e.getClientRects().length&&(!e.closest('[inert]'))).filter(e=>{const r=e.getBoundingClientRect();return r.left < -2 || r.right > innerWidth+2}).map(e=>e.name||e.tagName)})''')
 entry={'role':role,'path':re.sub(r'/contract/[A-Za-z0-9_-]{30,}','/contract/<token>',path),'viewport':viewport,'status':response.status,'metrics':data}
 if data['body']>data['width']+2:report['errors'].append(f'Body overflow {role} {path} {viewport["width"]}: {data["body"]}')
 if data['overflows']:report['errors'].append(f'Field overflow {role} {path} {viewport["width"]}: {data["overflows"]}')
 if data['emoji']:report['errors'].append(f'Emoji icon in {role} {path}')
 if 'Yekan Bakh FaNum' not in data['font']:report['errors'].append(f'Unexpected font in {path}')
 if response.status>=500:report['errors'].append(f'Server error {response.status} {role} {path}')
 if axe and args.axe_script and response.status==200:
  page.evaluate(args.axe_script.read_text())
  result=page.evaluate('''async()=>{const r=await axe.run(document,{runOnly:{type:'tag',values:['wcag2a','wcag2aa','wcag21aa']}});return r.violations.map(x=>({id:x.id,impact:x.impact,nodes:x.nodes.map(n=>({target:n.target,summary:n.failureSummary})).slice(0,8)}))}''')
  entry['accessibility']=result
 if shot:
  name=role+'-'+re.sub(r'[^a-zA-Z0-9_-]','-',path).strip('-')+'-'+str(viewport['width'])+'.png'
  page.screenshot(path=str(shots/name),full_page=True);entry['screenshot']=name
 report['pages'].append(entry)
 return entry
def focused_checks(browser):
  c=browser.new_context(viewport={'width':390,'height':844},reduced_motion='reduce');page=c.new_page();login(page,'super_admin');page.goto(base+'/finance/greenpay',wait_until='load');page.locator('a[href^="#transfer-"]').first.click()
  check(page.locator('.bn-transfer[open]').count()==1,'Transfer action opens from its table link')
  fields=page.locator('.bn-transfer[open] input:not([type=hidden]),.bn-transfer[open] select,.bn-transfer[open] textarea').evaluate_all('(nodes)=>nodes.map(e=>({name:e.name,left:e.getBoundingClientRect().left,right:e.getBoundingClientRect().right}))')
  check(all(x['left']>=0 and x['right']<=390 for x in fields),'Opened transfer inputs fit the mobile viewport')
  check(not page.locator('input[name=confirm]').is_checked(),'Financial confirmation remains unchecked')
  page.screenshot(path=str(work/'greenpay-mobile.png'),full_page=True);c.close()
  c=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='no-preference');page=c.new_page();login(page,'applicant',email='new_cooperation_individual@example.test')
  check(page.locator('.bn-workspace-switch a').count()==2,'Dual-role account has two explicit workspaces')
  page.locator('.bn-workspace-switch a[href="/applicant"]').click();page.wait_for_load_state('load')
  check(page.locator('.bn-workspace-switch a[aria-current]').get_attribute('href')=='/applicant','Personal workspace stays selected for dual-role accounts')
  check(page.locator('a[href="/applicant/requests/new"]').count()>0,'Personal request entry is reachable after workspace switch')
  page.goto(base+'/applicant',wait_until='domcontentloaded');page.evaluate('document.fonts.ready');page.wait_for_timeout(650)
  fonts=page.evaluate('[...document.fonts].filter(f=>f.family.includes("Yekan Bakh")).map(f=>({weight:f.weight,status:f.status}))')
  check(all(f['status']=='loaded' for f in fonts),'All three selected Yekan Bakh weights load locally')
  duration=page.locator('.bn-pagehead').evaluate('(e)=>getComputedStyle(e).animationDuration')
  check(duration!='0s','Motion is enabled under the standard preference')
  page.emulate_media(reduced_motion='reduce');check(page.locator('.bn-pagehead').evaluate('(e)=>getComputedStyle(e).animationName')=='none','Reduced-motion preference disables the entrance animation')
  page.screenshot(path=str(work/'dual-role-desktop.png'),full_page=True);c.close()
  c=browser.new_context(java_script_enabled=False,reduced_motion='reduce',viewport={'width':390,'height':844});page=c.new_page();login(page,'applicant');page.goto(base+'/applicant/requests/new');check(page.locator('[data-guided-step]:visible').count()==3,'Applicant form keeps all stages available without JavaScript');check(page.locator('button[value=draft]').get_attribute('formnovalidate') is not None,'Draft save does not require final identity fields without JavaScript');c.close()
  c=browser.new_context(viewport={'width':390,'height':844},reduced_motion='reduce');page=c.new_page();fake='Z'*43
  response=page.goto(state['urls']['partners']+'/contract/'+fake,wait_until='load');check(response.status==404,'Expired contract link has the expected status');check('Yekan Bakh FaNum' in page.evaluate('getComputedStyle(document.body).fontFamily'),'Expired contract view uses the local product font')
  with page.expect_navigation(wait_until='load') as nav:
   page.evaluate('(action)=>{const f=document.createElement("form");f.method="POST";f.action=action;document.body.append(f);f.submit()}',state['urls']['partners']+'/contract/'+fake+'/otp/request')
  check(nav.value.status==409,'Invalid public OTP request has the expected status');check('Yekan Bakh FaNum' in page.evaluate('getComputedStyle(document.body).fontFamily'),'OTP error view uses the same local font');page.screenshot(path=str(work/'contract-error-mobile.png'),full_page=True);c.close()

with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=args.chromium,args=['--no-sandbox','--disable-dev-shm-usage'])
 for role,paths in roles.items():
  context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce')
  context.route('**/*',lambda route:route.continue_() if urlsplit(route.request.url).hostname in ['127.0.0.1','localhost'] else route.abort())
  page=context.new_page();page.on('pageerror',lambda error:report['errors'].append('JS '+str(error)))
  login(page,role)
  extra=set()
  for path in paths:
   e=visit(page,role,path,{'width':1440,'height':1000},axe=True,shot=path in ['/dashboard','/applicant','/acceptor','/bank','/finance','/loans/plans/new','/partners/marketplace/settings'])
   for href in page.locator('a[href]').evaluate_all('(nodes)=>nodes.map(e=>e.getAttribute("href"))'):
    if re.search(r'^/(?:applicant|acceptor|loans)/requests/[a-f0-9]{26}(?:/edit)?$|^/partners/(?:merchants|cooperation)/[a-f0-9]{26}(?:/marketplace|/edit)?$|^/documents/files/[a-f0-9]{26}$|^/finance/(?:cycles|bank-cycles)/[a-f0-9]{26}$|^/bank/cases/[a-f0-9]{26}$|^/contracts/(?:loan/)?[a-f0-9]{26}$',href or ''):extra.add(href)
  for path in sorted(extra)[:18]:
   visit(page,role,path,{'width':1440,'height':1000},axe=True,shot=False)
  for path in paths:
   visit(page,role,path,{'width':390,'height':844},axe=False,shot=path in ['/dashboard','/applicant','/acceptor','/applicant/requests/new','/bank','/finance'])
  visit(page,role,paths[0],{'width':768,'height':1024})
  visit(page,role,paths[0],{'width':320,'height':740})
  menu=page.locator('[data-menu-toggle]')
  if menu.count():
   menu.click();page.wait_for_timeout(80)
   check(page.locator('.bn-panel-main').evaluate('(e)=>e.inert'),f'{role}: mobile menu isolates background')
   page.keyboard.press('Escape');page.wait_for_timeout(80)
   check(menu.get_attribute('aria-expanded')=='false',f'{role}: Escape closes menu')
   check(not page.locator('.bn-panel-main').evaluate('(e)=>e.inert'),f'{role}: closing menu restores background')
  if role=='applicant':
   visit(page,role,'/applicant/requests/new',{'width':390,'height':844})
   check(page.locator('[data-guided-step]:visible').count()==1,'Applicant sees one form stage')
   page.locator('.bn-guided-controls button').last.click()
   check(page.locator('[data-guided-step]').first.is_visible(),'Required birth date prevents skipping the first stage')
   for field,value in [('birth_day','1'),('birth_month','1'),('birth_year','1370')]:page.locator('[name='+field+']').select_option(value)
   page.locator('.bn-guided-controls button').last.click()
   check(page.locator('select[name=merchant_id]').is_disabled(),'Open marketplace disables direct merchant choice')
   page.locator('[name=acceptor_assignment_mode]').select_option('applicant_selected_acceptor')
   check(page.locator('select[name=merchant_id]').is_visible() and not page.locator('select[name=merchant_id]').is_disabled(),'Direct assignment shows the merchant selector')
   check(not page.locator('[data-marketplace-fields]').is_visible(),'Direct assignment hides marketplace-only fields')
   page.locator('.bn-guided-controls button').last.click()
   page.locator('button[value=submit]').click()
   check(page.locator('[name=plan_id]').get_attribute('aria-invalid')=='true','Missing required plan gets an inline error')
   check(page.locator('button[value=submit]').is_enabled(),'Invalid submission never locks the submit button')
   page.screenshot(path=str(shots/'applicant-validation-mobile.png'),full_page=True)
  context.close();(work/'browser-audit.json').write_text(json.dumps(report,ensure_ascii=False,indent=2));print('Audited',role,flush=True)
 # Public entry and standalone service surfaces use the same local assets.
 context=browser.new_context(viewport={'width':1440,'height':1000},reduced_motion='reduce');page=context.new_page()
 for service,url in state['urls'].items():
  for route in (['/login'] if service=='panel' else ['/update.php']):
   response=page.goto(url+route,wait_until='load');page.evaluate('document.fonts.ready')
   report['pages'].append({'service':service,'path':route,'status':response.status,'font':page.evaluate('getComputedStyle(document.body).fontFamily')})
   page.screenshot(path=str(shots/(service+'-entry-desktop.png')),full_page=True)
 context.close();focused_checks(browser);browser.close()
a11y=sum(len(p.get('accessibility',[])) for p in report['pages']);report['accessibility_violations']=a11y
report['accessibility_engine']='axe-core' if args.axe_script else None
report['complete']=not report['errors'] and a11y==0;(work/'browser-audit.json').write_text(json.dumps(report,ensure_ascii=False,indent=2))
print('Pages:',len(report['pages']),'checks:',len(report['interactions']),'errors:',len(report['errors']))
for e in report['errors']:print('ISSUE',e)

if not report['complete']:raise SystemExit(1)
