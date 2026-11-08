#!/usr/bin/env python3
"""Native-PDO regressions and a complete synthetic loan over real local HTTP."""
from __future__ import annotations

import argparse
import base64
import datetime
import json
from pathlib import Path
import re
import secrets
import sys

from stack import SERVICES, Stack
from suites import run_suites
from onboarding import CooperationWorkflow
from credit import CreditFixture, complete_credit, credit_acceptance
from notifications import notification_acceptance
from leasing import leasing_acceptance
from referrals import referral_acceptance
from commissions import commission_acceptance
from report_parity import report_acceptance
from plan_access import plan_access_acceptance
from plan_documents import plan_document_acceptance
from smart_auth import smart_auth_acceptance
from greenpay_requests import greenpay_request_acceptance
from numbering import numbering_acceptance
from chunks import chunk_acceptance
from reference_parity import reference_contract_acceptance, cooperation_notification_acceptance


class Workflow:
    def __init__(self, stack):
        self.stack = stack
        self.tokens = {}
        self.checks = []

    def ensure_completion_documents(self,request_id,role='applicant'):
        requirements=self.call('loans',f'/api/v1/requests/{request_id}/document-requirements',role=role)
        completion=self.call('documents',f'/api/v1/entities/loan_request/{request_id}/completion',role=role)
        approved={x['document_type'] for x in completion['items'] if x['review_status']=='approved'}
        png=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=')
        for code in ['signed_invoice_files','completed_check_files']:
            if code not in requirements['required_documents'] or code in approved:continue
            document=self.call('documents',f'/api/v1/entities/loan_request/{request_id}/documents','POST',{'document_type':code},role=role,files=[('file','synthetic-'+code+'.png','image/png',png)],expected=201)['document']
            self.call('documents',f"/api/v1/files/{document['id']}/review",'POST',{'status':'approved'},role='operations_expert')

    @property
    def credit_fixture(self):
        if not hasattr(self.stack, 'credit_fixture'):
            self.stack.credit_fixture = CreditFixture(self.stack)
        return self.stack.credit_fixture

    def check(self, condition, label):
        self.checks.append({"name": label, "passed": bool(condition)})
        print(("PASS " if condition else "FAIL ") + label, flush=True)
        if not condition:
            raise AssertionError(label)

    def call(self, service, path, method="GET", data=None, role="super_admin", expected=200, **kwargs):
        token = self.tokens.get(role) if role else None
        status, value = self.stack.request(service, path, method, data, token, **kwargs)
        error = value.get("error", {}).get("code", "") if isinstance(value, dict) else "non-JSON"
        if status != expected and isinstance(value, dict):
            error += " " + str(value.get("error", {}).get("message", ""))
        safe_path = re.sub(r"[A-Za-z0-9_-]{30,}", "<token>", path)
        self.check(status == expected, f"{method} {service}{safe_path}: {status} (expected {expected}) {error}")
        return value.get("data", value) if isinstance(value, dict) else value

    def login(self):
        for role, account in self.stack.accounts.items():
            value = self.call("identity", "/api/v1/auth/login", "POST",
                              {"identifier": account["email"], "password": self.stack.password}, role=None)
            self.tokens[role] = value["session"]["session_credential"]
            self.check(bool(self.tokens[role]), f"{role} has an authenticated session")

    def initialize(self):
        for service in SERVICES:
            self.call(service, "/healthz", role=None)
        self.login()
        self.call("loans", "/api/v1/requests", role=None, expected=401)
        form = self.call("panel", "/login", role=None, signed=False).decode()
        csrf = re.search(r'name="_csrf" value="([^"]+)"', form).group(1)
        page = self.call("panel", "/login", "POST", {
            "_csrf": csrf, "identifier": self.stack.accounts["super_admin"]["email"],
            "password": self.stack.password,
        }, role=None, signed=False, form=True)
        self.check(isinstance(page, bytes) and b"/logout" in page, "Panel login establishes a server-side session")

    def run(self):
        self.initialize()
        today = datetime.datetime.now(datetime.timezone.utc).date().isoformat()
        catalog = self.call("partners", "/api/v1/marketplace/catalog")["catalog"]
        definition = {
            "expected_revision": catalog["revision"], "enabled": True, "require_category": True,
            "require_location": True, "allow_all_country": True,
            "categories": [{"id": "goods", "label": "Goods"}, {"id": "services", "label": "Services"}],
            "locations": [{"id": "tehran", "label": "Tehran"},
                          {"id": "tehran-city", "label": "Tehran city", "parent_id": "tehran"},
                          {"id": "rey", "label": "Rey", "parent_id": "tehran"},
                          {"id": "fars", "label": "Fars"},
                          {"id": "shiraz", "label": "Shiraz", "parent_id": "fars"}],
        }
        self.call("partners", "/api/v1/marketplace/configuration", "PUT", definition, role="acceptor", expected=403)
        self.call("partners", "/api/v1/marketplace/configuration", "PUT", definition)
        self.call("partners", "/api/v1/marketplace/configuration", "PUT", definition, expected=409)
        invalid_tree = dict(definition, expected_revision=catalog["revision"] + 1,
                            locations=[{"id": "x", "label": "X", "parent_id": "y"},
                                       {"id": "y", "label": "Y", "parent_id": "x"}])
        self.call("partners", "/api/v1/marketplace/configuration", "PUT", invalid_tree, expected=422)
        settings_page = self.call("panel", "/partners/marketplace/settings", role=None, signed=False)
        self.check(b'name="revision"' in settings_page and b'name="parent_id"' not in settings_page,
                   "Panel renders marketplace settings and the activity catalogue")
        csrf = re.search(rb'name="_csrf" value="([^"]+)"', settings_page).group(1).decode()
        revision = int(re.search(rb'name="revision" value="([0-9]+)"', settings_page).group(1))
        self.call("panel", "/partners/marketplace/settings", "POST", {
            "_csrf": "invalid", "revision": revision, "action": "term", "kind": "categories", "label": "Injected",
        }, role=None, signed=False, form=True, expected=403)
        self.call("panel", "/partners/marketplace/settings", "POST", {
            "_csrf": csrf, "revision": revision, "action": "term", "kind": "categories",
            "label": "Synthetic Panel category", "active": "1",
        }, role=None, signed=False, form=True)
        after = self.call("partners", "/api/v1/marketplace/catalog")["catalog"]
        self.check(any(term["label"] == "Synthetic Panel category" for term in after["categories"]),
                   "Panel catalogue form persists a generated term without exposing JSON configuration")
        profile = {"enabled": True, "category_ids": ["goods"], "location_ids": ["tehran-city"], "max_facility_amount": 0}

        merchant = self.call("partners", "/api/v1/merchants", "POST", {
            "code": "E2E-MERCHANT", "name": "Synthetic integration merchant",
            "owner_user_id": self.stack.accounts["acceptor"]["id"], "status": "active",
            "metadata": {"marketplace": profile, "unrelated_setting": "preserved"},
        }, expected=201)
        self.check(merchant["compatibility_sync"]["ok"], "Partners merchant synchronizes to Loans")
        merchant_id = merchant["merchant"]["id"]
        profile_page = self.call("panel", f"/partners/merchants/{merchant_id}/marketplace", role=None, signed=False)
        self.check(b'name="category_ids[]"' in profile_page and b'name="max_facility_amount"' in profile_page,
                   "Panel renders merchant marketplace profile controls")
        csrf = re.search(rb'name="_csrf" value="([^"]+)"', profile_page).group(1).decode()
        self.call("panel", f"/partners/merchants/{merchant_id}/marketplace", "POST", {
            "_csrf": csrf, "row_version": merchant["merchant"]["row_version"], "enabled": "1",
            "category_ids[]": ["goods"], "location_ids[]": ["tehran-city"], "max_facility_amount": "۹۹۹٬۹۹۹٬۹۹۹",
        }, role=None, signed=False, form=True)
        edited = self.call("partners", f"/api/v1/merchants/{merchant_id}")["merchant"]
        self.check(float(edited["metadata"]["marketplace"]["max_facility_amount"]) == 999999999,
                   "Merchant profile form accepts Persian digits and preserves the ceiling")
        merchant_search = self.call("partners", "/api/v1/merchants?q=E2E-MERCHANT")
        self.check(merchant_search["total"] == 1 and merchant_search["items"][0]["id"] == merchant_id,
                   "Merchant search finds the created merchant")
        plan = self.call("loans", "/api/v1/plans", "POST", {
            "code": "E2E-PLAN", "name": "Synthetic integration plan", "type": "market",
            "settings": {"min_amount": 1, "max_amount": 100000000, "active_installments": [6],
                         "fixed_monthly_rate": 2, "prepayment_percent": 20},
            "required_documents": ["national_card_front"],
            "rules": {"visibility_contexts":["merchant_request","applicant_request","calculator"], "contract_mode": "digital", "requires_identity_verification": True,
                      "requires_bank_validation": True, "merchant_access_mode": "allowlist"},
        }, expected=201)["plan"]
        self.call("loans", f'/api/v1/plans/{plan["id"]}/status', "POST", {"status": "active"})
        with self.stack.browser_session():
            login_page = self.call("panel", "/login", role=None, signed=False)
            csrf = re.search(rb'name="_csrf" value="([^"]+)"', login_page).group(1).decode()
            self.call("panel", "/login", "POST", {
                "_csrf": csrf, "identifier": self.stack.accounts["applicant"]["email"], "password": self.stack.password,
            }, role=None, signed=False, form=True)
            applicant_form = self.call("panel", "/applicant/requests/new", role=None, signed=False)
            self.check(b'name="requested_activity_categories[]"' in applicant_form and b'name="request_public_summary"' in applicant_form,
                       "Applicant form uses the canonical marketplace category and location controls")
            csrf = re.search(rb'name="_csrf" value="([^"]+)"', applicant_form).group(1).decode()
            draft_page = self.call("panel", "/applicant/requests/new", "POST", {
                "_csrf": csrf, "_action": "draft", "plan_id": plan["id"], "full_name": "Synthetic Panel applicant",
                "amount": "1000000", "product_name": "Synthetic Panel product", "installment_count": 6,
                "installment_interval": 1, "first_due_date": self.stack.first_due,
                "acceptor_assignment_mode": "open_acceptor_marketplace", "requested_service_scope": "selected_locations",
                "requested_activity_categories[]": ["goods"], "requested_location_terms[]": ["rey"],
                "request_public_summary": "panel-marketplace-draft",
            }, role=None, signed=False, form=True)
            panel_id = re.search(rb'/applicant/requests/([a-f0-9]{26})', draft_page).group(1).decode()
            panel_request = self.call("loans", f"/api/v1/requests/{panel_id}", role="applicant")["request"]
            self.check(panel_request["requested_activity_categories"] == ["goods"] and panel_request["requested_location_terms"] == ["rey"]
                       and panel_request["acceptor_assignment_mode"] == "open_acceptor_marketplace", "Applicant Panel submission persists marketplace selections")
        loan = self.call("loans", "/api/v1/requests", "POST", {
            "plan_id": plan["id"], "full_name": "Synthetic integration applicant", "father_name": "Synthetic",
            "certificate_number": "1", "national_code": "0013543318", "mobile_number": "09121234567",
            "birth_date": "1370/01/01", "occupation": "other", "residential_address": "Synthetic address",
            "work_address": "Synthetic address", "postal_code": "1234567890",
            "requested_amount": 1000000, "installment_count": 6, "installment_interval": 1,
            "first_due_date": self.stack.first_due, "order_items": [{"name": "Synthetic product", "qty": 1, "price": 1000000}],
            "acceptor_assignment_mode": "open_acceptor_marketplace", "request_public_summary": "e2e-public-summary",
            "requested_activity_categories": ["goods"], "requested_service_scope": "selected_locations",
            "requested_location_terms": ["rey"],
        }, role="applicant", key="e2e-create-request", expected=201)["request"]
        request_id = loan["id"]
        prefix = f"/api/v1/requests/{request_id}"
        market_path = "/api/v1/marketplace/requests?q=e2e-public-summary"
        claim_path = f"/api/v1/marketplace/requests/{request_id}/claim"
        self.call('loans', prefix + '/submit', 'POST', {}, role='applicant', expected=422)
        self.check(self.call('loans', market_path, role='acceptor')['total'] == 0, 'Private draft is not offered before final credit validation')
        _, preflight = complete_credit(self, request_id, role='applicant', fixture=self.credit_fixture)
        self.check(preflight['credit_status'] == 'verified', 'Required report is completed before final reference-style submission')
        self.call('loans', prefix + '/submit', 'POST', {}, role='applicant')
        self.credit_fixture.mode('disabled')
        self.check(self.call("loans", market_path, role="acceptor")["total"] == 0, "Allowlisted plan is hidden until permission is granted")
        self.call("loans", claim_path, "POST", {}, role="acceptor", expected=409)
        permission_path = f'/api/v1/merchants/{merchant_id}/plan-permissions/{plan["id"]}'
        self.call("partners", permission_path, "POST", {"status": "active"})
        membership_path = f"/api/v1/merchants/{merchant_id}/memberships"
        self.call("partners", membership_path, "POST", {"user_id": self.stack.accounts["acceptor"]["id"], "membership_role": "viewer", "status": "active"}, expected=201)
        self.call("loans", claim_path, "POST", {}, role="acceptor", expected=409)
        self.call("partners", membership_path, "POST", {"user_id": self.stack.accounts["acceptor"]["id"], "membership_role": "owner", "status": "active", "is_primary": True}, expected=201)
        self.check(self.call('loans', market_path, role='acceptor')['total'] == 1, 'Matching merchant can see the finalized reference request')
        private = self.call('loans', '/api/v1/requests', 'POST', {
            'plan_id': plan['id'], 'full_name': 'Private draft owner', 'requested_amount': 1000000,
            'installment_count': 6, 'installment_interval': 1, 'first_due_date': self.stack.first_due,
            'acceptor_assignment_mode': 'open_acceptor_marketplace', 'requested_service_scope': 'all_country',
            'requested_activity_categories': ['goods'], 'request_public_summary': 'private-draft-case',
        }, role='applicant', key='private-draft-case', expected=201)['request']
        self.check(self.call('loans', '/api/v1/marketplace/requests?q=private-draft-case', role='acceptor')['total'] == 0,
                   'An otherwise matching private draft cannot appear before final submission')
        self.call('partners', '/api/v1/marketplace/requests/'+private['id']+'/claim', 'POST', {}, role='acceptor', expected=409)
        for change, label in (({"category_ids": ["services"]}, "different activity"),
                              ({"location_ids": ["shiraz"]}, "different geographic tree"),
                              ({"max_facility_amount": 1}, "amount above the merchant ceiling"),
                              ({"enabled": False}, "disabled merchant marketplace")):
            current = self.call("partners", f"/api/v1/merchants/{merchant_id}")["merchant"]
            self.call("partners", f"/api/v1/merchants/{merchant_id}/marketplace", "PUT",
                      dict(profile, **change, row_version=current["row_version"]))
            self.check(self.call("loans", market_path, role="acceptor")["total"] == 0, label + " excludes the request")
            self.call("loans", claim_path, "POST", {}, role="acceptor", expected=409)
        current = self.call("partners", f"/api/v1/merchants/{merchant_id}")["merchant"]
        self.call("partners", f"/api/v1/merchants/{merchant_id}/marketplace", "PUT", dict(profile, row_version=current["row_version"]))
        saved = self.call("partners", f"/api/v1/merchants/{merchant_id}")["merchant"]
        self.check(saved["metadata"]["unrelated_setting"] == "preserved", "Marketplace profile editing preserves other merchant metadata")
        self.call("partners", f"/api/v1/merchants/{merchant_id}/marketplace", "PUT", dict(profile, row_version=current["row_version"]), expected=409)
        self.call("partners", f"/api/v1/merchants/{merchant_id}/marketplace", "PUT", dict(profile, row_version=saved["row_version"], category_ids=["unknown"]), expected=422)
        self.call("partners", f"/api/v1/merchants/{merchant_id}/marketplace", "PUT", dict(profile, row_version=saved["row_version"]), role="acceptor", expected=403)
        self.call("partners", permission_path, "POST", {"status": "inactive"})
        self.call("loans", claim_path, "POST", {}, role="acceptor", expected=409)
        self.call("partners", permission_path, "POST", {"status": "active"})
        market = self.call("loans", "/api/v1/marketplace/requests?q=e2e-public-summary", role="acceptor")
        self.check(market["total"] == 1 and "national_code" not in market["items"][0], "Marketplace search omits private applicant fields")
        self.call("loans", prefix, role="acceptor", expected=403)
        self.call("partners", f"/api/v1/marketplace/requests/{request_id}/claim", "POST", {}, role="acceptor", expected=201)
        self.call("partners", f"/api/v1/marketplace/requests/{request_id}/claim", "POST", {}, role="acceptor", expected=409)
        self.call("loans", f"/api/v1/marketplace/requests/{request_id}/claim", "POST", {}, role="acceptor", expected=409)
        self.call("loans", prefix + "/assignment", "POST", {
            "type": "bank_expert", "user_id": self.stack.accounts["bank_expert"]["id"],
        }, role="operations_expert")
        bank_cases = self.call("loans", "/api/v1/bank/cases?q=Synthetic", role="bank_expert")
        self.check(bank_cases["total"] == 1 and bank_cases["items"][0]["id"] == request_id,
                   "Bank case search works with native PDO and respects assignment")
        tasks = self.call("loans", "/api/v1/tasks?search=Synthetic", role="operations_expert")
        self.check(tasks["total"] > 0 and all(task["request_id"] == request_id for task in tasks["items"]),
                   "Task search returns the submitted case tasks")
        self.call("loans", prefix + "/transition", "POST", {"status": "expert_approved"},
                  role="operations_expert", expected=422)
        for kind, provider in (("identity_match", "shahkar"), ("credit_report", "ics24")):
            verification = self.call("loans", prefix + "/bank-credit/request", "POST", {
                "verification_type": kind, "provider": provider,
            }, role="bank_expert", key="e2e-proof-" + kind, expected=201)["verification"]
            proof = {"verification_id": verification["id"], "status": "verified",
                     "provider_reference": "synthetic-" + kind,
                     "override_reason": "Synthetic local verification for integration testing only"}
            self.call("loans", prefix + "/bank-credit/result", "POST", proof, role="bank_expert", expected=403)
            self.call("loans", prefix + "/bank-credit/result", "POST", proof)
        self.call("loans", prefix + "/transition", "POST", {"status": "expert_approved"}, role="operations_expert")
        self.call("loans", prefix + "/bank-review", "POST", {"status": "approved"}, role="bank_expert", expected=201)
        self.call("loans", prefix + "/transition", "POST", {"status": "docs_completion"}, role="bank_expert")
        self.call("loans", prefix + "/transition", "POST", {"status": "docs_verified"}, role="operations_expert", expected=409)

        png = base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=")
        document = self.call("documents", f"/api/v1/entities/loan_request/{request_id}/documents", "POST",
                             {"document_type": "national_card_front"}, role="applicant",
                             files=[("file", "synthetic.png", "image/png", png)], expected=201)["document"]
        self.call("documents", f'/api/v1/files/{document["id"]}/review', "POST", {"status": "approved"}, role="applicant", expected=403)
        self.call("documents", f'/api/v1/files/{document["id"]}/review', "POST", {"status": "approved"}, role="operations_expert")
        self.ensure_completion_documents(request_id)
        completion = self.call("documents", f"/api/v1/entities/loan_request/{request_id}/completion", role="applicant")
        self.check(completion["complete"], "Reviewed required document completes the owner checklist")
        self.call("loans", prefix + "/transition", "POST", {"status": "docs_verified"}, role="operations_expert")
        self.call("loans", prefix + "/grant", "POST", {}, role="operations_expert", expected=409)

        for kind in ("loan_application", "facility_agreement"):
            contract = self.call("partners", f"/api/v1/loan-requests/{request_id}/contracts", "POST",
                                 {"contract_kind": kind}, role="contract_expert", expected=201)["contract"]
            cid = contract["id"]
            issued = self.call("partners", f"/api/v1/contracts/{cid}/issue-link", "POST", {}, role="contract_expert")
            path = issued["public_path"]
            self.call("partners", path, role=None, signed=False)
            otp_page = self.call("partners", path + "/otp/request", "POST", {}, role=None, signed=False, form=True)
            match = re.search(rb"<strong>([0-9]{6})</strong>", otp_page)
            self.check(match is not None, "Local-only contract OTP is available without an external SMS call")
            self.call("partners", path + "/otp/verify", "POST", {"otp": match.group(1).decode()},
                      role=None, signed=False, form=True)
            saved = self.call("partners", f"/api/v1/contracts/{cid}", role="contract_expert")["contract"]
            self.check(saved["status"] == "signed" and saved["rendered_hash"] == contract["rendered_hash"],
                       "Digital signature preserves the frozen contract hash")
            self.check(saved["artifact_status"] == "stored" and bool(saved["pdf_document_id"]),
                       "Signed artifact and PDF cross-service handoff completes")
            pdf_id = saved["pdf_document_id"]
            self.call("documents", f"/api/v1/files/{pdf_id}/download", role=None, signed=False, expected=401)
            pdf = self.call("documents", f"/api/v1/files/{pdf_id}/download", role="contract_expert")
            self.check(pdf.startswith(b"%PDF-"), "Signed contract downloads as a server-generated PDF")
            access = self.call("documents", f"/api/v1/files/{pdf_id}/access-token", "POST", {}, role="contract_expert", expected=201)
            self.call("documents", access["path"], "POST", {}, role=None, signed=False, expected=401)
            self.call("documents", access["path"].replace("/download/", "/preview/"), role=None, signed=False, expected=404)
            public_pdf = self.call("documents", access["path"], role=None, signed=False)
            self.check(public_pdf.startswith(b"%PDF-"), "Issued one-use file capability works with required service authentication")
            self.call("documents", access["path"], role=None, signed=False, expected=404)

        readiness = self.call("loans", prefix + "/readiness", role="operations_expert")["readiness"]
        self.check(readiness["ready_for_grant"], "Required identity, credit, files and both contracts satisfy grant readiness")
        granted = self.call("loans", prefix + "/grant", "POST", {}, role="operations_expert", expected=201)
        self.check(granted["request"]["status"] == "granted", "Grant persists with evidence and history")
        self.call("loans", prefix + "/grant", "POST", {}, role="operations_expert", expected=409)

        bank_body = {"request_ids": [request_id], "settlement_date": today, "notes": "Synthetic bank cycle", "total_loan_amount": 1}
        bank_cycle = self.call("finance", "/api/v1/bank-cycles", "POST", bank_body, role="bank_expert",
                               key="e2e-bank-cycle", expected=201)["cycle"]
        replay = self.call("finance", "/api/v1/bank-cycles", "POST", bank_body, role="bank_expert",
                           key="e2e-bank-cycle", expected=201)["cycle"]
        self.check(replay["id"] == bank_cycle["id"] and bank_cycle["projection_status"] == "synced",
                   "Bank batch is idempotent and projects to Loans")
        self.check(float(bank_cycle["total_loan_amount"]) == float(granted["request"]["requested_amount"]),
                   "Bank cycle ignores browser-supplied total")
        self.call("finance", "/api/v1/bank-cycles", "POST", dict(bank_body, notes="Changed"), role="bank_expert",
                  key="e2e-bank-cycle", expected=409)

        cycle = self.call("finance", "/api/v1/cycles", "POST", {
            "merchant_id": merchant_id, "settlement_date": today, "description": "Synthetic merchant cycle",
        }, role="finance_expert", key="e2e-merchant-cycle", expected=201)["cycle"]
        amount = granted["request"]["acceptor_payment_amount"]
        self.call("finance", f'/api/v1/cycles/{cycle["id"]}/items', "POST", {
            "request_id": request_id, "settlement_amount": amount, "leasing_amount": 0, "merchant_amount": amount,
        }, role="finance_expert", key="e2e-cycle-item")
        self.call("documents", f"/api/v1/entities/loan_request/{request_id}/documents", "POST",
                  {"document_type": "national_card_front"}, role="finance_expert",
                  files=[("file", "synthetic.png", "image/png", png)], expected=403)
        self.call("documents", f'/api/v1/entities/settlement_cycle/{cycle["id"]}/documents', "POST",
                  {"document_type": "national_card_front"}, role="finance_expert",
                  files=[("file", "synthetic.png", "image/png", png)], expected=403)
        receipt = self.call("documents", f'/api/v1/entities/settlement_cycle/{cycle["id"]}/documents', "POST",
                            {"document_type": "settlement_receipt"}, role="finance_expert",
                            files=[("file", "receipt.png", "image/png", png)], expected=201)["document"]
        receipt_row = self.call("finance", "/api/v1/receipts", "POST", {
            "cycle_id": cycle["id"], "document_id": receipt["id"], "amount": amount,
        }, role="finance_expert", key="e2e-receipt", expected=201)["receipt"]
        self.call("finance", f'/api/v1/receipts/{receipt_row["id"]}/review', "POST", {"status": "approved"}, role="finance_expert", expected=409)
        self.call("finance", f'/api/v1/receipts/{receipt_row["id"]}/review', "POST", {"status": "approved"}, role="admin")
        closed = self.call("finance", f'/api/v1/cycles/{cycle["id"]}/close', "POST", {}, role="finance_expert")["cycle"]
        self.check(closed["status"] == "closed", "Merchant settlement cycle closes after receipt review")
        self.call("loans", prefix + "/merchant-settlement", "POST", {
            "status": "settled", "settlement_date": today, "cycle_id": cycle["id"],
            "note": "Synthetic settlement confirmation",
        }, role="finance_expert", expected=409)
        self.call("loans", prefix + "/financier-payment", "POST", {"status": "paid", "note": "Synthetic payment"}, role="finance_expert")
        snapshot = self.call("finance", f"/api/v1/requests/{request_id}/snapshot", role="finance_expert")["finance"]
        self.check(snapshot["merchant_settlement"] is None, "Closing a cycle does not falsely mark an unpaid merchant as settled")
        final = self.call("loans", prefix, role="applicant")["request"]
        self.check(final["bank_settlement_cycle_id"] == bank_cycle["id"] and final["bank_settlement_status"] == "recorded",
                   "Merchant settlement leaves the independent bank cycle intact")

        transfer = self.call("finance", "/api/v1/greenpay/transfers", "POST", {
            "cycle_id": cycle["id"], "from_pocket": "12345678901", "to_pocket": "12345678902",
            "amount_rial": int(float(amount)), "reason": "Synthetic transfer guard test only",
        }, key="e2e-transfer-intent", expected=201)["transfer"]
        transfer_path = f'/api/v1/greenpay/transfers/{transfer["id"]}'
        self.call("finance", transfer_path + "/approve", "POST", {"note": "Synthetic self approval denied"}, expected=409)
        self.call("finance", transfer_path + "/approve", "POST", {"note": "Synthetic independent approval"}, role="admin")
        self.call("finance", transfer_path + "/execute", "POST", {"otp": "123456"}, role="admin", expected=503)
        self.check(True, "Real provider transfer remains disabled; no external money movement attempted")
        CooperationWorkflow(self).run()
        leasing_acceptance(self)
        credit_acceptance(self)
        notification_acceptance(self)
        reference_contract_acceptance(self)
        cooperation_notification_acceptance(self)
        chunk_acceptance(self)
        numbering_acceptance(self)
        commission_acceptance(self)
        greenpay_request_acceptance(self)
        plan_document_acceptance(self)
        plan_access_acceptance(self)
        report_acceptance(self)
        referral_acceptance(self)
        smart_auth_acceptance(self)
        self.stack.stop("partners")
        self.call("loans", "/api/v1/marketplace/requests", role="acceptor", expected=503)
        self.call("loans", claim_path, "POST", {}, role="acceptor", expected=503)
        self.check(True, "Unavailable canonical merchant authority prevents marketplace visibility and claims")


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--scenario", choices=("regressions", "workflow", "onboarding", "suites", "auth", "all"), default="all")
    parser.add_argument("--report", type=Path, help="Write sanitized results; ephemeral credentials are never included")
    args = parser.parse_args()
    stack = Stack(Path(__file__).resolve().parents[2])
    workflow = Workflow(stack)
    result = {"scenario": args.scenario, "complete": False, "checks": workflow.checks}
    try:
        stack.prepare()
        result["migrations"] = stack.migrations
        if args.scenario in ("suites", "all"):
            result["service_suites"] = run_suites(stack)
            if not all(suite["passed"] for suite in result["service_suites"]):
                raise AssertionError("Existing service suites failed; see service_suites in the report")
        if args.scenario in ("regressions", "all"):
            result["repository_regressions"] = json.loads(stack.fixture_regressions())
            print("PASS native PDO repository regressions", flush=True)
        if args.scenario in ("workflow", "all"):
            stack.start()
            workflow.run()
        if args.scenario == "auth":
            stack.start()
            workflow.initialize()
            smart_auth_acceptance(workflow)
        if args.scenario == "onboarding":
            stack.start()
            workflow.initialize()
            CooperationWorkflow(workflow).run()
        result["complete"] = True
    except Exception as error:
        result["error"] = str(error)
        print("FAIL " + str(error), file=sys.stderr, flush=True)
        for log in stack.logs:
            log.flush()
            log.seek(0)
            for line in log.read().splitlines():
                if "unexpected error:" in line or "database error:" in line:
                    print(line[-1500:], file=sys.stderr)
    finally:
        stack.close()
        if args.report:
            args.report.parent.mkdir(parents=True, exist_ok=True)
            args.report.write_text(json.dumps(result, ensure_ascii=False, indent=2) + "\n")
    return 0 if result["complete"] else 1


if __name__ == "__main__":
    raise SystemExit(main())
