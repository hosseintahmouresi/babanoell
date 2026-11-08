"""Completion-stage evidence and frozen per-document guidance, independently of financial calculations."""
import base64
from onboarding import CooperationWorkflow


def plan_document_acceptance(w):
    config={'min_amount':1,'max_amount':100000000,'active_installments':[6],'fixed_monthly_rate':2,'prepayment_percent':20}
    rules={'visibility_contexts':['merchant_request','applicant_request','calculator'],'contract_mode':'digital','completed_check_files_enabled':False,'required_document_descriptions':{'national_card_front':'Synthetic original instruction <b>readable</b>'},'loan_application_contract_enabled':False,'facility_agreement_contract_enabled':False}
    plan=w.call('loans','/api/v1/plans','POST',{'code':'REFERENCE-PHASE-EVIDENCE','name':'Synthetic completion evidence','type':'market','settings':config,'rules':rules,'required_documents':['national_card_front']},expected=201)['plan'];pid=plan['id']
    w.call('loans','/api/v1/plans/'+pid+'/status','POST',{'status':'active'})
    body={'plan_id':pid,'submit':True,'full_name':'Synthetic completion applicant','father_name':'Synthetic','certificate_number':'1','national_code':'0013543318','mobile_number':'09121234567','birth_date':'1370/01/01','occupation':'other','residential_address':'Synthetic residence','work_address':'Synthetic work','postal_code':'1234567890','requested_amount':1000000,'installment_count':6,'installment_interval':1,'first_due_date':w.stack.first_due,'order_items':[{'name':'Synthetic product','qty':1,'price':1000000}]}
    loan=w.call('loans','/api/v1/requests','POST',body,role='applicant',expected=201)['request'];prefix='/api/v1/requests/'+loan['id']
    initial=w.call('loans',prefix+'/document-requirements',role='applicant');w.check(initial['required_documents']==['national_card_front'],'Signed invoice and completed checks are not requested before their reference completion stage')
    edited=w.call('loans','/api/v1/plans/'+pid,'PATCH',{'settings':{'acceptor_commission_percent':1},'rules':{'required_document_descriptions':{'national_card_front':'Synthetic later instruction'}}})['plan']
    w.check(edited['settings']['min_amount']==config['min_amount'] and edited['rules']['contract_mode']=='digital' and edited['rules']['completed_check_files_enabled'] is False,'Partial plan updates preserve unrelated amounts, signing mode and check policy')
    pinned=w.call('loans',prefix+'/document-requirements',role='applicant');w.check(pinned['required_document_descriptions']['national_card_front']=='Synthetic original instruction readable','Existing case keeps its frozen, text-safe document guidance after a plan revision')
    w.check(w.call('loans',prefix,role='applicant')['request']['calculation_result_hash']==loan['calculation_result_hash'],'Updating plan guidance and commission never recalculates an older case')
    w.call('loans',prefix+'/assignment','POST',{'type':'bank_expert','user_id':w.stack.accounts['bank_expert']['id']})
    w.call('loans',prefix+'/bank-review','POST',{'status':'approved'},role='bank_expert',expected=201)
    w.call('loans',prefix+'/transition','POST',{'status':'expert_approved'},role='operations_expert')
    w.call('loans',prefix+'/transition','POST',{'status':'docs_completion'},role='bank_expert')
    required=w.call('loans',prefix+'/document-requirements',role='applicant');w.check(set(required['required_documents'])=={'national_card_front','signed_invoice_files'},'Completion requires a signed invoice and respects the explicit reference check-image switch')
    w.call('loans',prefix+'/transition','POST',{'status':'docs_verified'},role='operations_expert',expected=409)
    png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=')
    for code in required['required_documents']:
        doc=w.call('documents',f"/api/v1/entities/loan_request/{loan['id']}/documents",'POST',{'document_type':code},role='applicant',files=[('file','synthetic-'+code+'.png','image/png',png)],expected=201)['document']
        w.call('loans',prefix+'/transition','POST',{'status':'docs_verified'},role='operations_expert',expected=409)
        w.call('documents',f"/api/v1/files/{doc['id']}/review",'POST',{'status':'approved'},role='operations_expert')
    complete=w.call('documents',f"/api/v1/entities/loan_request/{loan['id']}/completion",role='applicant');w.check(complete['complete'],'Approved initial and completion evidence jointly satisfy the final checklist')
    with CooperationWorkflow(w).panel('applicant'):
        html=w.call('panel',f"/loans/requests/{loan['id']}/documents",role=None,signed=False)
        w.check(b'Synthetic original instruction readable' in html and b'bn-document-check is-complete' in html,'Native checklist shows the frozen guidance and approved review state')
        w.check(b'<option value="completed_check_files">' not in html,'Disabled completed-check uploads are hidden from the owner form')
    w.call('loans',prefix+'/transition','POST',{'status':'docs_verified'},role='operations_expert')
    # Simulate the actual installed plan format, which predates the new metadata.
    import json
    from pathlib import Path
    w.stack.command(w.stack.php_command()+[str(Path(__file__).with_name('fixtures')/'legacy-plan-docs.php'),str(w.stack.root/'fixture.json'),pid],'Synthetic legacy plan document metadata')
    current=w.call('loans','/api/v1/plans/'+pid)['plan']
    w.check(current['rules']['completion_documents_required'] and current['rules']['completed_check_files_enabled'],'A pre-upgrade plan receives current completion defaults for future cases without a database rewrite')
    later=w.call('loans','/api/v1/requests','POST',body,role='applicant',expected=201)['request'];lp='/api/v1/requests/'+later['id']
    w.call('loans',lp+'/assignment','POST',{'type':'bank_expert','user_id':w.stack.accounts['bank_expert']['id']})
    w.call('loans',lp+'/bank-review','POST',{'status':'approved'},role='bank_expert',expected=201)
    w.call('loans',lp+'/transition','POST',{'status':'expert_approved'},role='operations_expert')
    w.call('loans',lp+'/transition','POST',{'status':'docs_completion'},role='bank_expert')
    later_docs=w.call('loans',lp+'/document-requirements',role='applicant')
    w.check({'signed_invoice_files','completed_check_files'}.issubset(later_docs['required_documents']),'A new case from an installed older plan receives both current completion requirements')
    old_docs=w.call('loans',prefix+'/document-requirements',role='applicant')
    w.check(old_docs['completed_check_files_enabled'] is False and 'completed_check_files' not in old_docs['required_documents'],'Updating current plan defaults preserves the earlier case frozen opt-out')
    return loan
