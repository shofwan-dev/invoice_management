<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 14px;
            color: #333;
        }
        .container-lg {
            max-width: 100%;
            padding: 0 15px;
        }
        .card {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            background-color: #fff;
        }
        .shadow-sm {
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075) !important;
        }
        .border-0 {
            border: 0 !important;
        }
        .text-primary {
            color: #0d6efd !important;
        }
        .text-success {
            color: #198754 !important;
        }
        .text-warning {
            color: #ffc107 !important;
        }
        .bg-success {
            background-color: #198754 !important;
        }
        .bg-warning {
            background-color: #ffc107 !important;
        }
        .bg-light {
            background-color: #f8f9fa !important;
        }
        .border-primary {
            border-color: #0d6efd !important;
        }
        .border-success {
            border-color: #198754 !important;
        }
        .border-warning {
            border-color: #ffc107 !important;
        }
        .table-light {
            background-color: #f8f9fa;
        }
        .table-primary {
            background-color: #cfe2ff;
        }
        .badge {
            display: inline-block;
            padding: 0.35em 0.65em;
            font-size: 0.75em;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.375rem;
            color: #fff;
        }
        .list-group-item {
            border: 1px solid rgba(0, 0, 0, 0.125);
            background-color: #fff;
        }
    </style>
</head>
<body>
    <div class="py-6">
        <div class="container-lg">
            <!-- Invoice Card -->
            <div class="card shadow-sm border-0 p-4">
                <!-- Header -->
                <div class="row mb-4 pb-3 border-bottom">
                    <div class="col-md-6">
                        @if($logoUrl)
                            <div class="mb-3">
                                <img src="{{ $logoUrl }}" alt="Company Logo" style="max-height: 60px; max-width: 300px; object-fit: contain;" />
                            </div>
                        @endif
                        <h3 class="mb-0">INVOICE</h3>
                        <p class="text-muted mb-0">{{ \App\Models\Setting::get('company_name', 'PT. Perusahaan Anda') }}</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <p class="mb-1"><strong>Nomor:</strong> {{ $invoice->invoice_number }}</p>
                        <p class="mb-1"><strong>Tanggal:</strong> {{ \Carbon\Carbon::parse($invoice->invoice_date)->translatedFormat('d F Y') }}</p>
                        <p class="mb-0"><strong>Dibuat oleh:</strong> {{ $invoice->creator->name ?? 'N/A' }}</p>
                    </div>
                </div>

                <!-- Bill To -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-2">TAGIHAN UNTUK:</h6>
                        <p class="mb-0">
                            <strong>{{ $invoice->client_name }}</strong><br>
                            <small class="text-muted">Tanggal Invoice: {{ \Carbon\Carbon::parse($invoice->invoice_date)->translatedFormat('d F Y') }}</small>
                        </p>
                    </div>
                    <div class="col-md-6 text-end">
                        <h6 class="fw-bold mb-2">STATUS INVOICE:</h6>
                        @php
                            $status = $invoice->status;
                            $badgeClass = $status === 'Lunas' ? 'bg-success' : 'bg-warning';
                        @endphp
                        <p class="mb-1">
                            <span class="badge {{ $badgeClass }} fs-6">{{ $status }}</span>
                        </p>
                        @if($invoice->payment_deadline)
                        <small class="text-muted d-block mt-2">Batas Waktu: {{ \Carbon\Carbon::parse($invoice->payment_deadline)->translatedFormat('d F Y') }}</small>
                        @endif
                    </div>
                </div>

                <!-- Financial Summary -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card border-primary bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Total Tagihan</h6>
                                <p class="card-text fs-5 fw-bold text-primary">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-success bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Total Diterima</h6>
                                <p class="card-text fs-5 fw-bold text-success">Rp {{ number_format($invoice->total_received, 0, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card border-warning bg-light">
                            <div class="card-body">
                                <h6 class="card-title">Sisa Pembayaran</h6>
                                <p class="card-text fs-5 fw-bold text-warning">Rp {{ number_format($invoice->remaining_amount, 0, ',', '.') }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Information -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="fw-bold mb-2">INFORMASI PERUSAHAAN:</h6>
                        <p class="mb-0">
                            <strong>{{ \App\Models\Setting::get('company_name', 'PT. Perusahaan Anda') }}</strong><br>
                            <small>Penanggung Jawab: {{ \App\Models\Setting::get('pic_name', '-') }}</small>
                        </p>
                    </div>
                </div>

                <!-- Items Table -->
                <h6 class="fw-bold mb-3">RINCIAN TAGIHAN:</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th class="text-center" style="width: 5%">#</th>
                                <th>Deskripsi</th>
                                <th class="text-center" style="width: 10%">Qty</th>
                                <th class="text-end" style="width: 15%">Harga</th>
                                <th class="text-end" style="width: 15%">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->items as $i => $it)
                            <tr>
                                <td class="text-center">{{ $i+1 }}</td>
                                <td>{{ $it->description }}</td>
                                <td class="text-center">{{ $it->qty }}</td>
                                <td class="text-end">Rp {{ number_format($it->price, 0, ',', '.') }}</td>
                                <td class="text-end fw-bold">Rp {{ number_format($it->subtotal, 0, ',', '.') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="table-primary fw-bold">
                                <td colspan="4" class="text-end">TOTAL:</td>
                                <td class="text-end">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Payment Steps -->
                <h6 class="fw-bold mb-3">TAHAPAN PEMBAYARAN:</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-striped">
                        <thead class="table-light">
                            <tr>
                                <th>Pembayaran ke</th>
                                <th class="text-end">Jumlah</th>
                                <th>Tanggal Pembayaran</th>
                                <th>Ke Bank Mana</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoice->paymentSteps as $ps)
                            <tr>
                                <td>Bayaran Ke-{{ $ps->step_number }}</td>
                                <td class="text-end fw-bold">Rp {{ number_format($ps->amount, 2, ',', '.') }}</td>
                                <td>
                                    @if($ps->payment_date)
                                        {{ \Carbon\Carbon::parse($ps->payment_date)->translatedFormat('d F Y') }}
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>{{ $ps->bank_name ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="badge bg-success">Berhasil</span>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted">Belum ada tahapan pembayaran</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Attachments -->
                @if($invoice->attachments->count() > 0)
                <h6 class="fw-bold mb-3">LAMPIRAN:</h6>
                <div class="mb-4">
                    <ul class="list-group list-group-flush">
                        @foreach($invoice->attachments as $att)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="text-decoration-none">
                                    <i class="bi bi-file"></i> {{ $att->filename }}
                                </span>
                                <small class="text-muted">{{ number_format($att->size / 1024, 2) }} KB</small>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <!-- Footer -->
                <div class="row mt-5 pt-4 border-top">
                    <div class="col-md-6">
                        <p class="text-muted small mb-0">Kwitansi ini adalah bukti pembayaran yang sah<br>dan telah diterima oleh PT Kaffa Rizquna Wisata.</p>
                    </div>
                    
                    <div class="col-md-6 text-end">
                        <div style="text-align: right; margin-top: 50px;">
                            @if(isset($verificationUrl) && $verificationUrl)
                                <p style="margin-bottom: 5px; font-size: 10pt;">
                                    Disahkan secara digital. Scan untuk verifikasi.
                                </p>
                                
                                {{-- MENGHASILKAN QR CODE dengan FQCN dan styling --}}
                                <img src="data:image/svg+xml;base64,{{ 
                                    base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::size(100)->generate($verificationUrl)) 
                                }}" alt="QR Code Verifikasi" 
                                style="width: 100px; height: 100px; margin-bottom: 5px; border: 1px solid #ccc; padding: 5px;">
                                
                                <p style="font-size: 8pt; margin-bottom: 0;">
                                    <i class="bi bi-shield-check"></i> Dokumen Terverifikasi
                                </p>
                            @else
                                <p style="font-size: 10pt; color: #dc3545;">
                                    Token verifikasi belum tersedia.
                                </p>
                            @endif
                        </div>
                        
                        <p class="mb-4" style="margin-top: 20px; border-bottom: 1px solid #333; display: inline-block; padding-bottom: 5px;">
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                        </p>
                        <p class="text-muted small mb-0">{{ \App\Models\Setting::get('pic_name', 'Penanggung Jawab') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>