"""Real HTTP credit flow with a provider transport injected only into disposable copies."""
from __future__ import annotations
import json
from pathlib import Path
import shutil
import hashlib
import re
from concurrent.futures import ThreadPoolExecutor


class CreditFixture:
    def __init__(self, stack):
        self.stack = stack
        self.path = stack.root/'integrations/storage/synthetic-credit.json'
        self.path.write_text(json.dumps({'mode': 'disabled'}))
        fixture = stack.root/'integrations/storage/SyntheticCreditTransport.php'
        shutil.copyfile(Path(__file__).with_name('fixtures')/'CreditTransport.php', fixture)
        index = stack.root/'integrations/public/index.php'
        code = index.read_text()
        old = 'new \\Babanoell\\Integrations\\App\\Router($pdo)'
        assert old in code
        code = code.replace(old, "new \\Babanoell\\Integrations\\App\\Router($pdo,new \\Babanoell\\Tests\\CreditTransport($root.'/storage/synthetic-credit.json'))")
        code = code.replace("require $root.'/bootstrap.php';", "require $root.'/bootstrap.php';require_once $root.'/storage/SyntheticCreditTransport.php';putenv('INTEGRATIONS_ICS24_USERNAME=synthetic-user');putenv('INTEGRATIONS_ICS24_PASSWORD=synthetic-password');")
        index.write_text(code)
        stack.env['INTEGRATIONS_ICS24_USERNAME'] = 'synthetic-user'
        stack.env['INTEGRATIONS_ICS24_PASSWORD'] = 'synthetic-password'

    def mode(self, mode):
        state = json.loads(self.path.read_text())
        state['mode'] = mode
        self.path.write_text(json.dumps(state))

    def state(self):
        return json.loads(self.path.read_text())


def complete_credit(workflow, request_id, role='super_admin', mode='ready', fixture=None):
    if fixture:
        fixture.mode(mode)
    base = f'/api/v1/requests/{request_id}/credit'
    queued = workflow.call('loans', base+'/request', 'POST', {}, role=role,
                           key='credit-request-'+request_id, expected=201)['verification']
    vid = queued['id']
    action = base+'/verifications/'+vid
    workflow.call('loans', action+'/dispatch', 'POST', {}, role=role)
    workflow.call('loans', action+'/otp', 'POST', {'otp': '۱۲۳۴۵۶'}, role=role,
                  key='credit-otp-'+request_id)
    result = workflow.call('loans', action+'/refresh', 'POST', {}, role=role)
    return vid, result


def credit_acceptance(workflow):
    w = workflow
    stack = w.stack
    fixture = w.credit_fixture
    fixture.mode('ready')
    plan = w.call('loans', '/api/v1/plans', 'POST', {
        'code': 'CREDIT-ACCEPTANCE', 'name': 'Credit acceptance plan', 'type': 'market',
        'settings': {'min_amount': 1, 'max_amount': 100000000, 'active_installments': [6],
                     'fixed_monthly_rate': 2, 'prepayment_percent': 20},
        'rules': {'visibility_contexts':['merchant_request','applicant_request','calculator'],'requires_bank_validation': True},
    }, expected=201)['plan']
    w.call('loans', '/api/v1/plans/'+plan['id']+'/status', 'POST', {'status': 'active'})
    sequence = 0
    def create():
        nonlocal sequence
        sequence += 1
        digits = '001'+str(sequence).zfill(6)
        remainder = sum(int(value)*(10-index) for index, value in enumerate(digits)) % 11
        national = digits+str(remainder if remainder < 2 else 11-remainder)
        data = {'plan_id': plan['id'], 'submit': True, 'applicant_user_id': stack.accounts['applicant']['id'],
                'full_name': 'Credit acceptance applicant', 'father_name': 'Synthetic', 'certificate_number': '1',
                'national_code': national, 'mobile_number': '0912'+str(sequence).zfill(7),
                'birth_date': '1370/01/01', 'occupation': 'other', 'residential_address': 'Synthetic address',
                'work_address': 'Synthetic work', 'postal_code': '1234567890', 'requested_amount': 1000000,
                'installment_count': 6, 'installment_interval': 1, 'first_due_date': stack.first_due,
                'order_items': [{'name': 'Synthetic product', 'qty': 1, 'price': 1000000}]}
        loan = w.call('loans', '/api/v1/requests', 'POST', data, expected=201)['request']
        w.check(loan['status'] == 'draft', 'Required credit is not bypassed by submit=true')
        return loan
    def state(action, vid):
        result = stack.command(stack.php_command()+[str(Path(__file__).with_name('fixtures')/'credit-state.php'),
                               str(stack.root/'fixture.json'), action, vid], 'credit fault fixture')
        return json.loads(result)
    def queue(loan):
        base = '/api/v1/requests/'+loan['id']+'/credit'
        verification = w.call('loans', base+'/request', 'POST', {}, role='applicant',
                              key='credit-acceptance-'+loan['id'], expected=201)['verification']
        return base, verification['id']

    loan = create()
    base, vid = queue(loan)
    w.call('loans', '/api/v1/requests/'+loan['id']+'/submit', 'POST', {}, role='applicant', expected=422)
    w.call('loans', base, role='acceptor', expected=403)
    action = base+'/verifications/'+vid
    w.call('loans', action+'/dispatch', 'POST', {}, role='applicant')
    calls_before = sum(count for path, count in fixture.state()['calls'].items() if path == '/b2b/api/request')
    state('lose-reference', vid)
    w.call('loans', action+'/recover', 'POST', {}, role='applicant')
    calls_after = sum(count for path, count in fixture.state()['calls'].items() if path == '/b2b/api/request')
    w.check(calls_before == calls_after, 'A lost dispatch response is recovered without another paid provider request')
    w.call('loans', action+'/refresh', 'POST', {}, role='applicant', expected=409)
    w.call('loans', action+'/otp', 'POST', {'otp': '000000'}, role='applicant', key='bad-otp-'+loan['id'], expected=422)
    w.call('loans', action+'/otp', 'POST', {'otp': '۱۲۳۴۵۶'}, role='applicant', key='good-otp-'+loan['id'])
    fixture.mode('processing')
    pending = w.call('loans', action+'/refresh', 'POST', {}, role='applicant')
    w.check(pending['credit_status'] == 'pending', 'OTP and a processing response never approve a credit report')
    fixture.mode('wrong_subject')
    w.call('loans', action+'/refresh', 'POST', {}, role='applicant', expected=503)
    fixture.mode('malformed')
    w.call('loans', action+'/refresh', 'POST', {}, role='applicant', expected=503)
    fixture.mode('lost')
    w.call('loans', action+'/refresh', 'POST', {}, role='applicant')
    w.call('loans', action+'/refresh', 'POST', {}, role='applicant')
    regenerated = sum(count for path, count in fixture.state()['calls'].items() if path.endswith('/reGenerateReport'))
    w.check(regenerated == 1, 'Reference report regeneration is limited to one attempt per hour')
    fixture.mode('ready')
    with ThreadPoolExecutor(max_workers=2) as pool:
        replies = list(pool.map(lambda _: w.call('loans', action+'/refresh', 'POST', {}, role='applicant'), range(2)))
    w.check(all(reply['credit_status'] == 'verified' for reply in replies), 'Concurrent final results are idempotent')
    w.check(state('evidence-count', vid)['count'] == 1, 'Concurrent polling creates exactly one immutable evidence row')
    report = w.call('loans', base+'/reports/'+vid, role='applicant')
    w.check(hashlib.sha256(report['canonical_json'].encode()).hexdigest() == report['sha256'], 'The stored report bytes match the evidence digest')
    w.call('loans', '/api/v1/requests/'+loan['id']+'/bank-credit/result', 'POST',
           {'verification_id': vid, 'status': 'verified', 'score': 999}, role='applicant', expected=403)
    state('expire-evidence', vid)
    w.call('loans', '/api/v1/requests/'+loan['id']+'/submit', 'POST', {}, role='applicant', expected=422)
    w.check(state('safe-logs', vid)['safe'], 'Integration operation logs contain no raw report, OTP or identity fields')

    other = create()
    other_base, other_vid = queue(other)
    other_action = other_base+'/verifications/'+other_vid
    w.call('loans', other_action+'/dispatch', 'POST', {}, role='applicant')
    w.call('loans', other_action+'/otp', 'POST', {'otp': '123456'}, role='applicant', key='otp-'+other['id'])
    w.call('loans', '/api/v1/requests/'+other['id'], 'PATCH', {'mobile_number': '09129999999'}, role='applicant')
    w.call('loans', other_action+'/refresh', 'POST', {}, role='applicant', expected=409)
    w.call('loans', other_base+'/reports/'+vid, role='applicant', expected=404)

    valid = create()
    valid_vid, _ = complete_credit(w, valid['id'], role='applicant', fixture=fixture)
    w.call('loans', '/api/v1/requests/'+valid['id']+'/submit', 'POST', {}, role='applicant')
    state('expire-evidence', valid_vid)
    snapshot = w.call('loans', '/api/v1/requests/'+valid['id']+'/credit', role='applicant')
    w.check(snapshot['submission']['ready'], 'Consumed evidence remains historical proof after its submission window')
    fixture.mode('disabled')
