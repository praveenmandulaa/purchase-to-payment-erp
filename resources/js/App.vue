<script setup>
import { ref, computed, onMounted } from 'vue';
import PurchaseOrders from './components/PurchaseOrders.vue';
import GoodsReceipts from './components/GoodsReceipts.vue';
import QualityControl from './components/QualityControl.vue';
import Inventory from './components/Inventory.vue';
import SupplierInvoices from './components/SupplierInvoices.vue';
import SupplierPayments from './components/SupplierPayments.vue';
import ErpDashboard from './components/ErpDashboard.vue';
import ErpReports from './components/ErpReports.vue';

const email = ref('');
const password = ref('');
const user = ref(null);
const error = ref('');
const loading = ref(true);
const submitting = ref(false);
const activePage = ref('dashboard');

const csrf = () =>
    document.querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

async function request(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            ...(options.headers || {}),
        },
    });

    const data = await response.json();

    if (!response.ok) {
        throw new Error(
            data.message || `Request failed (${response.status})`
        );
    }

    return data;
}

async function loadUser() {
    try {
        const data = await request('/erp/auth/me');
        user.value = data.user;
    } catch {
        user.value = null;
    } finally {
        loading.value = false;
    }
}

async function login() {
    error.value = '';
    submitting.value = true;

    try {
        await request('/erp/auth/login', {
            method: 'POST',
            body: JSON.stringify({
                email: email.value,
                password: password.value,
            }),
        });

        // Refresh CSRF token after session regeneration.
        window.location.reload();
    } catch (e) {
        error.value = e.message;
        submitting.value = false;
    }
}

async function logout() {
    error.value = '';

    try {
        await request('/erp/auth/logout', {
            method: 'POST',
        });

        window.location.reload();
    } catch (e) {
        error.value = e.message;
    }
}

const roles = computed(() => user.value?.roles ?? []);

const isManager = computed(() =>
    roles.value.includes('purchase-manager')
);

const canViewPO = computed(() =>
    roles.value.some(role =>
        ['admin', 'buyer', 'purchase-manager',
         'warehouse-officer', 'accounts-officer'].includes(role)
    )
);
const canViewGRN = computed(() =>
    roles.value.some(role =>
        [
            'admin',
            'buyer',
            'purchase-manager',
            'warehouse-officer',
        ].includes(role)
    )
);
const canViewQC = computed(() =>
    roles.value.some(role =>
        [
            'admin',
            'qc-inspector',
            'qc-manager',
            'warehouse-officer',
            'buyer',
            'purchase-manager',
        ].includes(role)
    )
);
const canViewInventory = computed(() =>
    roles.value.includes('admin') ||
    roles.value.includes('inventory-manager') ||
    roles.value.includes('warehouse-officer')
);

const canPostInventory = computed(() =>
    roles.value.includes('admin') ||
    roles.value.includes('inventory-manager') ||
    roles.value.includes('warehouse-officer')
);

const canCreateInvoices = computed(() =>
    roles.value.includes('admin') ||
    roles.value.includes('accounts-officer')
);

const canApproveInvoices = computed(() =>
    roles.value.includes('admin') ||
    roles.value.includes('finance-manager')
);

const canViewInvoices = computed(() =>
    canCreateInvoices.value ||
    canApproveInvoices.value
);

const displayRole = computed(() =>
    roles.value
        .map(role => role.replaceAll('-', ' '))
        .join(', ') || 'Employee'
);

const canRecordPayments = computed(() =>
    roles.value.includes('admin') ||
    roles.value.includes('accounts-officer')
);

const canViewDashboard = computed(() =>
    ['admin', 'purchase-manager', 'inventory-manager', 'finance-manager']
        .some(role => roles.value.includes(role))
);

const canViewReports = computed(() =>
    ['admin', 'purchase-manager', 'inventory-manager', 'finance-manager']
        .some(role => roles.value.includes(role))
);

const initials = computed(() =>
    (user.value?.name ?? 'U')
        .split(' ')
        .slice(0, 2)
        .map(part => part.charAt(0))
        .join('')
        .toUpperCase()
);

onMounted(loadUser);
</script>

<template>
    <div class="erp-app">

        <!-- Loading -->
        <div v-if="loading" class="loading-screen">
            <div class="spinner"></div>
            <p>Loading ERP application...</p>
        </div>

        <!-- Login -->
        <div v-else-if="!user" class="login-page">
            <div class="login-card">
                <div class="brand-icon">E</div>

                <h1>Welcome back</h1>
                <p class="login-subtitle">
                    Sign in to your ERP workspace
                </p>

                <form @submit.prevent="login">
                    <div class="form-group">
                        <label for="email">Email address</label>
                        <input
                            id="email"
                            v-model="email"
                            type="email"
                            placeholder="you@company.com"
                            autocomplete="username"
                            required
                        />
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <input
                            id="password"
                            v-model="password"
                            type="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        />
                    </div>

                    <p v-if="error" class="alert-error">
                        {{ error }}
                    </p>

                    <button
                        class="primary-btn login-btn"
                        type="submit"
                        :disabled="submitting"
                    >
                        {{ submitting ? 'Signing in...' : 'Sign In' }}
                    </button>
                </form>

                <p class="login-footer">
                    Purchase-to-Payment ERP
                </p>
            </div>
        </div>

        <!-- Authenticated Application -->
        <div v-else class="app-layout">

            <!-- Sidebar -->
            <aside class="sidebar">
                <div class="sidebar-brand">
                    <div class="sidebar-logo">E</div>
                    <div>
                        <strong>ERP System</strong>
                        <small>Purchase to Payment</small>
                    </div>
                </div>

                <div class="sidebar-section-label">
                    WORKSPACE
                </div>

                <nav class="sidebar-nav">
                <button
                    v-if="canViewDashboard"
                    :class="{ active: activePage === 'dashboard' }"
                    @click="activePage = 'dashboard'"
                >
                    Dashboard
                </button>
                <button
    v-if="canViewReports"
    type="button"
    :class="{ active: activePage === 'reports' }"
    @click="activePage = 'reports'"
>
    Reports
</button>

                    <button
                        v-if="canViewPO"
                        :class="{ active: activePage === 'purchase-orders' }"
                        @click="activePage = 'purchase-orders'"
                    >
                        <span class="nav-icon">▤</span>
                        Purchase Orders
                    </button>
                    <button
                        v-if="canViewGRN"
                        type="button"
                        :class="{ active: activePage === 'grn' }"
                        @click="activePage = 'grn'"
                    >
                        <span class="nav-icon">◫</span>
                        Goods Receipts
                    </button>
                    <button
                        v-if="canViewQC"
                        type="button"
                        :class="{ active: activePage === 'qc' }"
                        @click="activePage = 'qc'"
                    >
                        <span class="nav-icon">◇</span>
                        Quality Control
                    </button>
                    <button
    v-if="canViewInventory"
    type="button"
    @click="activePage = 'inventory'"
>
                        <span class="nav-icon">◇I</span>

    Inventory
</button>
<button
    v-if="canViewInvoices"
    type="button"
    @click="activePage = 'invoices'"
>
 <span class="nav-icon">◇</span>
    Supplier Invoices
</button>
<button
    v-if="canRecordPayments"
    type="button"
    :class="{ active: activePage === 'payments' }"
    @click="activePage = 'payments'"
>
    Supplier Payments
</button>
                </nav>

                <div class="sidebar-bottom">
                    <div class="sidebar-user">
                        <div class="avatar">{{ initials }}</div>
                        <div class="user-info">
                            <strong>{{ user.name }}</strong>
                            <small>{{ displayRole }}</small>
                        </div>
                    </div>

                    <button class="logout-btn" @click="logout">
                        Sign Out
                    </button>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="main-area">

                <header class="topbar">
                    <div>
                        <span class="breadcrumb">Workspace / </span>
                        <strong>
    {{
        activePage === 'dashboard'
            ? 'Dashboard'
            : activePage === 'reports'
                ? 'Reports'
                : activePage === 'purchase-orders'
                    ? 'Purchase Orders'
                    : activePage === 'grn'
                        ? 'Goods Receipts'
                        : activePage === 'qc'
                            ? 'Quality Control'
                            : activePage === 'inventory'
                                ? 'Inventory'
                                : activePage === 'invoices'
                                    ? 'Supplier Invoices'
                                    : activePage === 'payments'
                                        ? 'Supplier Payments'
                                        : 'ERP Workspace'
    }}
</strong>
                    </div>

                    <div class="topbar-right">
                        <span class="status-dot"></span>
                        <span>System Online</span>
                        <div class="top-avatar">{{ initials }}</div>
                    </div>
                </header>

                <main class="page-content">

                    <!-- Dashboard -->
<template v-if="activePage === 'dashboard' && canViewDashboard">
    <ErpDashboard />
</template>

<!-- Reports -->
<template v-else-if="activePage === 'reports' && canViewReports">
    <ErpReports />
</template>

<!-- Purchase Orders -->
<template v-else-if="activePage === 'purchase-orders' && canViewPO">
                        <div class="page-heading">
                            <div>
                                <h1>Purchase Orders</h1>
                                <p>Create, submit, approve, edit and cancel purchase orders.</p>
                            </div>
                        </div>
                        <div class="module-card">
                            <PurchaseOrders :user="user" />
                        </div>
                    </template>

                    <!-- Goods Receipts -->
                    <template v-else-if="activePage === 'grn' && canViewGRN">
                        <div class="page-heading">
                            <div>
                                <h1>Goods Receipt Notes</h1>
                                <p>Receive materials against approved purchase orders and track partial deliveries.</p>
                            </div>
                        </div>
                        <div class="module-card">
                            <GoodsReceipts :user="user" />
                        </div>
                    </template>

                    <!-- Quality Control -->
                    <template v-else-if="activePage === 'qc' && canViewQC">
                        <div class="page-heading">
                            <div>
                                <h1>Quality Control</h1>
                                <p>Inspect received materials and manage QC inspection approvals.</p>
                            </div>
                        </div>
                        <div class="module-card">
                            <QualityControl :user="user" />
                        </div>
                    </template>

                    <!-- Inventory -->
                    <template v-else-if="activePage === 'inventory' && canViewInventory">
                        <Inventory :can-post="canPostInventory" />
                    </template>

                    <!-- Supplier Invoices -->
                    <template v-else-if="activePage === 'invoices' && canViewInvoices">
                        <SupplierInvoices
                            :can-create="canCreateInvoices"
                            :can-approve="canApproveInvoices"
                        />
                    </template>
                    <!-- Supplier Payments -->
                    <SupplierPayments
                        v-else-if="activePage === 'payments' && canRecordPayments"
                    />

                    <p v-if="error" class="alert-error">
                        {{ error }}
                    </p>

                </main>
            </div>
        </div>
    </div>
</template>

<style>
/* Global styles — not scoped */
* {
    box-sizing: border-box;
}

html, body, #app {
    margin: 0;
    min-height: 100%;
    width: 100%;
}

body {
    font-family: Inter, "Segoe UI", Arial, sans-serif;
    background: #f5f7fb;
    color: #182238;
}

button, input, select {
    font: inherit;
}

button {
    cursor: pointer;
}

.erp-app {
    min-height: 100vh;
}

/* Loading */
.loading-screen {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: #64748b;
    gap: 16px;
}

.spinner {
    width: 36px;
    height: 36px;
    border: 4px solid #dbeafe;
    border-top-color: #2563eb;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Login */
.login-page {
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background:
        radial-gradient(circle at 20% 20%, #dbeafe 0%, transparent 40%),
        linear-gradient(135deg, #f8fafc, #e9eef9);
}

.login-card {
    width: 100%;
    max-width: 430px;
    background: white;
    padding: 42px;
    border-radius: 18px;
    box-shadow: 0 18px 60px rgba(15, 23, 42, 0.10);
    border: 1px solid #e8edf5;
}

.brand-icon, .sidebar-logo {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #2563eb;
    color: white;
    border-radius: 12px;
    font-size: 23px;
    font-weight: 800;
}

.login-card h1 {
    margin: 26px 0 8px;
    font-size: 29px;
    letter-spacing: -0.8px;
}

.login-subtitle {
    color: #64748b;
    margin-bottom: 30px;
    font-size: 14px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 13px;
    font-weight: 600;
}

.form-group input {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid #d7deeb;
    border-radius: 9px;
    outline: none;
    background: white;
    transition: border-color .2s, box-shadow .2s;
}

.form-group input:focus {
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, .10);
}

.primary-btn {
    border: none;
    background: #2563eb;
    color: white;
    border-radius: 9px;
    padding: 13px 18px;
    font-weight: 600;
}

.primary-btn:hover {
    background: #1d4ed8;
}

.login-btn {
    width: 100%;
}

.login-btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}

.login-footer {
    margin: 26px 0 0;
    text-align: center;
    font-size: 12px;
    color: #94a3b8;
}

.alert-error {
    background: #fef2f2;
    color: #b91c1c;
    padding: 12px;
    border-radius: 8px;
    font-size: 13px;
}

/* Application layout */
.app-layout {
    min-height: 100vh;
    display: flex;
}

.sidebar {
    width: 252px;
    flex-shrink: 0;
    background: #111c35;
    color: white;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
}

.sidebar-brand {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 26px 20px;
    border-bottom: 1px solid rgba(255,255,255,.08);
}

.sidebar-brand strong {
    display: block;
    font-size: 15px;
}

.sidebar-brand small {
    display: block;
    margin-top: 4px;
    color: #94a3b8;
    font-size: 11px;
}

.sidebar-section-label {
    padding: 30px 22px 12px;
    color: #7486a8;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.3px;
}

.sidebar-nav {
    padding: 0 12px;
}

.sidebar-nav button {
    display: flex;
    align-items: center;
    gap: 13px;
    width: 100%;
    padding: 13px 15px;
    border: none;
    border-radius: 9px;
    background: transparent;
    color: #b8c5db;
    text-align: left;
    font-size: 13px;
    margin-bottom: 5px;
}

.sidebar-nav button:hover {
    background: rgba(255,255,255,.07);
    color: white;
}

.sidebar-nav button.active {
    background: #2563eb;
    color: white;
}

.nav-icon {
    font-size: 18px;
    width: 20px;
}

.sidebar-bottom {
    margin-top: auto;
    padding: 18px;
    border-top: 1px solid rgba(255,255,255,.08);
}

.sidebar-user {
    display: flex;
    align-items: center;
    gap: 10px;
}

.avatar, .top-avatar {
    width: 36px;
    height: 36px;
    background: #3b82f6;
    color: white;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 12px;
    flex-shrink: 0;
}

.user-info {
    min-width: 0;
}

.user-info strong {
    display: block;
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.user-info small {
    display: block;
    margin-top: 3px;
    color: #94a3b8;
    text-transform: capitalize;
    font-size: 11px;
}

.logout-btn {
    width: 100%;
    margin-top: 16px;
    padding: 11px;
    border: 1px solid rgba(255,255,255,.15);
    background: transparent;
    color: #e2e8f0;
    border-radius: 8px;
}

.logout-btn:hover {
    background: rgba(255,255,255,.08);
}

/* Topbar */
.main-area {
    flex: 1;
    min-width: 0;
}

.topbar {
    height: 72px;
    background: white;
    border-bottom: 1px solid #e8edf5;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 32px;
    font-size: 13px;
}

.breadcrumb {
    color: #94a3b8;
}

.topbar-right {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #64748b;
    font-size: 12px;
}

.status-dot {
    width: 8px;
    height: 8px;
    background: #22c55e;
    border-radius: 50%;
}

.top-avatar {
    margin-left: 14px;
}

/* Page */
.page-content {
    padding: 34px;
    max-width: 1500px;
    margin: 0 auto;
}

.page-heading {
    margin-bottom: 26px;
}

.page-heading h1 {
    margin: 0 0 8px;
    font-size: 27px;
    letter-spacing: -.6px;
}

.page-heading p {
    color: #64748b;
    margin: 0;
    font-size: 14px;
}

.welcome-banner {
    background: linear-gradient(110deg, #1e40af, #2563eb);
    color: white;
    border-radius: 15px;
    padding: 32px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 25px;
    overflow: hidden;
}

.eyebrow {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 1.6px;
    opacity: .75;
}

.welcome-banner h2 {
    margin: 10px 0;
    font-size: 23px;
}

.welcome-banner p {
    max-width: 520px;
    color: #dbeafe;
    line-height: 1.7;
    font-size: 13px;
}

.banner-btn {
    background: white;
    color: #1d4ed8;
    border: none;
    border-radius: 8px;
    padding: 11px 16px;
    margin-top: 12px;
    font-weight: 600;
}

.banner-art {
    font-size: 68px;
    font-weight: 900;
    opacity: .12;
    letter-spacing: -5px;
}

/* Overview cards */
.overview-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 25px;
}

.overview-card {
    background: white;
    border: 1px solid #e8edf5;
    border-radius: 13px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.card-icon {
    width: 39px;
    height: 39px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    margin-bottom: 5px;
}

.card-icon.blue { background: #dbeafe; color: #2563eb; }
.card-icon.orange { background: #ffedd5; color: #ea580c; }
.card-icon.purple { background: #f3e8ff; color: #9333ea; }
.card-icon.green { background: #dcfce7; color: #16a34a; }

.overview-card span {
    color: #64748b;
    font-size: 12px;
}

.overview-card strong {
    font-size: 15px;
}

.overview-card small {
    color: #94a3b8;
    font-size: 11px;
}

/* Account panel */
.info-panel, .module-card {
    background: white;
    border: 1px solid #e8edf5;
    border-radius: 13px;
    padding: 25px;
}

.info-panel {
    max-width: 650px;
}

.info-panel h3 {
    margin: 0 0 20px;
    font-size: 16px;
}

.account-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    padding: 13px 0;
    border-bottom: 1px solid #f1f5f9;
    font-size: 13px;
}

.account-row > span:first-child {
    color: #64748b;
}

.role-pill {
    background: #dbeafe;
    color: #1d4ed8;
    padding: 6px 11px;
    border-radius: 20px;
    font-size: 11px;
    text-transform: capitalize;
    font-weight: 600;
}

.manager-note {
    margin-top: 20px;
    background: #eff6ff;
    color: #1d4ed8;
    padding: 14px;
    border-radius: 8px;
    font-size: 12px;
    line-height: 1.6;
}

.module-card {
    overflow-x: auto;
}

/* Responsive */
@media (max-width: 1100px) {
    .overview-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .app-layout {
        flex-direction: column;
    }

    .sidebar {
        width: 100%;
        min-height: auto;
    }

    .sidebar-brand {
        padding: 15px 20px;
    }

    .sidebar-section-label {
        display: none;
    }

    .sidebar-nav {
        display: flex;
        padding: 10px;
    }

    .sidebar-nav button {
        width: auto;
        margin: 0;
    }

    .sidebar-bottom {
        padding: 12px;
    }

    .topbar {
        height: 60px;
        padding: 0 18px;
    }

    .page-content {
        padding: 20px 15px;
    }

    .overview-grid {
        grid-template-columns: 1fr;
    }

    .welcome-banner {
        padding: 24px;
    }

    .banner-art {
        display: none;
    }

    .login-card {
        padding: 28px;
    }
}
</style>
