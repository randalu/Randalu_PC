<?php

use App\Support\SriLankanPhone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('customer_phone_normalized', 20)->nullable()->after('customer_phone')->index();
        });

        // Backfill existing orders so historical phone lookups become exact
        // SQL matches instead of loading rows into PHP for same() comparison.
        DB::table('orders')->select(['id', 'customer_phone'])->orderBy('id')->chunk(100, function ($orders): void {
            foreach ($orders as $order) {
                DB::table('orders')
                    ->where('id', $order->id)
                    ->update(['customer_phone_normalized' => SriLankanPhone::normalize($order->customer_phone)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex(['customer_phone_normalized']);
            $table->dropColumn('customer_phone_normalized');
        });
    }
};
