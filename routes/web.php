<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CompanyController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\FbrIntegrationController;
use App\Http\Controllers\Api\V1\FbrInvoiceController;
use App\Http\Controllers\Api\V1\FbrReferenceController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\ItemController;
use App\Http\Controllers\Api\V1\ItemPriceController;
use App\Http\Controllers\Api\V1\OverviewController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Middleware\EnsureCompanyConfigured;
use App\Models\User;
use Database\Seeders\SingleUserSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
 * These first-party API endpoints intentionally use the web group for
 * cookie sessions and CSRF protection. Sanctum authenticates protected routes.
 */
Route::get('api/v1/auth/registration-status', function () {
    return response()->json(['data' => ['can_register' => ! config('erp.single_user_mode', true) || ! User::query()->exists()]])
        ->header('Cache-Control', 'no-store, private');
})->middleware('throttle:30,1')->name('api.v1.auth.registration-status');

Route::get('api/system/setup-master-user', function (Request $request) {
    $provided = (string) $request->query('key', '');
    $expected = (string) config('erp.setup_secret_key', '');
    if (strlen($expected) < 32) {
        return response()->json(['success' => false, 'message' => 'Master setup is not configured on this server.'], 503)->header('Cache-Control', 'no-store, private');
    }
    if ($provided === '' || ! hash_equals($expected, $provided)) {
        return response()->json(['success' => false, 'message' => 'Unauthorized access.'], 401)->header('Cache-Control', 'no-store, private');
    }
    if (User::query()->exists()) {
        return response()->json(['success' => false, 'message' => 'Master user already exists; setup cannot reset credentials.'], 409)->header('Cache-Control', 'no-store, private');
    }
    try {
        Artisan::call('db:seed', ['--class' => SingleUserSeeder::class, '--force' => true]);

        return response()->json(['success' => true, 'message' => 'Master user initialized. Sign in and configure Company & FBR Setup.', 'default_email' => config('erp.admin_default_email')])->header('Cache-Control', 'no-store, private');
    } catch (Throwable $exception) {
        Log::error('Master-user setup failed.', ['exception' => $exception::class]);

        return response()->json(['success' => false, 'message' => 'Setup failed. Verify server configuration and logs.'], 500)->header('Cache-Control', 'no-store, private');
    }
})->middleware('throttle:3,1')->name('system.setup-master-user');

Route::prefix('api/v1/auth')->name('api.v1.auth.')->group(function (): void {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:registration')->name('register');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::put('password', [AuthController::class, 'changePassword'])->middleware('throttle:6,1')->name('password');
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    });
});

Route::prefix('api/company')->middleware('auth:sanctum')->group(function (): void {
    Route::get('fbr/status', [FbrIntegrationController::class, 'status'])->name('api.company.fbr.status');
    Route::post('verify-fbr', [FbrIntegrationController::class, 'verifyFbr'])->middleware('throttle:6,1')->name('api.company.verify-fbr');
    Route::post('sandbox-token', [FbrIntegrationController::class, 'saveSandboxToken'])->middleware('throttle:6,1')->name('api.company.sandbox-token');
    Route::post('run-sandbox-certification', [FbrIntegrationController::class, 'runSandboxCertification'])->middleware('throttle:3,1')->name('api.company.run-sandbox-certification');
    Route::post('production-token', [FbrIntegrationController::class, 'saveProductionToken'])->middleware('throttle:6,1')->name('api.company.production-token');
});

Route::prefix('api/v1/companies')->middleware('auth:sanctum')->group(function (): void {
    Route::post('/', [CompanyController::class, 'store']);
    Route::get('{company}', [CompanyController::class, 'show'])->whereNumber('company');
    Route::put('{company}', [CompanyController::class, 'update'])->whereNumber('company');
    Route::get('{company}/members', [CompanyController::class, 'members'])->whereNumber('company');
    Route::post('{company}/members', [CompanyController::class, 'addMember'])->whereNumber('company');
    Route::put('{company}/members/{user}', [CompanyController::class, 'updateMember'])->whereNumber(['company', 'user']);
    Route::delete('{company}/members/{user}', [CompanyController::class, 'removeMember'])->whereNumber(['company', 'user']);
});

Route::prefix('api/v1/companies/{company}/customers')->middleware(['auth:sanctum', EnsureCompanyConfigured::class])->whereNumber('company')->name('api.v1.customers.')->group(function (): void {
    Route::get('/', [CustomerController::class, 'index'])->name('index');
    Route::post('/', [CustomerController::class, 'store'])->name('store');
    Route::get('{customer}', [CustomerController::class, 'show'])->whereNumber('customer')->name('show');
    Route::put('{customer}', [CustomerController::class, 'update'])->whereNumber('customer')->name('update');
    Route::patch('{customer}/status', [CustomerController::class, 'status'])->whereNumber('customer')->name('status');
    Route::delete('{customer}', [CustomerController::class, 'destroy'])->whereNumber('customer')->name('destroy');
});

Route::prefix('api/v1/companies/{company}/items')->middleware(['auth:sanctum', EnsureCompanyConfigured::class])->whereNumber('company')->name('api.v1.items.')->group(function (): void {
    Route::get('/', [ItemController::class, 'index'])->name('index');
    Route::post('/', [ItemController::class, 'store'])->name('store');
    Route::get('{item}', [ItemController::class, 'show'])->whereNumber('item')->name('show');
    Route::put('{item}', [ItemController::class, 'update'])->whereNumber('item')->name('update');
    Route::patch('{item}/status', [ItemController::class, 'status'])->whereNumber('item')->name('status');
    Route::delete('{item}', [ItemController::class, 'destroy'])->whereNumber('item')->name('destroy');
    Route::prefix('{item}/prices')->whereNumber('item')->name('prices.')->group(function (): void {
        Route::get('/', [ItemPriceController::class, 'index'])->name('index');
        Route::post('/', [ItemPriceController::class, 'store'])->name('store');
        Route::get('{price}', [ItemPriceController::class, 'show'])->whereNumber('price')->name('show');
        Route::put('{price}', [ItemPriceController::class, 'update'])->whereNumber('price')->name('update');
        Route::delete('{price}', [ItemPriceController::class, 'destroy'])->whereNumber('price')->name('destroy');
    });
});

Route::get('api/v1/companies/{company}/fbr/hs-codes', [FbrReferenceController::class, 'hsCodes'])->middleware('auth:sanctum')->whereNumber('company')->name('api.v1.fbr.hs-codes');

Route::get('api/v1/companies/{company}/fbr/units', [FbrReferenceController::class, 'units'])->middleware('auth:sanctum')->whereNumber('company');
Route::get('api/v1/companies/{company}/fbr/sale-types', [FbrReferenceController::class, 'saleTypes'])->middleware('auth:sanctum')->whereNumber('company');
Route::get('api/v1/companies/{company}/fbr/provinces', [FbrReferenceController::class, 'provinces'])->middleware('auth:sanctum')->whereNumber('company');
Route::get('api/v1/companies/{company}/fbr/rates', [FbrReferenceController::class, 'rates'])->middleware('auth:sanctum')->whereNumber('company');
Route::get('api/v1/companies/{company}/fbr/sro-schedules', [FbrReferenceController::class, 'sroSchedules'])->middleware('auth:sanctum')->whereNumber('company');
Route::get('api/v1/companies/{company}/fbr/sro-items', [FbrReferenceController::class, 'sroItems'])->middleware('auth:sanctum')->whereNumber('company');
Route::get('api/v1/companies/{company}/fbr/buyer-verification', [FbrReferenceController::class, 'buyerVerification'])->middleware('auth:sanctum')->whereNumber('company');

Route::prefix('api/v1/companies/{company}')->middleware(['auth:sanctum', EnsureCompanyConfigured::class])->whereNumber('company')->group(function (): void {
    Route::post('invoices/{invoice}/fbr/reconcile', [FbrInvoiceController::class, 'reconcile'])->whereNumber('invoice')->middleware('throttle:fbr-reconcile');
    Route::get('invoices/{invoice}/fbr', [FbrInvoiceController::class, 'show'])->whereNumber('invoice');
    Route::post('invoices/{invoice}/fbr/{operation}', [FbrInvoiceController::class, 'run'])->whereNumber('invoice')->whereIn('operation', ['validate', 'submit'])->middleware('throttle:fbr-invoice');
    Route::get('overview', [OverviewController::class, 'show']);
    Route::get('invoices', [InvoiceController::class, 'index']);
    Route::post('invoices/preview', [InvoiceController::class, 'preview']);
    Route::post('invoices/{invoice}/approve', [InvoiceController::class, 'approve'])->whereNumber('invoice');
    Route::post('invoices/{invoice}/reopen', [InvoiceController::class, 'reopen'])->whereNumber('invoice');
    Route::post('invoices', [InvoiceController::class, 'store']);
    Route::get('invoices/lookups/{kind}', [InvoiceController::class, 'lookups'])->whereIn('kind', ['customers', 'items']);
    Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->whereNumber('invoice');
    Route::put('invoices/{invoice}', [InvoiceController::class, 'update'])->whereNumber('invoice');
    Route::delete('invoices/{invoice}', [InvoiceController::class, 'destroy'])->whereNumber('invoice');
    Route::get('stock/balances', [StockController::class, 'balances']);
    Route::get('stock/movements', [StockController::class, 'index']);
    Route::post('stock/movements', [StockController::class, 'store']);
    Route::post('stock/movements/{movement}/reverse', [StockController::class, 'reverse'])->whereNumber('movement');
});

/*
 * Run one explicitly named migration. The browser only submits the filename;
 * the server resolves it inside database/migrations and never accepts a path.
 */
Route::get('/run-migration', function () {
    if (! auth('sanctum')->check()) {
        return redirect('/#/login');
    }

    return view('run-migration');
})->name('run-migration.form');

Route::post('/run-migration', function (Request $request) {
    $validated = $request->validate([
        'migration' => [
            'required',
            'string',
            'max:255',
            'regex:/\\A[0-9]{4}_[0-9]{2}_[0-9]{2}_[0-9]{6}_[A-Za-z0-9_]+\\.php\\z/',
        ],
    ]);

    $filename = $validated['migration'];
    $migrationDirectory = realpath(database_path('migrations'));
    $migrationFile = realpath(database_path('migrations/'.$filename));

    if (
        $migrationDirectory === false ||
        $migrationFile === false ||
        dirname($migrationFile) !== $migrationDirectory ||
        ! is_file($migrationFile)
    ) {
        return back()
            ->withErrors(['migration' => 'Migration file was not found in database/migrations.'])
            ->withInput();
    }

    try {
        $exitCode = Artisan::call('migrate', [
            '--path' => 'database/migrations/'.$filename,
            '--force' => true,
        ]);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            Log::error('Selected migration command failed.', [
                'migration' => $filename,
                'exit_code' => $exitCode,
                'output' => $output,
            ]);

            return back()->with('migration_error', $output !== '' ? $output : 'Migration command failed. Check the Laravel log.');
        }

        return back()->with('migration_success', $output !== '' ? $output : 'Migration command completed successfully.');
    } catch (Throwable $exception) {
        Log::error('Selected migration threw an exception.', [
            'migration' => $filename,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);

        return back()->with('migration_error', 'Migration failed. Check storage/logs/laravel.log for details.');
    }
})->middleware(['auth:sanctum', 'throttle:3,1'])->name('run-migration.execute');
