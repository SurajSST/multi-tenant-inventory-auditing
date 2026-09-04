# Prativa Stock Auditing & Procurement

Internal stock and procurement system, **multi-tenant**: several schools in one database, keyed by
`tenant_id`. Laravel 13, PHP 8.4, MariaDB, Livewire 4, Tailwind 4.
Read [README.md](README.md) first — it explains what the system is for and how the controls work.

On Windows, PHP is at `C:\xampp\php\php.exe` and MariaDB at `C:\xampp\mysql\bin\mysql.exe`.

## Tenancy

Every domain table carries `tenant_id`, and isolation is enforced in two places:

1. **The schema** — composite foreign keys on `(tenant_id, parent_id)`, so a row physically cannot
   reference a parent in another school. Actor columns point at the global `users` table instead, so
   `trg_actor_posted_*` triggers assert the actor holds a live posting at that school.
2. **`App\Tenancy`** — `TenantContext` holds the active school; `BelongsToTenant` adds a global
   scope and stamps `tenant_id` on write. **A query with no school active throws** rather than
   returning every school's rows. Use `TenantContext::runFor($tenant, …)` in seeders, jobs and
   console code, and `runUnscoped()` only in the platform console.

**Identity vs posting.** `users` is who somebody is — global, one row per person, email is the login
and is globally unique. `tenant_users` is what they are at one school: staff code, designation,
approval tier, roles (`user_roles` hangs off the posting), auditor block scope. `$user->hasRole()`,
`->approval_tier`, `->designation` and `->staff_code` all resolve against the posting at the
**active** school, which is what lets the gates, middleware, services and Blade stay unchanged.
Eager-load `currentMembership` when a list prints other people's designations.

**Raw SQL and views do not get the global scope.** Every `DB::select`/`DB::table` in
`InventoryService`, `ReportService` and `BillService` filters `tenant_id` explicitly. `v_stock_register`
and `InventoryService::variance()` CROSS JOIN items with blocks — both carry a tenant predicate, and
without it they would cross one school's items with another's.

**The platform owner** (`users.is_platform_owner`) administers schools and reads across them at
`/platform`, behind the `platform` middleware. They deliberately cannot approve, order, receive or
issue tokens — an account that could act anywhere would dissolve the separation of duties. They hold
a normal posting at each school for anything they do inside one.

## Livewire actions are a public API

A `public function` on a component can be called with any arguments by anyone who can load that
component. The route's `role:` middleware gates the *screen*, and Livewire's checksum covers the
component's *own state* — neither covers the arguments of a method call.

So any component method taking an id from the browser must re-establish scope itself. `tenant_users`
is the trap, because it deliberately carries no global scope: `Setup\Staff` looks every posting up
through its own `posting()` helper, which filters on the active tenant. Without it a school's Super
Admin could pass another school's membership id and reset that person's password — a global login,
so it would lock them out everywhere. `TenantIsolationTest::test_a_super_admin_cannot_touch_another
_schools_staff` holds that line.

Models using `BelongsToTenant` are already safe here; `Tenant` and `TenantUser` are not.

## Notifications

`app/Notifications` + [`app/Services/Notifier.php`](app/Services/Notifier.php), which owns all the
"who gets told" logic so the services stay readable. Two channels: an in-app bell
(`TenantDatabaseChannel`, which stamps `tenant_id` so somebody working at two schools sees only
the active one) and mail.

**They are sent inline, and must stay that way.** `QUEUE_CONNECTION=database`, so making a
notification `ShouldQueue` turns it into a row in `jobs` that nobody drains — no school server runs
`queue:work`. It would pass every test, because `phpunit.xml` forces the queue to `sync`.
`NotificationTest::test_notifications_arrive_without_a_queue_worker_running` uses the real driver
and exists to catch exactly that.

Two rules in `Notifier`: nobody is told about their own action, and a failed send is logged, never
thrown — the approval already committed. Dispatch happens *after* the transaction closes, so a
rolled-back decision never notifies anybody.

## The rule that shapes everything

No single person can move money or stock alone. Separation of duties is enforced in three places,
and the order matters:

1. **The database** — CHECK constraints and triggers in
   [`app/Support/IntegrityRules.php`](app/Support/IntegrityRules.php), applied by the
   `apply_integrity_rules` migration. This is the guarantee.
2. **The services** in `app/Services` — they check first so the user gets a readable message.
3. **The screens** — they hide what a person cannot do, so they never see it.

Never weaken layer 1 to make a feature work. If a change needs the database rules altered, alter
them deliberately and update `IntegrityRules::status()` so `php artisan integrity:verify` still
covers them.

Three tables are append-only and the database refuses UPDATE and DELETE on them: `audit_log`,
`demand_approvals`, `stock_count_entries`. Corrections are new rows, never edits.

## Where things live

- **Business rules**: `app/Services`. Livewire components stay thin — validate, call a service,
  render. Every service writes to `audit_log` inside its own transaction.
- **Money**: `decimal(14,2)` columns, `decimal:2` casts, and all arithmetic through
  `App\Support\Money` (bcmath). Never float arithmetic on money.
- **Current stock**: read the `v_current_stock` view, never a stored total.
- **References** (DF-/PO-/PC-): `App\Support\RefCounter::next()`. It is atomic; do not reimplement.
- **Fiscal year**: `App\Support\FiscalYear` (Bikram Sambat, rolls 16 July).

## Conventions

- Keys are ordered UUIDs (`HasUuids`). Most tables have `created_at` only, so models set
  `public $timestamps = false`.
- Enums are backed string enums in `app/Enums` whose values match the DB literals exactly — CHECK
  constraints compare against them.
- Roles live in `user_roles`, keyed on the **posting** (`tenant_user_id`); use `$user->hasRole()` /
  `hasAnyRole()`, and gates defined in `AppServiceProvider`. Route protection is the `role:`
  middleware; the console uses `platform`.
- Blade components in `resources/views/components` (`x-card`, `x-field`, `x-button`, `x-money`, …).
  Use them rather than repeating Tailwind strings.
- `@tailwindcss/forms` is installed — the `rounded-lg border-slate-300 focus:ring-indigo-500`
  idiom on inputs depends on it.

## Testing

`php artisan test` — 132 tests, running against the `prativa_inventory_test` **MariaDB** database,
never SQLite: the triggers and CHECK constraints are the things under test. Several tests bypass
the service layer and assert a raw query is still refused. Keep it that way.

`TestCase` puts every test inside the seeded Prativa school. `TenantIsolationTest` is where the
second school comes out — it checks both that the application scopes correctly and that the database
refuses a cross-school row when the service layer is bypassed entirely.

## Before finishing a change

```bash
php artisan test
php vendor/bin/pint
npm run build          # if any Blade or CSS changed
```
