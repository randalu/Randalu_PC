<?php

namespace Tests\Feature;

use App\Filament\Resources\Settings\SettingResource;
use App\Jobs\SendSms;
use App\Models\EventLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\SmsService;
use App\Services\SmsTestService;
use App\Support\SriLankanPhone;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class SmsTest extends FeatureTestCase
{
    public function test_sri_lankan_phone_numbers_are_normalized_for_sms(): void
    {
        $this->assertSame('+94771234567', SriLankanPhone::normalize('0771234567'));
        $this->assertSame('+94771234567', SriLankanPhone::normalize('94771234567'));
        $this->assertSame('+94771234567', SriLankanPhone::normalize('+94 77 123 4567'));
    }

    public function test_sms_settings_are_limited_to_super_admins(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($admin);
        $this->assertTrue(SettingResource::canViewAny());

        $this->actingAs($staff);
        $this->assertFalse(SettingResource::canViewAny());
    }

    public function test_settings_updates_are_logged_without_storing_secret_values(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();
        $this->actingAs($admin);

        Setting::query()->updateOrCreate(['key' => 'sms_sender_id'], ['value' => 'PeachTreeLK']);

        $event = EventLog::query()->where('type', 'setting.updated')->latest()->firstOrFail();

        $this->assertSame('Setting sms_sender_id updated', $event->summary);
        $this->assertSame($admin->id, $event->user_id);
        $this->assertSame('sms_sender_id', $event->metadata['key']);
        $this->assertArrayNotHasKey('value', $event->metadata);
    }

    public function test_admin_test_sms_is_sent_and_logged(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        $admin = User::query()->firstOrFail();

        $sent = app(SmsTestService::class)->send('0771234567', 'Randalu PC SMS test message.', $admin->id);

        $this->assertTrue($sent);
        Http::assertSent(fn (Request $request): bool => $request['contact'] === '+94771234567'
            && $request['message'] === 'Randalu PC SMS test message.');
        $this->assertDatabaseHas('event_logs', [
            'type' => 'sms.test_sent',
            'severity' => 'success',
            'user_id' => $admin->id,
            'customer_phone' => '0771234567',
        ]);
    }

    public function test_sms_send_treats_success_false_as_failure(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Insufficient credit'])]);

        $sent = app(SmsService::class)->send('0771234567', 'Hello');

        $this->assertFalse($sent);
        $this->assertDatabaseHas('event_logs', ['type' => 'sms.failed', 'severity' => 'error']);
    }

    public function test_sms_sent_event_records_campaign_and_pages(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response([
            'success' => true,
            'message' => 'SMS sent successfully',
            'data' => [
                'campaign_id' => 'CMP-12345',
                'pages' => 2,
                'sms_credit_balance' => 250,
            ],
        ])]);

        $sent = app(SmsService::class)->send('0771234567', 'Hello Randalu PC');

        $this->assertTrue($sent);

        $event = EventLog::query()->where('type', 'sms.sent')->latest()->firstOrFail();
        $this->assertSame('CMP-12345', $event->metadata['campaign_id']);
        $this->assertSame(2, $event->metadata['pages']);
        $this->assertSame(250, $event->metadata['balance']);
    }

    public function test_sms_job_sends_message(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        SendSms::dispatch('0771234567', 'Queued status update');

        Http::assertSent(fn (Request $request): bool => $request['contact'] === '+94771234567'
            && $request['message'] === 'Queued status update');

        $this->assertDatabaseHas('event_logs', [
            'type' => 'sms.sent',
            'customer_phone' => '+94771234567',
        ]);
    }
}
