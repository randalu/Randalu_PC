<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->seedAdmin();

        foreach ($this->settings() as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->call(ProductCatalogSeeder::class);
    }

    /**
     * Create the super-admin on first seed only. Re-seeding must never
     * overwrite a live admin's password with the published default.
     */
    private function seedAdmin(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@randalu-pc.lk');
        $name = env('ADMIN_NAME', 'Randalu PC Admin');

        $admin = User::query()->where('email', $email)->first();

        if ($admin) {
            // Sync non-sensitive fields only — never touch the password.
            $admin->update([
                'name' => $name,
                'role' => User::ROLE_SUPER_ADMIN,
            ]);

            $this->command?->warn("Admin {$email} already exists; password left unchanged.");

            return;
        }

        $password = env('ADMIN_PASSWORD');

        if (app()->environment('production') && (blank($password) || $password === 'ChangeMeNow!2026')) {
            throw new RuntimeException(
                'Refusing to seed the default admin password in production. Set a strong ADMIN_PASSWORD in .env.'
            );
        }

        User::query()->create([
            'email' => $email,
            'name' => $name,
            'role' => User::ROLE_SUPER_ADMIN,
            'password' => Hash::make($password ?: 'ChangeMeNow!2026'),
        ]);
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
            'delivery_fee' => '0',
            'delivery_fee_note' => 'Delivery fee is confirmed by our team before dispatch.',
        ];
    }
}
