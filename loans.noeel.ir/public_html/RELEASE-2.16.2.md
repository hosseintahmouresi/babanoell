# Loans 2.16.2 — live WordPress numeric parity

Localized digits, monetary grouping and decimal/percent marks in accepted plan settings are now converted to canonical numbers before persistence. The calculator registry and service input validation also normalize legacy stored settings before calculation and bounds/schedule checks, including localized installment lists and arrays. This prevents a Persian fixed deduction such as `۱٬۰۰۰٬۰۰۰` being silently cast to zero, or a valid purchase being rejected by a raw formatted bound.

The actual WordPress Hami Plus calculator was exercised with purchase1,000,000,000 / 12 installments / interval1. Before the fix, five of sixteen integer outputs differed due to the fixed deduction; changing only that input to a canonical number removed the discrepancy. The regression fixture now compares all sixteen observed outputs and localized market/legacy inputs. Invalid or blank required rates remain available for validation rejection.

No migration, math-formula rewrite, plan-row rewrite or historical calculation/result rewrite. Existing calculated cases retain their recorded results. New calculations from legacy formatted settings use the corrected numeric interpretation; operators should review newly computed financial previews before production use.

This package does not change installed private configuration or repair missing shared service keys. Panel2.23.1 remains the current interface release. Remote provider/payment/contract acceptance is separate from the local regression and live reference comparison.
