<?php
function app_db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $dir = __DIR__ . '/data';
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    $pdo = new PDO('sqlite:' . $dir . '/app.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            is_admin INTEGER NOT NULL DEFAULT 0,
            banned_until TEXT DEFAULT NULL,
            ban_forever INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS themes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            owner_user_id INTEGER NOT NULL,
            owner_name TEXT NOT NULL,
            q100 TEXT NOT NULL, a100 TEXT NOT NULL,
            q200 TEXT NOT NULL, a200 TEXT NOT NULL,
            q300 TEXT NOT NULL, a300 TEXT NOT NULL,
            q400 TEXT NOT NULL, a400 TEXT NOT NULL,
            q500 TEXT NOT NULL, a500 TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS bans (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            username TEXT NOT NULL,
            reason_theme_id INTEGER DEFAULT NULL,
            reason_theme_title TEXT DEFAULT '',
            banned_by TEXT NOT NULL,
            banned_until TEXT DEFAULT NULL,
            is_forever INTEGER NOT NULL DEFAULT 0,
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS lobbies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            host_user_id INTEGER NOT NULL,
            status TEXT NOT NULL DEFAULT 'waiting',
            selected_theme_ids TEXT NOT NULL DEFAULT '[]',
            used_questions TEXT NOT NULL DEFAULT '[]',
            current_theme_id INTEGER DEFAULT NULL,
            current_points INTEGER DEFAULT NULL,
            buzzer_open INTEGER NOT NULL DEFAULT 0,
            buzzed_member_id INTEGER DEFAULT NULL,
            answer_shown INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        );
        CREATE TABLE IF NOT EXISTS lobby_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            lobby_id INTEGER NOT NULL,
            user_id INTEGER DEFAULT NULL,
            nickname TEXT NOT NULL,
            avatar TEXT NOT NULL,
            joined_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
            score INTEGER NOT NULL DEFAULT 0
        );
    ");
    $admin = $pdo->query("SELECT id FROM users WHERE username = 'Admin'")->fetch(PDO::FETCH_ASSOC);
    if (!$admin) {
        $stmt = $pdo->prepare("INSERT INTO users(username, password_hash, is_admin) VALUES(?,?,1)");
        $stmt->execute(['Admin', password_hash('07032012', PASSWORD_DEFAULT)]);
    }
    return $pdo;
}
function current_user() {
    return $_SESSION['user'] ?? null;
}
function api_json($data) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function api_error($message, $code = 400) {
    http_response_code($code);
    api_json(['ok' => false, 'error' => $message]);
}
function ensure_logged_in() {
    $u = current_user();
    if (!$u) api_error('Нужен вход в аккаунт.', 401);
    return $u;
}
function is_user_banned(PDO $pdo, array $user): bool {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return false;
    if ((int)$row['ban_forever'] === 1) return true;
    if (!empty($row['banned_until']) && strtotime($row['banned_until']) > time()) return true;
    return false;
}
