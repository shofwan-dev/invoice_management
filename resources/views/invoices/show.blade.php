<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Invoice {{ $invoice->invoice_number }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="container-lg">
            <!-- Tombol Aksi -->
            <div class="mb-4 d-flex gap-2 flex-wrap">
                <a href="{{ route('invoices.export', $invoice) }}" class="btn btn-success shadow-sm" title="Download PDF">
                    <i class="bi bi-file-pdf"></i> Export PDF
                </a>
                <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-warning shadow-sm" title="Edit invoice">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                <form action="{{ route('invoices.destroy', $invoice) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus invoice ini?');">
                    @csrf 
                    @method('DELETE')
                    <button class="btn btn-danger shadow-sm" title="Hapus invoice">
                        <i class="bi bi-trash"></i> Hapus
                    </button>
                </form>
                <form action="{{ route('invoices.continue', $invoice) }}" method="POST" class="d-inline">
                    @csrf
                    <button class="btn btn-primary shadow-sm" title="Lanjutkan Pembayaran" onclick="return confirm('Buat invoice baru dengan data yang sama?')">
                        <i class="bi bi-copy"></i> Lanjutkan Pembayaran
                    </button>
                </form>
                <form action="{{ route('invoices.whatsapp', $invoice) }}" method="POST" class="d-inline">
                    @csrf
                    <button class="btn btn-info shadow-sm" title="Kirim ke WhatsApp">
                        <i class="bi bi-whatsapp"></i> WhatsApp
                    </button>
                </form>
            </div>

            <!-- Invoice Card -->
            <div class="card shadow-sm border-0 p-4">
                <!-- Header -->
                <div class="row mb-4 pb-3 border-bottom">
                    <div class="col-md-6">
                        @php
                            $companyLogo = \App\Models\Setting::get('company_logo');
                        @endphp
                        @if($companyLogo && strpos($companyLogo, 'data:') !== 0)
                            <div class="mb-3">
                                <img src="{{ asset($companyLogo) }}" alt="Company Logo" style="max-height: 60px; max-width: 300px; object-fit: contain;" />
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
                                <a href="{{ asset('storage/' . $att->path) }}" target="_blank" class="text-decoration-none">
                                    <i class="bi bi-file"></i> {{ $att->filename }}
                                </a>
                                <small class="text-muted">{{ number_format($att->size / 1024, 2) }} KB</small>
                            </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <!-- Footer -->
                <div class="row mt-5 pt-4 border-top">
                    <div class="col-md-6">
                        <p class="text-muted small mb-0">Terima kasih telah berbisnis dengan kami.</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <p class="mb-4">_____________________</p>
                        <p class="text-muted small mb-0">{{ \App\Models\Setting::get('pic_name', 'Penanggung Jawab') }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>