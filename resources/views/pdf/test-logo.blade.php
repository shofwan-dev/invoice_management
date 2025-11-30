<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body{font-family: Arial, sans-serif;}
        .test-section{margin: 20px 0; padding: 20px; border: 1px solid #ccc;}
    </style>
</head>
<body>
    <h1>Test Logo Rendering</h1>
    
    <div class="test-section">
        <h2>Debug Info:</h2>
        @php
            $companyLogo = \App\Models\Setting::get('company_logo');
            echo '<p><strong>Logo dari DB:</strong> ' . ($companyLogo ?? 'NOT SET') . '</p>';
            
            // Construct path directly without public_path()
            $basePath = dirname(dirname(dirname(__DIR__)));
            echo '<p><strong>Base path:</strong> ' . $basePath . '</p>';
            
            $storageRelativePath = $companyLogo;
            if ($companyLogo && !str_starts_with($storageRelativePath, 'storage/')) {
                $storageRelativePath = 'storage/' . $storageRelativePath;
            }
            echo '<p><strong>Storage relative path:</strong> ' . ($storageRelativePath ?? 'NULL') . '</p>';
            
            // Construct absolute path to public directory
            $publicPath = $basePath . DIRECTORY_SEPARATOR . 'public';
            $absolutePath = $publicPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageRelativePath);
            
            echo '<p><strong>Absolute path:</strong> ' . $absolutePath . '</p>';
            echo '<p><strong>File exists:</strong> ' . (file_exists($absolutePath) ? 'YES' : 'NO') . '</p>';
            
            // Create file URI with forward slashes
            $fileUri = 'file:///' . str_replace('\\', '/', $absolutePath);
            echo '<p><strong>File URI:</strong> ' . $fileUri . '</p>';
        @endphp
    </div>
    
    <div class="test-section">
        <h2>Test Image Rendering:</h2>
        @php
            $companyLogo = \App\Models\Setting::get('company_logo');
            if ($companyLogo) {
                $storageRelativePath = str_starts_with($companyLogo, 'storage/') ? $companyLogo : 'storage/' . $companyLogo;
                
                // Construct path directly
                $basePath = dirname(dirname(dirname(__DIR__)));
                $publicPath = $basePath . DIRECTORY_SEPARATOR . 'public';
                $absolutePath = $publicPath . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $storageRelativePath);
                
                if (file_exists($absolutePath)) {
                    $fileUri = 'file:///' . str_replace('\\', '/', $absolutePath);
                } else {
                    $fileUri = null;
                }
            } else {
                $fileUri = null;
            }
        @endphp
        
        @if($fileUri)
            <p>Image using file:// URI:</p>
            <img src="{{ $fileUri }}" alt="Test Logo" style="max-height: 200px; border: 1px solid red;" />
        @else
            <p>Logo not found</p>
        @endif
    </div>
</body>
</html>
