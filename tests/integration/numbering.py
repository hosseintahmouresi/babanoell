"""Reference business numbers, configurable step and serialization under concurrent creation."""
import concurrent.futures
import secrets


def numbering_acceptance(w):
    path='/api/v1/admin/request-numbering'
    w.call('loans',path,role='applicant',expected=403)
    before=w.call('loans','/api/v1/requests/'+w.reference_loans[0])['request']
    settings=w.call('loans',path)['sequence']
    body={'revision':settings['revision'],'step':7}
    w.call('loans',path,'POST',body)
    w.call('loans',path,'POST',body,expected=409)
    w.call('loans',path,'POST',dict(body,step=0),expected=422)
    payload={'plan_id':before['plan_id'],'requested_amount':100000000,'installment_count':12,'installment_interval':1,'first_due_date':w.stack.first_due,'submit':False}
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:
        rows=list(pool.map(lambda _:w.stack.request('loans','/api/v1/requests','POST',payload,w.tokens['super_admin'],key=secrets.token_hex(24)),range(4)))
    w.check(all(x[0]==201 for x in rows),'Concurrent requests all receive business numbers')
    numbers=sorted(int(x[1]['data']['request']['request_no']) for x in rows)
    w.check(len(set(numbers))==4 and all(b-a==7 for a,b in zip(numbers,numbers[1:])),'Reference numbering is unique and uses the configured step under contention')
    key=secrets.token_hex(24);one=w.call('loans','/api/v1/requests','POST',payload,key=key,expected=201)['request'];two=w.call('loans','/api/v1/requests','POST',payload,key=key,expected=201)['request']
    w.check(one['id']==two['id'] and one['request_no']==two['request_no'],'Idempotent case submission retains its original business number')
    after=w.call('loans','/api/v1/requests/'+before['id'])['request'];w.check(after['request_no']==before['request_no'],'Changing the numbering step does not rewrite previous numbers')
    settings=w.call('loans',path)['sequence'];w.call('loans',path,'POST',{'revision':settings['revision'],'step':1})
    w.call('panel','/loans/request-numbering',role=None,signed=False)
