<?php

namespace App\Console\Commands;

use App\Support\IntegrityRules;
use Illuminate\Console\Command;

class VerifyIntegrityRules extends Command
{
    protected $signature = 'integrity:verify';

    protected $description = 'Confirm every database-level control is present. Exits non-zero if anything is missing.';

    public function handle(): int
    {
        $status = IntegrityRules::status();
        $missing = [];

        foreach ($status as $kind => $items) {
            $this->newLine();
            $this->line('<options=bold>'.ucfirst($kind).'</>');

            foreach ($items as $name => $present) {
                $this->line($present
                    ? "  <fg=green>✓</> {$name}"
                    : "  <fg=red>✗</> {$name}");

                if (! $present) {
                    $missing[] = "{$kind}: {$name}";
                }
            }
        }

        $this->newLine();

        if ($missing) {
            $this->error(count($missing).' control(s) missing. Run: php artisan integrity:apply');

            return self::FAILURE;
        }

        $this->info('All database controls are in place.');

        return self::SUCCESS;
    }
}
