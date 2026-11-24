# Interview preparation

## 60-second introduction
I built a Laravel and Vue purchase-to-payment ERP that connects purchase orders, receiving, QC, stock posting, invoice matching and payments. The focus is business controls, traceability and role separation. I used MySQL transactions, row locking, role-based authorization and audit records. The existing regression suite passed 56 tests with 359 assertions.

## Questions and answers
### 1. Describe your project.
I built a Laravel and Vue purchase-to-payment ERP that connects purchase orders, receiving, QC, stock posting, invoice matching and payments. The focus is business controls, traceability and role separation.

### 2. Why use services?
Controllers handle HTTP validation and responses; services encapsulate business transitions, transaction boundaries and reusable rules.

### 3. What is three-way matching?
Compare invoice lines with the approved PO price and accepted posted receipt quantities, subtracting quantities reserved by other matched, pending or approved invoices.

### 4. Why not match against GRN quantity?
Received goods can be rejected or awaiting QC and posting. Only accepted posted quantities qualify.

### 5. How do partial payments work?
Sum recorded payments against an approved invoice; compare the requested amount with outstanding balance while holding an invoice lock.

### 6. How do you prevent duplicate payments?
Validate the supplier/reference pair and enforce a composite unique database index. The index protects against races as well as normal duplicates.

### 7. Why use integer paise?
Avoid binary floating-point comparison errors in financial validation; store amounts as decimal values.

### 8. What is segregation of duties?
Separate creation and approval responsibilities and enforce self-approval restrictions in services, even for users with multiple roles.

### 9. How do you prevent repeated stock posting?
Check eligibility and existing postings within transactional service logic; test repeated posting and rollback.

### 10. What happens if posting fails?
The transaction rolls back related writes; the existing regression suite tests rollback of posting, movements and balances.

### 11. How is authorization different from authentication?
Authentication identifies the session user; authorization checks whether that active user can perform a specific operation.

### 12. How do you test safely?
Use a separate MySQL testing database and RefreshDatabase, with a guard against connecting to development. The observed suite passed 56 tests.

### 13. What is an audit trail?
A record of actor, action, state changes and relevant snapshots. It supports investigation and traceability, not guaranteed immutability.

### 14. How do you handle concurrent invoice matching?
Lock the parent PO before invoice processing to serialize reservations for the same PO.

### 15. What would you improve before production?
Correct and verify seeding, remove development routes, add dedicated finance regression and concurrency tests, review deployment security, monitoring and backups.

### 16. What is the difference between QC approval and posting?
QC decides accepted/rejected quantities; posting separately updates inventory and movement records.

### 17. How does Vue communicate with Laravel?
The SPA calls JSON endpoints using the authenticated web session; server-side validation and permission checks remain authoritative.

### 18. What if an invoice is for 600 units but only 550 were accepted?
It cannot fully match; the matching service records mismatch and the invoice cannot proceed through normal approval until matched.

### 19. What reports can you discuss?
Explain dashboard and report data as derived from transactional records; demonstrate the actual six reports rather than inventing names or metrics.

### 20. What is the current testing limitation?
The 56 passing tests cover earlier operational workflows and authorization, not comprehensive automated invoice, payment or report coverage.

## Resume bullet
Developed a Laravel 13 / Vue 3 / MySQL P2P ERP with purchase approvals, QC, inventory posting, three-way invoice matching, partial payments, RBAC and audit trails; verified 56 automated regression tests.

## Live demo sequence
Login as buyer, show approved PO, switch to warehouse and QC, show posted stock, show matched and approved invoice, show payment settlement and dashboard. Explain one blocked operation. Use existing records or a separate demo dataset.
