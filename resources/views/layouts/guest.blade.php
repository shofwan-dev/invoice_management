<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
            <div class="text-center">
                <a href="/" class="block">
                    @php
                        // Cache selama 24 jam (86400 detik)
                        $companyLogo = cache()->remember('company_logo', 86400, function () {
                            return \App\Models\Setting::get('company_logo');
                        });
                        $companyName = cache()->remember('company_name', 86400, function () {
                            return \App\Models\Setting::get('company_name', 'PT ABCD');
                        });
                    @endphp
                    @if($companyLogo && $companyLogo !== 'data:image/png;base64,' && strpos($companyLogo, 'data:') !== 0)
                        <img src="{{ asset($companyLogo) }}" alt="{{ $companyName }}" class="w-20 h-20 object-contain mx-auto" />
                    @else
                        <x-application-logo class="w-20 h-20 fill-current text-gray-500 mx-auto" />
                    @endif
                </a>
                
                <!-- Judul di bawah logo -->
                <div class="mt-4">
                    <h1 class="text-xl font-semibold text-gray-800">Sistem Informasi</h1>
                    <p class="text-lg font-semibold text-gray-600 mt-1">{{ $companyName }}</p>
                </div>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>