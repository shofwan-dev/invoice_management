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
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\Setting; // Pastikan model Setting di-import
use GuzzleHttp\Client; // Pastikan GuzzleHttp di-import

class InvoiceController extends Controller
{
    public function __construct()
    {
        // Pastikan Guzzle terinstal: composer require guzzlehttp/guzzle
        $this->middleware('auth');
    }
    
    public function index(Request $request)
    {
        $query = Invoice::query();

        // Search
        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                  ->orWhere('client_name', 'like', "%{$search}%");
            });
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
            $dir = $request->get('direction', 'desc');
            $query->orderBy($sort, $dir);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // =======================================================
        // ✅ PERBAIKAN: Hitung Statistik BERDASARKAN FILTER SAAT INI
        // (Menggunakan (clone $query) sebelum pagination)
        // =======================================================
        
        // 1. Total Nilai Faktur (dari hasil filter)
        $totalInvoiceAmount = (clone $query)->sum('total_amount');
        
        // 2. Total Sisa Tagihan (dari hasil filter)
        $totalRemaining = (clone $query)->sum('remaining_amount');
        
        // 3. Jumlah Faktur Belum Lunas (dari hasil filter)
        $unpaidInvoices = (clone $query)->where('remaining_amount', '>', 0)->count(); 

        // 4. Jumlah Faktur Sudah Lunas (dari hasil filter)
        $paidInvoices = (clone $query)->where('remaining_amount', 0)->count(); 
        
        // =======================================================

        // Pagination
        $invoices = $query->paginate(15)->appends($request->query());
        
        // Kirim SEMUA variabel agregat ke view
        return view('invoices.index', compact('invoices', 'totalInvoiceAmount', 'totalRemaining', 'unpaidInvoices', 'paidInvoices'));
    }

    public function create()
    {
        $invoiceNumber = $this->generateInvoiceNumber();
        return view('invoices.create', compact('invoiceNumber'));
    }

    public function store(Request $request)
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
            $invoice->public_token = Str::random(60);
            $invoice->unique_hash = Str::random(32); 

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
            $totalReceived = 0;
            if ($request->has('payment_steps')) {
                foreach ($request->payment_steps as $index => $paymentData) {
                    // Clean amount
                    $amount = $paymentData['amount'];
                    if (is_string($amount)) {
                        $amount = str_replace('.', '', $amount);
                        $amount = str_replace(',', '.', $amount);
                    }
                    $amount = (float) $amount;
                    
                    $isPaid = isset($paymentData['payment_date']) && !empty($paymentData['payment_date']);

                    $invoice->paymentSteps()->create([
                        'step_number' => $index + 1,
                        'amount' => $amount,
                        'payment_date' => $paymentData['payment_date'] ?? null,
                        'bank_name' => $paymentData['bank_name'] ?? null,
                    ]);
                    
                    if ($isPaid) {
                        $totalReceived += $amount;
                    }
                }
            }

            // Update total amount dan remaining amount
            $invoice->total_amount = $total;
            $invoice->total_received = $totalReceived;
            $invoice->remaining_amount = $total - $totalReceived;
            $invoice->status = $invoice->remaining_amount <= 0 ? 'Lunas' : 'Belum Lunas';

            // Simpan attachments (Tambahkan logika attachments jika ada di form)
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
            Log::error('Gagal membuat Invoice: ' . $e->getMessage());
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['items', 'paymentSteps', 'attachments', 'creator']);
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
            
            // Fail-safe untuk unique_hash saat update
            if (empty($invoice->unique_hash)) {
                $invoice->unique_hash = Str::random(32); 
            }

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
            $totalReceived = 0;
            if ($request->has('payment_steps')) {
                foreach ($request->payment_steps as $index => $paymentData) {
                    // Clean amount
                    $amount = $paymentData['amount'];
                    if (is_string($amount)) {
                        $amount = str_replace('.', '', $amount);
                        $amount = str_replace(',', '.', $amount);
                    }
                    $amount = (float) $amount;
                    
                    $isPaid = isset($paymentData['payment_date']) && !empty($paymentData['payment_date']);

                    $invoice->paymentSteps()->create([
                        'step_number' => $index + 1,
                        'amount' => $amount,
                        'payment_date' => $paymentData['payment_date'] ?? null,
                        'bank_name' => $paymentData['bank_name'] ?? null,
                    ]);
                    
                    if ($isPaid) {
                        $totalReceived += $amount;
                    }
                }
            }

            // Update total
            $invoice->total_amount = $total;
            $invoice->total_received = $totalReceived;
            $invoice->remaining_amount = $total - $totalReceived;
            $invoice->status = $invoice->remaining_amount <= 0 ? 'Lunas' : 'Belum Lunas';
            $invoice->save();

            // Handle attachments (Jika ada di form update)
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
            Log::error('Gagal memperbarui Invoice: ' . $e->getMessage());
            return redirect()->back()
                ->withInput()
                ->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function destroy(Invoice $invoice)
    {
        // Check authorization (hanya admin)
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized');
        }

        try {
            // Hapus attachments files
            foreach ($invoice->attachments as $att) {
                // Perhatikan: $att->file_path digunakan jika Anda mengikuti skema migrasi awal
                // Jika skema Anda menggunakan $att->path dan $att->filename, sesuaikan
                Storage::disk('public')->delete($att->file_path ?? $att->path); 
            }
            $invoice->delete();
            return redirect()->route('invoices.index')->with('success','Invoice dihapus.');
        } catch (\Exception $e) {
            Log::error('Gagal menghapus Invoice: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Gagal menghapus invoice: ' . $e->getMessage());
        }
    }

    /**
     * ✅ FUNGSI BULK DELETE
     */
    public function bulkDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        
        // 1. Validasi Keberadaan ID
        if (empty($ids)) {
            return redirect()->route('invoices.index')->with('error', 'Tidak ada invoice yang dipilih.');
        }

        // 2. Check authorization (Hanya admin)
        // Pastikan role diambil dengan benar dari model User
        if (Auth::user()->role !== 'admin') {
            abort(403, 'Unauthorized');
        }

        // 3. Ambil Invoice
        $invoices = Invoice::whereIn('id', $ids)->get();
        if ($invoices->isEmpty()) {
            return redirect()->route('invoices.index')->with('error', 'Invoice tidak ditemukan.');
        }
        
        $count = 0;
        $failedIds = []; // Array untuk mencatat ID yang gagal dihapus
        
        // 4. Proses Penghapusan Massal
        foreach ($invoices as $invoice) {
            try {
                 // Hapus attachments files (menggunakan preferensi 'file_path' jika ada, lalu fallback ke 'path')
                foreach ($invoice->attachments as $att) {
                    $filePath = $att->file_path ?? $att->path; // Mengambil path yang paling mungkin
                    if ($filePath && Storage::disk('public')->exists($filePath)) {
                        Storage::disk('public')->delete($filePath);
                    }
                }
                
                // Hapus invoice dan semua relasinya (items, paymentSteps, attachments)
                // Jika relasi diset onDelete('cascade') di migrasi, ini akan menghapus semua relasi.
                // Jika tidak, Anda harus menghapus relasi secara eksplisit (seperti yang dilakukan untuk attachment).
                $invoice->delete(); 
                $count++;
            } catch (\Exception $e) {
                Log::error('Gagal menghapus invoice ID ' . $invoice->id . ': ' . $e->getMessage());
                $failedIds[] = $invoice->id;
            }
        }

        // 5. Kirim Notifikasi Akhir
        if ($count > 0 && empty($failedIds)) {
            return redirect()->route('invoices.index')->with('success', 'Berhasil menghapus ' . $count . ' invoice.');
        } elseif ($count > 0 && !empty($failedIds)) {
            $failedList = implode(', ', $failedIds);
            return redirect()->route('invoices.index')->with('warning', 
                "Berhasil menghapus $count invoice, namun gagal menghapus invoice dengan ID: $failedList."
            );
        } else {
            return redirect()->route('invoices.index')->with('error', 'Gagal menghapus semua invoice yang dipilih.');
        }
    }

    public function exportPdf(Invoice $invoice)
    {
        // 1. FAIL-SAFE: Pastikan unique_hash ada.
        if (empty($invoice->unique_hash)) {
            $invoice->unique_hash = Str::random(32); 
            $invoice->save(); 
        }
        
        // Set locale ke Indonesia
        Carbon::setLocale('id');
        
        $invoice->load(['items','paymentSteps','creator']);
        
        // Gunakan view yang sama seperti di dashboard
        $view = 'pdf.invoice_modern';
        
        // Prepare logo URL
        $logoUrl = '';
        $companyLogo = Setting::get('company_logo');
        if ($companyLogo) {
            $storageRelativePath = $companyLogo;
            if (!str_starts_with($storageRelativePath, 'storage/')) {
                $storageRelativePath = 'storage/' . $storageRelativePath;
            }
            $logoUrl = asset($storageRelativePath);
        }

        // LOGIC QR CODE
        $uniqueHash = $invoice->unique_hash; 
        $verificationUrl = null;
        
        if ($uniqueHash) {
            // Membuat URL penuh untuk verifikasi publik
            $verificationUrl = route('invoice.verify.public', ['unique_hash' => $uniqueHash], true); 
        }
        
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
        
        return $pdf->download($filename);
    }

    /**
     * ✅ FUNGSI BULK EXPORT
     */
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

        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) { // Tambahkan OVERWRITE
            foreach ($invoices as $invoice) {
                // FAIL-SAFE: Pastikan unique_hash ada.
                if (empty($invoice->unique_hash)) {
                    $invoice->unique_hash = Str::random(32); 
                    $invoice->save(); 
                }
                
                $invoice->load(['items', 'paymentSteps', 'creator']);
                Carbon::setLocale('id');
                
                // Prepare logo URL
                $logoUrl = '';
                $companyLogo = Setting::get('company_logo');
                if ($companyLogo) {
                    $storageRelativePath = $companyLogo;
                    if (!str_starts_with($storageRelativePath, 'storage/')) {
                        $storageRelativePath = 'storage/' . $storageRelativePath;
                    }
                    $logoUrl = asset($storageRelativePath);
                }

                // LOGIC QR CODE
                $verificationUrl = route('invoice.verify.public', ['unique_hash' => $invoice->unique_hash], true);
                
                $data = compact('invoice', 'logoUrl', 'verificationUrl');
                
                $pdf = Pdf::loadView('pdf.invoice_modern', $data);
                $pdf->setOptions([
                    'isRemoteEnabled' => true, 
                    'isHtml5ParserEnabled' => true,
                    'isPhpEnabled' => true,
                    'defaultPaperSize' => 'a4',
                    'defaultPaperOrientation' => 'landscape',
                    'dpi' => 96
                ]);
                $pdfContent = $pdf->output();
                
                // Format filename untuk setiap file dalam ZIP
                $formattedDate = Carbon::parse($invoice->invoice_date)->format('Y-m-d');
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


    /**
     * ✅ FUNGSI WHATSAPP MANUAL UNTUK KLIEN (Tombol di view show)
     */
    public function sendWhatsapp(Invoice $invoice, Request $request)
    {
        $invoice->load(['items', 'paymentSteps', 'creator']);
        
        $endpoint = Setting::get('whatsapp_endpoint');
        $apiKey = Setting::get('whatsapp_api_key');
        $sender = Setting::get('whatsapp_sender');
        $recipient = $invoice->client_phone ?? Setting::get('whatsapp_number'); // Gunakan nomor klien jika ada, fallback ke internal

        if (!$endpoint || !$apiKey || !$sender || !$recipient) {
            return redirect()->route('invoices.show', $invoice)->with('error', 'Pengaturan WhatsApp belum lengkap atau Nomor Klien/Default tidak ditemukan. Silakan atur di menu Settings.');
        }

        // Ambil data tambahan
        $companyName = Setting::get('company_name', 'Perusahaan');
        $picName = Setting::get('pic_name', 'Penanggung Jawab');
        
        // Format pesan untuk KLIEN
        $message = "Halo *{$invoice->client_name}*,\n\n";
        $message .= "Kami dari {$companyName} ingin memberitahukan bahwa Invoice berikut telah diterbitkan:\n\n";
        
        // Informasi Dasar
        $message .= "📄 *No. Invoice:* {$invoice->invoice_number}\n";
        $message .= "📅 *Tanggal:* " . Carbon::parse($invoice->invoice_date)->format('d/m/Y') . "\n";
        
        if ($invoice->payment_deadline) {
            $message .= "⏰ *Jatuh Tempo:* " . Carbon::parse($invoice->payment_deadline)->format('d/m/Y') . "\n";
        }
        
        $message .= "💰 *Total Tagihan:* Rp " . number_format($invoice->total_amount, 0, ',', '.') . "\n";
        $message .= "✅ *Total Diterima:* Rp " . number_format($invoice->total_received, 0, ',', '.') . "\n";
        $message .= "❌ *Sisa Tagihan:* Rp " . number_format($invoice->remaining_amount, 0, ',', '.') . "\n\n";
        
        $message .= "Mohon segera melakukan pembayaran. Detail pembayaran dapat dilihat di dokumen PDF terlampir atau melalui tautan berikut (jika ada):\n\n";
        // Asumsi ada public link
        // $message .= route('invoice.public', $invoice->public_token) . "\n\n";
        
        $message .= "Terima kasih atas kerja samanya.\n";
        $message .= "Hormat kami,\n";
        $message .= "{$picName} ({$companyName})";

        try {
            // GUNAKAN METHOD GET
            $client = new Client([
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

            Log::info('Manual WhatsApp send to client - URL: ' . substr($url, 0, 100) . '...');

            $response = $client->get($url);
            
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode === 200) {
                Log::info('Manual WhatsApp send SUCCESS to client: ' . $recipient);
                return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice berhasil dikirim via WhatsApp ke ' . $recipient . '.');
            } else {
                Log::warning('Manual WhatsApp send failed with status: ' . $statusCode);
                return redirect()->route('invoices.show', $invoice)->with('error', 'Gagal mengirim ke WhatsApp. Status: ' . $statusCode . ' - ' . $body);
            }
        } catch (\Exception $e) {
            Log::error('Gagal mengirim WhatsApp manual: ' . $e->getMessage());
            return redirect()->route('invoices.show', $invoice)->with('error', 'Gagal mengirim ke WhatsApp: ' . $e->getMessage());
        }
    }

    /**
     * ✅ FUNGSI PENDUKUNG: Cek konfigurasi WhatsApp
     */
    private function isWhatsAppConfigured()
    {
        $endpoint = Setting::get('whatsapp_endpoint');
        $apiKey = Setting::get('whatsapp_api_key');
        $sender = Setting::get('whatsapp_sender');
        $recipient = Setting::get('whatsapp_number'); // Nomor Internal Tim
        
        return !empty($endpoint) && !empty($apiKey) && !empty($sender) && !empty($recipient);
    }

    /**
     * ✅ FUNGSI PENDUKUNG: Kirim notifikasi WhatsApp Internal (Setelah Create/Update)
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

            $endpoint = Setting::get('whatsapp_endpoint');
            $apiKey = Setting::get('whatsapp_api_key');
            $sender = Setting::get('whatsapp_sender');
            $recipient = Setting::get('whatsapp_number'); // Nomor Internal Tim

            // Ambil data tambahan
            $companyName = Setting::get('company_name', 'Perusahaan');
            $picName = Setting::get('pic_name', 'Penanggung Jawab');
            
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
            $client = new Client([
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

            $response = $client->get($url);
            
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode === 200) {
                return [
                    'success' => true,
                    'message' => 'Notifikasi WhatsApp berhasil dikirim ke ' . $recipient
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Notifikasi Internal gagal. Status: ' . $statusCode . ' - ' . $body
                ];
            }

        } catch (\Exception $e) {
            Log::error('Gagal mengirim notifikasi WhatsApp: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gagal mengirim notifikasi WhatsApp: ' . $e->getMessage()
            ];
        }
    }


    public function continuePayment(Invoice $invoice)
    {
        try {
            // Duplikat data invoice utama
            $newInvoice = $invoice->replicate();
            $newInvoice->invoice_number = $this->generateInvoiceNumber();
            $newInvoice->invoice_date = Carbon::now();
            $newInvoice->payment_deadline = null; // Reset deadline
            
            // Set status dan amount fields dengan nilai default
            $newInvoice->status = 'Belum Lunas';
            $newInvoice->total_received = 0;
            $newInvoice->remaining_amount = $newInvoice->total_amount;
            $newInvoice->created_by = Auth::id();
            $newInvoice->public_token = Str::random(60);
            $newInvoice->unique_hash = Str::random(32); 
            
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
                    $originalPath = $attachment->file_path ?? $attachment->path;
                    $originalFilename = $attachment->file_name ?? $attachment->filename;
                    
                    $newFilename = 'attachment_' . $newInvoice->id . '_' . time() . '_' . $originalFilename;
                    $newPath = 'invoice_attachments/' . $newFilename;
                    
                    if (Storage::disk('public')->exists($originalPath)) {
                        Storage::disk('public')->copy($originalPath, $newPath);
                        $newAttachment->file_path = $newPath;
                        $newAttachment->file_name = $originalFilename;
                        $newAttachment->save();
                    }
                } catch (\Exception $e) {
                    Log::error('Gagal duplikat attachment: ' . $e->getMessage());
                    // Continue dengan attachment lainnya
                }
            }
            
            return redirect()->route('invoices.edit', $newInvoice)
                ->with('success', 'Invoice berhasil diduplikat. Silakan lanjutkan pembayaran.')
                ->with('is_duplicated', true);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Gagal melanjutkan pembayaran: ' . $e->getMessage());
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