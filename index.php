<?php
/**
 * Localhost Studio - Laragon Development Dashboard
 * Responsive Neo-Brutalism Developer Workspace
 * Supporting: Project discovery, File creation & Code viewer,
 * Interactive D3.js Workspace Graph, SweetAlert2 / Swal2,
 * Framework Scaffolding (Laravel, Vue, React, CodeIgniter 4, etc.)
 */

// 1. Session & Configuration Setup
session_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$root = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
$root = $root ?: __DIR__;

// Helper: Find PHP CLI & Composer executable
function getPhpBinary(): string {
    if (defined('PHP_BINARY') && file_exists(PHP_BINARY) && !str_ends_with(strtolower(PHP_BINARY), 'httpd.exe')) {
        return PHP_BINARY;
    }
    // Scan Laragon php directory
    $phpDirs = glob('C:/laragon/bin/php/php*');
    if ($phpDirs) {
        rsort($phpDirs);
        foreach ($phpDirs as $pDir) {
            $candidate = $pDir . '/php.exe';
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
    }
    return 'php';
}

function getComposerCommand(): string {
    $php = escapeshellarg(getPhpBinary());
    $phar = 'C:/laragon/bin/composer/composer.phar';
    if (file_exists($phar)) {
        return $php . ' ' . escapeshellarg($phar);
    }
    return 'composer';
}

function getNodeCommand(): string {
    $candidates = [
        'C:/Program Files/nodejs/node.exe',
        'C:/laragon/bin/nodejs/node.exe'
    ];
    foreach ($candidates as $c) {
        if (file_exists($c)) return escapeshellarg($c);
    }
    return 'node';
}

function getNpmCommand(): string {
    $candidates = [
        'C:/Program Files/nodejs/npm.cmd',
        'C:/laragon/bin/nodejs/npm.cmd'
    ];
    foreach ($candidates as $c) {
        if (file_exists($c)) return escapeshellarg($c);
    }
    return 'npm.cmd';
}

function h(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

// 2. REST API Actions Handler
if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $api = $_GET['api'];

    // Resolve path within workspace root
    function resolveSafePath(string $subPath, string $root): ?string {
        $sub = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $subPath), DIRECTORY_SEPARATOR);
        $target = realpath($root . ($sub !== '' ? DIRECTORY_SEPARATOR . $sub : ''));
        if (!$target && !file_exists($root . ($sub !== '' ? DIRECTORY_SEPARATOR . $sub : ''))) {
            // For creation of non-existing files/folders
            $parent = realpath(dirname($root . DIRECTORY_SEPARATOR . $sub));
            if ($parent && str_starts_with($parent, $root)) {
                return $root . DIRECTORY_SEPARATOR . $sub;
            }
            return null;
        }
        if ($target && (str_starts_with($target, $root) || $target === $root)) {
            return $target;
        }
        return null;
    }

    if ($api === 'create_item') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $type = $input['type'] ?? 'file'; // file or folder
        $targetDir = trim($input['dir'] ?? '', '/');
        $name = trim($input['name'] ?? '');
        $content = $input['content'] ?? '';

        if (empty($name)) {
            echo json_encode(['success' => false, 'message' => 'Nama tidak boleh kosong.']);
            exit;
        }

        // Prevent traversal in name
        if (str_contains($name, '..') || str_contains($name, '/') || str_contains($name, '\\')) {
            echo json_encode(['success' => false, 'message' => 'Nama file atau folder tidak valid.']);
            exit;
        }

        $baseDir = resolveSafePath($targetDir, $root);
        if (!$baseDir || !is_dir($baseDir)) {
            echo json_encode(['success' => false, 'message' => 'Direktori tujuan tidak valid.']);
            exit;
        }

        $fullPath = $baseDir . DIRECTORY_SEPARATOR . $name;
        if (file_exists($fullPath)) {
            echo json_encode(['success' => false, 'message' => 'File atau folder dengan nama ini sudah ada!']);
            exit;
        }

        if ($type === 'folder') {
            if (@mkdir($fullPath, 0777, true)) {
                echo json_encode(['success' => true, 'message' => "Folder '{$name}' berhasil dibuat!"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal membuat folder. Periksa permission.']);
            }
        } else {
            if (@file_put_contents($fullPath, $content) !== false) {
                echo json_encode(['success' => true, 'message' => "File '{$name}' berhasil dibuat!"]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal membuat file. Periksa permission.']);
            }
        }
        exit;
    }

    if ($api === 'save_file') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $filePath = trim($input['file'] ?? '', '/');
        $content = $input['content'] ?? '';

        $target = resolveSafePath($filePath, $root);
        if (!$target || is_dir($target)) {
            echo json_encode(['success' => false, 'message' => 'File tidak ditemukan atau path tidak aman.']);
            exit;
        }

        // Protected files
        $baseName = basename($target);
        if (in_array(strtolower($baseName), ['.env', '.git', 'id_rsa', 'composer.lock', 'package-lock.json'])) {
            echo json_encode(['success' => false, 'message' => 'File konfigurasi ini dilindungi dari pengeditan.']);
            exit;
        }

        if (@file_put_contents($target, $content) !== false) {
            echo json_encode(['success' => true, 'message' => 'File berhasil disimpan!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file.']);
        }
        exit;
    }

    if ($api === 'read_source') {
        $fileParam = trim($_GET['file'] ?? '', '/');
        $target = resolveSafePath($fileParam, $root);

        if (!$target || !is_file($target)) {
            echo json_encode(['success' => false, 'message' => 'File tidak ditemukan.']);
            exit;
        }

        $baseName = basename($target);
        $denylist = ['.env', 'id_rsa', 'id_rsa.pub', 'credentials', 'master.key'];
        if (in_array(strtolower($baseName), $denylist) || str_contains($target, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
            echo json_encode(['success' => false, 'message' => 'Akses ke file rahasia/sensitif ini ditolak.']);
            exit;
        }

        $size = filesize($target);
        if ($size > 1.5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'File melebihi batas ukuran (maksimal 1.5 MB).']);
            exit;
        }

        $ext = strtolower(pathinfo($target, PATHINFO_EXTENSION));
        $content = file_get_contents($target);
        echo json_encode([
            'success' => true,
            'filename' => $baseName,
            'path' => $fileParam,
            'extension' => $ext,
            'size' => $size,
            'content' => $content
        ]);
        exit;
    }

    if ($api === 'delete_item') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $targetPath = trim($input['path'] ?? '', '/');

        $target = resolveSafePath($targetPath, $root);
        if (!$target || $target === $root) {
            echo json_encode(['success' => false, 'message' => 'Path tidak valid atau tidak diizinkan.']);
            exit;
        }

        if (is_file($target)) {
            if (@unlink($target)) {
                echo json_encode(['success' => true, 'message' => 'File berhasil dihapus.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus file.']);
            }
        } else if (is_dir($target)) {
            // Delete folder recursively
            $it = new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach($files as $file) {
                if ($file->isDir()){
                    @rmdir($file->getRealPath());
                } else {
                    @unlink($file->getRealPath());
                }
            }
            if (@rmdir($target)) {
                echo json_encode(['success' => true, 'message' => 'Folder berhasil dihapus.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Gagal menghapus folder.']);
            }
        }
        exit;
    }

    if ($api === 'graph_data') {
        // Build D3.js tree/network data of projects & structure
        $nodes = [
            ['id' => 'root', 'name' => 'Workspace Root', 'type' => 'root', 'group' => 1]
        ];
        $links = [];

        $scan = scandir($root) ?: [];
        foreach ($scan as $item) {
            if ($item === '.' || $item === '..' || in_array($item, ['.git', 'node_modules', 'vendor'])) continue;
            $full = $root . DIRECTORY_SEPARATOR . $item;
            $isDir = is_dir($full);

            $nodeId = $item;
            $type = $isDir ? 'dir' : 'file';
            $group = $isDir ? 2 : 3;

            if ($isDir) {
                if (file_exists($full . '/artisan')) { $type = 'laravel'; $group = 4; }
                elseif (file_exists($full . '/vite.config.js') || file_exists($full . '/vite.config.ts')) { $type = 'vite'; $group = 5; }
                elseif (file_exists($full . '/spark')) { $type = 'codeigniter'; $group = 6; }
                elseif (file_exists($full . '/composer.json')) { $type = 'php-pkg'; $group = 7; }
                elseif (file_exists($full . '/package.json')) { $type = 'node-pkg'; $group = 8; }
            }

            $nodes[] = ['id' => $nodeId, 'name' => $item, 'type' => $type, 'group' => $group];
            $links[] = ['source' => 'root', 'target' => $nodeId];

            // If it's a directory like "code" or major folder, show 1 level deep children
            if ($isDir) {
                $subScan = @scandir($full) ?: [];
                $count = 0;
                foreach ($subScan as $subItem) {
                    if ($subItem === '.' || $subItem === '..' || in_array($subItem, ['.git', 'node_modules', 'vendor'])) continue;
                    if ($count > 12) break; // Limit for clean visualization
                    $subId = $item . '/' . $subItem;
                    $subFull = $full . DIRECTORY_SEPARATOR . $subItem;
                    $subIsDir = is_dir($subFull);
                    $subType = $subIsDir ? 'dir' : 'file';
                    $subGroup = $subIsDir ? 2 : 3;
                    if ($subIsDir && file_exists($subFull . '/artisan')) { $subType = 'laravel'; $subGroup = 4; }

                    $nodes[] = ['id' => $subId, 'name' => $subItem, 'type' => $subType, 'group' => $subGroup];
                    $links[] = ['source' => $nodeId, 'target' => $subId];
                    $count++;
                }
            }
        }

        echo json_encode(['nodes' => $nodes, 'links' => $links]);
        exit;
    }

    if ($api === 'install_project') {
        // Scaffolding Project
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $framework = strtolower(trim($input['framework'] ?? ''));
        $projectName = trim($input['name'] ?? '');
        $targetDir = trim($input['dir'] ?? '', '/');

        // Validation
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $projectName)) {
            echo json_encode(['success' => false, 'message' => 'Nama project hanya boleh huruf, angka, dash (-), dan underscore (_).']);
            exit;
        }

        $baseDir = resolveSafePath($targetDir, $root);
        if (!$baseDir || !is_dir($baseDir)) {
            $baseDir = $root;
        }

        $dest = $baseDir . DIRECTORY_SEPARATOR . $projectName;
        if (file_exists($dest)) {
            echo json_encode(['success' => false, 'message' => "Folder target '{$projectName}' sudah ada!"]);
            exit;
        }

        $phpBin = getPhpBinary();
        $composerCmd = getComposerCommand();
        $nodeCmd = getNodeCommand();
        $npmCmd = getNpmCommand();

        $output = [];
        $status = 0;
        $commandRun = "";

        // Set max execution time for scaffolding
        set_time_limit(300);

        if ($framework === 'laravel') {
            // composer create-project laravel/laravel
            $cmd = "cd " . escapeshellarg($baseDir) . " && " . $composerCmd . " create-project laravel/laravel " . escapeshellarg($projectName) . " --prefer-dist --no-interaction 2>&1";
            $commandRun = "composer create-project laravel/laravel {$projectName}";
            exec($cmd, $output, $status);
        } elseif ($framework === 'codeigniter') {
            // composer create-project codeigniter4/appstarter
            $cmd = "cd " . escapeshellarg($baseDir) . " && " . $composerCmd . " create-project codeigniter4/appstarter " . escapeshellarg($projectName) . " --no-interaction 2>&1";
            $commandRun = "composer create-project codeigniter4/appstarter {$projectName}";
            exec($cmd, $output, $status);
        } elseif ($framework === 'react') {
            // npm create vite@latest <project> -- --template react
            $cmd = "cd " . escapeshellarg($baseDir) . " && " . $npmCmd . " create vite@latest " . escapeshellarg($projectName) . " -- --template react -y 2>&1";
            $commandRun = "npm create vite {$projectName} --template react";
            exec($cmd, $output, $status);
        } elseif ($framework === 'vue') {
            // npm create vite@latest <project> -- --template vue
            $cmd = "cd " . escapeshellarg($baseDir) . " && " . $npmCmd . " create vite@latest " . escapeshellarg($projectName) . " -- --template vue -y 2>&1";
            $commandRun = "npm create vite {$projectName} --template vue";
            exec($cmd, $output, $status);
        } elseif ($framework === 'html_starter') {
            // Custom lightweight modern HTML5 + CSS + JS starter
            @mkdir($dest, 0777, true);
            $indexHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$projectName}</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div style="font-family:system-ui,sans-serif;padding:40px;text-align:center;">
        <h1>🚀 {$projectName}</h1>
        <p>Starter template created successfully in Laragon Localhost.</p>
        <button onclick="alert('Hello from {$projectName}!')" style="padding:10px 20px;cursor:pointer;font-weight:bold;">Click Me</button>
    </div>
    <script src="app.js"></script>
</body>
</html>
HTML;
            $styleCss = "body { margin:0; background: #f9f9fb; color: #222; }";
            $appJs = "console.log('Project {$projectName} initialized.');";
            @file_put_contents($dest . '/index.html', $indexHtml);
            @file_put_contents($dest . '/style.css', $styleCss);
            @file_put_contents($dest . '/app.js', $appJs);
            $status = 0;
            $output[] = "Template HTML5 Starter berhasil dibuat di {$projectName}.";
        } elseif ($framework === 'php_starter') {
            @mkdir($dest, 0777, true);
            $indexPhp = <<<PHP
<?php
// {$projectName} Entry Point
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= "{$projectName}" ?></title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f4f6f9; padding: 40px; text-align: center; }
        .card { background: white; max-width: 500px; margin: auto; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.06); }
    </style>
</head>
<body>
    <div class="card">
        <h2>🐘 <?= "{$projectName}" ?></h2>
        <p>PHP Application running on PHP <?= PHP_VERSION ?></p>
        <p><small>Created via Laragon Localhost Studio</small></p>
    </div>
</body>
</html>
PHP;
            @file_put_contents($dest . '/index.php', $indexPhp);
            $status = 0;
            $output[] = "PHP Web Project berhasil dibuat di {$projectName}.";
        } else {
            echo json_encode(['success' => false, 'message' => 'Preset framework tidak dikenali.']);
            exit;
        }

        if ($status === 0 || file_exists($dest)) {
            echo json_encode([
                'success' => true,
                'message' => "Project '{$projectName}' berhasil di-scaffold!",
                'command' => $commandRun,
                'output' => implode("\n", array_slice($output, -15))
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => "Proses installasi menghasilkan error code: {$status}",
                'command' => $cmd,
                'output' => implode("\n", array_slice($output, -20))
            ]);
        }
        exit;
    }

    if ($api === 'check_runtimes') {
        // Detect system runtimes
        $runtimes = [];

        // PHP
        $runtimes[] = [
            'name' => 'PHP Runtime',
            'version' => PHP_VERSION,
            'detected' => true,
            'binary' => getPhpBinary(),
            'icon' => '🐘'
        ];

        // Composer
        $compOut = [];
        $compStatus = 1;
        $compCmd = getComposerCommand() . " --version 2>&1";
        exec($compCmd, $compOut, $compStatus);
        $compVer = 'Not found';
        if ($compStatus === 0 && !empty($compOut[0])) {
            preg_match('/Composer version ([\d\.]+)/i', $compOut[0], $m);
            $compVer = $m[1] ?? trim($compOut[0]);
        }
        $runtimes[] = [
            'name' => 'Composer',
            'version' => $compVer,
            'detected' => $compStatus === 0,
            'binary' => 'composer.phar / cli',
            'icon' => '📦'
        ];

        // Node.js
        $nodeOut = [];
        $nodeStatus = 1;
        exec(getNodeCommand() . " -v 2>&1", $nodeOut, $nodeStatus);
        $nodeVer = ($nodeStatus === 0 && !empty($nodeOut[0])) ? trim($nodeOut[0]) : 'Not found';
        $runtimes[] = [
            'name' => 'Node.js',
            'version' => $nodeVer,
            'detected' => $nodeStatus === 0,
            'binary' => getNodeCommand(),
            'icon' => '🟢'
        ];

        // npm
        $npmOut = [];
        $npmStatus = 1;
        exec(getNpmCommand() . " -v 2>&1", $npmOut, $npmStatus);
        $npmVer = ($npmStatus === 0 && !empty($npmOut[0])) ? trim($npmOut[0]) : 'Not found';
        $runtimes[] = [
            'name' => 'npm Package Manager',
            'version' => $npmVer,
            'detected' => $npmStatus === 0,
            'binary' => getNpmCommand(),
            'icon' => '⚡'
        ];

        // Git
        $gitOut = [];
        $gitStatus = 1;
        exec("git --version 2>&1", $gitOut, $gitStatus);
        $gitVer = ($gitStatus === 0 && !empty($gitOut[0])) ? trim($gitOut[0]) : 'Not found';
        $runtimes[] = [
            'name' => 'Git VCS',
            'version' => $gitVer,
            'detected' => $gitStatus === 0,
            'binary' => 'git',
            'icon' => '🐙'
        ];

        // MySQL
        $mysqlVer = 'MySQL / MariaDB Service';
        $runtimes[] = [
            'name' => 'Database Engine',
            'version' => extension_loaded('mysqli') || extension_loaded('pdo_mysql') ? 'MySQL Extension Active' : 'Extension Inactive',
            'detected' => extension_loaded('mysqli') || extension_loaded('pdo_mysql'),
            'binary' => 'Laragon MySQL',
            'icon' => '🐬'
        ];

        echo json_encode(['success' => true, 'runtimes' => $runtimes]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'API route not recognized.']);
    exit;
}

// 3. Navigation & Directory Scanning Logic
$relative = trim(str_replace('\\', '/', $_GET['dir'] ?? ''), '/');
$current = realpath($root . ($relative !== '' ? DIRECTORY_SEPARATOR . $relative : ''));

if (
    $current === false ||
    !is_dir($current) ||
    ($current !== $root && !str_starts_with($current, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))
) {
    $current = $root;
    $relative = '';
}

function detectProjectType(string $dir): array {
    $types = [];
    if (file_exists($dir . DIRECTORY_SEPARATOR . 'artisan')) {
        $types[] = ['name' => 'Laravel', 'color' => '#FF2D20', 'bg' => '#ffe5e3', 'entry' => '/public'];
    }
    if (file_exists($dir . DIRECTORY_SEPARATOR . 'spark')) {
        $types[] = ['name' => 'CodeIgniter 4', 'color' => '#EF4223', 'bg' => '#ffede8', 'entry' => '/public'];
    }
    if (file_exists($dir . DIRECTORY_SEPARATOR . 'vite.config.js') || file_exists($dir . DIRECTORY_SEPARATOR . 'vite.config.ts')) {
        $types[] = ['name' => 'Vite App', 'color' => '#646CFF', 'bg' => '#eaebff', 'entry' => ''];
    }
    if (file_exists($dir . DIRECTORY_SEPARATOR . 'package.json')) {
        $pkg = @json_decode(@file_get_contents($dir . DIRECTORY_SEPARATOR . 'package.json'), true);
        if ($pkg && isset($pkg['dependencies'])) {
            if (isset($pkg['dependencies']['vue'])) $types[] = ['name' => 'Vue.js', 'color' => '#42b883', 'bg' => '#e2f7ed', 'entry' => ''];
            if (isset($pkg['dependencies']['react'])) $types[] = ['name' => 'React', 'color' => '#087ea4', 'bg' => '#def3fa', 'entry' => ''];
        }
    }
    if (file_exists($dir . DIRECTORY_SEPARATOR . 'composer.json') && empty($types)) {
        $types[] = ['name' => 'PHP Project', 'color' => '#4F5D95', 'bg' => '#e9ebf5', 'entry' => ''];
    }
    if (file_exists($dir . DIRECTORY_SEPARATOR . 'index.php') && empty($types)) {
        $types[] = ['name' => 'PHP Web', 'color' => '#4F5D95', 'bg' => '#e9ebf5', 'entry' => ''];
    }
    if (file_exists($dir . DIRECTORY_SEPARATOR . 'index.html') && empty($types)) {
        $types[] = ['name' => 'Static HTML', 'color' => '#E34F26', 'bg' => '#feeae5', 'entry' => ''];
    }

    return $types ?: [['name' => 'Folder', 'color' => '#555', 'bg' => '#eee', 'entry' => '']];
}

$items = [];
$projectCount = 0;
$folderCount = 0;
$fileCount = 0;

foreach (scandir($current) ?: [] as $name) {
    if ($name === '.' || $name === '..') continue;
    if (in_array($name, ['.git', '.env', 'node_modules', 'vendor', '.idea', '.vscode'], true)) continue;

    $absolute = $current . DIRECTORY_SEPARATOR . $name;
    if (is_link($absolute)) continue;

    $isDir = is_dir($absolute);
    $projTypes = $isDir ? detectProjectType($absolute) : [];
    $isProject = $isDir && ($projTypes[0]['name'] !== 'Folder');

    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    $items[] = [
        'name' => $name,
        'dir' => $isDir,
        'project' => $isProject,
        'types' => $projTypes,
        'size' => $isDir ? null : filesize($absolute),
        'modified' => filemtime($absolute),
        'ext' => $ext
    ];

    $isDir ? $folderCount++ : $fileCount++;
    if ($isProject) $projectCount++;
}

usort($items, function ($a, $b) {
    if ($a['dir'] !== $b['dir']) return $a['dir'] ? -1 : 1;
    return strcasecmp($a['name'], $b['name']);
});

$server = $_SERVER['SERVER_SOFTWARE'] ?? 'Apache/Laragon';
$phpVersion = PHP_VERSION;
$parent = dirname($relative);
$parent = ($parent === '.' || $parent === '/' || $parent === '\\') ? '' : $parent;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Localhost Studio — Laragon Dashboard</title>
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Space+Grotesk:wght@500;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- SweetAlert2 (swal2) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- D3.js -->
    <script src="https://cdn.jsdelivr.net/npm/d3@7"></script>

    <!-- Prism.js for Syntax Highlighting -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/themes/prism-tomorrow.min.css">
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/prism.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-markup.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-javascript.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-css.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-php.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-json.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-sql.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/prismjs@1.29.0/components/prism-bash.min.js"></script>

    <style>
        /* Neo-Brutalism Design Tokens (as specified in PRD.md) */
        :root {
            --bg: #F5F4EF;
            --surface: #FFFFFF;
            --primary: #C8F169;
            --primary-hover: #b6e254;
            --secondary: #B9D7FF;
            --accent: #FF9B71;
            --accent-pink: #FFB3D9;
            --text: #171717;
            --muted: #686868;
            --border: #171717;
            --border-width: 2.5px;
            --shadow-hard: 4px 4px 0px #171717;
            --shadow-hard-hover: 6px 6px 0px #171717;
            --shadow-hard-active: 1px 1px 0px #171717;
            --radius-md: 10px;
            --radius-sm: 6px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        h1, h2, h3, h4, .brand-text, .mono-heading {
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        code, pre, .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }

        a { color: inherit; text-decoration: none; }
        button, input, select, textarea { font-family: inherit; }

        /* Neo-Brutalist Layout Structure */
        .app-wrapper {
            display: flex;
            width: 100vw;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--surface);
            border-right: var(--border-width) solid var(--border);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            position: sticky;
            top: 0;
            height: 100vh;
            z-index: 100;
            transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .sidebar-brand {
            padding: 24px;
            border-bottom: var(--border-width) solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
            background: var(--surface);
        }

        .brand-logo-icon {
            width: 44px;
            height: 44px;
            background: var(--primary);
            border: var(--border-width) solid var(--border);
            box-shadow: 2px 2px 0px var(--border);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 900;
            color: var(--text);
        }

        .brand-titles h1 {
            font-size: 19px;
            line-height: 1.1;
        }

        .brand-badge {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: var(--secondary);
            border: 1.5px solid var(--border);
            padding: 1px 6px;
            border-radius: 4px;
            display: inline-block;
            margin-top: 3px;
        }

        .sidebar-nav {
            padding: 20px 16px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-grow: 1;
            overflow-y: auto;
        }

        .nav-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.08em;
            padding: 8px 12px 2px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 11px 14px;
            font-weight: 600;
            font-size: 14px;
            border-radius: var(--radius-sm);
            border: 2px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .nav-link i { font-size: 17px; }

        .nav-link:hover {
            background: #f0eee6;
            border-color: var(--border);
        }

        .nav-link.active {
            background: var(--primary);
            border: var(--border-width) solid var(--border);
            box-shadow: var(--shadow-hard);
            transform: translate(-1px, -1px);
        }

        .sidebar-footer {
            padding: 16px;
            border-top: var(--border-width) solid var(--border);
            background: var(--bg);
        }

        .server-quick-status {
            background: var(--surface);
            border: 2px solid var(--border);
            padding: 10px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .live-pulse {
            width: 10px;
            height: 10px;
            background: #10b981;
            border: 1.5px solid var(--border);
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
            animation: pulse 1.8s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* Main Content Container */
        .main-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow-y: auto;
            background: var(--bg);
        }

        /* Top Bar */
        .topbar {
            background: var(--surface);
            border-bottom: var(--border-width) solid var(--border);
            padding: 14px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 90;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .mobile-toggle {
            display: none;
            background: var(--surface);
            border: var(--border-width) solid var(--border);
            box-shadow: 2px 2px 0 var(--border);
            border-radius: var(--radius-sm);
            padding: 6px 10px;
            cursor: pointer;
            font-size: 18px;
        }

        .breadcrumb-neo {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 6px;
            font-weight: 600;
            font-size: 13px;
        }

        .breadcrumb-neo a {
            padding: 3px 8px;
            background: var(--bg);
            border: 1.5px solid var(--border);
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .breadcrumb-neo a:hover {
            background: var(--secondary);
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .clock-widget {
            background: var(--surface);
            border: var(--border-width) solid var(--border);
            box-shadow: 3px 3px 0 var(--border);
            padding: 6px 14px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
        }

        /* Buttons & Neo Controls */
        .neo-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 9px 16px;
            font-weight: 700;
            font-size: 13px;
            border: var(--border-width) solid var(--border);
            border-radius: var(--radius-sm);
            background: var(--surface);
            color: var(--text);
            box-shadow: var(--shadow-hard);
            cursor: pointer;
            transition: all 0.12s ease;
            user-select: none;
            white-space: nowrap;
        }

        .neo-btn:hover {
            transform: translate(-2px, -2px);
            box-shadow: var(--shadow-hard-hover);
        }

        .neo-btn:active {
            transform: translate(2px, 2px);
            box-shadow: var(--shadow-hard-active);
        }

        .neo-btn-primary {
            background: var(--primary);
        }
        .neo-btn-primary:hover {
            background: var(--primary-hover);
        }

        .neo-btn-secondary {
            background: var(--secondary);
        }

        .neo-btn-accent {
            background: var(--accent);
            color: #fff;
        }

        .neo-btn-pink {
            background: var(--accent-pink);
        }

        .neo-btn-sm {
            padding: 6px 10px;
            font-size: 12px;
            box-shadow: 2px 2px 0 var(--border);
        }
        .neo-btn-sm:hover {
            box-shadow: 4px 4px 0 var(--border);
        }

        /* Content Body */
        .content-body {
            padding: 28px;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        /* Hero Banner */
        .hero-banner {
            background: var(--surface);
            border: var(--border-width) solid var(--border);
            box-shadow: var(--shadow-hard);
            border-radius: var(--radius-md);
            padding: 28px 32px;
            margin-bottom: 24px;
            position: relative;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .hero-banner::after {
            content: "DEV";
            position: absolute;
            right: 20px;
            bottom: -15px;
            font-size: 120px;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 900;
            color: rgba(0,0,0,0.03);
            pointer-events: none;
            line-height: 1;
        }

        .hero-banner h2 {
            font-size: 26px;
            margin-bottom: 8px;
        }

        .hero-banner p {
            color: var(--muted);
            max-width: 620px;
            font-size: 14px;
        }

        .hero-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            z-index: 2;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--surface);
            border: var(--border-width) solid var(--border);
            box-shadow: var(--shadow-hard);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            position: relative;
            transition: transform 0.15s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .stat-tag {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
        }

        .stat-icon-badge {
            width: 32px;
            height: 32px;
            border: 2px solid var(--border);
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
        }

        .stat-number {
            font-size: 30px;
            font-weight: 800;
            font-family: 'Space Grotesk', sans-serif;
            line-height: 1.1;
        }

        /* D3.js Visualization Panel */
        .graph-panel {
            background: var(--surface);
            border: var(--border-width) solid var(--border);
            box-shadow: var(--shadow-hard);
            border-radius: var(--radius-md);
            margin-bottom: 28px;
            overflow: hidden;
        }

        .graph-header {
            padding: 16px 20px;
            border-bottom: var(--border-width) solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #faf9f5;
        }

        .graph-header h3 {
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        #d3-container {
            width: 100%;
            height: 300px;
            background: #fbfbf9;
            position: relative;
            cursor: grab;
        }
        #d3-container:active { cursor: grabbing; }

        .d3-legend {
            padding: 8px 16px;
            border-top: 1.5px solid var(--border);
            background: var(--surface);
            font-size: 11px;
            font-weight: 600;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            border: 1.5px solid var(--border);
        }

        /* Projects Section Header & Controls */
        .section-control {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 20px;
        }

        .search-box-neo {
            display: flex;
            align-items: center;
            background: var(--surface);
            border: var(--border-width) solid var(--border);
            box-shadow: 3px 3px 0 var(--border);
            border-radius: var(--radius-sm);
            padding: 4px 12px;
            width: 320px;
            max-width: 100%;
        }

        .search-box-neo i {
            color: var(--muted);
            margin-right: 8px;
            font-size: 15px;
        }

        .search-box-neo input {
            border: none;
            background: transparent;
            outline: none;
            font-size: 13px;
            font-weight: 500;
            width: 100%;
            padding: 6px 0;
        }

        .view-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Projects Grid / List */
        .projects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .project-card {
            background: var(--surface);
            border: var(--border-width) solid var(--border);
            box-shadow: var(--shadow-hard);
            border-radius: var(--radius-md);
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.15s ease;
        }

        .project-card:hover {
            transform: translate(-2px, -2px);
            box-shadow: var(--shadow-hard-hover);
        }

        .card-top {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 14px;
        }

        .card-icon {
            width: 46px;
            height: 46px;
            border: var(--border-width) solid var(--border);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
            box-shadow: 2px 2px 0 var(--border);
        }

        .card-info {
            flex-grow: 1;
            min-width: 0;
        }

        .card-title {
            font-size: 17px;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 3px;
        }

        .card-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 4px;
        }

        .neo-tag {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 7px;
            border: 1.5px solid var(--border);
            border-radius: 4px;
            box-shadow: 1px 1px 0 var(--border);
        }

        .card-meta {
            font-size: 11px;
            color: var(--muted);
            border-top: 1.5px dashed #ccc;
            padding-top: 10px;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
        }

        .card-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* List Mode Style */
        .projects-grid.list-view {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .projects-grid.list-view .project-card {
            flex-direction: row;
            align-items: center;
            padding: 12px 18px;
        }

        .projects-grid.list-view .card-top {
            margin-bottom: 0;
            align-items: center;
            flex: 1;
        }

        .projects-grid.list-view .card-meta {
            border-top: none;
            padding-top: 0;
            margin-bottom: 0;
            margin-right: 20px;
        }

        /* Code Viewer Modal */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.65);
            z-index: 999;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(2px);
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: var(--surface);
            border: var(--border-width) solid var(--border);
            box-shadow: 8px 8px 0 var(--border);
            border-radius: var(--radius-md);
            width: 900px;
            max-width: 100%;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            animation: modalPop 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            padding: 16px 20px;
            border-bottom: var(--border-width) solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #faf8f0;
        }

        .modal-body {
            padding: 18px 20px;
            overflow-y: auto;
            flex-grow: 1;
        }

        .code-pre-box {
            background: #1d1f21 !important;
            border: var(--border-width) solid var(--border) !important;
            border-radius: var(--radius-sm);
            padding: 16px !important;
            max-height: 520px;
            overflow: auto;
            font-size: 13px !important;
        }

        .code-textarea {
            width: 100%;
            height: 380px;
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            padding: 14px;
            border: var(--border-width) solid var(--border);
            border-radius: var(--radius-sm);
            resize: vertical;
            outline: none;
        }

        /* Server / Runtime info badges */
        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }

        .info-card {
            background: var(--surface);
            border: var(--border-width) solid var(--border);
            box-shadow: 3px 3px 0 var(--border);
            border-radius: var(--radius-sm);
            padding: 14px 18px;
        }

        .info-card-title {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            margin-bottom: 6px;
        }

        .info-card-val {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            word-break: break-all;
        }

        /* Empty State */
        .empty-state {
            grid-column: 1 / -1;
            background: var(--surface);
            border: var(--border-width) dashed var(--border);
            padding: 50px 20px;
            text-align: center;
            border-radius: var(--radius-md);
        }

        /* Responsive Breakpoints */
        @media (max-width: 900px) {
            .sidebar {
                position: fixed;
                transform: translateX(-100%);
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .mobile-toggle {
                display: flex;
            }
            .topbar {
                padding: 12px 18px;
            }
            .content-body {
                padding: 18px;
            }
        }
    </style>
</head>
<body>

<div class="app-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-logo-icon">
                <i class="bi bi-terminal-split"></i>
            </div>
            <div class="brand-titles">
                <h1>Localhost Studio</h1>
                <span class="brand-badge">Laragon Core</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Main Workspace</div>
            <a href="index.php" class="nav-link <?= empty($relative) ? 'active' : '' ?>">
                <i class="bi bi-grid-fill"></i>
                <span>Dashboard Overview</span>
            </a>
            <a href="index.php?dir=code" class="nav-link <?= ($relative === 'code') ? 'active' : '' ?>">
                <i class="bi bi-code-slash"></i>
                <span>Direct /code Folder</span>
            </a>
            <a href="javascript:void(0)" onclick="openNewProjectModal()" class="nav-link">
                <i class="bi bi-rocket-takeoff-fill"></i>
                <span>Install Framework</span>
            </a>
            <a href="javascript:void(0)" onclick="openCreateItemModal('file')" class="nav-link">
                <i class="bi bi-file-earmark-plus-fill"></i>
                <span>Create New File</span>
            </a>
            <a href="javascript:void(0)" onclick="openCreateItemModal('folder')" class="nav-link">
                <i class="bi bi-folder-plus"></i>
                <span>Create Folder</span>
            </a>

            <div class="nav-label" style="margin-top:10px;">Environment</div>
            <a href="javascript:void(0)" onclick="checkRuntimesModal()" class="nav-link">
                <i class="bi bi-cpu-fill"></i>
                <span>Runtime & Tools</span>
            </a>
            <a href="#d3-container" class="nav-link">
                <i class="bi bi-diagram-3-fill"></i>
                <span>Workspace Graph</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="server-quick-status">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="live-pulse"></span>
                    <span style="font-weight:700;">Laragon Online</span>
                </div>
                <span style="font-size:11px;font-family:monospace;"><?= h($phpVersion) ?></span>
            </div>
        </div>
    </aside>

    <!-- Main Workspace -->
    <div class="main-wrapper">
        <!-- Top Bar -->
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-toggle" onclick="toggleSidebar()">
                    <i class="bi bi-list"></i>
                </button>
                <div class="breadcrumb-neo">
                    <a href="index.php"><i class="bi bi-house-door-fill"></i> root</a>
                    <?php
                    $crumbAcc = '';
                    foreach (array_filter(explode('/', $relative), 'strlen') as $part):
                        $crumbAcc = $crumbAcc === '' ? $part : $crumbAcc . '/' . $part;
                    ?>
                        <span>/</span>
                        <a href="index.php?dir=<?= rawurlencode($crumbAcc) ?>"><?= h($part) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="topbar-right">
                <div class="clock-widget" id="realtime-clock">
                    <i class="bi bi-clock-history"></i>
                    <span id="clock-text">--:--:--</span>
                </div>
                <button class="neo-btn neo-btn-sm" onclick="location.reload()" title="Refresh Page">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
        </header>

        <!-- Body Content -->
        <main class="content-body">
            <!-- Hero Banner -->
            <section class="hero-banner">
                <div>
                    <h2>Welcome to Localhost Studio</h2>
                    <p>Workspace modern lokal Laragon. Jelajahi folder <code>/code</code>, kelola source code langsung, install project Laravel, Vue, React, CodeIgniter secara instan.</p>
                </div>
                <div class="hero-actions">
                    <button class="neo-btn neo-btn-primary" onclick="openNewProjectModal()">
                        <i class="bi bi-plus-circle-fill"></i> Install Project
                    </button>
                    <button class="neo-btn neo-btn-secondary" onclick="openCreateItemModal('file')">
                        <i class="bi bi-file-plus-fill"></i> Create File
                    </button>
                </div>
            </section>

            <!-- Stats Grid -->
            <section class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-tag">Detected Projects</span>
                        <div class="stat-icon-badge" style="background:#C8F169;"><i class="bi bi-box-seam-fill"></i></div>
                    </div>
                    <div class="stat-number"><?= $projectCount ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-tag">Folders In Path</span>
                        <div class="stat-icon-badge" style="background:#B9D7FF;"><i class="bi bi-folder-fill"></i></div>
                    </div>
                    <div class="stat-number"><?= $folderCount ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-tag">Files In Path</span>
                        <div class="stat-icon-badge" style="background:#FF9B71;"><i class="bi bi-file-code-fill"></i></div>
                    </div>
                    <div class="stat-number"><?= $fileCount ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-tag">PHP Engine</span>
                        <div class="stat-icon-badge" style="background:#FFB3D9;"><i class="bi bi-cpu"></i></div>
                    </div>
                    <div class="stat-number" style="font-size:22px;margin-top:6px;"><?= h($phpVersion) ?></div>
                </div>
            </section>

            <!-- Interactive D3.js Workspace Graph -->
            <section class="graph-panel">
                <div class="graph-header">
                    <h3><i class="bi bi-diagram-3-fill"></i> Workspace Architecture Graph (D3.js)</h3>
                    <div style="display:flex;gap:8px;">
                        <button class="neo-btn neo-btn-sm" onclick="loadD3Graph()"><i class="bi bi-arrow-repeat"></i> Reload Graph</button>
                    </div>
                </div>
                <div id="d3-container"></div>
                <div class="d3-legend">
                    <div class="legend-item"><div class="legend-dot" style="background:#171717;"></div> Root</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#B9D7FF;"></div> Directory</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#FF2D20;"></div> Laravel</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#646CFF;"></div> React/Vue/Vite</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#EF4223;"></div> CodeIgniter</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#4F5D95;"></div> PHP Package</div>
                    <div class="legend-item"><div class="legend-dot" style="background:#686868;"></div> File</div>
                </div>
            </section>

            <!-- Project & Files Section -->
            <section>
                <div class="section-control">
                    <div class="search-box-neo">
                        <i class="bi bi-search"></i>
                        <input type="search" id="neo-search" placeholder="Filter projects, files, or extensions..." autocomplete="off">
                    </div>

                    <div class="view-actions">
                        <button class="neo-btn neo-btn-sm" id="btn-toggle-view" onclick="toggleLayoutView()">
                            <i class="bi bi-list-ul"></i> List View
                        </button>
                        <button class="neo-btn neo-btn-sm neo-btn-secondary" onclick="openCreateItemModal('file')">
                            <i class="bi bi-plus-lg"></i> New File
                        </button>
                        <button class="neo-btn neo-btn-sm neo-btn-primary" onclick="openCreateItemModal('folder')">
                            <i class="bi bi-folder-plus"></i> New Folder
                        </button>
                    </div>
                </div>

                <!-- Cards Grid -->
                <div class="projects-grid" id="items-container">
                    <?php if ($relative !== ''): ?>
                        <article class="project-card" data-name=".. parent directory">
                            <div class="card-top">
                                <div class="card-icon" style="background:var(--secondary);"><i class="bi bi-arrow-return-left"></i></div>
                                <div class="card-info">
                                    <h4 class="card-title">.. Parent Directory</h4>
                                    <div class="card-badges"><span class="neo-tag" style="background:#eee;">Directory Nav</span></div>
                                </div>
                            </div>
                            <div class="card-buttons">
                                <a href="index.php?dir=<?= rawurlencode($parent) ?>" class="neo-btn neo-btn-sm">
                                    <i class="bi bi-folder2-open"></i> Back Up
                                </a>
                            </div>
                        </article>
                    <?php endif; ?>

                    <?php foreach ($items as $item):
                        $itemPath = $relative === '' ? $item['name'] : $relative . '/' . $item['name'];
                        $visitUrl = '/' . implode('/', array_map('rawurlencode', explode('/', $itemPath)));
                        $browseUrl = 'index.php?dir=' . rawurlencode($itemPath);

                        // Icon selection
                        $iconClass = 'bi-file-earmark-code';
                        $iconBg = '#fafafa';
                        if ($item['dir']) {
                            $iconClass = 'bi-folder-fill';
                            $iconBg = '#ffe9a0';
                            if ($item['project']) {
                                $pType = $item['types'][0]['name'] ?? '';
                                if ($pType === 'Laravel') { $iconClass = 'bi-fire'; $iconBg = '#ffe5e3'; }
                                elseif ($pType === 'Vue.js') { $iconClass = 'bi-vimeo'; $iconBg = '#e2f7ed'; }
                                elseif ($pType === 'React') { $iconClass = 'bi-atom'; $iconBg = '#def3fa'; }
                                elseif ($pType === 'CodeIgniter 4') { $iconClass = 'bi-flame'; $iconBg = '#ffede8'; }
                                else { $iconClass = 'bi-box-seam-fill'; $iconBg = '#C8F169'; }
                            }
                        } else {
                            if (in_array($item['ext'], ['php'])) { $iconClass = 'bi-filetype-php'; $iconBg = '#e9ebf5'; }
                            elseif (in_array($item['ext'], ['js', 'ts'])) { $iconClass = 'bi-filetype-js'; $iconBg = '#fff6cc'; }
                            elseif (in_array($item['ext'], ['css', 'scss'])) { $iconClass = 'bi-filetype-css'; $iconBg = '#def0ff'; }
                            elseif (in_array($item['ext'], ['html', 'htm'])) { $iconClass = 'bi-filetype-html'; $iconBg = '#feeae5'; }
                            elseif (in_array($item['ext'], ['json'])) { $iconClass = 'bi-filetype-json'; $iconBg = '#e3fae8'; }
                            elseif (in_array($item['ext'], ['md'])) { $iconClass = 'bi-markdown'; $iconBg = '#f0f0f0'; }
                            elseif (in_array($item['ext'], ['sql'])) { $iconClass = 'bi-database-fill'; $iconBg = '#fcf0ff'; }
                            elseif (in_array($item['ext'], ['png', 'jpg', 'jpeg', 'svg', 'webp', 'gif'])) { $iconClass = 'bi-image'; $iconBg = '#efffe6'; }
                        }
                    ?>
                        <article class="project-card" data-name="<?= h(strtolower($item['name'] . ' ' . $item['ext'])) ?>">
                            <div>
                                <div class="card-top">
                                    <div class="card-icon" style="background:<?= $iconBg ?>;">
                                        <i class="bi <?= $iconClass ?>"></i>
                                    </div>
                                    <div class="card-info">
                                        <h4 class="card-title" title="<?= h($item['name']) ?>"><?= h($item['name']) ?></h4>
                                        <div class="card-badges">
                                            <?php if ($item['dir']): ?>
                                                <?php foreach ($item['types'] as $tp): ?>
                                                    <span class="neo-tag" style="background:<?= $tp['bg'] ?>;color:<?= $tp['color'] ?>;font-weight:700;">
                                                        <?= h($tp['name']) ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <span class="neo-tag" style="background:#eee;"><?= strtoupper($item['ext'] ?: 'FILE') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="card-meta">
                                    <span><?= $item['dir'] ? 'Folder' : number_format(($item['size'] ?? 0) / 1024, 1) . ' KB' ?></span>
                                    <span>Mod: <?= date('d M Y H:i', $item['modified']) ?></span>
                                </div>
                            </div>

                            <div class="card-buttons">
                                <?php if ($item['dir']): ?>
                                    <a href="<?= h($browseUrl) ?>" class="neo-btn neo-btn-sm">
                                        <i class="bi bi-folder2-open"></i> Browse
                                    </a>
                                    <?php if ($item['project']): 
                                        $entrySub = $item['types'][0]['entry'] ?? '';
                                        $targetUrl = $visitUrl . $entrySub;
                                    ?>
                                        <a href="<?= h($targetUrl) ?>" target="_blank" rel="noopener" class="neo-btn neo-btn-sm neo-btn-primary">
                                            <i class="bi bi-box-arrow-up-right"></i> Visit
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <button class="neo-btn neo-btn-sm neo-btn-secondary" onclick="viewSourceFile('<?= h($itemPath) ?>')">
                                        <i class="bi bi-code-square"></i> View Source
                                    </button>
                                    <a href="<?= h($visitUrl) ?>" target="_blank" rel="noopener" class="neo-btn neo-btn-sm">
                                        <i class="bi bi-box-arrow-up-right"></i> Open
                                    </a>
                                <?php endif; ?>
                                <button class="neo-btn neo-btn-sm neo-btn-accent" onclick="confirmDeleteItem('<?= h($itemPath) ?>', '<?= $item['dir'] ? 'folder' : 'file' ?>')" title="Delete Item">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>

                    <div class="empty-state" id="empty-state" style="display:none;">
                        <i class="bi bi-search" style="font-size:32px;color:var(--muted);display:block;margin-bottom:12px;"></i>
                        <h3>Tidak ada item yang cocok dengan pencarian</h3>
                        <p style="color:var(--muted);margin-top:4px;">Coba gunakan kata kunci pencarian yang lain.</p>
                    </div>
                </div>
            </section>

            <!-- Server Info Cards -->
            <section class="info-cards">
                <div class="info-card">
                    <div class="info-card-title"><i class="bi bi-hdd-network"></i> Web Server Software</div>
                    <div class="info-card-val"><?= h($server) ?></div>
                </div>
                <div class="info-card">
                    <div class="info-card-title"><i class="bi bi-folder-symlink"></i> Workspace Document Root</div>
                    <div class="info-card-val"><?= h($root) ?></div>
                </div>
                <div class="info-card">
                    <div class="info-card-title"><i class="bi bi-code-slash"></i> PHP SAPI & Version</div>
                    <div class="info-card-val"><?= php_sapi_name() ?> / PHP <?= h($phpVersion) ?></div>
                </div>
            </section>
        </main>
    </div>
</div>

<!-- Modal: Code Viewer / Editor -->
<div class="modal-overlay" id="code-modal">
    <div class="modal-box">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:10px;">
                <i class="bi bi-code-slash" style="font-size:20px;"></i>
                <h3 id="modal-filename">filename.php</h3>
            </div>
            <div style="display:flex;gap:8px;">
                <button class="neo-btn neo-btn-sm neo-btn-primary" id="btn-save-code" onclick="saveCurrentCode()">
                    <i class="bi bi-floppy2-fill"></i> Save Changes
                </button>
                <button class="neo-btn neo-btn-sm" onclick="closeCodeModal()">
                    <i class="bi bi-x-lg"></i> Close
                </button>
            </div>
        </div>
        <div class="modal-body">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:12px;color:var(--muted);">
                <span id="modal-filepath">path/to/file</span>
                <span id="modal-filesize">0 KB</span>
            </div>
            <textarea class="code-textarea" id="code-editor-textarea" spellcheck="false"></textarea>
        </div>
    </div>
</div>

<script>
    // Realtime Clock Widget
    function updateClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('id-ID', { hour12: false });
        const dateStr = now.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' });
        const el = document.getElementById('clock-text');
        if (el) el.textContent = `${dateStr} · ${timeStr}`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    // Mobile Sidebar Toggle
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }

    // Toggle Grid / List View
    function toggleLayoutView() {
        const container = document.getElementById('items-container');
        const btn = document.getElementById('btn-toggle-view');
        const isList = container.classList.toggle('list-view');
        btn.innerHTML = isList ? '<i class="bi bi-grid-fill"></i> Grid View' : '<i class="bi bi-list-ul"></i> List View';
    }

    // Search and Filter Items
    const searchInput = document.getElementById('neo-search');
    const itemsContainer = document.getElementById('items-container');
    const emptyState = document.getElementById('empty-state');

    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase().trim();
        const cards = itemsContainer.querySelectorAll('.project-card');
        let matched = 0;

        cards.forEach(card => {
            const name = card.dataset.name || '';
            const isMatch = name.includes(query);
            card.style.display = isMatch ? '' : 'none';
            if (isMatch) matched++;
        });

        emptyState.style.display = (matched === 0) ? 'block' : 'none';
    });

    // 1. D3.js Workspace Architecture Graph
    function loadD3Graph() {
        const container = document.getElementById('d3-container');
        container.innerHTML = '<div style="padding:40px;text-align:center;color:#686868;"><i class="bi bi-arrow-repeat spin"></i> Memuat Visualisasi D3.js...</div>';

        fetch('index.php?api=graph_data')
            .then(res => res.json())
            .then(data => {
                container.innerHTML = '';
                const width = container.clientWidth || 800;
                const height = 300;

                const svg = d3.select('#d3-container')
                    .append('svg')
                    .attr('width', width)
                    .attr('height', height)
                    .attr('viewBox', [0, 0, width, height]);

                const colorMap = {
                    root: '#171717',
                    dir: '#B9D7FF',
                    laravel: '#FF2D20',
                    vite: '#646CFF',
                    codeigniter: '#EF4223',
                    'php-pkg': '#4F5D95',
                    'node-pkg': '#339933',
                    file: '#C8F169'
                };

                const simulation = d3.forceSimulation(data.nodes)
                    .force('link', d3.forceLink(data.links).id(d => d.id).distance(65))
                    .force('charge', d3.forceManyBody().strength(-120))
                    .force('center', d3.forceCenter(width / 2, height / 2))
                    .force('collision', d3.forceCollide().radius(22));

                const link = svg.append('g')
                    .attr('stroke', '#171717')
                    .attr('stroke-width', 1.8)
                    .attr('stroke-opacity', 0.6)
                    .selectAll('line')
                    .data(data.links)
                    .join('line');

                const node = svg.append('g')
                    .selectAll('g')
                    .data(data.nodes)
                    .join('g')
                    .call(d3.drag()
                        .on('start', dragstarted)
                        .on('drag', dragged)
                        .on('end', dragended)
                    );

                node.append('circle')
                    .attr('r', d => d.type === 'root' ? 14 : 9)
                    .attr('fill', d => colorMap[d.type] || '#fff')
                    .attr('stroke', '#171717')
                    .attr('stroke-width', 2);

                node.append('text')
                    .text(d => d.name)
                    .attr('x', 13)
                    .attr('y', 4)
                    .attr('font-size', '10px')
                    .attr('font-weight', '600')
                    .attr('font-family', 'Space Grotesk, sans-serif')
                    .attr('fill', '#171717');

                simulation.on('tick', () => {
                    link
                        .attr('x1', d => d.source.x)
                        .attr('y1', d => d.source.y)
                        .attr('x2', d => d.target.x)
                        .attr('y2', d => d.target.y);

                    node.attr('transform', d => `translate(${d.x},${d.y})`);
                });

                function dragstarted(event, d) {
                    if (!event.active) simulation.alphaTarget(0.3).restart();
                    d.fx = d.x;
                    d.fy = d.y;
                }
                function dragged(event, d) {
                    d.fx = event.x;
                    d.fy = event.y;
                }
                function dragended(event, d) {
                    if (!event.active) simulation.alphaTarget(0);
                    d.fx = null;
                    d.fy = null;
                }
            })
            .catch(err => {
                container.innerHTML = '<div style="padding:30px;color:red;text-align:center;">Gagal memuat graph D3.js: ' + err.message + '</div>';
            });
    }

    // Auto load D3 Graph on page startup
    document.addEventListener('DOMContentLoaded', loadD3Graph);

    // 2. SweetAlert2: Create New File / Folder
    function openCreateItemModal(type = 'file') {
        const isFile = type === 'file';
        Swal.fire({
            title: isFile ? '📝 Buat File Baru' : '📁 Buat Folder Baru',
            html: `
                <div style="text-align:left;font-family:Inter,sans-serif;">
                    <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Lokasi Folder:</label>
                    <input id="swal-dir" class="swal2-input" style="width:100%;margin:0 0 14px 0;font-size:13px;" value="<?= h($relative) ?>" placeholder="Contoh: code atau subfolder">

                    <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Nama ${isFile ? 'File (contoh: test.php, app.js)' : 'Folder'}:</label>
                    <input id="swal-name" class="swal2-input" style="width:100%;margin:0 0 14px 0;font-size:13px;" placeholder="${isFile ? 'app.php' : 'my-new-folder'}">

                    ${isFile ? `
                        <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Isi Konten Awal (Opsional):</label>
                        <textarea id="swal-content" class="swal2-textarea" style="width:100%;margin:0;height:100px;font-family:monospace;font-size:12px;" placeholder="// Tulis kode awal di sini..."></textarea>
                    ` : ''}
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Buat Sekarang',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#171717',
            focusConfirm: false,
            preConfirm: () => {
                const dir = document.getElementById('swal-dir').value.trim();
                const name = document.getElementById('swal-name').value.trim();
                const content = isFile ? document.getElementById('swal-content').value : '';
                if (!name) {
                    Swal.showValidationMessage('Nama tidak boleh kosong!');
                    return false;
                }
                return { type, dir, name, content };
            }
        }).then(result => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Membuat...',
                    text: 'Sedang menyimpan ke disk server.',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch('index.php?api=create_item', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(result.value)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Berhasil!',
                            text: data.message,
                            confirmButtonColor: '#171717'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Gagal',
                            text: data.message,
                            confirmButtonColor: '#171717'
                        });
                    }
                })
                .catch(err => {
                    Swal.fire('Error', err.message, 'error');
                });
            }
        });
    }

    // 3. SweetAlert2: Install Framework / Scaffold Project (Laravel, Vue, React, CodeIgniter, etc.)
    function openNewProjectModal() {
        Swal.fire({
            title: '🚀 Install Project Baru',
            html: `
                <div style="text-align:left;font-family:Inter,sans-serif;">
                    <p style="font-size:13px;color:#666;margin-bottom:14px;">Pilih framework atau template yang ingin Anda install ke dalam lingkungan Laragon.</p>
                    
                    <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Pilih Preset Framework:</label>
                    <select id="swal-framework" class="swal2-select" style="width:100%;margin:0 0 14px 0;font-size:13px;display:block;">
                        <option value="laravel">🔥 Laravel (Composer create-project)</option>
                        <option value="vue">🟢 Vue.js (Vite Template)</option>
                        <option value="react">⚛️ React (Vite Template)</option>
                        <option value="codeigniter">⚡ CodeIgniter 4 (AppStarter)</option>
                        <option value="php_starter">🐘 Clean PHP Web Starter</option>
                        <option value="html_starter">🌐 Modern HTML5/CSS/JS Starter</option>
                    </select>

                    <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Target Direktori:</label>
                    <input id="swal-proj-dir" class="swal2-input" style="width:100%;margin:0 0 14px 0;font-size:13px;" value="<?= h($relative ?: 'code') ?>" placeholder="code">

                    <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Nama Folder Project (alphanumeric):</label>
                    <input id="swal-proj-name" class="swal2-input" style="width:100%;margin:0;font-size:13px;" placeholder="my-awesome-app">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Install Project',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#171717',
            focusConfirm: false,
            preConfirm: () => {
                const framework = document.getElementById('swal-framework').value;
                const dir = document.getElementById('swal-proj-dir').value.trim();
                const name = document.getElementById('swal-proj-name').value.trim();
                if (!name) {
                    Swal.showValidationMessage('Nama project harus diisi!');
                    return false;
                }
                return { framework, dir, name };
            }
        }).then(res => {
            if (res.isConfirmed) {
                Swal.fire({
                    title: 'Sedang Menginstall...',
                    html: `Memproses instalasi <b>${res.value.framework}</b>.<br><small style="color:#666;">Proses Composer/NPM dapat memakan waktu beberapa saat tergantung koneksi.</small>`,
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch('index.php?api=install_project', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(res.value)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Instalasi Berhasil!',
                            html: `<p>${data.message}</p><pre style="background:#171717;color:#fff;padding:10px;border-radius:6px;font-size:11px;text-align:left;max-height:160px;overflow:auto;">${data.output || 'Proses selesai dengan sukses.'}</pre>`,
                            confirmButtonColor: '#171717'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Instalasi Gagal',
                            html: `<p>${data.message}</p><pre style="background:#171717;color:#ff8080;padding:10px;border-radius:6px;font-size:11px;text-align:left;max-height:160px;overflow:auto;">${data.output || 'Error'}</pre>`,
                            confirmButtonColor: '#171717'
                        });
                    }
                })
                .catch(err => {
                    Swal.fire('Error', err.message, 'error');
                });
            }
        });
    }

    // 4. Source Code Viewer & In-Browser Editor
    let activeFilePath = '';

    function viewSourceFile(filePath) {
        Swal.fire({
            title: 'Membaca Source Code...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        fetch('index.php?api=read_source&file=' + encodeURIComponent(filePath))
            .then(r => r.json())
            .then(data => {
                Swal.close();
                if (!data.success) {
                    Swal.fire('Peringatan', data.message, 'warning');
                    return;
                }

                activeFilePath = data.path;
                document.getElementById('modal-filename').textContent = data.filename;
                document.getElementById('modal-filepath').textContent = data.path;
                document.getElementById('modal-filesize').textContent = (data.size / 1024).toFixed(1) + ' KB';
                document.getElementById('code-editor-textarea').value = data.content;

                document.getElementById('code-modal').classList.add('active');
            })
            .catch(err => {
                Swal.fire('Error', err.message, 'error');
            });
    }

    function closeCodeModal() {
        document.getElementById('code-modal').classList.remove('active');
    }

    function saveCurrentCode() {
        if (!activeFilePath) return;
        const newContent = document.getElementById('code-editor-textarea').value;

        Swal.fire({
            title: 'Menyimpan Perubahan...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        fetch('index.php?api=save_file', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                file: activeFilePath,
                content: newContent
            })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Tersimpan!',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Gagal Menyimpan', data.message, 'error');
            }
        })
        .catch(err => {
            Swal.fire('Error', err.message, 'error');
        });
    }

    // 5. Delete File or Folder
    function confirmDeleteItem(itemPath, type) {
        Swal.fire({
            title: `Hapus ${type === 'folder' ? 'Folder' : 'File'}?`,
            text: `Yakin ingin menghapus permanent '${itemPath}'? Tindakan ini tidak dapat dibatalkan.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#171717',
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then(result => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Menghapus...',
                    didOpen: () => Swal.showLoading(),
                    allowOutsideClick: false
                });

                fetch('index.php?api=delete_item', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ path: itemPath })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Dihapus',
                            text: data.message,
                            confirmButtonColor: '#171717'
                        }).then(() => location.reload());
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                })
                .catch(err => Swal.fire('Error', err.message, 'error'));
            }
        });
    }

    // 6. Check Runtimes Modal
    function checkRuntimesModal() {
        Swal.fire({
            title: 'Memeriksa Runtime...',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        fetch('index.php?api=check_runtimes')
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    Swal.fire('Error', 'Gagal mendeteksi runtime', 'error');
                    return;
                }

                let listHtml = '<div style="text-align:left;font-family:Inter,sans-serif;">';
                data.runtimes.forEach(rt => {
                    const statusBadge = rt.detected 
                        ? `<span style="background:#C8F169;border:1.5px solid #171717;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:700;">DETECTED</span>`
                        : `<span style="background:#ffcccc;border:1.5px solid #171717;padding:2px 6px;border-radius:4px;font-size:10px;font-weight:700;">NOT FOUND</span>`;
                    
                    listHtml += `
                        <div style="border:2px solid #171717;background:#fff;padding:10px 14px;border-radius:8px;margin-bottom:8px;box-shadow:2px 2px 0 #171717;">
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <strong style="font-size:14px;">${rt.icon} ${rt.name}</strong>
                                ${statusBadge}
                            </div>
                            <div style="font-family:JetBrains Mono,monospace;font-size:12px;margin-top:4px;color:#333;">${rt.version}</div>
                            <div style="font-size:11px;color:#777;margin-top:2px;">Binary: ${rt.binary}</div>
                        </div>
                    `;
                });
                listHtml += '</div>';

                Swal.fire({
                    title: '⚙️ Runtime & Environment',
                    html: listHtml,
                    confirmButtonText: 'Tutup',
                    confirmButtonColor: '#171717',
                    width: '580px'
                });
            })
            .catch(err => Swal.fire('Error', err.message, 'error'));
    }
</script>
</body>
</html>
