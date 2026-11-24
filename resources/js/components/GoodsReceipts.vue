
<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    user: { type: Object, required: true },
});

const receipts = ref([]);
const warehouses = ref([]);
const purchaseOrders = ref([]);

const selectedPoId = ref('');
const selectedWarehouseId = ref('');
const receiptDate = ref(new Date().toLocaleDateString('en-CA'));
const deliveryReference = ref('');
const remarks = ref('');
const quantities = ref({});

const loading = ref(false);
const saving = ref(false);
const error = ref('');
const message = ref('');
const showForm = ref(false);
const selectedReceipt = ref(null);

const canCreate = computed(() =>
    props.user.roles.includes('warehouse-officer') ||
    props.user.roles.includes('admin')
);

const selectedPO = computed(() =>
    purchaseOrders.value.find(
        po => Number(po.id) === Number(selectedPoId.value)
    )
);

const eligibleLines = computed(() =>
    selectedPO.value?.items.filter(
        line => Number(line.outstanding_quantity) > 0
    ) ?? []
);

const csrf = () =>
    document.querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

async function api(url, method = 'GET', body = null) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
        },
        ...(body !== null ? { body: JSON.stringify(body) } : {}),
    });

    const data = await response.json();

    if (!response.ok) {
        const validationError = data.errors
            ? Object.values(data.errors).flat()[0]
            : null;

        throw new Error(
            validationError ||
            data.message ||
            `HTTP ${response.status}`
        );
    }

    return data;
}

async function loadData() {
    loading.value = true;

    try {
        const [grnData, warehouseData, poData] =
            await Promise.all([
                api('/erp/grn'),
                api('/erp/grn/warehouses'),
                api('/erp/grn/approved-pos'),
            ]);

        receipts.value = grnData.data;
        warehouses.value = warehouseData;
        purchaseOrders.value = poData;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

function selectPO() {
    quantities.value = {};

    for (const line of eligibleLines.value) {
        quantities.value[line.id] = 0;
    }
}

function startCreate() {
    selectedPoId.value = '';
    selectedWarehouseId.value = '';
    deliveryReference.value = '';
    remarks.value = '';
    receiptDate.value = new Date().toLocaleDateString('en-CA');
    quantities.value = {};
    error.value = '';
    message.value = '';
    showForm.value = true;
}

function closeForm() {
    showForm.value = false;
}

async function saveGRN() {
    error.value = '';
    message.value = '';

    const lines = eligibleLines.value
        .filter(line => Number(quantities.value[line.id]) > 0)
        .map(line => ({
            purchase_order_item_id: line.id,
            received_quantity: Number(quantities.value[line.id]),
        }));

    if (lines.length === 0) {
        error.value = 'Enter a received quantity for at least one item.';
        return;
    }

    for (const line of eligibleLines.value) {
        const qty = Number(quantities.value[line.id] || 0);

        if (!Number.isInteger(qty) || qty < 0 ||
            qty > Number(line.outstanding_quantity)) {
            error.value =
                `Invalid received quantity for ${line.item?.name}.`;
            return;
        }
    }

    saving.value = true;

    try {
        const result = await api('/erp/grn', 'POST', {
            purchase_order_id: Number(selectedPoId.value),
            warehouse_id: Number(selectedWarehouseId.value),
            receipt_date: receiptDate.value,
            delivery_reference: deliveryReference.value.trim(),
            remarks: remarks.value || null,
            items: lines,
        });

        message.value =
            `${result.data.grn_number} created successfully.`;

        showForm.value = false;
        await loadData();
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function viewReceipt(id) {
    error.value = '';

    try {
        selectedReceipt.value = await api(`/erp/grn/${id}`);
    } catch (e) {
        error.value = e.message;
    }
}

function formatQuantity(value) {
    return Number(value).toLocaleString('en-IN', {
        maximumFractionDigits: 3,
    });
}

onMounted(loadData);
</script>

<template>
    <section class="grn-page">
        <div class="page-toolbar">
            <div>
                <h2>Goods Receipt Notes</h2>
                <p>Record supplier deliveries against approved purchase orders.</p>
            </div>

            <button
                v-if="canCreate && !showForm"
                class="primary-btn"
                @click="startCreate"
            >
                + New GRN
            </button>
        </div>

        <p v-if="error" class="alert error">{{ error }}</p>
        <p v-if="message" class="alert success">{{ message }}</p>

        <form
            v-if="showForm && canCreate"
            class="panel"
            @submit.prevent="saveGRN"
        >
            <h3>Create Goods Receipt</h3>

            <div class="form-grid">
                <label>
                    Approved Purchase Order
                    <select
                        v-model="selectedPoId"
                        required
                        @change="selectPO"
                    >
                        <option value="">Select PO</option>
                        <option
                            v-for="po in purchaseOrders"
                            :key="po.id"
                            :value="po.id"
                        >
                            {{ po.po_number }} —
                            {{ po.supplier?.name }}
                        </option>
                    </select>
                </label>

                <label>
                    Receiving Warehouse
                    <select v-model="selectedWarehouseId" required>
                        <option value="">Select warehouse</option>
                        <option
                            v-for="warehouse in warehouses"
                            :key="warehouse.id"
                            :value="warehouse.id"
                        >
                            {{ warehouse.code }} —
                            {{ warehouse.name }}
                        </option>
                    </select>
                </label>

                <label>
                    Receipt Date
                    <input
                        v-model="receiptDate"
                        type="date"
                        required
                    />
                </label>

                <label>
                    Supplier Delivery Reference
                    <input
                        v-model="deliveryReference"
                        type="text"
                        maxlength="100"
                        placeholder="e.g. DC-2026-001"
                        required
                    />
                </label>
            </div>

            <div v-if="selectedPO" class="po-summary">
                <strong>Supplier:</strong>
                {{ selectedPO.supplier?.name }}
            </div>

            <div v-if="selectedPO" class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Ordered</th>
                            <th>Received</th>
                            <th>Outstanding</th>
                            <th>Receive Now</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="line in eligibleLines"
                            :key="line.id"
                        >
                            <td>
                                {{ line.item?.name }}
                                <small>({{ line.item?.uom }})</small>
                            </td>
                            <td>{{ formatQuantity(line.quantity) }}</td>
                            <td>
                                {{ formatQuantity(line.received_quantity) }}
                            </td>
                            <td>
                                <strong>
                                    {{ formatQuantity(line.outstanding_quantity) }}
                                </strong>
                            </td>
                            <td>
                                <input
                                    v-model.number="quantities[line.id]"
                                    type="number"
                                    min="0"
                                    step="1"
                                    :max="line.outstanding_quantity"
                                    class="quantity-input"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <label class="remarks-field">
                Receiving Remarks
                <textarea
                    v-model="remarks"
                    rows="3"
                    maxlength="1000"
                    placeholder="Optional delivery notes"
                ></textarea>
            </label>

            <div class="form-actions">
                <button
                    class="primary-btn"
                    type="submit"
                    :disabled="saving"
                >
                    {{ saving ? 'Saving...' : 'Create GRN' }}
                </button>

                <button type="button" @click="closeForm">
                    Cancel Form
                </button>
            </div>
        </form>

        <div class="panel">
            <h3>Goods Receipt History</h3>

            <p v-if="loading">Loading receipts...</p>

            <div v-else class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>GRN Number</th>
                            <th>PO Number</th>
                            <th>Warehouse</th>
                            <th>Delivery Ref</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="grn in receipts"
                            :key="grn.id"
                        >
                            <td><strong>{{ grn.grn_number }}</strong></td>
                            <td>{{ grn.purchase_order?.po_number }}</td>
                            <td>{{ grn.warehouse?.name }}</td>
                            <td>{{ grn.delivery_reference }}</td>
                            <td>{{ grn.status }}</td>
                            <td>
                                <button @click="viewReceipt(grn.id)">
                                    View
                                </button>
                            </td>
                        </tr>

                        <tr v-if="receipts.length === 0">
                            <td colspan="6">
                                No goods receipts recorded yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="selectedReceipt" class="panel">
            <div class="page-toolbar">
                <h3>{{ selectedReceipt.grn_number }} — Details</h3>
                <button @click="selectedReceipt = null">
                    Close
                </button>
            </div>

            <p>
                <strong>PO:</strong>
                {{ selectedReceipt.purchase_order?.po_number }}
            </p>
            <p>
                <strong>Received by:</strong>
                {{ selectedReceipt.receiver?.name }}
            </p>
            <p>
                <strong>Warehouse:</strong>
                {{ selectedReceipt.warehouse?.name }}
            </p>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>QC Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="line in selectedReceipt.items"
                            :key="line.id"
                        >
                            <td>{{ line.item?.name }}</td>
                            <td>
                                {{ formatQuantity(line.received_quantity) }}
                            </td>
                            <td>{{ line.qc_status }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>

<style scoped>
.grn-page {
    display: grid;
    gap: 20px;
    color: #0f172a;
}

.page-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
}

.page-toolbar h2,
.page-toolbar h3 {
    margin: 0;
}

.page-toolbar p {
    color: #64748b;
    margin: 6px 0 0;
}

.panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 22px;
}

.panel h3 {
    margin-top: 0;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

label {
    display: grid;
    gap: 7px;
    font-weight: 600;
    font-size: 13px;
}

input, select, textarea {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    font: inherit;
    background: white;
    color: #0f172a;
}

.quantity-input {
    max-width: 130px;
}

.primary-btn {
    background: #2563eb;
    color: white;
    border: 1px solid #2563eb;
    padding: 11px 16px;
    border-radius: 7px;
    cursor: pointer;
    font-weight: 600;
}

button:not(.primary-btn) {
    padding: 9px 13px;
    border: 1px solid #cbd5e1;
    background: white;
    border-radius: 7px;
    cursor: pointer;
}

.table-scroll {
    overflow-x: auto;
    margin-top: 16px;
}

table {
    width: 100%;
    border-collapse: collapse;
    text-align: left;
}

th, td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
}

th {
    background: #f8fafc;
    font-size: 12px;
    color: #475569;
}

small {
    color: #64748b;
}

.po-summary {
    margin-top: 18px;
    padding: 12px;
    background: #eff6ff;
    border-radius: 7px;
}

.remarks-field {
    margin-top: 20px;
}

.form-actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
}

.alert {
    padding: 12px;
    border-radius: 8px;
}

.error {
    color: #991b1b;
    background: #fef2f2;
}

.success {
    color: #166534;
    background: #f0fdf4;
}

@media (max-width: 700px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
