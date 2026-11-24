<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\ErpAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ErpAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ErpAccessSeeder::class);
    }

    public function test_buyer_can_create_but_cannot_approve_po(): void
    {
        $buyer = User::factory()->create([
            'is_active' => true,
        ]);
        $buyer->roles()->attach(
            Role::where('slug', 'buyer')->firstOrFail()
        );

        $this->actingAs($buyer)
            ->getJson('/erp/test/create-po')
            ->assertOk();

        $this->actingAs($buyer)
            ->getJson('/erp/test/approve-po')
            ->assertForbidden();
        $this->assertTrue($buyer->hasRole('buyer'));
        $this->assertTrue($buyer->hasPermission('po.create'));
        $this->assertTrue((bool) $buyer->is_active);
    }

    public function test_purchase_manager_can_approve_po(): void
    {
        $manager = User::factory()->create([
            'is_active' => true,
        ]);
        $manager->roles()->attach(
            Role::where('slug', 'purchase-manager')->firstOrFail()
        );

        $this->actingAs($manager)
            ->getJson('/erp/test/approve-po')
            ->assertOk();
        $this->assertTrue($manager->hasRole('purchase-manager'));
        $this->assertTrue($manager->hasPermission('po.approve'));
        $this->assertTrue((bool) $manager->is_active);
    }

    public function test_guest_cannot_access_protected_route(): void
    {
        $this->getJson('/erp/test/approve-po')
            ->assertUnauthorized();
    }
}