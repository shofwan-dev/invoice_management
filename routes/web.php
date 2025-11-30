<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;

Route::post('/invoices/bulk-export', [InvoiceController::class, 'bulkExport'])->name('invoices.bulk-export');
Route::delete('/invoices/bulk-delete', [InvoiceController::class, 'bulkDelete'])->name('invoices.bulk-delete');
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return redirect()->route('invoices.index');
});

Route::get('/dashboard', function () {
    $invoices = \App\Models\Invoice::all();
    $totalInvoices = $invoices->count();
    $totalAmount = $invoices->sum('total_amount');
    $recentInvoices = \App\Models\Invoice::orderBy('created_at', 'desc')->limit(5)->get();
    return view('dashboard', compact('totalInvoices', 'totalAmount', 'recentInvoices'));
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

// Invoice routes (application scaffold)
use App\Http\Controllers\SettingController;

Route::middleware(['auth'])->group(function () {
    Route::resource('invoices', InvoiceController::class);
    Route::get('/invoices/{invoice}/export', [InvoiceController::class, 'exportPdf'])->name('invoices.export');
    Route::get('invoices/{invoice}/export', [InvoiceController::class, 'exportPdf'])->name('invoices.export');
    Route::post('/invoices/{invoice}/continue', [InvoiceController::class, 'continuePayment'])->name('invoices.continue');
    Route::post('invoices/{invoice}/whatsapp', [InvoiceController::class, 'sendWhatsapp'])->name('invoices.whatsapp');
    
    // Bulk actions
    Route::post('invoices/bulk-export', [InvoiceController::class, 'bulkExport'])->name('invoices.bulk-export');
    Route::delete('invoices/bulk-delete', [InvoiceController::class, 'bulkDelete'])->name('invoices.bulk-delete');
    
    // Settings routes
    Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::post('settings', [SettingController::class, 'update'])->name('settings.update');
    
    // Debug route
    Route::get('debug-logo', function () {
        return view('debug-logo');
    })->name('debug.logo');
    
    Route::get('test-logo-pdf', function () {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.test-logo');
        $pdf->setOptions(['isRemoteEnabled' => true]);
        return $pdf->download('test-logo.pdf');
    })->name('test.logo.pdf');
});
