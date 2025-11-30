<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function edit()
    {
        $settings = [
            'company_name' => Setting::get('company_name', 'PT. Perusahaan Anda'),
            'company_logo' => Setting::get('company_logo', 'data:image/png;base64,'),
            'pic_name' => Setting::get('pic_name', 'Nama Penanggung Jawab'),
            'whatsapp_endpoint' => Setting::get('whatsapp_endpoint', ''),
            'whatsapp_api_key' => Setting::get('whatsapp_api_key', ''),
            'whatsapp_sender' => Setting::get('whatsapp_sender', ''),
            'whatsapp_number' => Setting::get('whatsapp_number', ''),
        ];
        return view('settings.edit', compact('settings'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'company_name' => 'required|string|max:255',
            'pic_name' => 'required|string|max:255',
            'company_logo' => 'nullable|image|max:2048',
            'whatsapp_endpoint' => 'nullable|string',
            'whatsapp_api_key' => 'nullable|string',
            'whatsapp_sender' => 'nullable|string',
            'whatsapp_number' => 'nullable|string',
        ]);

        // Remove any file value from validated data before saving plain settings
        $fileValue = null;
        if (array_key_exists('company_logo', $validated)) {
            $fileValue = $validated['company_logo'];
            unset($validated['company_logo']);
        }

        foreach ($validated as $key => $value) {
            Setting::set($key, $value);
        }

        // Handle logo upload separately (use Request->hasFile to ensure proper UploadedFile)
        if ($request->hasFile('company_logo')) {
            $logo = $request->file('company_logo');
            if (! $logo || ! $logo->isValid()) {
                return redirect()->route('settings.edit')->withErrors(['company_logo' => 'File logo tidak valid atau upload gagal. Periksa pengaturan PHP (upload_tmp_dir, upload_max_filesize).']);
            }

            try {
                // Try the usual store method first
                $logoPath = null;
                try {
                    $logoPath = $logo->store('logos', 'public');
                } catch (\Throwable $e) {
                    // Catch any low-level errors (e.g. ValueError when path empty) and log
                    logger()->error('Inner store() failed for company_logo: ' . $e->getMessage(), ['exception' => $e]);
                    $logoPath = null;
                }

                // If store didn't succeed (empty path), fallback to manual move
                if (! $logoPath || ! is_string($logoPath)) {
                    $destination = storage_path('app/public/logos');
                    if (! file_exists($destination)) {
                        mkdir($destination, 0755, true);
                    }
                    $filename = time() . '_' . uniqid() . '.' . $logo->getClientOriginalExtension();
                    $moved = $logo->move($destination, $filename);
                    if ($moved) {
                        Setting::set('company_logo', 'storage/logos/' . $filename);
                    } else {
                        throw new \RuntimeException('Gagal memindahkan file logo.');
                    }
                } else {
                    Setting::set('company_logo', 'storage/' . $logoPath);
                }
            } catch (\Throwable $e) {
                logger()->error('Failed to store/move company_logo: ' . $e->getMessage(), ['exception' => $e]);
                return redirect()->route('settings.edit')->withErrors(['company_logo' => 'Gagal menyimpan logo: ' . $e->getMessage()]);
            }
        }

        return redirect()->route('settings.edit')->with('success', 'Pengaturan berhasil disimpan.');
    }
}
