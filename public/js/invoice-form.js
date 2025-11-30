// Format angka ke Rupiah tanpa desimal
function formatRupiah(num) {
    if (!num || isNaN(num)) return '0';
    // Ensure integer value, no decimals
    const intNum = Math.floor(Number(num));
    const formatted = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
        useGrouping: true
    }).format(intNum);
    // Ensure no .00 suffix
    return formatted.replace(/,00$/, '');
}

// Parse rupiah string ke number (handle formatted strings like "50.000" or "50000")
function parseRupiah(str) {
    if (!str) return 0;
    str = str.toString().trim();
    // Remove all dots (thousands separator in Indonesian format)
    const cleaned = str.replace(/\./g, '');
    const num = parseFloat(cleaned);
    return isNaN(num) ? 0 : num;
}

// Calculate subtotal for items
function calculateItemSubtotal() {
    let total = 0;
    document.querySelectorAll('#items_table tbody tr').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty')?.value || 0);
        const price = parseRupiah(row.querySelector('.price')?.value || 0);
        const subtotal = qty * price;
        if (row.querySelector('.subtotal')) {
            row.querySelector('.subtotal').textContent = formatRupiah(subtotal);
        }
        total += subtotal;
    });

    const totalEl = document.getElementById('total_amount');
    if (totalEl) {
        totalEl.textContent = formatRupiah(total);
    }
    calculateRemainingAmount();
}

// Calculate total received from payment steps
function calculateTotalReceived() {
    let totalReceived = 0;
    document.querySelectorAll('#payment_steps tr.payment-row').forEach(row => {
        const amount = parseRupiah(row.querySelector('.payment-amount')?.value || 0);
        totalReceived += amount;
    });

    const totalReceivedEl = document.getElementById('total_received');
    if (totalReceivedEl) {
        totalReceivedEl.textContent = formatRupiah(totalReceived);
    }
    calculateRemainingAmount();
}

// Calculate remaining amount
function calculateRemainingAmount() {
    const totalText = document.getElementById('total_amount')?.textContent || '0';
    const receivedText = document.getElementById('total_received')?.textContent || '0';

    const total = parseRupiah(totalText);
    const received = parseRupiah(receivedText);
    const remaining = Math.max(0, total - received);

    const remainingEl = document.getElementById('remaining_amount');
    if (remainingEl) {
        remainingEl.textContent = formatRupiah(remaining);
    }
}

// Format input on blur - items price
function formatPriceInput(input) {
    if (input.value) {
        const value = parseRupiah(input.value);
        input.value = formatRupiah(value);
    }
}

// Format input on blur - payment amount
function formatPaymentInput(input) {
    if (input.value) {
        const value = parseRupiah(input.value);
        input.value = formatRupiah(value);
    }
}

// Add item row
document.getElementById('add_item')?.addEventListener('click', function() {
    const table = document.getElementById('items_table');
    const rowCount = table.querySelectorAll('tbody tr').length;
    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <td><input name="items[${rowCount}][description]" class="form-control form-control-sm shadow-sm" placeholder="Deskripsi item" required></td>
        <td><input type="number" min="1" name="items[${rowCount}][qty]" class="form-control form-control-sm shadow-sm qty" value="1" required></td>
        <td><input type="text" name="items[${rowCount}][price]" class="form-control form-control-sm shadow-sm price" placeholder="0" required></td>
        <td class="subtotal text-end fw-bold">0</td>
        <td class="text-center"><button class="btn btn-sm btn-danger remove-row" type="button"><i class="bi bi-trash"></i></button></td>
    `;
    table.querySelector('tbody').appendChild(newRow);
    attachItemListeners(newRow);
});

// Remove item row
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-row')) {
        e.target.closest('tr').remove();
        calculateItemSubtotal();
    }
});

// Add payment step row
document.getElementById('add_payment')?.addEventListener('click', function() {
    const table = document.getElementById('payment_table');
    const rowCount = table.querySelectorAll('tbody tr').length;
    const newRow = document.createElement('tr');
    newRow.classList.add('payment-row');
    newRow.innerHTML = `
        <td><input type="number" min="1" name="payment_steps[${rowCount}][step_number]" class="form-control form-control-sm shadow-sm" value="${rowCount + 1}" placeholder="1" required></td>
        <td><input type="text" name="payment_steps[${rowCount}][amount]" class="form-control form-control-sm shadow-sm payment-amount" placeholder="0" required></td>
        <td><input type="date" name="payment_steps[${rowCount}][payment_date]" class="form-control form-control-sm shadow-sm"></td>
        <td><input type="text" name="payment_steps[${rowCount}][bank_name]" class="form-control form-control-sm shadow-sm" placeholder="Nama Bank"></td>
        <td class="text-center"><button class="btn btn-sm btn-danger remove-payment" type="button"><i class="bi bi-trash"></i></button></td>
    `;
    table.querySelector('tbody').appendChild(newRow);
    attachPaymentListeners(newRow);
});

// Remove payment row
document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-payment')) {
        e.target.closest('tr').remove();
        calculateTotalReceived();
    }
});

// Attach listeners to item inputs
function attachItemListeners(row) {
    row.querySelectorAll('.qty').forEach(input => {
        input.addEventListener('change', calculateItemSubtotal);
        input.addEventListener('input', calculateItemSubtotal);
    });
    
    row.querySelectorAll('.price').forEach(input => {
        input.addEventListener('input', function() {
            // Allow only digits (0-9)
            this.value = this.value.replace(/[^0-9]/g, '');
            calculateItemSubtotal();
        });
        input.addEventListener('blur', function() {
            formatPriceInput(this);
            calculateItemSubtotal();
        });
    });
}

// Attach listeners to payment inputs
function attachPaymentListeners(row) {
    row.querySelectorAll('.payment-amount').forEach(input => {
        input.addEventListener('input', function() {
            // Allow only digits (0-9)
            this.value = this.value.replace(/[^0-9]/g, '');
            calculateTotalReceived();
        });
        input.addEventListener('blur', function() {
            formatPaymentInput(this);
            calculateTotalReceived();
        });
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Immediate initialization
    console.log('Invoice form initializing...');
    
    // Attach listeners to existing item rows
    const itemRows = document.querySelectorAll('#items_table tbody tr');
    console.log('Found ' + itemRows.length + ' item rows');
    itemRows.forEach(row => {
        attachItemListeners(row);
    });
    
    // Attach listeners to existing payment rows
    const paymentRows = document.querySelectorAll('#payment_steps tr.payment-row');
    console.log('Found ' + paymentRows.length + ' payment rows');
    paymentRows.forEach(row => {
        attachPaymentListeners(row);
    });
    
    // Initial calculations
    console.log('Running initial calculations...');
    calculateItemSubtotal();
    calculateTotalReceived();
    calculateRemainingAmount();

    const formEl = document.querySelector('form');
    if (formEl) {
        formEl.addEventListener('submit', function() {
            document.querySelectorAll('.price').forEach(input => {
                input.value = String(parseRupiah(input.value));
            });
            document.querySelectorAll('.payment-amount').forEach(input => {
                input.value = String(parseRupiah(input.value));
            });
        });
    }
    console.log('Invoice form initialized');
});
