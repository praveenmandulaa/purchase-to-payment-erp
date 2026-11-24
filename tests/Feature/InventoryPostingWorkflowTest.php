<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\InventoryBalance;
use App\Models\InventoryPosting;
use App\Models\InventoryPostingLine;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\QcInspection;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryPostingService;
use Database\Seeders\ErpAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InventoryPostingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $purchaseManager;
    private User $warehouseOfficer;
    private User $inspector;
    private User $qcManager;

    private GoodsReceipt $grn;
    private Item $item;
    private Warehouse $warehouse;

    private int $poId;
    private int $poLineId;
    private int $grnLineId;

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
        $this->purchaseManager = $this->makeUser(
            'purchase-manager'
        );
        $this->warehouseOfficer = $this->makeUser(
            'warehouse-officer'
        );
        $this->inspector = $this->makeUser(
            'qc-inspector'
        );
        $this->qcManager = $this->makeUser(
            'qc-manager'
        );

        $supplier = Supplier::create([
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

        // Create approved PO through the existing API.
        $poResponse = $this->actingAs($this->buyer)
            ->postJson('/erp/po', [
                'supplier_id' => $supplier->id,
                'remarks' => 'Inventory posting test',
                'items' => [
                    [
                        'item_id' => $this->item->id,
                        'quantity' => 1000,
                        'unit_price' => 250,
                    ],
                ],
            ])
            ->assertCreated();

        $this->poId = (int) $poResponse->json('data.id');

        $this->actingAs($this->buyer)
            ->postJson(
                "/erp/po/{$this->poId}/submit"
            )
            ->assertOk();

        $this->actingAs($this->purchaseManager)
            ->postJson(
                "/erp/po/{$this->poId}/approve"
            )
            ->assertOk();

        $this->poLineId = PurchaseOrder::findOrFail(
            $this->poId
        )->items()->firstOrFail()->id;

        // Receive 600 units, requiring QC.
        $grnResponse = $this->actingAs(
            $this->warehouseOfficer
        )->postJson('/erp/grn', [
            'purchase_order_id' => $this->poId,
            'warehouse_id' => $this->warehouse->id,
            'receipt_date' => now()->toDateString(),
            'delivery_reference' => 'INV-TEST-DC-001',
            'items' => [
                [
                    'purchase_order_item_id' =>
                        $this->poLineId,
                    'received_quantity' => 600,
                ],
            ],
        ])->assertCreated();

        $this->grn = GoodsReceipt::findOrFail(
            $grnResponse->json('data.id')
        );

        $this->grnLineId = $this->grn
            ->items()
            ->firstOrFail()
            ->id;

        auth()->guard('web')->logout();
        $this->app['auth']->forgetGuards();
    }

    private function makeUser(string $roleSlug): User
    {
        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $user->roles()->attach(
            Role::where('slug', $roleSlug)
                ->firstOrFail()
                ->id
        );

        return $user;
    }

    /**
     * Create and approve a QC inspection through
     * the existing, tested QC endpoints.
     */
    private function approveQc(
        int $accepted = 550,
        int $rejected = 50
    ): QcInspection {
        $response = $this->actingAs(
            $this->inspector
        )->postJson('/erp/qc/inspections', [
            'goods_receipt_id' => $this->grn->id,
            'inspection_date' => now()->toDateString(),
            'remarks' => 'Inventory test inspection',
            'items' => [
                [
                    'goods_receipt_item_id' =>
                        $this->grnLineId,
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => $rejected,
                    'rejection_reason' => $rejected > 0
                        ? 'Surface damage detected'
                        : null,
                ],
            ],
        ])->assertCreated();

        $inspectionId = $response->json('data.id');

        $this->actingAs($this->inspector)
            ->postJson(
                "/erp/qc/inspections/{$inspectionId}/submit"
            )
            ->assertOk();

        $this->actingAs($this->qcManager)
            ->postJson(
                "/erp/qc/inspections/{$inspectionId}/approve"
            )
            ->assertOk();

        return QcInspection::findOrFail($inspectionId);
    }

    /**
     * Call the posting service directly.
     * No inventory API endpoint is required yet.
     */
    private function postInventory(
        ?GoodsReceipt $grn = null,
        ?User $actor = null
    ): InventoryPosting {
        return app(InventoryPostingService::class)
            ->post(
                $grn ?? $this->grn,
                $actor ?? $this->warehouseOfficer,
                'Automated inventory posting test'
            );
    }

    /**
     * Create a second receipt from the same PO.
     * The first receipt used 600 of the ordered 1000,
     * so at most 400 remain available for receipt.
     *
     * The item is changed to QC-exempt before this
     * new receipt is created, so the GRN service
     * should mark the new line qc_not_required.
     */
    private function createNonQcReceipt(
        int $quantity = 250,
        ?Warehouse $warehouse = null
    ): GoodsReceipt {
        $this->item->update([
            'requires_qc' => false,
        ]);

        $warehouse ??= $this->warehouse;

        $response = $this->actingAs(
            $this->warehouseOfficer
        )->postJson('/erp/grn', [
            'purchase_order_id' => $this->poId,
            'warehouse_id' => $warehouse->id,
            'receipt_date' => now()->toDateString(),
            'delivery_reference' => 'INV-TEST-DC-002',
            'items' => [
                [
                    'purchase_order_item_id' =>
                        $this->poLineId,
                    'received_quantity' => $quantity,
                ],
            ],
        ])->assertCreated();

        return GoodsReceipt::findOrFail(
            $response->json('data.id')
        );
    }

    public function test_approved_qc_posts_accepted_and_rejected_stock(): void
    {
        $inspection = $this->approveQc(550, 50);

        $posting = $this->postInventory();

        $this->assertSame(
            'posted',
            $posting->status
        );

        $this->assertSame(
            $inspection->id,
            $posting->qc_inspection_id
        );

        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'available_quantity' => '550.000',
            'rejected_quantity' => '50.000',
        ]);

        $this->assertDatabaseHas('inventory_posting_lines', [
            'inventory_posting_id' => $posting->id,
            'goods_receipt_item_id' => $this->grnLineId,
            'accepted_quantity' => '550.000',
            'rejected_quantity' => '50.000',
        ]);

        $this->assertDatabaseCount(
            'inventory_postings',
            1
        );

        $this->assertDatabaseCount(
            'inventory_posting_lines',
            1
        );

        $this->assertDatabaseCount(
            'stock_movements',
            2
        );
    }

    public function test_fully_accepted_qc_creates_only_accepted_movement(): void
    {
        $this->approveQc(600, 0);

        $this->postInventory();

        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'available_quantity' => '600.000',
            'rejected_quantity' => '0.000',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'goods_receipt_item_id' => $this->grnLineId,
            'movement_type' => 'receipt_accepted',
            'quantity' => '600.000',
        ]);

        $this->assertDatabaseCount(
            'stock_movements',
            1
        );
    }

    public function test_pending_qc_cannot_post_inventory(): void
    {
        try {
            $this->postInventory();

            $this->fail(
                'Posting should fail without approved QC.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'goods_receipt_id',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'inventory_postings',
            0
        );

        $this->assertDatabaseCount(
            'inventory_balances',
            0
        );

        $this->assertDatabaseCount(
            'stock_movements',
            0
        );
    }

    public function test_same_grn_line_cannot_be_posted_twice(): void
    {
        $this->approveQc(550, 50);

        $firstPosting = $this->postInventory();

        $this->assertNotNull($firstPosting->id);

        try {
            $this->postInventory();

            $this->fail(
                'Duplicate posting should be rejected.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'goods_receipt_id',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'inventory_postings',
            1
        );

        $this->assertDatabaseCount(
            'inventory_posting_lines',
            1
        );

        $this->assertDatabaseCount(
            'stock_movements',
            2
        );

        $balance = InventoryBalance::firstOrFail();

        $this->assertSame(
            '550.000',
            $balance->available_quantity
        );

        $this->assertSame(
            '50.000',
            $balance->rejected_quantity
        );
    }

    public function test_non_qc_receipt_can_be_posted_without_inspection(): void
    {
        $secondGrn = $this->createNonQcReceipt(250);

        $line = $secondGrn
            ->items()
            ->firstOrFail();

        $this->assertSame(
            'qc_not_required',
            $line->qc_status
        );

        $posting = $this->postInventory($secondGrn);

        $this->assertNull(
            $posting->qc_inspection_id
        );

        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'available_quantity' => '250.000',
            'rejected_quantity' => '0.000',
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'goods_receipt_item_id' => $line->id,
            'movement_type' => 'receipt_accepted',
            'quantity' => '250.000',
        ]);
    }

    public function test_two_receipts_accumulate_in_same_warehouse(): void
    {
        $this->approveQc(550, 50);

        $this->postInventory();

        $secondGrn = $this->createNonQcReceipt(250);

        $this->postInventory($secondGrn);

        $balance = InventoryBalance::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item->id)
            ->firstOrFail();

        $this->assertSame(
            '800.000',
            $balance->available_quantity
        );

        $this->assertSame(
            '50.000',
            $balance->rejected_quantity
        );

        $this->assertDatabaseCount(
            'inventory_postings',
            2
        );

        $this->assertDatabaseCount(
            'inventory_posting_lines',
            2
        );

        $this->assertDatabaseCount(
            'stock_movements',
            3
        );
    }

    public function test_different_warehouses_have_separate_balances(): void
    {
        $this->approveQc(600, 0);

        $this->postInventory();

        $secondWarehouse = Warehouse::create([
            'code' => 'WH002',
            'name' => 'Secondary Warehouse',
            'location' => 'Hyderabad',
            'is_active' => true,
        ]);

        $secondGrn = $this->createNonQcReceipt(
            250,
            $secondWarehouse
        );

        $this->postInventory($secondGrn);

        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'available_quantity' => '600.000',
        ]);

        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $secondWarehouse->id,
            'item_id' => $this->item->id,
            'available_quantity' => '250.000',
        ]);

        $this->assertDatabaseCount(
            'inventory_balances',
            2
        );
    }

    public function test_buyer_cannot_post_inventory(): void
    {
        $this->approveQc(600, 0);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(0);

        $this->postInventory(
            $this->grn,
            $this->buyer
        );
    }

    public function test_movements_reconcile_with_posting_line(): void
    {
        $this->approveQc(550, 50);

        $posting = $this->postInventory();

        $line = InventoryPostingLine::query()
            ->where('inventory_posting_id', $posting->id)
            ->firstOrFail();

        $accepted = StockMovement::query()
            ->where('inventory_posting_id', $posting->id)
            ->where('movement_type', 'receipt_accepted')
            ->firstOrFail();

        $rejected = StockMovement::query()
            ->where('inventory_posting_id', $posting->id)
            ->where('movement_type', 'receipt_rejected')
            ->firstOrFail();

        $this->assertSame(
            $line->accepted_quantity,
            $accepted->quantity
        );

        $this->assertSame(
            $line->rejected_quantity,
            $rejected->quantity
        );

        $this->assertSame(
            '0.000',
            $accepted->balance_before
        );

        $this->assertSame(
            '550.000',
            $accepted->balance_after
        );

        $this->assertSame(
            '0.000',
            $rejected->balance_before
        );

        $this->assertSame(
            '50.000',
            $rejected->balance_after
        );
    }

    public function test_posting_failure_rolls_back_all_changes(): void
    {
        $this->approveQc(600, 0);

        // Seed a near-capacity balance to force overflow.
        InventoryBalance::create([
            'warehouse_id' => $this->warehouse->id,
            'item_id' => $this->item->id,
            'available_quantity' => '999999999999.000',
            'rejected_quantity' => '0.000',
        ]);

        try {
            $this->postInventory();

            $this->fail(
                'Posting should fail when balance exceeds capacity.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'quantity',
                $exception->errors()
            );
        }

        $this->assertDatabaseCount(
            'inventory_postings',
            0
        );

        $this->assertDatabaseCount(
            'inventory_posting_lines',
            0
        );

        $this->assertDatabaseCount(
            'stock_movements',
            0
        );

        $balance = InventoryBalance::firstOrFail();

        $this->assertSame(
            '999999999999.000',
            $balance->available_quantity
        );
    }
}
