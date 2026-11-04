# Panel 2.9.0 — Bank workflow portal

- Bank cases display installments, grant date, bank settlement status and bank settlement date separately (not merchant settlement fields).
- Added direct links to each case's bank tasks.
- Added provider flow from the case: Shahkar/ICS24 dispatch, ICS24 OTP submission, and provider status check through existing Loans APIs.
- OTP acceptance is not credit-score approval. Do not treat 'otp_verified' as 'credit approved'.
- CSV includes the new fields and protects Excel/LibreOffice against spreadsheet formula injection.
- Reuses the current Noel UI kit, role validation and CSRF.
- No new migrations. Installing this before Loans 2.9.0 could show incomplete fields.
