<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\ErpAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GoodsReceiptWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $manager;
    private User $warehouseOfficer;

    private Supplier $supplier;
    private Item $item;
    private Warehouse $warehouse;

    private PurchaseOrder $po;
    private int $poLineId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ErpAccessSeeder::class);

        DB::table('document_sequences')->insert([
            [
                'document_type' => 'PO',
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'document_type' => 'GRN',
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->buyer = $this->makeUser('buyer');
        $this->manager = $this->makeUser('purchase-manager');
        $this->warehouseOfficer = $this->makeUser('warehouse-officer');

        $this->supplier = Supplier::create([
            'code' => 'SUP001',
            'name' => 'ABC Steel Suppliers',
            'is_active' => true,
        ]);

        $this->item = Item::create([
            'code' => 'ITM001',
            'name' => 'Steel Plate',
            'uom' => 'PCS',
            'requires_qc' => true,
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'code' => 'WH001',
            'name' => 'Main Warehouse',
            'location' => 'Hyderabad',
            'is_active' => true,
        ]);

        // Use the existing PO workflow instead of bypassing it.
        $create = $this->actingAs($this->buyer)
            ->postJson('/erp/po', [
                'supplier_id' => $this->supplier->id,
                'remarks' => 'Steel procurement',
                'items' => [
                    [
                        'item_id' => $this->item->id,
                        'quantity' => 1000,
                        'unit_price' => 250,
                    ],
                ],
            ]);

        $create->assertCreated();

        $this->po = PurchaseOrder::findOrFail(
            $create->json('data.id')
        );

        $this->poLineId = $this->po->items()
            ->firstOrFail()->id;
    }

    private function makeUser(string $role): User
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $user->roles()->attach(
            Role::where('slug', $role)->firstOrFail()->id
        );

        return $user;
    }

    private function approvePO(): void
    {
        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$this->po->id}/submit")
            ->assertOk();

        $this->actingAs($this->manager)
            ->postJson("/erp/po/{$this->po->id}/approve")
            ->assertOk();
    }

    private function payload(
        int $quantity = 600,
        string $deliveryReference = 'DC-001'
    ): array {
        return [
            'purchase_order_id' => $this->po->id,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'delivery_reference' => $deliveryReference,
            'remarks' => 'Materials physically received',
            'items' => [
                [
                    'purchase_order_item_id' => $this->poLineId,
                    'received_quantity' => $quantity,
                ],
            ],
        ];
    }

    public function test_warehouse_officer_can_receive_approved_po(): void
    {
        $this->approvePO();

        $response = $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'received');

        $grn = GoodsReceipt::findOrFail(
            $response->json('data.id')
        );

        $this->assertStringStartsWith(
            'GRN-',
            $grn->grn_number
        );

        $this->assertDatabaseHas('goods_receipt_items', [
            'goods_receipt_id' => $grn->id,
            'purchase_order_item_id' => $this->poLineId,
            'received_quantity' => 600,
            'qc_status' => 'pending_qc',
        ]);
    }

    public function test_draft_po_cannot_be_received(): void
    {
        $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload())
            ->assertUnprocessable();

        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_partial_receipts_are_allowed(): void
    {
        $this->approvePO();

        $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload(600, 'DC-001'))
            ->assertCreated();

        $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload(250, 'DC-002'))
            ->assertCreated();

        $received = DB::table('goods_receipt_items')
            ->where('purchase_order_item_id', $this->poLineId)
            ->sum('received_quantity');

        $this->assertEquals(850, $received);
        $this->assertDatabaseCount('goods_receipts', 2);
    }

    public function test_over_receiving_is_rejected(): void
    {
        $this->approvePO();

        $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload(600, 'DC-001'))
            ->assertCreated();

        $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload(500, 'DC-002'))
            ->assertUnprocessable();

        $this->assertDatabaseCount('goods_receipts', 1);
        $this->assertDatabaseCount('goods_receipt_items', 1);
    }

    public function test_exact_outstanding_quantity_can_be_received(): void
    {
        $this->approvePO();

        $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload(600, 'DC-001'))
            ->assertCreated();

        $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload(400, 'DC-002'))
            ->assertCreated();

        $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload(1, 'DC-003'))
            ->assertUnprocessable();

        $this->assertDatabaseCount('goods_receipts', 2);
    }

    public function test_duplicate_delivery_reference_is_rejected(): void
    {
        $this->approvePO();

        $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload(300, 'DC-001'))
            ->assertCreated();

        $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload(200, 'DC-001'))
            ->assertUnprocessable();

        $this->assertDatabaseCount('goods_receipts', 1);
    }

    public function test_buyer_cannot_create_grn(): void
    {
        $this->approvePO();

        $this->actingAs($this->buyer)
            ->postJson('/erp/grn', $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('goods_receipts', 0);
    }

   public function test_guest_cannot_create_grn(): void
{
    // setUp() authenticated the buyer while creating the test PO.
    // Reset the authentication state before testing guest access.
    auth()->guard('web')->logout();

    $this->app['auth']->forgetGuards();

    $this->assertGuest('web');

    $this->postJson('/erp/grn', $this->payload())
        ->assertUnauthorized();

    $this->assertDatabaseCount('goods_receipts', 0);
}

    public function test_inactive_warehouse_is_rejected(): void
    {
        $this->approvePO();

        $this->warehouse->update(['is_active' => false]);

        $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload())
            ->assertUnprocessable();

        $this->assertDatabaseCount('goods_receipts', 0);
    }

    public function test_invalid_quantity_does_not_create_grn(): void
    {
        $this->approvePO();

        $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload(-10))
            ->assertUnprocessable();

        $this->assertDatabaseCount('goods_receipts', 0);

        $this->assertEquals(
            0,
            DB::table('document_sequences')
                ->where('document_type', 'GRN')
                ->value('last_number')
        );
    }

    public function test_non_qc_item_is_marked_correctly(): void
    {
        $this->approvePO();

        $this->item->update(['requires_qc' => false]);

        $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', $this->payload())
            ->assertCreated();

        $this->assertDatabaseHas('goods_receipt_items', [
            'purchase_order_item_id' => $this->poLineId,
            'qc_status' => 'qc_not_required',
        ]);
    }
}
