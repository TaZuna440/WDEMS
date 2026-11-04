<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Services\Participants\AutomaticIdentityResolution;
use Illuminate\Console\Command;

class ResolveHeldRegistrations extends Command
{
    protected $signature = 'registrations:resolve-held {--dry-run : Count pending legacy holds without changing records}';

    protected $description = 'Resolve legacy duplicate holds automatically while preserving original records';

    public function handle(AutomaticIdentityResolution $resolver): int
    {
        if ($this->option('dry-run')) {
            $this->info(Registration::held()->count().' legacy holds pending. No records changed.');

            return self::SUCCESS;
        }
        $count = 0;
        Registration::held()->select('id')->chunkById(100, function ($rows) use ($resolver, &$count): void {
            foreach ($rows as $row) {
                $count += (int) $resolver->resolveHeld($row->id);
            }
        });
        $this->info($count.' legacy holds resolved automatically.');

        return self::SUCCESS;
    }
}
