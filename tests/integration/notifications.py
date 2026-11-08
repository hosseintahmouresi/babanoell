"""Reference SMS settings, private inbox and durable worker acceptance using synthetic messages only."""
import json
from pathlib import Path

def notification_acceptance(w):
    settings=w.call('loans','/api/v1/admin/notifications/settings')
    w.call('loans','/api/v1/admin/notifications/settings',role='applicant',expected=403)
    w.call('identity','/api/v1/services/notification-contact','POST',{'user_id':w.stack.accounts['acceptor']['id']},expected=403)
    rule={'enabled':True,'pattern_id':123,'variables':['Original [[status]]'],'send_to_applicant':True,'send_to_acceptor':False}
    body={'revision':settings['revision'],'settings':{'loan_statuses':{'under_review':rule}}}
    w.call('loans','/api/v1/admin/notifications/settings','POST',body)
    w.call('loans','/api/v1/admin/notifications/settings','POST',body,expected=409)
    plan=w.call('loans','/api/v1/plans','POST',{'code':'NOTIFICATION-TEST','name':'Synthetic notification plan','type':'market','rules':{'visibility_contexts':['merchant_request','applicant_request','calculator']},
        'settings':{'min_amount':1,'max_amount':100000000,'active_installments':[6],'fixed_monthly_rate':2,'prepayment_percent':20}},expected=201)['plan']
    w.call('loans','/api/v1/plans/'+plan['id']+'/status','POST',{'status':'active'})
    loan=w.call('loans','/api/v1/requests','POST',{'plan_id':plan['id'],'submit':True,
        'applicant_user_id':w.stack.accounts['applicant']['id'],'full_name':'Synthetic notification applicant','father_name':'Synthetic',
        'certificate_number':'1','national_code':'0013543318','mobile_number':'09121234567','birth_date':'1370/01/01',
        'occupation':'other','residential_address':'Synthetic address','work_address':'Synthetic work','postal_code':'1234567890',
        'requested_amount':1000000,'installment_count':6,'installment_interval':1,'first_due_date':w.stack.first_due,
        'order_items':[{'name':'Synthetic product','qty':1,'price':1000000}]},expected=201)['request']
    rows=w.call('loans','/api/v1/admin/notifications/deliveries')
    own=[row for row in rows['items'] if row['request_id']==loan['id']]
    w.check(len(own)==1 and own[0]['target_masked']=='0912***4567','Final submission atomically queues the configured SMS')
    settings=w.call('loans','/api/v1/admin/notifications/settings');rule['variables']=['Changed [[status]]']
    w.call('loans','/api/v1/admin/notifications/settings','POST',{'revision':settings['revision'],'settings':{'loan_statuses':{'under_review':rule}}})
    raw=w.stack.command(w.stack.php_command()+[str(Path(__file__).with_name('fixtures')/'notifications-state.php'),str(w.stack.root/'fixture.json'),loan['id']],'Notification worker acceptance')
    for check in json.loads(raw)['checks']:w.check(check['passed'],check['name'])
    inbox=w.call('loans','/api/v1/notifications',role='applicant')
    row=next(item for item in inbox['items'] if item['metadata'].get('request_id')==loan['id'])
    w.call('loans','/api/v1/notifications/'+row['id']+'/read','POST',{},role='acceptor')
    current=w.call('loans','/api/v1/notifications',role='applicant')
    w.check(next(item for item in current['items'] if item['id']==row['id'])['read_at'] is None,'An unrelated account cannot change another user’s read receipt')
    w.call('loans','/api/v1/notifications/'+row['id']+'/read','POST',{},role='applicant')
    current=w.call('loans','/api/v1/notifications',role='applicant')
    w.check(next(item for item in current['items'] if item['id']==row['id'])['read_at'] is not None,'Owner can mark an in-app notification as read')
    w.call('panel','/notifications',role=None,signed=False)
    w.call('panel','/notifications/deliveries',role=None,signed=False)
    w.call('panel','/notifications/settings',role=None,signed=False)
    # Leave default-disabled state for later scenarios; no real provider work is allowed.
    settings=w.call('loans','/api/v1/admin/notifications/settings')
    w.call('loans','/api/v1/admin/notifications/settings','POST',{'revision':settings['revision'],'settings':{'loan_statuses':{}}})
