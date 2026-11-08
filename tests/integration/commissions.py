from pathlib import Path
"""WordPress user-level commission precedence, server authority, explicit zero and frozen cases."""
import json


def commission_acceptance(w):
    user=w.stack.accounts['acceptor']['id'];path='/api/v1/admin/users/'+user+'/commission'
    initial=w.call('partners',path)['commission'];w.call('partners',path,role='acceptor',expected=403)
    w.call('partners',path,'POST',{'revision':initial['revision'],'commission_percent':'2.5'},role='operations_expert',expected=403)
    saved=w.call('partners',path,'POST',{'revision':initial['revision'],'commission_percent':'۲٫۵'})['commission']
    w.call('partners',path,'POST',{'revision':initial['revision'],'commission_percent':'1'},expected=409)
    w.call('partners',path,'POST',{'revision':saved['revision'],'commission_percent':'100.1'},expected=422)
    cases=json.loads((Path(__file__).resolve().parents[2]/'loans.noeel.ir/public_html/tests/fixtures/golden-calculations.json').read_text())
    settings=dict(next(x['settings'] for x in cases if x['type']=='leasing'),acceptor_commission_percent=3)
    plan=w.call('loans','/api/v1/plans','POST',{'code':'REFERENCE-USER-COMMISSION','name':'Synthetic reference commission','type':'leasing','settings':settings,'required_documents':[]},expected=201)['plan']
    w.call('loans','/api/v1/plans/'+plan['id']+'/status','POST',{'status':'active'})
    payload={'plan_id':plan['id'],'amount':100000000,'installment_count':12,'installment_interval':1,'commission_override':99,'plan_context':'merchant_request'}
    result=w.call('loans','/api/v1/calculations','POST',payload,role='acceptor',expected=201)['result']
    w.check(result['acceptorCommission']==2500000,'Merchant calculator uses the canonical user commission and ignores a forged client percentage')
    loan=w.call('loans','/api/v1/requests','POST',dict(payload,requested_amount=100000000,submit=False),role='acceptor',expected=201)['request']
    w.check(float(loan['calculation_result']['acceptorCommission'])==2500000 and float(loan['acceptor_payment_amount'])==float(result['acceptorPayment']),'Merchant request and calculator apply the same reference commission')
    saved=w.call('partners',path,'POST',{'revision':saved['revision'],'commission_percent':'0'})['commission']
    result=w.call('loans','/api/v1/calculations','POST',payload,role='acceptor',expected=201)['result']
    w.check(result['acceptorCommission']==0,'An explicit zero user commission replaces the plan default')
    old=w.call('loans','/api/v1/requests/'+loan['id'])['request'];w.check(float(old['calculation_result']['acceptorCommission'])==2500000,'Changing a user commission never recalculates previous case snapshots')
    applicant=w.call('loans','/api/v1/calculations','POST',dict(payload,plan_context='applicant_request'),role='applicant',expected=201)['result']
    w.check(applicant['acceptorCommission']==3000000,'Applicant calculator keeps the reference plan commission instead of a selected merchant user override')
    saved=w.call('partners',path,'POST',{'revision':saved['revision'],'commission_percent':''})['commission']
    result=w.call('loans','/api/v1/calculations','POST',payload,role='acceptor',expected=201)['result']
    w.check(saved['commission_percent'] is None and result['acceptorCommission']==3000000,'Clearing the user setting restores the plan commission')
    w.call('panel','/users/'+user+'/commission',role=None,signed=False)
    w.call('panel','/users/'+user+'/commission','POST',{},role=None,signed=False,expected=403)
