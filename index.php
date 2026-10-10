<?php
/**
 * Localhost Studio - Laragon Development Workspace & Project Manager
 * Multi-page architecture:
 *  - page=dashboard : Ringkasan Workspace, Quick Actions, System Runtimes, D3.js Architecture Graph
 *  - page=workspace : Dedicated Workspace & File Explorer (Breadcrumb, Search, Grid/List, In-browser Code Viewer & Editor)
 *  - page=scaffold  : Project Installer (Laravel, Vue, React, CodeIgniter 4, PHP OOP, PHP MVC, PHP Pure, HTML5)
 *  - page=runtimes  : Version Switcher & Downloader (PHP 8.1, 8.2, 8.3, 8.4, Node.js 18, 20, 22)
 *
 * Full SweetAlert2 (`swal2`) dialogs, D3.js Force Simulation, and Neo-Brutalism Design.
 */

session_start();
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$root = realpath($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
$root = $root ?: __DIR__;
$page = $_GET['page'] ?? 'dashboard';

// --- SYSTEM HELPER FUNCTIONS ---

function getPhpBinary(): string {
    if (defined('PHP_BINARY') && file_exists(PHP_BINARY) && !str_ends_with(strtolower(PHP_BINARY), 'httpd.exe')) {
        return PHP_BINARY;
    }
    $phpDirs = glob('C:/laragon/bin/php/php*');
    if ($phpDirs) {
        rsort($phpDirs);
        foreach ($phpDirs as $pDir) {
            $candidate = $pDir . '/php.exe';
            if (file_exists($candidate)) return $candidate;
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
        'C:/laragon/bin/nodejs/node-v18/node.exe',
        'C:/laragon/bin/nodejs/node.exe'
    ];
    $laragonNode = glob('C:/laragon/bin/nodejs/*/node.exe');
    if ($laragonNode) array_unshift($candidates, $laragonNode[0]);
    foreach ($candidates as $c) {
        if (file_exists($c)) return escapeshellarg($c);
    }
    return 'node';
}

function getNpmCommand(): string {
    $candidates = [
        'C:/Program Files/nodejs/npm.cmd',
        'C:/laragon/bin/nodejs/node-v18/npm.cmd',
        'C:/laragon/bin/nodejs/npm.cmd'
    ];
    $laragonNpm = glob('C:/laragon/bin/nodejs/*/npm.cmd');
    if ($laragonNpm) array_unshift($candidates, $laragonNpm[0]);
    foreach ($candidates as $c) {
        if (file_exists($c)) return escapeshellarg($c);
    }
    return 'npm.cmd';
}

function h(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function resolveSafePath(string $subPath, string $root): ?string {
    $sub = trim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $subPath), DIRECTORY_SEPARATOR);
    $target = realpath($root . ($sub !== '' ? DIRECTORY_SEPARATOR . $sub : ''));
    if (!$target && !file_exists($root . ($sub !== '' ? DIRECTORY_SEPARATOR . $sub : ''))) {
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
    if (file_exists($dir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Controllers') && file_exists($dir . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Models')) {
        $types[] = ['name' => 'PHP Native MVC', 'color' => '#8A2BE2', 'bg' => '#f3e8ff', 'entry' => '/public'];
    }
    if (file_exists($dir . DIRECTORY_SEPARATOR . 'src') && file_exists($dir . DIRECTORY_SEPARATOR . 'index.php') && empty($types)) {
        $types[] = ['name' => 'PHP Native OOP', 'color' => '#2563EB', 'bg' => '#dbeafe', 'entry' => ''];
    }
    if (file_exists($dir . DIRECTORY_SEPARATOR . 'composer.json') && empty($types)) {
        $types[] = ['name' => 'PHP Project', 'color' => '#4F5D95', 'bg' => '#e9ebf5', 'entry' => ''];
    }
    if (file_exists($dir . DIRECTORY_SEPARATOR . 'index.php') && empty($types)) {
        $types[] = ['name' => 'PHP Web (Pure)', 'color' => '#4F5D95', 'bg' => '#e9ebf5', 'entry' => ''];
    }
    if (file_exists($dir . DIRECTORY_SEPARATOR . 'index.html') && empty($types)) {
        $types[] = ['name' => 'Static HTML', 'color' => '#E34F26', 'bg' => '#feeae5', 'entry' => ''];
    }
    return $types ?: [['name' => 'Folder', 'color' => '#555', 'bg' => '#eee', 'entry' => '']];
}

// --- REST API DISPATCHER ---

if (isset($_GET['api'])) {
    header('Content-Type: application/json; charset=UTF-8');
    $api = $_GET['api'];

    // 1. D3.js Graph Data API
    if ($api === 'graph_data') {
        $nodes = [['id' => 'root', 'name' => 'Workspace Root', 'type' => 'root', 'group' => 1]];
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
                elseif (file_exists($full . '/app/Controllers')) { $type = 'php-mvc'; $group = 7; }
                elseif (file_exists($full . '/composer.json')) { $type = 'php-pkg'; $group = 8; }
            }

            $nodes[] = ['id' => $nodeId, 'name' => $item, 'type' => $type, 'group' => $group];
            $links[] = ['source' => 'root', 'target' => $nodeId];

            if ($isDir) {
                $subScan = @scandir($full) ?: [];
                $count = 0;
                foreach ($subScan as $subItem) {
                    if ($subItem === '.' || $subItem === '..' || in_array($subItem, ['.git', 'node_modules', 'vendor'])) continue;
                    if ($count > 10) break;
                    $subId = $item . '/' . $subItem;
                    $subFull = $full . DIRECTORY_SEPARATOR . $subItem;
                    $subIsDir = is_dir($subFull);
                    $nodes[] = ['id' => $subId, 'name' => $subItem, 'type' => $subIsDir ? 'dir' : 'file', 'group' => $subIsDir ? 2 : 3];
                    $links[] = ['source' => $nodeId, 'target' => $subId];
                    $count++;
                }
            }
        }
        echo json_encode(['nodes' => $nodes, 'links' => $links]);
        exit;
    }

    // 2. File & Directory CRUD APIs
    if ($api === 'create_item') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $type = $input['type'] ?? 'file';
        $targetDir = trim($input['dir'] ?? '', '/');
        $name = trim($input['name'] ?? '');
        $content = $input['content'] ?? '';

        if (empty($name) || str_contains($name, '..') || str_contains($name, '/') || str_contains($name, '\\')) {
            echo json_encode(['success' => false, 'message' => 'Nama file/folder tidak valid.']);
            exit;
        }

        $baseDir = resolveSafePath($targetDir, $root);
        if (!$baseDir || !is_dir($baseDir)) {
            echo json_encode(['success' => false, 'message' => 'Direktori tujuan tidak valid.']);
            exit;
        }

        $fullPath = $baseDir . DIRECTORY_SEPARATOR . $name;
        if (file_exists($fullPath)) {
            echo json_encode(['success' => false, 'message' => 'File atau folder sudah ada!']);
            exit;
        }

        if ($type === 'folder') {
            $ok = @mkdir($fullPath, 0777, true);
        } else {
            $ok = @file_put_contents($fullPath, $content) !== false;
        }
        echo json_encode(['success' => $ok, 'message' => $ok ? ucfirst($type) . " '{$name}' berhasil dibuat!" : 'Gagal membuat item.']);
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
        if (in_array(strtolower($baseName), ['.env', 'id_rsa', 'id_rsa.pub', 'credentials', 'master.key']) || str_contains($target, DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR)) {
            echo json_encode(['success' => false, 'message' => 'Akses ke file rahasia/sensitif ini ditolak.']);
            exit;
        }
        $size = filesize($target);
        if ($size > 1.5 * 1024 * 1024) {
            echo json_encode(['success' => false, 'message' => 'Ukuran file melebihi 1.5 MB.']);
            exit;
        }
        echo json_encode([
            'success' => true,
            'filename' => $baseName,
            'path' => $fileParam,
            'size' => $size,
            'content' => file_get_contents($target)
        ]);
        exit;
    }

    if ($api === 'save_file') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $filePath = trim($input['file'] ?? '', '/');
        $target = resolveSafePath($filePath, $root);
        if (!$target || is_dir($target)) {
            echo json_encode(['success' => false, 'message' => 'File tidak valid.']);
            exit;
        }
        if (in_array(strtolower(basename($target)), ['.env', '.git'])) {
            echo json_encode(['success' => false, 'message' => 'File ini dilindungi.']);
            exit;
        }
        $ok = @file_put_contents($target, $input['content'] ?? '') !== false;
        echo json_encode(['success' => $ok, 'message' => $ok ? 'File berhasil disimpan!' : 'Gagal menyimpan file.']);
        exit;
    }

    if ($api === 'delete_item') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $target = resolveSafePath(trim($input['path'] ?? '', '/'), $root);
        if (!$target || $target === $root) {
            echo json_encode(['success' => false, 'message' => 'Akses ditolak.']);
            exit;
        }
        if (is_file($target)) {
            $ok = @unlink($target);
        } else {
            $it = new RecursiveDirectoryIterator($target, RecursiveDirectoryIterator::SKIP_DOTS);
            $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $f) {
                $f->isDir() ? @rmdir($f->getRealPath()) : @unlink($f->getRealPath());
            }
            $ok = @rmdir($target);
        }
        echo json_encode(['success' => $ok, 'message' => $ok ? 'Item berhasil dihapus.' : 'Gagal menghapus item.']);
        exit;
    }

    // 3. Project Scaffolding API (Frameworks & PHP Native OOP/MVC/Pure)
    if ($api === 'install_project') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $framework = strtolower(trim($input['framework'] ?? ''));
        $projectName = trim($input['name'] ?? '');
        $targetDir = trim($input['dir'] ?? '', '/');

        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $projectName)) {
            echo json_encode(['success' => false, 'message' => 'Nama project hanya boleh huruf, angka, (-), dan (_).']);
            exit;
        }

        $baseDir = resolveSafePath($targetDir, $root) ?: $root;
        $dest = $baseDir . DIRECTORY_SEPARATOR . $projectName;
        if (file_exists($dest)) {
            echo json_encode(['success' => false, 'message' => "Folder target '{$projectName}' sudah ada!"]);
            exit;
        }

        set_time_limit(300);
        $composerCmd = getComposerCommand();
        $npmCmd = getNpmCommand();
        $output = [];
        $status = 0;

        if ($framework === 'laravel') {
            $cmd = "cd " . escapeshellarg($baseDir) . " && " . $composerCmd . " create-project laravel/laravel " . escapeshellarg($projectName) . " --prefer-dist --no-interaction 2>&1";
            exec($cmd, $output, $status);
        } elseif ($framework === 'codeigniter') {
            $cmd = "cd " . escapeshellarg($baseDir) . " && " . $composerCmd . " create-project codeigniter4/appstarter " . escapeshellarg($projectName) . " --no-interaction 2>&1";
            exec($cmd, $output, $status);
        } elseif ($framework === 'vue') {
            $cmd = "cd " . escapeshellarg($baseDir) . " && " . $npmCmd . " create vite@latest " . escapeshellarg($projectName) . " -- --template vue -y 2>&1";
            exec($cmd, $output, $status);
        } elseif ($framework === 'react') {
            $cmd = "cd " . escapeshellarg($baseDir) . " && " . $npmCmd . " create vite@latest " . escapeshellarg($projectName) . " -- --template react -y 2>&1";
            exec($cmd, $output, $status);
        } elseif ($framework === 'php_mvc') {
            // PHP Native MVC Architecture
            @mkdir($dest . '/app/Controllers', 0777, true);
            @mkdir($dest . '/app/Models', 0777, true);
            @mkdir($dest . '/app/Views', 0777, true);
            @mkdir($dest . '/app/Core', 0777, true);
            @mkdir($dest . '/public', 0777, true);

            // Router & Core App
            $appCore = <<<'PHP'
<?php
namespace App\Core;

class App {
    protected $controller = 'HomeController';
    protected $method = 'index';
    protected $params = [];

    public function __construct() {
        $url = $this->parseUrl();
        if ($url && file_exists(__DIR__ . '/../Controllers/' . ucfirst($url[0]) . 'Controller.php')) {
            $this->controller = ucfirst($url[0]) . 'Controller';
            unset($url[0]);
        }
        require_once __DIR__ . '/../Controllers/' . $this->controller . '.php';
        $class = "App\\Controllers\\" . $this->controller;
        $this->controller = new $class();

        if (isset($url[1]) && method_exists($this->controller, $url[1])) {
            $this->method = $url[1];
            unset($url[1]);
        }
        $this->params = $url ? array_values($url) : [];
        call_user_func_array([$this->controller, $this->method], $this->params);
    }

    public function parseUrl() {
        if (isset($_GET['url'])) {
            return explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL));
        }
        return [];
    }
}
PHP;
            @file_put_contents($dest . '/app/Core/App.php', $appCore);

            $baseController = <<<'PHP'
<?php
namespace App\Core;

class Controller {
    public function view(string $view, array $data = []) {
        extract($data);
        require_once __DIR__ . '/../Views/' . $view . '.php';
    }
}
PHP;
            @file_put_contents($dest . '/app/Core/Controller.php', $baseController);

            $homeController = <<<'PHP'
<?php
namespace App\Controllers;
use App\Core\Controller;

class HomeController extends Controller {
    public function index() {
        $data = [
            'title' => 'PHP Native MVC Application',
            'desc' => 'Struktur MVC bersih siap dikembangkan dengan Controller, Model, dan View terpisah.'
        ];
        $this->view('home', $data);
    }
}
PHP;
            @file_put_contents($dest . '/app/Controllers/HomeController.php', $homeController);

            $homeView = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= \$title ?></title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f5f4ef; color: #171717; margin: 0; padding: 40px; }
        .box { max-width: 600px; margin: auto; background: white; border: 2.5px solid #171717; border-radius: 8px; padding: 30px; box-shadow: 4px 4px 0 #171717; }
        h1 { margin-top: 0; }
        .badge { background: #C8F169; border: 1.5px solid #171717; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 12px; }
    </style>
</head>
<body>
    <div class="box">
        <span class="badge">PHP MVC ARCHITECTURE</span>
        <h1><?= \$title ?></h1>
        <p><?= \$desc ?></p>
        <p>Lokasi file: <code>app/Controllers/HomeController.php</code> dan <code>app/Views/home.php</code></p>
    </div>
</body>
</html>
HTML;
            @file_put_contents($dest . '/app/Views/home.php', $homeView);

            // Public Entry Point & .htaccess
            $publicIndex = <<<'PHP'
<?php
spl_autoload_register(function($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

use App\Core\App;
$app = new App();
PHP;
            @file_put_contents($dest . '/public/index.php', $publicIndex);

            $htaccess = <<<'HTACCESS'
Options -Multiviews
RewriteEngine On
RewriteBase /
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.+)$ index.php?url=$1 [QSA,L]
HTACCESS;
            @file_put_contents($dest . '/public/.htaccess', $htaccess);

            $output[] = "PHP Native MVC Project berhasil di-scaffold di {$dest}.";
        } elseif ($framework === 'php_oop') {
            // PHP Native OOP Architecture
            @mkdir($dest . '/src', 0777, true);
            @mkdir($dest . '/config', 0777, true);

            $dbClass = <<<'PHP'
<?php
namespace App;

class Database {
    private $host = 'localhost';
    private $user = 'root';
    private $pass = '';
    private $dbname = 'test';
    protected $conn;

    public function __construct() {
        try {
            $this->conn = new \PDO("mysql:host={$this->host};dbname={$this->dbname}", $this->user, $this->pass);
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\PDOException $e) {
            // Silent fallback for localhost preview
        }
    }

    public function getConnection() {
        return $this->conn;
    }
}
PHP;
            @file_put_contents($dest . '/src/Database.php', $dbClass);

            $userClass = <<<'PHP'
<?php
namespace App;

class UserManager {
    public function getSystemStatus(): string {
        return "PHP Native OOP Service running cleanly.";
    }

    public function getSampleData(): array {
        return ['Modular Classes', 'PSR-4 Structure Ready', 'Encapsulation', 'Extensibility'];
    }
}
PHP;
            @file_put_contents($dest . '/src/UserManager.php', $userClass);

            $oopIndex = <<<'PHP'
<?php
spl_autoload_register(function($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});

use App\UserManager;
$manager = new UserManager();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>PHP Native OOP Starter</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #F5F4EF; color: #171717; padding: 40px; }
        .card { max-width: 550px; margin: auto; background: white; border: 2.5px solid #171717; padding: 25px; border-radius: 8px; box-shadow: 4px 4px 0 #171717; }
        ul { padding-left: 20px; }
        li { margin-bottom: 6px; }
    </style>
</head>
<body>
    <div class="card">
        <h2>🚀 PHP Native OOP Starter</h2>
        <p><?= $manager->getSystemStatus() ?></p>
        <h4>OOP Principles Included:</h4>
        <ul>
            <?php foreach ($manager->getSampleData() as $item): ?>
                <li><strong><?= htmlspecialchars($item) ?></strong></li>
            <?php endforeach; ?>
        </ul>
        <p><small>Created via Laragon Localhost Studio</small></p>
    </div>
</body>
</html>
PHP;
            @file_put_contents($dest . '/index.php', $oopIndex);
            $output[] = "PHP Native OOP Project berhasil di-scaffold di {$dest}.";
        } elseif ($framework === 'php_pure') {
            // PHP Pure (Simple single file or structured script)
            @mkdir($dest, 0777, true);
            $pureIndex = <<<PHP
<?php
// Pure PHP Script / Application Entry
\$appName = "{$projectName}";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title><?= \$appName ?></title>
    <style>
        body { font-family: system-ui, sans-serif; background: #fafafa; color: #171717; padding: 40px; text-align: center; }
        .box { max-width: 450px; margin: auto; background: white; border: 2px solid #171717; padding: 30px; border-radius: 8px; box-shadow: 4px 4px 0 #171717; }
    </style>
</head>
<body>
    <div class="box">
        <h2>🐘 <?= \$appName ?></h2>
        <p>Pure PHP Native without framework bloat.</p>
        <p>PHP Version: <b><?= PHP_VERSION ?></b></p>
    </div>
</body>
</html>
PHP;
            @file_put_contents($dest . '/index.php', $pureIndex);
            $output[] = "Pure PHP Project berhasil di-scaffold di {$dest}.";
        } elseif ($framework === 'html_starter') {
            @mkdir($dest, 0777, true);
            $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$projectName}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #F5F4EF; margin: 0; padding: 40px; display: flex; justify-content: center; }
        .card { background: white; border: 2.5px solid #171717; padding: 30px; border-radius: 10px; box-shadow: 4px 4px 0 #171717; text-align: center; max-width: 480px; }
        button { background: #C8F169; border: 2px solid #171717; padding: 10px 20px; font-weight: bold; cursor: pointer; box-shadow: 2px 2px 0 #171717; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🌐 {$projectName}</h1>
        <p>Modern HTML5, CSS3, & JavaScript template.</p>
        <button onclick="alert('Hello from {$projectName}!')">Click Test</button>
    </div>
</body>
</html>
HTML;
            @file_put_contents($dest . '/index.html', $html);
            $output[] = "HTML5 Starter berhasil dibuat di {$dest}.";
        } else {
            echo json_encode(['success' => false, 'message' => 'Preset framework tidak dikenali.']);
            exit;
        }

        $success = ($status === 0 || file_exists($dest));
        echo json_encode([
            'success' => $success,
            'message' => $success ? "Project '{$projectName}' berhasil di-scaffold!" : "Scaffolding error code: {$status}",
            'output' => implode("\n", array_slice($output, -15))
        ]);
        exit;
    }

    // 4. Runtime Manager & Version Switcher / Downloader API
    if ($api === 'switch_version') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $type = $input['type'] ?? 'php'; // php or nodejs
        $targetVersion = trim($input['version'] ?? '');

        $iniPath = 'C:/laragon/usr/laragon.ini';
        if (!file_exists($iniPath)) {
            echo json_encode(['success' => false, 'message' => 'File laragon.ini tidak ditemukan.']);
            exit;
        }

        $content = file_get_contents($iniPath);
        if ($type === 'php') {
            $folder = "C:/laragon/bin/php/{$targetVersion}";
            if (!is_dir($folder)) {
                echo json_encode(['success' => false, 'message' => "Folder versi PHP '{$targetVersion}' tidak ditemukan di disk!"]);
                exit;
            }

            // Update laragon.ini
            $content = preg_replace('/(\[php\][^\[]*Version=)[^\r\n]*/', '$1' . $targetVersion, $content);
            @file_put_contents($iniPath, $content);

            // Update C:/laragon/etc/apache2/mod_php.conf if Apache is running
            $modPhpFile = 'C:/laragon/etc/apache2/mod_php.conf';
            if (file_exists($modPhpFile)) {
                // Determine dll: php8apache2_4.dll or php7apache2_4.dll
                $dll = file_exists($folder . '/php8apache2_4.dll') ? 'php8apache2_4.dll' : 'php7apache2_4.dll';
                $modConf = "# This file is auto-generated by Laragon\n";
                $modConf .= "LoadModule php_module \"{$folder}/{$dll}\"\n";
                $modConf .= "PHPIniDir \"{$folder}\"\n";
                $modConf .= "<IfModule mime_module>\n    AddType application/x-httpd-php .php\n</IfModule>\n";
                @file_put_contents($modPhpFile, $modConf);
            }

            echo json_encode([
                'success' => true,
                'message' => "Versi PHP berhasil diganti ke '{$targetVersion}'. Silakan restart Apache di Laragon jika diperlukan."
            ]);
            exit;
        } elseif ($type === 'nodejs') {
            $folder = "C:/laragon/bin/nodejs/{$targetVersion}";
            if (!is_dir($folder)) {
                echo json_encode(['success' => false, 'message' => "Folder Node.js '{$targetVersion}' tidak ditemukan di disk!"]);
                exit;
            }
            $content = preg_replace('/(\[nodejs\][^\[]*Version=)[^\r\n]*/', '$1' . $targetVersion, $content);
            @file_put_contents($iniPath, $content);

            echo json_encode([
                'success' => true,
                'message' => "Versi Node.js berhasil dialihkan ke '{$targetVersion}' di konfigurasi Laragon."
            ]);
            exit;
        }
    }

    if ($api === 'download_runtime') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $type = $input['type'] ?? 'php'; // php or nodejs
        $url = trim($input['url'] ?? '');
        $folderName = trim($input['folder'] ?? '');

        if (empty($url) || empty($folderName)) {
            echo json_encode(['success' => false, 'message' => 'URL download atau nama folder tidak valid.']);
            exit;
        }

        set_time_limit(600);
        $targetDir = ($type === 'php') ? 'C:/laragon/bin/php/' . $folderName : 'C:/laragon/bin/nodejs/' . $folderName;
        if (file_exists($targetDir)) {
            echo json_encode(['success' => false, 'message' => "Folder '{$folderName}' sudah ada di disk!"]);
            exit;
        }

        $tmpZip = 'C:/laragon/tmp_' . time() . '.zip';
        $psDownload = "powershell -Command \"Invoke-WebRequest -Uri '{$url}' -OutFile '{$tmpZip}'\"";
        exec($psDownload, $dOut, $dStatus);

        if ($dStatus !== 0 || !file_exists($tmpZip)) {
            echo json_encode(['success' => false, 'message' => 'Gagal mendownload package runtime dari server remote.']);
            exit;
        }

        // Extract
        @mkdir($targetDir, 0777, true);
        $psExtract = "powershell -Command \"Expand-Archive -Path '{$tmpZip}' -DestinationPath '{$targetDir}' -Force\"";
        exec($psExtract, $eOut, $eStatus);
        @unlink($tmpZip);

        if ($eStatus === 0) {
            echo json_encode([
                'success' => true,
                'message' => "Runtime {$type} ('{$folderName}') berhasil didownload dan diekstrak ke Laragon bin!"
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengekstrak arsip package runtime.']);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid API route.']);
    exit;
}

// --- WORKSPACE & METADATA SCANNING ---

$relative = trim(str_replace('\\', '/', $_GET['dir'] ?? ''), '/');
$current = realpath($root . ($relative !== '' ? DIRECTORY_SEPARATOR . $relative : ''));
if ($current === false || !is_dir($current) || ($current !== $root && !str_starts_with($current, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR))) {
    $current = $root;
    $relative = '';
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

// Read installed & available runtimes from Laragon disk
$installedPhp = array_map('basename', glob('C:/laragon/bin/php/php*') ?: []);
$installedNode = array_map('basename', glob('C:/laragon/bin/nodejs/*') ?: []);

// Read active from laragon.ini
$iniData = @parse_ini_file('C:/laragon/usr/laragon.ini', true);
$activePhpVer = $iniData['php']['Version'] ?? basename(dirname(PHP_BINARY));
$activeNodeVer = $iniData['nodejs']['Version'] ?? ($installedNode[0] ?? 'node-v18');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Localhost Studio — <?= ucfirst($page) ?></title>
    <!-- Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600&family=Space+Grotesk:wght@500;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- SweetAlert2 (swal2) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- D3.js -->
    <script src="https://cdn.jsdelivr.net/npm/d3@7"></script>

    <style>
        :root {
            --bg: #F5F4EF;
            --surface: #FFFFFF;
            --primary: #C8F169;
            --primary-hover: #b4df56;
            --secondary: #B9D7FF;
            --accent: #FF9B71;
            --accent-pink: #FFB3D9;
            --accent-purple: #E0C3FC;
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

        * { box-sizing: border-box; margin: 0; padding: 0; }

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

        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
        a { color: inherit; text-decoration: none; }
        button, input, select, textarea { font-family: inherit; }

        /* Neo-Brutalist Layout Structure */
        .app-wrapper { display: flex; width: 100vw; min-height: 100vh; }

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
            padding: 22px 20px;
            border-bottom: var(--border-width) solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
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
            color: var(--text);
        }

        .brand-titles h1 { font-size: 19px; line-height: 1.1; }
        .brand-badge {
            font-size: 10px; font-weight: 700; text-transform: uppercase;
            background: var(--secondary); border: 1.5px solid var(--border);
            padding: 1px 6px; border-radius: 4px; display: inline-block; margin-top: 3px;
        }

        .sidebar-nav {
            padding: 18px 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            flex-grow: 1;
            overflow-y: auto;
        }

        .nav-label {
            font-size: 11px; font-weight: 800; color: var(--muted);
            text-transform: uppercase; letter-spacing: 0.08em; padding: 10px 10px 4px;
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

        .nav-link i { font-size: 18px; }

        .nav-link:hover {
            background: #edece6;
            border-color: var(--border);
        }

        .nav-link.active {
            background: var(--primary);
            border: var(--border-width) solid var(--border);
            box-shadow: var(--shadow-hard);
            transform: translate(-1px, -1px);
        }

        .sidebar-footer {
            padding: 14px 16px;
            border-top: var(--border-width) solid var(--border);
            background: var(--bg);
        }

        .server-quick-status {
            background: var(--surface);
            border: 2px solid var(--border);
            padding: 9px 12px;
            border-radius: var(--radius-sm);
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .live-pulse {
            width: 9px; height: 9px; background: #10b981;
            border: 1.5px solid var(--border); border-radius: 50%;
            display: inline-block; animation: pulse 1.8s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* Main Workspace Container */
        .main-wrapper {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
            overflow-y: auto;
            background: var(--bg);
        }

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

        .topbar-left { display: flex; align-items: center; gap: 14px; }
        .mobile-toggle {
            display: none; background: var(--surface);
            border: var(--border-width) solid var(--border); box-shadow: 2px 2px 0 var(--border);
            border-radius: var(--radius-sm); padding: 6px 10px; cursor: pointer; font-size: 18px;
        }

        .clock-widget {
            background: var(--surface);
            border: var(--border-width) solid var(--border);
            box-shadow: 3px 3px 0 var(--border);
            padding: 6px 14px;
            border-radius: var(--radius-sm);
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

        .neo-btn-primary { background: var(--primary); }
        .neo-btn-primary:hover { background: var(--primary-hover); }
        .neo-btn-secondary { background: var(--secondary); }
        .neo-btn-accent { background: var(--accent); color: #fff; }
        .neo-btn-purple { background: var(--accent-purple); }
        .neo-btn-sm { padding: 6px 11px; font-size: 12px; box-shadow: 2px 2px 0 var(--border); }
        .neo-btn-sm:hover { box-shadow: 4px 4px 0 var(--border); }

        .content-body {
            padding: 28px;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        .page-header {
            margin-bottom: 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-header h2 { font-size: 28px; }
        .page-header p { color: var(--muted); font-size: 14px; margin-top: 3px; }

        /* Neo Card */
        .neo-card {
            background: var(--surface);
            border: var(--border-width) solid var(--border);
            box-shadow: var(--shadow-hard);
            border-radius: var(--radius-md);
            padding: 24px;
            margin-bottom: 24px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: var(--surface);
            border: var(--border-width) solid var(--border);
            box-shadow: var(--shadow-hard);
            border-radius: var(--radius-md);
            padding: 18px 20px;
            transition: transform 0.15s;
        }

        .stat-card:hover { transform: translateY(-2px); }
        .stat-tag { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; }
        .stat-number { font-size: 30px; font-weight: 800; font-family: 'Space Grotesk', sans-serif; line-height: 1.1; margin-top: 6px; }

        /* Scaffolding Presets Grid */
        .preset-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(310px, 1fr));
            gap: 18px;
            margin-top: 16px;
        }

        .preset-card {
            background: var(--surface);
            border: var(--border-width) solid var(--border);
            box-shadow: var(--shadow-hard);
            border-radius: var(--radius-md);
            padding: 22px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.15s ease;
        }

        .preset-card:hover { transform: translate(-2px, -2px); box-shadow: var(--shadow-hard-hover); }
        .preset-header { display: flex; align-items: center; gap: 14px; margin-bottom: 12px; }
        .preset-icon {
            width: 48px; height: 48px; border: var(--border-width) solid var(--border);
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            font-size: 24px; box-shadow: 2px 2px 0 var(--border);
        }

        .preset-title { font-size: 18px; font-weight: 700; }
        .preset-desc { font-size: 13px; color: var(--muted); margin-bottom: 16px; line-height: 1.45; }

        /* File Explorer Style */
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

        .project-card:hover { transform: translate(-2px, -2px); box-shadow: var(--shadow-hard-hover); }
        .card-top { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 14px; }
        .card-icon {
            width: 44px; height: 44px; border: var(--border-width) solid var(--border);
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            font-size: 20px; box-shadow: 2px 2px 0 var(--border); flex-shrink: 0;
        }
        .card-title { font-size: 16px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .neo-tag {
            font-size: 10px; font-weight: 800; text-transform: uppercase;
            padding: 2px 7px; border: 1.5px solid var(--border); border-radius: 4px; box-shadow: 1px 1px 0 var(--border);
        }
        .card-meta { font-size: 11px; color: var(--muted); border-top: 1.5px dashed #ccc; padding-top: 10px; margin-bottom: 14px; display: flex; justify-content: space-between; }
        .card-buttons { display: flex; gap: 8px; flex-wrap: wrap; }

        /* List View */
        .projects-grid.list-view { display: flex; flex-direction: column; gap: 10px; }
        .projects-grid.list-view .project-card { flex-direction: row; align-items: center; padding: 12px 18px; }
        .projects-grid.list-view .card-top { margin-bottom: 0; align-items: center; flex: 1; }
        .projects-grid.list-view .card-meta { border-top: none; padding-top: 0; margin-bottom: 0; margin-right: 20px; }

        /* Search input */
        .search-box-neo {
            display: flex; align-items: center; background: var(--surface);
            border: var(--border-width) solid var(--border); box-shadow: 3px 3px 0 var(--border);
            border-radius: var(--radius-sm); padding: 4px 12px; width: 320px; max-width: 100%;
        }
        .search-box-neo input { border: none; background: transparent; outline: none; font-size: 13px; font-weight: 600; width: 100%; padding: 6px 0; }

        /* Modal Overlay */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(0,0,0,0.65);
            z-index: 999; display: none; align-items: center; justify-content: center; padding: 20px; backdrop-filter: blur(2px);
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: var(--surface); border: var(--border-width) solid var(--border);
            box-shadow: 8px 8px 0 var(--border); border-radius: var(--radius-md);
            width: 900px; max-width: 100%; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden;
        }
        .modal-header { padding: 16px 20px; border-bottom: var(--border-width) solid var(--border); display: flex; justify-content: space-between; align-items: center; background: #faf8f0; }
        .modal-body { padding: 18px 20px; overflow-y: auto; flex-grow: 1; }
        .code-textarea {
            width: 100%; height: 420px; background: #1e1e1e; color: #d4d4d4;
            font-family: 'JetBrains Mono', monospace; font-size: 13px; padding: 14px;
            border: var(--border-width) solid var(--border); border-radius: var(--radius-sm); resize: vertical; outline: none;
        }

        /* Runtime Version Card Table */
        .runtime-version-item {
            background: var(--surface); border: var(--border-width) solid var(--border);
            box-shadow: 3px 3px 0 var(--border); border-radius: var(--radius-sm);
            padding: 16px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px;
        }

        /* Responsive Breakpoints */
        @media (max-width: 900px) {
            .sidebar { position: fixed; transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .mobile-toggle { display: flex; }
            .topbar { padding: 12px 18px; }
            .content-body { padding: 18px; }
        }
    </style>
</head>
<body>

<div class="app-wrapper">
    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-logo-icon"><i class="bi bi-terminal-split"></i></div>
            <div class="brand-titles">
                <h1>Localhost Studio</h1>
                <span class="brand-badge">Laragon Core</span>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-label">Navigation</div>
            <a href="index.php?page=dashboard" class="nav-link <?= ($page === 'dashboard') ? 'active' : '' ?>">
                <i class="bi bi-grid-fill"></i>
                <span>Dashboard Overview</span>
            </a>
            <a href="index.php?page=workspace" class="nav-link <?= ($page === 'workspace') ? 'active' : '' ?>">
                <i class="bi bi-folder-check"></i>
                <span>Workspace Explorer</span>
            </a>
            <a href="index.php?page=scaffold" class="nav-link <?= ($page === 'scaffold') ? 'active' : '' ?>">
                <i class="bi bi-rocket-takeoff-fill"></i>
                <span>Install & Scaffolding</span>
            </a>
            <a href="index.php?page=runtimes" class="nav-link <?= ($page === 'runtimes') ? 'active' : '' ?>">
                <i class="bi bi-cpu-fill"></i>
                <span>Runtime & Versions</span>
            </a>

            <div class="nav-label" style="margin-top:12px;">Quick Folders</div>
            <a href="index.php?page=workspace&dir=code" class="nav-link <?= ($page === 'workspace' && $relative === 'code') ? 'active' : '' ?>">
                <i class="bi bi-code-square"></i>
                <span>Direct /code Folder</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="server-quick-status">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="live-pulse"></span>
                    <span style="font-weight:700;">Laragon Online</span>
                </div>
                <span style="font-size:11px;font-family:monospace;">PHP <?= h($phpVersion) ?></span>
            </div>
        </div>
    </aside>

    <!-- Main Workspace -->
    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <button class="mobile-toggle" onclick="toggleSidebar()"><i class="bi bi-list"></i></button>
                <div style="display:flex;align-items:center;gap:8px;">
                    <h3 style="font-size:16px;text-transform:capitalize;"><?= h($page) ?> Page</h3>
                    <span style="font-size:12px;color:var(--muted);">· C:\laragon\www<?= $relative ? '\\' . h($relative) : '' ?></span>
                </div>
            </div>

            <div class="topbar-right" style="display:flex;align-items:center;gap:12px;">
                <div class="clock-widget">
                    <i class="bi bi-clock-history"></i>
                    <span id="clock-text">--:--:--</span>
                </div>
                <button class="neo-btn neo-btn-sm" onclick="location.reload()" title="Refresh Page">
                    <i class="bi bi-arrow-clockwise"></i>
                </button>
            </div>
        </header>

        <!-- Content Area Per Page -->
        <main class="content-body">
            
            <?php if ($page === 'dashboard'): ?>
                <!-- ================= PAGE 1: DASHBOARD ================= -->
                <div class="page-header">
                    <div>
                        <h2>Dashboard Overview</h2>
                        <p>Ringkasan performa sistem, statistik project lokal, dan arsitektur workspace Laragon.</p>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <a href="index.php?page=scaffold" class="neo-btn neo-btn-primary">
                            <i class="bi bi-plus-circle-fill"></i> New Project
                        </a>
                        <a href="index.php?page=workspace" class="neo-btn neo-btn-secondary">
                            <i class="bi bi-folder-fill"></i> Open Workspace
                        </a>
                    </div>
                </div>

                <!-- Stats Grid -->
                <section class="stats-grid">
                    <div class="stat-card">
                        <span class="stat-tag">Discovered Projects</span>
                        <div class="stat-number" style="color:#171717;"><?= $projectCount ?></div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-tag">Total Directories</span>
                        <div class="stat-number"><?= $folderCount ?></div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-tag">Total Files In Path</span>
                        <div class="stat-number"><?= $fileCount ?></div>
                    </div>
                    <div class="stat-card">
                        <span class="stat-tag">Active PHP Runtime</span>
                        <div class="stat-number" style="font-size:22px;margin-top:8px;">PHP <?= h($phpVersion) ?></div>
                    </div>
                </section>

                <!-- D3.js Workspace Architecture Graph -->
                <section class="neo-card" style="padding:0;overflow:hidden;">
                    <div style="padding:16px 20px;border-bottom:var(--border-width) solid var(--border);display:flex;justify-content:space-between;align-items:center;background:#faf9f5;">
                        <h3 style="font-size:16px;"><i class="bi bi-diagram-3-fill"></i> D3.js Interactive Workspace Architecture</h3>
                        <button class="neo-btn neo-btn-sm" onclick="loadD3Graph()"><i class="bi bi-arrow-repeat"></i> Reload Graph</button>
                    </div>
                    <div id="d3-container" style="width:100%;height:320px;background:#fbfbf9;cursor:grab;"></div>
                    <div style="padding:10px 18px;border-top:1.5px solid var(--border);background:var(--surface);font-size:11px;font-weight:700;display:flex;gap:16px;flex-wrap:wrap;">
                        <div><span style="display:inline-block;width:10px;height:10px;background:#171717;border-radius:50%;margin-right:4px;"></span> Root</div>
                        <div><span style="display:inline-block;width:10px;height:10px;background:#B9D7FF;border-radius:50%;margin-right:4px;"></span> Folder</div>
                        <div><span style="display:inline-block;width:10px;height:10px;background:#FF2D20;border-radius:50%;margin-right:4px;"></span> Laravel</div>
                        <div><span style="display:inline-block;width:10px;height:10px;background:#646CFF;border-radius:50%;margin-right:4px;"></span> Vue/React/Vite</div>
                        <div><span style="display:inline-block;width:10px;height:10px;background:#EF4223;border-radius:50%;margin-right:4px;"></span> CodeIgniter</div>
                        <div><span style="display:inline-block;width:10px;height:10px;background:#8A2BE2;border-radius:50%;margin-right:4px;"></span> PHP MVC</div>
                        <div><span style="display:inline-block;width:10px;height:10px;background:#C8F169;border-radius:50%;margin-right:4px;"></span> Files</div>
                    </div>
                </section>

                <!-- Quick Runtimes Status Overview -->
                <section class="neo-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <h3><i class="bi bi-cpu-fill"></i> Environment & Local Toolchain</h3>
                        <a href="index.php?page=runtimes" class="neo-btn neo-btn-sm neo-btn-purple">Manage Versions & Download</a>
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(240px, 1fr));gap:14px;">
                        <div style="border:2px solid var(--border);padding:12px;border-radius:8px;background:#fff;box-shadow:2px 2px 0 var(--border);">
                            <div style="font-size:11px;color:var(--muted);font-weight:700;">ACTIVE PHP</div>
                            <div style="font-weight:700;font-size:15px;margin-top:4px;">PHP <?= h($phpVersion) ?></div>
                            <div style="font-size:11px;color:#555;margin-top:2px;">Config: <?= h($activePhpVer) ?></div>
                        </div>
                        <div style="border:2px solid var(--border);padding:12px;border-radius:8px;background:#fff;box-shadow:2px 2px 0 var(--border);">
                            <div style="font-size:11px;color:var(--muted);font-weight:700;">ACTIVE NODE.JS</div>
                            <div style="font-weight:700;font-size:15px;margin-top:4px;"><?= h($activeNodeVer) ?></div>
                            <div style="font-size:11px;color:#555;margin-top:2px;">In Laragon / System PATH</div>
                        </div>
                        <div style="border:2px solid var(--border);padding:12px;border-radius:8px;background:#fff;box-shadow:2px 2px 0 var(--border);">
                            <div style="font-size:11px;color:var(--muted);font-weight:700;">WEB SERVER</div>
                            <div style="font-weight:700;font-size:15px;margin-top:4px;"><?= h($server) ?></div>
                            <div style="font-size:11px;color:#555;margin-top:2px;">Doc Root: <?= h($root) ?></div>
                        </div>
                    </div>
                </section>

            <?php elseif ($page === 'workspace'): ?>
                <!-- ================= PAGE 2: WORKSPACE EXPLORER ================= -->
                <div class="page-header">
                    <div>
                        <h2>Workspace Explorer</h2>
                        <p>Kelola, jelajahi, buat file, dan edit source code langsung di direktori Laragon.</p>
                    </div>
                    <div style="display:flex;gap:10px;">
                        <button class="neo-btn neo-btn-secondary" onclick="openCreateItemModal('file')">
                            <i class="bi bi-file-earmark-plus-fill"></i> New File
                        </button>
                        <button class="neo-btn neo-btn-primary" onclick="openCreateItemModal('folder')">
                            <i class="bi bi-folder-plus"></i> New Folder
                        </button>
                    </div>
                </div>

                <!-- Breadcrumb & Filter Bar -->
                <div class="neo-card" style="padding:16px 20px;margin-bottom:20px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;">
                    <div style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:700;flex-wrap:wrap;">
                        <a href="index.php?page=workspace" style="background:#fff;border:1.5px solid var(--border);padding:3px 8px;border-radius:4px;"><i class="bi bi-house-door-fill"></i> root</a>
                        <?php
                        $crumbAcc = '';
                        foreach (array_filter(explode('/', $relative), 'strlen') as $part):
                            $crumbAcc = $crumbAcc === '' ? $part : $crumbAcc . '/' . $part;
                        ?>
                            <span>/</span>
                            <a href="index.php?page=workspace&dir=<?= rawurlencode($crumbAcc) ?>" style="background:#fff;border:1.5px solid var(--border);padding:3px 8px;border-radius:4px;"><?= h($part) ?></a>
                        <?php endforeach; ?>
                    </div>

                    <div style="display:flex;gap:10px;align-items:center;">
                        <div class="search-box-neo">
                            <i class="bi bi-search"></i>
                            <input type="search" id="neo-search" placeholder="Search files or folders..." autocomplete="off">
                        </div>
                        <button class="neo-btn neo-btn-sm" id="btn-toggle-view" onclick="toggleLayoutView()">
                            <i class="bi bi-list-ul"></i> List View
                        </button>
                    </div>
                </div>

                <!-- File Items Grid -->
                <div class="projects-grid" id="items-container">
                    <?php if ($relative !== ''): ?>
                        <article class="project-card" data-name=".. parent directory">
                            <div class="card-top">
                                <div class="card-icon" style="background:var(--secondary);"><i class="bi bi-arrow-return-left"></i></div>
                                <div>
                                    <h4 class="card-title">.. Parent Directory</h4>
                                    <div class="card-badges"><span class="neo-tag" style="background:#eee;">Navigate Up</span></div>
                                </div>
                            </div>
                            <div class="card-buttons">
                                <a href="index.php?page=workspace&dir=<?= rawurlencode($parent) ?>" class="neo-btn neo-btn-sm">
                                    <i class="bi bi-folder2-open"></i> Back
                                </a>
                            </div>
                        </article>
                    <?php endif; ?>

                    <?php foreach ($items as $item):
                        $itemPath = $relative === '' ? $item['name'] : $relative . '/' . $item['name'];
                        $visitUrl = '/' . implode('/', array_map('rawurlencode', explode('/', $itemPath)));
                        $browseUrl = 'index.php?page=workspace&dir=' . rawurlencode($itemPath);

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
                                elseif (str_contains($pType, 'MVC')) { $iconClass = 'bi-diagram-2'; $iconBg = '#f3e8ff'; }
                                elseif (str_contains($pType, 'OOP')) { $iconClass = 'bi-boxes'; $iconBg = '#dbeafe'; }
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
                            elseif (in_array($item['ext'], ['png', 'jpg', 'jpeg', 'svg', 'webp'])) { $iconClass = 'bi-image'; $iconBg = '#efffe6'; }
                        }
                    ?>
                        <article class="project-card" data-name="<?= h(strtolower($item['name'] . ' ' . $item['ext'])) ?>">
                            <div>
                                <div class="card-top">
                                    <div class="card-icon" style="background:<?= $iconBg ?>;">
                                        <i class="bi <?= $iconClass ?>"></i>
                                    </div>
                                    <div style="flex-grow:1;min-width:0;">
                                        <h4 class="card-title" title="<?= h($item['name']) ?>"><?= h($item['name']) ?></h4>
                                        <div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:4px;">
                                            <?php if ($item['dir']): ?>
                                                <?php foreach ($item['types'] as $tp): ?>
                                                    <span class="neo-tag" style="background:<?= $tp['bg'] ?>;color:<?= $tp['color'] ?>;">
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
                                    <span><?= date('d M Y H:i', $item['modified']) ?></span>
                                </div>
                            </div>

                            <div class="card-buttons">
                                <?php if ($item['dir']): ?>
                                    <a href="<?= h($browseUrl) ?>" class="neo-btn neo-btn-sm">
                                        <i class="bi bi-folder2-open"></i> Browse
                                    </a>
                                    <?php if ($item['project']): 
                                        $entrySub = $item['types'][0]['entry'] ?? '';
                                    ?>
                                        <a href="<?= h($visitUrl . $entrySub) ?>" target="_blank" rel="noopener" class="neo-btn neo-btn-sm neo-btn-primary">
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
                                <button class="neo-btn neo-btn-sm neo-btn-accent" onclick="confirmDeleteItem('<?= h($itemPath) ?>', '<?= $item['dir'] ? 'folder' : 'file' ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

            <?php elseif ($page === 'scaffold'): ?>
                <!-- ================= PAGE 3: INSTALL & SCAFFOLDING ================= -->
                <div class="page-header">
                    <div>
                        <h2>Install & Scaffolding Project</h2>
                        <p>Pilih arsitektur atau framework yang diinginkan untuk membuat project baru siap pakai di Laragon.</p>
                    </div>
                </div>

                <div class="preset-grid">
                    <!-- Option 1: PHP Native MVC -->
                    <div class="preset-card">
                        <div>
                            <div class="preset-header">
                                <div class="preset-icon" style="background:#E0C3FC;"><i class="bi bi-diagram-2"></i></div>
                                <div>
                                    <h3 class="preset-title">PHP Native MVC</h3>
                                    <span class="neo-tag" style="background:#f3e8ff;color:#8A2BE2;">Custom Architecture</span>
                                </div>
                            </div>
                            <p class="preset-desc">Arsitektur MVC (Model-View-Controller) murni tanpa framework eksternal. Dilengkapi dengan auto-routing, controller dasar, view rendering, dan struktur folder rapi.</p>
                        </div>
                        <button class="neo-btn neo-btn-primary" onclick="triggerScaffoldModal('php_mvc', 'PHP Native MVC')">
                            <i class="bi bi-plus-circle-fill"></i> Scaffold MVC Project
                        </button>
                    </div>

                    <!-- Option 2: PHP Native OOP -->
                    <div class="preset-card">
                        <div>
                            <div class="preset-header">
                                <div class="preset-icon" style="background:#B9D7FF;"><i class="bi bi-boxes"></i></div>
                                <div>
                                    <h3 class="preset-title">PHP Native OOP</h3>
                                    <span class="neo-tag" style="background:#dbeafe;color:#2563EB;">Modular & Clean</span>
                                </div>
                            </div>
                            <p class="preset-desc">Template Object-Oriented Programming dengan PSR-4 autoloader internal, pemisahan class model database PDO, dan enkapsulasi modular siap dikembangkan.</p>
                        </div>
                        <button class="neo-btn neo-btn-primary" onclick="triggerScaffoldModal('php_oop', 'PHP Native OOP')">
                            <i class="bi bi-plus-circle-fill"></i> Scaffold OOP Project
                        </button>
                    </div>

                    <!-- Option 3: Pure PHP -->
                    <div class="preset-card">
                        <div>
                            <div class="preset-header">
                                <div class="preset-icon" style="background:#C8F169;"><i class="bi bi-filetype-php"></i></div>
                                <div>
                                    <h3 class="preset-title">Pure PHP (Single Script)</h3>
                                    <span class="neo-tag" style="background:#e9ebf5;color:#4F5D95;">Lightweight</span>
                                </div>
                            </div>
                            <p class="preset-desc">Template PHP Native bersih tanpa kompleksitas class atau router. Cocok untuk project sederhana, script utilitas, atau prototype cepat.</p>
                        </div>
                        <button class="neo-btn neo-btn-primary" onclick="triggerScaffoldModal('php_pure', 'Pure PHP')">
                            <i class="bi bi-plus-circle-fill"></i> Scaffold Pure PHP
                        </button>
                    </div>

                    <!-- Option 4: Laravel -->
                    <div class="preset-card">
                        <div>
                            <div class="preset-header">
                                <div class="preset-icon" style="background:#ffe5e3;"><i class="bi bi-fire" style="color:#FF2D20;"></i></div>
                                <div>
                                    <h3 class="preset-title">Laravel Framework</h3>
                                    <span class="neo-tag" style="background:#ffe5e3;color:#FF2D20;">Composer</span>
                                </div>
                            </div>
                            <p class="preset-desc">Framework PHP terpopuler di dunia via <code>composer create-project laravel/laravel</code>. Dilengkapi fitur routing, Eloquent ORM, dan Artisan CLI.</p>
                        </div>
                        <button class="neo-btn neo-btn-primary" onclick="triggerScaffoldModal('laravel', 'Laravel')">
                            <i class="bi bi-download"></i> Install Laravel
                        </button>
                    </div>

                    <!-- Option 5: CodeIgniter 4 -->
                    <div class="preset-card">
                        <div>
                            <div class="preset-header">
                                <div class="preset-icon" style="background:#ffede8;"><i class="bi bi-flame" style="color:#EF4223;"></i></div>
                                <div>
                                    <h3 class="preset-title">CodeIgniter 4</h3>
                                    <span class="neo-tag" style="background:#ffede8;color:#EF4223;">Composer</span>
                                </div>
                            </div>
                            <p class="preset-desc">Framework PHP yang cepat, ringan, dan tangguh via <code>composer create-project codeigniter4/appstarter</code>. Sangat bersahabat dengan Laragon.</p>
                        </div>
                        <button class="neo-btn neo-btn-primary" onclick="triggerScaffoldModal('codeigniter', 'CodeIgniter 4')">
                            <i class="bi bi-download"></i> Install CodeIgniter 4
                        </button>
                    </div>

                    <!-- Option 6: Vue.js (Vite) -->
                    <div class="preset-card">
                        <div>
                            <div class="preset-header">
                                <div class="preset-icon" style="background:#e2f7ed;"><i class="bi bi-vimeo" style="color:#42b883;"></i></div>
                                <div>
                                    <h3 class="preset-title">Vue.js (Vite Template)</h3>
                                    <span class="neo-tag" style="background:#e2f7ed;color:#42b883;">Frontend / NPM</span>
                                </div>
                            </div>
                            <p class="preset-desc">Scaffold Vue 3 modern berbasis Vite bundler melalui <code>npm create vite@latest ... --template vue</code>. Instan, reaktif, dan performa tinggi.</p>
                        </div>
                        <button class="neo-btn neo-btn-primary" onclick="triggerScaffoldModal('vue', 'Vue.js')">
                            <i class="bi bi-download"></i> Create Vue.js App
                        </button>
                    </div>

                    <!-- Option 7: React (Vite) -->
                    <div class="preset-card">
                        <div>
                            <div class="preset-header">
                                <div class="preset-icon" style="background:#def3fa;"><i class="bi bi-atom" style="color:#087ea4;"></i></div>
                                <div>
                                    <h3 class="preset-title">React (Vite Template)</h3>
                                    <span class="neo-tag" style="background:#def3fa;color:#087ea4;">Frontend / NPM</span>
                                </div>
                            </div>
                            <p class="preset-desc">Scaffold aplikasi React modern berbasis Vite bundler melalui <code>npm create vite@latest ... --template react</code>.</p>
                        </div>
                        <button class="neo-btn neo-btn-primary" onclick="triggerScaffoldModal('react', 'React')">
                            <i class="bi bi-download"></i> Create React App
                        </button>
                    </div>

                    <!-- Option 8: HTML5 Starter -->
                    <div class="preset-card">
                        <div>
                            <div class="preset-header">
                                <div class="preset-icon" style="background:#feeae5;"><i class="bi bi-filetype-html" style="color:#E34F26;"></i></div>
                                <div>
                                    <h3 class="preset-title">Modern HTML5 Starter</h3>
                                    <span class="neo-tag" style="background:#feeae5;color:#E34F26;">Static Web</span>
                                </div>
                            </div>
                            <p class="preset-desc">Template website statis modern dengan boilerplate CSS responsif, struktur JavaScript bersih, dan metadata HTML5 lengkap.</p>
                        </div>
                        <button class="neo-btn neo-btn-primary" onclick="triggerScaffoldModal('html_starter', 'HTML5 Starter')">
                            <i class="bi bi-plus-circle-fill"></i> Create HTML5 Project
                        </button>
                    </div>
                </div>

            <?php elseif ($page === 'runtimes'): ?>
                <!-- ================= PAGE 4: RUNTIME & VERSIONS ================= -->
                <div class="page-header">
                    <div>
                        <h2>Runtime Manager & Version Switcher</h2>
                        <p>Ganti versi PHP & Node.js yang aktif di Laragon, atau download versi baru langsung ke disk server.</p>
                    </div>
                </div>

                <!-- PHP Versions Section -->
                <div class="neo-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <div>
                            <h3>🐘 PHP Runtime Manager</h3>
                            <p style="font-size:12px;color:var(--muted);">Versi aktif di konfigurasi Laragon: <b><?= h($activePhpVer) ?></b></p>
                        </div>
                        <button class="neo-btn neo-btn-sm neo-btn-primary" onclick="openDownloadModal('php')">
                            <i class="bi bi-cloud-arrow-down-fill"></i> Download PHP Version
                        </button>
                    </div>

                    <?php foreach ($installedPhp as $pVer): 
                        $isActive = ($pVer === $activePhpVer);
                    ?>
                        <div class="runtime-version-item">
                            <div>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <strong style="font-size:15px;"><?= h($pVer) ?></strong>
                                    <?php if ($isActive): ?>
                                        <span class="neo-tag" style="background:#C8F169;">ACTIVE IN LARAGON</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:11px;color:var(--muted);margin-top:4px;">Path: C:\laragon\bin\php\<?= h($pVer) ?></div>
                            </div>
                            <div>
                                <?php if (!$isActive): ?>
                                    <button class="neo-btn neo-btn-sm neo-btn-secondary" onclick="switchVersion('php', '<?= h($pVer) ?>')">
                                        <i class="bi bi-arrow-repeat"></i> Set Sebagai Versi Aktif
                                    </button>
                                <?php else: ?>
                                    <span style="font-size:12px;font-weight:700;color:#10b981;"><i class="bi bi-check-circle-fill"></i> Sedang Digunakan</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Node.js Versions Section -->
                <div class="neo-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                        <div>
                            <h3>🟢 Node.js Runtime Manager</h3>
                            <p style="font-size:12px;color:var(--muted);">Versi aktif di konfigurasi Laragon: <b><?= h($activeNodeVer) ?></b></p>
                        </div>
                        <button class="neo-btn neo-btn-sm neo-btn-primary" onclick="openDownloadModal('nodejs')">
                            <i class="bi bi-cloud-arrow-down-fill"></i> Download Node.js Version
                        </button>
                    </div>

                    <?php foreach ($installedNode as $nVer): 
                        $isActive = ($nVer === $activeNodeVer);
                    ?>
                        <div class="runtime-version-item">
                            <div>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <strong style="font-size:15px;"><?= h($nVer) ?></strong>
                                    <?php if ($isActive): ?>
                                        <span class="neo-tag" style="background:#C8F169;">ACTIVE IN LARAGON</span>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size:11px;color:var(--muted);margin-top:4px;">Path: C:\laragon\bin\nodejs\<?= h($nVer) ?></div>
                            </div>
                            <div>
                                <?php if (!$isActive): ?>
                                    <button class="neo-btn neo-btn-sm neo-btn-secondary" onclick="switchVersion('nodejs', '<?= h($nVer) ?>')">
                                        <i class="bi bi-arrow-repeat"></i> Set Sebagai Versi Aktif
                                    </button>
                                <?php else: ?>
                                    <span style="font-size:12px;font-weight:700;color:#10b981;"><i class="bi bi-check-circle-fill"></i> Sedang Digunakan</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

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
                <button class="neo-btn neo-btn-sm neo-btn-primary" onclick="saveCurrentCode()">
                    <i class="bi bi-floppy2-fill"></i> Save Changes
                </button>
                <button class="neo-btn neo-btn-sm" onclick="closeCodeModal()">
                    <i class="bi bi-x-lg"></i> Close
                </button>
            </div>
        </div>
        <div class="modal-body">
            <div style="display:flex;justify-content:space-between;margin-bottom:8px;font-size:12px;color:var(--muted);">
                <span id="modal-filepath">path</span>
                <span id="modal-filesize">0 KB</span>
            </div>
            <textarea class="code-textarea" id="code-editor-textarea" spellcheck="false"></textarea>
        </div>
    </div>
</div>

<script>
    // 1. Clock Widget
    function updateClock() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString('id-ID', { hour12: false });
        const dateStr = now.toLocaleDateString('id-ID', { weekday: 'short', day: 'numeric', month: 'short' });
        const el = document.getElementById('clock-text');
        if (el) el.textContent = `${dateStr} · ${timeStr}`;
    }
    setInterval(updateClock, 1000);
    updateClock();

    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
    }

    // 2. D3.js Workspace Graph
    function loadD3Graph() {
        const container = document.getElementById('d3-container');
        if (!container) return;
        container.innerHTML = '<div style="padding:40px;text-align:center;color:#686868;">Memuat Visualisasi D3.js...</div>';

        fetch('index.php?api=graph_data')
            .then(res => res.json())
            .then(data => {
                container.innerHTML = '';
                const width = container.clientWidth || 800;
                const height = 320;

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
                    'php-mvc': '#8A2BE2',
                    'php-pkg': '#4F5D95',
                    file: '#C8F169'
                };

                const simulation = d3.forceSimulation(data.nodes)
                    .force('link', d3.forceLink(data.links).id(d => d.id).distance(65))
                    .force('charge', d3.forceManyBody().strength(-110))
                    .force('center', d3.forceCenter(width / 2, height / 2))
                    .force('collision', d3.forceCollide().radius(20));

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
                        .on('start', (e, d) => { if (!e.active) simulation.alphaTarget(0.3).restart(); d.fx = d.x; d.fy = d.y; })
                        .on('drag', (e, d) => { d.fx = e.x; d.fy = e.y; })
                        .on('end', (e, d) => { if (!e.active) simulation.alphaTarget(0); d.fx = null; d.fy = null; })
                    );

                node.append('circle')
                    .attr('r', d => d.type === 'root' ? 14 : 9)
                    .attr('fill', d => colorMap[d.type] || '#fff')
                    .attr('stroke', '#171717')
                    .attr('stroke-width', 2);

                node.append('text')
                    .text(d => d.name)
                    .attr('x', 12)
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
            })
            .catch(err => {
                container.innerHTML = '<div style="padding:30px;color:red;text-align:center;">Gagal memuat graph D3.js: ' + err.message + '</div>';
            });
    }

    if (document.getElementById('d3-container')) {
        document.addEventListener('DOMContentLoaded', loadD3Graph);
    }

    // 3. Workspace Search & Grid/List View
    const searchInput = document.getElementById('neo-search');
    const itemsContainer = document.getElementById('items-container');
    if (searchInput && itemsContainer) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            itemsContainer.querySelectorAll('.project-card').forEach(card => {
                const name = card.dataset.name || '';
                card.style.display = name.includes(query) ? '' : 'none';
            });
        });
    }

    function toggleLayoutView() {
        if (!itemsContainer) return;
        const btn = document.getElementById('btn-toggle-view');
        const isList = itemsContainer.classList.toggle('list-view');
        btn.innerHTML = isList ? '<i class="bi bi-grid-fill"></i> Grid View' : '<i class="bi bi-list-ul"></i> List View';
    }

    // 4. Create File / Folder Modal
    function openCreateItemModal(type = 'file') {
        const isFile = type === 'file';
        Swal.fire({
            title: isFile ? '📝 Buat File Baru' : '📁 Buat Folder Baru',
            html: `
                <div style="text-align:left;font-family:Inter,sans-serif;">
                    <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Lokasi Folder:</label>
                    <input id="swal-dir" class="swal2-input" style="width:100%;margin:0 0 14px 0;font-size:13px;" value="<?= h($relative) ?>" placeholder="Contoh: code">

                    <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Nama ${isFile ? 'File' : 'Folder'}:</label>
                    <input id="swal-name" class="swal2-input" style="width:100%;margin:0 0 14px 0;font-size:13px;" placeholder="${isFile ? 'index.php' : 'my-folder'}">

                    ${isFile ? `
                        <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Isi Awal (Opsional):</label>
                        <textarea id="swal-content" class="swal2-textarea" style="width:100%;margin:0;height:100px;font-family:monospace;font-size:12px;"></textarea>
                    ` : ''}
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Buat Sekarang',
            confirmButtonColor: '#171717',
            preConfirm: () => {
                const dir = document.getElementById('swal-dir').value.trim();
                const name = document.getElementById('swal-name').value.trim();
                const content = isFile ? document.getElementById('swal-content').value : '';
                if (!name) { Swal.showValidationMessage('Nama tidak boleh kosong!'); return false; }
                return { type, dir, name, content };
            }
        }).then(res => {
            if (res.isConfirmed) {
                fetch('index.php?api=create_item', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(res.value)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Berhasil!', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                });
            }
        });
    }

    // 5. Trigger Scaffolding Modal
    function triggerScaffoldModal(framework, label) {
        Swal.fire({
            title: `🚀 Scaffold ${label}`,
            html: `
                <div style="text-align:left;font-family:Inter,sans-serif;">
                    <p style="font-size:13px;color:#666;margin-bottom:14px;">Setup struktur project <b>${label}</b> ke direktori workspace.</p>
                    <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Target Direktori:</label>
                    <input id="swal-scaffold-dir" class="swal2-input" style="width:100%;margin:0 0 14px 0;font-size:13px;" value="code" placeholder="code">

                    <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Nama Folder Project:</label>
                    <input id="swal-scaffold-name" class="swal2-input" style="width:100%;margin:0;font-size:13px;" placeholder="my-app">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Mulai Buat Project',
            confirmButtonColor: '#171717',
            preConfirm: () => {
                const dir = document.getElementById('swal-scaffold-dir').value.trim();
                const name = document.getElementById('swal-scaffold-name').value.trim();
                if (!name) { Swal.showValidationMessage('Nama project harus diisi!'); return false; }
                return { framework, dir, name };
            }
        }).then(res => {
            if (res.isConfirmed) {
                Swal.fire({
                    title: 'Memproses Scaffolding...',
                    text: 'Sedang mengeksekusi template atau perintah package manager...',
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
                            title: 'Project Siap!',
                            html: `<p>${data.message}</p><pre style="background:#171717;color:#fff;padding:10px;border-radius:6px;font-size:11px;text-align:left;max-height:160px;overflow:auto;">${data.output || 'Selesai.'}</pre>`,
                            confirmButtonColor: '#171717'
                        }).then(() => {
                            window.location.href = `index.php?page=workspace&dir=${encodeURIComponent(res.value.dir || '')}`;
                        });
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                })
                .catch(err => Swal.fire('Error', err.message, 'error'));
            }
        });
    }

    // 6. Source Viewer & Editor
    let activeFilePath = '';
    function viewSourceFile(filePath) {
        Swal.fire({ title: 'Membaca File...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
        fetch('index.php?api=read_source&file=' + encodeURIComponent(filePath))
            .then(r => r.json())
            .then(data => {
                Swal.close();
                if (!data.success) { Swal.fire('Peringatan', data.message, 'warning'); return; }
                activeFilePath = data.path;
                document.getElementById('modal-filename').textContent = data.filename;
                document.getElementById('modal-filepath').textContent = data.path;
                document.getElementById('modal-filesize').textContent = (data.size / 1024).toFixed(1) + ' KB';
                document.getElementById('code-editor-textarea').value = data.content;
                document.getElementById('code-modal').classList.add('active');
            });
    }

    function closeCodeModal() { document.getElementById('code-modal').classList.remove('active'); }

    function saveCurrentCode() {
        if (!activeFilePath) return;
        fetch('index.php?api=save_file', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ file: activeFilePath, content: document.getElementById('code-editor-textarea').value })
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                Swal.fire({ icon: 'success', title: 'Tersimpan!', text: data.message, timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire('Gagal', data.message, 'error');
            }
        });
    }

    function confirmDeleteItem(itemPath, type) {
        Swal.fire({
            title: `Hapus ${type}?`,
            text: `Yakin ingin menghapus permanent '${itemPath}'?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            confirmButtonText: 'Ya, Hapus'
        }).then(res => {
            if (res.isConfirmed) {
                fetch('index.php?api=delete_item', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ path: itemPath })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Dihapus', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                });
            }
        });
    }

    // 7. Runtime Switcher & Downloader
    function switchVersion(type, version) {
        Swal.fire({
            title: `Ganti ke ${version}?`,
            text: `Konfigurasi Laragon akan diperbarui untuk menggunakan versi ${version}.`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Terapkan Versi',
            confirmButtonColor: '#171717'
        }).then(res => {
            if (res.isConfirmed) {
                Swal.fire({ title: 'Menerapkan...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });
                fetch('index.php?api=switch_version', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type, version })
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Berhasil!', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                });
            }
        });
    }

    function openDownloadModal(type) {
        const isPhp = (type === 'php');
        const defaultVersions = isPhp ? [
            { label: 'PHP 8.3.14 (VS16 x64 Thread Safe)', folder: 'php-8.3.14-Win32-vs16-x64', url: 'https://windows.php.net/downloads/releases/archives/php-8.3.14-Win32-vs16-x64.zip' },
            { label: 'PHP 8.2.26 (VS16 x64 Thread Safe)', folder: 'php-8.2.26-Win32-vs16-x64', url: 'https://windows.php.net/downloads/releases/archives/php-8.2.26-Win32-vs16-x64.zip' }
        ] : [
            { label: 'Node.js v20.18.0 (x64)', folder: 'node-v20.18.0-win-x64', url: 'https://nodejs.org/dist/v20.18.0/node-v20.18.0-win-x64.zip' },
            { label: 'Node.js v22.11.0 (x64)', folder: 'node-v22.11.0-win-x64', url: 'https://nodejs.org/dist/v22.11.0/node-v22.11.0-win-x64.zip' }
        ];

        let selectOptions = defaultVersions.map(v => `<option value="${v.url}" data-folder="${v.folder}">${v.label}</option>`).join('');

        Swal.fire({
            title: `⬇️ Download ${isPhp ? 'PHP' : 'Node.js'} Runtime Baru`,
            html: `
                <div style="text-align:left;font-family:Inter,sans-serif;">
                    <p style="font-size:13px;color:#666;margin-bottom:12px;">Pilih versi siap unduh atau masukkan URL zip binary kustom Windows.</p>
                    <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Pilihan Versi Siap Unduh:</label>
                    <select id="swal-dl-preset" class="swal2-select" style="width:100%;margin:0 0 14px 0;font-size:13px;display:block;" onchange="updateDownloadFields(this)">
                        ${selectOptions}
                        <option value="custom">-- Masukkan Custom Zip URL --</option>
                    </select>

                    <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Target Folder Name (di C:\\laragon\\bin\\${isPhp ? 'php' : 'nodejs'}):</label>
                    <input id="swal-dl-folder" class="swal2-input" style="width:100%;margin:0 0 14px 0;font-size:13px;" value="${defaultVersions[0].folder}">

                    <label style="font-weight:700;font-size:13px;display:block;margin-bottom:6px;">Direct Zip Download URL:</label>
                    <input id="swal-dl-url" class="swal2-input" style="width:100%;margin:0;font-size:13px;" value="${defaultVersions[0].url}">
                </div>
            `,
            showCancelButton: true,
            confirmButtonText: 'Download & Ekstrak',
            confirmButtonColor: '#171717',
            preConfirm: () => {
                const folder = document.getElementById('swal-dl-folder').value.trim();
                const url = document.getElementById('swal-dl-url').value.trim();
                if (!folder || !url) { Swal.showValidationMessage('Nama folder dan URL wajib diisi!'); return false; }
                return { type, folder, url };
            }
        }).then(res => {
            if (res.isConfirmed) {
                Swal.fire({
                    title: 'Mendownload & Mengekstrak...',
                    html: `Sedang mengunduh file zip dari server remote ke Laragon bin.<br><small style="color:#666;">Proses ini memakan waktu beberapa detik tergantung kecepatan koneksi.</small>`,
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch('index.php?api=download_runtime', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(res.value)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Selesai!', data.message, 'success').then(() => location.reload());
                    } else {
                        Swal.fire('Gagal', data.message, 'error');
                    }
                })
                .catch(err => Swal.fire('Error', err.message, 'error'));
            }
        });
    }

    function updateDownloadFields(select) {
        if (select.value === 'custom') {
            document.getElementById('swal-dl-folder').value = '';
            document.getElementById('swal-dl-url').value = '';
        } else {
            const opt = select.selectedOptions[0];
            document.getElementById('swal-dl-folder').value = opt.getAttribute('data-folder');
            document.getElementById('swal-dl-url').value = opt.value;
        }
    }
</script>
</body>
</html>
