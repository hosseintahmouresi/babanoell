#!/usr/bin/env python3
"""Rehearse seven native updaters from an installed Git revision and restore synthetic database backups."""
import argparse,contextlib,importlib.util,json,subprocess,tempfile
from pathlib import Path
from stack import Stack,SERVICES
from run import Workflow

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--packages',type=Path,required=True);parser.add_argument('--baseline',required=True);parser.add_argument('--artifacts',type=Path,required=True)
    parser.add_argument('--mixed-collations',action='store_true',help='Use legacy database defaults while retaining existing table collations.')
    args=parser.parse_args();args.artifacts.mkdir(parents=True,exist_ok=True)
    repo=Path(__file__).resolve().parents[2];spec=importlib.util.spec_from_file_location('builder',repo/'tools/build-release.py');builder=importlib.util.module_from_spec(spec);spec.loader.exec_module(builder)
    index=json.loads((args.packages/'release-index.json').read_text());report={'complete':False,'baseline_commit':args.baseline,'source_commit':index['source_commit'],'manual_bootstrap':False}
    fixture_script=Path(__file__).with_name('upgrade-fixture.php')
    with tempfile.TemporaryDirectory(prefix='bb-upgrade-source-') as temporary:
        source=Path(temporary)
        for service in builder.SERVICES:
            root=source/(service+'.noeel.ir/public_html');root.mkdir(parents=True)
            for name,data in builder.git_files(service,args.baseline).items():
                target=root/name;target.parent.mkdir(parents=True,exist_ok=True);target.write_bytes(data)
            if service=='documents':(root/'vendor').symlink_to(repo/'documents.noeel.ir/public_html/vendor',target_is_directory=True)
        stack=Stack(source)
        try:
            stack.prepare();report['initial_migrations']=stack.migrations
            stack.command(stack.php_command()+[str(fixture_script),str(stack.root/'fixture.json'),'seed'],'Synthetic upgrade history')
            if args.mixed_collations:
                subprocess.run(stack.client()+['-e',"ALTER DATABASE test_identity CHARACTER SET latin1 COLLATE latin1_swedish_ci; ALTER DATABASE test_partners CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci"],env=stack.env,check=True,capture_output=True,timeout=20)
                report['database_defaults']={'identity':'latin1_swedish_ci','partners':'utf8mb4_general_ci'}
            backups={};original_counts={}
            dump=stack.maria/'bin/mariadb-dump'
            for service in SERVICES:
                if service=='panel':continue  # Panel keeps encrypted sessions in private storage, not a database.
                original_counts[service]=json.loads(stack.command(stack.php_command()+[str(fixture_script),str(stack.root/'fixture.json'),'counts','test_'+service],'Snapshot synthetic row counts'))
                path=stack.root/('backup-'+service+'.sql')
                with path.open('wb') as file:
                    subprocess.run([str(dump),'--no-defaults','--socket='+stack.socket,'--user='+stack.user,'--single-transaction','--skip-comments','test_'+service],env=stack.env,stdout=file,stderr=subprocess.PIPE,check=True,timeout=60)
                backups[service]=path
            for service in SERVICES:
                (stack.root/service/'storage/release-canary.bin').write_bytes(b'Synthetic private runtime data must survive the upgrade.')
                (stack.root/service/'config/service-auth.local.php').write_text('<?php return [];\n')
            raw=stack.command(stack.php_command()+[str(Path(__file__).with_name('upgrade-apply.php')),str(stack.root/'fixture.json'),str(args.packages)],'Native update rehearsal')
            report['updates']=json.loads(raw)
            expected=sum(len(row['new_migrations']) for row in index['packages']);actual=sum(row['migrations_applied'] for row in report['updates'])
            if actual!=expected:raise AssertionError('Unexpected number of upgrade migrations')
            report['preserved_history']=json.loads(stack.command(stack.php_command()+[str(fixture_script),str(stack.root/'fixture.json'),'verify'],'Verify historical evidence'))
            print('PASS native updaters:',len(report['updates']),'; new migrations:',actual,flush=True)
            stack.start()
            with (args.artifacts/'post-upgrade-workflow.log').open('w') as log,contextlib.redirect_stdout(log):
                workflow=Workflow(stack);workflow.run()
            report['http_checks']=len(workflow.checks);report['all_http_passed']=all(check['passed'] for check in workflow.checks)
            if not report['all_http_passed']:raise AssertionError('Post-upgrade workflow failed')
            # Restore each pre-upgrade backup into a separate local database; deployed test data is untouched.
            restored=[]
            for service,path in backups.items():
                database='restore_'+service
                subprocess.run(stack.client()+['-e','CREATE DATABASE `'+database+'` CHARACTER SET utf8mb4'],env=stack.env,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE,check=True,timeout=20)
                with path.open('rb') as file:subprocess.run(stack.client()+[database],env=stack.env,stdin=file,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE,check=True,timeout=60)
                counts=json.loads(stack.command(stack.php_command()+[str(fixture_script),str(stack.root/'fixture.json'),'counts',database],'Verify restored synthetic backup'))
                if counts!=original_counts[service]:raise AssertionError('Restored row counts differ: '+service)
                restored.append({'service':service,'tables':len(counts),'row_counts_match':True})
            report['restored_databases']=restored;report['complete']=True
            print('PASS post-upgrade HTTP checks:',report['http_checks'],'; restored databases:',len(restored),flush=True)
        finally:
            stack.close();(args.artifacts/'deployment-rehearsal.json').write_text(json.dumps(report,ensure_ascii=False,indent=2)+'\n')
if __name__=='__main__':main()
