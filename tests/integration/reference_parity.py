"""Reference templates and cooperation notifications over the real disposable HTTP stack."""
import json
from pathlib import Path


def reference_contract_acceptance(w):
    w.reference_loans=[]
    cases=json.loads((w.stack.repository/'loans.noeel.ir/public_html/tests/fixtures/golden-calculations.json').read_text())
    settings=dict(next(x['settings'] for x in cases if x['type']=='leasing'),interest_rate=26)
    for code in ('hami_plus_reference','hami_retirees_reference'):
        plan=w.call('loans','/api/v1/plans','POST',{'code':code.upper(),'name':'Synthetic reference contract','type':'leasing','settings':settings,'required_documents':[],
            'rules':{'contract_mode':'digital','facility_agreement_template_code':code,'contract_first_due_rule':'jalali_month_end_half'}},expected=201)['plan']
        w.call('loans','/api/v1/plans/'+plan['id']+'/status','POST',{'status':'active'})
        body={'plan_id':plan['id'],'submit':False,'applicant_user_id':w.stack.accounts['applicant']['id'],'full_name':'Synthetic <buyer>',
            'father_name':'Synthetic father','certificate_number':'123','national_code':'0013543318','mobile_number':'09121234567','phone_number':'02112345678','birth_date':'1370/01/01',
            'residential_address':'Synthetic residence','work_address':'Synthetic work','postal_code':'1234567890','retirement_code':'R123','retirement_organization':'Synthetic pension',
            'delivery_date':'1405/09/01','requested_amount':100000000,'installment_count':12,'installment_interval':1,'first_due_date':w.stack.first_due,
            'order_items':[{'name':'Synthetic product','qty':1,'price':100000000}]}
        loan=w.call('loans','/api/v1/requests','POST',body,expected=201)['request'];w.reference_loans.append(loan['id']);path=f"/api/v1/loan-requests/{loan['id']}/contracts"
        if code=='hami_retirees_reference':
            w.call('partners',path,'POST',{'contract_kind':'facility_agreement'},role='contract_expert',expected=422)
            loan=w.call('loans',f"/api/v1/requests/{loan['id']}",'PATCH',{'certificate_city':'Synthetic city'},role='applicant')['request']
        else:
            w.call('loans',f"/api/v1/requests/{loan['id']}",'PATCH',{'certificate_city':'Synthetic city'},role='applicant')
        c=w.call('partners',path,'POST',{'contract_kind':'facility_agreement'},role='contract_expert',expected=201)['contract']
        w.call('partners',f"/api/v1/contracts/{c['id']}/signing-link",'POST',{},role='acceptor',expected=403)
        w.call('partners',f"/api/v1/contracts/{c['id']}/signing-link",'POST',{},role='applicant')
        html=c['rendered_content']
        w.check('Synthetic &lt;buyer&gt;' in html and 'Synthetic city' in html and '[full_name]' not in html,'Reference contract resolves persisted identity and escapes buyer input')
        w.check(c['template_code']==code and c['rendered_hash'] and f"{float(loan['facility_amount']):,.0f}" in html,'Contract uses the selected immutable reference template and purchase snapshot')
        replay=w.call('partners',path,'POST',{'contract_kind':'facility_agreement'},role='contract_expert',expected=201)['contract']
        w.check(replay['id']==c['id'] and replay['rendered_hash']==c['rendered_hash'],'Repeated contract creation preserves its original dated snapshot')
        link=w.call('partners',f"/api/v1/contracts/{c['id']}/issue-link",'POST',{},role='contract_expert')
        # Authenticated details and public print must expose the same frozen legal text.
        w.call('partners',f"/api/v1/contracts/{c['id']}",role='applicant')
        w.call('loans',f"/api/v1/requests/{loan['id']}",'PATCH',{'full_name':'Synthetic refreshed <buyer>','mobile_number':'09121234568'},role='applicant')
        current=w.call('partners',f"/api/v1/contracts/{c['id']}",role='applicant')['contract']
        revised=w.call('partners',f"/api/v1/contracts/{c['id']}/reissue",'POST',{'row_version':current['row_version'],'reason':'Synthetic refreshed reference contract','refresh_context':True},role='contract_expert',expected=201)['contract']
        w.check('Synthetic refreshed &lt;buyer&gt;' in revised['rendered_content'] and revised['signer_mobile']=='09121234568' and revised['status']=='pending','Reference reissue refreshes canonical profile/mobile and starts unsigned')
        old=w.call('partners',f"/api/v1/contracts/{c['id']}",role='applicant')['contract']
        w.check(old['rendered_hash']==c['rendered_hash'] and old['rendered_content']==c['rendered_content'],'Refreshing a revision preserves the prior frozen legal text')
        w.call('partners',f"/api/v1/contracts/{revised['id']}/reissue",'POST',{'row_version':revised['row_version'],'reason':'Synthetic conflicting authoring options','refresh_context':True,'content_html':'<p>Manual text</p>'},role='contract_expert',expected=422)



def cooperation_notification_acceptance(w):
    n=w.call('partners','/api/v1/admin/notifications/settings')
    w.call('partners','/api/v1/admin/notifications/settings',role='operations_expert',expected=403)
    rule={'enabled':True,'pattern_id':123,'variables':['Original [[status]]']}
    body={'revision':n['revision'],'settings':{'cooperation_statuses':{'under_review':rule}}}
    w.call('partners','/api/v1/admin/notifications/settings','POST',body)
    w.call('partners','/api/v1/admin/notifications/settings','POST',body,expected=409)
    existing=w.call('partners','/api/v1/cooperation/requests')['items']
    source=next(x for x in existing if x['request_type']=='individual')
    # Copy only a synthetic prior form; the server revalidates it against the current definition.
    source=w.call('partners','/api/v1/cooperation/requests/'+source['id'])['request']
    d=dict(source['data']);d['mobile']='09121234567';d['mobile_number']='09121234567'
    form=w.call('partners','/api/v1/cooperation/form')['form'];d['activity_field']=form['options']['activity_field_options'][0]
    row=w.call('partners','/api/v1/cooperation/requests','POST',{'request_type':'individual','data':d},expected=201)['request']
    deliveries=w.call('partners','/api/v1/admin/notifications/deliveries')['items']
    own=[x for x in deliveries if x['request_id']==row['id']]
    w.check(len(own)==1,'New cooperation submission queues exactly one configured reference status SMS')
    n=w.call('partners','/api/v1/admin/notifications/settings');rule['variables']=['Changed [[status]]']
    w.call('partners','/api/v1/admin/notifications/settings','POST',{'revision':n['revision'],'settings':{'cooperation_statuses':{'under_review':rule}}})
    raw=w.stack.command(w.stack.php_command()+[str(Path(__file__).with_name('fixtures')/'cooperation-notifications.php'),str(w.stack.root/'fixture.json'),row['id']],'Cooperation notification worker acceptance')
    for check in json.loads(raw)['checks']:w.check(check['passed'],check['name'])
    w.call('panel','/cooperation-notifications/settings',role=None,signed=False)
    w.call('panel','/cooperation-notifications/deliveries',role=None,signed=False)
    n=w.call('partners','/api/v1/admin/notifications/settings');w.call('partners','/api/v1/admin/notifications/settings','POST',{'revision':n['revision'],'settings':{'cooperation_statuses':{}}})
