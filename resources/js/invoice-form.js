// Invoice Form JavaScript
document.addEventListener('DOMContentLoaded', function() {
    console.log('Invoice form script loaded');

    // Recalculate total
    function recalc() {
        let total = 0;
        document.querySelectorAll('#items_table tbody tr').forEach(function(row){
            const qty = parseFloat(row.querySelector('.qty')?.value || 0);
            const price = parseFloat(row.querySelector('.price')?.value || 0);
            const subtotal = qty * price;
            const subtotalEl = row.querySelector('.subtotal');
            if (subtotalEl) {
                subtotalEl.innerText = subtotal.toFixed(2);
            }
            total += subtotal;
        });
        const totalEl = document.getElementById('total_amount');
        if (totalEl) {
            totalEl.innerText = total.toFixed(2);
        }
    }

    // Attach events to item rows
    function attachRowEvents(row) {
        row.querySelectorAll('.qty, .price').forEach(function(el){
            el.addEventListener('input', recalc);
        });
        row.querySelectorAll('.remove-row').forEach(function(btn){
            btn.addEventListener('click', function(e){ 
                e.preventDefault(); 
                row.remove(); 
                recalc(); 
            });
        });
    }

    // Tambah Item Button
    const addItemBtn = document.getElementById('add_item');
    if (addItemBtn) {
        console.log('Add item button found');
        addItemBtn.addEventListener('click', function(e){
            e.preventDefault();
            console.log('Add item clicked');
            const tbody = document.querySelector('#items_table tbody');
            if (!tbody) {
                console.error('Items table tbody not found');
                return;
            }
            const index = tbody.querySelectorAll('tr').length;
            const tr = document.createElement('tr');
            tr.innerHTML = `<td><input name="items[${index}][description]" class="form-control form-control-sm shadow-sm" placeholder="Deskripsi item" required></td>
            <td><input type="number" min="1" name="items[${index}][qty]" class="form-control form-control-sm shadow-sm qty" value="1" required></td>
            <td><input type="number" step="0.01" min="0" name="items[${index}][price]" class="form-control form-control-sm shadow-sm price" value="0" placeholder="0.00" required></td>
            <td class="subtotal text-end fw-bold">0.00</td>
            <td class="text-center"><button class="btn btn-sm btn-danger remove-row" type="button"><i class="bi bi-trash"></i></button></td>`;
            tbody.appendChild(tr);
            attachRowEvents(tr);
            recalc();
            console.log('New item row added');
        });
    } else {
        console.warn('Add item button not found');
    }

    // Attach events to existing item rows
    document.querySelectorAll('#items_table tbody tr').forEach(function(r){ 
        attachRowEvents(r); 
    });

    // Tambah Tahapan Pembayaran Button
    const addPaymentBtn = document.getElementById('add_payment');
    if (addPaymentBtn) {
        console.log('Add payment button found');
        addPaymentBtn.addEventListener('click', function(e){
            e.preventDefault();
            console.log('Add payment clicked');
            const container = document.getElementById('payment_steps');
            if (!container) {
                console.error('Payment steps container not found');
                return;
            }
            const tbody = document.querySelector('#payment_table tbody');
            if (!tbody) {
                console.error('Payment table tbody not found');
                return;
            }
            const index = tbody.querySelectorAll('tr').length;
            const tr = document.createElement('tr');
            tr.className = 'payment-row';
            tr.innerHTML = `<td><input type="number" min="1" name="payment_steps[${index}][step_number]" class="form-control form-control-sm shadow-sm" value="${index + 1}" required></td>
            <td><input type="number" step="0.01" name="payment_steps[${index}][amount]" class="form-control form-control-sm shadow-sm" placeholder="0.00" required></td>
            <td><input type="text" name="payment_steps[${index}][bank_name]" class="form-control form-control-sm shadow-sm" placeholder="Nama Bank" required></td>
            <td class="text-center"><button class="btn btn-sm btn-danger remove-payment" type="button"><i class="bi bi-trash"></i></button></td>`;
            tbody.appendChild(tr);
            
            // Attach remove event
            tr.querySelector('.remove-payment').addEventListener('click', function(e){ 
                e.preventDefault(); 
                tr.remove(); 
            });
            console.log('New payment step row added');
        });
    } else {
        console.warn('Add payment button not found');
    }

    // Attach remove events to existing payment steps
    document.querySelectorAll('.remove-payment').forEach(function(b){ 
        b.addEventListener('click', function(e){ 
            e.preventDefault(); 
            b.closest('.payment-row').remove(); 
        }); 
    });

    // Initial recalc
    recalc();
    console.log('Invoice form initialized');
});
