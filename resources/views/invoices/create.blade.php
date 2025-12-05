<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ isset($invoice) ? 'Edit Invoice' : 'Buat Invoice Baru' }}</h2>
    </x-slot>

    <div class="py-6">
        <div class="container-lg">
            <div class="card shadow-sm border-0">
                <div class="card-body p-6">
                    <!-- Debug Info -->
                    <div class="alert alert-info d-none" id="debugInfo">
                        <strong>Debug Info:</strong>
                        <div id="debugContent"></div>
                    </div>

                    @if($errors->any())
                        <div class="alert alert-danger">
                            <h6>Error Validasi:</h6>
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('whatsapp_success'))
                        <div class="alert alert-success">
                            {{ session('whatsapp_success') }}
                        </div>
                    @endif

                    @if(session('whatsapp_error'))
                        <div class="alert alert-warning">
                            {{ session('whatsapp_error') }}
                        </div>
                    @endif

                    @if($isDuplicated ?? false)
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="bi bi-info-circle"></i> 
                            <strong>Invoice berhasil diduplikat!</strong> Silakan lanjutkan pembayaran dengan data yang sama. Nomor invoice telah diperbarui secara otomatis.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    @endif

                    <form method="POST" action="{{ isset($invoice) ? route('invoices.update', $invoice) : route('invoices.store') }}" enctype="multipart/form-data" novalidate id="invoiceForm">
                        @csrf
                        @if(isset($invoice))
                            @method('PUT')
                            <input type="hidden" name="invoice_id" value="{{ $invoice->id }}">
                        @endif

                        <!-- Informasi Dasar -->
                        <h5 class="mb-3 border-bottom pb-2">Informasi Dasar</h5>
                        
                        <div class="row mb-3">
                            <div class="col-lg-6">
                                <label class="form-label">Tanggal Invoice <span class="text-danger">*</span></label>
                                <input type="date" name="invoice_date" class="form-control shadow-sm @error('invoice_date') is-invalid @enderror" 
                                       value="{{ old('invoice_date', $invoice->invoice_date ?? today()->toDateString()) }}" required>
                                @error('invoice_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Wajib diisi</small>
                            </div>
                            <div class="col-lg-6">
                                <label class="form-label">Batas Waktu Pembayaran (Opsional)</label>
                                <input type="date" name="payment_deadline" class="form-control shadow-sm @error('payment_deadline') is-invalid @enderror" 
                                       value="{{ old('payment_deadline', $invoice->payment_deadline ?? '') }}">
                                @error('payment_deadline')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-lg-6">
                                <label class="form-label">Nama yang Ditagih (Client) <span class="text-danger">*</span></label>
                                <input type="text" name="client_name" class="form-control shadow-sm @error('client_name') is-invalid @enderror" 
                                       value="{{ old('client_name', $invoice->client_name ?? '') }}" placeholder="Nama klien" required>
                                @error('client_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Wajib diisi</small>
                            </div>
                        </div>

                        <!-- Rincian Tagihan -->
                        <h5 class="mb-3 border-bottom pb-2">Rincian Tagihan <span class="text-danger">*</span></h5>
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> Semua field dalam rincian tagihan wajib diisi.
                        </div>
                        
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-hover" id="items_table">
                                <thead class="table-light">
                                    <tr>
                                        <th>Deskripsi <span class="text-danger">*</span></th>
                                        <th style="width: 120px;">Jumlah <span class="text-danger">*</span></th>
                                        <th style="width: 150px;">Harga <span class="text-danger">*</span></th>
                                        <th style="width: 150px;">Subtotal</th>
                                        <th style="width: 60px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $oldItems = old('items', isset($invoice) ? $invoice->items->toArray() : [['description'=>'','qty'=>1,'price'=>0]]);
                                    @endphp
                                    @foreach($oldItems as $i => $it)
                                    <tr>
                                        <td>
                                            <input name="items[{{ $i }}][description]" 
                                                   class="form-control form-control-sm shadow-sm" 
                                                   value="{{ $it['description'] ?? '' }}" 
                                                   placeholder="Deskripsi item" 
                                                   required>
                                            <small class="text-muted d-block">Wajib</small>
                                        </td>
                                        <td>
                                            <input type="number" min="1" name="items[{{ $i }}][qty]" 
                                                   class="form-control form-control-sm shadow-sm qty" 
                                                   value="{{ $it['qty'] ?? 1 }}" 
                                                   required>
                                            <small class="text-muted d-block">Wajib</small>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="items[{{ $i }}][price]" 
                                                   class="form-control form-control-sm shadow-sm price" 
                                                   value="{{ isset($it['price']) ? number_format((float)$it['price'], 0, ',', '.') : '0' }}" 
                                                   placeholder="0" 
                                                   required>
                                            <small class="text-muted d-block">Wajib</small>
                                        </td>
                                        <td class="subtotal text-end fw-bold">
                                            @php
                                                $qty = isset($it['qty']) ? (int)$it['qty'] : 0;
                                                $price = isset($it['price']) ? (float)str_replace('.', '', $it['price']) : 0;
                                                $subtotal = $qty * $price;
                                            @endphp
                                            {{ $subtotal > 0 ? number_format($subtotal, 0, ',', '.') : '0' }}
                                        </td>
                                        <td class="text-center"><button class="btn btn-sm btn-danger remove-row" type="button"><i class="bi bi-trash"></i></button></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="button" id="add_item" class="btn btn-sm btn-outline-primary mb-3">
                            <i class="bi bi-plus-circle"></i> Tambah Item
                        </button>

                        <!-- Tahapan Pembayaran -->
                        <h5 class="mb-3 border-bottom pb-2">Tahapan Pembayaran <span class="text-danger">*</span></h5>
                        <div class="alert alert-info mb-3">
                            <i class="bi bi-info-circle"></i> Minimal satu tahapan pembayaran wajib diisi. Field dengan tanda * wajib diisi.
                        </div>
                        
                        <div class="table-responsive mb-3">
                            <table class="table table-bordered table-hover" id="payment_table">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">Pembayaran ke <span class="text-danger">*</span></th>
                                        <th>Jumlah <span class="text-danger">*</span></th>
                                        <th style="width: 150px;">Tanggal Pembayaran</th>
                                        <th>Bank Tujuan</th>
                                        <th style="width: 60px;"></th>
                                    </tr>
                                </thead>
                                <tbody id="payment_steps">
                                    @php $psOld = old('payment_steps', isset($invoice) ? $invoice->paymentSteps->toArray() : [['step_number'=>1,'amount'=>0,'payment_date'=>'','bank_name'=>'']]); @endphp
                                    @forelse($psOld as $i => $ps)
                                    <tr class="payment-row">
                                        <td>
                                            <input type="number" min="1" name="payment_steps[{{ $i }}][step_number]" 
                                                   class="form-control form-control-sm shadow-sm" 
                                                   value="{{ $ps['step_number'] ?? 1 }}" 
                                                   placeholder="1" 
                                                   required>
                                            <small class="text-muted d-block">Wajib</small>
                                        </td>
                                        <td>
                                            <input type="text" 
                                                   name="payment_steps[{{ $i }}][amount]" 
                                                   class="form-control form-control-sm shadow-sm payment-amount" 
                                                   value="{{ $ps['amount'] ? number_format((float)$ps['amount'], 0, ',', '.') : '0' }}" 
                                                   placeholder="0" 
                                                   required>
                                            <small class="text-muted d-block">Wajib</small>
                                        </td>
                                        <td>
                                            <input type="date" name="payment_steps[{{ $i }}][payment_date]" 
                                                   class="form-control form-control-sm shadow-sm" 
                                                   value="{{ $ps['payment_date'] ?? '' }}">
                                            <small class="text-muted d-block">Opsional</small>
                                        </td>
                                        <td>
                                            <input type="text" name="payment_steps[{{ $i }}][bank_name]" 
                                                   class="form-control form-control-sm shadow-sm" 
                                                   value="{{ $ps['bank_name'] ?? '' }}" 
                                                   placeholder="Nama Bank">
                                            <small class="text-muted d-block">Opsional</small>
                                        </td>
                                        <td class="text-center"><button class="btn btn-sm btn-danger remove-payment" type="button"><i class="bi bi-trash"></i></button></td>
                                    </tr>
                                    @empty
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <button type="button" id="add_payment" class="btn btn-sm btn-outline-secondary mb-3">
                            <i class="bi bi-plus-circle"></i> Tambah Tahapan Pembayaran
                        </button>

                        <!-- Total Received -->
                        <div class="row mb-3">
                            <div class="col-lg-6">
                                <div class="alert alert-success mb-0">
                                    <strong>Total Diterima: Rp <span id="total_received">0</span></strong>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="alert alert-warning mb-0">
                                    <strong>Sisa Pembayaran: Rp <span id="remaining_amount">0</span></strong>
                                </div>
                            </div>
                        </div>

                        <!-- Total -->
                        <div class="alert alert-info mb-3">
                            <strong>Total Tagihan: Rp <span id="total_amount">0</span></strong>
                        </div>

                        <!-- Template & Lampiran -->
                        <h5 class="mb-3 border-bottom pb-2">Opsi Tambahan</h5>
                        
                        <div class="row mb-3">
                            <div class="col-lg-6">
                                <label class="form-label">Template Invoice</label>
                                <select name="template" class="form-select shadow-sm">
                                    <option value="default" {{ (old('template', $invoice->template ?? '')=='default') ? 'selected' : '' }}>Default</option>
                                    <option value="modern" {{ (old('template', $invoice->template ?? '')=='modern') ? 'selected' : '' }}>Modern</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label">Lampiran (Opsional)</label>
                            <input type="file" name="attachments[]" multiple class="form-control shadow-sm">
                            <small class="text-muted">Pilih satu atau lebih file untuk dilampirkan</small>
                        </div>

                        <!-- Tombol -->
                        <div class="d-flex gap-3 mt-5">
                            <button class="btn btn-success shadow" type="submit" style="min-width: 120px;">
                                <i class="bi bi-check-circle"></i> {{ isset($invoice) ? 'Perbarui' : 'Simpan' }}
                            </button>
                            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary shadow" style="min-width: 120px;">
                                <i class="bi bi-x-circle"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Invoice form calculation script loaded - WITH VALIDATION');

        // Fungsi untuk validasi form
        function validateForm() {
            const form = document.getElementById('invoiceForm');
            let isValid = true;
            let errorMessages = [];

            // Validasi field required
            const requiredFields = form.querySelectorAll('[required]');
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                    errorMessages.push(`Field ${field.name} harus diisi`);
                } else {
                    field.classList.remove('is-invalid');
                }
            });

            // Validasi minimal satu item
            const itemRows = document.querySelectorAll('#items_table tbody tr');
            if (itemRows.length === 0) {
                isValid = false;
                errorMessages.push('Minimal satu item harus ditambahkan');
            }

            // Validasi minimal satu tahapan pembayaran
            const paymentRows = document.querySelectorAll('#payment_steps tr');
            if (paymentRows.length === 0) {
                isValid = false;
                errorMessages.push('Minimal satu tahapan pembayaran harus ditambahkan');
            }

            // Validasi angka positif
            const numberInputs = form.querySelectorAll('input[type="number"]');
            numberInputs.forEach(input => {
                if (input.value && parseFloat(input.value) <= 0) {
                    input.classList.add('is-invalid');
                    isValid = false;
                    errorMessages.push(`${input.name} harus lebih dari 0`);
                }
            });

            if (!isValid) {
                alert('Terdapat kesalahan:\n' + errorMessages.join('\n'));
            }

            return isValid;
        }

        // Prevent external script conflicts
        window.invoiceFormInitialized = true;

        // Safe element selector dengan null checking
        function getElementSafe(selector) {
            const element = document.querySelector(selector);
            if (!element) {
                console.warn('Element not found:', selector);
            }
            return element;
        }

        // Elements dengan safe checking
        const addItemBtn = getElementSafe('#add_item');
        const addPaymentBtn = getElementSafe('#add_payment');
        const totalAmountEl = getElementSafe('#total_amount');
        const totalReceivedEl = getElementSafe('#total_received');
        const remainingAmountEl = getElementSafe('#remaining_amount');

        // Jika element penting tidak ditemukan, stop execution
        if (!addItemBtn || !totalAmountEl) {
            console.error('Required elements not found, stopping script');
            return;
        }

        // Fungsi untuk mengubah format angka Indonesia ke number
        function parseIndonesianNumber(str) {
            if (!str || str === '') return 0;
            const cleanStr = String(str).replace(/[^\d,]/g, '');
            return parseFloat(cleanStr.replace(',', '.')) || 0;
        }

        // Fungsi untuk memformat angka ke format Indonesia
        function formatIndonesianNumber(num) {
            return new Intl.NumberFormat('id-ID').format(num);
        }

        // Fungsi untuk menghitung subtotal per baris
        function calculateSubtotal(row) {
            const qtyInput = row.querySelector('.qty');
            const priceInput = row.querySelector('.price');
            const subtotalCell = row.querySelector('.subtotal');
            
            if (!qtyInput || !priceInput || !subtotalCell) return 0;
            
            const qty = parseInt(qtyInput.value) || 0;
            const price = parseIndonesianNumber(priceInput.value);
            const subtotal = qty * price;
            
            subtotalCell.textContent = formatIndonesianNumber(subtotal);
            return subtotal;
        }

        // Fungsi untuk menghitung total semua item
        function calculateTotal() {
            let total = 0;
            const rows = document.querySelectorAll('#items_table tbody tr');
            
            rows.forEach(row => {
                total += calculateSubtotal(row);
            });
            
            if (totalAmountEl) {
                totalAmountEl.textContent = formatIndonesianNumber(total);
            }
            return total;
        }

        // Fungsi untuk menghitung total pembayaran
        function calculateTotalReceived() {
            let totalReceived = 0;
            const paymentInputs = document.querySelectorAll('.payment-amount');
            
            paymentInputs.forEach(input => {
                totalReceived += parseIndonesianNumber(input.value);
            });
            
            if (totalReceivedEl) {
                totalReceivedEl.textContent = formatIndonesianNumber(totalReceived);
            }
            return totalReceived;
        }

        // Fungsi untuk update semua perhitungan
        function updateAllCalculations() {
            const total = calculateTotal();
            const totalReceived = calculateTotalReceived();
            const remaining = Math.max(0, total - totalReceived);
            
            if (remainingAmountEl) {
                remainingAmountEl.textContent = formatIndonesianNumber(remaining);
            }
        }

        // Format input harga saat blur (setelah selesai edit)
        function handleBlur(e) {
            if (e.target.classList.contains('price') || e.target.classList.contains('payment-amount')) {
                const value = parseIndonesianNumber(e.target.value);
                if (!isNaN(value) && value >= 0) {
                    e.target.value = formatIndonesianNumber(value);
                    updateAllCalculations();
                }
            }
        }

        // Recalculate saat ada perubahan input
        function handleInput(e) {
            if (e.target.classList.contains('qty') || 
                e.target.classList.contains('price') || 
                e.target.classList.contains('payment-amount')) {
                updateAllCalculations();
            }
        }

        // Tambah item baru
        function addItemHandler() {
            console.log('Add item clicked');
            const tbody = document.querySelector('#items_table tbody');
            if (!tbody) return;
            
            const rowCount = tbody.querySelectorAll('tr').length;
            
            const newRow = document.createElement('tr');
            newRow.innerHTML = `
                <td>
                    <input name="items[${rowCount}][description]" class="form-control form-control-sm shadow-sm" placeholder="Deskripsi item" required>
                    <small class="text-muted d-block">Wajib</small>
                </td>
                <td>
                    <input type="number" min="1" name="items[${rowCount}][qty]" class="form-control form-control-sm shadow-sm qty" value="1" required>
                    <small class="text-muted d-block">Wajib</small>
                </td>
                <td>
                    <input type="text" name="items[${rowCount}][price]" class="form-control form-control-sm shadow-sm price" value="0" placeholder="0" required>
                    <small class="text-muted d-block">Wajib</small>
                </td>
                <td class="subtotal text-end fw-bold">0</td>
                <td class="text-center"><button class="btn btn-sm btn-danger remove-row" type="button"><i class="bi bi-trash"></i></button></td>
            `;
            
            tbody.appendChild(newRow);
            updateAllCalculations();
        }

        // Tambah payment step baru
        function addPaymentHandler() {
            console.log('Add payment clicked');
            const tbody = document.getElementById('payment_steps');
            if (!tbody) return;
            
            const rowCount = tbody.querySelectorAll('tr').length;
            
            const newRow = document.createElement('tr');
            newRow.className = 'payment-row';
            newRow.innerHTML = `
                <td>
                    <input type="number" min="1" name="payment_steps[${rowCount}][step_number]" class="form-control form-control-sm shadow-sm" value="${rowCount + 1}" placeholder="1" required>
                    <small class="text-muted d-block">Wajib</small>
                </td>
                <td>
                    <input type="text" name="payment_steps[${rowCount}][amount]" class="form-control form-control-sm shadow-sm payment-amount" value="0" placeholder="0" required>
                    <small class="text-muted d-block">Wajib</small>
                </td>
                <td>
                    <input type="date" name="payment_steps[${rowCount}][payment_date]" class="form-control form-control-sm shadow-sm">
                    <small class="text-muted d-block">Opsional</small>
                </td>
                <td>
                    <input type="text" name="payment_steps[${rowCount}][bank_name]" class="form-control form-control-sm shadow-sm" placeholder="Nama Bank">
                    <small class="text-muted d-block">Opsional</small>
                </td>
                <td class="text-center"><button class="btn btn-sm btn-danger remove-payment" type="button"><i class="bi bi-trash"></i></button></td>
            `;
            
            tbody.appendChild(newRow);
            updateAllCalculations();
        }

        // Hapus row
        function handleRemoveClick(e) {
            if (e.target.closest('.remove-row')) {
                e.preventDefault();
                const row = e.target.closest('tr');
                if (row && document.querySelectorAll('#items_table tbody tr').length > 1) {
                    row.remove();
                    updateAllCalculations();
                } else {
                    alert('Minimal harus ada satu item');
                }
            }
            
            if (e.target.closest('.remove-payment')) {
                e.preventDefault();
                const row = e.target.closest('tr');
                if (row && document.querySelectorAll('#payment_steps tr').length > 1) {
                    row.remove();
                    updateAllCalculations();
                } else {
                    alert('Minimal harus ada satu tahapan pembayaran');
                }
            }
        }

        // Setup event listeners dengan proteksi duplikasi
        function setupEventListeners() {
            console.log('Setting up event listeners');
            
            // Remove existing event listeners by cloning buttons
            if (addItemBtn) {
                const newAddItemBtn = addItemBtn.cloneNode(true);
                addItemBtn.parentNode.replaceChild(newAddItemBtn, addItemBtn);
                newAddItemBtn.addEventListener('click', addItemHandler);
            }
            
            if (addPaymentBtn) {
                const newAddPaymentBtn = addPaymentBtn.cloneNode(true);
                addPaymentBtn.parentNode.replaceChild(newAddPaymentBtn, addPaymentBtn);
                newAddPaymentBtn.addEventListener('click', addPaymentHandler);
            }
            
            // Event delegation untuk remove buttons
            document.addEventListener('click', handleRemoveClick);
            
            // Event untuk input dan blur
            document.addEventListener('blur', handleBlur, true);
            document.addEventListener('input', handleInput);
        }

        // Format semua nilai yang sudah ada saat load
        document.querySelectorAll('.price, .payment-amount').forEach(input => {
            const value = parseIndonesianNumber(input.value);
            if (!isNaN(value) && value >= 0) {
                input.value = formatIndonesianNumber(value);
            }
        });

        // Initialize
        setTimeout(() => {
            setupEventListeners();
            updateAllCalculations();
            console.log('Invoice form calculations initialized');

            // Tambahkan validasi sebelum submit
            const form = document.getElementById('invoiceForm');
            form.addEventListener('submit', function(e) {
                if (!validateForm()) {
                    e.preventDefault();
                    return false;
                }
                return true;
            });
        }, 100);
    });
    </script>
</x-app-layout>