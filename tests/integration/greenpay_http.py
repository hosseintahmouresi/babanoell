"""Signed Finance → Integrations → scripted provider, never a real wallet endpoint."""
import concurrent.futures
import json
from pathlib import Path
import secrets
import shutil


class GreenpayHttpFixture:
    def __init__(self, stack):
        self.stack=stack
        self.path=stack.root/'integrations/storage/synthetic-greenpay.json'
        self.path.write_text(json.dumps({'mode':'success'}))
        fixture=stack.root/'integrations/storage/SyntheticGreenpayTransport.php'
        shutil.copyfile(Path(__file__).with_name('fixtures')/'GreenpayTransport.php',fixture)
        index=stack.root/'integrations/public/index.php';code=index.read_text()
        old="new \\Babanoell\\Integrations\\App\\Router($pdo,new \\Babanoell\\Tests\\CreditTransport($root.'/storage/synthetic-credit.json'))"
        assert old in code
        code=code.replace(old,old[:-1]+",new \\Babanoell\\Tests\\GreenpayTransport($root.'/storage/synthetic-greenpay.json'))")
        code=code.replace("require $root.'/bootstrap.php';","require $root.'/bootstrap.php';require_once $root.'/storage/SyntheticGreenpayTransport.php';")
        index.write_text(code)
        self.execution_key=secrets.token_hex(32)
        self.configure({'integrations':{'INTEGRATIONS_GREENPAY_USERNAME':'synthetic','INTEGRATIONS_GREENPAY_PASSWORD':'synthetic','INTEGRATIONS_GREENPAY_API_KEY':'synthetic',
            'INTEGRATIONS_GREENPAY_AUTH_URL':'https://auth.example.ir','INTEGRATIONS_GREENPAY_PURCHASE_URL':'https://purchase.example.ir','INTEGRATIONS_GREENPAY_TERMINAL':'1023','INTEGRATIONS_GREENPAY_MERCHANT_ID':'1',
            'INTEGRATIONS_GREENPAY_TRANSFERS_ENABLED':'1','INTEGRATIONS_GREENPAY_EXECUTION_KEY':self.execution_key},
            'finance':{'FINANCE_GREENPAY_REQUEST_DEBITS_ENABLED':'1','FINANCE_GREENPAY_TRANSFERS_ENABLED':'1','FINANCE_GREENPAY_OTP_ENABLED':'1','FINANCE_GREENPAY_EXECUTION_KEY':self.execution_key,'FINANCE_GREENPAY_DESTINATION_POCKET':'444455556666'}})
    def configure(self,changes):
        private=self.stack.root/'synthetic-greenpay-settings.json';private.write_text(json.dumps(changes));private.chmod(0o600)
        self.stack.command(self.stack.php_command()+[str(Path(__file__).with_name('fixtures')/'synthetic-config.php'),str(self.stack.root/'fixture.json'),str(private)],'Configure scripted GreenPay services')
    def mode(self,mode):
        state=json.loads(self.path.read_text());state['mode']=mode;self.path.write_text(json.dumps(state))
    def state(self):return json.loads(self.path.read_text())


def greenpay_http_acceptance(w,fixture,loan):
    request_id=loan['id'];base='/api/v1/greenpay/requests/'+request_id
    w.call('integrations','/api/v1/greenpay/request-debit','POST',{},expected=401)
    data=w.call('finance',base+'/enrollment','POST',{'eligible':True,'row_version':0,'from_pocket':'111122223333','amount_rial':1})['debit']
    data=w.call('finance',base+'/send-otp','POST',{'row_version':data['row_version']})['debit']
    w.check(data['status']=='awaiting_otp' and data['attempts'][0]['amount_rial']==70000000,'Signed HTTP freezes the canonical amount and ignores a forged client amount')
    fixture.configure({'finance':{'FINANCE_GREENPAY_EXECUTION_KEY':'synthetic-invalid-execution-key-0000000000'}})
    w.call('finance',base+'/execute','POST',{'row_version':data['row_version'],'otp':'123456'},expected=503)
    w.check(fixture.state().get('debit_calls',0)==0,'An incorrect execution signature cannot reach the provider')
    fixture.configure({'finance':{'FINANCE_GREENPAY_EXECUTION_KEY':fixture.execution_key}})
    fixture.configure({'integrations':{'INTEGRATIONS_GREENPAY_API_KEY':'synthetic-changed'}})
    w.call('finance',base+'/execute','POST',{'row_version':data['row_version'],'otp':'123456'},expected=409)
    w.check(fixture.state().get('debit_calls',0)==0,'Configuration drift blocks the complete HTTP path before any wallet debit')
    fixture.configure({'integrations':{'INTEGRATIONS_GREENPAY_API_KEY':'synthetic'}})
    fixture.mode('unknown')
    data=w.call('finance',base+'/execute','POST',{'row_version':data['row_version'],'otp':'123456'})['debit']
    w.check(data['status']=='unknown' and fixture.state()['debit_calls']==1,'A real signed HTTP timeout result is quarantined without retry')
    w.call('finance',base+'/execute','POST',{'row_version':data['row_version'],'otp':'123456'},expected=409)
    ref=data['attempts'][0]['external_reference']
    data=w.call('finance',base+'/reconcile','POST',{'row_version':data['row_version'],'decision':'unpaid','reference':ref,'note':'Synthetic provider confirms no debit','confirmed':True})['debit']
    data=w.call('finance',base+'/send-otp','POST',{'row_version':data['row_version']})['debit']
    fixture.mode('time_window');before=fixture.state()['debit_calls'];body={'row_version':data['row_version'],'otp':'۱۲۳۴۵۶'}
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        results=list(pool.map(lambda _:w.stack.request('finance',base+'/execute','POST',body,w.tokens['super_admin']),range(2)))
    w.check(all(x[0] in (200,409) for x in results),'Concurrent HTTP confirmation returns a committed result or conflict')
    after=w.call('finance',base)['debit'];calls=fixture.state()
    w.check(after['status']=='success' and calls['debit_calls']==before+2,'Provider time-window correction makes exactly one safe retry under concurrent HTTP confirmation')
    w.check(len(after['attempts'])==2 and after['attempts'][1]['status']=='failed','Both complete HTTP attempt snapshots remain visible after reconciliation and success')
    again=w.call('finance',base+'/execute','POST',{'row_version':after['row_version'],'otp':'123456'})['debit']
    w.check(again['status']=='success' and fixture.state()['debit_calls']==calls['debit_calls'],'Repeated successful HTTP confirmation cannot debit again')
    w.call('panel','/finance/greenpay/requests/'+request_id,role=None,signed=False)
