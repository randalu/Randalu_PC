<?php

namespace Tests\Feature;

use App\Models\Customer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;

class CustomerAuthTest extends FeatureTestCase
{
    public function test_customer_can_register_with_sms_otp(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        $this->post(route('customer.login.request-otp'), ['phone' => '0771234567'])
            ->assertRedirect()
            ->assertSessionHas('otp_phone', '+94771234567');

        $sentOtp = null;
        Http::assertSent(function (Request $request) use (&$sentOtp): bool {
            preg_match('/\b(\d{6})\b/', (string) $request['message'], $matches);
            $sentOtp = $matches[1] ?? null;

            return $request->url() === 'https://smslenz.lk/api/send-sms' && $sentOtp !== null;
        });

        $this->post(route('customer.login.verify'), [
            'phone' => '0771234567',
            'otp' => $sentOtp,
        ])->assertRedirect(route('customer.account'));

        $this->assertDatabaseHas('customers', ['phone' => '+94771234567']);
        $this->assertNotNull(session('customer_id'));
        $this->assertDatabaseHas('event_logs', ['type' => 'customer.registered', 'customer_phone' => '+94771234567']);
    }

    public function test_existing_customer_can_login_and_view_their_orders(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        $order = $this->placeOrder('Existing Customer', '0771234567');
        Customer::query()->firstOrCreate(['phone' => '+94771234567'], ['name' => 'Existing Customer']);

        $this->post(route('customer.login.request-otp'), ['phone' => '0771234567'])->assertRedirect();

        $sentOtp = null;
        Http::assertSent(function (Request $request) use (&$sentOtp): bool {
            preg_match('/\b(\d{6})\b/', (string) $request['message'], $matches);
            $sentOtp = $matches[1] ?? null;

            return $sentOtp !== null;
        });

        $this->post(route('customer.login.verify'), [
            'phone' => '0771234567',
            'otp' => $sentOtp,
        ])->assertRedirect(route('customer.account'));

        $this->assertDatabaseHas('event_logs', ['type' => 'customer.logged_in', 'customer_phone' => '+94771234567']);

        $this->get(route('customer.account'))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Existing Customer');
    }

    public function test_account_lists_orders_by_customer_id_and_normalized_phone(): void
    {
        $this->seed();

        $customer = Customer::query()->create(['phone' => '+94771234567', 'name' => 'History Customer']);

        // Same number in a legacy display format, no customer link (guest order).
        $phoneMatched = $this->placeOrder('History Customer', '94 77 123 4567');

        // Linked to the account but ordered under a different phone.
        $linked = $this->placeOrder('Relative Buyer', '0769998888');
        $linked->update(['customer_id' => $customer->id]);

        // Neither linked nor phone-matched: must not appear.
        $hidden = $this->placeOrder('Other Customer', '0773334444');

        $this->withSession(['customer_id' => $customer->id])
            ->get(route('customer.account'))
            ->assertOk()
            ->assertSee($phoneMatched->order_number)
            ->assertSee($linked->order_number)
            ->assertDontSee($hidden->order_number);
    }

    public function test_customer_can_complete_profile_after_registration(): void
    {
        $this->seed();
        $customer = Customer::query()->create(['phone' => '+94771234567']);

        $this->withSession(['customer_id' => $customer->id])
            ->post(route('customer.profile'), [
                'name' => 'Kasun Perera',
                'email' => 'kasun@example.com',
                'delivery_address' => 'Negombo',
            ])->assertRedirect(route('customer.account'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Kasun Perera',
            'delivery_address' => 'Negombo',
        ]);
    }

    public function test_customer_login_otp_verification_is_rate_limited(): void
    {
        $this->seed();
        $this->enableSms();
        Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully'])]);

        RateLimiter::clear('customer-otp-verify-phone:+94771234567');
        RateLimiter::clear('customer-otp-verify-ip:127.0.0.1');

        Customer::query()->create(['phone' => '+94771234567', 'name' => 'Verify Limited Customer']);

        $this->post(route('customer.login.request-otp'), ['phone' => '0771234567'])->assertRedirect();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('customer.login.verify'), ['phone' => '0771234567', 'otp' => '000000'])
                ->assertSessionHasErrors('otp');
        }

        $this->post(route('customer.login.verify'), ['phone' => '0771234567', 'otp' => '000000'])
            ->assertSessionHasErrors('otp');

        $this->assertDatabaseHas('event_logs', [
            'type' => 'customer.otp_verify_rate_limited',
            'severity' => 'warning',
            'customer_phone' => '+94771234567',
        ]);
    }
}
