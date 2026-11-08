#!/usr/bin/env python3
"""Reproduce the released FK failure and rehearse authenticated, non-destructive forward completion."""
import argparse,contextlib,fcntl,hashlib,importlib.util,json,re,shutil,tempfile,zipfile
from pathlib import Path
from stack import Stack,SERVICES
from run import Workflow

BASE='5673b0bb69ddc3c710fa0e32bee8191d2c82d236'
OVERLAY=['public/update.php','src/Platform/ResumableUploadRecovery.php','database/migrations/202611080001_resumable_uploads.php']

def main():
 parser=argparse.ArgumentParser();parser.add_argument('--packages',type=Path,required=True);parser.add_argument('--rescue',type=Path,required=True);parser.add_argument('--artifacts',type=Path,required=True);parser.add_argument('--workflow',action='store_true');args=parser.parse_args();args.artifacts.mkdir(parents=True,exist_ok=True)
 repo=Path(__file__).resolve().parents[2];spec=importlib.util.spec_from_file_location('builder',repo/'tools/build-release.py');builder=importlib.util.module_from_spec(spec);spec.loader.exec_module(builder)
 originalIndex=json.loads((repo/'releases/platform-2.18.0/release-index.json').read_text());original=repo/'releases/platform-2.18.0'/next(x['file'] for x in originalIndex['packages'] if x['service']=='documents')
 hotfixIndex=json.loads((args.packages/'release-index.json').read_text());hotfix=args.packages/next(x['file'] for x in hotfixIndex['packages'] if x['service']=='documents')
 report={'complete':False,'source_commit':hotfixIndex['source_commit'],'checks':[],'cases':[]}
 def check(ok,label):
  report['checks'].append({'name':label,'passed':bool(ok)});print(('PASS ' if ok else 'FAIL ')+label,flush=True)
  if not ok:raise AssertionError(label)
 def csrf(body):return re.search(rb'name="_csrf" value="([a-f0-9]+)"',body).group(1).decode()
 try:
  with tempfile.TemporaryDirectory(prefix='bb-collation-source-') as tmp:
   source=Path(tmp)
   for service in SERVICES:
    target=source/(service+'.noeel.ir')
    if service!='documents':target.symlink_to(repo/(service+'.noeel.ir'),target_is_directory=True);continue
    root=target/'public_html';root.mkdir(parents=True)
    for name,data in builder.git_files(service,BASE).items():
     file=root/name;file.parent.mkdir(parents=True,exist_ok=True);file.write_bytes(data)
    (root/'vendor').symlink_to(repo/'documents.noeel.ir/public_html/vendor',target_is_directory=True)
   for collation,broken in [('latin1_swedish_ci',True),('utf8mb4_general_ci',True),('latin1_swedish_ci',False),('utf8mb4_general_ci',False),('utf8mb4_unicode_ci',False)]:
    stack=Stack(source)
    try:
     stack.prepare();stack.start();fixture=Path(__file__).with_name('fixtures')/'documents-collation.php'
     def php(action,*extra):return json.loads(stack.command(stack.php_command()+[str(fixture),str(stack.root/'fixture.json'),action,*map(str,extra)],'Synthetic Documents recovery '+action))
     php('seed',collation);old=php('state');private={n:hashlib.sha256((stack.root/'documents'/n).read_bytes()).hexdigest() for n in ['config/local.env.php','storage/installed.lock','storage/synthetic-existing-document.bin']}
     archive=stack.root/'documents/storage/update.zip';shutil.copyfile(original if broken else hotfix,archive);result=php('apply',archive)
     if broken:
      check(result['failure'] is not None and '150' in result['failure'] and 'chunk_upload_parts' in result['failure'],collation+': original archive reproduces the exact FK failure')
      failed=php('state');check(failed['state']['phase']=='database_recovery_required' and 'chunk_upload_parts' not in failed['tables'],collation+': original update leaves two tables and a recovery journal')
      check(sum(x['status']=='applied' for x in failed['migrations'])==8,collation+': failed migration is not marked applied')
      with zipfile.ZipFile(args.rescue) as z:
       check(set(z.namelist())==set(OVERLAY),'Rescue archive contains exactly the three reviewed files')
       for name in z.namelist():
        target=stack.root/'documents'/name;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(z.read(name))
      with stack.browser_session():
       status,body=stack.request('documents','/update.php','POST',{'complete_resumable_upload_migration':'1','_csrf':'invalid'},form=True,signed=False)
       check(status==200 and b'name="identifier"' in body,'Anonymous visitor cannot invoke migration completion')
      with stack.browser_session():
       _,login=stack.request('documents','/update.php',signed=False)
       status,body=stack.request('documents','/update.php','POST',{'_csrf':csrf(login),'identifier':stack.accounts['operations_expert']['email'],'password':stack.password},form=True,signed=False)
       check(status==200 and b'name="identifier"' in body,'A non-administrator cannot invoke updater recovery')
      status,body=stack.request('documents','/update.php',signed=False);token=csrf(body)
      status,body=stack.request('documents','/update.php','POST',{'_csrf':token,'identifier':stack.accounts['super_admin']['email'],'password':stack.password},form=True,signed=False)
      check(status==200 and 'تکمیل اصلاح آپلود'.encode() in body,'Authenticated updater recognizes the exact recoverable migration')
      token=csrf(body);status,_=stack.request('documents','/update.php','POST',{'_csrf':'bad','complete_resumable_upload_migration':'1'},form=True,signed=False)
      check(status==403 and php('state')['state']['phase']=='database_recovery_required','Invalid CSRF cannot complete the migration')
      path=stack.root/'documents/src/Service/ChunkUploadService.php';saved=path.read_bytes();path.write_bytes(saved+b'\n ')
      status,_=stack.request('documents','/update.php','POST',{'_csrf':token,'complete_resumable_upload_migration':'1'},form=True,signed=False)
      check(status==409 and php('state')['state']['phase']=='database_recovery_required','Changed deployed source blocks the dedicated recovery');path.write_bytes(saved)
      with (stack.root/'documents/storage/update.lock').open('a+b') as lock:
       fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB);status,_=stack.request('documents','/update.php','POST',{'_csrf':token,'complete_resumable_upload_migration':'1'},form=True,signed=False);check(status==409,'Concurrent updater lock prevents overlapping repair');fcntl.flock(lock,fcntl.LOCK_UN)
      if collation=='utf8mb4_general_ci':
       php('interrupt');check(php('state')['state']['phase']=='migrating','Interrupted repair is rehearsed after SQL commit and before release completion')
      status,body=stack.request('documents','/update.php','POST',{'_csrf':token,'complete_resumable_upload_migration':'1'},form=True,signed=False)
      check(status==200 and php('state')['state']['phase']=='complete','Forward recovery completes the additive migration without database restoration')
      status,_=stack.request('documents','/update.php','POST',{'_csrf':token,'complete_resumable_upload_migration':'1'},form=True,signed=False);check(status==409,'Repeated recovery cannot change a completed release')
      status,body=stack.request('documents','/update.php','POST',{'_csrf':token,'upload_validate':'1'},files=[('package',hotfix.name,'application/zip',hotfix.read_bytes())],signed=False)
      check(status==200 and b'name="apply_update"' in body,'Existing updater validates the corrective Documents archive')
      status,body=stack.request('documents','/update.php','POST',{'_csrf':csrf(body),'apply_update':'1'},form=True,signed=False)
      check(status==200 and php('state')['version']['version']=='1.23.1','Existing updater installs Documents 1.23.1 after the repair')
     else:
      check(result['failure'] is None,'Corrective archive upgrades the standard installation directly')
     final=php('state');check(final['state']['phase']=='complete' and len(final['migrations'])==9 and all(x['status']=='applied' for x in final['migrations']),collation+': all nine migrations are applied')
     check(final['tables']['chunk_upload_parts']=='utf8mb4_unicode_ci' and final['tables']['chunk_uploads']=='utf8mb4_unicode_ci',collation+': parent and child use the same explicit collation')
     check(final['document_label']==old['document_label'] and final['file_hash']==old['file_hash'],collation+': existing document metadata and exact original bytes are preserved')
     check(all(hashlib.sha256((stack.root/'documents'/n).read_bytes()).hexdigest()==h for n,h in private.items() if n!='storage/installed.lock'),collation+': private configuration and file bytes remain unchanged')
     fk=php('fk');check(fk['orphan_rejected'] and fk['cascade_removed_parts'] and fk['rerun']==[],collation+': foreign key, cascading delete and migration replay are correct')
     status,health=stack.request('documents','/healthz',signed=False);check(status==200 and health['version']=='1.23.1',collation+': Documents health and service connectivity recover')
     report['cases'].append({'database_collation':collation,'recovered_failed_2_18':broken,'version':'1.23.1','migrations':9})
     if args.workflow and collation=='latin1_swedish_ci' and broken:
      with (args.artifacts/'post-recovery-workflow.log').open('w') as log,contextlib.redirect_stdout(log):w=Workflow(stack);w.run()
      report['post_recovery_http_checks']=len(w.checks);check(all(x['passed'] for x in w.checks),'Full seven-service workflow passes after recovering the latin1 installation')
    finally:stack.close()
  report['complete']=True
 except Exception as error:report['error']=str(error);raise
 finally:(args.artifacts/'recovery-validation.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
if __name__=='__main__':main()
