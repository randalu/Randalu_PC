<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->settings() as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', array_keys($this->settings()))->delete();
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
            'sms_otp_template' => 'Your PMS order status OTP is {otp}. It expires in 10 minutes.',
            'sms_order_update_template' => 'Your order {order_number} is now {status}. Track it at {tracking_url}',
        ];
    }
};
