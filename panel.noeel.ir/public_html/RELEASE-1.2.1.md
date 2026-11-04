# Panel 1.2.1 — calculator repair and plan lifecycle UX

- Fixed the calculator fatal error caused by the missing integer parser.
- Persian/Arabic digits are accepted for installment count and interval without silent decimal coercion.
- Invalid calculator inputs now return an in-panel validation message instead of breaking the page.
- Plan management defaults to non-archived plans, with explicit archived/all filters and a direct archive shortcut.
- Only active plans remain selectable in calculator and new-request screens.
- Archived plans can be opened and moved back to active, inactive, or draft.
