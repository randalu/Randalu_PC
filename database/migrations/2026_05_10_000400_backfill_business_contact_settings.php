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
            'store_address' => 'Priyanthi Multi Stores, Katunayake, Sri Lanka',
            'google_maps_embed_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3958.770553911933!2d79.87817187499869!3d7.1525068928518865!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3ae2f11dde391c3b%3A0x18d0e58c6ffb9ba3!2sPriyanthi%20Multi%20Stores!5e0!3m2!1sen!2slk!4v1778339907048!5m2!1sen!2slk',
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
