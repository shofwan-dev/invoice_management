<!-- resources/views/dashboard.blade.php -->
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Dashboard</h2>
    </x-slot>

    <div class="py-6">
        <div class="container-lg">
            <!-- Statistik Ringkas -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-primary shadow-sm">
                        <div class="card-body text-center">
                            <h6 class="card-title text-muted">Total Invoice</h6>
                            <p class="card-text fs-4 fw-bold text-primary">{{ $totalInvoices }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-success shadow-sm">
                        <div class="card-body text-center">
                            <h6 class="card-title text-muted">Total Tagihan</h6>
                            <p class="card-text fs-4 fw-bold text-success">Rp {{ number_format($totalAmount, 0, ',', '.') }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-warning shadow-sm">
                        <div class="card-body text-center">
                            <h6 class="card-title text-muted">Belum Lunas</h6>
                            <p class="card-text fs-4 fw-bold text-warning">{{ $unpaidInvoices }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="card border-info shadow-sm">
                        <div class="card-body text-center">
                            <h6 class="card-title text-muted">Total Lunas</h6>
                            <p class="card-text fs-4 fw-bold text-info">{{ $paidInvoices }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Grafik Transaksi -->
            <div class="row mb-4">
                <!-- Grafik Bulanan -->
                <div class="col-lg-8 mb-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 text-dark">
                                    <i class="bi bi-bar-chart text-primary me-2"></i>Statistik Bulanan ({{ date('Y') }})
                                </h5>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary active" data-chart-type="bar">Bar</button>
                                    <button type="button" class="btn btn-outline-primary" data-chart-type="line">Line</button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body bg-white">
                            @if($monthlyStats->isNotEmpty())
                                <div style="position: relative; height: 300px;">
                                    <canvas id="monthlyChart"></canvas>
                                </div>
                                <div class="mt-3">
                                    <div class="row text-center">
                                        <div class="col-md-4 mb-2">
                                            <span class="badge bg-primary">
                                                <i class="bi bi-receipt me-1"></i>Total Invoice: {{ $monthlyStats->sum('invoice_count') }}
                                            </span>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <span class="badge bg-success">
                                                <i class="bi bi-cash-coin me-1"></i>Total: Rp {{ number_format($monthlyStats->sum('total_amount'), 0, ',', '.') }}
                                            </span>
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <span class="badge bg-info">
                                                <i class="bi bi-graph-up me-1"></i>Rata-rata: Rp {{ number_format($monthlyStats->avg('total_amount'), 0, ',', '.') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="text-center py-5">
                                    <i class="bi bi-bar-chart text-muted display-6"></i>
                                    <p class="text-muted mt-3">Belum ada data transaksi bulanan</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Grafik Tahunan -->
                <div class="col-lg-4 mb-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white border-bottom">
                            <h5 class="mb-0 text-dark">
                                <i class="bi bi-pie-chart text-success me-2"></i>Distribusi Tahunan
                            </h5>
                        </div>
                        <div class="card-body bg-white">
                            @if($yearlyStats->isNotEmpty())
                                <div style="position: relative; height: 250px;">
                                    <canvas id="yearlyChart"></canvas>
                                </div>
                                <div class="mt-3">
                                    <div id="yearlyLegend" class="d-flex flex-wrap justify-content-center gap-2"></div>
                                </div>
                            @else
                                <div class="text-center py-5">
                                    <i class="bi bi-pie-chart text-muted display-6"></i>
                                    <p class="text-muted mt-3">Belum ada data tahunan</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Invoices -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0 text-dark">Invoice Terbaru</h5>
                </div>
                <div class="card-body bg-white p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nomor</th>
                                    <th>Client</th>
                                    <th>Tanggal</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentInvoices as $invoice)
                                @php
                                    $remaining = $invoice->remaining_amount;
                                    $isPaid = $remaining == 0;
                                    $statusClass = $isPaid ? 'bg-success' : 'bg-warning';
                                    $statusText = $isPaid ? 'Lunas' : 'Belum Lunas';
                                @endphp
                                <tr>
                                    <td><strong>{{ $invoice->invoice_number }}</strong></td>
                                    <td>{{ $invoice->client_name }}</td>
                                    <td>{{ \Carbon\Carbon::parse($invoice->invoice_date)->format('d/m/Y') }}</td>
                                    <td class="text-end fw-bold">Rp {{ number_format($invoice->total_amount, 0, ',', '.') }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
                                    </td>
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
                                    <td colspan="6" class="text-center text-muted py-4">Belum ada invoice</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white">
                    <a href="{{ route('invoices.index') }}" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-arrow-right"></i> Lihat semua invoice
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Warna untuk chart
        const colors = [
            '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
            '#06B6D4', '#84CC16', '#F97316', '#6366F1', '#EC4899',
            '#14B8A6', '#F43F5E', '#0EA5E9', '#22C55E', '#FACC15'
        ];

        @if($monthlyStats->isNotEmpty())
        // Data untuk grafik bulanan
        const monthlyLabels = [
            @foreach($monthlyStats as $stat)
            "{{ \Carbon\Carbon::createFromFormat('m', $stat->month)->translatedFormat('MMMM') }}",
            @endforeach
        ];
        
        const monthlyInvoiceCounts = [
            @foreach($monthlyStats as $stat)
            {{ $stat->invoice_count }},
            @endforeach
        ];
        
        const monthlyAmounts = [
            @foreach($monthlyStats as $stat)
            {{ $stat->total_amount }},
            @endforeach
        ];

        // Buat array warna untuk setiap bulan
        const monthlyColors = monthlyLabels.map((_, index) => colors[index % colors.length]);

        // Grafik Bulanan
        const monthlyCtx = document.getElementById('monthlyChart');
        let monthlyChart = new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [{
                    label: 'Jumlah Invoice',
                    data: monthlyInvoiceCounts,
                    backgroundColor: monthlyColors.map(color => color + '80'), // 50% opacity
                    borderColor: monthlyColors,
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Total Tagihan (Juta Rp)',
                    data: monthlyAmounts.map(amount => amount / 1000000),
                    type: 'line',
                    borderColor: '#EF4444',
                    backgroundColor: '#FEE2E2',
                    borderWidth: 2,
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6B7280'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Jumlah Invoice',
                            color: '#6B7280'
                        },
                        ticks: {
                            color: '#6B7280',
                            stepSize: 1
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.05)'
                        }
                    },
                    y1: {
                        beginAtZero: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Total (Juta Rp)',
                            color: '#6B7280'
                        },
                        ticks: {
                            color: '#6B7280',
                            callback: function(value) {
                                return 'Rp ' + value;
                            }
                        },
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                },
                plugins: {
                    legend: {
                        labels: {
                            color: '#374151',
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.95)',
                        titleColor: '#1F2937',
                        bodyColor: '#1F2937',
                        borderColor: '#E5E7EB',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (context.datasetIndex === 1) {
                                    const value = context.raw * 1000000;
                                    return label + ': Rp ' + value.toLocaleString('id-ID');
                                }
                                return label + ': ' + context.raw;
                            }
                        }
                    }
                }
            }
        });

        // Tombol toggle chart type
        document.querySelectorAll('[data-chart-type]').forEach(button => {
            button.addEventListener('click', function() {
                const chartType = this.dataset.chartType;
                
                // Update active button
                document.querySelectorAll('[data-chart-type]').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                // Update chart type
                if (chartType === 'line') {
                    monthlyChart.data.datasets[0].type = 'line';
                    monthlyChart.data.datasets[0].borderWidth = 2;
                    monthlyChart.data.datasets[0].tension = 0.4;
                    monthlyChart.data.datasets[1].hidden = true;
                } else {
                    monthlyChart.data.datasets[0].type = 'bar';
                    monthlyChart.data.datasets[0].borderWidth = 1;
                    monthlyChart.data.datasets[1].hidden = false;
                }
                
                monthlyChart.update();
            });
        });
        @endif

        @if($yearlyStats->isNotEmpty())
        // Data untuk grafik tahunan
        const yearlyLabels = [
            @foreach($yearlyStats as $stat)
            "{{ $stat->year }}",
            @endforeach
        ];
        
        const yearlyData = [
            @foreach($yearlyStats as $stat)
            {{ $stat->total_amount }},
            @endforeach
        ];
        
        const yearlyColors = yearlyLabels.map((_, index) => colors[index % colors.length]);

        // Grafik Tahunan - Doughnut Chart
        const yearlyCtx = document.getElementById('yearlyChart');
        const yearlyChart = new Chart(yearlyCtx, {
            type: 'doughnut',
            data: {
                labels: yearlyLabels,
                datasets: [{
                    data: yearlyData,
                    backgroundColor: yearlyColors,
                    borderColor: '#FFFFFF',
                    borderWidth: 2,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '60%',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.95)',
                        titleColor: '#1F2937',
                        bodyColor: '#1F2937',
                        borderColor: '#E5E7EB',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((value / total) * 100).toFixed(1);
                                return `${label}: Rp ${value.toLocaleString('id-ID')} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Buat custom legend untuk pie chart
        const yearlyLegend = document.getElementById('yearlyLegend');
        yearlyChart.data.labels.forEach((label, i) => {
            const legendItem = document.createElement('div');
            legendItem.className = 'd-flex align-items-center mb-1';
            legendItem.innerHTML = `
                <span class="badge me-2" style="background-color: ${yearlyColors[i]}; width: 12px; height: 12px; padding: 0;"></span>
                <small class="text-muted">${label}</small>
            `;
            yearlyLegend.appendChild(legendItem);
        });
        @endif
    });
    </script>
</x-app-layout>