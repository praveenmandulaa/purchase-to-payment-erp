<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\ErpAccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PurchaseOrderEditCancelTest extends TestCase
{
    use RefreshDatabase;

    private User $buyer;
    private User $otherBuyer;
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

        $this->buyer = $this->makeUser('buyer');
        $this->otherBuyer = $this->makeUser('buyer');
        $this->manager = $this->makeUser('purchase-manager');

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
        int $quantity = 1000,
        float $price = 250
    ): array {
        return [
            'supplier_id' => $this->supplier->id,
            'remarks' => 'Steel plate procurement',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                ],
            ],
        ];
    }

    private function createDraft(): PurchaseOrder
    {
        $response = $this->actingAs($this->buyer)
            ->postJson('/erp/po', $this->payload());

        $response->assertCreated();

        return PurchaseOrder::findOrFail(
            $response->json('data.id')
        );
    }

    public function test_buyer_can_edit_own_draft_po(): void
    {
        $po = $this->createDraft();

        $this->actingAs($this->buyer)
            ->putJson(
                "/erp/po/{$po->id}",
                $this->payload(1200, 260)
            )
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');

        $po->refresh();

        $this->assertEquals(312000, $po->total_amount);

        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $po->id,
            'item_id' => $this->item->id,
            'quantity' => 1200,
            'unit_price' => 260,
            'line_total' => 312000,
        ]);

        $this->assertDatabaseHas('purchase_order_activities', [
            'purchase_order_id' => $po->id,
            'user_id' => $this->buyer->id,
            'action' => 'updated',
        ]);
    }

    public function test_other_buyer_cannot_edit_po(): void
    {
        $po = $this->createDraft();

        $this->actingAs($this->otherBuyer)
            ->putJson(
                "/erp/po/{$po->id}",
                $this->payload(1200, 260)
            )
            ->assertForbidden();

        $this->assertEquals(
            250000,
            $po->fresh()->total_amount
        );
    }

    public function test_submitted_po_cannot_be_edited(): void
    {
        $po = $this->createDraft();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po->id}/submit")
            ->assertOk();

        $this->actingAs($this->buyer)
            ->putJson(
                "/erp/po/{$po->id}",
                $this->payload(1200, 260)
            )
            ->assertUnprocessable();

        $this->assertEquals(
            250000,
            $po->fresh()->total_amount
        );
    }

    public function test_buyer_can_cancel_own_draft(): void
    {
        $po = $this->createDraft();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po->id}/cancel", [
                'reason' => 'Supplier delivery is no longer required.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('purchase_order_activities', [
            'purchase_order_id' => $po->id,
            'user_id' => $this->buyer->id,
            'action' => 'cancelled',
            'from_status' => 'draft',
            'to_status' => 'cancelled',
        ]);
    }

    public function test_buyer_can_cancel_pending_po(): void
    {
        $po = $this->createDraft();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po->id}/submit")
            ->assertOk();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po->id}/cancel", [
                'reason' => 'The production requirement changed.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->actingAs($this->manager)
            ->postJson("/erp/po/{$po->id}/approve")
            ->assertUnprocessable();

        $this->assertDatabaseCount(
            'purchase_order_approvals',
            0
        );
    }

    public function test_cancellation_requires_reason(): void
    {
        $po = $this->createDraft();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po->id}/cancel", [])
            ->assertUnprocessable();

        $this->assertSame('draft', $po->fresh()->status);
    }

    public function test_other_buyer_cannot_cancel_po(): void
    {
        $po = $this->createDraft();

        $this->actingAs($this->otherBuyer)
            ->postJson("/erp/po/{$po->id}/cancel", [
                'reason' => 'Unauthorized cancellation attempt.',
            ])
            ->assertForbidden();

        $this->assertSame('draft', $po->fresh()->status);
    }

    public function test_approved_po_cannot_be_cancelled(): void
    {
        $po = $this->createDraft();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po->id}/submit")
            ->assertOk();

        $this->actingAs($this->manager)
            ->postJson("/erp/po/{$po->id}/approve")
            ->assertOk();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po->id}/cancel", [
                'reason' => 'Attempt to cancel approved PO.',
            ])
            ->assertUnprocessable();

        $this->assertSame('approved', $po->fresh()->status);
    }

    public function test_cancelled_po_cannot_be_cancelled_twice(): void
    {
        $po = $this->createDraft();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po->id}/cancel", [
                'reason' => 'Original cancellation reason.',
            ])
            ->assertOk();

        $this->actingAs($this->buyer)
            ->postJson("/erp/po/{$po->id}/cancel", [
                'reason' => 'Second cancellation attempt.',
            ])
            ->assertUnprocessable();
$this->assertDatabaseCount(
    'purchase_order_activities',
    2
);

$this->assertSame(
    1,
    \Illuminate\Support\Facades\DB::table(
        'purchase_order_activities'
    )->where('action', 'created')->count()
);

$this->assertSame(
    1,
    \Illuminate\Support\Facades\DB::table(
        'purchase_order_activities'
    )->where('action', 'cancelled')->count()
);    }

    public function test_invalid_edit_preserves_original_lines(): void
    {
        $po = $this->createDraft();

        $this->actingAs($this->buyer)
            ->putJson(
                "/erp/po/{$po->id}",
                $this->payload(-10, 260)
            )
            ->assertUnprocessable();

        $this->assertEquals(
            250000,
            $po->fresh()->total_amount
        );

        $this->assertDatabaseCount(
            'purchase_order_items',
            1
        );
$this->assertDatabaseCount(
    'purchase_order_activities',
    1
);

$this->assertSame(
    1,
    \Illuminate\Support\Facades\DB::table(
        'purchase_order_activities'
    )->where('action', 'created')->count()
);

$this->assertSame(
    0,
    \Illuminate\Support\Facades\DB::table(
        'purchase_order_activities'
    )->where('action', 'updated')->count()
);
}
}
