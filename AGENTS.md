# Prativa Stock Auditing & Procurement

Internal system for Prativa Secondary School. Laravel 13, PHP 8.4, MariaDB, Livewire 4, Tailwind 4.
Read [README.md](README.md) first — it explains what the system is for and how the controls work.

On Windows, PHP is at `C:\xampp\php\php.exe` and MariaDB at `C:\xampp\mysql\bin\mysql.exe`.

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
- Roles live in the `user_roles` pivot; use `$user->hasRole()` / `hasAnyRole()`, and gates defined
  in `AppServiceProvider`. Route protection is the `role:` middleware.
- Blade components in `resources/views/components` (`x-card`, `x-field`, `x-button`, `x-money`, …).
  Use them rather than repeating Tailwind strings.
- `@tailwindcss/forms` is installed — the `rounded-lg border-slate-300 focus:ring-indigo-500`
  idiom on inputs depends on it.

## Testing

`php artisan test` — 103 tests, running against the `prativa_inventory_test` **MariaDB** database,
never SQLite: the triggers and CHECK constraints are the things under test. Several tests bypass
the service layer and assert a raw query is still refused. Keep it that way.

## Before finishing a change

```bash
php artisan test
php vendor/bin/pint
npm run build          # if any Blade or CSS changed
```
