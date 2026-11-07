"""Reusable merchant referral, applicant lock and claimed-card visibility reference parity."""
from pathlib import Path
import json,re
from onboarding import CooperationWorkflow

def referral_acceptance(w):
    a=w.call('partners','/api/v1/me/invitation','POST',{},role='acceptor')['invitation']
    replay=w.call('partners','/api/v1/me/invitation','POST',{},role='acceptor')['invitation']
    w.check(a['code']==replay['code'] and a['lock_days']==30,'Merchant referral code is stable and reusable with the reference 30-day lock')
    b=w.call('partners','/api/v1/me/invitation','POST',{},role='new_cooperation_individual')['invitation']
    w.call('partners','/api/v1/me/invitation','POST',{},role='applicant',expected=403)
    locked=w.call('partners','/api/v1/invitations/apply','POST',{'code':a['code']},role='applicant')['lock']
    kept=w.call('partners','/api/v1/invitations/apply','POST',{'code':b['code']},role='applicant')['lock']
    w.check(kept['merchant_id']==a['merchant_id'] and kept['expires_at']==locked['expires_at'],'A new invitation does not replace or extend an existing active lock by default')
    plan=w.call('loans','/api/v1/plans','POST',{'code':'REFERRAL-ACCEPTANCE','name':'Synthetic referral acceptance','type':'market',
        'settings':{'min_amount':1,'max_amount':100000000,'active_installments':[6],'fixed_monthly_rate':2,'prepayment_percent':20}},expected=201)['plan']
    w.call('loans','/api/v1/plans/'+plan['id']+'/status','POST',{'status':'active'})
    body={'plan_id':plan['id'],'plan_context':'applicant_request','submit':True,'full_name':'Synthetic referral applicant','father_name':'Synthetic',
        'certificate_number':'1','national_code':'0013543318','mobile_number':'09121234567','birth_date':'1370/01/01','occupation':'other',
        'residential_address':'Synthetic address','work_address':'Synthetic work','postal_code':'1234567890','requested_amount':1000000,
        'installment_count':6,'installment_interval':1,'first_due_date':w.stack.first_due,'order_items':[{'name':'Synthetic product','qty':1,'price':1000000}],
        'acceptor_assignment_mode':'open_acceptor_marketplace','requested_service_scope':'all_country','requested_activity_categories':['goods']}
    bound=w.call('loans','/api/v1/requests','POST',dict(body,merchant_id=b['merchant_id']),role='applicant',key='referral-bound-request',expected=201)['request']
    w.check(bound['merchant_id']==a['merchant_id'] and bound['acceptor_user_id']==w.stack.accounts['acceptor']['id'] and bound['acceptor_assignment_mode']=='applicant_selected_acceptor','Server enforces the locked canonical merchant and owner despite forged form choices')
    w.call('loans','/api/v1/requests/'+bound['id'],role='acceptor')
    w.call('partners',f"/api/v1/merchants/{a['merchant_id']}/plan-permissions/{plan['id']}",'POST',{'status':'inactive'})
    w.call('loans','/api/v1/requests','POST',body,role='applicant',expected=422)
    replay=w.call('loans','/api/v1/requests','POST',dict(body,merchant_id=b['merchant_id']),role='applicant',key='referral-bound-request',expected=201)['request']
    w.check(replay['id']==bound['id'],'Idempotent replay recovers the existing case without creating a new binding')
    w.call('partners',f"/api/v1/merchants/{a['merchant_id']}/plan-permissions/{plan['id']}",'POST',{'status':'active'})
    catalog=w.call('partners','/api/v1/marketplace/catalog')['catalog']
    def save(**changes):
        current=w.call('partners','/api/v1/marketplace/catalog')['catalog']
        w.call('partners','/api/v1/marketplace/configuration','PUT',dict(current,expected_revision=current['revision'],**changes))
    save(allow_override_existing_invitation_lock=True)
    changed=w.call('partners','/api/v1/invitations/apply','POST',{'code':b['code']},role='applicant')['lock']
    w.check(changed['merchant_id']==b['merchant_id'],'An administrator-enabled override follows the reference setting')
    w.stack.command(w.stack.php_command()+[str(Path(__file__).with_name('fixtures')/'referral-state.php'),str(w.stack.root/'fixture.json'),'expire-lock',w.stack.accounts['applicant']['id']],'Expire synthetic referral lock')
    w.check(w.call('partners','/api/v1/me/invitation-lock',role='applicant')['lock'] is None,'Expired locks stop applying independently of the PHP display timezone')
    save(allow_override_existing_invitation_lock=False)
    w.call('partners','/api/v1/invitations/apply','POST',{'code':a['code']},role='applicant')
    with CooperationWorkflow(w).panel('applicant'):
        page=w.call('panel','/applicant/requests/new',role=None,signed=False)
        w.check(re.search(rb'<select name="merchant_id" disabled>',page) is not None and a['merchant_id'].encode() in page,'Applicant form explains the lock and fixes the selected merchant')
        w.call('panel','/applicant/invitation','POST',{'_csrf':'invalid','code':b['code']},role=None,signed=False,form=True,expected=403)
    with CooperationWorkflow(w).panel('acceptor'):
        page=w.call('panel','/acceptor/invitation',role=None,signed=False)
        w.check(a['code'].encode() in page,'Acceptor can retrieve the same shareable invitation in Panel')
    # A separate user without a referral can still choose the open market.
    open_case=w.call('loans','/api/v1/requests','POST',dict(body,request_public_summary='REFERRAL-VISIBILITY'),role='new_cooperation_legal',expected=201)['request']
    w.call('loans',f"/api/v1/marketplace/requests/{open_case['id']}/claim",'POST',{},role='acceptor',expected=201)
    path='/api/v1/marketplace/requests?q=REFERRAL-VISIBILITY'
    w.check(w.call('loans',path,role='new_cooperation_individual')['total']==1,'Recently claimed matching card remains visible to another acceptor by default')
    save(show_claimed_cards_to_other_acceptors=False)
    w.check(w.call('loans',path,role='new_cooperation_individual')['total']==0 and w.call('loans',path,role='acceptor')['total']==1,'Claimed-card privacy hides others while retaining the claimant’s recent card')
    w.stack.command(w.stack.php_command()+[str(Path(__file__).with_name('fixtures')/'referral-state.php'),str(w.stack.root/'fixture.json'),'age-claim',open_case['id']],'Age synthetic claimed card')
    w.check(w.call('loans',path,role='acceptor')['total']==0,'Claimed cards leave the market after the configured reference window')
    save(show_claimed_cards_to_other_acceptors=True)
