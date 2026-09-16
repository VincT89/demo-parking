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
Route::get('/subscription-payment/{uuid}/result', [\App\Http\Controllers\SubscriptionStripeController::class, 'publicResult'])
    ->name('public.subscription-payment.result');

Route::get('/booking/{externalId}/payment', [\App\Http\Controllers\PublicPaymentController::class, 'show'])->name('public.booking.payment');
Route::post('/booking/{externalId}/stripe/checkout', [\App\Http\Controllers\StripePaymentController::class, 'checkout'])->name('public.booking.stripe.checkout');
Route::post('/booking/{externalId}/paypal/order', [\App\Http\Controllers\PayPalPaymentController::class, 'createOrder'])->name('public.booking.paypal.order');
Route::post('/booking/{externalId}/paypal/capture', [\App\Http\Controllers\PayPalPaymentController::class, 'capture'])->name('public.booking.paypal.capture');
Route::post('/booking/{externalId}/onsite/confirm', [\App\Http\Controllers\PublicPaymentController::class, 'confirmOnsite'])->name('public.booking.onsite.confirm');
Route::post('/webhooks/stripe', [\App\Http\Controllers\StripeWebhookController::class, 'handle'])->name('webhooks.stripe');
Route::post('/webhooks/paypal', [\App\Http\Controllers\PayPalWebhookController::class, 'handle'])->name('webhooks.paypal');

Route::middleware(['auth', 'verified'])->group(function () {

    // Dashboard
    Route::get('/customers/lookup', [\App\Http\Controllers\CustomerController::class, 'lookup'])->name('customers.lookup');
    Route::resource('customers', \App\Http\Controllers\CustomerController::class)->except('destroy');
    Route::get('/customers/{customer}/records', [\App\Http\Controllers\CustomerController::class, 'records'])->name('customers.records');
    Route::post('/customers/{customer}/records', [\App\Http\Controllers\CustomerController::class, 'link'])->name('customers.records.link');
    Route::patch('/customers/{customer}/status', [\App\Http\Controllers\CustomerController::class, 'status'])
        ->middleware('can:manage-parkings')->name('customers.status');

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

    // Garage: abbonamenti, ingressi giornalieri e pagamenti al banco
    Route::get('/garage', \App\Http\Controllers\GarageDashboardController::class)
        ->name('garage.index');
    Route::resource('/garage/subscriptions', \App\Http\Controllers\ParkingSubscriptionController::class)
        ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
        ->names('garage.subscriptions');
    Route::resource('/garage/stays', \App\Http\Controllers\ParkingStayController::class)
        ->only(['index', 'create', 'store', 'show'])
        ->names('garage.stays');
    Route::get('/garage/stays/{stay}/ticket', [ParkingTicketController::class, 'showStay'])
        ->name('garage.stays.ticket');
    Route::post('/garage/stays/{stay}/checkout', [\App\Http\Controllers\ParkingStayController::class, 'checkOut'])
        ->name('garage.stays.checkout');
    Route::post('/garage/subscriptions/{subscription}/payments', [\App\Http\Controllers\GaragePaymentController::class, 'storeForSubscription'])
        ->name('garage.subscriptions.payments.store');
    Route::post('/garage/stays/{stay}/payments', [\App\Http\Controllers\GaragePaymentController::class, 'storeForStay'])
        ->name('garage.stays.payments.store');
    Route::post('/garage/payments/{payment}/reverse', [\App\Http\Controllers\GaragePaymentController::class, 'reverse'])
        ->name('garage.payments.reverse');
    Route::post('/garage/subscriptions/{subscription}/stripe-checkout', [\App\Http\Controllers\SubscriptionStripeController::class, 'checkout'])
        ->name('garage.subscriptions.stripe.checkout');
    Route::get('/garage/payments/{payment}/stripe-simulation', [\App\Http\Controllers\SubscriptionStripeController::class, 'simulation'])
        ->name('garage.stripe.simulation');
    Route::post('/garage/payments/{payment}/stripe-simulation', [\App\Http\Controllers\SubscriptionStripeController::class, 'simulate'])
        ->name('garage.stripe.simulate');

    // Pianificazione navette aeroportuali
    Route::get('/shuttles', [\App\Http\Controllers\ShuttleController::class, 'index'])->name('shuttles.index');
    Route::post('/shuttles/generate', [\App\Http\Controllers\ShuttleController::class, 'generate'])->name('shuttles.generate');
    Route::post('/shuttles/trips', [\App\Http\Controllers\ShuttleController::class, 'storeTrip'])->name('shuttles.trips.store');
    Route::put('/shuttles/trips/{trip}', [\App\Http\Controllers\ShuttleController::class, 'updateTrip'])->name('shuttles.trips.update');
    Route::post('/shuttles/trips/{trip}/cancel', [\App\Http\Controllers\ShuttleController::class, 'cancelTrip'])->name('shuttles.trips.cancel');
    Route::post('/shuttles/trips/{trip}/assignments', [\App\Http\Controllers\ShuttleController::class, 'assign'])->name('shuttles.assignments.store');
    Route::delete('/shuttles/assignments/{assignment}', [\App\Http\Controllers\ShuttleController::class, 'unassign'])->name('shuttles.assignments.destroy');

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
            ->only(['store', 'update', 'destroy']);

        Route::resource('admin/garage-rates', \App\Http\Controllers\GarageRateController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->names('garage.rates');

        Route::get('admin/shuttles/settings', [\App\Http\Controllers\ShuttleSettingController::class, 'edit'])
            ->name('shuttles.settings.edit');
        Route::put('admin/shuttles/settings/{parking}', [\App\Http\Controllers\ShuttleSettingController::class, 'update'])
            ->name('shuttles.settings.update');
        Route::post('admin/shuttles/vehicles', [\App\Http\Controllers\ShuttleSettingController::class, 'storeVehicle'])
            ->name('shuttles.vehicles.store');
        Route::put('admin/shuttles/vehicles/{vehicle}', [\App\Http\Controllers\ShuttleSettingController::class, 'updateVehicle'])
            ->name('shuttles.vehicles.update');
        Route::delete('admin/shuttles/vehicles/{vehicle}', [\App\Http\Controllers\ShuttleSettingController::class, 'destroyVehicle'])
            ->name('shuttles.vehicles.destroy');
    });

    // Calendario
    Route::get('/calendar', [\App\Http\Controllers\CalendarController::class, 'index'])->name('calendar');
    Route::get('/calendar/data', [\App\Http\Controllers\CalendarController::class, 'data'])->name('calendar.data');
    Route::get('/calendar/day', [\App\Http\Controllers\CalendarController::class, 'day'])->name('calendar.day');
    Route::get('/calendar/day/export', [\App\Http\Controllers\CalendarController::class, 'exportDay'])->name('calendar.day.export');
});

require __DIR__ . '/auth.php';
