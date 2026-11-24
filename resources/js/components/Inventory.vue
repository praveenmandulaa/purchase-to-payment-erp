<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const props = defineProps({
    canPost: {
        type: Boolean,
        default: false,
    },
});

const activeTab = ref('balances');
const balances = ref([]);
const eligibleGrns = ref([]);
const postings = ref([]);

const loading = ref(false);
const postingId = ref(null);
const error = ref('');
const success = ref('');

const tabs = [
    { key: 'balances', label: 'Stock Balances' },
    { key: 'eligible', label: 'Ready to Post' },
    { key: 'history', label: 'Posting History' },
];

const totalAvailable = computed(() =>
    balances.value.reduce(
        (sum, row) =>
            sum + Number(row.available_quantity || 0),
        0
    )
);

const totalRejected = computed(() =>
    balances.value.reduce(
        (sum, row) =>
            sum + Number(row.rejected_quantity || 0),
        0
    )
);

function quantity(value) {
    return Number(value || 0).toLocaleString('en-IN', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
}

function readableDate(value) {
    if (!value) return '-';

    return String(value).replace('T', ' ').slice(0, 19);
}

function getErrorMessage(err) {
    const errors = err.response?.data?.errors;

    if (errors) {
        const first = Object.values(errors).flat()[0];
        if (first) return String(first);
    }

    return err.response?.data?.message
        || 'An unexpected error occurred.';
}

async function loadData() {
    loading.value = true;
    error.value = '';

    try {
        const requests = [
            axios.get('/erp/inventory/balances'),
            axios.get('/erp/inventory/postings'),
        ];

        if (props.canPost) {
            requests.push(
                axios.get('/erp/inventory/eligible-grns')
            );
        }

        const results = await Promise.all(requests);

        balances.value = results[0].data.data || [];
        postings.value = results[1].data.data || [];

        eligibleGrns.value = props.canPost
            ? results[2].data.data || []
            : [];
    } catch (err) {
        error.value = getErrorMessage(err);
    } finally {
        loading.value = false;
    }
}

async function postInventory(grn) {
    if (!props.canPost || postingId.value !== null) {
        return;
    }

    const confirmed = window.confirm(
        `Post eligible inventory for ${grn.grn_number}?\n\n` +
        'This will update warehouse stock balances ' +
        'and create permanent stock movements.'
    );

    if (!confirmed) return;

    postingId.value = grn.id;
    error.value = '';
    success.value = '';

    try {
        const response = await axios.post(
            `/erp/inventory/grn/${grn.id}/post`,
            {
                remarks: 'Posted from Inventory module',
            }
        );

        success.value =
            `${response.data.data.posting_number} posted successfully.`;

        await loadData();

        activeTab.value = 'history';
    } catch (err) {
        error.value = getErrorMessage(err);
    } finally {
        postingId.value = null;
    }
}

onMounted(loadData);
</script>

<template>
    <section class="inventory-page">
        <div class="inventory-heading">
            <div>
                <h1>Inventory Management</h1>
                <p>
                    Warehouse balances, goods receipt posting,
                    and stock movement history.
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

        <div v-if="error" class="message error">
            {{ error }}
        </div>

        <div v-if="success" class="message success">
            {{ success }}
        </div>

        <div class="inventory-summary">
            <div class="summary-card">
                <span>Warehouse / Item Balances</span>
                <strong>{{ balances.length }}</strong>
            </div>

            <div class="summary-card">
                <span>Total Available Quantity</span>
                <strong>{{ quantity(totalAvailable) }}</strong>
            </div>

            <div class="summary-card">
                <span>Total Rejected Quantity</span>
                <strong>{{ quantity(totalRejected) }}</strong>
            </div>
        </div>

        <div class="inventory-tabs">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                v-show="tab.key !== 'eligible' || canPost"
                type="button"
                :class="{ active: activeTab === tab.key }"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
                <span
                    v-if="tab.key === 'eligible' && canPost"
                    class="tab-count"
                >
                    {{ eligibleGrns.length }}
                </span>
            </button>
        </div>

        <div class="inventory-panel">
            <template v-if="activeTab === 'balances'">
                <h2>Warehouse Stock Balances</h2>

                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Warehouse</th>
                                <th>Item Code</th>
                                <th>Item Name</th>
                                <th>UOM</th>
                                <th class="number">Available</th>
                                <th class="number">Rejected</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="row in balances"
                                :key="row.id"
                            >
                                <td>{{ row.warehouse }}</td>
                                <td>{{ row.item_code }}</td>
                                <td>{{ row.item_name }}</td>
                                <td>{{ row.uom }}</td>
                                <td class="number">
                                    {{ quantity(row.available_quantity) }}
                                </td>
                                <td class="number rejected">
                                    {{ quantity(row.rejected_quantity) }}
                                </td>
                            </tr>

                            <tr v-if="!balances.length">
                                <td colspan="6" class="empty">
                                    No stock balances yet.
                                    Post an eligible GRN to create stock.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <template v-else-if="activeTab === 'eligible' && canPost">
                <h2>Goods Receipts Ready for Posting</h2>

                <p class="section-note">
                    QC-required items appear only after QC approval.
                    Items not requiring QC can be posted directly.
                </p>

                <div
                    v-for="grn in eligibleGrns"
                    :key="grn.id"
                    class="grn-card"
                >
                    <div class="grn-heading">
                        <div>
                            <strong>{{ grn.grn_number }}</strong>
                            <p>
                                {{ grn.warehouse }}
                                · {{ grn.eligible_line_count }} eligible line(s)
                            </p>
                            <small v-if="grn.qc_inspection_number">
                                Approved QC:
                                {{ grn.qc_inspection_number }}
                            </small>
                        </div>

                        <button
                            type="button"
                            class="post-button"
                            :disabled="postingId !== null"
                            @click="postInventory(grn)"
                        >
                            {{
                                postingId === grn.id
                                    ? 'Posting...'
                                    : 'Post Inventory'
                            }}
                        </button>
                    </div>

                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="number">Received</th>
                                    <th>QC Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in grn.items"
                                    :key="index"
                                >
                                    <td>
                                        {{ item.item_code }} —
                                        {{ item.item_name }}
                                    </td>
                                    <td class="number">
                                        {{ quantity(item.received_quantity) }}
                                    </td>
                                    <td>{{ item.qc_status }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p
                    v-if="!eligibleGrns.length"
                    class="empty"
                >
                    No GRNs are currently ready for posting.
                </p>
            </template>

            <template v-else-if="activeTab === 'history'">
                <h2>Inventory Posting History</h2>

                <div
                    v-for="posting in postings"
                    :key="posting.id"
                    class="grn-card"
                >
                    <div class="grn-heading">
                        <div>
                            <strong>{{ posting.posting_number }}</strong>
                            <p>
                                {{ posting.grn_number }} ·
                                {{ posting.warehouse }}
                            </p>
                            <small>
                                Posted by {{ posting.posted_by }}
                                · {{ readableDate(posting.posted_at) }}
                            </small>
                        </div>

                        <span class="posted-badge">
                            {{ posting.status }}
                        </span>
                    </div>

                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th class="number">Accepted</th>
                                    <th class="number">Rejected</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(item, index) in posting.items"
                                    :key="index"
                                >
                                    <td>
                                        {{ item.item_code }} —
                                        {{ item.item_name }}
                                    </td>
                                    <td class="number">
                                        {{ quantity(item.accepted_quantity) }}
                                    </td>
                                    <td class="number rejected">
                                        {{ quantity(item.rejected_quantity) }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p v-if="!postings.length" class="empty">
                    No inventory postings recorded yet.
                </p>
            </template>
        </div>
    </section>
</template>

<style scoped>
.inventory-page {
    padding: 24px;
    color: #1e293b;
}

.inventory-heading,
.grn-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

.inventory-heading h1 {
    font-size: 26px;
    margin: 0 0 6px;
}

.inventory-heading p,
.grn-heading p,
.section-note {
    color: #64748b;
    margin: 5px 0;
}

.inventory-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
    margin: 24px 0;
}

.summary-card,
.inventory-panel,
.grn-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 18px;
}

.summary-card span {
    display: block;
    font-size: 13px;
    color: #64748b;
    margin-bottom: 12px;
}

.summary-card strong {
    font-size: 24px;
}

.inventory-tabs {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.inventory-tabs button,
.refresh-button {
    background: white;
    border: 1px solid #cbd5e1;
    padding: 10px 16px;
    border-radius: 8px;
    cursor: pointer;
}

.inventory-tabs button.active {
    background: #1d4ed8;
    color: white;
    border-color: #1d4ed8;
}

.tab-count {
    margin-left: 6px;
    font-weight: bold;
}

.inventory-panel h2 {
    font-size: 18px;
    margin: 0 0 16px;
}

.grn-card {
    margin-top: 14px;
}

.grn-heading {
    margin-bottom: 14px;
}

.grn-heading small {
    color: #64748b;
}

.post-button {
    border: none;
    background: #15803d;
    color: white;
    padding: 11px 18px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.post-button:disabled,
.refresh-button:disabled {
    opacity: 0.55;
    cursor: not-allowed;
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
}

th {
    background: #f8fafc;
    color: #475569;
}

.number {
    text-align: right;
    font-variant-numeric: tabular-nums;
}

.rejected {
    color: #b91c1c;
}

.empty {
    text-align: center;
    padding: 28px;
    color: #64748b;
}

.message {
    padding: 12px 16px;
    border-radius: 8px;
    margin-top: 14px;
}

.message.error {
    background: #fef2f2;
    color: #991b1b;
}

.message.success {
    background: #f0fdf4;
    color: #166534;
}

.posted-badge {
    background: #dcfce7;
    color: #166534;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    text-transform: uppercase;
}

@media (max-width: 700px) {
    .inventory-summary {
        grid-template-columns: 1fr;
    }

    .inventory-heading,
    .grn-heading {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
