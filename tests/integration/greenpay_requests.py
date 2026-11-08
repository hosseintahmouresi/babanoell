"""Reference per-case GreenPay domain; all financial transport is scripted synthetic data."""
import json
from pathlib import Path


def greenpay_request_acceptance(w):
    cases=json.loads((w.stack.repository/'loans.noeel.ir/public_html/tests/fixtures/golden-calculations.json').read_text())
    settings=dict(next(x['settings'] for x in cases if x['type']=='leasing'),credit_share_percent=70)
    plan=w.call('loans','/api/v1/plans','POST',{'code':'GREENPAY-REFERENCE','name':'Synthetic per-case GreenPay','type':'leasing','settings':settings,'required_documents':[],
        'rules':{'loan_application_contract_enabled':False,'facility_agreement_contract_enabled':False}},expected=201)['plan']
    w.call('loans','/api/v1/plans/'+plan['id']+'/status','POST',{'status':'active'})
    loan=greenpay_case(w,plan)
    w.call('finance','/api/v1/greenpay/requests',role='applicant',expected=403)
    w.call('finance','/api/v1/greenpay/requests/'+loan['id'],role='operations_expert',expected=403)
    data=w.call('finance','/api/v1/greenpay/requests/'+loan['id'])['debit'];w.check(not data['eligible'],'Real API defaults every case to non-eligible')
    raw=w.stack.command(w.stack.php_command()+[str(Path(__file__).with_name('fixtures')/'greenpay-requests.php'),str(w.stack.root/'fixture.json'),loan['id']],'Synthetic per-case GreenPay state machine')
    for check in json.loads(raw)['checks']:w.check(check['passed'],check['name'])
    public=w.call('finance','/api/v1/greenpay/requests/'+loan['id'])['debit'];w.check(public['status']=='success' and public['attempts'][0]['amount_rial']==70000000,'Real API exposes per-case reference history with the financed amount')
    w.call('panel','/finance/greenpay/requests',role=None,signed=False)
    w.call('panel','/finance/greenpay/requests/'+loan['id'],role=None,signed=False)
    w.call('panel','/finance/greenpay/requests/'+loan['id']+'/execute','POST',{},role=None,signed=False,expected=403)
    w.greenpay_request_id=loan['id']
    from greenpay_http import GreenpayHttpFixture, greenpay_http_acceptance
    w.stack.greenpay_fixture=GreenpayHttpFixture(w.stack)
    http_loan=greenpay_case(w,plan)
    greenpay_http_acceptance(w,w.stack.greenpay_fixture,http_loan)
    w.greenpay_http_id=http_loan['id']
    fixture=w.stack.greenpay_fixture;fixture.mode('success')
    fixture.configure({'integrations':{'INTEGRATIONS_GREENPAY_ENVIRONMENT':'test','INTEGRATIONS_GREENPAY_TEST_PROFILE_1023':'1','INTEGRATIONS_GREENPAY_AUTH_URL':'https://wallet.bki.ir/walletbackend/api/sandbox'},'finance':{'FINANCE_GREENPAY_TEST_PROFILE_1023':'1'}})
    sandbox=greenpay_case(w,plan);base='/api/v1/greenpay/requests/'+sandbox['id']
    state=w.call('finance',base+'/enrollment','POST',{'eligible':True,'row_version':0})['debit'];before=fixture.state()
    state=w.call('finance',base+'/send-otp','POST',{'row_version':state['row_version']})['debit'];after=fixture.state()
    w.check(state['status']=='success' and after.get('otp_calls',0)==before.get('otp_calls',0) and after['debit_calls']==before['debit_calls']+1,'Sandbox 1023 uses fixed reference wallets/OTP and completes without a provider OTP request')
    fixture.configure({'integrations':{'INTEGRATIONS_GREENPAY_ENVIRONMENT':'live','INTEGRATIONS_GREENPAY_TEST_PROFILE_1023':'0','INTEGRATIONS_GREENPAY_AUTH_URL':'https://auth.example.ir'},'finance':{'FINANCE_GREENPAY_TEST_PROFILE_1023':'0'}})
    w.greenpay_sandbox_id=sandbox['id']



def greenpay_case(w,plan):
    loan=w.call('loans','/api/v1/requests','POST',{'plan_id':plan['id'],'submit':True,'applicant_user_id':w.stack.accounts['applicant']['id'],
        'full_name':'Synthetic GreenPay','father_name':'Synthetic','certificate_number':'123','national_code':'0013543318','mobile_number':'09121234567','birth_date':'1370/01/01',
        'residential_address':'Synthetic address','work_address':'Synthetic work','postal_code':'1234567890','requested_amount':100000000,'installment_count':12,'installment_interval':1,
        'first_due_date':w.stack.first_due,'order_items':[{'name':'Synthetic goods','qty':1,'price':100000000}]},expected=201)['request']
    prefix='/api/v1/requests/'+loan['id']
    w.call('loans',prefix+'/assignment','POST',{'type':'bank_expert','user_id':w.stack.accounts['bank_expert']['id']})
    w.call('loans',prefix+'/bank-review','POST',{'status':'approved'},role='bank_expert',expected=201)
    w.call('loans',prefix+'/transition','POST',{'status':'expert_approved'},role='operations_expert')
    w.call('loans',prefix+'/transition','POST',{'status':'docs_completion'},role='bank_expert')
    w.call('loans',prefix+'/transition','POST',{'status':'docs_verified'},role='operations_expert')
    return loan
