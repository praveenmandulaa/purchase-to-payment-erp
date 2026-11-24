<script setup>
import { ref, computed, onMounted } from 'vue';

const props = defineProps({
    user: { type: Object, required: true },
});

const orders = ref([]);
const suppliers = ref([]);
const items = ref([]);

const error = ref('');
const message = ref('');
const loading = ref(false);
const editingId = ref(null);
const showForm = ref(false);

const isBuyer = computed(() =>
    props.user.roles.includes('buyer')
    || props.user.roles.includes('admin')
);

const isManager = computed(() =>
    props.user.roles.includes('purchase-manager')
    || props.user.roles.includes('admin')
);

function emptyForm() {
    return {
        supplier_id: '',
        expected_delivery_date: '',
        remarks: '',
        items: [
            { item_id: '', quantity: 1, unit_price: 0 },
        ],
    };
}

const form = ref(emptyForm());

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
        ...(body !== null
            ? { body: JSON.stringify(body) }
            : {}),
    });

    const data = await response.json();

    if (!response.ok) {
        const firstError = data.errors
            ? Object.values(data.errors).flat()[0]
            : null;

        throw new Error(
            firstError || data.message
            || `Request failed (${response.status})`
        );
    }

    return data;
}

async function loadOrders() {
    const result = await api('/erp/po');
    orders.value = result.data;
}

async function loadMasters() {
    const [supplierData, itemData] = await Promise.all([
        api('/erp/masters/suppliers'),
        api('/erp/masters/items'),
    ]);

    suppliers.value = supplierData;
    items.value = itemData;
}

function resetForm() {
    editingId.value = null;
    form.value = emptyForm();
    showForm.value = false;
}

function startCreate() {
    resetForm();
    showForm.value = true;
    error.value = '';
    message.value = '';
}

async function startEdit(po) {
    error.value = '';
    message.value = '';

    try {
        // Fetch fresh details rather than editing the list object.
        const data = await api(`/erp/po/${po.id}`);

        editingId.value = po.id;

        form.value = {
            supplier_id: data.supplier_id,
            expected_delivery_date:
                data.expected_delivery_date ?? '',
            remarks: data.remarks ?? '',
            items: data.items.map(line => ({
                item_id: line.item_id,
                quantity: Number(line.quantity),
                unit_price: Number(line.unit_price),
            })),
        };

        showForm.value = true;
    } catch (e) {
        error.value = e.message;
    }
}

function addLine() {
    form.value.items.push({
        item_id: '',
        quantity: 1,
        unit_price: 0,
    });
}

function removeLine(index) {
    if (form.value.items.length > 1) {
        form.value.items.splice(index, 1);
    }
}

const calculatedTotal = computed(() =>
    form.value.items.reduce(
        (sum, line) =>
            sum + Number(line.quantity || 0)
                * Number(line.unit_price || 0),
        0
    )
);

async function savePO() {
    error.value = '';
    message.value = '';
    loading.value = true;

    try {
        const payload = {
            supplier_id: Number(form.value.supplier_id),
            expected_delivery_date:
                form.value.expected_delivery_date || null,
            remarks: form.value.remarks || null,
            items: form.value.items.map(line => ({
                item_id: Number(line.item_id),
                quantity: Number(line.quantity),
                unit_price: Number(line.unit_price),
            })),
        };

        if (editingId.value) {
            await api(
                `/erp/po/${editingId.value}`,
                'PUT',
                payload
            );

            message.value = 'Purchase Order updated successfully';
        } else {
            const result = await api('/erp/po', 'POST', payload);
            message.value =
                `Purchase Order ${result.data.po_number} created`;
        }

        resetForm();
        await loadOrders();
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

async function cancelPO(po) {
    const reason = window.prompt(
        `Why do you want to cancel ${po.po_number}?`
    );

    if (reason === null) return;

    if (reason.trim().length < 5) {
        error.value =
            'Cancellation reason must contain at least 5 characters.';
        return;
    }

    error.value = '';
    message.value = '';

    if (!window.confirm(
        `Confirm cancellation of ${po.po_number}?`
    )) {
        return;
    }

    try {
        await api(
            `/erp/po/${po.id}/cancel`,
            'POST',
            { reason: reason.trim() }
        );

        message.value = `${po.po_number} cancelled successfully`;
        await loadOrders();
    } catch (e) {
        error.value = e.message;
    }
}

async function action(po, type) {
    error.value = '';
    message.value = '';

    let remarks = null;

    if (type === 'reject') {
        remarks = window.prompt('Enter rejection reason');

        if (!remarks?.trim()) return;
    }

    try {
        const body = remarks ? { remarks } : {};

        await api(
            `/erp/po/${po.id}/${type}`,
            'POST',
            body
        );

        message.value = `${po.po_number}: ${type} successful`;
        await loadOrders();
    } catch (e) {
        error.value = e.message;
    }
}

function canEdit(po) {
    return isBuyer.value
        && po.created_by === props.user.id
        && po.status === 'draft';
}

function canCancel(po) {
    return isBuyer.value
        && po.created_by === props.user.id
        && ['draft', 'pending_approval'].includes(po.status);
}

function canSubmit(po) {
    return isBuyer.value
        && po.created_by === props.user.id
        && po.status === 'draft';
}

function canApprove(po) {
    return isManager.value
        && po.created_by !== props.user.id
        && po.status === 'pending_approval';
}

function formatMoney(value) {
    return Number(value).toLocaleString('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
    });
}

function statusLabel(status) {
    return status.replaceAll('_', ' ')
        .replace(/\b\w/g, letter => letter.toUpperCase());
}

onMounted(async () => {
    try {
        await Promise.all([loadOrders(), loadMasters()]);
    } catch (e) {
        error.value = e.message;
    }
});
</script>

<template>
    <section class="po-module">
        <div v-if="isBuyer" class="module-toolbar">
            <button
                v-if="!showForm"
                class="primary-action"
                type="button"
                @click="startCreate"
            >
                + Create Purchase Order
            </button>
        </div>

        <form
            v-if="showForm && isBuyer"
            @submit.prevent="savePO"
        >
            <h3>
                {{ editingId
                    ? 'Edit Purchase Order'
                    : 'Create Purchase Order' }}
            </h3>

            <label for="po-supplier">Supplier</label>
            <select
                id="po-supplier"
                v-model="form.supplier_id"
                required
            >
                <option value="">Select supplier</option>
                <option
                    v-for="supplier in suppliers"
                    :key="supplier.id"
                    :value="supplier.id"
                >
                    {{ supplier.name }}
                </option>
            </select>

            <label for="po-delivery">Expected delivery</label>
            <input
                id="po-delivery"
                v-model="form.expected_delivery_date"
                type="date"
            />

            <label for="po-remarks">Remarks</label>
            <textarea
                id="po-remarks"
                v-model="form.remarks"
                rows="3"
                maxlength="1000"
                placeholder="Optional purchasing notes"
            ></textarea>

            <h3>PO Items</h3>

            <div
                v-for="(line, index) in form.items"
                :key="index"
                class="line"
            >
                <select v-model="line.item_id" required>
                    <option value="">Select item</option>
                    <option
                        v-for="item in items"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }} ({{ item.uom }})
                    </option>
                </select>

                <input
                    v-model.number="line.quantity"
                    type="number"
                    min="1"
                    step="1"
                    placeholder="Quantity"
                    required
                />

                <input
                    v-model.number="line.unit_price"
                    type="number"
                    min="0.01"
                    step="0.01"
                    placeholder="Unit price"
                    required
                />

                <button
                    type="button"
                    @click="removeLine(index)"
                >
                    Remove
                </button>
            </div>

            <button type="button" @click="addLine">
                + Add Item
            </button>

            <div class="po-total">
                Estimated Total:
                <strong>{{ formatMoney(calculatedTotal) }}</strong>
            </div>

            <div class="form-actions">
                <button
                    type="submit"
                    class="primary-action"
                    :disabled="loading"
                >
                    {{ loading ? 'Saving...' :
                        editingId ? 'Save Changes' : 'Create PO' }}
                </button>

                <button type="button" @click="resetForm">
                    Close
                </button>
            </div>
        </form>

        <h3>Purchase Order List</h3>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Supplier</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <tr v-for="po in orders" :key="po.id">
                        <td>{{ po.po_number }}</td>
                        <td>{{ po.supplier?.name }}</td>
                        <td>{{ formatMoney(po.total_amount) }}</td>
                        <td>
                            <span
                                class="status-pill"
                                :class="`status-${po.status}`"
                            >
                                {{ statusLabel(po.status) }}
                            </span>
                        </td>
                        <td class="action-cell">
                            <button
                                v-if="canEdit(po)"
                                @click="startEdit(po)"
                            >
                                Edit
                            </button>

                            <button
                                v-if="canSubmit(po)"
                                @click="action(po, 'submit')"
                            >
                                Submit
                            </button>

                            <button
                                v-if="canCancel(po)"
                                class="danger-action"
                                @click="cancelPO(po)"
                            >
                                Cancel
                            </button>

                            <template v-if="canApprove(po)">
                                <button
                                    @click="action(po, 'approve')"
                                >
                                    Approve
                                </button>

                                <button
                                    class="danger-action"
                                    @click="action(po, 'reject')"
                                >
                                    Reject
                                </button>
                            </template>

                            <span
                                v-if="
                                    !canEdit(po) &&
                                    !canSubmit(po) &&
                                    !canCancel(po) &&
                                    !canApprove(po)
                                "
                                class="no-actions"
                            >
                                —
                            </span>
                        </td>
                    </tr>

                    <tr v-if="orders.length === 0">
                        <td colspan="5" class="empty-state">
                            No purchase orders found.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <p v-if="message" class="success-message">
            {{ message }}
        </p>

        <p v-if="error" class="error-message">
            {{ error }}
        </p>
    </section>
</template>


<style scoped>
section {
    width: 100%;
    margin: 0;
    color: #1e293b;
}

h2 {
    margin: 0 0 24px;
    font-size: 20px;
    font-weight: 700;
}

h3 {
    margin: 0 0 18px;
    font-size: 17px;
    font-weight: 700;
    color: #1e293b;
}

form {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding-bottom: 30px;
    margin-bottom: 30px;
    border-bottom: 1px solid #e2e8f0;
}

label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin-bottom: -5px;
}

input,
select {
    width: 100%;
    min-height: 43px;
    padding: 10px 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    color: #1e293b;
    font-size: 13px;
    outline: none;
}

input:focus,
select:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.line {
    display: grid;
    grid-template-columns: minmax(180px, 2fr) 1fr 1fr auto;
    gap: 12px;
    align-items: center;
    padding: 16px;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
}

button {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: fit-content;
    min-height: 40px;
    padding: 9px 18px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    background: #ffffff;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s, border-color 0.2s;
}

button:hover {
    background: #f1f5f9;
    border-color: #94a3b8;
}

button[type="submit"] {
    background: #2563eb;
    color: #ffffff;
    border-color: #2563eb;
}

button[type="submit"]:hover {
    background: #1d4ed8;
}

button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 16px;
    font-size: 13px;
}

th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
    text-align: left;
    padding: 14px;
    border-bottom: 1px solid #e2e8f0;
}

td {
    padding: 14px;
    border-bottom: 1px solid #e2e8f0;
    color: #334155;
}

tbody tr:hover {
    background: #f8fafc;
}

td button {
    min-height: 34px;
    padding: 6px 12px;
    margin: 3px;
    background: #eff6ff;
    border-color: #bfdbfe;
    color: #1d4ed8;
}

td button:hover {
    background: #dbeafe;
}

p {
    font-size: 13px;
    line-height: 1.5;
}

@media (max-width: 768px) {
    .line {
        grid-template-columns: 1fr;
    }

    table {
        min-width: 650px;
    }
}

.module-toolbar {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 20px;
}

.primary-action {
    background: #2563eb;
    color: white;
    border-color: #2563eb;
}

.primary-action:hover {
    background: #1d4ed8;
    color: white;
}

textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    font: inherit;
    resize: vertical;
}

.po-total {
    display: flex;
    justify-content: flex-end;
    gap: 16px;
    font-size: 15px;
    padding: 12px 0;
}

.form-actions {
    display: flex;
    gap: 12px;
}

.table-wrap {
    overflow-x: auto;
}

.action-cell {
    white-space: nowrap;
}

.danger-action {
    color: #b91c1c;
    background: #fef2f2;
    border-color: #fecaca;
}

.danger-action:hover {
    background: #fee2e2;
}

.status-pill {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
    background: #f1f5f9;
    color: #475569;
}

.status-draft {
    background: #f1f5f9;
    color: #475569;
}

.status-pending_approval {
    background: #fef3c7;
    color: #92400e;
}

.status-approved {
    background: #dcfce7;
    color: #166534;
}

.status-rejected,
.status-cancelled {
    background: #fee2e2;
    color: #991b1b;
}

.success-message {
    color: #166534;
    background: #f0fdf4;
    padding: 12px;
    border-radius: 8px;
}

.error-message {
    color: #b91c1c;
    background: #fef2f2;
    padding: 12px;
    border-radius: 8px;
}

.no-actions {
    color: #94a3b8;
}

.empty-state {
    text-align: center;
    color: #64748b;
    padding: 30px;
}
</style>
