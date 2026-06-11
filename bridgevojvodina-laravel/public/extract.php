<?php
/**
 * Automated extraction script for GitHub Actions deployment.
 */

$token = 'DEPLOY_TOKEN_PLACEHOLDER'; 

$receivedToken = $_POST['token'] ?? $_GET['token'] ?? null;

if (!$receivedToken || $receivedToken !== $token) {
    header('HTTP/1.0 403 Forbidden');
    die('Unauthorized');
}

if (!class_exists('ZipArchive')) {
    header('HTTP/1.0 500 Internal Server Error');
    die('Error: ZipArchive class not found.');
}

// Smart path detection
$currentDir = __DIR__;
$parentDir = dirname($currentDir);
$zipName = 'deploy.zip';

// Look for ZIP in current dir or parent dir
if (file_exists($currentDir . '/' . $zipName)) {
    $zipPath = $currentDir . '/' . $zipName;
    $extractPath = (basename($currentDir) === 'public_html') ? $parentDir : $currentDir;
} elseif (file_exists($parentDir . '/' . $zipName)) {
    $zipPath = $parentDir . '/' . $zipName;
    $extractPath = $parentDir;
} else {
    header('HTTP/1.0 404 Not Found');
    die("Error: $zipName not found in " . $currentDir . " or " . $parentDir);
}

$zip = new ZipArchive;
if ($zip->open($zipPath) === TRUE) {
    $zip->extractTo($extractPath);
    $zip->close();
    unlink($zipPath);
    echo "Extraction successful to: " . realpath($extractPath) . "\n";
    
    // Run migrations and optimize
    $artisanPath = $extractPath . '/artisan';
    echo "Checking for Artisan at: $artisanPath\n";
    if (file_exists($artisanPath)) {
        try {
            echo "Bootstrapping Laravel...\n";
            // Bootstrap Laravel
            require $extractPath . '/vendor/autoload.php';
            $app = require_once $extractPath . '/bootstrap/app.php';
            $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
            
            echo "\n--- Running migrations ---\n";
            $exitCode = $kernel->call('migrate', ['--force' => true]);
            echo "Migration Exit Code: $exitCode\n";
            echo "Migration Output:\n" . \Illuminate\Support\Facades\Artisan::output() . "\n";

            echo "\n--- Optimizing ---\n";
            $exitCode = $kernel->call('optimize');
            echo "Optimize Exit Code: $exitCode\n";
            echo "Optimize Output:\n" . \Illuminate\Support\Facades\Artisan::output() . "\n";

            if ($exitCode === 0) {
                echo "\nDeployment completed successfully.\n";
            } else {
                echo "\nDeployment failed during Artisan tasks.\n";
            }
        } catch (\Exception $e) {
            echo "\nError during Laravel tasks: " . $e->getMessage() . "\n";
            echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
        }
    } else {
        echo "Artisan not found at $artisanPath\n";
    }

} else {
    header('HTTP/1.0 500 Internal Server Error');
    echo "Failed to open ZIP file.";
}
