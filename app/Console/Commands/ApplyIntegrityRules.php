<?php

namespace App\Console\Commands;

use App\Support\IntegrityRules;
use Illuminate\Console\Command;

class ApplyIntegrityRules extends Command
{
    protected $signature = 'integrity:apply';

    protected $description = 'Rebuild the database CHECK constraints, triggers and views that enforce separation of duties';

    public function handle(): int
    {
        $this->info('Rebuilding the integrity rules…');

        IntegrityRules::apply();

        $this->info('Done. Run integrity:verify to confirm.');

        return self::SUCCESS;
    }
}
