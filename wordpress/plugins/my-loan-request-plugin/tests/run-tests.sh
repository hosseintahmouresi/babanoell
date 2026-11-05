#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
echo '== PHP lint =='
php_count=0
while IFS= read -r -d '' f; do php -l "$f" >/dev/null; php_count=$((php_count+1)); done < <(find . -type f -name '*.php' -print0)
echo "PASS: ${php_count} PHP files"
echo '== JavaScript syntax =='
js_count=0
while IFS= read -r -d '' f; do node --check "$f" >/dev/null; js_count=$((js_count+1)); done < <(find . -type f -name '*.js' -print0)
echo "PASS: ${js_count} JS files"
echo '== Hardening unit tests =='
php tests/unit-hardening.php
echo '== Occupation documents unit tests =='
php tests/unit-occupation-documents.php
echo '== Plan-admin notice isolation regression tests =='
php tests/unit-plan-admin-notice-isolation.php
echo '== Plan-order unit tests =='
php tests/unit-plan-order.php
echo '== Plan-duplicate unit tests =='
php tests/unit-plan-duplicate.php
echo '== Guarantor staged-upload unit tests =='
php tests/unit-guarantor-upload.php
echo '== Guarantor real-save-handler integration tests =='
php tests/integration-guarantor-save-handler.php
echo '== Plan-duplicate real-handler integration tests =='
for mode in success busy verify_fail db_fail; do
  out="$(php tests/integration-plan-duplicate-handler.php "$mode")"
  echo "$out"
  if grep -q 'INTEGRATION_TEST_FAILED' <<<"$out"; then exit 1; fi
done
echo '== GreenPay safety unit tests =='
php tests/unit-greenpay-safety.php
echo '== Legacy contract layout compatibility tests =='
php tests/unit-legacy-contract-layout.php
echo '== Digital-to-physical conversion unit tests =='
php tests/unit-physical-conversion.php
echo '== Bank settlement cycle unit tests =='
php tests/unit-bank-settlement-cycles.php
echo '== Legacy note integration test =='
php tests/integration-admin-note-legacy.php
echo '== Admin record correction unit tests =='
php tests/unit-record-corrections.php
echo '== Static/invariant checks =='
python3 tests/static-checks.py
echo 'ALL AUTOMATED CHECKS PASSED'
