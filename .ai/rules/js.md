---
paths:
  - 'resources/js/**/*.tsx'
---

# Js

## Forms must surface every validation error, including nested array fields
A failed save must never look like nothing happened. Every form needs:
- A visible `FormErrorSummary` (see resources/js/Components/FormErrorSummary.tsx) rendered at the top, plus per-field `InputError` next to each input.
- Explicit handling for Laravel's dotted array-field error keys (e.g. `line_items.0.description`) when the form has a repeatable/array section - `errors.line_items` alone misses these. Look up `errors[`${field}.${index}.${subfield}`]`, show it under that row, and add a red border to the offending input.
- Native HTML validation attributes (`required`, `min`, `type`) on required inputs, so obvious mistakes are caught before a server round-trip.
- A matching `attributes()` override in the FormRequest for any `*.field` wildcard rule, so messages read as "The description field is required" instead of "The line_items.0.description field is required" (the row-level placement already disambiguates which row).

Root cause this fixes: QuoteForm originally only read `errors.customer_id/status/notes/line_items`, so a per-line validation failure (e.g. empty description) redirected back with zero visible feedback.
