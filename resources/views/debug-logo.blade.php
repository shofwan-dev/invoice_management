<!DOCTYPE html>
<html>
<head>
    <title>Debug Logo</title>
</head>
<body>
    <h1>Debug Logo Test</h1>
    
    @php
        $companyLogo = \App\Models\Setting::get('company_logo');
        echo '<p><strong>1. Logo dari DB:</strong> ' . htmlspecialchars($companyLogo ?? 'NULL') . '</p>';
        
        $logoPath = $companyLogo;
        if ($logoPath && !str_starts_with($logoPath, 'storage/')) {
            $logoPath = 'storage/' . $logoPath;
        }
        echo '<p><strong>2. Logo Path setelah normalisasi:</strong> ' . htmlspecialchars($logoPath ?? 'NULL') . '</p>';
        
        if ($logoPath) {
            $publicPath = public_path($logoPath);
            echo '<p><strong>3. Public Path (filesystem):</strong> ' . htmlspecialchars($publicPath) . '</p>';
            echo '<p><strong>4. File exists?:</strong> ' . (file_exists($publicPath) ? 'YES ✓' : 'NO ✗') . '</p>';
            
            $assetUrl = asset($logoPath);
            echo '<p><strong>5. Asset URL:</strong> ' . htmlspecialchars($assetUrl) . '</p>';
            
            // Try to access it
            $ch = curl_init($assetUrl);
            curl_setopt($ch, CURLOPT_NOBODY, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            echo '<p><strong>6. HTTP Status dari asset URL:</strong> ' . $httpCode . '</p>';
        }
    @endphp
    
    <hr>
    
    @if($companyLogo && strpos($companyLogo, 'data:') !== 0)
        <h2>Test Display</h2>
        <img src="{{ asset(str_starts_with($companyLogo, 'storage/') ? $companyLogo : 'storage/' . $companyLogo) }}" alt="Test" style="max-height: 200px;">
    @endif
</body>
</html>
