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
            $dir = $request->get('dir', 'desc');
            $query->orderBy($sort, $dir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $invoices = $query->paginate(15)->appends($request->query());

        // Statistics
        $totalInvoiceAmount = Invoice::sum('total_amount');
        $totalRemaining = Invoice::sum('remaining_amount');
        $unpaidInvoices = Invoice::where('remaining_amount', '>', 0)->count();
        $paidInvoices = Invoice::where('remaining_amount', 0)->count();

        return view('invoices.index', compact(
            'invoices', 
            'totalInvoiceAmount', 
            'totalRemaining', 
            'unpaidInvoices', 
            'paidInvoices'
        ));
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
        try {
            // Validasi dasar dulu
            $validated = $request->validate([
                'invoice_date' => 'required|date',
                'client_name' => 'required|string|max:255',
                'items' => 'required|array|min:1',
                'items.*.description' => 'required|string',
                'items.*.qty' => 'required|integer|min:1',
                'items.*.price' => 'required',
                'payment_steps' => 'required|array|min:1',
                'payment_steps.*.step_number' => 'required|integer|min:1',
                'payment_steps.*.amount' => 'required',
            ]);

            // Buat invoice dulu tanpa items
            $invoice = new Invoice();
            $invoice->invoice_number = $this->generateInvoiceNumber();
            $invoice->invoice_date = $request->invoice_date;
            $invoice->payment_deadline = $request->payment_deadline;
            $invoice->client_name = $request->client_name;
            $invoice->created_by = Auth::id();
            $invoice->template = $request->input('template', 'default');
            $invoice->total_amount = 0;
            $invoice->status = 'Belum Lunas';
            $invoice->total_received = 0;
            $invoice->remaining_amount = 0;

            // Simpan invoice untuk mendapatkan ID
            $invoice->save();

            // Process items
            $total = 0;
            if ($request->has('items')) {
                foreach ($request->items as $index => $itemData) {
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
            }

            // Process payment steps
            if ($request->has('payment_steps')) {
                foreach ($request->payment_steps as $index => $paymentData) {
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

            // KIRIM NOTIFIKASI WHATSAPP HANYA JIKA SETTING LENGKAP
            $whatsappMessage = ['success' => false, 'message' => ''];
            
            if ($this->isWhatsAppConfigured()) {
                $whatsappMessage = $this->sendWhatsAppNotification($invoice, 'created');
            } else {
                $whatsappMessage['message'] = 'Pengaturan WhatsApp tidak lengkap. Notifikasi tidak dikirim.';
            }

            // Redirect dengan pesan yang sesuai
            $redirect = redirect()->route('invoices.show', $invoice)
                ->with('success', 'Invoice berhasil dibuat.');
                
            if ($whatsappMessage['success']) {
                return $redirect->with('whatsapp_success', $whatsappMessage['message']);
            } else {
                return $redirect->with('whatsapp_error', $whatsappMessage['message']);
            }

        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function update(Request $request, Invoice $invoice)
    {
        try {
            // Validasi dasar
            $validated = $request->validate([
                'invoice_date' => 'required|date',
                'client_name' => 'required|string|max:255',
                'items' => 'required|array|min:1',
                'items.*.description' => 'required|string',
                'items.*.qty' => 'required|integer|min:1',
                'items.*.price' => 'required',
                'payment_steps' => 'required|array|min:1',
                'payment_steps.*.step_number' => 'required|integer|min:1',
                'payment_steps.*.amount' => 'required',
            ]);

            // Update invoice data
            $invoice->invoice_date = $request->invoice_date;
            $invoice->payment_deadline = $request->payment_deadline;
            $invoice->client_name = $request->client_name;
            $invoice->template = $request->input('template', $invoice->template ?? 'default');

            // Delete existing items and payment steps
            $invoice->items()->delete();
            $invoice->paymentSteps()->delete();

            // Process new items
            $total = 0;
            foreach ($request->items as $index => $itemData) {
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

            // KIRIM NOTIFIKASI WHATSAPP HANYA JIKA SETTING LENGKAP
            $whatsappMessage = ['success' => false, 'message' => ''];
            
            if ($this->isWhatsAppConfigured()) {
                $whatsappMessage = $this->sendWhatsAppNotification($invoice, 'updated');
            } else {
                $whatsappMessage['message'] = 'Pengaturan WhatsApp tidak lengkap. Notifikasi tidak dikirim.';
            }

            $redirect = redirect()->route('invoices.show', $invoice)
                ->with('success', 'Invoice berhasil diperbarui.');
                
            if ($whatsappMessage['success']) {
                return $redirect->with('whatsapp_success', $whatsappMessage['message']);
            } else {
                return $redirect->with('whatsapp_error', $whatsappMessage['message']);
            }

        } catch (\Exception $e) {
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
        $invoice->load(['items', 'paymentSteps', 'creator']);
        
        $endpoint = \App\Models\Setting::get('whatsapp_endpoint');
        $apiKey = \App\Models\Setting::get('whatsapp_api_key');
        $sender = \App\Models\Setting::get('whatsapp_sender');
        $recipient = \App\Models\Setting::get('whatsapp_number');

        if (!$endpoint || !$apiKey || !$sender || !$recipient) {
            return redirect()->route('invoices.show', $invoice)->with('error', 'Pengaturan WhatsApp belum lengkap. Silakan atur di menu Settings.');
        }

        // Ambil data tambahan
        $companyName = \App\Models\Setting::get('company_name', 'Perusahaan');
        $picName = \App\Models\Setting::get('pic_name', 'Penanggung Jawab');
        
        // Format pesan untuk TIM INTERNAL
        $message = "📋 *[NOTIFIKASI INVOICE] KIRIM KE KLIEN*\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
        
        // Informasi Dasar
        $message .= "📄 *Invoice:* {$invoice->invoice_number}\n";
        $message .= "🏢 *Dari:* {$companyName}\n";
        $message .= "👤 *PIC:* {$picName}\n";
        $message .= "📅 *Tanggal:* " . \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') . "\n";
        
        if ($invoice->payment_deadline) {
            $message .= "⏰ *Jatuh Tempo:* " . \Carbon\Carbon::parse($invoice->payment_deadline)->format('d/m/Y') . "\n";
        }
        
        $message .= "\n━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📦 *RINCIAN TAGIHAN:*\n";
        
        // Daftar item
        $counter = 1;
        foreach ($invoice->items as $item) {
            $message .= "{$counter}. {$item->description}\n";
            $message .= "   ➤ Qty: {$item->qty} x Rp " . number_format($item->price, 0, ',', '.') . "\n";
            $message .= "   ➤ Subtotal: Rp " . number_format($item->subtotal, 0, ',', '.') . "\n";
            $counter++;
        }
        
        $message .= "\n━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💰 *RINGKASAN PEMBAYARAN:*\n";
        $message .= "➤ Total: Rp " . number_format($invoice->total_amount, 0, ',', '.') . "\n";
        
        if ($invoice->paymentSteps->count() > 0) {
            $message .= "\n📋 *TAHAPAN PEMBAYARAN:*\n";
            foreach ($invoice->paymentSteps as $step) {
                $message .= "➤ *Tahap {$step->step_number}:* Rp " . number_format($step->amount, 0, ',', '.') . "\n";
                
                if ($step->bank_name) {
                    $message .= "   Transfer ke: {$step->bank_name}\n";
                }
                
                if ($step->payment_date) {
                    $message .= "   Tanggal: " . \Carbon\Carbon::parse($step->payment_date)->format('d/m/Y') . "\n";
                }
                
                $message .= "\n";
            }
        }
        
        $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "Mohon segera melakukan pembayaran sesuai dengan tahapan di atas.\n\n";
        
        $message .= "Terima kasih,\n";
        $message .= "{$companyName}\n";
        $message .= "{$picName}";

        try {
            // GUNAKAN METHOD GET
            $client = new \GuzzleHttp\Client([
                'timeout' => 30,
                'verify' => false,
            ]);

            // Bangun URL dengan query parameters
            $url = $endpoint . '?' . http_build_query([
                'api_key' => $apiKey,
                'sender' => $sender,
                'number' => $recipient,
                'message' => $message
            ]);

            Log::info('Manual WhatsApp send - URL: ' . substr($url, 0, 100) . '...');

            $response = $client->get($url);
            
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode === 200) {
                Log::info('Manual WhatsApp send SUCCESS');
                return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice berhasil dikirim via WhatsApp.');
            } else {
                Log::warning('Manual WhatsApp send failed with status: ' . $statusCode);
                return redirect()->route('invoices.show', $invoice)->with('error', 'Gagal mengirim ke WhatsApp. Status: ' . $statusCode . ' - ' . $body);
            }
        } catch (\Exception $e) {
            Log::error('Gagal mengirim WhatsApp manual: ' . $e->getMessage());
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
            
            // Set status dan amount fields dengan nilai default
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

            // Duplikat attachments (jika ada)
            foreach ($invoice->attachments as $attachment) {
                try {
                    $newAttachment = $attachment->replicate();
                    $newAttachment->invoice_id = $newInvoice->id;
                    
                    // Duplikat file fisik
                    $newFilename = 'attachment_' . $newInvoice->id . '_' . time() . '_' . $attachment->filename;
                    $newPath = 'attachments/' . $newFilename;
                    
                    if (Storage::disk('public')->exists($attachment->path)) {
                        Storage::disk('public')->copy($attachment->path, $newPath);
                        $newAttachment->path = $newPath;
                        $newAttachment->filename = $attachment->filename;
                        $newAttachment->save();
                    }
                } catch (\Exception $e) {
                    // Continue dengan attachment lainnya
                }
            }
            
            return redirect()->route('invoices.edit', $newInvoice)
                ->with('success', 'Invoice berhasil diduplikat. Silakan lanjutkan pembayaran.');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal melanjutkan pembayaran: ' . $e->getMessage());
        }
    }

    /**
     * TAMBAHKAN: Method untuk cek apakah WhatsApp dikonfigurasi
     */
    private function isWhatsAppConfigured()
    {
        $endpoint = \App\Models\Setting::get('whatsapp_endpoint');
        $apiKey = \App\Models\Setting::get('whatsapp_api_key');
        $sender = \App\Models\Setting::get('whatsapp_sender');
        $recipient = \App\Models\Setting::get('whatsapp_number');
        
        return !empty($endpoint) && !empty($apiKey) && !empty($sender) && !empty($recipient);
    }

    /**
     * PERBAIKI: Method untuk kirim notifikasi WhatsApp
     */
    private function sendWhatsAppNotification(Invoice $invoice, string $action = 'created')
    {
        try {
            // CEK DULU KONFIGURASI
            if (!$this->isWhatsAppConfigured()) {
                return [
                    'success' => false,
                    'message' => 'Pengaturan WhatsApp belum lengkap. Silakan atur di menu Settings.'
                ];
            }

            $endpoint = \App\Models\Setting::get('whatsapp_endpoint');
            $apiKey = \App\Models\Setting::get('whatsapp_api_key');
            $sender = \App\Models\Setting::get('whatsapp_sender');
            $recipient = \App\Models\Setting::get('whatsapp_number');

            // Ambil data tambahan
            $companyName = \App\Models\Setting::get('company_name', 'Perusahaan');
            $picName = \App\Models\Setting::get('pic_name', 'Penanggung Jawab');
            
            // LOG UNTUK DEBUG
            Log::info('Mengirim notifikasi WhatsApp', [
                'invoice' => $invoice->invoice_number,
                'action' => $action,
                'recipient' => $recipient,
                'endpoint' => $endpoint
            ]);

            // Format pesan untuk TIM INTERNAL
            if ($action == 'created') {
                $message = "📋 *[INTERNAL NOTIFIKASI] INVOICE BARU*\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
            } else {
                $message = "📋 *[INTERNAL NOTIFIKASI] INVOICE DIPERBARUI*\n";
                $message .= "━━━━━━━━━━━━━━━━━━━━━━\n\n";
            }
            
            // Informasi Dasar
            $message .= "📄 *No. Invoice:* {$invoice->invoice_number}\n";
            $message .= "🏢 *Perusahaan:* {$companyName}\n";
            $message .= "👤 *PIC:* {$picName}\n";
            $message .= "👤 *Klien:* {$invoice->client_name}\n";
            $message .= "📅 *Tanggal Invoice:* " . \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') . "\n";
            
            if ($invoice->payment_deadline) {
                $message .= "⏰ *Batas Pembayaran:* " . \Carbon\Carbon::parse($invoice->payment_deadline)->format('d/m/Y') . "\n";
            }
            
            $message .= "\n━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📦 *RINCIAN TAGIHAN:*\n";
            
            // Daftar item
            $counter = 1;
            foreach ($invoice->items as $item) {
                $message .= "{$counter}. {$item->description}\n";
                $message .= "   ➤ Qty: {$item->qty} x Rp " . number_format($item->price, 0, ',', '.') . "\n";
                $message .= "   ➤ Subtotal: Rp " . number_format($item->subtotal, 0, ',', '.') . "\n";
                $counter++;
            }
            
            $message .= "\n━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "💰 *RINGKASAN KEUANGAN:*\n";
            $message .= "➤ Total Tagihan: Rp " . number_format($invoice->total_amount, 0, ',', '.') . "\n";
            $message .= "➤ Total Diterima: Rp " . number_format($invoice->total_received, 0, ',', '.') . "\n";
            $message .= "➤ Sisa Pembayaran: Rp " . number_format($invoice->remaining_amount, 0, ',', '.') . "\n";
            
            $message .= "\n━━━━━━━━━━━━━━━━━━━━━━\n";
            
            if ($invoice->paymentSteps->count() > 0) {
                $message .= "📋 *TAHAPAN PEMBAYARAN:*\n";
                foreach ($invoice->paymentSteps as $step) {
                    $message .= "➤ *Pembayaran ke-{$step->step_number}:* Rp " . number_format($step->amount, 0, ',', '.') . "\n";
                    
                    if ($step->bank_name) {
                        $message .= "   Bank: {$step->bank_name}\n";
                    }
                    
                    if ($step->payment_date) {
                        $message .= "   Tanggal: " . \Carbon\Carbon::parse($step->payment_date)->format('d/m/Y') . "\n";
                    }
                    
                    $message .= "   Status: " . ($step->payment_date ? '✅ LUNAS' : '⌛ MENUNGGU') . "\n\n";
                }
            }
            
            $message .= "━━━━━━━━━━━━━━━━━━━━━━\n";
            $message .= "📊 *STATUS:* {$invoice->status}\n";
            $message .= "📝 *Dibuat oleh:* " . ($invoice->creator->name ?? 'System') . "\n";
            $message .= "\n";
            $message .= "🔔 *CATATAN:* Notifikasi ini untuk informasi internal tim.\n";
            $message .= "Segera follow up pembayaran dengan klien.\n\n";
            
            $message .= "Terima kasih,\n";
            $message .= "Tim {$companyName}";

            // GUNAKAN METHOD GET
            $client = new \GuzzleHttp\Client([
                'timeout' => 30,
                'verify' => false,
            ]);

            // Bangun URL dengan query parameters
            $url = $endpoint . '?' . http_build_query([
                'api_key' => $apiKey,
                'sender' => $sender,
                'number' => $recipient,
                'message' => $message
            ]);

            Log::info('Mengirim notifikasi via GET: ' . substr($url, 0, 100) . '...');

            $response = $client->get($url);
            
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            Log::info('Response notifikasi:', [
                'status_code' => $statusCode,
                'body' => substr($body, 0, 200)
            ]);

            if ($statusCode === 200) {
                Log::info('Notifikasi WhatsApp berhasil dikirim', [
                    'invoice' => $invoice->invoice_number,
                    'response_status' => $statusCode
                ]);

                return [
                    'success' => true,
                    'message' => 'Notifikasi WhatsApp berhasil dikirim ke ' . $recipient
                ];
            } else {
                Log::warning('WhatsApp notifikasi failed with status: ' . $statusCode);
                return [
                    'success' => false,
                    'message' => 'Response status: ' . $statusCode . ' - ' . $body
                ];
            }

        } catch (\Exception $e) {
            Log::error('Gagal mengirim notifikasi WhatsApp: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return [
                'success' => false,
                'message' => 'Gagal mengirim notifikasi WhatsApp: ' . $e->getMessage()
            ];
        }
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