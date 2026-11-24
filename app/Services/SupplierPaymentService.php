<?php

namespace App\Services;

use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierPaymentService
{
    public function record(
        SupplierInvoice $invoice,
        User $actor,
        array $data
    ): SupplierPayment {
        abort_unless(
            $actor->is_active &&
            $actor->hasPermission('payment.record'),
            403,
            'You do not have permission to record payments.'
        );

        $validated = validator($data, [
            'amount' => [
                'required',
                'regex:/^\d{1,13}(\.\d{1,2})?$/',
            ],
            'payment_date' => [
                'required',
                'date_format:Y-m-d',
            ],
            'payment_method' => [
                'required',
                'in:bank_transfer,upi,cheque,cash',
            ],
            'payment_reference' => [
                'required',
                'string',
                'max:100',
            ],
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ])->validate();

        $amountCents = $this->toCents($validated['amount']);

        if ($amountCents <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        $reference = trim($validated['payment_reference']);

        if ($reference === '') {
            throw ValidationException::withMessages([
                'payment_reference' => 'Payment reference is required.',
            ]);
        }

        try {
            return DB::transaction(function () use (
                $invoice,
                $actor,
                $validated,
                $amountCents,
                $reference
            ) {
                // Lock the invoice before calculating outstanding.
                // Every payment for this invoice must acquire this lock.
                $lockedInvoice = SupplierInvoice::query()
                    ->whereKey($invoice->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedInvoice->status !== 'approved') {
                    throw ValidationException::withMessages([
                        'invoice' =>
                            'Only approved invoices can receive payments.',
                    ]);
                }

                if ($lockedInvoice->currency !== 'INR') {
                    throw ValidationException::withMessages([
                        'invoice' =>
                            'Only INR payments are supported currently.',
                    ]);
                }

                $duplicate = SupplierPayment::query()
                    ->where('supplier_id', $lockedInvoice->supplier_id)
                    ->where('payment_reference', $reference)
                    ->exists();

                if ($duplicate) {
                    throw ValidationException::withMessages([
                        'payment_reference' =>
                            'This supplier payment reference already exists.',
                    ]);
                }

                // Sum recorded payments using decimal strings.
                $paidAmount = SupplierPayment::query()
                    ->where('supplier_invoice_id', $lockedInvoice->id)
                    ->where('status', 'recorded')
                    ->sum('amount');

                $invoiceCents = $this->toCents(
                    (string) $lockedInvoice->total_amount
                );

                $paidCents = $this->toCents(
                    (string) $paidAmount
                );

                $outstandingCents = $invoiceCents - $paidCents;

                if ($outstandingCents <= 0) {
                    throw ValidationException::withMessages([
                        'invoice' =>
                            'This invoice has already been fully paid.',
                    ]);
                }

                if ($amountCents > $outstandingCents) {
                    throw ValidationException::withMessages([
                        'amount' =>
                            'Payment exceeds the outstanding balance of ₹' .
                            $this->fromCents($outstandingCents) . '.',
                    ]);
                }

                /*
                 * Create with a temporary unique number.
                 *
                 * Once MySQL assigns the primary key, generate
                 * a stable, globally unique ERP payment number.
                 * The temporary number is never committed.
                 */
                $payment = SupplierPayment::create([
                    'payment_number' => 'TMP-' . Str::uuid(),
                    'supplier_invoice_id' => $lockedInvoice->id,
                    'supplier_id' => $lockedInvoice->supplier_id,
                    'payment_date' => $validated['payment_date'],
                    'amount' => $this->fromCents($amountCents),
                    'currency' => $lockedInvoice->currency,
                    'payment_method' => $validated['payment_method'],
                    'payment_reference' => $reference,
                    'status' => 'recorded',
                    'recorded_by' => $actor->id,
                    'remarks' => $validated['remarks'] ?? null,
                ]);

                $payment->update([
                    'payment_number' => sprintf(
                        'PAY-%s-%06d',
                        now()->format('Y'),
                        $payment->id
                    ),
                ]);

                $remainingCents = $outstandingCents - $amountCents;

                $payment->activities()->create([
                    'user_id' => $actor->id,
                    'action' => 'recorded',
                    'from_status' => null,
                    'to_status' => 'recorded',
                    'reason' => null,
                    'snapshot' => [
                        'payment_number' => $payment->payment_number,
                        'invoice_number' =>
                            $lockedInvoice->invoice_number,
                        'supplier_invoice_id' => $lockedInvoice->id,
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'payment_method' => $payment->payment_method,
                        'payment_reference' => $payment->payment_reference,
                        'outstanding_before' =>
                            $this->fromCents($outstandingCents),
                        'outstanding_after' =>
                            $this->fromCents($remainingCents),
                    ],
                ]);

                return $payment->load([
                    'invoice',
                    'supplier',
                    'recordedBy',
                    'activities.user',
                ]);
            }, 3);
        } catch (QueryException $exception) {
            // The database unique constraint is the final protection
            // against simultaneous duplicate reference submissions.
            if (
                in_array((string) $exception->getCode(), [
                    '23000',
                    '23505',
                ], true)
            ) {
                throw ValidationException::withMessages([
                    'payment_reference' =>
                        'Payment reference already exists or conflicts ' .
                        'with another payment. Please verify and retry.',
                ]);
            }

            throw $exception;
        }
    }

    /**
     * Convert a decimal amount into integer paise.
     * No floating-point arithmetic is used.
     */
    private function toCents(string $amount): int
    {
        if (!preg_match(
            '/^\d{1,13}(\.\d{1,2})?$/',
            $amount
        )) {
            throw ValidationException::withMessages([
                'amount' => 'Invalid monetary amount.',
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $amount, 2),
            2,
            '0'
        );

        return ((int) $whole * 100)
            + (int) str_pad($fraction, 2, '0');
    }

    private function fromCents(int $cents): string
    {
        return sprintf(
            '%d.%02d',
            intdiv($cents, 100),
            $cents % 100
        );
    }
}
