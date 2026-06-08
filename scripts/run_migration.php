<?php
/**
 * Browser-based migration runner
 * Admin-only — requires active admin session
 */

ini_set('session.cookie_samesite', 'Lax');
session_start();

require_once __DIR__ . '/../config/db_connect.php';

// ── Auth check — admin only ──────────────────────────────────────────────────
$role = $_SESSION['role'] ?? null;
if (!$role || !in_array($role, ['admin'])) {
    http_response_code(403);
    die(renderPage('Access Denied', '<div class="alert error">❌ You must be logged in as <strong>admin</strong> to run migrations.</div>'));
}

$user = $_SESSION['username'] ?? $_SESSION['full_name'] ?? 'Admin';

// ── Available migrations ─────────────────────────────────────────────────────
$migrationsDir = __DIR__ . '/../migrations/';
$availableMigrations = [
    'rename_violation_levels' => [
        'file'  => $migrationsDir . 'rename_violation_levels.sql',
        'title' => 'Rename Violation Levels to 1st–5th Offense',
        'desc'  => 'Renames Permitted 1/2 → 1st/2nd Offense, Warning 1/2/3 → 3rd/4th/5th Offense. Also updates the student_violation_levels ENUM and stored function.',
        'danger' => false,
        'extra_function' => "CREATE FUNCTION `get_next_violation_level`(
    `current_level` VARCHAR(50),
    `total_violations` INT
) RETURNS VARCHAR(50) CHARSET utf8mb4 DETERMINISTIC READS SQL DATA
BEGIN
    DECLARE next_level VARCHAR(50);
    CASE current_level
        WHEN 'offense1' THEN SET next_level = IF(total_violations >= 2, 'offense2', 'offense1');
        WHEN 'offense2' THEN SET next_level = IF(total_violations >= 3, 'offense3', 'offense2');
        WHEN 'offense3' THEN SET next_level = IF(total_violations >= 4, 'offense4', 'offense3');
        WHEN 'offense4' THEN SET next_level = IF(total_violations >= 5, 'offense5', 'offense4');
        WHEN 'offense5' THEN SET next_level = IF(total_violations >= 6, 'disciplinary', 'offense5');
        ELSE SET next_level = 'disciplinary';
    END CASE;
    RETURN next_level;
END",
    ],
];

$migrationKey = $_POST['migration'] ?? $_GET['migration'] ?? null;
$action       = $_POST['action']    ?? $_GET['action']    ?? 'list';
$result       = null;

// ── Run migration ─────────────────────────────────────────────────────────────
if ($action === 'run' && $migrationKey && isset($availableMigrations[$migrationKey])) {
    $migration = $availableMigrations[$migrationKey];
    $sqlFile   = $migration['file'];

    if (!file_exists($sqlFile)) {
        $result = ['success' => false, 'message' => "Migration file not found: $sqlFile"];
    } else {
        $result = runMigration($conn, $sqlFile, $migrationKey, $migration['extra_function'] ?? null);
    }
}

// ── Run migration logic ───────────────────────────────────────────────────────
function runMigration($conn, $sqlFile, $key, $extraFunction = null) {
    $sql = file_get_contents($sqlFile);
    if (!$sql) {
        return ['success' => false, 'message' => 'Could not read SQL file.'];
    }

    $steps   = [];
    $errors  = [];
    $success = true;

    // Split into individual statements by semicolon, skip comments
    $lines = explode("\n", $sql);
    $stmts = [];
    $current = '';
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || strpos($trimmed, '--') === 0) continue;
        $current .= ' ' . $trimmed;
        if (substr(rtrim($current), -1) === ';') {
            $stmt = trim(rtrim(trim($current), ';'));
            if (!empty($stmt)) $stmts[] = $stmt;
            $current = '';
        }
    }
    if (!empty(trim($current))) {
        $stmt = trim(rtrim(trim($current), ';'));
        if (!empty($stmt)) $stmts[] = $stmt;
    }

    foreach ($stmts as $stmt) {
        $display = substr($stmt, 0, 120) . (strlen($stmt) > 120 ? '…' : '');

        if ($conn->query($stmt)) {
            $steps[] = ['sql' => $display, 'ok' => true];
        } else {
            $errMsg = $conn->error;
            $isWarn = stripos($errMsg, 'already exists') !== false ||
                      stripos($errMsg, 'duplicate column') !== false ||
                      stripos($errMsg, "doesn't exist") !== false ||
                      stripos($errMsg, 'Unknown column') !== false;
            $steps[] = ['sql' => $display, 'ok' => $isWarn, 'warning' => $isWarn, 'error' => $errMsg];
            if (!$isWarn) {
                $errors[]  = $errMsg;
                $success   = false;
            }
        }
    }

    // Execute stored function separately (no DELIMITER needed via PHP)
    if ($extraFunction && $success) {
        $display = substr($extraFunction, 0, 120) . '…';
        if ($conn->query($extraFunction)) {
            $steps[] = ['sql' => 'CREATE FUNCTION get_next_violation_level (…)', 'ok' => true];
        } else {
            $errMsg  = $conn->error;
            $steps[] = ['sql' => 'CREATE FUNCTION get_next_violation_level (…)', 'ok' => false, 'error' => $errMsg];
            $errors[]  = $errMsg;
            $success   = false;
        }
    }

    return [
        'success' => $success,
        'steps'   => $steps,
        'errors'  => $errors,
        'message' => $success
            ? '✅ Migration completed successfully!'
            : ('❌ Migration failed: ' . implode('; ', $errors)),
    ];
}

// ── HTML helpers ──────────────────────────────────────────────────────────────
function renderPage($title, $body) {
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Migration Runner — E-OSAS</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Inter', -apple-system, sans-serif; background: #0f0f0f; color: #e5e5e5; min-height: 100vh; padding: 40px 20px; }
  .container { max-width: 860px; margin: 0 auto; }
  h1 { font-size: 1.5rem; font-weight: 700; color: #FFD700; margin-bottom: 6px; }
  .subtitle { font-size: .85rem; color: #888; margin-bottom: 32px; }
  .card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
  .card-title { font-size: 1rem; font-weight: 600; color: #fff; margin-bottom: 6px; }
  .card-desc { font-size: .82rem; color: #888; margin-bottom: 18px; line-height: 1.5; }
  .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 22px; border-radius: 8px; border: none; font-size: .88rem; font-weight: 600; cursor: pointer; text-decoration: none; transition: all .2s; }
  .btn-gold { background: #FFD700; color: #000; }
  .btn-gold:hover { background: #FFC200; transform: translateY(-1px); box-shadow: 0 4px 14px rgba(255,215,0,.3); }
  .btn-danger { background: #ef4444; color: #fff; }
  .btn-danger:hover { background: #dc2626; }
  .btn-ghost { background: #2a2a2a; color: #ccc; }
  .btn-ghost:hover { background: #333; color: #fff; }
  .alert { padding: 14px 18px; border-radius: 8px; font-size: .88rem; margin-bottom: 20px; }
  .alert.success { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.3); color: #86efac; }
  .alert.error   { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.3); color: #fca5a5; }
  .alert.warning { background: rgba(245,158,11,.12); border: 1px solid rgba(245,158,11,.3); color: #fcd34d; }
  .steps { margin-top: 20px; }
  .step { display: flex; align-items: flex-start; gap: 10px; padding: 8px 0; border-bottom: 1px solid #222; font-size: .78rem; }
  .step:last-child { border-bottom: none; }
  .step-icon { flex-shrink: 0; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 700; }
  .step-ok   { background: rgba(34,197,94,.2); color: #86efac; }
  .step-warn { background: rgba(245,158,11,.2); color: #fcd34d; }
  .step-fail { background: rgba(239,68,68,.2); color: #fca5a5; }
  .step-sql  { color: #aaa; font-family: monospace; flex: 1; word-break: break-all; }
  .step-err  { color: #fca5a5; font-size: .75rem; margin-top: 2px; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: .72rem; font-weight: 600; }
  .badge-admin { background: rgba(255,215,0,.15); color: #FFD700; }
  .meta { font-size: .78rem; color: #666; margin-bottom: 24px; }
  .back { margin-bottom: 20px; display: block; }
  form { display: inline; }
</style>
</head>
<body>
<div class="container">
  <h1>⚙ Migration Runner</h1>
  <p class="subtitle">E-OSAS Database Migration Tool — Admin Only</p>
  $body
</div>
</body>
</html>
HTML;
}

// ── Build page body ───────────────────────────────────────────────────────────
ob_start();

echo '<p class="meta">Logged in as: <span class="badge badge-admin">' . htmlspecialchars($user) . ' — ' . htmlspecialchars($role) . '</span></p>';

// Show result if just ran
if ($result !== null) {
    $alertClass = $result['success'] ? 'success' : 'error';
    echo '<div class="alert ' . $alertClass . '">' . htmlspecialchars($result['message']) . '</div>';

    if (!empty($result['steps'])) {
        echo '<div class="card"><div class="card-title">Execution Log</div><div class="steps">';
        foreach ($result['steps'] as $step) {
            $iconClass = isset($step['warning']) ? 'step-warn' : ($step['ok'] ? 'step-ok' : 'step-fail');
            $icon      = isset($step['warning']) ? '⚠' : ($step['ok'] ? '✓' : '✕');
            echo '<div class="step">';
            echo '<div class="step-icon ' . $iconClass . '">' . $icon . '</div>';
            echo '<div><div class="step-sql">' . htmlspecialchars($step['sql']) . '</div>';
            if (!empty($step['error'])) {
                echo '<div class="step-err">' . htmlspecialchars($step['error']) . '</div>';
            }
            echo '</div></div>';
        }
        echo '</div></div>';
    }

    echo '<a href="run_migration.php" class="btn btn-ghost back">← Back to migrations</a>';
} else {
    // List available migrations
    foreach ($availableMigrations as $key => $mig) {
        $fileExists = file_exists($mig['file']);
        echo '<div class="card">';
        echo '<div class="card-title">' . htmlspecialchars($mig['title']) . '</div>';
        echo '<div class="card-desc">' . htmlspecialchars($mig['desc']) . '</div>';

        if (!$fileExists) {
            echo '<div class="alert warning">⚠ Migration file not found.</div>';
        } else {
            echo '<form method="POST">';
            echo '<input type="hidden" name="migration" value="' . htmlspecialchars($key) . '">';
            echo '<input type="hidden" name="action" value="run">';
            $btnClass = ($mig['danger'] ?? false) ? 'btn-danger' : 'btn-gold';
            echo '<button type="submit" class="btn ' . $btnClass . '" onclick="return confirm(\'Run this migration? This will modify your database.\')">▶ Run Migration</button>';
            echo '</form>';
        }

        echo '</div>';
    }
}

$body = ob_get_clean();
echo renderPage('Migration Runner', $body);
