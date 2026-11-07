"""Leasing, guarantor identity evidence and physical contract revisions over native HTTP."""
import base64, json, re
from pathlib import Path

def leasing_acceptance(w):
    golden=json.loads((Path(__file__).resolve().parents[2]/'loans.noeel.ir/public_html/tests/fixtures/golden-calculations.json').read_text())
    reference=next(row for row in golden if row['type']=='leasing')
    settings=dict(reference['settings']);settings['active_installments']=[reference['installments']]
    plan=w.call('loans','/api/v1/plans','POST',{'code':'LEASING-ACCEPTANCE','name':'Synthetic leasing acceptance',
        'type':'leasing','settings':settings,'required_documents':['national_card_front','guarantor_identity'],
        'rules':{'contract_mode':'physical'}},expected=201)['plan']
    w.call('loans','/api/v1/plans/'+plan['id']+'/status','POST',{'status':'active'})
    loan=w.call('loans','/api/v1/requests','POST',{'plan_id':plan['id'],'submit':True,
        'applicant_user_id':w.stack.accounts['applicant']['id'],'full_name':'Synthetic leasing applicant','father_name':'Synthetic',
        'certificate_number':'1','national_code':'0013543318','mobile_number':'09121234567','birth_date':'1370/01/01',
        'occupation':'other','residential_address':'Synthetic address','work_address':'Synthetic work','postal_code':'1234567890',
        'requested_amount':reference['amount'],'installment_count':reference['installments'],'installment_interval':reference['interval'],
        'first_due_date':w.stack.first_due,'order_items':[{'name':'Synthetic product','qty':1,'price':reference['amount']}]},expected=201)['request']
    prefix='/api/v1/requests/'+loan['id']
    w.check(float(loan['facility_amount'])==reference['expected']['loanAmount'],'Leasing financed amount reproduces reference calculation')
    schedule=w.call('loans',prefix+'/schedule',role='applicant')['schedule']
    w.check(len(schedule)==reference['installments'] and round(sum(float(x['amount']) for x in schedule))==round(float(loan['total_payment'])),'Leasing schedule preserves reference count and payment total')
    w.call('loans',prefix+'/assignment','POST',{'type':'bank_expert','user_id':w.stack.accounts['bank_expert']['id']})
    w.call('loans',prefix+'/transition','POST',{'status':'need_correction','reason':'Synthetic guarantor correction'},role='operations_expert')
    guarantors=w.call('loans',prefix+'/guarantors','POST',{'guarantors':[{'full_name':'Synthetic guarantor','national_code':'0013543318','mobile_number':'09121234567'}]},role='applicant')['guarantors'];gid=guarantors[0]['id']
    w.call('loans',prefix+'/submit','POST',{},role='applicant')
    w.call('loans',prefix+'/bank-review','POST',{'status':'approved'},role='bank_expert',expected=201)
    w.call('loans',prefix+'/transition','POST',{'status':'expert_approved'},role='operations_expert')
    w.call('loans',prefix+'/transition','POST',{'status':'docs_completion'},role='bank_expert')
    png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=')
    def upload(entity,eid,kind,role='applicant'):
        return w.call('documents',f'/api/v1/entities/{entity}/{eid}/documents','POST',{'document_type':kind},role=role,files=[('file','synthetic-signed-scan.png','image/png',png)],expected=201)['document']
    front=upload('loan_request',loan['id'],'national_card_front')
    w.call('documents',f"/api/v1/files/{front['id']}/review",'POST',{'status':'approved'},role='operations_expert')
    w.call('documents',f"/api/v1/entities/loan_request/{loan['id']}/documents",'POST',{'document_type':'guarantor_identity'},role='applicant',files=[('file','synthetic.png','image/png',png)],expected=422)
    proof=upload('loan_guarantor',gid,'guarantor_identity')
    w.call('documents',f"/api/v1/files/{proof['id']}/review",'POST',{'status':'rejected','reason':'Synthetic incomplete scan'},role='operations_expert')
    w.call('loans',prefix+'/transition','POST',{'status':'docs_verified'},role='operations_expert',expected=409)
    replacement=w.call('documents',f"/api/v1/files/{proof['id']}/versions",'POST',{},role='applicant',files=[('file','synthetic-replacement.png','image/png',png)],expected=201)['document']
    w.call('documents',f"/api/v1/files/{proof['id']}/review",'POST',{'status':'approved'},role='operations_expert')
    complete=w.call('documents',f"/api/v1/entities/loan_request/{loan['id']}/completion",role='applicant')
    w.check(complete['complete'] and complete['guarantors'][0]['guarantor_id']==gid,'Corrected guarantor evidence completes the owner checklist')
    amended=dict(guarantors[0],full_name='Synthetic corrected spelling')
    same=w.call('loans',prefix+'/guarantors','POST',{'guarantors':[amended]},role='applicant')['guarantors'][0]
    w.check(same['id']==gid,'Spelling correction retains a stable guarantor identity')
    changed=dict(same,national_code='1234567891')
    fresh_guarantor=w.call('loans',prefix+'/guarantors','POST',{'guarantors':[changed]},role='applicant')['guarantors'][0]
    w.check(fresh_guarantor['id']!=gid,'Changing the guarantor person creates a fresh identity without reusing approved documents')
    w.call('loans','/api/v1/guarantors/'+gid,role='applicant',expected=404)
    missing=w.call('documents',f"/api/v1/entities/loan_request/{loan['id']}/completion",role='applicant')
    w.check(not missing['complete'],'Old guarantor documents cannot approve a replacement person')
    fresh_proof=upload('loan_guarantor',fresh_guarantor['id'],'guarantor_identity')
    w.call('documents',f"/api/v1/files/{fresh_proof['id']}/review",'POST',{'status':'approved'},role='operations_expert')
    w.call('loans',prefix+'/transition','POST',{'status':'docs_verified'},role='operations_expert')
    w.call('loans',prefix+'/guarantors','POST',{'guarantors':[]},role='applicant',expected=403)
    for kind in ('loan_application','facility_agreement'):
        c=w.call('partners',f"/api/v1/loan-requests/{loan['id']}/contracts",'POST',{'contract_kind':kind},role='contract_expert',expected=201)['contract'];cid=c['id']
        w.call('partners',f'/api/v1/contracts/{cid}/sign-physical','POST',{},role='contract_expert',expected=422)
        scan=upload('contract',cid,'signed_contract',role='contract_expert')
        payload={'document_id':scan['id'],'version_id':scan['current_version_id'],'row_version':c['row_version'],'confirmed':True}
        w.call('partners',f'/api/v1/contracts/{cid}/sign-physical','POST',payload,role='applicant',expected=403)
        signed=w.call('partners',f'/api/v1/contracts/{cid}/sign-physical','POST',payload,role='contract_expert')['contract']
        w.check(signed['physical_sha256']==scan['checksum_sha256'] and signed['physical_document_id']==scan['id'],'Physical signature pins the exact immutable scan and checksum')
        w.call('documents',f"/api/v1/files/{scan['id']}/versions",'POST',{},role='super_admin',files=[('file','overwrite.png','image/png',png)],expected=409)
        w.call('documents',f"/api/v1/files/{scan['id']}/archive",'POST',{'reason':'Synthetic overwrite attempt'},expected=409)
        revised=w.call('partners',f'/api/v1/contracts/{cid}/reissue','POST',{'row_version':signed['row_version'],'reason':'Synthetic physical correction','content_html':'<p>Corrected synthetic physical contract</p>'},role='contract_expert',expected=201)['contract']
        w.check(revised['status']=='pending' and not revised['physical_document_id'],'Reissued physical contract starts unsigned and carries no old signature evidence')
        old=w.call('partners',f'/api/v1/contracts/{cid}',role='applicant')['contract']
        w.check(old['status']=='signed' and old['physical_document_id']==scan['id'] and old['rendered_hash']==signed['rendered_hash'],'Reissue preserves the original signed text and physical evidence')
        w.call('loans',prefix+'/grant','POST',{},role='operations_expert',expected=409)
        wrong=dict(payload,row_version=revised['row_version'])
        w.call('partners',f"/api/v1/contracts/{revised['id']}/sign-physical",'POST',wrong,role='contract_expert',expected=422)
        fresh=upload('contract',revised['id'],'signed_contract',role='contract_expert')
        final=w.call('partners',f"/api/v1/contracts/{revised['id']}/sign-physical",'POST',{'document_id':fresh['id'],'version_id':fresh['current_version_id'],'row_version':revised['row_version'],'confirmed':True},role='contract_expert')['contract']
    granted=w.call('loans',prefix+'/grant','POST',{},role='operations_expert',expected=201)
    w.check(granted['status']=='granted' if 'status' in granted else granted['request']['status']=='granted','Leasing reaches grant only after corrected guarantor and latest physical contracts')
    w.call('loans',prefix+'/guarantors','POST',{'guarantors':[]},role='applicant',expected=403)
