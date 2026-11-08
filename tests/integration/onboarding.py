"""Fresh accounts through cooperation, signing, activation recovery and merchant use."""
from __future__ import annotations

import base64
import hashlib
from contextlib import contextmanager
from concurrent.futures import ThreadPoolExecutor
import re


class CooperationWorkflow:
    def __init__(self, workflow):
        self.w = workflow
        self.stack = workflow.stack
        self.call, self.check = workflow.call, workflow.check
        self.png = base64.b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=")

    @staticmethod
    def hidden(page, name):
        return re.search(rb'name="' + name.encode() + rb'" value="([^"]*)"', page).group(1).decode()

    @contextmanager
    def panel(self, role, email=None):
        with self.stack.browser_session():
            page = self.call("panel", "/login", role=None, signed=False)
            self.call("panel", "/login", "POST", {
                "_csrf": self.hidden(page, "_csrf"),
                "identifier": email or self.stack.accounts[role]["email"], "password": self.stack.password,
            }, role=None, signed=False, form=True)
            yield

    def current(self, request_id, role="super_admin"):
        return self.call("partners", f"/api/v1/cooperation/requests/{request_id}", role=role)

    def transition(self, request_id, status, role="operations_expert", expected=200):
        row = self.current(request_id, role)["request"]
        return self.call("partners", f"/api/v1/cooperation/requests/{request_id}/transition", "POST", {
            "status": status, "row_version": row["row_version"], "note": "Synthetic cooperation review and decision",
        }, role=role, expected=expected)

    def approve_panel(self, request_id):
        with self.panel("operations_expert"):
            path = f"/partners/cooperation/{request_id}"
            page = self.call("panel", path, role=None, signed=False)
            self.call("panel", path + "/transition", "POST", {
                "_csrf": self.hidden(page, "_csrf"), "row_version": self.hidden(page, "row_version"),
                "status": "approved", "note": "Synthetic final cooperation approval",
            }, role=None, signed=False, form=True)
        state = self.current(request_id)
        state["created_merchant"] = self.call("partners", f'/api/v1/merchants/{state["request"]["merchant_id"]}')["merchant"]
        return state

    def run(self):
        catalog = self.call("partners", "/api/v1/marketplace/catalog")["catalog"]
        if not any(t["id"] == "goods" for t in catalog["categories"]):
            catalog["categories"].append({"id": "goods", "label": "Synthetic goods", "active": True})
        if not any(t["id"] == "tehran" for t in catalog["locations"]):
            catalog["locations"].append({"id": "tehran", "label": "Synthetic Tehran", "active": True, "parent_id": ""})
        self.call("partners", "/api/v1/marketplace/configuration", "PUT", dict(catalog, expected_revision=catalog["revision"]))
        form = self.call("partners", "/api/v1/cooperation/form")["form"]
        self.call("partners", "/api/v1/cooperation/form", "POST", {
            "expected_revision": form["revision"], "fields": {
                "national_card": {"visible": True, "required": True},
                "business_license": {"visible": True, "required": True},
            },
        })
        plan = self.call("loans", "/api/v1/plans", "POST", {
            "code": "ONBOARDING-PLAN", "name": "Synthetic onboarding plan", "type": "market",
            "settings": {"min_amount": 1, "max_amount": 100000000, "active_installments": [6],
                         "fixed_monthly_rate": 2, "prepayment_percent": 20},
            "rules": {"merchant_access_mode": "allowlist"},
        }, expected=201)["plan"]
        self.plan_id = plan["id"]
        self.call("loans", f'/api/v1/plans/{self.plan_id}/status', "POST", {"status": "active"})
        for number, kind in enumerate(("individual", "legal"), 1):
            self.case(kind, number)

    def case(self, kind, number):
        role = "new_cooperation_" + kind
        email = role + "@example.test"
        page = self.call("panel", "/users/new", role=None, signed=False)
        self.call("panel", "/users/new", "POST", {
            "_csrf": self.hidden(page, "_csrf"), "display_name": "Synthetic new " + kind,
            "mobile": "0919333000" + str(number), "email": email,
            "password": self.stack.password, "roles[]": ["applicant"],
        }, role=None, signed=False, form=True)
        users = self.call("identity", "/api/v1/admin/users?q=" + email)["items"]
        self.check(len(users) == 1 and users[0]["roles"] == ["applicant"], "Panel creates a fresh applicant account: " + kind)
        user_id = users[0]["id"]
        session = self.call("identity", "/api/v1/auth/login", "POST", {
            "identifier": email, "password": self.stack.password,
        }, role=None)
        self.w.tokens[role] = session["session"]["session_credential"]
        self.call("identity", "/api/v1/partners/activate-acceptor", "POST", {
            "cooperation_id": "a" * 26, "merchant_id": "b" * 26, "user_id": user_id,
        }, expected=403)
        with self.panel(role, email):
            path = "/partners/cooperation/new?request_type=" + kind
            page = self.call("panel", path, role=None, signed=False)
            form = self.call("partners", "/api/v1/cooperation/form", role=role)["form"]
            data = {"store_owner_name": "Synthetic new individual", "store_name": "Synthetic store " + kind,
                    "company_name": "Synthetic new legal", "mobile_number": "0919333000" + str(number),
                    "activity_field": form["options"]["activity_field_options"][0],
                    "applicant_id_number": "0013543318" if kind == "individual" else "12345678901"}
            key = self.hidden(page, "_operation_key")
            self.call("panel", "/partners/cooperation/new", "POST", dict(data, **{
                "_csrf": self.hidden(page, "_csrf"), "_operation_key": key, "form_revision": form["revision"],
                "request_type": kind,
            }), role=None, signed=False, form=True)
            rows = self.call("partners", "/api/v1/cooperation/requests", role=role)
            self.check(rows["total"] == 1, "Fresh user submits exactly one cooperation through Panel: " + kind)
            request_id = rows["items"][0]["id"]
            api = f"/api/v1/cooperation/requests/{request_id}"
            detail = f"/partners/cooperation/{request_id}"
            replay = self.call("partners", "/api/v1/cooperation/requests", "POST", {
                "request_type": kind, "form_revision": form["revision"], "data": data,
            }, role=role, key=key, expected=201)["request"]
            self.check(replay["id"] == request_id, "Cooperation submission replay reuses the original request")
            self.call("partners", api, role="applicant", expected=403)
            self.call("partners", api + "/transition", "POST", {"status": "approved"}, role=role, expected=403)
            self.transition(request_id, "under_review", expected=422)
            self.transition(request_id, "need_correction")
            if kind == "individual":
                current_form = self.call("partners", "/api/v1/cooperation/form")["form"]
                self.call("partners", "/api/v1/cooperation/form", "POST", {
                    "expected_revision": current_form["revision"],
                    "options": {"activity_field_options": ["Synthetic future activity"]},
                })
            edit = self.call("panel", detail + "/edit", role=None, signed=False)
            version = self.hidden(edit, "row_version")
            data["store_name"] = "Synthetic corrected store " + kind
            self.call("panel", detail + "/edit", "POST", dict(data, **{
                "_csrf": self.hidden(edit, "_csrf"), "row_version": version,
            }), role=None, signed=False, form=True)
            self.call("partners", api, "PATCH", {"row_version": int(version), "data": data}, role=role, expected=409)
            self.transition(request_id, "submitted", role=role, expected=422)
            for doc_type in ("cooperation_identity", "cooperation_license"):
                page = self.call("panel", detail, role=None, signed=False)
                self.call("panel", detail + "/documents", "POST", {
                    "_csrf": self.hidden(page, "_csrf"), "document_type": doc_type,
                }, role=None, signed=False, files=[("file", "synthetic.png", "image/png", self.png)])
            documents = self.call("documents", f"/api/v1/entities/cooperation_request/{request_id}/documents", role=role)["items"]
            self.check(len(documents) == 2, "Both required documents are uploaded through Panel")
            self.call("documents", f'/api/v1/files/{documents[0]["id"]}/review', "POST", {"status": "approved"}, role=role, expected=403)
            self.transition(request_id, "submitted", role=role)
            self.transition(request_id, "under_review")
            self.transition(request_id, "approved", expected=422)
            with self.panel("operations_expert"):
                for document in documents:
                    page = self.call("panel", detail, role=None, signed=False)
                    self.check((f'/documents/{document["id"]}/review').encode() in page, "Cooperation reviewer has an actionable document review form")
                    self.call("panel", detail + f'/documents/{document["id"]}/review', "POST", {
                        "_csrf": self.hidden(page, "_csrf"), "status": "approved", "reason": "Synthetic evidence reviewed",
                    }, role=None, signed=False, form=True)
                self.transition(request_id, "approved", expected=409)
                self.transition(request_id, "docs_completion")
                self.transition(request_id, "docs_verified")
                self.transition(request_id, "contracting")
                contracts_path = f"/contracts/cooperation/{request_id}"
                page = self.call("panel", contracts_path, role=None, signed=False)
                self.call("panel", contracts_path, "POST", {
                    "_csrf": self.hidden(page, "_csrf"), "mode": "digital", "contract_kind": "cooperation_agreement",
                }, role=None, signed=False, form=True)
            contract = self.call("partners", api + "/contracts", role=role)["items"][0]
            self.call("partners", f'/api/v1/contracts/{contract["id"]}/signing-link', "POST", {}, role="applicant", expected=403)
            signed, original_pdf_hash = self.sign(contract["id"], role)
            if kind == "individual":
                before = self.current(request_id)["request"]
                self.call("partners", f"/api/v1/admin/cooperation/{request_id}/profile-corrections", "POST", {
                    "row_version": before["row_version"], "reason": "Synthetic correction requiring contract reissue",
                    "acknowledge_signed": True, "fields": {"full_name": "Synthetic corrected owner name"},
                })
                self.transition(request_id, "approved", expected=409)
                self.check(self.current(request_id)["request"]["merchant_id"] is None,
                           "A signature for outdated cooperation data cannot create a merchant")
                revised = self.call("partners", f'/api/v1/contracts/{signed["id"]}/reissue', "POST", {
                    "row_version": signed["row_version"], "reason": "Synthetic profile correction and new agreement",
                    "content_html": "<p>Synthetic corrected owner name: updated cooperation agreement</p>", "mode": "digital",
                }, role="operations_expert", expected=201)["contract"]
                self.call("partners", f'/api/v1/contracts/{signed["id"]}/signing-link', "POST", {}, role=role, expected=409)
                self.transition(request_id, "approved", expected=409)
                self.sign(revised["id"], role)
                old_pdf = self.call("panel", f'/contracts/{signed["id"]}/pdf', role=None, signed=False)
                self.check(hashlib.sha256(old_pdf).hexdigest() == original_pdf_hash, "Reissue preserves the original signed cooperation PDF")
                # An authenticated account can be disabled while cooperation review is in progress.
                self.call("identity", "/api/v1/admin/user-status", "POST", {"user_id": user_id, "status": "disabled"})
                pending = self.approve_panel(request_id)
                self.call("identity", "/api/v1/admin/user-status", "POST", {"user_id": user_id, "status": "active"})
                fresh = self.call("identity", "/api/v1/auth/login", "POST", {"identifier": email, "password": self.stack.password}, role=None)
                self.w.tokens[role] = fresh["session"]["session_credential"]
            else:
                self.stack.stop("loans")
                try:
                    pending = self.approve_panel(request_id)
                finally:
                    self.stack.restart("loans")
            self.check(pending["activation"]["status"] == "pending" and pending["created_merchant"]["status"] == "inactive",
                       "Partial activation preserves an inactive merchant: " + kind)
            merchant_id = pending["created_merchant"]["id"]
            expected_error = "account_inactive" if kind == "individual" else "loans_activation_pending"
            self.check(pending["activation"]["last_error"] == expected_error, "Partial activation records the failing delivery step")
            self.call("partners", f"/api/v1/merchants/{merchant_id}", "PUT", {
                "status": "active", "row_version": pending["created_merchant"]["row_version"],
            }, expected=409)
            self.check(self.call("partners", "/api/v1/me/merchant", role=role)["merchant"] is None,
                       "Pending activation cannot supply a usable merchant membership")
            self.call("partners", api + "/activation/retry", "POST", {}, role=role, expected=403)
            if kind == "individual":
                latest = self.call("partners", api + "/contracts", role=role)["items"][0]
                new_revision = self.call("partners", f'/api/v1/contracts/{latest["id"]}/reissue', "POST", {
                    "row_version": latest["row_version"], "reason": "Synthetic revision during pending activation", "mode": "digital",
                }, role="operations_expert", expected=201)["contract"]
                self.call("partners", api + "/activation/retry", "POST", {}, role="operations_expert", expected=409)
                self.sign(new_revision["id"], role)
                self.call("partners", api + "/activation/retry", "POST", {}, role="operations_expert", expected=409)
                self.stack.stop("loans")
                try:
                    refreshed = self.approve_panel(request_id)
                finally:
                    self.stack.restart("loans")
                self.check(refreshed["activation"]["contract_id"] == new_revision["id"]
                           and refreshed["activation"]["merchant_id"] == merchant_id,
                           "A new signed revision requires explicit reapproval and preserves the pending merchant")
            with self.panel("operations_expert"):
                page = self.call("panel", detail, role=None, signed=False)
                self.check(b"/activation/retry" in page, "A partial activation exposes a controlled retry in Panel")
                with ThreadPoolExecutor(max_workers=2) as pool:
                    repeated = list(pool.map(lambda _: self.call("partners", api + "/activation/retry", "POST", {}, role="operations_expert"), range(2)))
                self.check(all(row["created_merchant"]["id"] == merchant_id for row in repeated),
                           "Concurrent activation retries keep a single merchant and role binding")
                self.call("panel", detail + "/activation/retry", "POST", {
                    "_csrf": self.hidden(page, "_csrf"),
                }, role=None, signed=False, form=True)
            active = self.current(request_id)["activation"]
            self.check(active["status"] == "active" and active["merchant_id"] == merchant_id, "Activation retry completes the same merchant")
            self.check(active["evidence"]["complete"] and active["evidence"]["required_count"] == 2
                       and len(active["evidence"]["items"]) == 2, "Approval preserves the accepted document checklist as evidence")
            replay = self.transition(request_id, "approved")
            self.check(replay["created_merchant"]["id"] == merchant_id, "Approval replay creates no additional merchant")
            me = self.call("identity", "/api/v1/auth/me", role=role)
            self.check(set(me["roles"]) == {"applicant", "acceptor"}, "Activation adds acceptor privileges and preserves the applicant role")
            self.merchant_use(role, kind, merchant_id, user_id)

    def sign(self, contract_id, role):
        page = self.call("panel", f"/contracts/{contract_id}", role=None, signed=False)
        self.check(b"/open-signing" in page, "The authenticated signer can enter contract signing from Panel")
        public = self.call("panel", f"/contracts/{contract_id}/open-signing", "POST", {
            "_csrf": self.hidden(page, "_csrf"),
        }, role=None, signed=False, form=True)
        path = re.search(rb'(/contract/[A-Za-z0-9_-]{30,120})/otp/request', public).group(1).decode()
        current = self.call("partners", f'/api/v1/contracts/{contract_id}', role=role)["contract"]
        if current["subject_type"] == "cooperation_request":
            issued = self.call("partners", f'/api/v1/contracts/{contract_id}/signing-link', "POST", {}, role=role)
            self.check(issued["public_path"] == path, "Cooperation signing and repeated issuance preserve the same public link")
            self.check(current["public_token_expires_at"] is None, "Cooperation contract link has no one-day expiry")
        otp_page = self.call("partners", path + "/otp/request", "POST", {}, role=None, signed=False, form=True)
        otp = re.search(rb"<strong>([0-9]{6})</strong>", otp_page).group(1).decode()
        self.call("partners", path + "/otp/verify", "POST", {"otp": "000000"}, role=None, signed=False, form=True, expected=409)
        self.call("partners", path + "/otp/verify", "POST", {"otp": otp}, role=None, signed=False, form=True)
        saved = self.call("partners", f"/api/v1/contracts/{contract_id}", role=role)["contract"]
        self.check(saved["status"] == "signed" and bool(saved["pdf_document_id"]) and saved["artifact_status"] == "stored",
                   "Cooperation signature stores its evidence and final PDF")
        pdf = self.call("panel", f"/contracts/{contract_id}/pdf", role=None, signed=False)
        self.check(pdf.startswith(b"%PDF-"), "The cooperation signer can download the signed PDF")
        return saved, hashlib.sha256(pdf).hexdigest()

    def merchant_use(self, role, kind, merchant_id, user_id):
        merchant = self.call("partners", f"/api/v1/merchants/{merchant_id}")["merchant"]
        with self.panel("operations_expert"):
            detail = f"/partners/merchants/{merchant_id}"
            page = self.call("panel", detail, role=None, signed=False)
            self.call("panel", detail + "/plan-access", "POST", {
                "_csrf": self.hidden(page, "_csrf"), "plan_id": self.plan_id, "status": "active",
            }, role=None, signed=False, form=True)
            page = self.call("panel", detail + "/marketplace", role=None, signed=False)
            self.call("panel", detail + "/marketplace", "POST", {
                "_csrf": self.hidden(page, "_csrf"), "row_version": self.hidden(page, "row_version"),
                "enabled": "1", "category_ids[]": ["goods"], "location_ids[]": ["tehran"], "max_facility_amount": 2500000,
            }, role=None, signed=False, form=True)
        page = self.call("panel", "/acceptor", role=None, signed=False)
        self.check(b"/acceptor/requests/new" in page, "The existing owner session can enter the activated acceptor dashboard")
        page = self.call("panel", "/acceptor/requests/new", role=None, signed=False)
        self.call("panel", "/acceptor/requests/new", "POST", {
            "_csrf": self.hidden(page, "_csrf"), "_operation_key": self.hidden(page, "_operation_key"),
            "_action": "draft", "plan_id": self.plan_id, "full_name": "Onboarded merchant customer " + kind,
            "product_name": "Synthetic onboarding product", "amount": 1000000, "installment_count": 6,
            "installment_interval": 1, "first_due_date": self.stack.first_due,
        }, role=None, signed=False, form=True)
        requests = self.call("loans", "/api/v1/requests?q=Onboarded", role=role)["items"]
        owned = [row for row in requests if row["merchant_id"] == merchant_id]
        self.check(len(owned) == 1 and owned[0]["request_origin"] in ("merchant", "acceptor")
                   and owned[0]["acceptor_user_id"] == user_id, "Activated user creates a merchant loan through Panel with both roles present")
        personal = self.call("loans", "/api/v1/requests", "POST", {
            "plan_id": self.plan_id, "plan_context": "applicant_request", "requested_amount": 1000000,
            "installment_count": 6, "full_name": "Synthetic personal application " + kind,
        }, role=role, key="onboarding-personal-" + kind, expected=201)["request"]
        self.check(personal["request_origin"] == "applicant" and personal["applicant_user_id"] == user_id,
                   "The new acceptor retains the ability to submit personal applications explicitly")
        available = self.call("loans", "/api/v1/requests", "POST", {
            "plan_id": self.plan_id, "requested_amount": 1000000, "installment_count": 6,
            "acceptor_assignment_mode": "open_acceptor_marketplace", "requested_service_scope": "all_country",
            "requested_activity_categories": ["goods"], "request_public_summary": "ONBOARDING-OPEN-" + kind,
            "submit": True, "full_name": "Synthetic marketplace applicant", "father_name": "Synthetic",
            "certificate_number": "1", "national_code": "0013543318", "mobile_number": "09121234567",
            "postal_code": "1234567890", "birth_date": "1370/01/01", "occupation": "other",
            "residential_address": "Synthetic address", "work_address": "Synthetic work",
            "installment_interval": 1, "first_due_date": self.stack.first_due,
            "order_items": [{"name": "Synthetic product", "qty": 1, "price": 1000000}],
        }, role="applicant", key="onboarding-market-" + kind, expected=201)["request"]
        market = self.call("partners", "/api/v1/marketplace/requests?q=ONBOARDING-OPEN-" + kind, role=role)
        self.check(any(row["id"] == available["id"] for row in market["items"]), "Newly configured acceptor sees eligible marketplace cases")
        page = self.call("panel", "/partners/marketplace", role=None, signed=False)
        self.call("panel", f'/partners/marketplace/{available["id"]}/claim', "POST", {
            "_csrf": self.hidden(page, "_csrf"),
        }, role=None, signed=False, form=True)
        claimed = self.call("loans", f'/api/v1/requests/{available["id"]}', role=role)["request"]
        self.check(claimed["merchant_id"] == merchant_id and claimed["acceptor_user_id"] == user_id,
                   "The fresh acceptor claims an eligible case through Panel")
