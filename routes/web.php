<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\PublicInvoiceController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Public Route (Invoice Verification)
Route::get('/invoice/verify/{unique_hash}', [PublicInvoiceController::class, 'verify'])
    ->name('invoice.verify.public');

// Landing Page Redirect
Route::get('/', function () {
    return redirect()->route('invoices.index');
});

// Dashboard Route (with Auth and Verification)
Route::get('/dashboard', function () {
    // Statistik Dashboard
    $invoices = \App\Models\Invoice::all(); 
    $totalInvoices = $invoices->count();
    $totalAmount = $invoices->sum('total_amount');
    $unpaidInvoices = \App\Models\Invoice::where('remaining_amount', '>', 0)->count();
    $paidInvoices = \App\Models\Invoice::where('remaining_amount', 0)->count();
    $recentInvoices = \App\Models\Invoice::orderBy('created_at', 'desc')->limit(5)->get();
    
    // Statistik bulanan (tahun ini)
    $currentYear = date('Y');
    $monthlyStats = \App\Models\Invoice::select(
        DB::raw('MONTH(invoice_date) as month'),
        DB::raw('COUNT(*) as invoice_count'),
        DB::raw('SUM(total_amount) as total_amount')
    )
    ->whereYear('invoice_date', $currentYear)
    ->groupBy('month')
    ->orderBy('month')
    ->get();
    
    // Statistik tahunan (5 tahun terakhir)
    $yearlyStats = \App\Models\Invoice::select(
        DB::raw('YEAR(invoice_date) as year'),
        DB::raw('COUNT(*) as invoice_count'),
        DB::raw('SUM(total_amount) as total_amount')
    )
    ->whereYear('invoice_date', '>=', $currentYear - 4)
    ->groupBy('year')
    ->orderBy('year', 'desc')
    ->get();
    
    return view('dashboard', compact(
        'totalInvoices', 
        'totalAmount', 
        'unpaidInvoices',
        'paidInvoices',
        'recentInvoices',
        'monthlyStats',
        'yearlyStats'
    ));
})->middleware(['auth', 'verified'])->name('dashboard');

// Authentication Routes (dari Breeze/Jetstream)
require __DIR__.'/auth.php';

// --- Application Routes (Membutuhkan otentikasi) ---
Route::middleware(['auth'])->group(function () {
    
    // 1. Profile Routes
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // 2. Invoice Routes
    // PENTING: Custom routes diletakkan DI ATAS resource agar tidak dianggap sebagai {id}
    
    // Bulk Actions (Menggunakan POST agar kompatibel dengan form HTML)
    Route::post('/invoices/bulk-export', [InvoiceController::class, 'bulkExport'])->name('invoices.bulk-export');
    Route::post('/invoices/bulk-delete', [InvoiceController::class, 'bulkDelete'])->name('invoices.bulk-delete');

    // Single Item Custom Actions
    Route::get('/invoices/{invoice}/export', [InvoiceController::class, 'exportPdf'])->name('invoices.export');
    Route::post('/invoices/{invoice}/continue', [InvoiceController::class, 'continuePayment'])->name('invoices.continue');
    Route::post('/invoices/{invoice}/whatsapp', [InvoiceController::class, 'sendWhatsapp'])->name('invoices.whatsapp');
    
    // Resource Route (CRUD Standar - Diletakkan paling bawah dalam grup invoice)
    Route::resource('invoices', InvoiceController::class);

    // 3. Settings Routes
    Route::get('/settings', [SettingController::class, 'edit'])->name('settings.edit');
    Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/test-whatsapp', [SettingController::class, 'testWhatsApp'])->name('settings.test-whatsapp');
    
    // 4. Debug Routes
    Route::get('/debug-logo', function () {
        return view('debug-logo');
    })->name('debug.logo');
    
    Route::get('/test-logo-pdf', function () {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.test-logo');
        $pdf->setOptions(['isRemoteEnabled' => true]);
        return $pdf->download('test-logo.pdf');
    })->name('test.logo.pdf');
});