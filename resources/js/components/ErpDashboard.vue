
<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const dashboard = ref(null);
const loading = ref(false);
const error = ref('');
const lastUpdated = ref('');

function money(value) {
    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));
}

function quantity(value) {
    return new Intl.NumberFormat('en-IN', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    }).format(Number(value ?? 0));
}

function dateLabel(value) {
    if (!value) return '—';

    const datePart = String(value).slice(0, 10);
    const [year, month, day] = datePart.split('-');

    if (!year || !month || !day) return datePart;

    return `${day}-${month}-${year}`;
}

function getErrorMessage(err) {
    if (err.response?.status === 403) {
        return 'You do not have permission to view the ERP dashboard.';
    }

    return err.response?.data?.message ||
        'Unable to load dashboard data.';
}

const purchaseOrders = computed(() =>
    dashboard.value?.purchase_orders?.total ?? 0
);

const goodsReceipts = computed(() =>
    dashboard.value?.goods_receipts?.total ?? 0
);

const qcInspections = computed(() =>
    dashboard.value?.quality_control?.total ?? 0
);

const inventoryPostings = computed(() =>
    dashboard.value?.inventory?.total_postings ?? 0
);

const availableQuantity = computed(() =>
    dashboard.value?.inventory?.available_quantity ?? '0.000'
);

const rejectedQuantity = computed(() =>
    dashboard.value?.inventory?.rejected_quantity ?? '0.000'
);

const totalInvoices = computed(() =>
    dashboard.value?.invoices?.total ?? 0
);

const approvedInvoices = computed(() =>
    dashboard.value?.invoices?.approved_count ?? 0
);

const pendingInvoices = computed(() =>
    dashboard.value?.invoices?.pending_count ?? 0
);

const approvedAmount = computed(() =>
    Number(dashboard.value?.invoices?.approved_amount ?? 0)
);

const paidAmount = computed(() =>
    Number(dashboard.value?.payments?.paid_amount ?? 0)
);

const outstandingAmount = computed(() =>
    Number(dashboard.value?.payments?.outstanding_amount ?? 0)
);

const paymentCount = computed(() =>
    dashboard.value?.payments?.total ?? 0
);

const paymentPercentage = computed(() => {
    if (approvedAmount.value <= 0) return 0;

    return Math.min(
        100,
        Math.max(
            0,
            (paidAmount.value / approvedAmount.value) * 100
        )
    );
});

const recentInvoices = computed(() =>
    dashboard.value?.recent_invoices ?? []
);

const recentPayments = computed(() =>
    dashboard.value?.recent_payments ?? []
);

async function loadDashboard() {
    loading.value = true;
    error.value = '';

    try {
        const response = await axios.get(
            '/erp/dashboard/summary'
        );

        dashboard.value = response.data;

        lastUpdated.value = new Date().toLocaleString('en-IN');
    } catch (err) {
        error.value = getErrorMessage(err);
    } finally {
        loading.value = false;
    }
}

onMounted(loadDashboard);
</script>

<template>
    <div class="dashboard-page">
        <header class="dashboard-header">
            <div>
                <p class="eyebrow">ERP OVERVIEW</p>
                <h1>Management Dashboard</h1>
                <p class="subtitle">
                    Purchase-to-Payment operational and financial overview
                </p>
            </div>

            <button
                type="button"
                class="refresh-button"
                :disabled="loading"
                @click="loadDashboard"
            >
                {{ loading ? 'Refreshing...' : 'Refresh Dashboard' }}
            </button>
        </header>

        <div v-if="error" class="error-message">
            {{ error }}
        </div>

        <div
            v-if="!dashboard && loading"
            class="empty-state"
        >
            Loading ERP dashboard...
        </div>

        <template v-if="dashboard">
            <div class="section-heading">
                <h2>Procurement Operations</h2>
                <span>Live transaction counts</span>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card">
                    <span class="kpi-label">Purchase Orders</span>
                    <strong>{{ purchaseOrders }}</strong>
                    <small>Total purchase orders</small>
                </div>

                <div class="kpi-card">
                    <span class="kpi-label">Goods Receipts</span>
                    <strong>{{ goodsReceipts }}</strong>
                    <small>Total GRNs created</small>
                </div>

                <div class="kpi-card">
                    <span class="kpi-label">QC Inspections</span>
                    <strong>{{ qcInspections }}</strong>
                    <small>Total inspections</small>
                </div>

                <div class="kpi-card">
                    <span class="kpi-label">Inventory Postings</span>
                    <strong>{{ inventoryPostings }}</strong>
                    <small>Total stock postings</small>
                </div>
            </div>

            <div class="section-heading">
                <h2>Inventory Overview</h2>
                <span>Across all warehouses</span>
            </div>

            <div class="kpi-grid two-columns">
                <div class="kpi-card">
                    <span class="kpi-label">Available Quantity</span>
                    <strong>{{ quantity(availableQuantity) }}</strong>
                    <small>Sum of available inventory quantities</small>
                </div>

                <div class="kpi-card">
                    <span class="kpi-label">Rejected Quantity</span>
                    <strong>{{ quantity(rejectedQuantity) }}</strong>
                    <small>Sum of rejected inventory quantities</small>
                </div>
            </div>

            <p class="inventory-note">
                Inventory figures are quantities, not stock values.
                Different item units should be analyzed separately
                in detailed inventory reports.
            </p>

            <div class="section-heading">
                <h2>Accounts Payable</h2>
                <span>Approved supplier invoices and payments</span>
            </div>

            <div class="kpi-grid">
                <div class="kpi-card">
                    <span class="kpi-label">Supplier Invoices</span>
                    <strong>{{ totalInvoices }}</strong>
                    <small>
                        {{ approvedInvoices }} approved,
                        {{ pendingInvoices }} pending
                    </small>
                </div>

                <div class="kpi-card">
                    <span class="kpi-label">Approved Invoice Value</span>
                    <strong class="money-value">
                        {{ money(approvedAmount) }}
                    </strong>
                    <small>Total approved invoice amount</small>
                </div>

                <div class="kpi-card">
                    <span class="kpi-label">Total Paid</span>
                    <strong class="money-value paid-text">
                        {{ money(paidAmount) }}
                    </strong>
                    <small>{{ paymentCount }} recorded payments</small>
                </div>

                <div class="kpi-card">
                    <span class="kpi-label">Outstanding Payable</span>
                    <strong class="money-value">
                        {{ money(outstandingAmount) }}
                    </strong>
                    <small>Remaining approved invoice balance</small>
                </div>
            </div>

            <section class="panel payment-panel">
                <div class="panel-heading">
                    <div>
                        <h2>Payment Completion</h2>
                        <p>Progress against approved supplier invoices</p>
                    </div>

                    <strong class="percentage">
                        {{ paymentPercentage.toFixed(1) }}%
                    </strong>
                </div>

                <div
                    class="progress-track"
                    role="progressbar"
                    :aria-valuenow="paymentPercentage"
                    aria-valuemin="0"
                    aria-valuemax="100"
                    aria-label="Payment completion"
                >
                    <div
                        class="progress-fill"
                        :style="{ width: paymentPercentage + '%' }"
                    ></div>
                </div>

                <div class="progress-labels">
                    <span>
                        Paid: {{ money(paidAmount) }}
                    </span>
                    <span>
                        Outstanding: {{ money(outstandingAmount) }}
                    </span>
                </div>
            </section>

            <div class="recent-grid">
                <section class="panel">
                    <div class="panel-heading">
                        <h2>Recent Supplier Invoices</h2>
                        <span>Latest 5</span>
                    </div>

                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Supplier</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="invoice in recentInvoices"
                                    :key="invoice.id"
                                >
                                    <td>
                                        <strong>
                                            {{ invoice.invoice_number }}
                                        </strong>
                                        <small>
                                            {{ dateLabel(invoice.invoice_date) }}
                                        </small>
                                    </td>

                                    <td>
                                        {{ invoice.supplier?.name || '—' }}
                                    </td>

                                    <td>
                                        {{ money(invoice.total_amount) }}
                                    </td>

                                    <td>
                                        <span
                                            class="status-pill"
                                            :class="invoice.status"
                                        >
                                            {{ invoice.status }}
                                        </span>
                                    </td>
                                </tr>

                                <tr v-if="recentInvoices.length === 0">
                                    <td colspan="4" class="empty-cell">
                                        No supplier invoices found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-heading">
                        <h2>Recent Supplier Payments</h2>
                        <span>Latest 5</span>
                    </div>

                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>Payment</th>
                                    <th>Supplier</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                <tr
                                    v-for="payment in recentPayments"
                                    :key="payment.id"
                                >
                                    <td>
                                        <strong>
                                            {{ payment.payment_number }}
                                        </strong>
                                        <small>
                                            {{ dateLabel(payment.payment_date) }}
                                        </small>
                                    </td>

                                    <td>
                                        {{ payment.supplier?.name || '—' }}
                                    </td>

                                    <td>
                                        {{ money(payment.amount) }}
                                    </td>

                                    <td>
                                        <span class="status-pill recorded">
                                            {{ payment.status }}
                                        </span>
                                    </td>
                                </tr>

                                <tr v-if="recentPayments.length === 0">
                                    <td colspan="4" class="empty-cell">
                                        No supplier payments found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <p class="updated-text">
                Last refreshed: {{ lastUpdated }}
            </p>
        </template>
    </div>
</template>

<style scoped>
.dashboard-page {
    padding: 24px;
    color: #17243b;
}

.dashboard-header,
.panel-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
}

.dashboard-header {
    margin-bottom: 28px;
}

.eyebrow {
    color: #2563eb;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.4px;
    margin: 0 0 8px;
}

h1 {
    margin: 0 0 8px;
    font-size: 27px;
    font-weight: 750;
}

.subtitle,
.panel-heading p,
.section-heading span,
.updated-text,
.inventory-note {
    color: #64748b;
    font-size: 13px;
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin: 26px 0 14px;
}

.section-heading h2 {
    font-size: 17px;
    margin: 0;
}

.kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.kpi-grid.two-columns {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.kpi-card,
.panel {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}

.kpi-card {
    display: flex;
    flex-direction: column;
    gap: 11px;
    min-width: 0;
}

.kpi-label {
    color: #64748b;
    font-size: 13px;
    font-weight: 600;
}

.kpi-card strong {
    font-size: 29px;
    line-height: 1.2;
    overflow-wrap: anywhere;
}

.kpi-card strong.money-value {
    font-size: 22px;
}

.kpi-card small {
    color: #64748b;
    font-size: 12px;
}

.paid-text {
    color: #15803d;
}

.inventory-note {
    margin-top: 12px;
    line-height: 1.6;
}

.payment-panel {
    margin-top: 20px;
}

.panel-heading h2 {
    margin: 0 0 6px;
    font-size: 17px;
}

.panel-heading p {
    margin: 0;
}

.percentage {
    font-size: 24px;
    color: #15803d;
}

.progress-track {
    height: 14px;
    border-radius: 20px;
    background: #e2e8f0;
    overflow: hidden;
    margin: 20px 0 12px;
}

.progress-fill {
    height: 100%;
    background: #16a34a;
    border-radius: 20px;
    transition: width 250ms ease;
}

.progress-labels {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    color: #475569;
    font-size: 13px;
}

.recent-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    margin-top: 20px;
}

.table-scroll {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

th,
td {
    text-align: left;
    padding: 12px 9px;
    border-bottom: 1px solid #e2e8f0;
}

th {
    color: #64748b;
    background: #f8fafc;
    font-size: 12px;
}

td strong {
    white-space: nowrap;
}

td small {
    display: block;
    margin-top: 5px;
    color: #64748b;
}

.status-pill {
    display: inline-block;
    border-radius: 20px;
    padding: 5px 9px;
    background: #e2e8f0;
    color: #334155;
    font-size: 11px;
    font-weight: 700;
    text-transform: capitalize;
}

.status-pill.approved,
.status-pill.recorded {
    background: #dcfce7;
    color: #166534;
}

.status-pill.pending_approval {
    background: #fef3c7;
    color: #92400e;
}

.refresh-button {
    padding: 10px 15px;
    background: #fff;
    color: #1d4ed8;
    border: 1px solid #bfdbfe;
    border-radius: 8px;
    font-weight: 650;
    cursor: pointer;
}

.refresh-button:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.error-message {
    background: #fef2f2;
    color: #b91c1c;
    padding: 14px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.empty-state,
.empty-cell {
    padding: 24px;
    text-align: center;
    color: #64748b;
}

.updated-text {
    text-align: right;
    margin-top: 18px;
}

@media (max-width: 1100px) {
    .kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .recent-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 640px) {
    .dashboard-page {
        padding: 12px;
    }

    .dashboard-header,
    .section-heading {
        flex-direction: column;
    }

    .kpi-grid,
    .kpi-grid.two-columns {
        grid-template-columns: 1fr;
    }

    .progress-labels {
        flex-direction: column;
    }
}
</style>
