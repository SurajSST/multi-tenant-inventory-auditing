<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The platform owner exists above every school, so it is created before
        // any of them and belongs to none by default.
        $owner = $this->platformOwner();

        $prativa = Tenant::firstOrCreate(
            ['slug' => 'prativa'],
            [
                'name' => 'Prativa Secondary School',
                'short_name' => 'Prativa',
                'logo_url' => '/img/logo/prativaLogoWhite.png',
                'is_active' => true,
            ],
        );

        // Demo data is for LOCAL ONLY, never a real install. The records it
        // fabricates — demand forms, orders, a receipt, a bill, petty cash
        // tokens — land in append-only tables, so on a live database they could
        // never afterwards be removed. Gating on "not running tests" would have
        // been true in production.
        $this->seedSchool($prativa, withDemoData: app()->environment('local'));

        // A second school, locally only. Isolation that cannot be seen is
        // isolation nobody checks — with two schools on screen, a leak between
        // them is obvious the first time somebody looks.
        if (app()->environment('local')) {
            $everest = Tenant::firstOrCreate(
                ['slug' => 'everest'],
                [
                    'name' => 'Everest English Academy',
                    'short_name' => 'Everest',
                    'logo_url' => null,
                    'is_active' => true,
                ],
            );

            $this->seedSchool($everest, withDemoData: false);
        }

        // The owner works at every school as well as above them, so their
        // actions inside one are attributed to a posting there like anybody
        // else's — the separation-of-duties rules still apply to them.
        foreach (Tenant::all() as $tenant) {
            app(TenantContext::class)->runFor($tenant, function () use ($owner, $tenant) {
                (new UserSeeder)->attachOwner($owner, $tenant);
            });
        }

        $this->announce();
    }

    private function platformOwner(): User
    {
        $owner = User::firstOrNew(['email' => 'admin@gmail.com']);

        $owner->fill([
            'full_name' => 'System Administrator',
            'password' => Hash::make('admin123'),
            'is_active' => true,
            'must_reset_password' => false,
        ]);

        // Not fillable, deliberately: this is the one flag that crosses every
        // school boundary, so it is only ever set on purpose.
        $owner->is_platform_owner = true;
        $owner->save();

        return $owner;
    }

    private function seedSchool(Tenant $tenant, bool $withDemoData): void
    {
        app(TenantContext::class)->runFor($tenant, function () use ($withDemoData) {
            $this->call([
                LocationSeeder::class,
                CategorySeeder::class,
                ItemTypeSeeder::class,
                ApprovalTierSeeder::class,
                SettingSeeder::class,
                UserSeeder::class,
                OpeningBalanceSeeder::class,
            ]);

            if ($withDemoData) {
                $this->call(TestingDataSeeder::class);
            }
        });
    }

    private function announce(): void
    {
        $this->command?->newLine();
        $this->command?->info('Platform owner: admin@gmail.com / admin123 — every school, plus the console at /platform');
        $this->command?->info('Staff accounts: md@prativa.edu.np / '.config('prativa.seed_password'));
        $this->command?->line('Each staff account is forced to change its password on first sign-in.');

        if (app()->environment('local')) {
            $this->command?->line('Two schools are seeded locally so tenant isolation is visible on screen.');
        }
    }
}
