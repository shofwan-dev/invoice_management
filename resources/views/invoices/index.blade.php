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

            <div class="card shadow-sm border-0">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Daftar Invoice</h5>
                    <a href="{{ route('invoices.create') }}" class="btn btn-success btn-sm shadow-sm">
                        <i class="bi bi-plus-circle"></i> Buat Invoice Baru
                    </a>
                </div>

                <div class="card-body">
                    <form class="row g-2 mb-4" method="GET" action="{{ route('invoices.index') }}">
                        <div class="col-md-4">
                            <input name="q" value="{{ request('q') }}" class="form-control shadow-sm" placeholder="Cari nomor atau nama client">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-outline-primary w-100 shadow-sm">Cari</button>
                        </div>
                    </form>

                    <!-- Bulk Controls - PASTIKAN ID SESUAI -->
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="btn-group" role="group">
                            <button type="button" id="selectAllBtnInvoices" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-check-square"></i> Pilih Semua
                            </button>
                            <button type="button" id="unselectAllBtnInvoices" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-square"></i> Batalkan Pilihan
                            </button>
                        </div>

                        <!-- Bulk Actions Bar -->
                        <div id="invoiceBulkBar" class="alert alert-info d-none align-items-center mb-0" style="min-width:260px;">
                            <div class="d-flex justify-content-between align-items-center w-100">
                                <span><strong id="selectedCountInvoices">0</strong> invoice dipilih</span>
                                <div class="btn-group ms-3" role="group">
                                    <button type="button" id="invoiceBulkExport" class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-file-pdf"></i> Export
                                    </button>
                                    <button type="button" id="invoiceBulkDelete" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash"></i> Hapus
                                    </button>
                                    <button type="button" id="invoiceBulkClear" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-x"></i> Batal
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th style="width: 30px;">
                                        <input type="checkbox" id="selectAllInvoices" class="form-check-input">
                                    </th>
                                    <th>Nomor Invoice</th>
                                    <th>Client</th>
                                    <th>Tanggal</th>
                                    <th class="text-end">Total</th>
                                    <th>Bayaran Pertama</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($invoices as $inv)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input invoice-checkbox" value="{{ $inv->id }}">
                                    </td>
                                    <td><strong>{{ $inv->invoice_number }}</strong></td>
                                    <td>{{ $inv->client_name }}</td>
                                    <td>{{ \Carbon\Carbon::parse($inv->invoice_date)->format('d/m/Y') }}</td>
                                    <td class="text-end"><strong>Rp {{ number_format($inv->total_amount,0,',','.') }}</strong></td>
                                    <td>
                                        @if($inv->paymentSteps->first())
                                            Rp {{ number_format($inv->paymentSteps->first()->amount, 0, ',', '.') }}<br>
                                            <small class="text-muted">{{ $inv->paymentSteps->first()->payment_date ? \Carbon\Carbon::parse($inv->paymentSteps->first()->payment_date)->format('d/m/Y') : '-' }}</small>
                                        @else
                                            <span class="badge bg-secondary">Belum ada</span>
                                        @endif
                                    </td>
                                    <td>
                                        <a href="{{ route('invoices.show', $inv) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('invoices.edit', $inv) }}" class="btn btn-sm btn-outline-warning">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="{{ route('invoices.export', $inv) }}" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-file-pdf"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">Belum ada invoice</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>

                <div class="card-footer bg-light">
                    {{ $invoices->links() }}
                </div>
            </div>
        </div>
    </div>

    <!-- SCRIPT SEDERHANA DAN LANGSUNG -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Invoice selection manager started');

    // Elements
    const selectAllCheckbox = document.getElementById('selectAllInvoices');
    const selectAllBtn = document.getElementById('selectAllBtnInvoices');
    const unselectAllBtn = document.getElementById('unselectAllBtnInvoices');
    const bulkActionsBar = document.getElementById('invoiceBulkBar');
    const selectedCountEl = document.getElementById('selectedCountInvoices');
    const bulkExportBtn = document.getElementById('invoiceBulkExport');
    const bulkDeleteBtn = document.getElementById('invoiceBulkDelete');
    const clearSelectionBtn = document.getElementById('invoiceBulkClear');

    // Function to get all invoice checkboxes
    function getCheckboxes() {
        return document.querySelectorAll('.invoice-checkbox');
    }

    // Function to get selected invoice IDs
    function getSelectedIds() {
        return Array.from(getCheckboxes())
            .filter(cb => cb.checked)
            .map(cb => cb.value);
    }

    // Function to update the UI
    function updateSelectionUI() {
        const checkedCount = getSelectedIds().length;
        
        console.log('Updating UI - checked count:', checkedCount);
        
        // Update bulk actions bar
        if (checkedCount > 0) {
            bulkActionsBar.classList.remove('d-none');
        } else {
            bulkActionsBar.classList.add('d-none');
        }
        
        // Update selected count
        selectedCountEl.textContent = checkedCount;
        
        // Update select all checkbox
        const checkboxes = getCheckboxes();
        const allChecked = checkboxes.length > 0 && checkedCount === checkboxes.length;
        const someChecked = checkedCount > 0 && checkedCount < checkboxes.length;
        
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = allChecked;
            selectAllCheckbox.indeterminate = someChecked;
        }
    }

    // Select all function
    function selectAllInvoices() {
        console.log('Selecting all invoices');
        getCheckboxes().forEach(checkbox => {
            checkbox.checked = true;
        });
        updateSelectionUI();
    }

    // Unselect all function  
    function unselectAllInvoices() {
        console.log('Unselecting all invoices');
        getCheckboxes().forEach(checkbox => {
            checkbox.checked = false;
        });
        updateSelectionUI();
    }

    // Submit bulk action form
    function submitBulkAction(url, method, selectedIds) {
        console.log('Submitting bulk action:', method, selectedIds);
        
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        
        // CSRF token
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '_token';
        csrfInput.value = '{{ csrf_token() }}';
        form.appendChild(csrfInput);
        
        // Method spoofing for DELETE
        if (method === 'DELETE') {
            const methodInput = document.createElement('input');
            methodInput.type = 'hidden';
            methodInput.name = '_method';
            methodInput.value = 'DELETE';
            form.appendChild(methodInput);
        }
        
        // Selected IDs
        selectedIds.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = id;
            form.appendChild(input);
        });
        
        document.body.appendChild(form);
        form.submit();
    }

    // Event listeners
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            getCheckboxes().forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectionUI();
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', selectAllInvoices);
    }

    if (unselectAllBtn) {
        unselectAllBtn.addEventListener('click', unselectAllInvoices);
    }

    if (clearSelectionBtn) {
        clearSelectionBtn.addEventListener('click', unselectAllInvoices);
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

            console.log('Bulk export for IDs:', selectedIds);
            submitBulkAction('{{ route("invoices.bulk-export") }}', 'POST', selectedIds);
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

            console.log('Bulk delete for IDs:', selectedIds);
            submitBulkAction('{{ route("invoices.bulk-delete") }}', 'DELETE', selectedIds);
        });
    }

    // Initialize
    updateSelectionUI();
    console.log('Invoice selection manager initialized');
});
</script>
</x-app-layout>