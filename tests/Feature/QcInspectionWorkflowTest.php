<?php

namespace Tests\Feature;

use App\Models\GoodsReceipt;
use App\Models\QcInspection;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ErpAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class QcInspectionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $purchaseManager;
    private User $warehouseOfficer;
    private User $inspector;
    private User $qcManager;

    private GoodsReceipt $grn;
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
        $this->purchaseManager = $this->makeUser('purchase-manager');
        $this->warehouseOfficer = $this->makeUser('warehouse-officer');
        $this->inspector = $this->makeUser('qc-inspector');
        $this->qcManager = $this->makeUser('qc-manager');

        $supplier = \App\Models\Supplier::create([
            'code' => 'SUP001',
            'name' => 'ABC Steel Suppliers',
            'is_active' => true,
        ]);

        $item = \App\Models\Item::create([
            'code' => 'ITM001',
            'name' => 'Steel Plate',
            'uom' => 'PCS',
            'requires_qc' => true,
            'is_active' => true,
        ]);

        $warehouse = \App\Models\Warehouse::create([
            'code' => 'WH001',
            'name' => 'Main Warehouse',
            'location' => 'Hyderabad',
            'is_active' => true,
        ]);

        // Build an approved PO using the existing API.
        $poResponse = $this->actingAs($this->buyer)
            ->postJson('/erp/po', [
                'supplier_id' => $supplier->id,
                'remarks' => 'QC test purchase',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => 1000,
                        'unit_price' => 250,
                    ],
                ],
            ])->assertCreated();

        $poId = $poResponse->json('data.id');

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$poId}/submit")
            ->assertOk();

        $this->actingAs($this->purchaseManager)
            ->postJson("/erp/po/{$poId}/approve")
            ->assertOk();

        $poLineId = \App\Models\PurchaseOrder::findOrFail(
            $poId
        )->items()->firstOrFail()->id;

        // Receive 600 units using the existing GRN API.
        $grnResponse = $this->actingAs($this->warehouseOfficer)
            ->postJson('/erp/grn', [
                'purchase_order_id' => $poId,
                'warehouse_id' => $warehouse->id,
                'receipt_date' => now()->toDateString(),
                'delivery_reference' => 'QC-TEST-DC-001',
                'items' => [
                    [
                        'purchase_order_item_id' => $poLineId,
                        'received_quantity' => 600,
                    ],
                ],
            ])->assertCreated();

        $this->grn = GoodsReceipt::findOrFail(
            $grnResponse->json('data.id')
        );

        $this->grnLineId = $this->grn->items()
            ->firstOrFail()->id;

        // setUp() authenticated the warehouse officer.
        // Start every test with no authenticated user.
        auth()->guard('web')->logout();
        $this->app['auth']->forgetGuards();
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

    private function payload(
        int $accepted = 550,
        int $rejected = 50,
        ?string $reason = 'Surface damage detected'
    ): array {
        return [
            'goods_receipt_id' => $this->grn->id,
            'inspection_date' => now()->toDateString(),
            'remarks' => 'Incoming steel plate inspection',
            'items' => [
                [
                    'goods_receipt_item_id' => $this->grnLineId,
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => $rejected,
                    'rejection_reason' => $reason,
                ],
            ],
        ];
    }

    private function createInspection(): QcInspection
    {
        $response = $this->actingAs($this->inspector)
            ->postJson('/erp/qc/inspections', $this->payload())
            ->assertCreated();

        return QcInspection::findOrFail(
            $response->json('data.id')
        );
    }

    private function submitInspection(): QcInspection
    {
        $inspection = $this->createInspection();

        $this->actingAs($this->inspector)
            ->postJson(
                "/erp/qc/inspections/{$inspection->id}/submit"
            )
            ->assertOk();

        return $inspection->fresh();
    }

    public function test_inspector_can_create_draft(): void
    {
        $inspection = $this->createInspection();

        $this->assertSame('draft', $inspection->status);

        $this->assertDatabaseHas('qc_inspection_items', [
            'qc_inspection_id' => $inspection->id,
            'goods_receipt_item_id' => $this->grnLineId,
            'accepted_quantity' => 550,
            'rejected_quantity' => 50,
        ]);

        $this->assertDatabaseHas('qc_inspection_activities', [
            'qc_inspection_id' => $inspection->id,
            'action' => 'created',
        ]);
    }

    public function test_inspector_can_submit_own_draft(): void
    {
        $inspection = $this->createInspection();

        $this->actingAs($this->inspector)
            ->postJson(
                "/erp/qc/inspections/{$inspection->id}/submit"
            )
            ->assertOk();

        $this->assertSame(
            'pending_approval',
            $inspection->fresh()->status
        );
    }

    public function test_manager_can_approve_inspection(): void
    {
        $inspection = $this->submitInspection();

        $this->actingAs($this->qcManager)
            ->postJson(
                "/erp/qc/inspections/{$inspection->id}/approve"
            )
            ->assertOk();

        $this->assertSame(
            'approved',
            $inspection->fresh()->status
        );

        $this->assertDatabaseHas('qc_inspection_activities', [
            'qc_inspection_id' => $inspection->id,
            'action' => 'approved',
            'user_id' => $this->qcManager->id,
        ]);
    }

    public function test_manager_can_reject_inspection(): void
    {
        $inspection = $this->submitInspection();

        $this->actingAs($this->qcManager)
            ->postJson(
                "/erp/qc/inspections/{$inspection->id}/reject",
                ['reason' => 'Inspection report is incomplete']
            )
            ->assertOk();

        $this->assertSame(
            'rejected',
            $inspection->fresh()->status
        );
    }

    public function test_rejected_inspection_can_be_replaced(): void
    {
        $inspection = $this->submitInspection();

        $this->actingAs($this->qcManager)
            ->postJson(
                "/erp/qc/inspections/{$inspection->id}/reject",
                ['reason' => 'Incorrect quality classification']
            )
            ->assertOk();

        $this->actingAs($this->inspector)
            ->postJson(
                '/erp/qc/inspections',
                $this->payload(580, 20, 'Edge damage found')
            )
            ->assertCreated();

        $this->assertDatabaseCount('qc_inspections', 2);
    }

    public function test_accepted_and_rejected_must_equal_received(): void
    {
        $this->actingAs($this->inspector)
            ->postJson(
                '/erp/qc/inspections',
                $this->payload(550, 60)
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount('qc_inspections', 0);
    }

    public function test_rejection_reason_is_required(): void
    {
        $this->actingAs($this->inspector)
            ->postJson(
                '/erp/qc/inspections',
                $this->payload(550, 50, null)
            )
            ->assertUnprocessable();

        $this->assertDatabaseCount('qc_inspections', 0);
    }

    public function test_duplicate_active_inspection_is_rejected(): void
    {
        $this->createInspection();

        $this->actingAs($this->inspector)
            ->postJson('/erp/qc/inspections', $this->payload())
            ->assertUnprocessable();

        $this->assertDatabaseCount('qc_inspections', 1);
    }

    public function test_approved_inspection_cannot_be_approved_twice(): void
    {
        $inspection = $this->submitInspection();

        $this->actingAs($this->qcManager)
            ->postJson(
                "/erp/qc/inspections/{$inspection->id}/approve"
            )
            ->assertOk();

        $this->actingAs($this->qcManager)
            ->postJson(
                "/erp/qc/inspections/{$inspection->id}/approve"
            )
            ->assertUnprocessable();
    }

    public function test_buyer_cannot_create_qc_inspection(): void
    {
        $this->actingAs($this->buyer)
            ->postJson('/erp/qc/inspections', $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('qc_inspections', 0);
    }

    public function test_guest_cannot_create_qc_inspection(): void
    {
        $this->assertGuest('web');

        $this->postJson('/erp/qc/inspections', $this->payload())
            ->assertUnauthorized();

        $this->assertDatabaseCount('qc_inspections', 0);
    }

    public function test_manager_cannot_approve_draft(): void
    {
        $inspection = $this->createInspection();

        $this->actingAs($this->qcManager)
            ->postJson(
                "/erp/qc/inspections/{$inspection->id}/approve"
            )
            ->assertUnprocessable();
    }

    public function test_self_approval_is_forbidden(): void
    {
        // Give the inspector manager permission as well.
        $this->inspector->roles()->syncWithoutDetaching([
            Role::where('slug', 'qc-manager')->firstOrFail()->id,
        ]);

        $inspection = $this->submitInspection();

        $this->actingAs($this->inspector)
            ->postJson(
                "/erp/qc/inspections/{$inspection->id}/approve"
            )
            ->assertForbidden();
    }

    public function test_qc_approval_does_not_post_inventory(): void
    {
        $inspection = $this->submitInspection();

        $this->actingAs($this->qcManager)
            ->postJson(
                "/erp/qc/inspections/{$inspection->id}/approve"
            )
            ->assertOk();

        $this->assertDatabaseHas('goods_receipt_items', [
            'id' => $this->grnLineId,
            'received_quantity' => 600,
            'qc_status' => 'pending_qc',
        ]);
    }
}
