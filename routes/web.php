<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\AvailabilityBlockController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\ElectronicInvoiceController;
use App\Http\Controllers\OperationalSettingsController;
use App\Http\Controllers\ParkingTicketController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

Route::post('/locale', function (Request $request) {
    $validated = $request->validate([
        'locale' => ['required', Rule::in(array_keys(config('app.supported_locales', [])))],
    ]);

    $request->session()->put('locale', $validated['locale']);

    return back();
})->name('locale.update');

Route::get('/', function () {
    return redirect()->route('dashboard');
});

// Public Booking Form (Laravel)
Route::get('/booking', [\App\Http\Controllers\PublicBookingController::class, 'showForm'])->name('public.booking.form');
Route::post('/booking/check-availability', [\App\Http\Controllers\PublicBookingController::class, 'checkAvailability'])->name('public.booking.check');
Route::post('/booking/store', [\App\Http\Controllers\PublicBookingController::class, 'store'])->name('public.booking.store');
Route::get('/booking/success/{uuid}', [\App\Http\Controllers\PublicBookingController::class, 'success'])->name('public.booking.success');

Route::get('/booking/{externalId}/payment', [\App\Http\Controllers\PublicPaymentController::class, 'show'])->name('public.booking.payment');
Route::post('/booking/{externalId}/stripe/checkout', [\App\Http\Controllers\StripePaymentController::class, 'checkout'])->name('public.booking.stripe.checkout');
Route::post('/booking/{externalId}/paypal/order', [\App\Http\Controllers\PayPalPaymentController::class, 'createOrder'])->name('public.booking.paypal.order');
Route::post('/booking/{externalId}/paypal/capture', [\App\Http\Controllers\PayPalPaymentController::class, 'capture'])->name('public.booking.paypal.capture');
Route::post('/booking/{externalId}/onsite/confirm', [\App\Http\Controllers\PublicPaymentController::class, 'confirmOnsite'])->name('public.booking.onsite.confirm');
Route::post('/webhooks/stripe', [\App\Http\Controllers\StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
Route::post('/webhooks/paypal', [\App\Http\Controllers\PayPalWebhookController::class, 'handle'])->name('webhooks.paypal');

Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Admin Account
    Route::get('/admin/account', [\App\Http\Controllers\AdminAccountController::class, 'edit'])
        ->name('admin.account.edit');
    Route::put('/admin/account/email', [\App\Http\Controllers\AdminAccountController::class, 'updateEmail'])
        ->name('admin.account.email.update');
    Route::put('/admin/account/password', [\App\Http\Controllers\AdminAccountController::class, 'updatePassword'])
        ->name('admin.account.password.update');

    // Report
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');

    // Analytics 
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts');
    Route::post('/alerts/{id}/dismiss', [AlertController::class, 'dismiss'])->name('alerts.dismiss');

    // Esportazione prenotazioni
    Route::get('/reservations/export', [ReservationController::class, 'export'])
    ->name('reservations.export');  

    // Prenotazioni
    Route::post('/reservations/{reservation}/toggle-movement', [ReservationController::class, 'toggleMovement'])->name('reservations.toggle-movement');
    Route::post('/reservations/{reservation}/mark-paid', [ReservationController::class, 'markPaid'])->name('reservations.mark-paid');
    Route::get('/reservations/{reservation}/ticket', [ParkingTicketController::class, 'show'])->name('reservations.ticket');
    Route::get('/reservations/{reservation}/invoice/create', [ElectronicInvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/reservations/{reservation}/invoice', [ElectronicInvoiceController::class, 'store'])->name('invoices.store');
    Route::resource('reservations', ReservationController::class);

    Route::get('/invoices', [ElectronicInvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/invoices/{invoice}', [ElectronicInvoiceController::class, 'show'])->name('invoices.show');
    Route::post('/invoices/{invoice}/submit', [ElectronicInvoiceController::class, 'submit'])->name('invoices.submit');
    Route::post('/invoices/{invoice}/refresh', [ElectronicInvoiceController::class, 'refresh'])->name('invoices.refresh');
    Route::post('/invoices/{invoice}/simulate', [ElectronicInvoiceController::class, 'simulate'])->name('invoices.simulate');
    Route::get('/invoices/{invoice}/xml', [ElectronicInvoiceController::class, 'downloadXml'])->name('invoices.xml');

    // Blocchi disponibilità
    Route::resource('availability-blocks', AvailabilityBlockController::class)
        ->only(['index', 'create', 'store', 'destroy']);

    // Piattaforme — solo admin
    Route::middleware('can:manage-platforms')->group(function () {
        Route::resource('platforms', PlatformController::class)
            ->except(['show']);
        Route::post('platforms/{platform}/attach', [PlatformController::class, 'attachToParking'])
            ->name('platforms.attach');

        Route::post('/platforms/sync', \App\Http\Controllers\ManualPlatformSyncController::class)
            ->name('platforms.sync');
            
        Route::post('/platforms/historical-sync', \App\Http\Controllers\HistoricalPlatformSyncController::class)
        ->name('platforms.historical-sync');

    Route::post('/platforms/future-sync', \App\Http\Controllers\FuturePlatformSyncController::class)
        ->name('platforms.future-sync');
        
        Route::post('platforms/{platform}/mappings', [PlatformController::class, 'storeMapping'])
            ->name('platforms.mappings.store');
        Route::delete('platforms/mappings/{mapping}', [PlatformController::class, 'destroyMapping'])
            ->name('platforms.mappings.destroy');

        Route::get('/sync-logs', [\App\Http\Controllers\SyncLogController::class, 'index'])->name('sync-logs.index');
    });

    // Parcheggio & Inventario — solo admin
    Route::middleware('can:manage-parkings')->group(function () {
        Route::get('admin/operational-settings', [OperationalSettingsController::class, 'edit'])
            ->name('operational-settings.edit');
        Route::put('admin/operational-settings/{parking}', [OperationalSettingsController::class, 'update'])
            ->name('operational-settings.update');

        Route::resource('admin/parkings', \App\Http\Controllers\ParkingController::class)
            ->except(['show'])
            ->names([
                'index'   => 'parkings.index',
                'create'  => 'parkings.create',
                'store'   => 'parkings.store',
                'edit'    => 'parkings.edit',
                'update'  => 'parkings.update',
                'destroy' => 'parkings.destroy',
            ]);
            
        Route::put('admin/parkings/{parking}/products', [\App\Http\Controllers\ParkingProductController::class, 'upsertForParking'])
            ->name('parkings.products.upsert');

        Route::resource('admin/parkings.allocations', \App\Http\Controllers\ParkingCapacityAllocationController::class)
            ->except(['index', 'show', 'edit']);
    });

    // Calendario
    Route::get('/calendar', [\App\Http\Controllers\CalendarController::class, 'index'])->name('calendar');
    Route::get('/calendar/data', [\App\Http\Controllers\CalendarController::class, 'data'])->name('calendar.data');
    Route::get('/calendar/day', [\App\Http\Controllers\CalendarController::class, 'day'])->name('calendar.day');
    Route::get('/calendar/day/export', [\App\Http\Controllers\CalendarController::class, 'exportDay'])->name('calendar.day.export');
});

require __DIR__ . '/auth.php';
