# Multi-Tenant Inventory & Auditing System

Internal system covering a school's full stock lifecycle: physical inventory counting, demand
forms, a tiered approval ladder, order placement and receipt verification, bill cross-checking,
petty cash tokens, and management reporting.

**Several schools, one database.** Each school's records are separated by `tenant_id`, enforced by
composite foreign keys in the schema as well as by a global scope in the application — a school
cannot see, reference, or number over another one. A person may work at more than one school under
a single login and switches between them from the sidebar. A **platform owner** administers schools
at `/platform`; they read everything and can set schools up, but cannot approve, order, receive or
pay, because an account that could act anywhere would dissolve the separation of duties this system
exists to enforce.

**People are told when something waits for them.** A form reaching your approval band, a decision on
one you raised, a delivery verified against your order, a bill that does not match — each appears in
the in-app bell and by email. Nobody is ever notified about their own action.

**A new school chooses what it starts with.** The platform console offers a copy of the standard
catalogue — blocks, categories, 54 item codes — or an empty register for a school with its own
buildings and its own inventory. The approval ladder and petty cash ceiling are set up either way.
Opening stock is always left empty: those figures are whatever the school's own auditor counts.

**Internal only.** It should sit on the school network or behind a VPN and must never be exposed
publicly.

Laravel 13 · PHP 8.4 · MariaDB · Livewire 4 · Tailwind 4.

---

## Getting it running

You need PHP 8.4 with `pdo_mysql`, `bcmath`, `gd`, `zip` and `mbstring`, Composer, Node 20+, and
MariaDB 10.4+ (or MySQL 8). On this machine PHP and MariaDB both come from XAMPP.

```bash
# 1. Databases
mysql -u root -e "CREATE DATABASE prativa_inventory CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -e "CREATE DATABASE prativa_inventory_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Install
cp .env.example .env          # then set DB_USERNAME / DB_PASSWORD
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan integrity:verify  # confirms every database control is in place

# Locally this seeds TWO schools — Prativa and Everest — so that tenant
# isolation is visible on screen rather than merely asserted in a test.
# Sign in as admin@gmail.com / admin123 to see both, or as
# md@prativa.edu.np / Prativa@2026 to see one school exactly as its staff do.

# 3. Front end
npm install
npm run build                 # or: npm run dev

# 4. Run
php artisan serve
```

`php artisan integrity:verify` is not decoration. If it reports anything missing, run
`php artisan integrity:apply` before the system is used — the separation-of-duties rules live in
the database, and without them the controls are advisory only.

### First sign-in

Every seeded account starts on the password in `SEED_PASSWORD` (default `Prativa@2026`) and is
forced to change it immediately.

| Email | Person | Can do |
|---|---|---|
| `md@prativa.edu.np` | S. Sharma, Managing Director | Everything; approves tier 3 |
| `chairman@prativa.edu.np` | R. Gurung, Chairman | Approves tier 4, with a minute reference |
| `admin.officer@prativa.edu.np` | B. Thapa | Approves tier 2 |
| `hod.science@prativa.edu.np` | M. Adhikari | Approves tier 1 |
| `purchase@prativa.edu.np` | K. Poudel | Places orders |
| `store@prativa.edu.np` | S. Lama | Verifies receipts |
| `accounts@prativa.edu.np` | A. Shrestha | Bills, issues tokens |
| `accounts2@prativa.edu.np` | N. Rai | Releases token payments |
| `auditor@prativa.edu.np` | D. Bhattarai | Enters physical counts |
| `p.karki@prativa.edu.np` | P. Karki | Raises demand forms |

**Change the Managing Director's password before anyone else touches the system.**

---

## How the controls actually work

The point of this system is that no single person can move money or stock alone. Four mechanisms
enforce that, and three of them sit in the database where the application cannot override them.
They are defined in [`app/Support/IntegrityRules.php`](app/Support/IntegrityRules.php) and applied
by a migration.

### 1. Two people, never one

`goods_receipts` carries a CHECK constraint plus a trigger:

```sql
CHECK (received_by_id <> ordered_by_id)
```

The trigger re-reads the purchase order and confirms `ordered_by_id` genuinely matches it, so the
column cannot be faked. A Super Admin, a bug in a service, and somebody at a `mysql` prompt all hit
the same wall.

The service layer checks first and returns a readable message. The database is the thing that
guarantees it.

### 2. Nobody signs off their own request

A trigger on `demand_approvals` refuses any row where the actor raised the form. The approvals
queue filters them out too, so they never appear in the first place.

The same idea covers petty cash: whoever issued a token cannot be the one who releases the payment.

### 3. History cannot be rewritten

`audit_log`, `demand_approvals` and `stock_count_entries` each carry triggers that reject UPDATE
and DELETE outright.

Stock is a ledger, not a number. Every count writes a new row carrying the previous figure beside
the new one. Current stock is a view (`v_current_stock`) reading the newest row per item and block,
so the displayed figure and the history can never disagree. Correcting a miscount adds a row; it
does not erase one.

A bill variance works the same way. Accounts can accept a difference, but the original three
figures stay on record and a written reason of at least ten characters is attached with the
clearer's name. A CHECK constraint refuses a cleared variance without one.

### 4. The three-way match

Approved, ordered and billed are compared live in `v_three_way_match`. A bill is MATCHED only when
it equals the order **and** does not exceed the approval. Anything else is MISMATCH until Accounts
clears it in writing.

Bill numbers are unique across the whole system, and a bill already in the main register is refused
a petty cash token — that blocks the classic double-claim.

---

## The approval ladder

Configurable under Setup → Approval Ladder. Seeded as:

| Tier | Range | Decides |
|---|---|---|
| 1 | Rs. 100 – 15,000 | Head of Department |
| 2 | Rs. 15,001 – 50,000 | Administrative Officer |
| 3 | Rs. 50,001 – 200,000 | Managing Director |
| 4 | Above Rs. 200,000 | Chairman & Committee (minute reference required) |

A form always enters at tier 1 and climbs until it reaches the tier its value demands, so every
band below the deciding one signs it too. Editing the ladder is validated so bands cannot overlap
or leave a gap — a gap would mean a value nobody is authorised to sign.

---

## Item codes

Prefixes come from the school's `LIST_OF_INV.xlsx` and are unique across the school. Individual
units are the prefix plus a running number: `CHAIR.S.1`, `CHAIR.S.2`, `CHAIR.S.3`. Numbering runs
across blocks in block order, matching the original sheet. Any item's full unit list is on its
register page.

> **One correction to the source sheet.** LIST_OF_INV gives *Canteen Table (New)* and *Canteen
> Table (Old)* the same code, `C.T.N.1`. The seed sets the old one to `C.T.O`. Confirm that is what
> you want before going live.

The consumables in the seed are placeholders — the original sheet has none. Replace them with the
school's real list under Setup → Item Types.

---

## Project layout

```
app/
  Enums/           Role, Lifespan, CountSource, DemandStatus, MatchStatus, TokenStatus, …
  Models/          Eloquent models, one per table
  Services/        the business rules — demands, orders, bills, petty cash, inventory, settings
  Support/         IntegrityRules (the real controls), Money (bcmath), FiscalYear (BS), RefCounter
  Livewire/        every staff screen, grouped by module
  Exports/         five PhpSpreadsheet workbooks
  Http/Middleware/ EnsureRole, EnsureActive, ForcePasswordChange
database/
  migrations/      schema, then apply_integrity_rules — the constraints, triggers and views
  seeders/         blocks A–F, categories, 54 item codes, the ladder, staff, opening balances
```

Business rules live in `app/Services`. Livewire components are thin: validate, call a service,
render. Every service writes to `audit_log` inside its own transaction.

Money is `decimal(14,2)` throughout and all arithmetic goes through `App\Support\Money` (bcmath) —
never floats, so a paisa cannot go missing in a three-way match.

---

## Tests

```bash
php artisan test
```

103 tests. They run against **MariaDB**, not SQLite, because the CHECK constraints and triggers are
the things under test — several tests bypass the service layer entirely and assert that a raw
`INSERT`/`UPDATE` is still refused.

---

## Excel exports

Real `.xlsx` files with frozen headers, autofilters and number formatting — not CSV renamed.

| Export | Contents |
|---|---|
| Stock register | Two sheets, Durable and Consumable, item × block matrix |
| Unit list | One line per physical unit in Code + Sequential Number order |
| Procurement | Demand → approval → order → receipt → bill, one row each, plus spend by category |
| Petty cash | Every token, with both the issuing and releasing officer |
| Audit trail | The complete, unedited record |

Taking records out of the system is itself written to the audit trail.

---

## Deployment & CI/CD

Automated deployment is configured with **GitHub Actions** (`.github/workflows/deploy.yml`). Pushing to the `main` branch (or triggering manually) will automatically build frontend assets, bundle production dependencies, and securely deploy to your production server via FTP.

### Pipeline Overview
1. **🐘 PHP 8.4 Setup:** Configures PHP with all required extensions (`mbstring`, `xml`, `ctype`, `iconv`, `mysql`, `pdo_mysql`, `bcmath`, `fileinfo`, `zip`, `gd`).
2. **🟢 Node 22 & Caching:** Sets up Node.js and caches `npm` modules for high-speed builds.
3. **📦 Composer Dependency Build:** Caches and installs production dependencies (`--no-dev --optimize-autoloader`).
4. **⚡ Vite Build:** Installs npm dependencies and compiles frontend assets into `public/build`.
5. **📂 Secure FTP Sync:** Deploys files using `SamKirkland/FTP-Deploy-Action@v4.3.6`.

### Required GitHub Secrets
In your GitHub repository, navigate to **Settings > Secrets and variables > Actions** and add:

| Secret | Description | Example |
|---|---|---|
| `FTP_SERVER` | FTP hostname or IP | `ftp.yourdomain.com` |
| `FTP_USERNAME` | FTP account username | `deployer@yourdomain.com` |
| `FTP_PASSWORD` | FTP account password | `••••••••••••` |
| `SERVER_DIR` | Target server directory | `public_html/` or `/var/www/inventory/` |

### Security & Exclusions
To safeguard credentials and prevent bloated transfers, the workflow explicitly excludes:
- **Credentials & Secrets:** `.env*`, `storage/*.key` (production `.env` stays on server).
- **VCS & CI Configurations:** `.git*`, `.github/**`.
- **Testing & Tools:** `tests/**`, `phpunit.xml`, `.phpunit*`, `node_modules/**`, `package.json`, `package-lock.json`, `vite.config.js`.
- **Developer Documentation:** `README.md`, `CLAUDE.md`, `AGENTS.md`.
- **Runtime State:** `storage/logs/**`, `storage/framework/cache/**`, `storage/framework/sessions/**`, `storage/framework/views/**`.

### Production Server Post-Deployment Setup
Run these commands once on the production server (or after schema updates):
```bash
# Generate app key if setting up for the first time
php artisan key:generate

# Symlink public storage
php artisan storage:link

# Run database migrations
php artisan migrate --force

# Cache configuration, routes, and views for optimal performance
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Verify all separation-of-duties triggers and checks
php artisan integrity:verify
```

---

## Before this handles real money

1. **Backups.** `mysqldump` nightly, copied off the machine. An audit system with no backup is not
   an audit system.
2. **TLS.** Put nginx or Caddy in front. Never run this over plain HTTP, even internally.
3. **Real opening balances.** The stock figures in `OpeningBalanceSeeder` are sample data carried
   over from the reference system. Replace them with the auditor's actual count, or the variance
   report compares real purchases against invented stock.
4. **The consumables list.** Currently placeholders.
5. **Approval brackets.** Seeded at 15k / 50k / 200k; the school still has to confirm these.
6. **Password policy.** Ten characters is the current floor.
7. **Attachments.** Scanned bills sit on the local private disk behind signed URLs. Point
   `ATTACHMENT_DISK` at proper storage if the volume grows.
8. **Penetration test**, before it holds procurement records anyone might want to alter.
