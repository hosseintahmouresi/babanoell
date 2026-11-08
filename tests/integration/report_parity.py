"""Native report/date/permission/export parity, including exact decimal totals and a real 5001-row limit."""
import csv,io,json,re
from decimal import Decimal
from pathlib import Path
from urllib.parse import urlencode
from onboarding import CooperationWorkflow


def report_acceptance(w):
    script=Path(__file__).with_name('fixtures')/'request-reports.php'
    def fixture(action,*extra):return json.loads(w.stack.command(w.stack.php_command()+[str(script),str(w.stack.root/'fixture.json'),action,*extra],'Synthetic report fixture '+action))
    base=w.reference_loans[0];rows=fixture('seed',base)['ids'];q='?q=RPT219-CASE'
    try:
        allrows=w.call('loans','/api/v1/requests'+q)['items'];w.check(len(allrows)==6,'Scoped request list contains all six synthetic reference rows')
        own=w.call('loans','/api/v1/reports/requests'+q,role='applicant');w.check(own['total']==5 and sum(x['count'] for x in own['statuses'])==5,'Applicant report, status groups and rows use the same ownership boundary')
        expected=Decimal('9007199254740993.25')+Decimal('100.10')*4
        w.check(Decimal(own['totals']['requested_amount'])==expected,'Report preserves exact DECIMAL totals above the JavaScript and float integer precision limit')
        day=q+'&'+urlencode({'date_from':'۱۴۰۵/۰۱/۰۱','date_to':'1405/01/01'})
        match=w.call('loans','/api/v1/requests'+day,role='applicant');report=w.call('loans','/api/v1/reports/requests'+day,role='applicant');export=w.call('loans','/api/v1/reports/requests/export'+day,role='applicant')
        w.check(set(x['id'] for x in match['items'])==set([rows[1],rows[2],rows[5]])==set(x['id'] for x in export['items']),'Inclusive Tehran day filtering agrees across list and complete export, including fractional-second midnight boundaries')
        w.check(report['total']==3 and Decimal(report['totals']['requested_amount'])==Decimal('9007199254741193.45'),'Filtered total equals the exact exported purchase amounts')
        settled=w.call('loans','/api/v1/requests'+q+'&status=granted&settlement_status=settled',role='applicant');w.check([x['id'] for x in settled['items']]==[rows[1]],'Settlement filtering uses its own column while the workflow status remains granted')
        for query,rid in [('%25literal',rows[0]),('_literal',rows[1])]:
            found=w.call('loans','/api/v1/requests?q='+query,role='applicant');w.check(found['total']==1 and found['items'][0]['id']==rid,'Search treats SQL wildcard characters as literal text')
        first=w.call('loans','/api/v1/reports/requests'+q+'&per_page=1&page=1',role='applicant');second=w.call('loans','/api/v1/reports/requests'+q+'&per_page=1&page=2',role='applicant');w.check(first['total']==second['total']==5 and first['items'][0]['id']!=second['items'][0]['id'],'Report pagination retains complete totals and a stable distinct row order')
        for invalid in ['date_from=2026-02-30','date_from=2026-03-22&date_to=2026-03-21','status%5B%5D=granted','plan_id=bad','settlement_status=bad']:
            w.call('loans','/api/v1/reports/requests?'+invalid,expected=422)
        w.call('identity','/api/v1/services/plan-policy','POST',{'user_id':w.stack.accounts['acceptor']['id']},expected=403)
        for role,applies in [('acceptor',True),('admin',False),('operations_expert',False),('finance_expert',False)]:
            policy=fixture('role-policy',w.stack.accounts[role]['id']);w.check(policy['status']==200 and policy['data']['policy']['plan_exclusions_apply'] is applies,'Signed role-policy lookup respects reference exemptions for '+role)
        for role in ['admin','bank_expert']:
            fixture('deny',w.stack.accounts[role]['id'],rows[1]);result=w.call('loans','/api/v1/reports/requests'+q,role=role);w.check(rows[1] not in [x['id'] for x in result['items']],'Explicit case denial remains effective in reports for '+role)
            w.call('loans','/api/v1/requests/'+rows[1],role=role,expected=403)
            if role=='bank_expert':w.check(rows[1] not in [x['id'] for x in w.call('loans','/api/v1/bank/export'+q,role=role)['items']],'Bank export honors the same explicit case denial')
            fixture('deny',w.stack.accounts[role]['id'],'*');result=w.call('loans','/api/v1/reports/requests'+q,role=role);w.check(result['total']==0 and result['items']==[],'Explicit global denial overrides broad and assigned access for '+role)
            w.check(w.call('loans','/api/v1/requests'+q,role=role)['total']==0,'Ordinary request list uses the same global denial for '+role)
            if role=='bank_expert':w.check(w.call('loans','/api/v1/bank/summary',role=role)['total']==0,'Bank summary cannot reveal counts hidden by a global denial')
        fixture('clear-denies')
        flow=CooperationWorkflow(w)
        with flow.panel('applicant'):
            html=w.call('panel','/reports/requests'+day,role=None,signed=False);w.check('گزارش تفصیلی تسهیلات'.encode() in html and b'name="date_from"' in html,'Applicant has a native report with preserved date filters')
            raw=w.call('panel','/reports/requests/export.csv'+day,role=None,signed=False);parsed=list(csv.reader(io.StringIO(raw.decode('utf-8-sig'))));w.check(len(parsed)==4 and any("'=SUM(1,1)" in row for row in parsed[1:]),'CSV contains the whole filtered set and escapes spreadsheet formulas')
            w.check(any('9007199254740993.25' in row for row in parsed[1:]),'CSV preserves exact persisted financial digits without float rounding')
            html=w.call('panel','/applicant/requests'+day,role=None,signed=False);w.check(b'name="date_to"' in html and b'/reports/requests?' in html,'Native applicant list exposes date, settlement and matching report navigation')
        bulk=fixture('bulk',base);w.check(len(bulk['ids'])==5001,'A real 5001-row synthetic export dataset was created')
        for endpoint in ['/api/v1/reports/requests/export','/api/v1/bank/export']:
            w.call('loans',endpoint+'?q=RPT219-LIMIT',expected=413)
        trimmed=w.call('loans','/api/v1/reports/requests/export?q=RPT219-LIMIT-5000');w.check(trimmed['total']==1 and trimmed['truncated'] is False,'A narrower export succeeds completely after the size guard')
    finally:
        fixture('clear-denies');fixture('cleanup')
