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

class InvoiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = Invoice::query();

        if ($search = $request->get('q')) {
            $query->where('invoice_number', 'like', "%{$search}%")
                ->orWhere('client_name', 'like', "%{$search}%");
        }

        if ($sort = $request->get('sort')) {
            $dir = $request->get('dir', 'desc');
            $query->orderBy($sort, $dir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $invoices = $query->withCount(['items'])->paginate(15)->appends($request->query());

        return view('invoices.index', compact('invoices'));
    }

    public function create()
    {
        return view('invoices.create');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['items', 'paymentSteps', 'creator']);
        return view('invoices.show', compact('invoice'));
    }

    public function edit(Invoice $invoice)
    {
        $invoice->load(['items','paymentSteps','attachments']);
        
        // Jika invoice baru diduplikat, tampilkan pesan
        $isDuplicated = session('is_duplicated', false);
        if ($isDuplicated) {
            session()->forget('is_duplicated');
        }
        
        return view('invoices.create', compact('invoice', 'isDuplicated'));
    }

    public function store(Request $request)
    {
        \Log::info('=== START INVOICE STORE ===');

        try {
            // Validasi dasar dulu
            $validated = $request->validate([
                'invoice_date' => 'required|date',
                'client_name' => 'required|string|max:255',
                'items' => 'required|array|min:1',
            ]);

            \Log::info('Basic validation passed');

            // Buat invoice dulu tanpa items
            $invoice = new Invoice();
            $invoice->invoice_date = $request->invoice_date;
            $invoice->payment_deadline = $request->payment_deadline;
            $invoice->client_name = $request->client_name;
            $invoice->created_by = Auth::id();
            $invoice->template = $request->input('template', 'default');
            $invoice->total_amount = 0;

            \Log::info('Invoice object created', ['invoice' => $invoice->toArray()]);

            // Simpan invoice untuk mendapatkan ID
            $invoice->save();
            \Log::info('Invoice saved with ID: ' . $invoice->id);

            // Process items
            $total = 0;
            if ($request->has('items')) {
                foreach ($request->items as $index => $itemData) {
                    \Log::info('Processing item ' . $index, $itemData);

                    // Clean price
                    $price = $itemData['price'];
                    if (is_string($price)) {
                        $price = str_replace('.', '', $price);
                        $price = str_replace(',', '.', $price);
                    }
                    $price = (float) $price;

                    $qty = (int) $itemData['qty'];
                    $subtotal = $qty * $price;

                    $invoice->items()->create([
                        'description' => $itemData['description'],
                        'qty' => $qty,
                        'price' => $price,
                        'subtotal' => $subtotal,
                    ]);

                    $total += $subtotal;
                    \Log::info('Item created', ['qty' => $qty, 'price' => $price, 'subtotal' => $subtotal]);
                }
            }

            // Process payment steps
            if ($request->has('payment_steps')) {
                foreach ($request->payment_steps as $index => $paymentData) {
                    \Log::info('Processing payment step ' . $index, $paymentData);

                    // Clean amount
                    $amount = $paymentData['amount'];
                    if (is_string($amount)) {
                        $amount = str_replace('.', '', $amount);
                        $amount = str_replace(',', '.', $amount);
                    }
                    $amount = (float) $amount;

                    $invoice->paymentSteps()->create([
                        'step_number' => $index + 1,
                        'amount' => $amount,
                        'payment_date' => $paymentData['payment_date'] ?? null,
                        'bank_name' => $paymentData['bank_name'] ?? null,
                    ]);
                }
            }

            // Update total amount
            $invoice->total_amount = $total;
            $invoice->save();

            \Log::info('Invoice completed', ['total' => $total, 'invoice_id' => $invoice->id]);

            return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice berhasil dibuat.');

        } catch (\Exception $e) {
            \Log::error('Store invoice error: ' . $e->getMessage());
            \Log::error('File: ' . $e->getFile());
            \Log::error('Line: ' . $e->getLine());
            \Log::error('Trace: ' . $e->getTraceAsString());

            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Invoice $invoice)
    {
        \Log::info('=== START INVOICE UPDATE ===');
        \Log::info('Updating invoice ID: ' . $invoice->id);

        try {
            // Validasi dasar
            $validated = $request->validate([
                'invoice_date' => 'required|date',
                'client_name' => 'required|string|max:255',
                'items' => 'required|array|min:1',
            ]);

            \Log::info('Basic validation passed for update');

            // Update invoice data
            $invoice->invoice_date = $request->invoice_date;
            $invoice->payment_deadline = $request->payment_deadline;
            $invoice->client_name = $request->client_name;
            $invoice->template = $request->input('template', $invoice->template ?? 'default');

            \Log::info('Invoice data updated');

            // Delete existing items and payment steps
            $invoice->items()->delete();
            $invoice->paymentSteps()->delete();

            \Log::info('Old items and payment steps deleted');

            // Process new items
            $total = 0;
            foreach ($request->items as $index => $itemData) {
                \Log::info('Processing item ' . $index, $itemData);

                // Clean price
                $price = $itemData['price'];
                if (is_string($price)) {
                    $price = str_replace('.', '', $price);
                    $price = str_replace(',', '.', $price);
                }
                $price = (float) $price;

                $qty = (int) $itemData['qty'];
                $subtotal = $qty * $price;

                $invoice->items()->create([
                    'description' => $itemData['description'],
                    'qty' => $qty,
                    'price' => $price,
                    'subtotal' => $subtotal,
                ]);

                $total += $subtotal;
            }

            // Process new payment steps
            if ($request->has('payment_steps')) {
                foreach ($request->payment_steps as $index => $paymentData) {
                    \Log::info('Processing payment step ' . $index, $paymentData);

                    // Clean amount
                    $amount = $paymentData['amount'];
                    if (is_string($amount)) {
                        $amount = str_replace('.', '', $amount);
                        $amount = str_replace(',', '.', $amount);
                    }
                    $amount = (float) $amount;

                    $invoice->paymentSteps()->create([
                        'step_number' => $index + 1,
                        'amount' => $amount,
                        'payment_date' => $paymentData['payment_date'] ?? null,
                        'bank_name' => $paymentData['bank_name'] ?? null,
                    ]);
                }
            }

            // Update total
            $invoice->total_amount = $total;
            $invoice->save();

            \Log::info('Invoice updated successfully', ['total' => $total]);

            return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice berhasil diperbarui.');

        } catch (\Exception $e) {
            \Log::error('Update invoice error: ' . $e->getMessage());
            \Log::error('File: ' . $e->getFile());
            \Log::error('Line: ' . $e->getLine());
            \Log::error('Trace: ' . $e->getTraceAsString());

            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function destroy(Invoice $invoice)
    {
        // only admin can delete
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized');
        }

        // delete attachments files
        foreach ($invoice->attachments as $att) {
            Storage::disk('public')->delete($att->path);
        }

        $invoice->delete();
        return redirect()->route('invoices.index')->with('success','Invoice dihapus.');
    }

    public function exportPdf(Invoice $invoice)
    {
        // Set locale ke Indonesia
        \Carbon\Carbon::setLocale('id');
        
        $invoice->load(['items','paymentSteps','creator']);
        
        // Gunakan view yang sama seperti di dashboard
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
        
        $pdf = Pdf::loadView($view, compact('invoice', 'logoUrl'));
        $pdf->setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,
            'defaultPaperSize' => 'a4',
            'defaultPaperOrientation' => 'landscape',
            'dpi' => 96
        ]);
        
        // Format filename
        $formattedDate = \Carbon\Carbon::parse($invoice->invoice_date)->format('Y-m-d');
        $cleanClientName = preg_replace('/[^a-zA-Z0-9\s]/', '', $invoice->client_name);
        $cleanClientName = str_replace(' ', '-', $cleanClientName);
        $filename = "{$formattedDate}-{$cleanClientName}-{$invoice->invoice_number}.pdf";
        
        return $pdf->download($filename);
    }

    public function sendWhatsapp(Invoice $invoice, Request $request)
    {
        $invoice->load(['items', 'paymentSteps']);
        
        $endpoint = \App\Models\Setting::get('whatsapp_endpoint');
        $apiKey = \App\Models\Setting::get('whatsapp_api_key');
        $sender = \App\Models\Setting::get('whatsapp_sender');
        $recipient = \App\Models\Setting::get('whatsapp_number');

        if (!$endpoint || !$apiKey || !$sender || !$recipient) {
            return redirect()->route('invoices.show', $invoice)->with('error', 'Pengaturan WhatsApp belum lengkap. Silakan atur di menu Settings.');
        }

        $companyName = \App\Models\Setting::get('company_name', 'Perusahaan');
        $message = "Halo,\n\nInvoice telah disiapkan untuk Anda:\n\n";
        $message .= "📄 *Invoice: {$invoice->invoice_number}*\n";
        $message .= "🏢 Perusahaan: {$companyName}\n";
        $message .= "👤 Klien: {$invoice->client_name}\n";
        $message .= "📅 Tanggal: " . \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') . "\n";
        $message .= "💰 Total: Rp " . number_format($invoice->total_amount, 0, ',', '.') . "\n\n";

        if ($invoice->paymentSteps->count() > 0) {
            $message .= "*Tahapan Pembayaran:*\n";
            foreach ($invoice->paymentSteps as $step) {
                $message .= "Bayaran Ke-{$step->step_number}: Rp " . number_format($step->amount, 0, ',', '.') . "\n";
            }
        }

        $message .= "\nTerima kasih.\n";

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->post($endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'phone' => $recipient,
                    'sender' => $sender,
                    'message' => $message,
                ]
            ]);

            return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice berhasil dikirim via WhatsApp.');
        } catch (\Exception $e) {
            return redirect()->route('invoices.show', $invoice)->with('error', 'Gagal mengirim ke WhatsApp: ' . $e->getMessage());
        }
    }

    public function bulkExport(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return redirect()->route('invoices.index')->with('error', 'Tidak ada invoice yang dipilih.');
        }

        $invoices = Invoice::whereIn('id', $ids)->get();
        if ($invoices->isEmpty()) {
            return redirect()->route('invoices.index')->with('error', 'Invoice tidak ditemukan.');
        }

        // Jika hanya 1 invoice, export langsung
        if ($invoices->count() === 1) {
            return $this->exportPdf($invoices->first());
        }

        // Jika lebih dari 1, buat ZIP
        $zip = new \ZipArchive();
        $zipFileName = 'invoices_' . date('YmdHis') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);
        
        // Create temp directory if not exists
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
            foreach ($invoices as $invoice) {
                $invoice->load(['items', 'paymentSteps', 'creator']);
                
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
                
                $pdf = Pdf::loadView('pdf.invoice_modern', compact('invoice', 'logoUrl'));
                $pdf->setOptions(['isRemoteEnabled' => true]);
                $pdfContent = $pdf->output();
                
                // Format filename untuk setiap file dalam ZIP
                $formattedDate = \Carbon\Carbon::parse($invoice->invoice_date)->format('Y-m-d');
                $cleanClientName = preg_replace('/[^a-zA-Z0-9\s]/', '', $invoice->client_name);
                $cleanClientName = str_replace(' ', '-', $cleanClientName);
                $filename = "{$formattedDate}-{$cleanClientName}-{$invoice->invoice_number}.pdf";
                
                $zip->addFromString($filename, $pdfContent);
            }
            $zip->close();

            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
        }

        return redirect()->route('invoices.index')->with('error', 'Gagal membuat ZIP file.');
    }

    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        if (empty($ids)) {
            return redirect()->route('invoices.index')->with('error', 'Tidak ada invoice yang dipilih.');
        }

        // Check authorization
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized');
        }

        $invoices = Invoice::whereIn('id', $ids)->get();
        if ($invoices->isEmpty()) {
            return redirect()->route('invoices.index')->with('error', 'Invoice tidak ditemukan.');
        }

        foreach ($invoices as $invoice) {
            // delete attachments files
            foreach ($invoice->attachments as $att) {
                Storage::disk('public')->delete($att->path);
            }
            $invoice->delete();
        }

        return redirect()->route('invoices.index')->with('success', 'Berhasil menghapus ' . $invoices->count() . ' invoice.');
    }

    public function continuePayment(Invoice $invoice)
    {
        try {
            // Duplikat data invoice utama
            $newInvoice = $invoice->replicate();
            $newInvoice->invoice_number = $this->generateInvoiceNumber();
            $newInvoice->invoice_date = now();
            $newInvoice->payment_deadline = null; // Reset deadline
            $newInvoice->status = 'Belum Lunas';
            $newInvoice->total_received = 0;
            $newInvoice->remaining_amount = $newInvoice->total_amount;
            $newInvoice->created_by = Auth::id();
            $newInvoice->save();

            // Duplikat items
            foreach ($invoice->items as $item) {
                $newItem = $item->replicate();
                $newItem->invoice_id = $newInvoice->id;
                $newItem->save();
            }

            // Duplikat payment steps (reset status)
            foreach ($invoice->paymentSteps as $step) {
                $newStep = $step->replicate();
                $newStep->invoice_id = $newInvoice->id;
                $newStep->payment_date = null; // Reset tanggal pembayaran
                $newStep->save();
            }

            // Duplikat attachments (jika perlu)
            foreach ($invoice->attachments as $attachment) {
                $newAttachment = $attachment->replicate();
                $newAttachment->invoice_id = $newInvoice->id;
                
                // Duplikat file fisik
                $originalPath = 'storage/' . $attachment->path;
                $newFilename = 'attachment_' . $newInvoice->id . '_' . time() . '_' . $attachment->filename;
                $newPath = 'attachments/' . $newFilename;
                
                if (Storage::disk('public')->exists($attachment->path)) {
                    Storage::disk('public')->copy($attachment->path, $newPath);
                    $newAttachment->path = $newPath;
                    $newAttachment->filename = $attachment->filename;
                    $newAttachment->save();
                }
            }

            session(['is_duplicated' => true]);

            return redirect()->route('invoices.edit', $newInvoice)
                ->with('success', 'Invoice berhasil diduplikat. Silakan lanjutkan pembayaran.');

        } catch (\Exception $e) {
            \Log::error('Continue payment error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal melanjutkan pembayaran: ' . $e->getMessage());
        }
    }

    private function generateInvoiceNumber()
    {
        $prefix = 'INV';
        $year = date('Y');
        $month = date('m');
        
        // Cari invoice terakhir dengan prefix yang sama
        $lastInvoice = Invoice::where('invoice_number', 'like', $prefix . '-' . $year . $month . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();
        
        if ($lastInvoice) {
            // Extract number dari invoice number terakhir
            $lastNumber = intval(substr($lastInvoice->invoice_number, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }
        
        return $prefix . '-' . $year . $month . '-' . $newNumber;
    }
}
