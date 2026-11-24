
<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const activeTab = ref('invoices');
const invoices = ref([]);
const payments = ref([]);
const selectedPayment = ref(null);

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const success = ref('');

const form = ref({
    supplier_invoice_id: '',
    payment_date: localDate(),
    amount: '',
    payment_method: 'bank_transfer',
    payment_reference: '',
    remarks: '',
});

function localDate() {
    const date = new Date();
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

const selectedInvoice = computed(() =>
    invoices.value.find(
        invoice =>
            String(invoice.id) ===
            String(form.value.supplier_invoice_id)
    ) || null
);

const payableInvoices = computed(() =>
    invoices.value.filter(
        invoice => Number(invoice.outstanding_amount) > 0
    )
);

const totalOutstanding = computed(() =>
    invoices.value.reduce(
        (sum, invoice) =>
            sum + Number(invoice.outstanding_amount || 0),
        0
    )
);

const totalPaid = computed(() =>
    invoices.value.reduce(
        (sum, invoice) =>
            sum + Number(invoice.paid_amount || 0),
        0
    )
);

function money(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value || 0));
}

function displayDate(value) {
    if (!value) return '—';

    return String(value).slice(0, 10);
}

function statusLabel(status) {
    return {
        unpaid: 'Unpaid',
        partially_paid: 'Partially Paid',
        paid: 'Fully Paid',
    }[status] || status || '—';
}

function methodLabel(method) {
    return {
        bank_transfer: 'Bank Transfer',
        upi: 'UPI',
        cheque: 'Cheque',
        cash: 'Cash',
    }[method] || method;
}

function getErrorMessage(err) {
    const data = err.response?.data;

    if (data?.errors) {
        return Object.values(data.errors)
            .flat()
            .join(' ');
    }

    return data?.message ||
        'Something went wrong. Please try again.';
}

async function loadData() {
    loading.value = true;
    error.value = '';

    try {
        const [invoiceResponse, paymentResponse] =
            await Promise.all([
                axios.get('/erp/payments/approved-invoices'),
                axios.get('/erp/payments'),
            ]);

        invoices.value = invoiceResponse.data.data || [];
        payments.value = paymentResponse.data.data || [];

        if (
            form.value.supplier_invoice_id &&
            !payableInvoices.value.some(
                invoice =>
                    String(invoice.id) ===
                    String(form.value.supplier_invoice_id)
            )
        ) {
            form.value.supplier_invoice_id = '';
        }
    } catch (err) {
        error.value = getErrorMessage(err);
    } finally {
        loading.value = false;
    }
}

function selectInvoice(invoice) {
    if (Number(invoice.outstanding_amount) <= 0) {
        return;
    }

    form.value.supplier_invoice_id = String(invoice.id);
    form.value.amount = '';
    form.value.payment_reference = '';

    success.value = '';
    error.value = '';
    activeTab.value = 'record';
}

function fillOutstanding() {
    if (!selectedInvoice.value) return;

    form.value.amount =
        selectedInvoice.value.outstanding_amount;
}

function resetForm() {
    form.value = {
        supplier_invoice_id: '',
        payment_date: localDate(),
        amount: '',
        payment_method: 'bank_transfer',
        payment_reference: '',
        remarks: '',
    };
}

async function recordPayment() {
    error.value = '';
    success.value = '';

    const invoice = selectedInvoice.value;

    if (!invoice) {
        error.value = 'Please select an approved invoice.';
        return;
    }

    const amount = Number(form.value.amount);
    const outstanding = Number(invoice.outstanding_amount);

    if (!Number.isFinite(amount) || amount <= 0) {
        error.value = 'Enter a payment amount greater than zero.';
        return;
    }

    if (amount > outstanding) {
        error.value =
            'Payment exceeds the outstanding amount of ' +
            money(outstanding) + '.';
        return;
    }

    if (!/^\d{1,13}(\.\d{1,2})?$/.test(
        String(form.value.amount).trim()
    )) {
        error.value =
            'Enter a valid amount with up to two decimal places.';
        return;
    }

    if (!form.value.payment_reference.trim()) {
        error.value = 'Payment reference is required.';
        return;
    }

    const confirmed = window.confirm(
        `Record a payment of ${money(amount)} against ` +
        `${invoice.invoice_number}?\n\n` +
        `Reference: ${form.value.payment_reference.trim()}\n` +
        'Only confirm if the payment has actually been made.'
    );

    if (!confirmed) return;

    saving.value = true;

    try {
        const response = await axios.post(
            '/erp/payments',
            {
                supplier_invoice_id: Number(
                    form.value.supplier_invoice_id
                ),
                payment_date: form.value.payment_date,
                amount: String(form.value.amount).trim(),
                payment_method: form.value.payment_method,
                payment_reference:
                    form.value.payment_reference.trim(),
                remarks: form.value.remarks.trim() || null,
            }
        );

        const paymentNumber =
            response.data.payment?.payment_number || 'Payment';

        resetForm();

        await loadData();

        success.value =
            `${paymentNumber} recorded successfully.`;

        activeTab.value = 'history';
    } catch (err) {
        error.value = getErrorMessage(err);
    } finally {
        saving.value = false;
    }
}

async function viewPayment(payment) {
    error.value = '';

    try {
        const response = await axios.get(
            `/erp/payments/${payment.id}`
        );

        selectedPayment.value = response.data.payment;
        activeTab.value = 'details';
    } catch (err) {
        error.value = getErrorMessage(err);
    }
}

onMounted(loadData);
</script>

<template>
    <div class="payments-page">
        <div class="page-heading">
            <div>
                <h1>Supplier Payments</h1>
                <p>
                    Record payments against approved supplier invoices
                    and monitor outstanding balances.
                </p>
            </div>

            <button
                type="button"
                class="refresh-button"
                :disabled="loading"
                @click="loadData"
            >
                {{ loading ? 'Loading...' : 'Refresh' }}
            </button>
        </div>

        <div v-if="error" class="alert error-alert">
            {{ error }}
        </div>

        <div v-if="success" class="alert success-alert">
            {{ success }}
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <span>Approved Invoices</span>
                <strong>{{ invoices.length }}</strong>
            </div>

            <div class="summary-card">
                <span>Total Paid</span>
                <strong>{{ money(totalPaid) }}</strong>
            </div>

            <div class="summary-card">
                <span>Total Outstanding</span>
                <strong>{{ money(totalOutstanding) }}</strong>
            </div>

            <div class="summary-card">
                <span>Payment Records Shown</span>
                <strong>{{ payments.length }}</strong>
            </div>
        </div>

        <div class="tabs">
            <button
                type="button"
                :class="{ active: activeTab === 'invoices' }"
                @click="activeTab = 'invoices'"
            >
                Approved Invoices
            </button>

            <button
                type="button"
                :class="{ active: activeTab === 'record' }"
                @click="activeTab = 'record'"
            >
                Record Payment
            </button>

            <button
                type="button"
                :class="{ active: activeTab === 'history' }"
                @click="activeTab = 'history'"
            >
                Payment History
            </button>
        </div>

        <!-- Approved invoices -->
        <section
            v-if="activeTab === 'invoices'"
            class="panel"
        >
            <h2>Approved Supplier Invoices</h2>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Supplier</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Outstanding</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="invoice in invoices"
                            :key="invoice.id"
                        >
                            <td>
                                <strong>
                                    {{ invoice.invoice_number }}
                                </strong>
                                <small>
                                    {{ invoice.supplier_invoice_number }}
                                </small>
                            </td>

                            <td>{{ invoice.supplier_name }}</td>

                            <td>{{ money(invoice.total_amount) }}</td>

                            <td>{{ money(invoice.paid_amount) }}</td>

                            <td>
                                <strong>
                                    {{ money(invoice.outstanding_amount) }}
                                </strong>
                            </td>

                            <td>
                                <span
                                    class="status"
                                    :class="invoice.payment_status"
                                >
                                    {{ statusLabel(invoice.payment_status) }}
                                </span>
                            </td>

                            <td>
                                <button
                                    type="button"
                                    class="primary-button"
                                    :disabled="
                                        Number(invoice.outstanding_amount) <= 0
                                    "
                                    @click="selectInvoice(invoice)"
                                >
                                    Record Payment
                                </button>
                            </td>
                        </tr>

                        <tr v-if="invoices.length === 0">
                            <td colspan="7" class="empty">
                                No approved supplier invoices found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Record payment -->
        <section
            v-else-if="activeTab === 'record'"
            class="panel"
        >
            <h2>Record Supplier Payment</h2>

            <p class="help-text">
                Only record a payment after the money has actually
                been transferred or paid to the supplier.
            </p>

            <form @submit.prevent="recordPayment">
                <div class="form-grid">
                    <label>
                        Approved Invoice
                        <select
                            v-model="form.supplier_invoice_id"
                            required
                        >
                            <option value="">
                                Select an invoice
                            </option>

                            <option
                                v-for="invoice in payableInvoices"
                                :key="invoice.id"
                                :value="String(invoice.id)"
                            >
                                {{ invoice.invoice_number }}
                                —
                                {{ invoice.supplier_name }}
                                —
                                {{ money(invoice.outstanding_amount) }}
                            </option>
                        </select>
                    </label>

                    <label>
                        Payment Date
                        <input
                            v-model="form.payment_date"
                            type="date"
                            required
                        />
                    </label>

                    <label>
                        Payment Amount (INR)
                        <input
                            v-model="form.amount"
                            type="text"
                            inputmode="decimal"
                            placeholder="50000.00"
                            required
                        />
                    </label>

                    <label>
                        Payment Method
                        <select
                            v-model="form.payment_method"
                            required
                        >
                            <option value="bank_transfer">
                                Bank Transfer
                            </option>
                            <option value="upi">UPI</option>
                            <option value="cheque">Cheque</option>
                            <option value="cash">Cash</option>
                        </select>
                    </label>

                    <label>
                        Payment Reference
                        <input
                            v-model="form.payment_reference"
                            type="text"
                            maxlength="100"
                            placeholder="Bank UTR / Cheque / Transaction ID"
                            required
                        />
                    </label>

                    <label>
                        Remarks (Optional)
                        <textarea
                            v-model="form.remarks"
                            rows="3"
                            maxlength="2000"
                            placeholder="Additional payment notes"
                        ></textarea>
                    </label>
                </div>

                <div
                    v-if="selectedInvoice"
                    class="invoice-summary"
                >
                    <h3>Selected Invoice</h3>

                    <div>
                        <span>Invoice</span>
                        <strong>
                            {{ selectedInvoice.invoice_number }}
                        </strong>
                    </div>

                    <div>
                        <span>Supplier</span>
                        <strong>
                            {{ selectedInvoice.supplier_name }}
                        </strong>
                    </div>

                    <div>
                        <span>Invoice Total</span>
                        <strong>
                            {{ money(selectedInvoice.total_amount) }}
                        </strong>
                    </div>

                    <div>
                        <span>Already Paid</span>
                        <strong>
                            {{ money(selectedInvoice.paid_amount) }}
                        </strong>
                    </div>

                    <div>
                        <span>Outstanding</span>
                        <strong>
                            {{ money(selectedInvoice.outstanding_amount) }}
                        </strong>
                    </div>

                    <button
                        type="button"
                        class="secondary-button"
                        @click="fillOutstanding"
                    >
                        Pay Full Outstanding
                    </button>
                </div>

                <div class="form-actions">
                    <button
                        type="submit"
                        class="primary-button"
                        :disabled="saving || !selectedInvoice"
                    >
                        {{
                            saving
                                ? 'Recording...'
                                : 'Record Payment'
                        }}
                    </button>

                    <button
                        type="button"
                        class="secondary-button"
                        @click="resetForm"
                    >
                        Clear Form
                    </button>
                </div>
            </form>
        </section>

        <!-- Payment history -->
        <section
            v-else-if="activeTab === 'history'"
            class="panel"
        >
            <h2>Payment History</h2>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Payment No.</th>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Supplier</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="payment in payments"
                            :key="payment.id"
                        >
                            <td>
                                <strong>
                                    {{ payment.payment_number }}
                                </strong>
                            </td>

                            <td>
                                {{ displayDate(payment.payment_date) }}
                            </td>

                            <td>
                                {{ payment.invoice?.invoice_number }}
                            </td>

                            <td>
                                {{ payment.supplier?.name }}
                            </td>

                            <td>{{ money(payment.amount) }}</td>

                            <td>
                                {{ methodLabel(payment.payment_method) }}
                            </td>

                            <td>{{ payment.payment_reference }}</td>

                            <td>
                                <button
                                    type="button"
                                    class="secondary-button"
                                    @click="viewPayment(payment)"
                                >
                                    View
                                </button>
                            </td>
                        </tr>

                        <tr v-if="payments.length === 0">
                            <td colspan="8" class="empty">
                                No payments have been recorded yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="help-text">
                Showing the most recent 20 payments.
            </p>
        </section>

        <!-- Payment details -->
        <section
            v-else-if="
                activeTab === 'details' &&
                selectedPayment
            "
            class="panel"
        >
            <button
                type="button"
                class="secondary-button"
                @click="activeTab = 'history'"
            >
                Back to Payment History
            </button>

            <h2>
                {{ selectedPayment.payment_number }}
            </h2>

            <div class="invoice-summary">
                <div>
                    <span>Supplier</span>
                    <strong>
                        {{ selectedPayment.supplier?.name }}
                    </strong>
                </div>

                <div>
                    <span>Invoice</span>
                    <strong>
                        {{ selectedPayment.invoice?.invoice_number }}
                    </strong>
                </div>

                <div>
                    <span>Payment Amount</span>
                    <strong>
                        {{ money(selectedPayment.amount) }}
                    </strong>
                </div>

                <div>
                    <span>Payment Date</span>
                    <strong>
                        {{ displayDate(selectedPayment.payment_date) }}
                    </strong>
                </div>

                <div>
                    <span>Method</span>
                    <strong>
                        {{ methodLabel(selectedPayment.payment_method) }}
                    </strong>
                </div>

                <div>
                    <span>Reference</span>
                    <strong>
                        {{ selectedPayment.payment_reference }}
                    </strong>
                </div>

                <div>
                    <span>Recorded By</span>
                    <strong>
                        {{ selectedPayment.recorded_by?.name ||
                           selectedPayment.recordedBy?.name ||
                           '—' }}
                    </strong>
                </div>

                <div>
                    <span>Status</span>
                    <strong>{{ selectedPayment.status }}</strong>
                </div>
            </div>

            <h3>Audit History</h3>

            <div
                v-for="activity in selectedPayment.activities || []"
                :key="activity.id"
                class="activity"
            >
                <strong>{{ activity.action }}</strong>

                <p>
                    By {{ activity.user?.name || 'Unknown' }}
                    —
                    {{ activity.created_at }}
                </p>

                <p v-if="activity.reason">
                    {{ activity.reason }}
                </p>
            </div>

            <p
                v-if="!selectedPayment.activities?.length"
                class="help-text"
            >
                No audit history found.
            </p>
        </section>
    </div>
</template>

<style scoped>
.payments-page {
    padding: 24px;
    color: #1f2937;
}

.page-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
}

.page-heading h1 {
    font-size: 26px;
    font-weight: 700;
    margin: 0 0 6px;
}

.page-heading p,
.help-text {
    color: #64748b;
    font-size: 14px;
    line-height: 1.5;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.summary-card,
.panel {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}

.summary-card {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.summary-card span {
    color: #64748b;
    font-size: 13px;
}

.summary-card strong {
    font-size: 23px;
    overflow-wrap: anywhere;
}

.tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 18px;
}

.tabs button {
    padding: 10px 16px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: white;
    color: #334155;
    cursor: pointer;
}

.tabs button.active {
    background: #1d4ed8;
    color: white;
    border-color: #1d4ed8;
}

.panel h2 {
    margin: 0 0 18px;
    font-size: 20px;
}

.panel h3 {
    margin: 20px 0 12px;
    font-size: 16px;
}

.table-scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
}

th,
td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: middle;
}

th {
    background: #f8fafc;
    color: #475569;
    font-weight: 600;
    white-space: nowrap;
}

td small {
    display: block;
    color: #64748b;
    margin-top: 4px;
}

.empty {
    padding: 28px;
    text-align: center;
    color: #64748b;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

label {
    display: flex;
    flex-direction: column;
    gap: 7px;
    font-size: 14px;
    font-weight: 600;
}

input,
select,
textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 11px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: white;
    color: #1f2937;
    font: inherit;
}

textarea {
    resize: vertical;
}

.invoice-summary {
    margin-top: 22px;
    padding: 18px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

.invoice-summary > div {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 9px 0;
    border-bottom: 1px solid #e2e8f0;
}

.invoice-summary > div span {
    color: #64748b;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 22px;
}

.primary-button,
.secondary-button,
.refresh-button {
    padding: 9px 14px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.primary-button {
    background: #1d4ed8;
    border: 1px solid #1d4ed8;
    color: white;
}

.secondary-button,
.refresh-button {
    background: white;
    border: 1px solid #cbd5e1;
    color: #334155;
}

button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.status {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.status.unpaid {
    background: #f1f5f9;
    color: #475569;
}

.status.partially_paid {
    background: #fef3c7;
    color: #92400e;
}

.status.paid {
    background: #dcfce7;
    color: #166534;
}

.alert {
    padding: 12px 16px;
    border-radius: 8px;
    margin-bottom: 18px;
}

.error-alert {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

.success-alert {
    background: #f0fdf4;
    color: #15803d;
    border: 1px solid #bbf7d0;
}

.activity {
    border-left: 3px solid #1d4ed8;
    padding: 8px 14px;
    margin: 12px 0;
    background: #f8fafc;
}

.activity p {
    color: #64748b;
    font-size: 13px;
    margin: 5px 0;
}

@media (max-width: 900px) {
    .summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 640px) {
    .payments-page {
        padding: 12px;
    }

    .summary-grid,
    .form-grid {
        grid-template-columns: 1fr;
    }

    .page-heading {
        flex-direction: column;
    }

    .form-actions {
        flex-wrap: wrap;
    }
}
</style>
