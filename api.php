<?php
session_start();
require __DIR__ . '/db.php';
$pdo = app_db();
$action = $_REQUEST['action'] ?? '';
$raw = file_get_contents('php://input');
$data = $raw ? (json_decode($raw, true) ?: []) : [];
if ($_POST) $data = array_merge($data, $_POST);

function user_can_delete_theme($user, $theme) {
    if (!$user) return false;
    return (int)$user['is_admin'] === 1 || (int)$user['id'] === (int)$theme['owner_user_id'];
}
function fetch_lobby(PDO $pdo, int $id) {
    $stmt = $pdo->prepare("SELECT * FROM lobbies WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
switch ($action) {
case 'me':
    api_json(['ok'=>true,'user'=>$_SESSION['user'] ?? null]);
case 'register':
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    if ($username === '' || $password === '') api_error('Заполни логин и пароль.');
    if (mb_strtolower($username) === 'admin') api_error('Этот логин зарезервирован.');
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) api_error('Такой пользователь уже существует.');
    $stmt = $pdo->prepare("INSERT INTO users(username, password_hash, is_admin) VALUES(?,?,0)");
    $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
    $_SESSION['user'] = ['id'=>(int)$pdo->lastInsertId(), 'username'=>$username, 'is_admin'=>0];
    api_json(['ok'=>true,'user'=>$_SESSION['user']]);
case 'login':
    $username = trim($data['username'] ?? '');
    $password = trim($data['password'] ?? '');
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u || !password_verify($password, $u['password_hash'])) api_error('Неверный логин или пароль.', 401);
    if ((int)$u['ban_forever'] === 1) api_error('Пользователь забанен навсегда.', 403);
    if (!empty($u['banned_until']) && strtotime($u['banned_until']) > time()) api_error('Пользователь забанен до ' . $u['banned_until'], 403);
    $_SESSION['user'] = ['id'=>(int)$u['id'], 'username'=>$u['username'], 'is_admin'=>(int)$u['is_admin']];
    api_json(['ok'=>true,'user'=>$_SESSION['user']]);
case 'logout':
    session_destroy();
    api_json(['ok'=>true]);
case 'list_themes':
    $user = $_SESSION['user'] ?? null;
    $rows = $pdo->query("SELECT * FROM themes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['can_delete'] = user_can_delete_theme($user, $row);
        $row['can_ban_owner'] = $user && (int)$user['is_admin'] === 1 && (int)$row['owner_user_id'] !== (int)$user['id'];
    }
    api_json(['ok'=>true,'themes'=>$rows]);
case 'create_theme':
    $user = ensure_logged_in();
    if (is_user_banned($pdo, $user)) api_error('Вы забанены и не можете создавать темы.', 403);
    foreach (['title','q100','a100','q200','a200','q300','a300','q400','a400','q500','a500'] as $k) {
        if (trim($data[$k] ?? '') === '') api_error('Заполни все поля темы.');
    }
    $stmt = $pdo->prepare("INSERT INTO themes(title, owner_user_id, owner_name, q100,a100,q200,a200,q300,a300,q400,a400,q500,a500) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        trim($data['title']), $user['id'], $user['username'],
        trim($data['q100']), trim($data['a100']), trim($data['q200']), trim($data['a200']),
        trim($data['q300']), trim($data['a300']), trim($data['q400']), trim($data['a400']),
        trim($data['q500']), trim($data['a500'])
    ]);
    api_json(['ok'=>true]);
case 'delete_theme':
    $user = ensure_logged_in();
    $themeId = (int)($data['theme_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM themes WHERE id = ?");
    $stmt->execute([$themeId]);
    $theme = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$theme) api_error('Тема не найдена.', 404);
    if (!user_can_delete_theme($user, $theme)) api_error('Нельзя удалить чужую тему.', 403);
    $stmt = $pdo->prepare("DELETE FROM themes WHERE id = ?");
    $stmt->execute([$themeId]);
    api_json(['ok'=>true]);
case 'create_lobby':
    $user = ensure_logged_in();
    $themeIds = $data['theme_ids'] ?? [];
    if (!is_array($themeIds) || count($themeIds) === 0) api_error('Выбери хотя бы одну тему.');
    $themeIds = array_values(array_unique(array_map('intval', $themeIds)));
    $code = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 6));
    $stmt = $pdo->prepare("INSERT INTO lobbies(code, host_user_id, selected_theme_ids) VALUES(?,?,?)");
    $stmt->execute([$code, $user['id'], json_encode($themeIds)]);
    $lobbyId = (int)$pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO lobby_members(lobby_id, user_id, nickname, avatar, score) VALUES(?,?,?,?,0)");
    $stmt->execute([$lobbyId, $user['id'], $user['username'], '👑']);
    $_SESSION['host_lobby_id'] = $lobbyId;
    api_json(['ok'=>true,'code'=>$code,'lobby_id'=>$lobbyId]);
case 'join_lobby':
    $code = strtoupper(trim($data['code'] ?? ''));
    $nickname = trim($data['nickname'] ?? '');
    $avatar = trim($data['avatar'] ?? '😀');
    if ($code === '' || $nickname === '') api_error('Заполни код и ник.');
    $stmt = $pdo->prepare("SELECT * FROM lobbies WHERE code = ?");
    $stmt->execute([$code]);
    $lobby = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$lobby) api_error('Лобби не найдено.', 404);
    $stmt = $pdo->prepare("INSERT INTO lobby_members(lobby_id, user_id, nickname, avatar, score) VALUES(?,?,?,?,0)");
    $stmt->execute([(int)$lobby['id'], ($_SESSION['user']['id'] ?? null), $nickname, $avatar]);
    $_SESSION['player_lobby_id'] = (int)$lobby['id'];
    $_SESSION['player_member_id'] = (int)$pdo->lastInsertId();
    api_json(['ok'=>true,'lobby_id'=>(int)$lobby['id'],'code'=>$code]);
case 'get_lobby_state':
    $lobbyId = (int)($data['lobby_id'] ?? ($_SESSION['host_lobby_id'] ?? $_SESSION['player_lobby_id'] ?? 0));
    if (!$lobbyId) api_error('Лобби не найдено.', 404);
    $lobby = fetch_lobby($pdo, $lobbyId);
    if (!$lobby) api_error('Лобби не найдено.', 404);
    $stmt = $pdo->prepare("SELECT * FROM lobby_members WHERE lobby_id = ? ORDER BY id ASC");
    $stmt->execute([$lobbyId]);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $themeIds = json_decode($lobby['selected_theme_ids'], true) ?: [];
    $used = json_decode($lobby['used_questions'], true) ?: [];
    $themes = [];
    if ($themeIds) {
        $in = implode(',', array_fill(0, count($themeIds), '?'));
        $stmt = $pdo->prepare("SELECT * FROM themes WHERE id IN ($in)");
        $stmt->execute($themeIds);
        $themes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    $currentQuestion = null;
    if (!empty($lobby['current_theme_id']) && !empty($lobby['current_points'])) {
        foreach ($themes as $theme) {
            if ((int)$theme['id'] === (int)$lobby['current_theme_id']) {
                $p = (int)$lobby['current_points'];
                $currentQuestion = ['theme_id'=>(int)$theme['id'],'theme_title'=>$theme['title'],'points'=>$p,'question'=>$theme['q'.$p],'answer'=>$theme['a'.$p]];
                break;
            }
        }
    }
    $buzzed = null;
    if (!empty($lobby['buzzed_member_id'])) {
        foreach ($members as $m) if ((int)$m['id'] === (int)$lobby['buzzed_member_id']) $buzzed = $m;
    }
    api_json(['ok'=>true,'lobby'=>$lobby,'members'=>$members,'themes'=>$themes,'used'=>$used,'current_question'=>$currentQuestion,'buzzed'=>$buzzed,'my_member_id'=>$_SESSION['player_member_id'] ?? null,'me'=>$_SESSION['user'] ?? null]);
case 'start_game':
    $user = ensure_logged_in();
    $lobbyId = (int)($data['lobby_id'] ?? ($_SESSION['host_lobby_id'] ?? 0));
    $lobby = fetch_lobby($pdo, $lobbyId);
    if (!$lobby || (int)$lobby['host_user_id'] !== (int)$user['id']) api_error('Только хост может начать игру.', 403);
    $stmt = $pdo->prepare("UPDATE lobbies SET status = 'playing' WHERE id = ?");
    $stmt->execute([$lobbyId]);
    api_json(['ok'=>true]);
case 'open_question':
    $user = ensure_logged_in();
    $lobbyId = (int)($data['lobby_id'] ?? ($_SESSION['host_lobby_id'] ?? 0));
    $lobby = fetch_lobby($pdo, $lobbyId);
    if (!$lobby || (int)$lobby['host_user_id'] !== (int)$user['id']) api_error('Только хост может открыть вопрос.', 403);
    $stmt = $pdo->prepare("UPDATE lobbies SET current_theme_id = ?, current_points = ?, buzzer_open = 1, buzzed_member_id = NULL, answer_shown = 0 WHERE id = ?");
    $stmt->execute([(int)$data['theme_id'], (int)$data['points'], $lobbyId]);
    api_json(['ok'=>true]);
case 'buzz':
    $lobbyId = (int)($data['lobby_id'] ?? ($_SESSION['player_lobby_id'] ?? 0));
    $memberId = (int)($_SESSION['player_member_id'] ?? 0);
    if (!$lobbyId || !$memberId) api_error('Вы не в лобби.', 403);
    $lobby = fetch_lobby($pdo, $lobbyId);
    if (!$lobby) api_error('Лобби не найдено.', 404);
    if ((int)$lobby['buzzer_open'] !== 1) api_error('Кнопка пока не активна.');
    if (!empty($lobby['buzzed_member_id'])) api_error('Кто-то уже нажал раньше.');
    $stmt = $pdo->prepare("UPDATE lobbies SET buzzed_member_id = ?, buzzer_open = 0 WHERE id = ? AND buzzed_member_id IS NULL");
    $stmt->execute([$memberId, $lobbyId]);
    api_json(['ok'=>true]);
case 'show_answer':
    $user = ensure_logged_in();
    $lobbyId = (int)($data['lobby_id'] ?? ($_SESSION['host_lobby_id'] ?? 0));
    $lobby = fetch_lobby($pdo, $lobbyId);
    if (!$lobby || (int)$lobby['host_user_id'] !== (int)$user['id']) api_error('Нет доступа.', 403);
    $stmt = $pdo->prepare("UPDATE lobbies SET answer_shown = 1 WHERE id = ?");
    $stmt->execute([$lobbyId]);
    api_json(['ok'=>true]);
case 'mark_correct':
    $user = ensure_logged_in();
    $lobbyId = (int)($data['lobby_id'] ?? ($_SESSION['host_lobby_id'] ?? 0));
    $lobby = fetch_lobby($pdo, $lobbyId);
    if (!$lobby || (int)$lobby['host_user_id'] !== (int)$user['id']) api_error('Нет доступа.', 403);
    if (empty($lobby['buzzed_member_id']) || empty($lobby['current_points'])) api_error('Нет активного ответа.');
    $points = (int)$lobby['current_points'];
    $stmt = $pdo->prepare("UPDATE lobby_members SET score = score + ? WHERE id = ?");
    $stmt->execute([$points, (int)$lobby['buzzed_member_id']]);
    $used = json_decode($lobby['used_questions'], true) ?: [];
    $used[] = $lobby['current_theme_id'] . '_' . $lobby['current_points'];
    $used = array_values(array_unique($used));
    $stmt = $pdo->prepare("UPDATE lobbies SET used_questions = ?, current_theme_id = NULL, current_points = NULL, buzzer_open = 0, buzzed_member_id = NULL, answer_shown = 1 WHERE id = ?");
    $stmt->execute([json_encode($used), $lobbyId]);
    api_json(['ok'=>true]);
case 'mark_wrong':
    $user = ensure_logged_in();
    $lobbyId = (int)($data['lobby_id'] ?? ($_SESSION['host_lobby_id'] ?? 0));
    $lobby = fetch_lobby($pdo, $lobbyId);
    if (!$lobby || (int)$lobby['host_user_id'] !== (int)$user['id']) api_error('Нет доступа.', 403);
    if (empty($lobby['buzzed_member_id']) || empty($lobby['current_points'])) api_error('Нет активного ответа.');
    $points = (int)$lobby['current_points'];
    $stmt = $pdo->prepare("UPDATE lobby_members SET score = score - ? WHERE id = ?");
    $stmt->execute([$points, (int)$lobby['buzzed_member_id']]);
    $stmt = $pdo->prepare("UPDATE lobbies SET buzzed_member_id = NULL, buzzer_open = 1 WHERE id = ?");
    $stmt->execute([$lobbyId]);
    api_json(['ok'=>true]);
case 'close_question':
    $user = ensure_logged_in();
    $lobbyId = (int)($data['lobby_id'] ?? ($_SESSION['host_lobby_id'] ?? 0));
    $lobby = fetch_lobby($pdo, $lobbyId);
    if (!$lobby || (int)$lobby['host_user_id'] !== (int)$user['id']) api_error('Нет доступа.', 403);
    $stmt = $pdo->prepare("UPDATE lobbies SET current_theme_id = NULL, current_points = NULL, buzzer_open = 0, buzzed_member_id = NULL, answer_shown = 0 WHERE id = ?");
    $stmt->execute([$lobbyId]);
    api_json(['ok'=>true]);
case 'list_bans':
    $user = ensure_logged_in();
    if ((int)$user['is_admin'] !== 1) api_error('Только для администратора.', 403);
    $rows = $pdo->query("SELECT * FROM bans WHERE active = 1 ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    api_json(['ok'=>true,'bans'=>$rows]);
case 'ban_user':
    $user = ensure_logged_in();
    if ((int)$user['is_admin'] !== 1) api_error('Только для администратора.', 403);
    $themeId = (int)($data['theme_id'] ?? 0);
    $forever = (int)($data['forever'] ?? 0);
    $days = (int)($data['days'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM themes WHERE id = ?");
    $stmt->execute([$themeId]);
    $theme = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$theme) api_error('Тема не найдена.', 404);
    $banUntil = null;
    if (!$forever) {
        if ($days <= 0) api_error('Укажи количество дней.');
        $banUntil = date('Y-m-d H:i:s', strtotime('+' . $days . ' days'));
    }
    $stmt = $pdo->prepare("UPDATE users SET banned_until = ?, ban_forever = ? WHERE id = ?");
    $stmt->execute([$banUntil, $forever ? 1 : 0, $theme['owner_user_id']]);
    $stmt = $pdo->prepare("INSERT INTO bans(user_id, username, reason_theme_id, reason_theme_title, banned_by, banned_until, is_forever, active) VALUES(?,?,?,?,?,?,?,1)");
    $stmt->execute([$theme['owner_user_id'], $theme['owner_name'], $theme['id'], $theme['title'], $user['username'], $banUntil, $forever ? 1 : 0]);
    $stmt = $pdo->prepare("DELETE FROM themes WHERE owner_user_id = ?");
    $stmt->execute([$theme['owner_user_id']]);
    api_json(['ok'=>true]);
case 'unban_user':
    $user = ensure_logged_in();
    if ((int)$user['is_admin'] !== 1) api_error('Только для администратора.', 403);
    $banId = (int)($data['ban_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM bans WHERE id = ?");
    $stmt->execute([$banId]);
    $ban = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ban) api_error('Бан не найден.', 404);
    $stmt = $pdo->prepare("UPDATE users SET banned_until = NULL, ban_forever = 0 WHERE id = ?");
    $stmt->execute([$ban['user_id']]);
    $stmt = $pdo->prepare("UPDATE bans SET active = 0 WHERE id = ?");
    $stmt->execute([$banId]);
    api_json(['ok'=>true]);
default:
    api_error('Неизвестное действие.', 404);
}
