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
                    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
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

                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label for="whatsapp_endpoint" class="form-label">Endpoint API</label>
                                <input type="url" class="form-control shadow-sm @error('whatsapp_endpoint') is-invalid @enderror" id="whatsapp_endpoint" name="whatsapp_endpoint" placeholder="https://api.example.com/send" value="{{ old('whatsapp_endpoint', $settings['whatsapp_endpoint']) }}">
                                @error('whatsapp_endpoint')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-6 mb-3">
                                <label for="whatsapp_api_key" class="form-label">API Key</label>
                                <input type="password" class="form-control shadow-sm @error('whatsapp_api_key') is-invalid @enderror" id="whatsapp_api_key" name="whatsapp_api_key" placeholder="Masukkan API Key" value="{{ old('whatsapp_api_key', $settings['whatsapp_api_key']) }}">
                                @error('whatsapp_api_key')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-lg-6 mb-3">
                                <label for="whatsapp_sender" class="form-label">Nomor Pengirim (Sender)</label>
                                <input type="text" class="form-control shadow-sm @error('whatsapp_sender') is-invalid @enderror" id="whatsapp_sender" name="whatsapp_sender" placeholder="62xxxxxxxxxx" value="{{ old('whatsapp_sender', $settings['whatsapp_sender']) }}">
                                @error('whatsapp_sender')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-lg-6 mb-4">
                                <label for="whatsapp_number" class="form-label">Nomor Penerima Default</label>
                                <input type="text" class="form-control shadow-sm @error('whatsapp_number') is-invalid @enderror" id="whatsapp_number" name="whatsapp_number" placeholder="62xxxxxxxxxx" value="{{ old('whatsapp_number', $settings['whatsapp_number']) }}">
                                @error('whatsapp_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
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
</x-app-layout>
