<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body{font-family: DejaVu Sans, sans-serif;}
        table{width:100%; border-collapse:collapse;}
        .header-table td{border:none; padding:5px;}
        .logo-cell{width:20%; vertical-align:top;}
        .company-cell{width:50%; vertical-align:top;}
        .invoice-cell{width:30%; text-align:right; vertical-align:top;}
        .company-name{font-size:18px; font-weight:bold; margin:0 0 5px 0;}
        .company-pic{font-size:12px; color:#555;}
        .items-table, .payments-table {width:100%; border-collapse: collapse; margin-top:15px;}
        .items-table th, .items-table td, .payments-table th, .payments-table td{border:1px solid #ddd; padding:8px;}
    </style>
</head>
<body>
    <table class="header-table" style="margin-bottom:30px;">
        <tr>
            <td class="logo-cell">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" alt="Company Logo" style="max-height:70px; width:auto;" />
                @endif
            </td>
            <td class="company-cell">
                <div class="company-name">{{ \App\Models\Setting::get('company_name', 'PT. Perusahaan Anda') }}</div>
                <div class="company-pic">Penanggung Jawab: {{ \App\Models\Setting::get('pic_name', 'Penanggung Jawab') }}</div>
            </td>
            <td class="invoice-cell">
                <div style="font-weight:bold; font-size:14px; margin-bottom:5px;">INVOICE</div>
                <div style="margin-bottom:3px;"><strong>No:</strong> {{ $invoice->invoice_number }}</div>
                <div><strong>Tanggal:</strong> {{ $invoice->invoice_date }}</div>
            </td>
        </tr>
    </table>

    <h4>Client: {{ $invoice->client_name }}</h4>

    <table class="items-table">
        <thead>
            <tr><th>#</th><th>Deskripsi</th><th>Qty</th><th>Harga</th><th>Subtotal</th></tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $i => $it)
            <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $it->description }}</td>
                <td>{{ $it->qty }}</td>
                <td style="text-align:right">{{ number_format($it->price,0,',','.') }}</td>
                <td style="text-align:right">{{ number_format($it->subtotal,2,',','.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table style="width:100%; margin-top:10px;">
        <tr>
            <td style="border: none; text-align:right; font-weight:bold;">Total: Rp {{ number_format($invoice->total_amount,2,',','.') }}</td>
        </tr>
    </table>

    <h5>Payment Steps</h5>
    <table class="payments-table">
        <thead><tr><th>Step</th><th>Amount</th><th>Payment Date</th></tr></thead>
        <tbody>
            @foreach($invoice->paymentSteps as $ps)
            <tr>
                <td>{{ $ps->step_number }}</td>
                <td style="text-align:right">{{ number_format($ps->amount,2,',','.') }}</td>
                <td>{{ $ps->payment_date ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div style="margin-top:40px;">
        <div style="float:right; text-align:center;">
            <div>__________________</div>
            <div>Authorized Signature</div>
        </div>
    </div>

</body>
</html>
