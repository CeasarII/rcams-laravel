# Cash Advance Monitoring System (CAMS)

Laravel 12 / PHP 8.2 / MySQL. This archive holds the application slice (app, routes,
resources, database, config, public) — copy it over a fresh Laravel 12 project
(`composer create-project laravel/laravel`) or into your existing `cams2` project.

## Setup

1. `.env`: `DB_CONNECTION=mysql`, `DB_DATABASE=cams2`, `SESSION_DRIVER=database`
2. `php artisan migrate:fresh --seed`
   (uploads need `upload_max_filesize` >= 5M and `post_max_size` >= 25M in php.ini; PHP defaults to 2M)
3. `php artisan serve` then open http://127.0.0.1:8000/login

`migrate:fresh` drops every table. `php artisan db:seed` alone is safe to re-run.

## Demo logins (password for all: `password`; created by DemoDataSeeder, skipped in production)

| Email | Role |
|---|---|
| admin@cams.test | ADMIN |
| requestor@cams.test | REQUESTOR |
| oe@cams.test | OE (scoped to MT Demo Alpha, MV Demo Bravo; Crewing + Operations depts) |
| staff@cams.test | ACCT_STAFF |
| head@cams.test | ACCT_HEAD |
| treasury@cams.test | TREASURY |
| multi@cams.test | REQUESTOR + OE |
| crewing@cams.test | CREWING |

## What's included

- Process A (Fund Request): migrations, models, FundRequestWorkflowService, policies, screens
- Fund request form: Department is the requestor's own; Vessel/Category come from vessels assigned on the
  user (Admin > Users). One vessel = auto-filled, several = pick-list, none = Shore Base only
- Cash Advance Liquidation (from a disbursed Cash Advance): expense breakdown with Philippine VAT
  (12%, VAT-inclusive receipts: net = gross / 1.12, input VAT = gross x 12/112; Non-VAT = no input tax),
  supporting documents per item, OE validate -> Staff verify -> APV (Staff) -> APV review (Head) ->
  reimbursement (Treasury), or Head closure when nothing is payable. The seeded demo cash advance
  CA-DEMO-00001 (requestor@cams.test) is ready to liquidate.
- Questionable Expense: an item tagged by the OE ("Liquidation okay?") or Accounting Staff ("Verification okay?")
  runs its own process: hard copy check -> Incident Report -> OE receipt -> Crewing salary deduction + proof -> close
- Revolving Fund: funds (Admin), voyages, a transaction ledger (funding / approved voyage expenses /
  replenishment), and Process B — voyage expense details: OE validate -> Staff validate -> Head approve ->
  AP voucher -> (Head sets HC pending when items are tagged) -> CV number -> disburse. A Revolving Fund
  request in Process A now names the fund it funds. Demo fund RF-ALPHA (custodian requestor@cams.test, 50,000, open voyage VOY-001).
- Master data also: Nature of Cost, Document Types
- Attachments: upload/download/remove on a fund request (private disk, Requestor only while Draft/Returned)
- Auth: login/logout only (no registration, no password reset)
- Admin: Users (/admin/users) and Master Data — Companies, Departments, Vessel Types, Vessels
  (no delete; untick Active to retire a record)
- All routes live in `routes/web.php`

## Not built yet

Dashboards (Phase 10), tests.
