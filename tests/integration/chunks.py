"""Real multipart chunks, replay, ownership, whole-file integrity and atomic completion."""
import base64
import concurrent.futures
import hashlib
import secrets


def chunk_acceptance(w):
    entity=w.reference_loans[0]
    catalog=w.call('documents','/api/v1/categories',role='applicant')
    w.check(catalog['upload_capabilities']['chunked_upload'] and catalog['upload_capabilities']['resumable_upload'],'Documents advertises its real chunk/resume capabilities')
    chunk=catalog['upload_capabilities']['chunk_size']
    png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=')+b'\0'*(chunk*2)
    def body(data=png,**change):
        return dict({'entity_type':'loan_request','entity_id':entity,'fields':{'document_type':'national_card_front'},'file_name':'synthetic-chunked.png','total_bytes':len(data),'checksum_sha256':hashlib.sha256(data).hexdigest(),'client_key':secrets.token_hex(32)},**change)
    b=body();session=w.call('documents','/api/v1/uploads','POST',b,role='applicant',expected=201)['upload'];path='/api/v1/uploads/'+session['id']
    same=w.call('documents','/api/v1/uploads','POST',b,role='applicant',expected=201)['upload']
    w.check(session['id']==same['id'],'A repeated chunk start reuses the same actor-bound session')
    w.call('documents','/api/v1/uploads','POST',dict(b,file_name='different.png'),role='applicant',expected=409)
    w.call('documents',path,role='acceptor',expected=404)
    w.call('documents',path+'/complete','POST',{},role='applicant',expected=409)
    def send(index,data,expected=200):
        return w.call('documents',path+'/parts','POST',{'index':index},role='applicant',files=[('file','part.bin','application/octet-stream',data)],expected=expected)
    send(0,png[:chunk]);send(0,png[:chunk]);send(0,b'x'+png[1:chunk],409)
    resumed=w.call('documents',path,role='applicant')['upload'];w.check(resumed['received_parts']==[0],'Interrupted upload reports only durable received parts')
    send(1,png[chunk:chunk*2-1],422)
    for i in range(1,session['total_chunks']):send(i,png[i*chunk:(i+1)*chunk])
    # Independent HTTP workers race the same completion. One database document must survive.
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        results=list(pool.map(lambda _:w.stack.request('documents',path+'/complete','POST',{},w.tokens['applicant']),range(2)))
    w.check(all(x[0]==201 for x in results),'Concurrent chunk completion responses are successful')
    ids=[x[1]['data']['document']['id'] for x in results];w.check(len(set(ids))==1,'Concurrent and repeated completion create exactly one private document')
    doc=w.call('documents','/api/v1/files/'+ids[0],role='applicant')['document']
    w.check(doc['checksum_sha256']==hashlib.sha256(png).hexdigest() and int(doc['size_bytes'])==len(png),'Assembled private document retains whole-file SHA and exact size')
    actual=w.call('documents','/api/v1/files/'+ids[0]+'/download',role='applicant');w.check(actual==png,'Chunked document download returns the exact selected bytes')
    w.call('documents',path+'/abort','POST',{},role='applicant',expected=409)
    aborted=w.call('documents','/api/v1/uploads','POST',body(),role='applicant',expected=201)['upload']['id']
    w.call('documents','/api/v1/uploads/'+aborted+'/abort','POST',{},role='applicant')
    w.call('documents','/api/v1/uploads/'+aborted,role='applicant',expected=410)
    w.call('documents','/api/v1/uploads','POST',body(file_name='synthetic.php'),role='applicant',expected=422)
    w.call('documents','/api/v1/uploads','POST',body(total_bytes=catalog['max_upload_bytes']+1),role='applicant',expected=422)
    bad=body(checksum_sha256='0'*64);bad_session=w.call('documents','/api/v1/uploads','POST',bad,role='applicant',expected=201)['upload']
    for i in range(bad_session['total_chunks']):
        w.call('documents','/api/v1/uploads/'+bad_session['id']+'/parts','POST',{'index':i},role='applicant',files=[('file','part.bin','application/octet-stream',png[i*chunk:(i+1)*chunk])])
    w.call('documents','/api/v1/uploads/'+bad_session['id']+'/complete','POST',{},role='applicant',expected=409)
    w.call('documents','/api/v1/uploads/'+bad_session['id']+'/abort','POST',{},role='applicant')
    w.call('panel','/uploads/chunks','POST',{},role=None,signed=False,expected=403)
