<?php

use App\Http\Controllers\Admin\ColorController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SizeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\ProductColorController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductSizeController;
use App\Http\Controllers\ReportController;
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

    Route::get('/dashboard/stats', [DashboardController::class, 'stats'])
        ->middleware('permission:view dashboard')
        ->name('dashboard.stats');

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

    Route::delete('/products/{product}/colors/{color}', [ProductColorController::class, 'destroy'])
        ->middleware('permission:edit products')
        ->name('products.colors.destroy');

    Route::get('/products/{product}/inventory/data', [ProductController::class, 'inventoryData'])
        ->middleware('permission:view inventory')
        ->name('products.inventory.data');

    Route::get('/products/{product}/inventory', [ProductController::class, 'manageInventory'])
        ->middleware('permission:view inventory')
        ->name('products.inventory');

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
    });
});
