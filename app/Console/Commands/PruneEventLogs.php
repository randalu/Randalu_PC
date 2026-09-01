<?php

namespace App\Console\Commands;

use App\Models\EventLog;
use App\Models\Setting;
use Illuminate\Console\Command;

class PruneEventLogs extends Command
{
    protected $signature = 'app:prune-event-logs {--days= : Override the retention window in days}';

    protected $description = 'Delete event logs older than the retention window';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = (int) ($daysOption !== null ? $daysOption : Setting::getValue('event_log_retention_days', '30'));

        if ($days < 1) {
            $this->error('The retention window must be at least 1 day.');

            return self::FAILURE;
        }

        $deleted = EventLog::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();

        $this->info("Pruned {$deleted} event log(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
