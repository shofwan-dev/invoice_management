<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Faktur {{ $invoice->invoice_number }}</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f7f6; /* Light background for the page */
        }
        .container-lg {
            max-width: 960px; /* Slightly wider container */
        }
        .invoice-card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.1);
        }
        .invoice-header-section {
            border-bottom: 2px solid #0d6efd; /* Primary blue border */
        }
        .verification-box {
            padding: 1rem;
            border-left: 5px solid #198754; 
            background-color: #d1e7dd; /* Success light background */
            color: #0f5132;
            border-radius: 0.5rem;
        }
        /* Custom table styling for coherence */
        .table-custom-header th {
            background-color: #e9ecef;
            border-bottom: 2px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="py-5">
        <div class="container-lg">
            <div class="card p-4 p-md-5 invoice-card">
                
                <div class="row pb-4 mb-4 invoice-header-section align-items-center">
                    <div class="col-md-6 mb-3 mb-md-0">
                        @if($logoUrl)
                            <img src="{{ $logoUrl }}" alt="{{ $companyName }} Logo" class="img-fluid" style="max-height: 70px; width: auto; margin-bottom: 10px;">
                        @endif
                        <h2 class="text-primary fw-bold mt-2">{{ $companyName }}</h2>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h3 class="text-secondary mb-0">Verifikasi Faktur</h3>
                        <p class="mb-1 fw-bold">Invoice #{{ $invoice->invoice_number }}</p>
                        <p class="small text-muted mb-0">Tanggal: {{ \Carbon\Carbon::parse($invoice->invoice_date)->translatedFormat('d F Y') }}</p>
                    </div>
                </div>

                <div class="verification-box mb-4">
                    <h4 class="mb-2"><i class="bi bi-shield-lock-fill me-2"></i> Status Verifikasi</h4>
                    <p class="fs-6 mb-1">
                        ✅ <strong>Faktur ini telah disahkan secara digital.</strong>
                    </p>
                    <p class="mb-0 small">
                        Data faktur yang Anda lihat sesuai dengan catatan resmi kami.
                    </p>
                </div>
                
                <div class="row mb-5">
                    <div class="col-md-6">
                        <h6 class="text-uppercase text-muted mb-2">Ditujukan Kepada:</h6>
                        <p class="fw-bold mb-0">{{ $invoice->client_name }}</p>
                        @if($invoice->client_email)<p class="small text-muted mb-0">{{ $invoice->client_email }}</p>@endif
                    </div>
                    <div class="col-md-6 text-md-end mt-3 mt-md-0">
                        <h6 class="text-uppercase text-muted mb-2">Status Pembayaran:</h6>
                        @if($invoice->remaining_amount == 0)
                            <span class="badge bg-success fs-5 p-2">LUNAS</span>
                        @else
                            <span class="badge bg-warning text-dark fs-5 p-2">BELUM LUNAS</span>
                        @endif
                        <p class="mt-2 mb-0 small text-danger">Jatuh Tempo: {{ \Carbon\Carbon::parse($invoice->due_date)->translatedFormat('d F Y') }}</p>
                    </div>
                </div>

                <h4 class="text-primary mb-3"><i class="bi bi-list-columns-reverse me-2"></i> Rincian Layanan</h4>
                <div class="table-responsive mb-5">
                    <table class="table table-bordered table-striped align-middle">
                        <thead class="table-custom-header">
                            <tr>
                                <th style="width: 5%;" class="text-center">#</th>
                                <th>Deskripsi</th>
                                <th style="width: 15%;" class="text-center">Qty</th>
                                <th style="width: 20%;" class="text-end">Harga Satuan</th>
                                <th style="width: 20%;" class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($invoice->items as $index => $item)
                                <tr>
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td>{{ $item->description }}</td>
                                    <td class="text-center">{{ $item->quantity }}</td>
                                    <td class="text-end">Rp {{ number_format($item->price, 0, ',', '.') }}</td>
                                    <td class="text-end fw-bold">Rp {{ number_format($item->quantity * $item->price, 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-light fw-bold">
                                <td colspan="4" class="text-end">TOTAL TAGIHAN</td>
                                <td class="text-end text-primary">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                @if($invoice->paymentSteps->count() > 0 && ($invoice->total_amount - $invoice->remaining_amount) > 0)
                    <h4 class="text-primary mb-3"><i class="bi bi-cash-stack me-2"></i> Riwayat Pembayaran</h4>
                    <div class="table-responsive mb-5">
                        <table class="table table-sm table-striped align-middle">
                            <thead class="table-info">
                                <tr>
                                    <th style="width: 10%;" class="text-center">Ke-</th>
                                    <th style="width: 25%;">Tanggal Pembayaran</th>
                                    <th>Deskripsi</th>
                                    <th style="width: 20%;" class="text-end">Jumlah Dibayar</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->paymentSteps->sortBy('step_date') as $index => $step)
                                    <tr>
                                        <td class="text-center">{{ $index + 1 }}</td>
                                        <td>{{ \Carbon\Carbon::parse($step->step_date)->translatedFormat('d F Y') }}</td>
                                        <td>{{ $step->description ?? 'Pembayaran' }}</td>
                                        <td class="text-end fw-bold">Rp {{ number_format($step->amount_paid, 0, ',', '.') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr class="bg-success-subtle fw-bold">
                                    <td colspan="3" class="text-end">TOTAL SUDAH DIBAYAR</td>
                                    <td class="text-end text-success">Rp {{ number_format($invoice->total_amount - $invoice->remaining_amount, 0, ',', '.') }}</td>
                                </tr>
                                <tr class="bg-danger-subtle fw-bold">
                                    <td colspan="3" class="text-end">SISA TAGIHAN</td>
                                    <td class="text-end text-danger">Rp {{ number_format($invoice->remaining_amount, 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
                
                @if($invoice->notes)
                    <div class="alert alert-secondary mt-4">
                        <p class="fw-bold mb-1">Catatan Tambahan:</p>
                        <p class="mb-0 small">{{ $invoice->notes }}</p>
                    </div>
                @endif
                
                <div class="row mt-5 pt-3 border-top">
                    <div class="col-md-12 text-center">
                        <p class="text-muted small mb-0">
                            Dokumen ini disahkan secara digital. {{ $companyName }}.
                        </p>
                        <p class="text-muted small mb-0">
                            Hash Verifikasi: {{ $invoice->unique_hash }}
                        </p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>