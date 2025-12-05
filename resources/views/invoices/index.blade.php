<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Riwayat Invoice</h2>
    </x-slot>

    <div class="py-6">
        <div class="container-lg">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <!-- Statistik Ringkas -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-primary bg-light">
                        <div class="card-body text-center">
                            <h6 class="card-title text-muted">Total Invoice</h6>
                            <p class="card-text fs-4 fw-bold text-primary">{{ $invoices->total() }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success bg-light">
                        <div class="card-body text-center">
                            <h6 class="card-title text-muted">Total Tagihan</h6>
                            <p class="card-text fs-4 fw-bold text-success">Rp {{ number_format($totalInvoiceAmount, 0, ',', '.') }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-warning bg-light">
                        <div class="card-body text-center">
                            <h6 class="card-title text-muted">Belum Lunas</h6>
                            <p class="card-text fs-4 fw-bold text-warning">{{ $unpaidInvoices }}</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-info bg-light">
                        <div class="card-body text-center">
                            <h6 class="card-title text-muted">Total Lunas</h6>
                            <p class="card-text fs-4 fw-bold text-info">{{ $paidInvoices }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Daftar Invoice</h5>
                    <a href="{{ route('invoices.create') }}" class="btn btn-success btn-sm shadow-sm">
                        <i class="bi bi-plus-circle"></i> Buat Invoice Baru
                    </a>
                </div>

                <div class="card-body">
                    <!-- Search & Filter -->
                    <form class="row g-2 mb-4" method="GET" action="{{ route('invoices.index') }}">
                        <div class="col-md-3">
                            <input name="q" value="{{ request('q') }}" class="form-control shadow-sm" placeholder="Cari nomor/nama client">
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select shadow-sm">
                                <option value="">Semua Status</option>
                                <option value="lunas" {{ request('status') == 'lunas' ? 'selected' : '' }}>Lunas</option>
                                <option value="belum_lunas" {{ request('status') == 'belum_lunas' ? 'selected' : '' }}>Belum Lunas</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="start_date" value="{{ request('start_date') }}" class="form-control shadow-sm" placeholder="Dari tanggal">
                        </div>
                        <div class="col-md-2">
                            <input type="date" name="end_date" value="{{ request('end_date') }}" class="form-control shadow-sm" placeholder="Sampai tanggal">
                        </div>
                        <div class="col-md-1">
                            <button class="btn btn-outline-primary w-100 shadow-sm">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <div class="col-md-1">
                            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary w-100 shadow-sm">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </form>

                    <!-- Bulk Actions (Optional) -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="d-flex gap-2">
                            <button type="button" id="selectAllBtn" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-check-square"></i> Pilih Semua
                            </button>
                            <button type="button" id="deselectAllBtn" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-square"></i> Batalkan
                            </button>
                        </div>
                        
                        <div id="bulkActions" class="d-none align-items-center gap-2">
                            <span class="text-muted"><span id="selectedCount">0</span> dipilih</span>
                            <button type="button" id="bulkExportBtn" class="btn btn-outline-success btn-sm">
                                <i class="bi bi-file-pdf"></i> Export
                            </button>
                            <button type="button" id="bulkDeleteBtn" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-trash"></i> Hapus
                            </button>
                        </div>
                    </div>

                    <!-- Table -->
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th>Nomor Invoice</th>
                                    <th>Client</th>
                                    <th>Tanggal</th>
                                    <th class="text-end">Total Tagihan</th>
                                    <th class="text-end">Sisa Tagihan</th>
                                    <th class="text-center">Status</th>
                                    <th style="width: 180px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoices as $inv)
                                @php
                                    $remaining = $inv->remaining_amount;
                                    $isPaid = $remaining == 0;
                                    $statusClass = $isPaid ? 'bg-success' : 'bg-warning';
                                    $statusText = $isPaid ? 'Lunas' : 'Belum Lunas';
                                @endphp
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input invoice-checkbox" value="{{ $inv->id }}">
                                    </td>
                                    <td>
                                        <strong>{{ $inv->invoice_number }}</strong>
                                        @if($inv->payment_deadline && !$isPaid && now() > \Carbon\Carbon::parse($inv->payment_deadline))
                                            <span class="badge bg-danger ms-1">Terlambat</span>
                                        @endif
                                    </td>
                                    <td>{{ $inv->client_name }}</td>
                                    <td>
                                        {{ \Carbon\Carbon::parse($inv->invoice_date)->format('d/m/Y') }}
                                        @if($inv->payment_deadline)
                                            <br>
                                            <small class="text-muted">Batas: {{ \Carbon\Carbon::parse($inv->payment_deadline)->format('d/m/Y') }}</small>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <strong>Rp {{ number_format($inv->total_amount, 0, ',', '.') }}</strong>
                                    </td>
                                    <td class="text-end">
                                        @if($isPaid)
                                            <span class="text-success fw-bold">Rp 0</span>
                                        @else
                                            <span class="text-danger fw-bold">Rp {{ number_format($remaining, 0, ',', '.') }}</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('invoices.show', $inv) }}" class="btn btn-sm btn-outline-primary" title="Lihat Detail">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="{{ route('invoices.edit', $inv) }}" class="btn btn-sm btn-outline-warning" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="{{ route('invoices.export', $inv) }}" class="btn btn-sm btn-outline-success" title="Export PDF">
                                                <i class="bi bi-file-pdf"></i>
                                            </a>
                                            <form action="{{ route('invoices.whatsapp', $inv) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-info" title="Kirim WhatsApp">
                                                    <i class="bi bi-whatsapp"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-receipt display-6"></i>
                                        <p class="mt-2">Belum ada invoice</p>
                                        <a href="{{ route('invoices.create') }}" class="btn btn-primary btn-sm mt-2">
                                            <i class="bi bi-plus-circle"></i> Buat Invoice Pertama
                                        </a>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>

                <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                    <div class="text-muted">
                        Menampilkan {{ $invoices->firstItem() ?? 0 }} - {{ $invoices->lastItem() ?? 0 }} dari {{ $invoices->total() }} invoice
                    </div>
                    {{ $invoices->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript untuk Bulk Actions -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Elements
        const selectAllCheckbox = document.getElementById('selectAll');
        const selectAllBtn = document.getElementById('selectAllBtn');
        const deselectAllBtn = document.getElementById('deselectAllBtn');
        const bulkActionsDiv = document.getElementById('bulkActions');
        const selectedCountSpan = document.getElementById('selectedCount');
        const bulkExportBtn = document.getElementById('bulkExportBtn');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
        
        // Get all checkboxes
        function getCheckboxes() {
            return document.querySelectorAll('.invoice-checkbox');
        }
        
        // Get selected IDs
        function getSelectedIds() {
            return Array.from(getCheckboxes())
                .filter(cb => cb.checked)
                .map(cb => cb.value);
        }
        
        // Update UI based on selection
        function updateSelectionUI() {
            const selectedCount = getSelectedIds().length;
            
            // Show/hide bulk actions
            if (selectedCount > 0) {
                bulkActionsDiv.classList.remove('d-none');
                bulkActionsDiv.classList.add('d-flex');
            } else {
                bulkActionsDiv.classList.add('d-none');
                bulkActionsDiv.classList.remove('d-flex');
            }
            
            // Update count
            selectedCountSpan.textContent = selectedCount;
            
            // Update select all checkbox state
            const checkboxes = getCheckboxes();
            const allChecked = checkboxes.length > 0 && selectedCount === checkboxes.length;
            const someChecked = selectedCount > 0 && selectedCount < checkboxes.length;
            
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked;
        }
        
        // Event Listeners
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function() {
                getCheckboxes().forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
                updateSelectionUI();
            });
        }
        
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                getCheckboxes().forEach(checkbox => {
                    checkbox.checked = true;
                });
                updateSelectionUI();
            });
        }
        
        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function() {
                getCheckboxes().forEach(checkbox => {
                    checkbox.checked = false;
                });
                updateSelectionUI();
            });
        }
        
        // Individual checkbox changes
        document.addEventListener('change', function(e) {
            if (e.target.classList.contains('invoice-checkbox')) {
                updateSelectionUI();
            }
        });
        
        // Bulk Export
        if (bulkExportBtn) {
            bulkExportBtn.addEventListener('click', function() {
                const selectedIds = getSelectedIds();
                
                if (selectedIds.length === 0) {
                    alert('Pilih minimal satu invoice untuk diexport.');
                    return;
                }
                
                // Create form for bulk export
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("invoices.bulk-export") }}';
                
                // CSRF token
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = '{{ csrf_token() }}';
                form.appendChild(csrfInput);
                
                // Add selected IDs
                selectedIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
            });
        }
        
        // Bulk Delete
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', function() {
                const selectedIds = getSelectedIds();
                
                if (selectedIds.length === 0) {
                    alert('Pilih minimal satu invoice untuk dihapus.');
                    return;
                }
                
                if (!confirm(`Anda yakin ingin menghapus ${selectedIds.length} invoice? Tindakan ini tidak dapat dibatalkan.`)) {
                    return;
                }
                
                // Create form for bulk delete
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '{{ route("invoices.bulk-delete") }}';
                
                // CSRF token
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = '{{ csrf_token() }}';
                form.appendChild(csrfInput);
                
                // Method spoofing for DELETE
                const methodInput = document.createElement('input');
                methodInput.type = 'hidden';
                methodInput.name = '_method';
                methodInput.value = 'DELETE';
                form.appendChild(methodInput);
                
                // Add selected IDs
                selectedIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
            });
        }
        
        // Initialize
        updateSelectionUI();
    });
    </script>
</x-app-layout>