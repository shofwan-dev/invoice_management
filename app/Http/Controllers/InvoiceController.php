<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentStep;
use App\Models\InvoiceAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str; // Tambahkan ini untuk generate unique_hash
use Carbon\Carbon; // Tambahkan ini untuk kemudahan penggunaan Carbon
use Illuminate\Support\Facades\Response; 


class InvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = Invoice::query();

        // Search
        if ($search = $request->get('q')) {
            $query->where('invoice_number', 'like', "%{$search}%")
                ->orWhere('client_name', 'like', "%{$search}%");
        }

        // Filter by status
        if ($status = $request->get('status')) {
            if ($status == 'lunas') {
                $query->where('remaining_amount', 0);
            } elseif ($status == 'belum_lunas') {
                $query->where('remaining_amount', '>', 0);
            }
        }

        // Filter by date range
        if ($startDate = $request->get('start_date')) {
            $query->whereDate('invoice_date', '>=', $startDate);
        }
        
        if ($endDate = $request->get('end_date')) {
            $query->whereDate('invoice_date', '<=', $endDate);
        }

        // Sorting
        if ($sort = $request->get('sort')) {
            $query->orderBy($sort, $request->get('direction', 'asc'));
        } else {
            $query->latest();
        }

        $invoices = $query->paginate(15);

        return view('invoices.index', compact('invoices'));
    }

    public function create()
    {
        $invoiceNumber = $this->generateInvoiceNumber();
        return view('invoices.create', compact('invoiceNumber'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'invoice_number' => 'required|unique:invoices,invoice_number',
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:20',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'total_amount' => 'required|numeric|min:0',
            'remaining_amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'attachments.*' => 'nullable|file|max:5120', // Max 5MB
        ]);

        try {
            $invoice = new Invoice($request->all());
            $invoice->created_by = Auth::id();
            $invoice->public_token = Str::random(60);
            
            // Generate unique_hash untuk verifikasi QR
            $invoice->unique_hash = Str::random(32); 

            $invoice->save();

            // Simpan items
            foreach ($request->items as $itemData) {
                $invoice->items()->create($itemData);
            }

            // Simpan attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if ($file) {
                        $path = $file->store('invoice_attachments', 'public');
                        $invoice->attachments()->create([
                            'file_path' => $path,
                            'file_name' => $file->getClientOriginalName(),
                            'file_type' => $file->getClientMimeType(),
                            'file_size' => $file->getSize(),
                        ]);
                    }
                }
            }
            
            // Simpan payment steps (default initial payment)
            $invoice->paymentSteps()->create([
                'step_date' => $invoice->invoice_date,
                'amount_paid' => $invoice->total_amount - $invoice->remaining_amount,
                'description' => 'Pembayaran Awal',
            ]);

            return redirect()->route('invoices.index')->with('success', 'Invoice berhasil dibuat.');

        } catch (\Exception $e) {
            Log::error('Gagal membuat Invoice: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['items', 'paymentSteps', 'attachments', 'creator']);
        return view('invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load(['items', 'paymentSteps', 'attachments']);
        return view('invoices.edit', compact('invoice'));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $request->validate([
            'invoice_number' => 'required|unique:invoices,invoice_number,' . $invoice->id,
            'client_name' => 'required|string|max:255',
            'client_email' => 'nullable|email|max:255',
            'client_phone' => 'nullable|string|max:20',
            'invoice_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:invoice_date',
            'total_amount' => 'required|numeric|min:0',
            'remaining_amount' => 'required|numeric|min:0',
            'payment_method' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items.*.description' => 'required|string|max:255',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'attachments.*' => 'nullable|file|max:5120',
        ]);

        try {
            $invoice->fill($request->all());
            
            // Fail-safe untuk unique_hash saat update
            if (empty($invoice->unique_hash)) {
                $invoice->unique_hash = Str::random(32); 
            }

            $invoice->save();

            // Update items
            $invoice->items()->delete();
            foreach ($request->items as $itemData) {
                $invoice->items()->create($itemData);
            }
            
            // Handle attachments
            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    if ($file) {
                        $path = $file->store('invoice_attachments', 'public');
                        $invoice->attachments()->create([
                            'file_path' => $path,
                            'file_name' => $file->getClientOriginalName(),
                            'file_type' => $file->getClientMimeType(),
                            'file_size' => $file->getSize(),
                        ]);
                    }
                }
            }

            // Logic payment steps (hanya contoh dasar)
            $invoice->paymentSteps()->where('description', 'Pembayaran Awal')->delete(); // Hapus step awal
            $invoice->paymentSteps()->create([
                'step_date' => $invoice->invoice_date,
                'amount_paid' => $invoice->total_amount - $invoice->remaining_amount,
                'description' => 'Pembayaran Awal',
            ]);


            return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice berhasil diperbarui.');

        } catch (\Exception $e) {
            Log::error('Gagal memperbarui Invoice: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function destroy(Invoice $invoice)
    {
        try {
            // Hapus attachments dari storage
            foreach ($invoice->attachments as $attachment) {
                Storage::disk('public')->delete($attachment->file_path);
            }
            $invoice->delete();
            return redirect()->route('invoices.index')->with('success', 'Invoice berhasil dihapus.');
        } catch (\Exception $e) {
            Log::error('Gagal menghapus Invoice: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menghapus invoice: ' . $e->getMessage());
        }
    }

    public function downloadAttachment(InvoiceAttachment $attachment)
    {
        // Pastikan attachment terkait dengan invoice yang bisa diakses user
        if ($attachment->invoice->created_by !== Auth::id()) {
            abort(403);
        }
        
        $filePath = $attachment->file_path; // Path relatif di disk 'public'
        $fileName = $attachment->file_name; // Nama file asli

       if (Storage::disk('public')->exists($filePath)) {
            
            $absolutePath = storage_path('app/public/' . $filePath);
            return response()->download($absolutePath, $fileName);
        }
        
        abort(404, 'File tidak ditemukan.');
    }
    
    // --- METHOD EXPORT PDF YANG TELAH DIPERBAIKI (FIX UNREACHABLE CODE & QR HASH) ---
    public function exportPdf(Invoice $invoice)
    {
        // 1. FAIL-SAFE: Pastikan unique_hash ada. Jika kosong, generate dan simpan.
        if (empty($invoice->unique_hash)) {
            $invoice->unique_hash = Str::random(32); 
            $invoice->save(); 
        }

        // Set locale ke Indonesia
        Carbon::setLocale('id');

        $invoice->load(['items','paymentSteps','creator']);

        // Gunakan view yang sama
        $view = 'pdf.invoice_modern';

        // Prepare logo URL
        $logoUrl = '';
        $companyLogo = \App\Models\Setting::get('company_logo');
        if ($companyLogo) {
            $storageRelativePath = $companyLogo;
            if (!str_starts_with($storageRelativePath, 'storage/')) {
                $storageRelativePath = 'storage/' . $storageRelativePath;
            }
            $logoUrl = asset($storageRelativePath);
        }

        // 2. LOGIC QR CODE
        $uniqueHash = $invoice->unique_hash; 
        $verificationUrl = null;
        
        if ($uniqueHash) {
            // Membuat URL penuh untuk verifikasi publik
            $verificationUrl = route('invoice.verify.public', ['unique_hash' => $uniqueHash], true); 
        }

        // Kumpulkan semua variabel
        $data = compact('invoice', 'logoUrl', 'verificationUrl');

        $pdf = Pdf::loadView($view, $data); 
        $pdf->setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,
            'defaultPaperSize' => 'a4',
            'defaultPaperOrientation' => 'landscape', 
            'dpi' => 96
        ]);

        // Format filename
        $formattedDate = Carbon::parse($invoice->invoice_date)->format('Y-m-d');
        $cleanClientName = preg_replace('/[^a-zA-Z0-9\s]/', '', $invoice->client_name);
        $cleanClientName = str_replace(' ', '-', $cleanClientName);
        $filename = "{$formattedDate}-{$cleanClientName}-{$invoice->invoice_number}.pdf";

        // 3. RETURN FINAL (FIX UNREACHABLE CODE)
        return $pdf->download($filename);
    }
    
    // ... (metode lainnya)

    // Metode sendWhatsapp dan generateInvoiceNumber tetap sama
    
    // ...
    private function sendWhatsapp(Invoice $invoice, $recipient)
    {
        // ... (Logika WhatsApp notifikasi)
    }

    public function sendWhatsappRoute(Invoice $invoice)
    {
        // ... (Logika sendWhatsappRoute)
    }
    
    public function continuePayment(Invoice $invoice)
    {
        // ... (Logika continuePayment)
    }

    private function generateInvoiceNumber()
    {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        $day = date('d');
        
        // Cari invoice terakhir dengan prefix yang sama
        $lastInvoice = Invoice::where('invoice_number', 'like', $prefix . '-%')
            ->orderBy('invoice_number', 'desc')
            ->first();
        
        if ($lastInvoice) {
            // Extract number dari invoice number terakhir
            $lastNumber = intval(substr($lastInvoice->invoice_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        $newInvoiceNumber = $prefix . '-' . $year . $month . $day . '-' . $newNumber;
        
        return $newInvoiceNumber;
    }
}