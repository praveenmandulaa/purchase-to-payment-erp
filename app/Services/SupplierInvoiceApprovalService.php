<?php

namespace App\Services;

use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierInvoiceApprovalService
{
    public function submit(
        SupplierInvoice $invoice,
        User $actor
    ): SupplierInvoice {
        $this->authorize($actor, 'invoice.create');

        return $this->changeStatus(
            $invoice,
            $actor,
            'matched',
            'pending_approval',
            'submitted_for_approval',
            null
        );
    }

    public function approve(
        SupplierInvoice $invoice,
        User $actor
    ): SupplierInvoice {
        $this->authorize($actor, 'invoice.approve');

        return $this->changeStatus(
            $invoice,
            $actor,
            'pending_approval',
            'approved',
            'approved',
            null
        );
    }

    public function reject(
        SupplierInvoice $invoice,
        User $actor,
        string $reason
    ): SupplierInvoice {
        $this->authorize($actor, 'invoice.approve');

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Rejection reason is required.',
            ]);
        }

        return $this->changeStatus(
            $invoice,
            $actor,
            'pending_approval',
            'rejected',
            'rejected',
            $reason
        );
    }

    private function changeStatus(
        SupplierInvoice $invoice,
        User $actor,
        string $expectedStatus,
        string $newStatus,
        string $action,
        ?string $reason
    ): SupplierInvoice {
        return DB::transaction(function () use (
            $invoice,
            $actor,
            $expectedStatus,
            $newStatus,
            $action,
            $reason
        ) {
            // Lock the parent PO first, matching the lock
            // order used by SupplierInvoiceMatchingService.
            $po = DB::table('purchase_orders')
                ->where('id', $invoice->purchase_order_id)
                ->lockForUpdate()
                ->first();

            if (!$po || $po->status !== 'approved') {
                throw ValidationException::withMessages([
                    'invoice' =>
                        'The purchase order must remain approved.',
                ]);
            }

            $invoice = SupplierInvoice::query()
                ->whereKey($invoice->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($invoice->status !== $expectedStatus) {
                throw ValidationException::withMessages([
                    'invoice' =>
                        "Invoice must be {$expectedStatus} " .
                        "before it can be {$newStatus}.",
                ]);
            }

            // The creator can submit, but cannot make
            // the final approval or rejection decision.
            if (
                in_array($action, ['approved', 'rejected'], true)
                && (int) $invoice->created_by === (int) $actor->id
            ) {
                throw ValidationException::withMessages([
                    'invoice' =>
                        'Invoice creator cannot approve or reject ' .
                        'their own invoice.',
                ]);
            }

            if ($action === 'submitted_for_approval') {
                $lines = $invoice->items()
                    ->lockForUpdate()
                    ->get();

                if ($lines->isEmpty()) {
                    throw ValidationException::withMessages([
                        'invoice' => 'Invoice has no items.',
                    ]);
                }

                foreach ($lines as $line) {
                    if (
                        $line->match_status !== 'matched'
                        || $line->matched_quantity
                            !== $line->invoiced_quantity
                    ) {
                        throw ValidationException::withMessages([
                            'invoice' =>
                                'All invoice items must be fully matched.',
                        ]);
                    }
                }
            }

            $changes = [
                'status' => $newStatus,
            ];

            if ($action === 'submitted_for_approval') {
                $changes['submitted_at'] = now();
            }

            if ($action === 'approved') {
                $changes['approved_by'] = $actor->id;
                $changes['approved_at'] = now();
                $changes['rejection_reason'] = null;
            }

            if ($action === 'rejected') {
                $changes['rejection_reason'] = $reason;
            }

            $invoice->update($changes);

            $invoice->activities()->create([
                'user_id' => $actor->id,
                'action' => $action,
                'from_status' => $expectedStatus,
                'to_status' => $newStatus,
                'reason' => $reason,
                'snapshot' => [
                    'invoice_number' => $invoice->invoice_number,
                    'supplier_invoice_number' =>
                        $invoice->supplier_invoice_number,
                    'total_amount' => $invoice->total_amount,
                    'currency' => $invoice->currency,
                    'actor_id' => $actor->id,
                ],
            ]);

            return $invoice->load([
                'supplier',
                'purchaseOrder',
                'createdBy',
                'approvedBy',
                'items.item',
                'activities.user',
            ]);
        }, 3);
    }

    private function authorize(User $actor, string $permission): void
    {
        abort_unless(
            $actor->is_active && $actor->hasPermission($permission),
            403,
            'You are not authorized to perform this action.'
        );
    }
}
