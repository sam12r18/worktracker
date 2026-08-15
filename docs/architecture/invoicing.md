# Invoicing Architecture

## Lifecycle
`ActivitySession -> PricingService -> Draft InvoiceItem -> Finalize -> BillingRateSnapshot`

Drafts are recalculable. Finalization freezes pricing. Application code rejects edits to finalized invoice totals.

## Period boundaries
The UI accepts inclusive start/end dates. Internally generation uses an exclusive end boundary (end date + one day). If an Activity crosses a period boundary, only the overlap seconds inside the invoice period are billed.

## Concurrency
Each legitimate Activity is a separate line. Overlapping Activities are not normalized. Coverage is useful for reporting, not for limiting invoice effort.

## Export
- Excel: SpreadsheetML 2003 (`.xls`), UTF-8 XML, no PhpSpreadsheet requirement.
- PDF: authenticated print view; use browser Print -> Save as PDF. This avoids a mandatory PDF library/font dependency on shared cPanel hosting.
