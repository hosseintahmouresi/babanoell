"""Reference account-level plan exclusions, empty visibility, calculation gates and immutable old cases."""
import concurrent.futures
from onboarding import CooperationWorkflow


def plan_access_acceptance(w):
    contexts=['merchant_request','applicant_request','calculator']
    config={'min_amount':1,'max_amount':100000000,'active_installments':[6],'fixed_monthly_rate':2,'prepayment_percent':20}
    # The default is compared independently of fixtures which explicitly opt in to every portal.
    default=w.call('loans','/api/v1/plans','POST',{'code':'REFERENCE-DEFAULT-CONTEXTS','name':'Synthetic default visibility','type':'market','settings':config},expected=201)['plan']
    w.check(set(default['rules']['visibility_contexts'])==set(['merchant_request','calculator']),'New unconfigured visibility matches the reference legacy merchant/calculator default')
    plan=w.call('loans','/api/v1/plans','POST',{'code':'REFERENCE-PLAN-EXCLUSIONS','name':'Synthetic user plan exclusion','type':'market','settings':config,'rules':{'visibility_contexts':contexts}},expected=201)['plan'];pid=plan['id']
    w.call('loans','/api/v1/plans/'+pid+'/status','POST',{'status':'active'})
    body={'plan_id':pid,'requested_amount':1000000,'installment_count':6,'installment_interval':1,'submit':False}
    old=w.call('loans','/api/v1/requests','POST',body,role='acceptor',key='reference-before-exclusion',expected=201)['request']
    uid=w.stack.accounts['acceptor']['id'];endpoint='/api/v1/admin/users/'+uid+'/plan-exclusions';before=w.call('partners',endpoint)['access']
    w.call('partners',endpoint,role='applicant',expected=403);w.call('partners',endpoint,'POST',{'revision':before['revision'],'disabled_plan_ids':[pid]},role='operations_expert',expected=403)
    # Two writers start from one revision; exactly one is allowed to commit.
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        results=list(pool.map(lambda _:w.stack.request('partners',endpoint,'POST',{'revision':before['revision'],'disabled_plan_ids':[pid]},w.tokens['super_admin']),range(2)))
    w.check(sorted(x[0] for x in results)==[200,409],'Concurrent first-save account exclusions commit once and reject the stale revision')
    saved=w.call('partners',endpoint)['access'];w.check(saved['disabled_plan_ids']==[pid],'Exclusions are stored against the chosen account rather than a plan or company default')
    w.call('partners',endpoint,'POST',{'revision':saved['revision'],'disabled_plan_ids':['e'*26]},expected=422)
    plans=w.call('loans','/api/v1/plans?context=merchant_request&per_page=100',role='acceptor');w.check(pid not in [x['id'] for x in plans['items']],'Excluded plan is removed before merchant plan pagination and totals are built')
    calc={'plan_id':pid,'amount':1000000,'installment_count':6,'installment_interval':1,'plan_context':'merchant_request'}
    w.call('loans','/api/v1/calculations','POST',calc,role='acceptor',expected=403)
    w.call('loans','/api/v1/requests','POST',body,role='acceptor',expected=403)
    replay=w.call('loans','/api/v1/requests','POST',body,role='acceptor',key='reference-before-exclusion',expected=201)['request'];w.check(replay['id']==old['id'] and replay['calculation_result_hash']==old['calculation_result_hash'],'Excluding a plan preserves an old case and an idempotent replay of its original creation')
    w.call('loans','/api/v1/calculations','POST',dict(calc,plan_context='applicant_request'),role='applicant',expected=201)
    merchant=w.call('partners','/api/v1/me/merchant',role='acceptor')['merchant']
    assignment=w.call('partners','/api/v1/directory/merchants/'+merchant['id']+'/assignment-context?plan_id='+pid,role='applicant')['context'];w.check(assignment['owner_plan_excluded'] is True,'Canonical applicant assignment checks the target owner account with signed Identity role policy')
    w.call('loans','/api/v1/requests','POST',dict(body,merchant_id=merchant['id'],acceptor_assignment_mode='applicant_selected_acceptor'),role='applicant',expected=422)
    context=w.call('partners','/api/v1/marketplace/context',role='acceptor')['context'];w.check(any(x['plan_id']==pid and x['status']=='inactive' for x in context['plan_permissions']),'Marketplace matching receives the same account exclusion')
    flow=CooperationWorkflow(w)
    with flow.panel('super_admin'):
        page=w.call('panel','/users/'+uid+'/plans',role=None,signed=False);w.check(b'name="selection_complete"' in page and 'طرح‌های غیرفعال'.encode() in page,'Admin has a native, named-account exclusion form with a complete selection')
        w.call('panel','/users/'+uid+'/plans','POST',{'_csrf':'wrong'},role=None,signed=False,form=True,expected=403)
    restored=w.call('partners',endpoint,'POST',{'revision':saved['revision'],'disabled_plan_ids':[]})['access'];w.check(restored['disabled_plan_ids']==[],'Clearing exclusions returns to the reference allow-all-current-and-future model')
    w.call('loans','/api/v1/calculations','POST',calc,role='acceptor',expected=201)
    w.call('loans','/api/v1/plans/'+pid,'PATCH',{'rules':{'visibility_contexts':[]}},expected=200)
    for ctx in contexts:
        listed=w.call('loans','/api/v1/plans?context='+ctx+'&per_page=100');w.check(pid not in [x['id'] for x in listed['items']],'Explicit empty visibility hides the plan in '+ctx)
    w.call('loans','/api/v1/calculations','POST',calc,role='acceptor',expected=422)
    w.call('loans','/api/v1/requests','POST',body,role='acceptor',expected=422)
    w.call('loans','/api/v1/plans/'+pid,'PATCH',{'rules':{'visibility_contexts':contexts}})
