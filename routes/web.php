<?php

use App\Http\Controllers\Admin\ColorController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SizeController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AiAssistanceController;
use App\Http\Controllers\AiOrderAssistantController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerOrderController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FacebookConversationController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\IntegrationController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductColorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductionController;
use App\Http\Controllers\ProductSizeController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SupplierOrderController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware('permission:view messenger conversations')->prefix('messenger')->name('messenger.')->group(function () {
        Route::get('/conversations', [FacebookConversationController::class, 'index'])->name('index');
        Route::get('/conversations/{conversation}', [FacebookConversationController::class, 'show'])->name('show');
        Route::post('/conversations/{conversation}/take-over', [FacebookConversationController::class, 'takeOver'])->middleware('permission:take over messenger conversations')->name('take-over');
        Route::post('/conversations/{conversation}/return-to-ai', [FacebookConversationController::class, 'returnToAi'])->middleware('permission:take over messenger conversations')->name('return-to-ai');
        Route::post('/conversations/{conversation}/prepare-summary', [FacebookConversationController::class, 'prepareSummary'])->middleware('permission:create messenger orders')->name('prepare-summary');
        Route::post('/conversations/{conversation}/confirm', [FacebookConversationController::class, 'confirm'])->middleware('permission:create messenger orders')->name('confirm');
        Route::post('/conversations/{conversation}/create-order', [FacebookConversationController::class, 'createOrder'])->middleware('permission:create messenger orders')->name('create-order');
    });

    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])
        ->middleware('permission:view dashboard')
        ->name('dashboard.stats');

    Route::get('/dashboard/stock-health', [DashboardController::class, 'stockHealth'])
        ->middleware('permission:view dashboard')
        ->name('dashboard.stock-health');

    Route::get('/dashboard/stock-movement-trend', [DashboardController::class, 'stockMovementTrend'])
        ->middleware('permission:view dashboard')
        ->name('dashboard.stock-movement-trend');

    Route::get('/dashboard/low-stock-by-product', [DashboardController::class, 'lowStockByProduct'])
        ->middleware('permission:view dashboard')
        ->name('dashboard.low-stock-by-product');

    Route::get('/dashboard/active-products', [DashboardController::class, 'activeProducts'])
        ->middleware('permission:view dashboard')
        ->name('dashboard.active-products');

    Route::get('/dashboard/recent-movements/data', [DashboardController::class, 'recentMovementsData'])
        ->middleware('permission:view dashboard')
        ->name('dashboard.recent-movements.data');

    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:view dashboard')
        ->name('dashboard');

    Route::middleware('permission:view products')->group(function () {
        Route::get('/products/data', [ProductController::class, 'data'])->name('products.data');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
    });

    Route::get('/products/create', [ProductController::class, 'create'])
        ->middleware('permission:create products')
        ->name('products.create');

    Route::get('/products/preview-code', [ProductController::class, 'previewCode'])
        ->middleware('permission:create products')
        ->name('products.preview-code');

    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('permission:create products')
        ->name('products.store');

    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])
        ->middleware('permission:edit products')
        ->name('products.edit');

    Route::put('/products/{product}', [ProductController::class, 'update'])
        ->middleware('permission:edit products')
        ->name('products.update');

    Route::delete('/products/{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:delete products')
        ->name('products.destroy');

    Route::get('/products/sizes/suggestions', [ProductSizeController::class, 'suggestions'])
        ->middleware('permission:edit products')
        ->name('products.sizes.suggestions');

    Route::get('/products/{product}/sizes/data', [ProductSizeController::class, 'data'])
        ->middleware('permission:edit products')
        ->name('products.sizes.data');

    Route::post('/products/{product}/sizes', [ProductSizeController::class, 'store'])
        ->middleware('permission:edit products')
        ->name('products.sizes.store');

    Route::post('/products/{product}/sizes/bulk', [ProductSizeController::class, 'storeBulk'])
        ->middleware('permission:edit products')
        ->name('products.sizes.bulk');

    Route::put('/products/{product}/sizes/{size}', [ProductSizeController::class, 'update'])
        ->middleware('permission:edit products')
        ->name('products.sizes.update');

    Route::delete('/products/{product}/sizes/{size}', [ProductSizeController::class, 'destroy'])
        ->middleware('permission:edit products')
        ->name('products.sizes.destroy');

    Route::get('/products/colors/suggestions', [ProductColorController::class, 'suggestions'])
        ->middleware('permission:edit products')
        ->name('products.colors.suggestions');

    Route::get('/products/{product}/colors/data', [ProductColorController::class, 'data'])
        ->middleware('permission:edit products')
        ->name('products.colors.data');

    Route::post('/products/{product}/colors', [ProductColorController::class, 'store'])
        ->middleware('permission:edit products')
        ->name('products.colors.store');

    Route::post('/products/{product}/colors/bulk', [ProductColorController::class, 'storeBulk'])
        ->middleware('permission:edit products')
        ->name('products.colors.bulk');

    Route::put('/products/{product}/colors/{color}', [ProductColorController::class, 'update'])
        ->middleware('permission:edit products')
        ->name('products.colors.update');

    Route::post('/products/{product}/colors/{color}/image', [ProductColorController::class, 'uploadImage'])
        ->middleware('permission:edit products')
        ->name('products.colors.image.upload');

    Route::delete('/products/{product}/colors/{color}/image', [ProductColorController::class, 'deleteImage'])
        ->middleware('permission:edit products')
        ->name('products.colors.image.destroy');

    Route::delete('/products/{product}/colors/{color}', [ProductColorController::class, 'destroy'])
        ->middleware('permission:edit products')
        ->name('products.colors.destroy');

    Route::get('/products/{product}/inventory/data', [ProductController::class, 'inventoryData'])
        ->middleware('permission:view inventory')
        ->name('products.inventory.data');

    Route::get('/products/{product}/inventory', [ProductController::class, 'manageInventory'])
        ->middleware('permission:view inventory')
        ->name('products.inventory');

    Route::get('/inventory/cell/{cell}/history', [InventoryController::class, 'cellHistory'])
        ->middleware('permission:view inventory')
        ->name('inventory.cell-history');

    Route::post('/inventory/stock-in', [InventoryController::class, 'stockIn'])
        ->middleware('permission:stock in')
        ->name('inventory.stock-in');

    Route::post('/inventory/stock-out', [InventoryController::class, 'stockOut'])
        ->middleware('permission:stock out')
        ->name('inventory.stock-out');

    Route::post('/inventory/reserve', [InventoryController::class, 'reserve'])
        ->middleware('permission:reserve stock')
        ->name('inventory.reserve');

    Route::post('/inventory/release', [InventoryController::class, 'release'])
        ->middleware('permission:release stock')
        ->name('inventory.release');

    Route::post('/inventory/damage', [InventoryController::class, 'damage'])
        ->middleware('permission:damage stock')
        ->name('inventory.damage');

    Route::post('/inventory/adjust', [InventoryController::class, 'adjust'])
        ->middleware('permission:adjust stock')
        ->name('inventory.adjust');

    Route::post('/inventory/bulk', [InventoryController::class, 'bulk'])
        ->middleware('permission:view inventory')
        ->name('inventory.bulk');

    Route::get('/orders/data', [CustomerOrderController::class, 'data'])
        ->middleware('permission:view orders')
        ->name('orders.data');

    Route::get('/orders/board/data', [CustomerOrderController::class, 'boardData'])
        ->middleware('permission:view orders')
        ->name('orders.board.data');

    Route::get('/orders/board', [CustomerOrderController::class, 'board'])
        ->middleware('permission:view orders')
        ->name('orders.board');

    Route::get('/orders/product-cells', [CustomerOrderController::class, 'productCells'])
        ->middleware('permission:create orders')
        ->name('orders.product-cells');

    Route::get('/orders/create', [CustomerOrderController::class, 'create'])
        ->middleware('permission:create orders')
        ->name('orders.create');

    Route::get('/orders', [CustomerOrderController::class, 'index'])
        ->middleware('permission:view orders')
        ->name('orders.index');

    Route::post('/orders', [CustomerOrderController::class, 'store'])
        ->middleware('permission:create orders')
        ->name('orders.store');

    Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])
        ->middleware('permission:view orders')
        ->name('orders.show');

    Route::post('/orders/{order}/fulfill', [CustomerOrderController::class, 'fulfill'])
        ->middleware('permission:fulfill orders')
        ->name('orders.fulfill');

    Route::post('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel'])
        ->middleware('permission:cancel orders')
        ->name('orders.cancel');

    Route::post('/orders/{order}/layouts', [CustomerOrderController::class, 'storeLayout'])
        ->middleware('permission:fulfill orders')
        ->name('orders.layouts.store');

    Route::post('/orders/{order}/layouts/{layout}/approve', [CustomerOrderController::class, 'approveLayout'])
        ->middleware('permission:fulfill orders')
        ->name('orders.layouts.approve');

    Route::put('/orders/{order}/delivery', [CustomerOrderController::class, 'updateDelivery'])
        ->middleware('permission:fulfill orders')
        ->name('orders.delivery.update');

    Route::post('/orders/{order}/release', [CustomerOrderController::class, 'release'])
        ->middleware('permission:fulfill orders')
        ->name('orders.release');

    Route::get('/customers/data', [CustomerController::class, 'data'])
        ->middleware('permission:view customers')
        ->name('customers.data');

    Route::get('/customers/create', [CustomerController::class, 'create'])
        ->middleware('permission:manage customers')
        ->name('customers.create');

    Route::get('/customers', [CustomerController::class, 'index'])
        ->middleware('permission:view customers')
        ->name('customers.index');

    Route::post('/customers', [CustomerController::class, 'store'])
        ->middleware('permission:manage customers')
        ->name('customers.store');

    Route::get('/customers/{customer}', [CustomerController::class, 'show'])
        ->middleware('permission:view customers')
        ->name('customers.show');

    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])
        ->middleware('permission:manage customers')
        ->name('customers.edit');

    Route::put('/customers/{customer}', [CustomerController::class, 'update'])
        ->middleware('permission:manage customers')
        ->name('customers.update');

    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])
        ->middleware('permission:manage customers')
        ->name('customers.destroy');

    Route::get('/finance', [FinanceController::class, 'index'])
        ->middleware('permission:view finance')
        ->name('finance.index');

    Route::post('/orders/{order}/payments', [FinanceController::class, 'storePayment'])
        ->middleware('permission:manage finance')
        ->name('orders.payments.store');

    Route::post('/orders/{order}/payments/{payment}/reverse', [FinanceController::class, 'reversePayment'])
        ->middleware('permission:manage finance')
        ->name('orders.payments.reverse');

    Route::get('/production/board/data', [ProductionController::class, 'boardData'])
        ->middleware('permission:view production')
        ->name('production.board.data');

    Route::get('/production', [ProductionController::class, 'index'])
        ->middleware('permission:view production')
        ->name('production.index');

    Route::post('/production/{order}/advance', [ProductionController::class, 'advance'])
        ->middleware('permission:manage production')
        ->name('production.advance');

    Route::get('/supplier-orders/data', [SupplierOrderController::class, 'data'])
        ->middleware('permission:view supplier orders')
        ->name('supplier-orders.data');

    Route::get('/supplier-orders/product-cells', [SupplierOrderController::class, 'productCells'])
        ->middleware('permission:create supplier orders')
        ->name('supplier-orders.product-cells');

    Route::get('/supplier-orders/create', [SupplierOrderController::class, 'create'])
        ->middleware('permission:create supplier orders')
        ->name('supplier-orders.create');

    Route::get('/supplier-orders', [SupplierOrderController::class, 'index'])
        ->middleware('permission:view supplier orders')
        ->name('supplier-orders.index');

    Route::post('/supplier-orders', [SupplierOrderController::class, 'store'])
        ->middleware('permission:create supplier orders')
        ->name('supplier-orders.store');

    Route::get('/supplier-orders/{po}', [SupplierOrderController::class, 'show'])
        ->middleware('permission:view supplier orders')
        ->name('supplier-orders.show');

    Route::post('/supplier-orders/{po}/receive', [SupplierOrderController::class, 'receive'])
        ->middleware('permission:receive supplier orders')
        ->name('supplier-orders.receive');

    Route::post('/supplier-orders/{po}/cancel', [SupplierOrderController::class, 'cancel'])
        ->middleware('permission:cancel supplier orders')
        ->name('supplier-orders.cancel');

    Route::get('/integrations', [IntegrationController::class, 'index'])
        ->middleware('permission:manage integrations')
        ->name('integrations.index');

    Route::put('/integrations/{provider}', [IntegrationController::class, 'update'])
        ->middleware('permission:manage integrations')
        ->whereIn('provider', ['openai', 'gemini'])
        ->name('integrations.update');

    Route::post('/integrations/{provider}/test', [IntegrationController::class, 'test'])
        ->middleware('permission:manage integrations')
        ->whereIn('provider', ['openai', 'gemini'])
        ->name('integrations.test');

    Route::delete('/integrations/{provider}', [IntegrationController::class, 'disconnect'])
        ->middleware('permission:manage integrations')
        ->whereIn('provider', ['openai', 'gemini'])
        ->name('integrations.disconnect');

    Route::post('/integrations/{provider}/default', [IntegrationController::class, 'setDefault'])
        ->middleware('permission:manage integrations')
        ->whereIn('provider', ['openai', 'gemini'])
        ->name('integrations.default');

    Route::get('/ai/order-assistant/drafts', [AiOrderAssistantController::class, 'drafts'])
        ->middleware('permission:use ai assistant')
        ->name('ai.order-assistant.drafts');

    Route::post('/ai/order-assistant/analyze', [AiOrderAssistantController::class, 'analyze'])
        ->middleware('permission:use ai assistant')
        ->name('ai.order-assistant.analyze');

    Route::post('/ai/order-assistant/provider', [AiOrderAssistantController::class, 'setProvider'])
        ->middleware('permission:manage integrations')
        ->name('ai.order-assistant.set-provider');

    Route::get('/ai/order-assistant/drafts/{draft}', [AiOrderAssistantController::class, 'show'])
        ->middleware('permission:use ai assistant')
        ->name('ai.order-assistant.drafts.show');

    Route::put('/ai/order-assistant/drafts/{draft}', [AiOrderAssistantController::class, 'update'])
        ->middleware('permission:use ai assistant')
        ->name('ai.order-assistant.drafts.update');

    Route::post('/ai/order-assistant/drafts/{draft}/convert', [AiOrderAssistantController::class, 'convert'])
        ->middleware('permission:use ai assistant')
        ->name('ai.order-assistant.drafts.convert');

    Route::post('/ai/order-assistant/drafts/{draft}/reject', [AiOrderAssistantController::class, 'reject'])
        ->middleware('permission:use ai assistant')
        ->name('ai.order-assistant.drafts.reject');

    Route::post('/ai/order-assistant/drafts/{draft}/image', [AiOrderAssistantController::class, 'uploadImage'])
        ->middleware('permission:use ai assistant')
        ->name('ai.order-assistant.drafts.image.upload');

    Route::delete('/ai/order-assistant/drafts/{draft}/image', [AiOrderAssistantController::class, 'deleteImage'])
        ->middleware('permission:use ai assistant')
        ->name('ai.order-assistant.drafts.image.destroy');

    Route::get('/ai/order-assistant', [AiOrderAssistantController::class, 'index'])
        ->middleware('permission:use ai assistant')
        ->name('ai.order-assistant.index');

    Route::get('/ai/assistance', [AiAssistanceController::class, 'index'])
        ->middleware('permission:use ai assistance')
        ->name('ai.assistance.index');

    Route::post('/ai/assistance/ask', [AiAssistanceController::class, 'ask'])
        ->middleware('permission:use ai assistance')
        ->name('ai.assistance.ask');

    Route::post('/ai/assistance/export/csv', [AiAssistanceController::class, 'exportCsv'])
        ->middleware('permission:use ai assistance')
        ->name('ai.assistance.export.csv');

    Route::post('/ai/assistance/export/pdf', [AiAssistanceController::class, 'exportPdf'])
        ->middleware('permission:use ai assistance')
        ->name('ai.assistance.export.pdf');

    Route::get('/reports/stock-history/filter-options', [ReportController::class, 'stockHistoryFilterOptions'])
        ->middleware('permission:view stock history')
        ->name('reports.stock-history.filter-options');

    Route::get('/reports/stock-history/data', [ReportController::class, 'stockHistoryData'])
        ->middleware('permission:view stock history')
        ->name('reports.stock-history.data');

    Route::get('/reports/stock-history', [ReportController::class, 'stockHistory'])
        ->middleware('permission:view stock history')
        ->name('reports.stock-history');

    Route::get('/reports/low-stock/data', [ReportController::class, 'lowStockData'])
        ->middleware('permission:view low stock report')
        ->name('reports.low-stock.data');

    Route::get('/reports/low-stock', [ReportController::class, 'lowStock'])
        ->middleware('permission:view low stock report')
        ->name('reports.low-stock');

    Route::get('/reports/out-of-stock/data', [ReportController::class, 'outOfStockData'])
        ->middleware('permission:view out of stock report')
        ->name('reports.out-of-stock.data');

    Route::get('/reports/out-of-stock', [ReportController::class, 'outOfStock'])
        ->middleware('permission:view out of stock report')
        ->name('reports.out-of-stock');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::middleware('permission:manage users')->group(function () {
            Route::get('/users/data', [UserController::class, 'data'])->name('users.data');
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        });

        Route::middleware('permission:manage roles')->group(function () {
            Route::get('/roles/data', [RoleController::class, 'data'])->name('roles.data');
            Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        });

        Route::middleware('permission:manage sizes')->group(function () {
            Route::get('/sizes/data', [SizeController::class, 'data'])->name('sizes.data');
            Route::get('/sizes', [SizeController::class, 'index'])->name('sizes.index');
            Route::post('/sizes', [SizeController::class, 'store'])->name('sizes.store');
            Route::put('/sizes/{size}', [SizeController::class, 'update'])->name('sizes.update');
            Route::delete('/sizes/{size}', [SizeController::class, 'destroy'])->name('sizes.destroy');
        });

        Route::middleware('permission:manage colors')->group(function () {
            Route::get('/colors/data', [ColorController::class, 'data'])->name('colors.data');
            Route::get('/colors', [ColorController::class, 'index'])->name('colors.index');
            Route::post('/colors', [ColorController::class, 'store'])->name('colors.store');
            Route::put('/colors/{color}', [ColorController::class, 'update'])->name('colors.update');
            Route::delete('/colors/{color}', [ColorController::class, 'destroy'])->name('colors.destroy');
        });

        Route::middleware('permission:manage suppliers')->group(function () {
            Route::get('/suppliers/data', [SupplierController::class, 'data'])->name('suppliers.data');
            Route::get('/suppliers/create', [SupplierController::class, 'create'])->name('suppliers.create');
            Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
            Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
            Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
        });
    });
});
