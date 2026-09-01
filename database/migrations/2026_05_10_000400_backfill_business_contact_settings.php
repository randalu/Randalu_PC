<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            'store_phone' => '+94776474542',
            'whatsapp_number' => '94776474542',
            'store_address' => 'Randalu PC, Sri Lanka',
            'google_maps_embed_url' => '',
        ];

        foreach ($settings as $key => $value) {
            DB::table('settings')->updateOrInsert(['key' => $key], [
                'value' => $value,
                'updated_at' => now(),
                'created_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->whereIn('key', ['store_address', 'google_maps_embed_url'])->delete();
    }
};
