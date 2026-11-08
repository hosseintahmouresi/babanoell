"""Intelligent login over real Panel/Identity HTTP; an isolated receiver accepts SMS locally."""
import concurrent.futures
import contextlib
import hashlib
import hmac
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
import json
from pathlib import Path
import re
import threading
import urllib.parse

class SmartAuthFixture:
    def __init__(self,w):
        self.w=w;self.stack=w.stack;self.messages=[];self.reject=False;fixture=self
        class Handler(BaseHTTPRequestHandler):
            def log_message(self,*args):pass
            def do_POST(self):
                raw=self.rfile.read(int(self.headers.get('Content-Length','0')));h=self.headers
                canonical='\n'.join(('v1','identity',h.get('X-Noel-Timestamp',''),h.get('X-Request-Id',''),'POST',self.path,hashlib.sha256(raw).hexdigest()))
                valid=self.path=='/api/v1/sms/send-pattern' and hmac.compare_digest(h.get('X-Noel-Payload-Signature',''),'v1='+hmac.new(fixture.stack.secret.encode(),canonical.encode(),hashlib.sha256).hexdigest())
                if valid:fixture.messages.append(json.loads(raw))
                self.send_response(200 if valid and not fixture.reject else 503);self.send_header('Content-Type','application/json');self.end_headers();self.wfile.write(json.dumps({'data':{'accepted':valid and not fixture.reject}}).encode())
        self.server=ThreadingHTTPServer(('127.0.0.1',0),Handler);self.thread=threading.Thread(target=self.server.serve_forever,daemon=True);self.thread.start()
        self.configfile=self.stack.root/'identity/config/local.env.php';self.original=self.configfile.read_bytes()
        self.fixture('configure',{'base':f'http://127.0.0.1:{self.server.server_port}'})
        self.accounts=self.fixture('seed')
    def fixture(self,action,data=None):
        return json.loads(self.stack.command(self.stack.php_command()+[str(Path(__file__).with_name('fixtures')/'smart-auth.php'),str(self.stack.root/'fixture.json'),action,json.dumps(data or {})],'Synthetic smart auth fixture'))
    def api(self,action,payload,expected=200):
        return self.w.call('identity','/api/v1/auth/smart/'+action,'POST',dict(payload,client_ip='192.0.2.91'),role=None,body_auth=True,expected=expected)
    def code(self):return self.messages[-1]['variables'][0]
    def inspect(self,mobile,challenge=None):return self.fixture('inspect',{'mobile':mobile,'challenge_id':challenge or ''})
    def cooldown(self):self.fixture('cooldown')
    def close(self):self.configfile.write_bytes(self.original);self.server.shutdown();self.server.server_close();self.thread.join()

def smart_auth_acceptance(w):
    with contextlib.closing(SmartAuthFixture(w)) as f:
        settings=w.call('identity','/api/v1/admin/auth-settings')['settings']
        w.call('identity','/api/v1/admin/auth-settings',role='admin',expected=403)
        w.call('identity','/api/v1/auth/smart/discover','POST',{'identifier':'09387777001'},role=None,expected=403)
        existing=f.api('discover',{'identifier':w.stack.accounts['applicant']['email']})
        w.check(existing['next']=='password' and not f.messages,'Existing password accounts choose the password stage without sending an SMS')
        unknown=f.api('discover',{'identifier':'۰۹۳۸۷۷۷۷۰۱۱'})
        w.check(unknown['next']=='register' and unknown['mobile']=='09387777011' and not f.messages,'An unknown normalized mobile opens registration before any SMS or account creation')
        registration={'mobile':'09387777011','display_name':'Synthetic new applicant','username':'smart.new','password':''}
        f.api('register',registration,expected=503)
        w.check(f.inspect(registration['mobile'])['user'] is None,'An unconfigured SMS provider cannot create an unverified account')
        saved=w.call('identity','/api/v1/admin/auth-settings','POST',dict(settings, sms_pattern_id=123))['settings']
        w.call('identity','/api/v1/admin/auth-settings','POST',dict(settings,sms_pattern_id=123),expected=409)
        c=f.api('register',dict(registration,roles=['super_admin'],status='active'));code=f.code()
        w.check(c['next']=='otp' and len(f.messages)==1 and f.messages[-1]['pattern_id']==123,'Identity sends only the code through the signed, local SMS bridge after valid registration details')
        w.check(f.inspect(registration['mobile'],c['challenge_id'])['challenge']['hash_only'] and code not in json.dumps(c),'The durable challenge stores only a keyed hash and never returns the code')
        f.api('resend',{'challenge_id':c['challenge_id']},expected=429)
        f.api('verify',{'challenge_id':c['challenge_id'],'code':'000000'},expected=422)
        result=f.api('verify',{'challenge_id':c['challenge_id'],'code':code});token=result['session']['session_credential']
        me=w.stack.request('identity','/api/v1/auth/me',token=token)[1]['data']
        db=f.inspect(registration['mobile'],c['challenge_id'])
        w.check(me['roles']==['applicant'] and db['user']['mobile_verified_at'] and not db['user']['has_password'] and db['challenge']['registration_cleared'],'Verified signup grants only applicant, preserves optional password semantics and erases pending registration data')
        f.api('verify',{'challenge_id':c['challenge_id'],'code':code},expected=410)
        f.api('register',dict(registration,display_name='Synthetic overwrite attempt'),expected=409)
        w.check(f.inspect(registration['mobile'])['user']['id']==db['user']['id'],'Duplicate registration cannot replace a user or their roles')
        c=f.api('discover',{'identifier':'smart.nohash'});code=f.code()
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            results=list(pool.map(lambda _:w.stack.request('identity','/api/v1/auth/smart/verify','POST',{'challenge_id':c['challenge_id'],'code':code,'client_ip':'192.0.2.92'},body_auth=True),range(2)))
        w.check(sorted(x[0] for x in results)==[200,410],'Concurrent correct verification consumes a challenge once and creates one session')
        before=len(f.messages)
        f.api('discover',{'identifier':'smart.disabled'},expected=403);f.api('discover',{'identifier':'smart.locked'},expected=403)
        w.check(len(f.messages)==before,'Disabled and locked accounts cannot receive login codes')
        c=f.api('discover',{'identifier':'smart.changed'});code=f.code();f.fixture('password',{'id':f.accounts['smart.changed']['id']})
        f.api('verify',{'challenge_id':c['challenge_id'],'code':code},expected=409)
        w.check(f.api('discover',{'identifier':'smart.changed'})['next']=='password','A newly set account password invalidates the older SMS login path')
        c=f.api('discover',{'identifier':'smart.expires'});f.fixture('expire',{'id':c['challenge_id']})
        f.api('verify',{'challenge_id':c['challenge_id'],'code':f.code()},expected=410)
        f.cooldown();c=f.api('resend',{'challenge_id':c['challenge_id']});w.check(c['next']=='otp','An expired code can be resent within the same bounded login flow');old=f.code();f.cooldown();c=f.api('resend',{'challenge_id':c['challenge_id']});new=f.code()
        if old!=new:f.api('verify',{'challenge_id':c['challenge_id'],'code':old},expected=422)
        for _ in range(5):
            status,_=w.stack.request('identity','/api/v1/auth/smart/verify','POST',{'challenge_id':c['challenge_id'],'code':'000000','client_ip':'192.0.2.93'},body_auth=True)
            w.check(status in (422,410),'Failed code attempts remain durably bounded')
        f.api('verify',{'challenge_id':c['challenge_id'],'code':new},expected=410)
        w.check(f.inspect('09387777005',c['challenge_id'])['challenge']['attempts']==5,'Resending cannot reset the five-attempt budget')
        f.cooldown();f.reject=True
        reg2={'mobile':'09387777012','username':'smart.rejected','display_name':'Synthetic rejected delivery','password':'Synthetic-password-123'}
        f.api('register',reg2,expected=503);f.reject=False
        w.check(f.inspect(reg2['mobile'])['user'] is None,'A rejected SMS delivery cannot verify a phone or create a session/account')
        # Native forms carry the challenge only in the server session, never in markup.
        with w.stack.browser_session():
            html=w.call('panel','/login',role=None,signed=False)
            w.check(b'name="identifier"' in html and b'name="password"' not in html,'The first login screen asks for one identifier only')
            def form(payload):
                nonlocal html
                token=re.search(rb'name="_csrf" value="([^"]+)"',html).group(1).decode();html=w.call('panel','/login','POST',dict(payload,_csrf=token),role=None,signed=False,form=True);return html
            form({'action':'identify','identifier':'smart.browser'})
            w.check(b'name="display_name"' in html and b'name="mobile"' in html,'Unknown usernames lead to the simple registration details stage')
            form({'action':'register','mobile':'09387777013','username':'smart.browser','display_name':'Synthetic browser applicant','password':'Synthetic-password-123'})
            w.check(b'name="code"' in html and b'name="challenge_id"' not in html and f.code().encode() not in html,'Native OTP form keeps its challenge and code out of browser markup')
            form({'action':'verify','code':f.code()})
            w.check(b'name="identifier"' not in html and f.inspect('09387777013')['user']['has_password'],'Verified native signup signs in immediately and preserves the chosen password')
        w.check(f.api('discover',{'identifier':'smart.browser'})['next']=='password','A registered username with a chosen password uses password login next time')
        f.api('discover',{'identifier':[]},expected=422)
        w.call('identity','/api/v1/admin/auth-settings','POST',dict(saved,registration_enabled=False))
        f.api('discover',{'identifier':'09387777019'},expected=403)
        w.check(f.api('discover',{'identifier':'smart.browser'})['next']=='password','Disabling new registration does not disable existing password accounts')
