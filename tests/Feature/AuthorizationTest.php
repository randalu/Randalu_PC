<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\ProductVariants\ProductVariantResource;
use App\Models\Customer;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class AuthorizationTest extends FeatureTestCase
{
    public function test_admin_dashboard_requires_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_admin_is_required_to_set_up_two_factor_authentication(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertRedirect('/admin/multi-factor-authentication/set-up');
    }

    public function test_staff_cannot_manage_orders_or_inventory(): void
    {
        $this->seed();
        $order = $this->placeOrder('Auth Check', '0771234567');
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);

        $this->actingAs($staff);

        $this->assertFalse(OrderResource::canViewAny());
        $this->assertFalse(OrderResource::canEdit($order));
        $this->assertFalse(ProductVariantResource::canCreate());
        $this->assertFalse(ProductVariantResource::canEdit(ProductVariant::query()->firstOrFail()));
    }

    public function test_admin_can_manage_orders_and_inventory(): void
    {
        $this->seed();
        $order = $this->placeOrder('Admin Check', '0772222222');
        $admin = User::query()->firstOrFail();

        $this->actingAs($admin);

        $this->assertTrue(OrderResource::canViewAny());
        $this->assertTrue(OrderResource::canEdit($order));
        $this->assertTrue(ProductVariantResource::canCreate());
        $this->assertTrue(ProductVariantResource::canEdit(ProductVariant::query()->firstOrFail()));
    }

    public function test_reseeding_does_not_overwrite_existing_admin_password(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();

        DB::table('users')->where('id', $admin->id)->update(['password' => 'existing-hash-value']);

        $this->seed();

        $this->assertSame('existing-hash-value', User::query()->find($admin->id)->password);
    }

    public function test_seeding_production_without_admin_password_fails(): void
    {
        $this->app['env'] = 'production';

        $this->expectException(RuntimeException::class);

        $this->artisan('db:seed', ['--force' => true]);
    }

    public function test_customer_resource_is_read_only_and_gated(): void
    {
        $this->seed();
        $admin = User::query()->firstOrFail();
        $staff = User::factory()->create(['role' => User::ROLE_STAFF]);
        $customer = Customer::query()->create(['phone' => '+94771234567', 'name' => 'Gate Customer']);

        $this->actingAs($admin);
        $this->assertTrue(CustomerResource::canViewAny());
        $this->assertFalse(CustomerResource::canCreate());
        $this->assertFalse(CustomerResource::canEdit($customer));
        $this->assertFalse(CustomerResource::canDelete($customer));

        $this->actingAs($staff);
        $this->assertFalse(CustomerResource::canViewAny());
    }
}
