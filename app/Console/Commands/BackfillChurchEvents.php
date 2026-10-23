<?php

namespace App\Console\Commands;

use App\Services\Church\ChurchEventBackfillService;
use Illuminate\Console\Command;
use JsonException;

class BackfillChurchEvents extends Command
{
    protected $signature = 'church:backfill-events
        {--dry-run : Report planned work without writing database rows}
        {--module=all : Select all, services, fellowship, or discipleship}
        {--limit= : Maximum number of source records to process}
        {--after-id= : Process source records with IDs greater than this value}
        {--report= : Optional path for writing the JSON report}';

    protected $description = 'Backfill legacy church module records into standard church Events.';

    public function handle(ChurchEventBackfillService $backfill): int
    {
        $limitOption = $this->positiveOption('limit');
        $afterIdOption = $this->positiveOption('after-id', allowZero: true);
        if ($limitOption === false || $afterIdOption === false) {
            return self::INVALID;
        }
        $limit = $limitOption;
        $afterId = $afterIdOption;

        $module = (string) $this->option('module');
        if (! in_array($module, ['all', 'services', 'fellowship', 'discipleship'], true)) {
            $this->error('Option --module must be all, services, fellowship, or discipleship.');

            return self::INVALID;
        }

        $report = $backfill->run(
            $module,
            (bool) $this->option('dry-run'),
            $limit,
            $afterId,
        );

        try {
            $json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $this->error('Could not encode the backfill report: '.$exception->getMessage());

            return self::FAILURE;
        }

        $reportPath = $this->option('report');
        if (is_string($reportPath) && $reportPath !== '') {
            $written = file_put_contents($reportPath, $json.PHP_EOL);
            if ($written === false) {
                $this->error("Could not write report file [{$reportPath}].");

                return self::FAILURE;
            }
            $this->info("Report written to {$reportPath}");
        }

        $this->line($json);

        return $report['totals']['conflicts'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function positiveOption(string $name, bool $allowZero = false): int|false|null
    {
        $value = $this->option($name);
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value) || (string) (int) $value !== (string) $value) {
            $this->error("Option --{$name} must be an integer.");

            return false;
        }

        $integer = (int) $value;
        if ($integer < 0 || (! $allowZero && $integer === 0)) {
            $this->error("Option --{$name} must be ".($allowZero ? 'zero or greater.' : 'greater than zero.'));

            return false;
        }

        return $integer;
    }
}
