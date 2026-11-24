# Purchase-to-Payment ERP

A full-stack ERP portfolio application implementing procurement, receiving, quality control, inventory, invoice matching and supplier payments.

**Stack:** Laravel 13 · PHP 8.5 · Vue 3 · Vite · MySQL · PHPUnit

## Features
- Session authentication and role-based permissions.
- Purchase orders: draft, edit, submit, approve, reject and cancel.
- Goods receipts with partial receiving and over-receipt prevention.
- QC inspection and independent approval; accepted/rejected stock posting.
- Supplier invoice three-way matching against PO price and accepted posted quantity, including prior invoice reservations.
- Supplier payments with partial settlement, duplicate reference and overpayment controls.
- Management dashboard, six reports and workflow audit records.

## Architecture
![Application architecture](docs/diagrams/architecture.svg)

## Business workflow
![Purchase-to-payment workflow](docs/diagrams/workflow.svg)

## Local setup (Windows / XAMPP)
Requires compatible PHP, Composer, Node.js/npm and MySQL. Start MySQL in XAMPP. From the project root:

```powershell
composer install
npm install
Copy-Item .env.example .env
php artisan key:generate
```
Create an empty MySQL database `erp_p2p`, configure `.env` with local DB host, port, database, username and password, then run:

```powershell
php artisan migrate
npm run build
php artisan serve
```
Open http://127.0.0.1:8000/erp. On a new installation, review and correct the access seeder before running it; create demo users, role assignments, document sequences and master data using the project's verified provisioning process. Do not reseed an existing database blindly. Never commit `.env` or database dumps containing credentials.

## Demo accounts
| Role | Email |
|---|---|
| Administrator | `admin@erp.test` |
| Buyer | `buyer@erp.test` |
| Purchase Manager | `manager@erp.test` |
| Warehouse Officer | `warehouse@erp.test` |
| QC Inspector | `qcinspector@erp.test` |
| QC Manager | `qcmanager@erp.test` |
| Accounts Officer | `accounts@erp.test` |
| Finance Manager | `finance@erp.test` |

Obtain the local demo password separately from the project owner. Do not publish the shared password or expose demo accounts on a live server.

## Testing
Latest observed regression run: **56 passed, 359 assertions, 12.02 seconds**. Coverage includes authorization, PO, GRN, QC, inventory and SPA smoke testing. Dedicated MySQL database: `erp_p2p_testing`. Verify `.env.testing`, phpunit.xml and the test database guard before running `php artisan test`. Additional invoice/payment/report automated suites are not implemented in this baseline.

## Screenshots
Historical captures from the actual application; refresh before final publication.

### Quality Control
![quality-control](docs/screenshots/quality-control.png)

### Inventory
![inventory](docs/screenshots/inventory.png)

### Invoice Audit
![invoice-audit](docs/screenshots/invoice-audit.png)

### Payments
![payments](docs/screenshots/payments.png)

### Management Dashboard
![management-dashboard](docs/screenshots/management-dashboard.png)

## Documentation
- [Technical and functional documentation](docs/PROJECT_DOCUMENTATION.md)
- [Conceptual entity relationships](docs/diagrams/entities.svg)
- [Interview preparation](docs/INTERVIEW_PREPARATION.md)
- [Screenshot checklist](docs/SCREENSHOT_GUIDE.md)

## Status
Core functional modules implemented. Final hardening remains open: access seeder correction, removal of development-only routes, confirmation of PO audit/security patches. Not represented as production-certified software.
