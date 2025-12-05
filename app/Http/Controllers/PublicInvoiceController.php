<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use SimpleSoftwareIO\QrCode\Facades\QrCode; // Tambahkan ini untuk fix FQCN QrCode

class PublicInvoiceController extends Controller
{
    public function show($token)
    {
        $invoice = Invoice::where('public_token', $token)->firstOrFail();
        
        // Cek token expiry
        if (!$invoice->isPublicTokenValid()) {
            abort(410, 'Link invoice telah kadaluarsa.');
        }
        
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
        
        $companyName = \App\Models\Setting::get('company_name', 'Perusahaan');
        $picName = \App\Models\Setting::get('pic_name', 'Penanggung Jawab');
        
        return view('invoices.public-show', compact('invoice', 'logoUrl', 'companyName', 'picName'));
    }

     public function download($token)
    {
        $invoice = Invoice::where('public_token', $token)->firstOrFail();
        
        if (!$invoice->isPublicTokenValid()) {
            abort(410, 'Link invoice telah kadaluarsa.');
        }
        
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
        
        // Generate QR Code untuk URL publik
        $publicUrl = route('invoices.public.show', ['token' => $invoice->public_token]);
        
        // Fix FQCN QrCode: Menggunakan Facade yang sudah di-use di atas
        $qrCode = QrCode::size(100)->generate($publicUrl); 
        
        $companyName = \App\Models\Setting::get('company_name', 'Perusahaan');
        $picName = \App\Models\Setting::get('pic_name', 'Penanggung Jawab');
        
        $pdf = Pdf::loadView('pdf.invoice_public', compact(
            'invoice', 
            'logoUrl', 
            'qrCode', 
            'publicUrl',
            'companyName',
            'picName'
        ));
        
        $pdf->setOptions([
            'isRemoteEnabled' => true,
            'isHtml5ParserEnabled' => true,
            'isPhpEnabled' => true,
            'defaultPaperSize' => 'a4',
            'defaultPaperOrientation' => 'landscape',
            'dpi' => 96
        ]);
        
        $formattedDate = \Carbon\Carbon::parse($invoice->invoice_date)->format('Y-m-d');
        $cleanClientName = preg_replace('/[^a-zA-Z0-9\s]/', '', $invoice->client_name);
        $cleanClientName = str_replace(' ', '-', $cleanClientName);
        $filename = "{$formattedDate}-{$cleanClientName}-{$invoice->invoice_number}-public.pdf";

        return $pdf->download($filename);
    }
    
    // --- METHOD VERIFY BARU UNTUK QR CODE ---
    /**
     * Menampilkan faktur kepada publik untuk tujuan verifikasi (melalui QR Code).
     */
    public function verify($uniqueHash)
    {
        // 1. Cari faktur
        $invoice = Invoice::where('unique_hash', $uniqueHash)->first();

        if (!$invoice) {
            return abort(404, 'Faktur tidak ditemukan atau sudah kadaluarsa.');
        }

        $invoice->load(['items', 'paymentSteps', 'creator']);

        // 2. Siapkan data logo dan company settings (WAJIB untuk tampilan)
        $logoUrl = '';
        $companyLogo = \App\Models\Setting::get('company_logo');
        if ($companyLogo) {
            $storageRelativePath = $companyLogo;
            if (!str_starts_with($storageRelativePath, 'storage/')) {
                $storageRelativePath = 'storage/' . $storageRelativePath;
            }
            // Menggunakan asset() karena ini adalah halaman web, bukan PDF Dompdf
            $logoUrl = asset($storageRelativePath); 
        }
        
        $companyName = \App\Models\Setting::get('company_name', 'Perusahaan');
        $picName = \App\Models\Setting::get('pic_name', 'Penanggung Jawab');

        // 3. Kirim data ke view
        return view('public.invoice_verification', compact('invoice', 'logoUrl', 'companyName', 'picName'));
    }
}