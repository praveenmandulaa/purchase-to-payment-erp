<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\ErpAuthController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\QcInspectionController;
use App\Http\Controllers\QcLookupController;
use App\Http\Controllers\Erp\InventoryController;
use App\Http\Controllers\Erp\SupplierPaymentController;
use App\Http\Controllers\Erp\DashboardController;
use App\Http\Controllers\Erp\ReportController;

use App\Http\Controllers\Erp\SupplierInvoiceController;

use App\Models\Item;
use App\Models\Supplier;


Route::get('/erp', function () {
    return view('erp');
});
Route::post('/erp/auth/login', [
    ErpAuthController::class, 'login'
])->middleware('throttle:5,1');

Route::middleware('auth')->group(function () {
    Route::get('/erp/auth/me', [
        ErpAuthController::class, 'me'
    ]);

    Route::post('/erp/auth/logout', [
        ErpAuthController::class, 'logout'
    ]);
});

Route::middleware([
    'auth',
    'erp.permission:po.create'
])->get('/erp/test/create-po', function () {
    return response()->json([
        'message' => 'Permission granted: Create PO',
    ]);
});

Route::middleware([
    'auth',
    'erp.permission:po.approve'
])->get('/erp/test/approve-po', function () {
    return response()->json([
        'message' => 'Permission granted: Approve PO',
    ]);
});


Route::prefix('erp/po')
    ->middleware(['auth', 'erp.active'])
    ->group(function () {

        Route::get('/', [
            PurchaseOrderController::class, 'index'
        ])->middleware('erp.permission:po.view');

        Route::post('/', [
            PurchaseOrderController::class, 'store'
        ])->middleware('erp.permission:po.create');

        Route::get('/{po}', [
            PurchaseOrderController::class, 'show'
        ])->middleware('erp.permission:po.view');

        Route::post('/{po}/submit', [
            PurchaseOrderController::class, 'submit'
        ])->middleware('erp.permission:po.submit');

        Route::post('/{po}/approve', [
            PurchaseOrderController::class, 'approve'
        ])->middleware('erp.permission:po.approve');

        Route::post('/{po}/reject', [
            PurchaseOrderController::class, 'reject'
        ])->middleware('erp.permission:po.reject');
        Route::put('/{po}', [
        PurchaseOrderController::class, 'update',
        ])->middleware('erp.permission:po.edit');

        Route::post('/{po}/cancel', [
            PurchaseOrderController::class, 'cancel',
        ])->middleware('erp.permission:po.cancel');
    });

    Route::prefix('erp/masters')
    ->middleware(['auth', 'erp.active'])
    ->group(function () {

        Route::get('/items', function () {
            return Item::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'uom']);
        })->middleware('erp.permission:po.view');

        Route::get('/suppliers', function () {
            return Supplier::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']);
        })->middleware('erp.permission:po.view');
        
    });
    Route::middleware(['auth', 'erp.active'])
    ->prefix('erp')
    ->group(function () {

        Route::get('/grn/warehouses', [
            GoodsReceiptController::class,
            'warehouses',
        ])->middleware('erp.permission:grn.view');

        Route::get('/grn/approved-pos', [
            GoodsReceiptController::class,
            'approvedPurchaseOrders',
        ])->middleware('erp.permission:grn.view');

        Route::get('/grn', [
            GoodsReceiptController::class,
            'index',
        ])->middleware('erp.permission:grn.view');

        Route::post('/grn', [
            GoodsReceiptController::class,
            'store',
        ])->middleware('erp.permission:grn.create');

        Route::get('/grn/{grn}', [
            GoodsReceiptController::class,
            'show',
        ])->middleware('erp.permission:grn.view');
        
    });
Route::middleware(['auth', 'erp.active'])
    ->prefix('erp')
    ->group(function () {

        Route::get('/qc/eligible-grns', [
            QcLookupController::class,
            'eligibleGrns',
        ])->middleware('erp.permission:qc.view');

        Route::get('/qc/inspections', [
            QcInspectionController::class,
            'index',
        ])->middleware('erp.permission:qc.view');

        Route::post('/qc/inspections', [
            QcInspectionController::class,
            'store',
        ])->middleware('erp.permission:qc.create');

        Route::get('/qc/inspections/{inspection}', [
            QcInspectionController::class,
            'show',
        ])->middleware('erp.permission:qc.view');

        Route::post('/qc/inspections/{inspection}/submit', [
            QcInspectionController::class,
            'submit',
        ])->middleware('erp.permission:qc.submit');

        Route::post('/qc/inspections/{inspection}/approve', [
            QcInspectionController::class,
            'approve',
        ])->middleware('erp.permission:qc.approve');

        Route::post('/qc/inspections/{inspection}/reject', [
            QcInspectionController::class,
            'reject',
        ])->middleware('erp.permission:qc.reject');
    });
    
Route::get('/erp/inventory/balances', [
    InventoryController::class,
    'balances',
])->middleware('erp.permission:stock.view');

Route::get('/erp/inventory/eligible-grns', [
    InventoryController::class,
    'eligibleGrns',
])->middleware('erp.permission:stock.post');

Route::get('/erp/inventory/postings', [
    InventoryController::class,
    'postings',
])->middleware('erp.permission:stock.view');

Route::post('/erp/invoices', [
    SupplierInvoiceController::class,
    'store',
])->middleware('erp.permission:invoice.create');

Route::post('/erp/inventory/grn/{grn}/post', [
    InventoryController::class,
    'post',
])->middleware('erp.permission:stock.post');




/*
|--------------------------------------------------------------------------
| Supplier Invoice Routes
|--------------------------------------------------------------------------
*/

Route::get('/erp/invoices/approved-pos', [
    SupplierInvoiceController::class,
    'approvedPurchaseOrders',
])->middleware('erp.permission:invoice.create');

Route::get('/erp/invoices', [
    SupplierInvoiceController::class,
    'index',
])->middleware('erp.permission:invoice.view');

Route::post('/erp/invoices', [
    SupplierInvoiceController::class,
    'store',
])->middleware('erp.permission:invoice.create');

Route::get('/erp/invoices/{invoice}', [
    SupplierInvoiceController::class,
    'show',
])->middleware('erp.permission:invoice.view');

Route::post('/erp/invoices/{invoice}/match', [
    SupplierInvoiceController::class,
    'match',
])->middleware('erp.permission:invoice.create');

Route::post('/erp/invoices/{invoice}/submit', [
    SupplierInvoiceController::class,
    'submit',
])->middleware('erp.permission:invoice.create');

Route::post('/erp/invoices/{invoice}/approve', [
    SupplierInvoiceController::class,
    'approve',
])->middleware('erp.permission:invoice.approve');

Route::post('/erp/invoices/{invoice}/reject', [
    SupplierInvoiceController::class,
    'reject',
])->middleware('erp.permission:invoice.approve');



/*
|--------------------------------------------------------------------------
| Supplier Payment Routes
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'erp.active'])
    ->prefix('erp/payments')
    ->group(function () {

        Route::get('/approved-invoices', [
            SupplierPaymentController::class,
            'approvedInvoices',
        ])->middleware('erp.permission:payment.record');

        Route::get('/', [
            SupplierPaymentController::class,
            'index',
        ])->middleware('erp.permission:payment.record');

        Route::post('/', [
            SupplierPaymentController::class,
            'store',
        ])->middleware('erp.permission:payment.record');

        Route::get('/{payment}', [
            SupplierPaymentController::class,
            'show',
        ])->middleware('erp.permission:payment.record');
    });
Route::middleware([
    'auth',
    'erp.active',
    'erp.permission:dashboard.view',
])->get(
    '/erp/dashboard/summary',
    [DashboardController::class, 'summary']
);

Route::middleware([
    'auth',
    'erp.active',
    'erp.permission:reports.view',
])->prefix('erp/reports')->group(function () {
    Route::get('/', [ReportController::class, 'index']);
    Route::get('/export', [ReportController::class, 'export']);
});