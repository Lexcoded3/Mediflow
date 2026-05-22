<?php
//config.php
// 1. Start session FIRST
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Base paths
define('BASE_URL', '/');
define('BASE_PATH', __DIR__ . '/../');

// 3. Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 4. Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'mediflow');
define('DB_USER', 'root');
define('DB_PASS', '');

// 5. Database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 6. Include auth helpers
require_once __DIR__ . '/auth.php';

// 7. HELPER FUNCTIONS (Declared ONLY ONCE)

if (!function_exists('setFlash')) {
    function setFlash(string $type, string $msg): void {
        $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
    }
}

if (!function_exists('getFlash')) {
    function getFlash(): ?array {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $flash;
    }
}

function e($str) {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

// 8. UI HELPERS
function priorityDot(?string $priority): string {
    $colors = ['red' => 'bg-red-500', 'orange' => 'bg-orange-500', 'yellow' => 'bg-yellow-500', 'green' => 'bg-green-500'];
    $color = $colors[$priority] ?? 'bg-slate-400';
    return "<span class=\"inline-block size-2 rounded-full $color\" title=\"" . ucfirst($priority ?? 'normal') . "\"></span>";
}

function orderStatusBadge(string $status): string {
    $badges = [
        'ordered' => 'bg-slate-100 dark:bg-zink-600 text-slate-600 dark:text-zink-200',
        'collected' => 'bg-sky-100 dark:bg-sky-500/20 text-sky-600 dark:text-sky-400',
        'completed' => 'bg-green-100 dark:bg-green-500/20 text-green-600 dark:text-green-400',
    ];
    $class = $badges[$status] ?? 'bg-slate-100 dark:bg-zink-600 text-slate-600 dark:text-zink-200';
    return "<span class=\"inline-flex px-2 py-0.5 text-[10px] font-medium rounded $class\">" . ucfirst($status) . "</span>";
}

// 9. CORE SYSTEM FUNCTIONS
function gstr() {
    parse_str($_SERVER['QUERY_STRING'], $dstr);
    return $dstr;
}

function generateVisitId($conn) {
    $date = date("ymd");
    $prefix = "OPD-" . $date . "-";
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM visits WHERE visit_date = CURDATE()");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $count = ($row['cnt'] ?? 0) + 1;
    return $prefix . str_pad($count, 3, "0", STR_PAD_LEFT);
}

function generatePatientId($conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM patients");
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $count = ($row['cnt'] ?? 0) + 1;
    return "PAT-" . str_pad($count, 5, "0", STR_PAD_LEFT);
}

function getTodayToken($conn, $department_id) {
    $stmt = $conn->prepare("SELECT COALESCE(MAX(token_number), 0) + 1 as next_token FROM visits WHERE visit_date = CURDATE() AND department_id = ?");
    $stmt->bind_param("i", $department_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['next_token'];
}
?>