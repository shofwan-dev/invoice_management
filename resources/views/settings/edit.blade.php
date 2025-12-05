<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Pengaturan Aplikasi</h2>
    </x-slot>

    <div class="py-6">
        <div class="container-md">
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" id="settings-form">
                        @csrf

                        <!-- Perusahaan Section -->
                        <h5 class="mb-3 border-bottom pb-2">Informasi Perusahaan</h5>

                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label for="company_name" class="form-label">Nama Perusahaan</label>
                                <input type="text" class="form-control shadow-sm @error('company_name') is-invalid @enderror" id="company_name" name="company_name" value="{{ old('company_name', $settings['company_name']) }}" required>
                                @error('company_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-6 mb-3">
                                <label for="pic_name" class="form-label">Nama Penanggung Jawab</label>
                                <input type="text" class="form-control shadow-sm @error('pic_name') is-invalid @enderror" id="pic_name" name="pic_name" value="{{ old('pic_name', $settings['pic_name']) }}" required>
                                @error('pic_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="company_logo" class="form-label">Logo Perusahaan</label>
                            <input type="file" class="form-control shadow-sm @error('company_logo') is-invalid @enderror" id="company_logo" name="company_logo" accept="image/*">
                            @error('company_logo')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            @if($settings['company_logo'] && $settings['company_logo'] !== 'data:image/png;base64,')
                                <small class="text-muted d-block mt-2">Logo saat ini: <img src="{{ $settings['company_logo'] }}" style="max-height: 50px;" /></small>
                            @endif
                        </div>

                        <!-- WhatsApp Gateway Section -->
                        <hr class="my-4">
                        <h5 class="mb-3">Pengaturan WhatsApp Gateway</h5>

                        <!-- Status Koneksi -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <label class="form-label">Status Koneksi:</label>
                                <div>
                                    @php
                                        // Gunakan $whatsappStatus yang dikirim dari controller
                                        $statusBadgeClass = [
                                            'connected' => 'bg-success',
                                            'failed' => 'bg-danger', 
                                            'unknown' => 'bg-secondary'
                                        ][$whatsappStatus ?? 'unknown'];
                                        
                                        $statusText = [
                                            'connected' => 'Terhubung',
                                            'failed' => 'Tidak Terhubung',
                                            'unknown' => 'Belum Diuji'
                                        ][$whatsappStatus ?? 'unknown'];
                                    @endphp
                                    <span id="whatsapp-status" class="badge {{ $statusBadgeClass }} fs-6">{{ $statusText }}</span>
                                    
                                    @if($whatsappStatus === 'connected' && session('whatsapp_last_test'))
                                        <small class="text-muted d-block mt-1">
                                            Terakhir diuji: {{ \Carbon\Carbon::parse(session('whatsapp_last_test'))->diffForHumans() }}
                                        </small>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label for="whatsapp_endpoint" class="form-label">Endpoint API</label>
                                <input type="url" class="form-control shadow-sm @error('whatsapp_endpoint') is-invalid @enderror" id="whatsapp_endpoint" name="whatsapp_endpoint" placeholder="https://wa.mutekar.com/send-message" value="{{ old('whatsapp_endpoint', $settings['whatsapp_endpoint']) }}">
                                @error('whatsapp_endpoint')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-6 mb-3">
                                <label for="whatsapp_api_key" class="form-label">API Key</label>
                                <div class="input-group">
                                    <input type="password" class="form-control shadow-sm @error('whatsapp_api_key') is-invalid @enderror" id="whatsapp_api_key" name="whatsapp_api_key" placeholder="Masukkan API Key" value="{{ old('whatsapp_api_key', $settings['whatsapp_api_key']) }}">
                                    <button type="button" class="btn btn-outline-secondary" id="toggle-api-key">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                </div>
                                @error('whatsapp_api_key')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label for="whatsapp_sender" class="form-label">Nomor Pengirim (Sender)</label>
                                <input type="text" class="form-control shadow-sm @error('whatsapp_sender') is-invalid @enderror" id="whatsapp_sender" name="whatsapp_sender" placeholder="62888xxxx" value="{{ old('whatsapp_sender', $settings['whatsapp_sender']) }}">
                                @error('whatsapp_sender')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-6 mb-4">
                                <label for="whatsapp_number" class="form-label">Nomor Penerima Default</label>
                                <input type="text" class="form-control shadow-sm @error('whatsapp_number') is-invalid @enderror" id="whatsapp_number" name="whatsapp_number" placeholder="62888xxxx" value="{{ old('whatsapp_number', $settings['whatsapp_number']) }}">
                                @error('whatsapp_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Tombol Test Koneksi -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <button type="button" id="test-whatsapp-connection" class="btn btn-info shadow-sm">
                                    <i class="bi bi-plug"></i> Test Koneksi WhatsApp
                                </button>
                                <small class="text-muted d-block mt-1">Test akan mengirim pesan "Test Connection" ke nomor penerima default</small>
                            </div>
                        </div>

                        <div class="d-flex gap-2 mt-5">
                            <button type="submit" class="btn btn-success shadow-sm" style="min-width: 120px;">
                                <i class="bi bi-check-circle"></i> Simpan
                            </button>
                            <a href="{{ route('invoices.index') }}" class="btn btn-outline-secondary shadow-sm" style="min-width: 120px;">
                                <i class="bi bi-x-circle"></i> Batal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- Main WhatsApp Test Script --}}
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const testButton = document.getElementById('test-whatsapp-connection');
        const statusBadge = document.getElementById('whatsapp-status');
        
        console.log('DOM loaded - initializing WhatsApp test...');
        
        if (testButton) {
            console.log('Test button found, adding event listener...');
            
            testButton.addEventListener('click', function() {
                console.log('Test button clicked - starting WhatsApp test...');
                
                // Ambil nilai dari form
                const endpoint = document.querySelector('[name="whatsapp_endpoint"]').value;
                const apiKey = document.querySelector('[name="whatsapp_api_key"]').value;
                const sender = document.querySelector('[name="whatsapp_sender"]').value;
                const number = document.querySelector('[name="whatsapp_number"]').value;

                console.log('Form values:', { endpoint, apiKey, sender, number });

                // Validasi form
                if (!endpoint || !apiKey || !sender || !number) {
                    alert('Harap isi semua field WhatsApp gateway sebelum testing!');
                    return;
                }

                // Update status
                statusBadge.className = 'badge bg-warning fs-6';
                statusBadge.textContent = 'Testing...';
                testButton.disabled = true;

                // Kirim request test
                fetch('{{ route('settings.test-whatsapp') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        whatsapp_endpoint: endpoint,
                        whatsapp_api_key: apiKey,
                        whatsapp_sender: sender,
                        whatsapp_number: number
                    })
                })
                .then(response => {
                    console.log('Response status:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        statusBadge.className = 'badge bg-success fs-6';
                        statusBadge.textContent = 'Terhubung';
                        
                        // Tampilkan timestamp
                        const now = new Date();
                        const timeString = now.toLocaleTimeString('id-ID', { 
                            hour: '2-digit', 
                            minute: '2-digit' 
                        });
                        const timestamp = document.createElement('small');
                        timestamp.className = 'text-muted d-block mt-1';
                        timestamp.textContent = `Terakhir diuji: ${timeString}`;
                        
                        // Hapus timestamp sebelumnya jika ada
                        const existingTimestamp = statusBadge.nextElementSibling;
                        if (existingTimestamp && existingTimestamp.tagName === 'SMALL') {
                            existingTimestamp.remove();
                        }
                        statusBadge.parentNode.appendChild(timestamp);
                    } else {
                        statusBadge.className = 'badge bg-danger fs-6';
                        statusBadge.textContent = 'Tidak Terhubung';
                        console.error('Error:', data.message);
                        alert('Test gagal: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    statusBadge.className = 'badge bg-danger fs-6';
                    statusBadge.textContent = 'Error';
                    alert('Terjadi error: ' + error.message);
                })
                .finally(() => {
                    testButton.disabled = false;
                });
            });
        } else {
            console.error('Test button not found!');
        }

        // Toggle API Key visibility
        const toggleApiKey = document.getElementById('toggle-api-key');
        const apiKeyInput = document.getElementById('whatsapp_api_key');
        
        if (toggleApiKey && apiKeyInput) {
            toggleApiKey.addEventListener('click', function() {
                const type = apiKeyInput.getAttribute('type') === 'password' ? 'text' : 'password';
                apiKeyInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
            });
        }
    });
    </script>
</x-app-layout>