<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate([
            'email' => env('ADMIN_EMAIL', 'admin@randalu-pc.lk'),
        ], [
            'name' => env('ADMIN_NAME', 'Randalu PC Admin'),
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => Hash::make(env('ADMIN_PASSWORD', 'ChangeMeNow!2026')),
        ]);

        foreach ($this->settings() as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->call(ProductCatalogSeeder::class);
    }

    /**
     * @return array<string, string>
     */
    private function settings(): array
    {
        return [
            'sms_enabled' => '0',
            'sms_order_updates_enabled' => '1',
            'sms_sender_id' => 'SMSlenzDEMO',
            'sms_otp_template' => 'Your Randalu PC order status OTP is {otp}. It expires in 10 minutes.',
            'sms_login_otp_template' => 'Your Randalu PC login OTP is {otp}. It expires in 10 minutes.',
            'sms_order_update_template' => 'Your order {order_number} is now {status}. Track it at {tracking_url}',
        ];
    }
}
