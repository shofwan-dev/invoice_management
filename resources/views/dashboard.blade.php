<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="container-lg">
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-lg-4 mb-3">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <p class="text-muted small mb-2">Total Invoice</p>
                            <h3 class="text-primary fw-bold mb-0">{{ $totalInvoices }}</h3>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-3">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <p class="text-muted small mb-2">Total Amount</p>
                            <h3 class="text-success fw-bold mb-0">Rp {{ number_format($totalAmount, 2, ',', '.') }}</h3>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-3">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <p class="text-muted small mb-2">Aksi Cepat</p>
                            <a href="{{ route('invoices.create') }}" class="btn btn-sm btn-primary">
                                <i class="bi bi-plus-circle"></i> Buat Invoice
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Invoices -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light border-bottom">
                    <h5 class="mb-0">Invoice Terbaru</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nomor</th>
                                    <th>Client</th>
                                    <th>Tanggal</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentInvoices as $invoice)
                                <tr>
                                    <td><strong>{{ $invoice->invoice_number }}</strong></td>
                                    <td>{{ $invoice->client_name }}</td>
                                    <td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}</td>
                                    <td class="text-end fw-bold">Rp {{ number_format($invoice->total_amount, 2, ',', '.') }}</td>
                                    <td class="text-center">
                                        <a href="{{ route('invoices.show', $invoice) }}" class="btn btn-sm btn-outline-primary" title="Lihat detail">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('invoices.export', $invoice) }}" class="btn btn-sm btn-outline-success" title="Export PDF">
                                            <i class="bi bi-file-pdf"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">Belum ada invoice</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light">
                    <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-arrow-right"></i> Lihat semua invoice
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
