<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    canCreate: { type: Boolean, default: false },
    canApprove: { type: Boolean, default: false },
});

const activeTab = ref('list');
const invoices = ref([]);
const purchaseOrders = ref([]);
const loading = ref(false);
const saving = ref(false);
const matching = ref(false);
const processingDecision = ref(false);
const error = ref('');
const success = ref('');
const selectedInvoice = ref(null);
const rejectionReason = ref('');
const showRejectForm = ref(false);

function emptyForm() {
    return {
        purchase_order_id: '',
        supplier_id: '',
        supplier_invoice_number: '',
        invoice_date: new Date().toLocaleDateString('en-CA'),
        due_date: '',
        tax_amount: '0.00',
        remarks: '',
        items: [],
    };
}

const form = ref(emptyForm());

const selectedPo = computed(() =>
    purchaseOrders.value.find(
        po => String(po.id) === String(form.value.purchase_order_id)
    )
);

const subtotal = computed(() =>
    form.value.items.reduce((sum, item) => {
        if (!item.selected) return sum;
        return sum + Number(item.invoiced_quantity || 0) * Number(item.unit_price || 0);
    }, 0)
);
const total = computed(() => subtotal.value + Number(form.value.tax_amount || 0));

function money(value) {
    return Number(value || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function quantity(value) {
    return Number(value || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
}

function errorMessage(err) {
    const errors = err.response?.data?.errors;
    if (errors) {
        const first = Object.values(errors).flat()[0];
        if (first) return String(first);
    }
    return err.response?.data?.message || 'Unable to complete the request.';
}

async function loadData() {
    loading.value = true;
    error.value = '';
    try {
        const invoiceResponse = await axios.get('/erp/invoices');
        invoices.value = invoiceResponse.data.data || [];
        if (props.canCreate) {
            const poResponse = await axios.get('/erp/invoices/approved-pos');
            purchaseOrders.value = poResponse.data.data || [];
        } else {
            purchaseOrders.value = [];
        }
    } catch (err) {
        error.value = errorMessage(err);
    } finally {
        loading.value = false;
    }
}

function selectPurchaseOrder() {
    const po = selectedPo.value;
    if (!po) {
        form.value.supplier_id = '';
        form.value.items = [];
        return;
    }
    form.value.supplier_id = po.supplier_id;
    form.value.items = (po.items || []).map(line => ({
        purchase_order_item_id: line.id,
        item_code: line.item_code,
        item_name: line.item_name,
        uom: line.uom,
        ordered_quantity: line.ordered_quantity,
        invoiced_quantity: String(line.ordered_quantity),
        unit_price: String(line.unit_price),
        selected: true,
    }));
}

function startNewInvoice() {
    if (!props.canCreate) return;
    selectedInvoice.value = null;
    form.value = emptyForm();
    error.value = '';
    success.value = '';
    activeTab.value = 'create';
}

async function createInvoice() {
    if (!props.canCreate || saving.value) return;
    error.value = '';
    success.value = '';
    const selectedItems = form.value.items.filter(item => item.selected);
    if (!form.value.purchase_order_id) {
        error.value = 'Select an approved purchase order.';
        return;
    }
    if (!form.value.supplier_invoice_number.trim()) {
        error.value = 'Enter the supplier invoice number.';
        return;
    }
    if (!selectedItems.length) {
        error.value = 'Select at least one invoice line.';
        return;
    }
    saving.value = true;
    try {
        const payload = {
            purchase_order_id: Number(form.value.purchase_order_id),
            supplier_id: Number(form.value.supplier_id),
            supplier_invoice_number: form.value.supplier_invoice_number.trim(),
            invoice_date: form.value.invoice_date,
            due_date: form.value.due_date || null,
            tax_amount: String(form.value.tax_amount || '0'),
            remarks: form.value.remarks || null,
            items: selectedItems.map(item => ({
                purchase_order_item_id: Number(item.purchase_order_item_id),
                invoiced_quantity: String(item.invoiced_quantity),
                unit_price: String(item.unit_price),
            })),
        };
        const response = await axios.post('/erp/invoices', payload);
        const number = response.data.data.invoice_number;
        await loadData();
        success.value = `Invoice ${number} created successfully as a draft.`;
        activeTab.value = 'list';
    } catch (err) {
        error.value = errorMessage(err);
    } finally {
        saving.value = false;
    }
}

async function runThreeWayMatch() {
    if (!props.canCreate || !selectedInvoice.value || matching.value) return;
    const invoiceNumber = selectedInvoice.value.invoice_number;
    if (!window.confirm(`Run 3-way matching for ${invoiceNumber}?`)) return;
    matching.value = true;
    error.value = '';
    success.value = '';
    try {
        const response = await axios.post(`/erp/invoices/${selectedInvoice.value.id}/match`);
        selectedInvoice.value = response.data.data;
        await loadData();
        if (selectedInvoice.value.status === 'matched') {
            success.value = `${invoiceNumber} passed 3-way matching.`;
        } else {
            error.value = `${invoiceNumber} has matching discrepancies. Review the invoice lines below.`;
        }
    } catch (err) {
        error.value = errorMessage(err);
    } finally {
        matching.value = false;
    }
}

async function viewInvoice(invoice) {
    error.value = '';
    success.value = '';
    try {
        const response = await axios.get(`/erp/invoices/${invoice.id}`);
        selectedInvoice.value = response.data.data;
        showRejectForm.value = false;
        rejectionReason.value = '';
        activeTab.value = 'details';
    } catch (err) {
        error.value = errorMessage(err);
    }
}

async function invoiceAction(action, payload = {}) {
    if (!selectedInvoice.value || processingDecision.value) return;
    processingDecision.value = true;
    error.value = '';
    success.value = '';
    try {
        const invoiceId = selectedInvoice.value.id;
        const response = await axios.post(`/erp/invoices/${invoiceId}/${action}`, payload);
        selectedInvoice.value = response.data.data;
        showRejectForm.value = false;
        rejectionReason.value = '';
        await loadData();
        success.value = response.data.message || `Invoice ${action} completed successfully.`;
    } catch (err) {
        error.value = errorMessage(err);
    } finally {
        processingDecision.value = false;
    }
}

function submitForApproval() {
    if (!props.canCreate || selectedInvoice.value?.status !== 'matched') return;
    if (!window.confirm('Submit this matched invoice for Finance Manager approval?')) return;
    invoiceAction('submit');
}

function approveInvoice() {
    if (!props.canApprove || selectedInvoice.value?.status !== 'pending_approval') return;
    if (!window.confirm('Approve this supplier invoice for payment processing?')) return;
    invoiceAction('approve');
}

function rejectInvoice() {
    if (!props.canApprove || selectedInvoice.value?.status !== 'pending_approval') return;
    const reason = rejectionReason.value.trim();
    if (reason.length < 5) {
        error.value = 'Please provide a rejection reason of at least 5 characters.';
        return;
    }
    invoiceAction('reject', { reason });
}

onMounted(loadData);
</script>

<template>
    <section class="invoice-page">
        <div class="page-header">
            <div>
                <h1>Supplier Invoices</h1>
                <p>Create supplier invoice drafts, match receipts, and review approval history.</p>
            </div>
            <button v-if="canCreate" type="button" class="primary" @click="startNewInvoice">
                + New Supplier Invoice
            </button>
        </div>

        <div v-if="error" class="alert error">{{ error }}</div>
        <div v-if="success" class="alert success">{{ success }}</div>

        <div class="tabs">
            <button type="button" :class="{ active: activeTab === 'list' }" @click="activeTab = 'list'">
                Invoice List
            </button>
            <button v-if="canCreate" type="button" :class="{ active: activeTab === 'create' }" @click="activeTab = 'create'">
                Create Invoice
            </button>
            <button v-if="selectedInvoice" type="button" :class="{ active: activeTab === 'details' }" @click="activeTab = 'details'">
                Invoice Details
            </button>
        </div>

        <div v-if="activeTab === 'list'" class="panel">
            <div class="panel-heading">
                <h2>Supplier Invoice Register</h2>
                <button type="button" :disabled="loading" @click="loadData">Refresh</button>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>ERP Invoice</th>
                            <th>Supplier Invoice</th>
                            <th>Supplier</th>
                            <th>PO</th>
                            <th>Status</th>
                            <th class="number">Total (₹)</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="invoice in invoices" :key="invoice.id">
                            <td>{{ invoice.invoice_number }}</td>
                            <td>{{ invoice.supplier_invoice_number }}</td>
                            <td>{{ invoice.supplier?.name }}</td>
                            <td>{{ invoice.purchase_order?.po_number }}</td>
                            <td><span class="status">{{ invoice.status }}</span></td>
                            <td class="number">{{ money(invoice.total_amount) }}</td>
                            <td><button type="button" @click="viewInvoice(invoice)">View</button></td>
                        </tr>
                        <tr v-if="!invoices.length">
                            <td colspan="7" class="empty">No supplier invoices yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="hint">Showing the first page of invoices (up to 20 records).</p>
        </div>

        <div v-else-if="activeTab === 'create' && canCreate" class="panel">
            <h2>Create Supplier Invoice Draft</h2>
            <form @submit.prevent="createInvoice">
                <div class="form-grid">
                    <label>
                        Approved Purchase Order
                        <select v-model="form.purchase_order_id" required @change="selectPurchaseOrder">
                            <option value="">Select Purchase Order</option>
                            <option v-for="po in purchaseOrders" :key="po.id" :value="po.id">
                                {{ po.po_number }} — {{ po.supplier_name }}
                            </option>
                        </select>
                    </label>
                    <label>
                        Supplier
                        <input :value="selectedPo?.supplier_name || ''" readonly />
                    </label>
                    <label>
                        Supplier Invoice Number
                        <input v-model="form.supplier_invoice_number" maxlength="100" placeholder="ABC/INV/001" required />
                    </label>
                    <label>
                        Invoice Date
                        <input v-model="form.invoice_date" type="date" required />
                    </label>
                    <label>
                        Due Date
                        <input v-model="form.due_date" type="date" :min="form.invoice_date" />
                    </label>
                    <label>
                        Tax Amount (₹)
                        <input v-model="form.tax_amount" type="number" min="0" step="0.01" />
                    </label>
                </div>

                <h3>Invoice Items</h3>
                <p class="hint">Quantities and prices are prefilled from the PO. Adjust them to match the supplier invoice. They have not yet passed 3-way matching.</p>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Include</th>
                                <th>Item</th>
                                <th class="number">PO Qty</th>
                                <th>Invoice Qty</th>
                                <th>Unit Price (₹)</th>
                                <th class="number">Amount (₹)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in form.items" :key="item.purchase_order_item_id">
                                <td><input v-model="item.selected" type="checkbox" /></td>
                                <td>{{ item.item_code }} — {{ item.item_name }}</td>
                                <td class="number">{{ quantity(item.ordered_quantity) }}</td>
                                <td>
                                    <input v-model="item.invoiced_quantity" type="number" min="0.001" step="0.001" :disabled="!item.selected" required />
                                </td>
                                <td>
                                    <input v-model="item.unit_price" type="number" min="0" step="0.01" :disabled="!item.selected" required />
                                </td>
                                <td class="number">
                                    {{ item.selected ? money(Number(item.invoiced_quantity || 0) * Number(item.unit_price || 0)) : '0.00' }}
                                </td>
                            </tr>
                            <tr v-if="!form.items.length">
                                <td colspan="6" class="empty">Select an approved PO to load its items.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="totals">
                    <p>Subtotal: <strong>₹{{ money(subtotal) }}</strong></p>
                    <p>Tax: <strong>₹{{ money(form.tax_amount) }}</strong></p>
                    <h3>Total: ₹{{ money(total) }}</h3>
                </div>

                <label class="remarks">
                    Remarks
                    <textarea v-model="form.remarks" rows="3" maxlength="2000" placeholder="Optional invoice notes"></textarea>
                </label>
                <div class="actions">
                    <button type="button" @click="activeTab = 'list'">Cancel</button>
                    <button type="submit" class="primary" :disabled="saving">
                        {{ saving ? 'Saving...' : 'Save Draft Invoice' }}
                    </button>
                </div>
            </form>
        </div>

        <div v-else-if="activeTab === 'details' && selectedInvoice" class="panel">
            <div class="panel-heading">
                <h2>{{ selectedInvoice.invoice_number }}</h2>
                <button
                    v-if="canCreate && ['draft', 'mismatch'].includes(selectedInvoice.status)"
                    type="button"
                    class="primary"
                    :disabled="matching"
                    @click="runThreeWayMatch"
                >
                    {{ matching ? 'Matching...' : 'Run 3-Way Match' }}
                </button>
            </div>

            <div class="approval-actions">
                <button
                    v-if="canCreate && selectedInvoice.status === 'matched'"
                    type="button"
                    class="primary"
                    :disabled="processingDecision"
                    @click="submitForApproval"
                >
                    {{ processingDecision ? 'Submitting...' : 'Submit for Approval' }}
                </button>
                <template v-if="canApprove && selectedInvoice.status === 'pending_approval'">
                    <button type="button" class="approve-button" :disabled="processingDecision" @click="approveInvoice">
                        Approve Invoice
                    </button>
                    <button type="button" class="reject-button" :disabled="processingDecision" @click="showRejectForm = !showRejectForm">
                        Reject Invoice
                    </button>
                </template>
            </div>

            <div v-if="showRejectForm && canApprove && selectedInvoice.status === 'pending_approval'" class="reject-panel">
                <label>
                    Rejection Reason
                    <textarea v-model="rejectionReason" rows="3" maxlength="2000" placeholder="Explain why the invoice is rejected"></textarea>
                </label>
                <button type="button" class="reject-button" :disabled="processingDecision" @click="rejectInvoice">
                    Confirm Rejection
                </button>
            </div>

            <div class="detail-grid">
                <p><strong>Supplier:</strong> {{ selectedInvoice.supplier?.name }}</p>
                <p><strong>Supplier Invoice:</strong> {{ selectedInvoice.supplier_invoice_number }}</p>
                <p><strong>Purchase Order:</strong> {{ selectedInvoice.purchase_order?.po_number }}</p>
                <p><strong>Status:</strong> {{ selectedInvoice.status }}</p>
                <p><strong>Invoice Date:</strong> {{ String(selectedInvoice.invoice_date).slice(0, 10) }}</p>
                <p><strong>Total:</strong> ₹{{ money(selectedInvoice.total_amount) }}</p>
            </div>

            <h3>Invoice Lines</h3>
            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th class="number">Quantity</th>
                            <th class="number">Unit Price</th>
                            <th class="number">Line Amount</th>
                            <th>Match Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="item in selectedInvoice.items" :key="item.id">
                            <td>{{ item.item?.code }} — {{ item.item?.name }}</td>
                            <td class="number">{{ quantity(item.invoiced_quantity) }}</td>
                            <td class="number">₹{{ money(item.unit_price) }}</td>
                            <td class="number">₹{{ money(item.line_amount) }}</td>
                            <td>
                                <span :class="{ 'match-success': item.match_status === 'matched', 'match-error': item.match_status === 'mismatch' }">
                                    {{ item.match_status }}
                                </span>
                                <p v-if="item.mismatch_reason" class="mismatch-reason">{{ item.mismatch_reason }}</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <h3>Audit History</h3>
            <div v-for="activity in selectedInvoice.activities" :key="activity.id" class="activity">
                <strong>{{ activity.action }}</strong>
                <span>{{ activity.user?.name }} · {{ activity.created_at }}</span>
            </div>
            <p class="hint">Matched invoices must be submitted and approved by an authorized Finance Manager before payment processing.</p>
        </div>
    </section>
</template>

<style scoped>
.invoice-page {
    padding: 24px;
    color: #1e293b;
}

.page-header,
.panel-heading,
.actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.page-header {
    margin-bottom: 22px;
}

.page-header h1 {
    margin: 0 0 6px;
    font-size: 26px;
}

.page-header p,
.hint {
    color: #64748b;
    font-size: 13px;
}

.panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}

.panel h2 {
    margin-top: 0;
}

.tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
}

button {
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 9px 14px;
    background: white;
    cursor: pointer;
}

button.primary,
.tabs button.active {
    background: #1d4ed8;
    border-color: #1d4ed8;
    color: white;
}

button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.form-grid,
.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

label {
    display: flex;
    flex-direction: column;
    gap: 7px;
    font-size: 13px;
    font-weight: 600;
}

input,
select,
textarea {
    width: 100%;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    padding: 9px;
    font: inherit;
}

input[type="checkbox"] {
    width: auto;
}

input[readonly] {
    background: #f1f5f9;
}

.table-scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin: 12px 0;
    font-size: 13px;
}

th,
td {
    border-bottom: 1px solid #e2e8f0;
    padding: 11px;
    text-align: left;
}

th {
    background: #f8fafc;
}

td input[type="number"] {
    min-width: 105px;
}

.number {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.empty {
    padding: 26px;
    text-align: center;
    color: #64748b;
}

.totals {
    text-align: right;
    margin: 20px 0;
}

.totals h3 {
    color: #166534;
}

.remarks {
    margin-bottom: 20px;
}

.alert {
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 14px;
}

.alert.error {
    background: #fef2f2;
    color: #991b1b;
}

.alert.success {
    background: #f0fdf4;
    color: #166534;
}

.status {
    display: inline-block;
    border-radius: 20px;
    padding: 5px 10px;
    background: #e2e8f0;
}

.activity {
    display: flex;
    justify-content: space-between;
    padding: 10px;
    border-bottom: 1px solid #e2e8f0;
    gap: 12px;
}

@media (max-width: 700px) {
    .form-grid,
    .detail-grid {
        grid-template-columns: 1fr;
    }

    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }
}

.match-success {
    color: #15803d;
    font-weight: 700;
}

.match-error {
    color: #b91c1c;
    font-weight: 700;
}

.mismatch-reason {
    color: #b91c1c;
    font-size: 12px;
    margin-top: 6px;
    line-height: 1.5;
}

.approval-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin: 16px 0;
}

.approve-button {
    background: #15803d;
    border-color: #15803d;
    color: white;
}

.reject-button {
    background: #b91c1c;
    border-color: #b91c1c;
    color: white;
}

.reject-panel {
    padding: 16px;
    border: 1px solid #fecaca;
    border-radius: 8px;
    background: #fff7f7;
    margin-bottom: 16px;
}

.reject-panel button {
    margin-top: 12px;
}

</style>