<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ErpAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseOrderWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $manager;
    private Supplier $supplier;
    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ErpAccessSeeder::class);

        DB::table('document_sequences')->insert([
            'document_type' => 'PO',
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->buyer = User::factory()->create([
            'is_active' => true,
        ]);

        $this->manager = User::factory()->create([
            'is_active' => true,
        ]);

        $this->buyer->roles()->attach(
            Role::where('slug', 'buyer')->firstOrFail()->id
        );

        $this->manager->roles()->attach(
            Role::where('slug', 'purchase-manager')
                ->firstOrFail()->id
        );

        $this->supplier = Supplier::create([
            'code' => 'SUP001',
            'name' => 'ABC Steel Suppliers',
            'is_active' => true,
        ]);

        $this->item = Item::create([
            'code' => 'ITM001',
            'name' => 'Steel Plate',
            'uom' => 'PCS',
            'is_active' => true,
        ]);
    }

    private function createPO(): array
    {
        $response = $this->actingAs($this->buyer)
            ->postJson('/erp/po', [
                'supplier_id' => $this->supplier->id,
                'items' => [
                    [
                        'item_id' => $this->item->id,
                        'quantity' => 1000,
                        'unit_price' => 250,
                    ],
                ],
            ]);

        $response->assertCreated();

        return $response->json('data');
    }

    public function test_buyer_can_create_draft_po(): void
    {
        $po = $this->createPO();

        $this->assertSame('draft', $po['status']);
        $this->assertEquals(250000, $po['total_amount']);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $po['id'],
            'item_id' => $this->item->id,
        ]);
    }

    public function test_buyer_can_submit_and_manager_can_approve(): void
    {
        $po = $this->createPO();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po['id']}/submit")
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'pending_approval'
            );

        $this->actingAs($this->manager)
            ->postJson("/erp/po/{$po['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('purchase_order_approvals', [
            'purchase_order_id' => $po['id'],
            'approver_id' => $this->manager->id,
            'action' => 'approved',
        ]);
    }

    public function test_buyer_cannot_approve_po(): void
    {
        $po = $this->createPO();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po['id']}/submit")
            ->assertOk();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po['id']}/approve")
            ->assertForbidden();
    }

    public function test_po_cannot_be_approved_twice(): void
    {
        $po = $this->createPO();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po['id']}/submit")
            ->assertOk();

        $this->actingAs($this->manager)
            ->postJson("/erp/po/{$po['id']}/approve")
            ->assertOk();

        $this->actingAs($this->manager)
            ->postJson("/erp/po/{$po['id']}/approve")
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'purchase_order_approvals',
            1
        );
    }

    public function test_rejection_requires_reason(): void
    {
        $po = $this->createPO();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po['id']}/submit")
            ->assertOk();

        $this->actingAs($this->manager)
            ->postJson("/erp/po/{$po['id']}/reject", [])
            ->assertUnprocessable();
    }

    public function test_negative_quantity_is_rejected(): void
    {
        $this->actingAs($this->buyer)
            ->postJson('/erp/po', [
                'supplier_id' => $this->supplier->id,
                'items' => [
                    [
                        'item_id' => $this->item->id,
                        'quantity' => -5,
                        'unit_price' => 250,
                    ],
                ],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('purchase_orders', 0);
    }
}