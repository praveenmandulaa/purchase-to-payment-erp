<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
    user: { type: Object, required: true },
});

const roles = computed(() => props.user.roles ?? []);

const canCreate = computed(() =>
    roles.value.includes('qc-inspector') ||
    roles.value.includes('admin')
);

const canSubmit = computed(() =>
    roles.value.includes('qc-inspector') ||
    roles.value.includes('admin')
);

const canDecide = computed(() =>
    roles.value.includes('qc-manager') ||
    roles.value.includes('admin')
);

const inspections = ref([]);
const eligibleGrns = ref([]);
const selectedGrnId = ref('');
const inspectionDate = ref(new Date().toLocaleDateString('en-CA'));
const remarks = ref('');
const quantities = ref({});
const showForm = ref(false);
const selectedInspection = ref(null);
const decisionReason = ref('');
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const message = ref('');

const selectedGrn = computed(() =>
    eligibleGrns.value.find(
        grn => Number(grn.id) === Number(selectedGrnId.value)
    )
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
            firstError ||
            data.message ||
            `Request failed (${response.status})`
        );
    }

    return data;
}

async function loadData() {
    loading.value = true;
    error.value = '';

    try {
        const [inspectionData, grnData] = await Promise.all([
            api('/erp/qc/inspections'),
            api('/erp/qc/eligible-grns'),
        ]);

        inspections.value = inspectionData.data;
        eligibleGrns.value = grnData;
    } catch (e) {
        error.value = e.message;
    } finally {
        loading.value = false;
    }
}

function startCreate() {
    selectedGrnId.value = '';
    inspectionDate.value = new Date().toLocaleDateString('en-CA');
    remarks.value = '';
    quantities.value = {};
    error.value = '';
    message.value = '';
    showForm.value = true;
}

function onGrnChange() {
    quantities.value = {};

    for (const line of selectedGrn.value?.items ?? []) {
        quantities.value[line.id] = {
            accepted_quantity: Number(line.received_quantity),
            rejected_quantity: 0,
            rejection_reason: '',
            remarks: '',
        };
    }
}

function quantity(value) {
    return Number(value).toLocaleString('en-IN', {
        maximumFractionDigits: 3,
    });
}

function lineIsValid(line) {
    const entry = quantities.value[line.id];

    if (!entry) return false;

    const accepted = Number(entry.accepted_quantity);
    const rejected = Number(entry.rejected_quantity);

    return Number.isInteger(accepted) &&
        Number.isInteger(rejected) &&
        accepted >= 0 &&
        rejected >= 0 &&
        accepted + rejected === Number(line.received_quantity) &&
        (rejected === 0 ||
            entry.rejection_reason.trim().length >= 5);
}

async function createInspection() {
    error.value = '';
    message.value = '';

    if (!selectedGrn.value) {
        error.value = 'Select a GRN.';
        return;
    }

    if (!selectedGrn.value.items.every(lineIsValid)) {
        error.value =
            'For every item, accepted + rejected must equal received. ' +
            'Rejected quantities require a reason of at least 5 characters.';
        return;
    }

    saving.value = true;

    try {
        const result = await api('/erp/qc/inspections', 'POST', {
            goods_receipt_id: Number(selectedGrnId.value),
            inspection_date: inspectionDate.value,
            remarks: remarks.value || null,
            items: selectedGrn.value.items.map(line => ({
                goods_receipt_item_id: line.id,
                ...quantities.value[line.id],
            })),
        });

        showForm.value = false;
        message.value =
            `${result.data.inspection_number} created as draft.`;

        await loadData();
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function viewInspection(id) {
    error.value = '';

    try {
        selectedInspection.value =
            await api(`/erp/qc/inspections/${id}`);
        decisionReason.value = '';
    } catch (e) {
        error.value = e.message;
    }
}

async function performAction(action) {
    if (!selectedInspection.value) return;

    error.value = '';
    message.value = '';

    if (action === 'reject' &&
        decisionReason.value.trim().length < 5) {
        error.value =
            'Provide a rejection reason of at least 5 characters.';
        return;
    }

    saving.value = true;

    try {
        const id = selectedInspection.value.id;

        const result = await api(
            `/erp/qc/inspections/${id}/${action}`,
            'POST',
            action === 'submit'
                ? {}
                : { reason: decisionReason.value || null }
        );

        selectedInspection.value = result.data;
        message.value = result.message;
        decisionReason.value = '';

        await loadData();
    } catch (e) {
        error.value = e.message;
    } finally {
        saving.value = false;
    }
}

onMounted(loadData);
</script>

<template>
    <section class="qc-page">
        <div class="toolbar">
            <div>
                <h2>Quality Control</h2>
                <p>Inspect received materials and approve QC results.</p>
            </div>

            <button
                v-if="canCreate && !showForm"
                class="primary"
                @click="startCreate"
            >
                + New Inspection
            </button>
        </div>

        <p v-if="error" class="alert error">{{ error }}</p>
        <p v-if="message" class="alert success">{{ message }}</p>

        <form
            v-if="showForm && canCreate"
            class="panel"
            @submit.prevent="createInspection"
        >
            <h3>Create QC Inspection</h3>

            <div class="form-grid">
                <label>
                    Goods Receipt Note
                    <select
                        v-model="selectedGrnId"
                        required
                        @change="onGrnChange"
                    >
                        <option value="">Select GRN</option>
                        <option
                            v-for="grn in eligibleGrns"
                            :key="grn.id"
                            :value="grn.id"
                        >
                            {{ grn.grn_number }} —
                            {{ grn.purchase_order?.po_number }}
                        </option>
                    </select>
                </label>

                <label>
                    Inspection Date
                    <input
                        v-model="inspectionDate"
                        type="date"
                        required
                    />
                </label>
            </div>

            <div v-if="selectedGrn" class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Received</th>
                            <th>Accepted</th>
                            <th>Rejected</th>
                            <th>Rejection Reason</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="line in selectedGrn.items"
                            :key="line.id"
                        >
                            <td>{{ line.item?.name }}</td>
                            <td>{{ quantity(line.received_quantity) }}</td>
                            <td>
                                <input
                                    v-model.number="
                                        quantities[line.id].accepted_quantity
                                    "
                                    type="number"
                                    min="0"
                                    step="1"
                                    :max="line.received_quantity"
                                />
                            </td>
                            <td>
                                <input
                                    v-model.number="
                                        quantities[line.id].rejected_quantity
                                    "
                                    type="number"
                                    min="0"
                                    step="1"
                                    :max="line.received_quantity"
                                />
                            </td>
                            <td>
                                <input
                                    v-model="
                                        quantities[line.id].rejection_reason
                                    "
                                    type="text"
                                    maxlength="1000"
                                    placeholder="Reason if rejected"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <label class="full-width">
                Inspection Remarks
                <textarea
                    v-model="remarks"
                    rows="3"
                    maxlength="2000"
                ></textarea>
            </label>

            <div class="actions">
                <button
                    type="submit"
                    class="primary"
                    :disabled="saving"
                >
                    {{ saving ? 'Saving...' : 'Save Draft Inspection' }}
                </button>

                <button
                    type="button"
                    @click="showForm = false"
                >
                    Cancel
                </button>
            </div>
        </form>

        <div class="panel">
            <h3>Inspection History</h3>

            <p v-if="loading">Loading inspections...</p>

            <div v-else class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Inspection</th>
                            <th>GRN</th>
                            <th>Inspector</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="inspection in inspections"
                            :key="inspection.id"
                        >
                            <td>
                                <strong>
                                    {{ inspection.inspection_number }}
                                </strong>
                            </td>
                            <td>
                                {{ inspection.goods_receipt?.grn_number }}
                            </td>
                            <td>{{ inspection.inspector?.name }}</td>
                            <td>{{ inspection.status }}</td>
                            <td>
                                <button
                                    @click="viewInspection(inspection.id)"
                                >
                                    View
                                </button>
                            </td>
                        </tr>

                        <tr v-if="inspections.length === 0">
                            <td colspan="5">
                                No QC inspections recorded yet.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="selectedInspection" class="panel">
            <div class="toolbar">
                <h3>
                    {{ selectedInspection.inspection_number }}
                </h3>

                <button @click="selectedInspection = null">
                    Close
                </button>
            </div>

            <p>
                <strong>Status:</strong>
                {{ selectedInspection.status }}
            </p>

            <p>
                <strong>GRN:</strong>
                {{ selectedInspection.goods_receipt?.grn_number }}
            </p>

            <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Accepted</th>
                            <th>Rejected</th>
                            <th>Rejection Reason</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr
                            v-for="line in selectedInspection.items"
                            :key="line.id"
                        >
                            <td>
                                {{ line.goods_receipt_item?.item?.name }}
                            </td>
                            <td>{{ quantity(line.accepted_quantity) }}</td>
                            <td>{{ quantity(line.rejected_quantity) }}</td>
                            <td>{{ line.rejection_reason || '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div
                v-if="
                    selectedInspection.status === 'draft' &&
                    canSubmit &&
                    Number(selectedInspection.inspected_by) ===
                        Number(user.id)
                "
                class="actions"
            >
                <button
                    class="primary"
                    :disabled="saving"
                    @click="performAction('submit')"
                >
                    Submit for Approval
                </button>
            </div>

            <div
                v-if="
                    selectedInspection.status === 'pending_approval' &&
                    canDecide &&
                    Number(selectedInspection.inspected_by) !==
                        Number(user.id)
                "
            >
                <label class="full-width">
                    Decision Reason
                    <textarea
                        v-model="decisionReason"
                        rows="2"
                        placeholder="Required when rejecting inspection"
                    ></textarea>
                </label>

                <div class="actions">
                    <button
                        class="primary"
                        :disabled="saving"
                        @click="performAction('approve')"
                    >
                        Approve Inspection
                    </button>

                    <button
                        class="danger"
                        :disabled="saving"
                        @click="performAction('reject')"
                    >
                        Reject Inspection
                    </button>
                </div>
            </div>

            <h4>Audit History</h4>

            <ul class="audit-list">
                <li
                    v-for="activity in selectedInspection.activities"
                    :key="activity.id"
                >
                    <strong>{{ activity.action }}</strong>
                    — {{ activity.user?.name }}
                    <span v-if="activity.reason">
                        — {{ activity.reason }}
                    </span>
                </li>
            </ul>
        </div>
    </section>
</template>

<style scoped>
.qc-page {
    display: grid;
    gap: 20px;
    color: #0f172a;
}

.toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
}

.toolbar h2,
.toolbar h3 {
    margin: 0;
}

.toolbar p {
    color: #64748b;
    margin: 6px 0 0;
}

.panel {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 22px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

label {
    display: grid;
    gap: 7px;
    font-size: 13px;
    font-weight: 600;
}

input,
select,
textarea {
    width: 100%;
    min-width: 0;
    padding: 10px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: white;
    color: #0f172a;
    font: inherit;
}

input[type="number"] {
    min-width: 95px;
}

.full-width {
    margin-top: 18px;
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

th,
td {
    padding: 12px;
    border-bottom: 1px solid #e2e8f0;
}

th {
    background: #f8fafc;
    font-size: 12px;
    color: #475569;
}

.actions {
    display: flex;
    gap: 12px;
    margin-top: 20px;
    flex-wrap: wrap;
}

button {
    padding: 10px 14px;
    border: 1px solid #cbd5e1;
    background: white;
    border-radius: 7px;
    cursor: pointer;
}

.primary {
    background: #2563eb;
    border-color: #2563eb;
    color: white;
    font-weight: 600;
}

.danger {
    background: #dc2626;
    border-color: #dc2626;
    color: white;
}

button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.alert {
    padding: 12px;
    border-radius: 8px;
}

.error {
    background: #fef2f2;
    color: #991b1b;
}

.success {
    background: #f0fdf4;
    color: #166534;
}

.audit-list {
    padding-left: 20px;
    line-height: 1.9;
}

@media (max-width: 700px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
</style>
