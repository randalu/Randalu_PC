<?php

namespace Tests\Feature;

use App\Models\EventLog;

class MaintenanceTest extends FeatureTestCase
{
    public function test_prune_event_logs_deletes_only_old_logs(): void
    {
        $this->seed();

        $old = EventLog::query()->create([
            'type' => 'test.old',
            'severity' => 'info',
            'summary' => 'Old log',
        ]);

        EventLog::query()->whereKey($old->id)->update(['created_at' => now()->subDays(60)]);

        EventLog::query()->create([
            'type' => 'test.recent',
            'severity' => 'info',
            'summary' => 'Recent log',
        ]);

        $this->artisan('app:prune-event-logs')->assertSuccessful();

        $this->assertDatabaseMissing('event_logs', ['type' => 'test.old']);
        $this->assertDatabaseHas('event_logs', ['type' => 'test.recent']);
    }

    public function test_prune_event_logs_rejects_invalid_retention_window(): void
    {
        $this->seed();

        $this->artisan('app:prune-event-logs', ['--days' => 0])
            ->assertFailed();
    }
}
