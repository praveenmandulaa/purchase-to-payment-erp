
<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const reports = [
    {
        id: 'purchase_orders',
        label: 'Purchase Orders',
        description: 'Purchase order register and supplier commitments.',
        dateLabel: 'PO Date',
        columns: [
            { key: 'po_number', label: 'PO Number' },
            { key: 'supplier', label: 'Supplier' },
            { key: 'po_date', label: 'PO Date', type: 'date' },
            { key: 'expected_delivery_date', label: 'Expected Delivery', type: 'date' },
            { key: 'status', label: 'Status', type: 'status' },
            { key: 'total_amount', label: 'Amount', type: 'money' },
            { key: 'remarks', label: 'Remarks' },
        ],
    },
    {
        id: 'goods_receipts',
        label: 'Goods Receipts',
        description: 'Goods received against purchase orders.',
        dateLabel: 'Receipt Date',
        columns: [
            { key: 'grn_number', label: 'GRN Number' },
            { key: 'po_number', label: 'PO Number' },
            { key: 'supplier', label: 'Supplier' },
            { key: 'warehouse', label: 'Warehouse' },
            { key: 'receipt_date', label: 'Receipt Date', type: 'date' },
            { key: 'delivery_reference', label: 'Delivery Reference' },
            { key: 'status', label: 'Status', type: 'status' },
            { key: 'remarks', label: 'Remarks' },
        ],
    },
    {
        id: 'inventory',
        label: 'Inventory',
        description: 'Current item and warehouse stock balances.',
        dateLabel: null,
        columns: [
            { key: 'warehouse_code', label: 'Warehouse Code' },
            { key: 'warehouse', label: 'Warehouse' },
            { key: 'item_code', label: 'Item Code' },
            { key: 'item', label: 'Item' },
            { key: 'category', label: 'Category' },
            { key: 'uom', label: 'UOM' },
            { key: 'available_quantity', label: 'Available', type: 'quantity' },
            { key: 'rejected_quantity', label: 'Rejected', type: 'quantity' },
            { key: 'updated_at', label: 'Last Updated', type: 'datetime' },
        ],
    },
    {
        id: 'invoices',
        label: 'Supplier Invoices',
        description: 'Supplier invoice approvals and values.',
        dateLabel: 'Invoice Date',
        columns: [
            { key: 'invoice_number', label: 'ERP Invoice' },
            { key: 'supplier_invoice_number', label: 'Supplier Invoice' },
            { key: 'supplier', label: 'Supplier' },
            { key: 'invoice_date', label: 'Invoice Date', type: 'date' },
            { key: 'due_date', label: 'Due Date', type: 'date' },
            { key: 'status', label: 'Status', type: 'status' },
            { key: 'currency', label: 'Currency' },
            { key: 'subtotal', label: 'Subtotal', type: 'money' },
            { key: 'tax_amount', label: 'Tax', type: 'money' },
            { key: 'total_amount', label: 'Total', type: 'money' },
        ],
    },
    {
        id: 'payments',
        label: 'Payments',
        description: 'Recorded supplier payments and bank references.',
        dateLabel: 'Payment Date',
        columns: [
            { key: 'payment_number', label: 'Payment Number' },
            { key: 'invoice_number', label: 'ERP Invoice' },
            { key: 'supplier', label: 'Supplier' },
            { key: 'payment_date', label: 'Payment Date', type: 'date' },
            { key: 'amount', label: 'Amount', type: 'money' },
            { key: 'payment_method', label: 'Method' },
            { key: 'reference', label: 'Reference' },
            { key: 'status', label: 'Status', type: 'status' },
        ],
    },
    {
        id: 'payables',
        label: 'Outstanding Payables',
        description: 'Unpaid and partially paid approved invoices.',
        dateLabel: 'Due Date',
        columns: [
            { key: 'invoice_number', label: 'ERP Invoice' },
            { key: 'supplier_invoice_number', label: 'Supplier Invoice' },
            { key: 'supplier', label: 'Supplier' },
            { key: 'invoice_date', label: 'Invoice Date', type: 'date' },
            { key: 'due_date', label: 'Due Date', type: 'date' },
            { key: 'currency', label: 'Currency' },
            { key: 'total_amount', label: 'Invoice Total', type: 'money' },
            { key: 'paid_amount', label: 'Paid', type: 'money' },
            { key: 'outstanding_amount', label: 'Outstanding', type: 'money' },
            { key: 'payment_status', label: 'Payment Status', type: 'status' },
            { key: 'days_overdue', label: 'Days Overdue' },
        ],
    },
];

const activeReport = ref('purchase_orders');
const fromDate = ref('');
const toDate = ref('');
const status = ref('');
const search = ref('');

const rows = ref([]);
const loading = ref(false);
const exporting = ref(false);
const error = ref('');
const success = ref('');
const lastUpdated = ref('');
const rowLimit = ref(5000);
const requestNumber = ref(0);

const currentReport = computed(() =>
    reports.find(report => report.id === activeReport.value)
    ?? reports[0]
);

const statusOptions = computed(() => {
    switch (activeReport.value) {
        case 'inventory':
            return [
                { value: 'available', label: 'Available stock > 0' },
                { value: 'rejected', label: 'Rejected stock > 0' },
                { value: 'zero_stock', label: 'Available stock = 0' },
            ];

        case 'payables':
            return [
                { value: 'unpaid', label: 'Unpaid' },
                { value: 'partially_paid', label: 'Partially Paid' },
                { value: 'overdue', label: 'Overdue' },
            ];

        case 'invoices':
            return [
                'draft',
                'matched',
                'mismatch',
                'pending_approval',
                'approved',
                'rejected',
            ].map(value => ({
                value,
                label: prettyStatus(value),
            }));

        case 'payments':
            return [
                { value: 'recorded', label: 'Recorded' },
            ];

        default:
            // Purchase order and GRN statuses depend on the
            // statuses implemented in your existing ERP.
            // Use free-text status entry for these two reports.
            return [];
    }
});

const filteredRows = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (!term) return rows.value;

    return rows.value.filter(row =>
        currentReport.value.columns.some(column =>
            String(row[column.key] ?? '')
                .toLowerCase()
                .includes(term)
        )
    );
});

const totalAmount = computed(() => {
    const field = {
        purchase_orders: 'total_amount',
        invoices: 'total_amount',
        payments: 'amount',
        payables: 'outstanding_amount',
    }[activeReport.value];

    if (!field) return null;

    return filteredRows.value.reduce(
        (total, row) => total + Number(row[field] ?? 0),
        0
    );
});

const hasDateFilters = computed(() =>
    activeReport.value !== 'inventory'
);

function money(value) {
    const number = Number(value ?? 0);

    return new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(number);
}

function quantity(value) {
    return new Intl.NumberFormat('en-IN', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    }).format(Number(value ?? 0));
}

function prettyStatus(value) {
    if (!value) return '—';

    return String(value)
        .replaceAll('_', ' ')
        .replace(/\b\w/g, letter => letter.toUpperCase());
}

function formatDate(value) {
    if (!value) return '—';

    const datePart = String(value).slice(0, 10);
    const match = datePart.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (!match) return String(value);

    return `${match[3]}-${match[2]}-${match[1]}`;
}

function formatDateTime(value) {
    if (!value) return '—';

    return String(value)
        .replace('T', ' ')
        .replace(/\.\d+Z$/, ' UTC')
        .slice(0, 23);
}

function displayValue(row, column) {
    const value = row[column.key];

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    switch (column.type) {
        case 'money':
            return money(value);
        case 'quantity':
            return quantity(value);
        case 'date':
            return formatDate(value);
        case 'datetime':
            return formatDateTime(value);
        case 'status':
            return prettyStatus(value);
        default:
            return String(value);
    }
}

function statusClass(value) {
    const normalized = String(value ?? '').toLowerCase();

    if ([
        'approved',
        'recorded',
        'paid',
        'completed',
        'accepted',
        'posted',
    ].includes(normalized)) {
        return 'positive';
    }

    if ([
        'pending_approval',
        'partially_paid',
        'draft',
        'submitted',
        'matched',
    ].includes(normalized)) {
        return 'warning';
    }

    if ([
        'rejected',
        'mismatch',
        'cancelled',
    ].includes(normalized)) {
        return 'negative';
    }

    return 'neutral';
}

function params() {
    const values = {
        report: activeReport.value,
    };

    if (hasDateFilters.value) {
        if (fromDate.value) values.from_date = fromDate.value;
        if (toDate.value) values.to_date = toDate.value;
    }

    if (status.value.trim()) {
        values.status = status.value.trim();
    }

    return values;
}

function validateDates() {
    if (
        hasDateFilters.value &&
        fromDate.value &&
        toDate.value &&
        fromDate.value > toDate.value
    ) {
        error.value = 'From Date cannot be later than To Date.';
        return false;
    }

    return true;
}

function getErrorMessage(err) {
    if (err.response?.status === 403) {
        return 'You do not have permission to view ERP reports.';
    }

    if (err.response?.status === 422) {
        const errors = err.response?.data?.errors;
        const firstError = errors
            ? Object.values(errors).flat()[0]
            : null;

        return firstError ||
            err.response?.data?.message ||
            'Please check your report filters.';
    }

    return err.response?.data?.message ||
        'Unable to load the report.';
}

async function loadReport() {
    error.value = '';
    success.value = '';

    if (!validateDates()) return;

    const currentRequest = ++requestNumber.value;

    loading.value = true;

    try {
        const response = await axios.get('/erp/reports', {
            params: params(),
        });

        // Ignore an older response if the user switched tabs.
        if (currentRequest !== requestNumber.value) return;

        rows.value = response.data.rows ?? [];
        rowLimit.value = response.data.row_limit ?? 5000;
        lastUpdated.value = new Date().toLocaleString('en-IN');
    } catch (err) {
        if (currentRequest !== requestNumber.value) return;

        rows.value = [];
        error.value = getErrorMessage(err);
    } finally {
        if (currentRequest === requestNumber.value) {
            loading.value = false;
        }
    }
}

function changeReport(reportId) {
    if (activeReport.value === reportId) return;

    activeReport.value = reportId;

    fromDate.value = '';
    toDate.value = '';
    status.value = '';
    search.value = '';
    rows.value = [];
    error.value = '';
    success.value = '';

    loadReport();
}

function clearFilters() {
    fromDate.value = '';
    toDate.value = '';
    status.value = '';
    search.value = '';

    loadReport();
}

function filenameFromHeaders(headers) {
    const disposition = headers?.['content-disposition'] ?? '';

    const match = disposition.match(/filename="?([^";]+)"?/i);

    if (match?.[1]) return match[1];

    return `erp_${activeReport.value}.csv`;
}

async function exportCsv() {
    error.value = '';
    success.value = '';

    if (!validateDates()) return;

    exporting.value = true;

    try {
        const reportId = activeReport.value;

        const response = await axios.get('/erp/reports/export', {
            params: params(),
            responseType: 'blob',
        });

        const blob = new Blob([response.data], {
            type: 'text/csv;charset=utf-8;',
        });

        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = filenameFromHeaders(response.headers);
        document.body.appendChild(link);
        link.click();
        link.remove();

        window.setTimeout(() => {
            window.URL.revokeObjectURL(url);
        }, 1000);

        success.value = `${prettyStatus(reportId)} CSV exported.`;
    } catch (err) {
        // Blob responses may contain JSON error text.
        if (err.response?.data instanceof Blob) {
            try {
                const text = await err.response.data.text();
                const json = JSON.parse(text);

                error.value = json.message ||
                    'Unable to export the report.';
            } catch {
                error.value = 'Unable to export the report.';
            }
        } else {
            error.value = getErrorMessage(err);
        }
    } finally {
        exporting.value = false;
    }
}

onMounted(loadReport);
</script>

<template>
    <div class="reports-page">
        <header class="page-header">
            <div>
                <p class="eyebrow">ERP ANALYTICS</p>
                <h1>Reports</h1>
                <p class="subtitle">
                    Procurement, inventory, invoices, payments,
                    and outstanding payables.
                </p>
            </div>

            <button
                class="btn btn-secondary"
                type="button"
                :disabled="loading"
                @click="loadReport"
            >
                {{ loading ? 'Loading...' : 'Refresh Report' }}
            </button>
        </header>

        <div class="report-tabs" role="tablist" aria-label="ERP reports">
            <button
                v-for="report in reports"
                :key="report.id"
                type="button"
                role="tab"
                class="report-tab"
                :class="{ active: activeReport === report.id }"
                :aria-selected="activeReport === report.id"
                @click="changeReport(report.id)"
            >
                {{ report.label }}
            </button>
        </div>

        <section class="panel">
            <div class="section-heading">
                <div>
                    <h2>{{ currentReport.label }} Report</h2>
                    <p>{{ currentReport.description }}</p>
                </div>

                <button
                    type="button"
                    class="btn btn-primary"
                    :disabled="exporting || loading"
                    @click="exportCsv"
                >
                    {{ exporting ? 'Exporting...' : 'Export CSV' }}
                </button>
            </div>

            <form class="filters" @submit.prevent="loadReport">
                <template v-if="hasDateFilters">
                    <label class="field">
                        <span>From {{ currentReport.dateLabel }}</span>
                        <input v-model="fromDate" type="date" />
                    </label>

                    <label class="field">
                        <span>To {{ currentReport.dateLabel }}</span>
                        <input
                            v-model="toDate"
                            type="date"
                            :min="fromDate || undefined"
                        />
                    </label>
                </template>

                <label class="field">
                    <span>Status / Stock Filter</span>

                    <select
                        v-if="statusOptions.length"
                        v-model="status"
                    >
                        <option value="">All</option>

                        <option
                            v-for="option in statusOptions"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>

                    <input
                        v-else
                        v-model="status"
                        type="text"
                        placeholder="All (or enter exact status)"
                    />
                </label>

                <div class="filter-actions">
                    <button
                        type="submit"
                        class="btn btn-primary"
                        :disabled="loading"
                    >
                        {{ loading ? 'Applying...' : 'Apply Filters' }}
                    </button>

                    <button
                        type="button"
                        class="btn btn-secondary"
                        @click="clearFilters"
                    >
                        Clear
                    </button>
                </div>
            </form>

            <p
                v-if="activeReport === 'inventory'"
                class="hint"
            >
                Inventory shows current balances. Historical date
                filtering is not available for this report.
                Quantities should be compared within the same UOM.
            </p>

            <p
                v-if="activeReport === 'payables'"
                class="hint"
            >
                Date filters use invoice due dates.
                Fully paid invoices are excluded.
            </p>

            <div v-if="error" class="alert alert-error" role="alert">
                {{ error }}
            </div>

            <div v-if="success" class="alert alert-success" role="status">
                {{ success }}
            </div>

            <div class="results-toolbar">
                <div class="result-summary">
                    <strong>{{ filteredRows.length }}</strong>
                    <span>
                        {{ filteredRows.length === 1 ? 'row' : 'rows' }}
                        displayed
                    </span>

                    <span v-if="search.trim()">
                        ({{ rows.length }} loaded)
                    </span>
                </div>

                <label class="search-field">
                    <span>Search loaded rows</span>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Search any visible column..."
                    />
                </label>
            </div>

            <div v-if="totalAmount !== null" class="amount-summary">
                <span>
                    {{
                        activeReport === 'payables'
                            ? 'Outstanding total (displayed rows)'
                            : 'Amount total (displayed rows)'
                    }}
                </span>
                <strong>{{ money(totalAmount) }}</strong>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th
                                v-for="column in currentReport.columns"
                                :key="column.key"
                                :class="{
                                    numeric:
                                        column.type === 'money' ||
                                        column.type === 'quantity'
                                }"
                            >
                                {{ column.label }}
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr v-if="loading">
                            <td
                                :colspan="currentReport.columns.length"
                                class="empty-cell"
                            >
                                Loading report data...
                            </td>
                        </tr>

                        <tr v-else-if="filteredRows.length === 0">
                            <td
                                :colspan="currentReport.columns.length"
                                class="empty-cell"
                            >
                                No records found for the selected filters.
                            </td>
                        </tr>

                        <tr
                            v-for="(row, index) in loading ? [] : filteredRows"
                            :key="`${activeReport}-${index}`"
                        >
                            <td
                                v-for="column in currentReport.columns"
                                :key="column.key"
                                :class="{
                                    numeric:
                                        column.type === 'money' ||
                                        column.type === 'quantity'
                                }"
                            >
                                <span
                                    v-if="column.type === 'status'"
                                    class="status-pill"
                                    :class="statusClass(row[column.key])"
                                >
                                    {{ displayValue(row, column) }}
                                </span>

                                <span v-else>
                                    {{ displayValue(row, column) }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="table-footer">
                <span>
                    API row limit: {{ rowLimit.toLocaleString('en-IN') }}
                </span>
                <span>
                    Last refreshed: {{ lastUpdated || 'Not yet loaded' }}
                </span>
            </div>

            <p class="hint">
                The search box filters loaded rows only.
                CSV export uses the selected server-side filters,
                not the search box. Reports are limited to
                {{ rowLimit.toLocaleString('en-IN') }} rows.
            </p>
        </section>
    </div>
</template>

<style scoped>
.reports-page {
    padding: 24px;
    color: #17243b;
}

.page-header,
.section-heading,
.results-toolbar,
.table-footer,
.amount-summary {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.page-header {
    align-items: flex-start;
    margin-bottom: 24px;
}

.eyebrow {
    margin: 0 0 8px;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 1.3px;
    color: #2563eb;
}

h1 {
    font-size: 27px;
    margin: 0 0 8px;
}

h2 {
    font-size: 18px;
    margin: 0 0 7px;
}

.subtitle,
.section-heading p,
.hint,
.table-footer {
    color: #64748b;
    font-size: 13px;
}

.section-heading p {
    margin: 0;
}

.report-tabs {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 18px;
}

.report-tab {
    border: 1px solid #cbd5e1;
    background: white;
    color: #334155;
    border-radius: 8px;
    padding: 11px 14px;
    font-size: 13px;
    cursor: pointer;
}

.report-tab.active {
    background: #2456e8;
    color: white;
    border-color: #2456e8;
}

.panel {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
}

.btn {
    padding: 10px 14px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 650;
    cursor: pointer;
    white-space: nowrap;
}

.btn:disabled {
    opacity: 0.55;
    cursor: not-allowed;
}

.btn-primary {
    background: #2456e8;
    border: 1px solid #2456e8;
    color: white;
}

.btn-secondary {
    background: white;
    border: 1px solid #cbd5e1;
    color: #334155;
}

.filters {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr)) auto;
    align-items: end;
    gap: 12px;
    margin: 24px 0 18px;
    padding: 18px;
    background: #f8fafc;
    border-radius: 10px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 7px;
    min-width: 0;
}

.field span,
.search-field span {
    font-size: 12px;
    font-weight: 650;
    color: #475569;
}

.field input,
.field select,
.search-field input {
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #cbd5e1;
    background: white;
    border-radius: 7px;
    padding: 10px;
    font-size: 13px;
    color: #17243b;
}

.filter-actions {
    display: flex;
    gap: 8px;
}

.results-toolbar {
    margin: 20px 0 14px;
}

.result-summary {
    display: flex;
    gap: 6px;
    align-items: baseline;
    font-size: 13px;
    color: #64748b;
}

.result-summary strong {
    font-size: 20px;
    color: #17243b;
}

.search-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    width: 260px;
    max-width: 100%;
}

.amount-summary {
    padding: 14px 16px;
    border-radius: 9px;
    background: #eff6ff;
    color: #1e40af;
    margin-bottom: 16px;
    font-size: 13px;
}

.amount-summary strong {
    font-size: 19px;
}

.table-wrapper {
    overflow-x: auto;
    border: 1px solid #e2e8f0;
    border-radius: 9px;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

th,
td {
    padding: 12px 11px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    white-space: nowrap;
}

th {
    background: #f8fafc;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
}

tbody tr:hover {
    background: #f8fafc;
}

tbody tr:last-child td {
    border-bottom: none;
}

.numeric {
    text-align: right;
}

.empty-cell {
    text-align: center;
    color: #64748b;
    padding: 32px;
}

.status-pill {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
}

.status-pill.positive {
    background: #dcfce7;
    color: #166534;
}

.status-pill.warning {
    background: #fef3c7;
    color: #92400e;
}

.status-pill.negative {
    background: #fee2e2;
    color: #991b1b;
}

.status-pill.neutral {
    background: #e2e8f0;
    color: #334155;
}

.alert {
    padding: 12px 14px;
    margin: 14px 0;
    border-radius: 8px;
    font-size: 13px;
}

.alert-error {
    background: #fef2f2;
    color: #b91c1c;
}

.alert-success {
    background: #f0fdf4;
    color: #166534;
}

.table-footer {
    margin-top: 15px;
    flex-wrap: wrap;
}

.hint {
    line-height: 1.6;
    margin-top: 12px;
}

@media (max-width: 1100px) {
    .filters {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 650px) {
    .reports-page {
        padding: 12px;
    }

    .page-header,
    .section-heading,
    .results-toolbar {
        align-items: stretch;
        flex-direction: column;
    }

    .filters {
        grid-template-columns: 1fr;
    }

    .filter-actions {
        flex-wrap: wrap;
    }

    .search-field {
        width: 100%;
    }
}
</style>
