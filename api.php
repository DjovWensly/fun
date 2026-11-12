<?php
declare(strict_types=1);

/**
 * JSON API used by the browser. Every request is a POST with a JSON body: {"action": "...", ...}.
 * Players are identified by a random token (kept in the browser's localStorage), not by login.
 *
 * Realtime is done with polling ("state" action) because free hosting has no WebSockets.
 * Each room has a version number; when it hasn't changed the server answers with a tiny
 * {"same": true} payload so polling stays cheap.
 */

// Warnings printed by PHP (XAMPP shows them by default) would break the JSON: log them instead.
ini_set('display_errors', '0');
ob_start();

require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex');

const GP_ONLINE_SECONDS = 20;   // a player not seen for this long is shown as offline
// Allowed pen colours and sizes in Dessine et devine (must match assets/js/games/drawguess.js).
const GP_DRAW_COLORS = ['#1c1915', '#ffffff', '#c4381d', '#1f4592', '#2f7d4f', '#e3a21a', '#7b4a22', '#e85d9a', '#8a8a8a', '#7a3fc0'];
const GP_DRAW_WIDTHS = [4, 8, 16, 32];
// Spectators are stored with the players but on seats from 100 up, so games never see them.
const GP_SPECTATOR_SEAT = 100;
const GP_BOT_DELAY = 1;         // seconds between bot moves so humans can follow the game

function out(array $data, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean(); // drop any stray output (PHP notices, BOM, stray spaces)
    }
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$in = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($in)) {
    $in = $_POST;
}
$action = (string)($in['action'] ?? '');

try {
    switch ($action) {
        case 'games':
            $list = [];
            foreach (gp_games() as $id => $engine) {
                $list[$id] = $engine->meta();
            }
            out(['ok' => true, 'games' => $list]);
            // no break (out() exits)
        case 'prompts': // used by the one-phone mode (local.php)
            out(['ok' => true, 'prompts' => gp_prompts_load()]);
        case 'create':
            out(actionCreate($in));
        case 'join':
            out(actionJoin($in));
        case 'watch':
            out(actionWatch($in));
        case 'state':
            out(actionState($in));
        case 'emote':
            out(actionEmote($in));
        case 'draw':
            out(actionDraw($in));
        case 'start':
        case 'add_bot':
        case 'kick':
        case 'leave':
        case 'move':
        case 'lobby':
        case 'switch_game':
        case 'sit':
            out(actionMutate($action, $in));
        default:
            throw new GameError('unknown_action');
    }
} catch (GameError $e) {
    out(['ok' => false, 'error' => $e->getMessage()]);
} catch (SetupError $e) {
    out(['ok' => false, 'error' => $e->key, 'help' => gp_setup_help($e->key), 'detail' => $e->getMessage()], 500);
} catch (Throwable $e) {
    if ($e instanceof PDOException && ($s = gp_setup_error($e))) {
        out(['ok' => false, 'error' => $s->key, 'help' => gp_setup_help($s->key), 'detail' => $e->getMessage()], 500);
    }
    try {
        if (gp_db()->inTransaction()) {
            gp_db()->rollBack();
        }
    } catch (Throwable $ignored) {
    }
    error_log('[games api] ' . $e);
    out(['ok' => false, 'error' => 'server_error'], 500);
}

// ---------------------------------------------------------------------------------------------

function cleanName($name): string
{
    $name = trim(preg_replace('/[\x00-\x1F\x7F]+/u', '', (string)$name));
    $name = preg_replace('/\s+/u', ' ', $name);
    $name = mb_substr($name, 0, 20);
    if ($name === '') {
        throw new GameError('name_required');
    }
    return $name;
}

function cleanCode($code): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$code));
}

function newCode(): string
{
    // No 0/O, 1/I/L: codes are easy to read aloud and type on a phone.
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $db = gp_db();
    for ($try = 0; $try < 20; $try++) {
        $code = '';
        for ($i = 0; $i < 5; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $st = $db->prepare('SELECT 1 FROM gp_rooms WHERE code = ?');
        $st->execute([$code]);
        if (!$st->fetchColumn()) {
            return $code;
        }
    }
    throw new RuntimeException('could not generate room code');
}

function newToken(): string
{
    return bin2hex(random_bytes(16));
}

function botName(array $players): string
{
    $names = ['Robo Jak', 'Ti Bot', 'Bot Zoe', 'Bot Max', 'Bot Lili', 'Bot Toto'];
    $used = array_column($players, 'name');
    foreach ($names as $n) {
        if (!in_array($n, $used, true)) {
            return $n;
        }
    }
    return 'Bot ' . random_int(10, 99);
}

function loadRoom(string $code, bool $lock): array
{
    $st = gp_db()->prepare('SELECT * FROM gp_rooms WHERE code = ?' . ($lock ? ' FOR UPDATE' : ''));
    $st->execute([$code]);
    $room = $st->fetch();
    if (!$room) {
        throw new GameError('room_not_found');
    }
    $room['options'] = json_decode((string)$room['options'], true) ?: [];
    $room['state'] = $room['state'] !== null ? json_decode($room['state'], true) : null;
    return $room;
}


/** Players at the table (seat => row). Pass $spectators = true to get the spectators instead. */
function loadPlayers(int $roomId, bool $spectators = false): array
{
    $st = gp_db()->prepare('SELECT * FROM gp_players WHERE room_id = ? AND seat ' . ($spectators ? '>=' : '<') . ' ' . GP_SPECTATOR_SEAT . ' ORDER BY seat');
    $st->execute([$roomId]);
    $out = [];
    foreach ($st->fetchAll() as $p) {
        $p['seat'] = (int)$p['seat'];
        $p['is_bot'] = (bool)$p['is_bot'];
        $p['is_host'] = (bool)$p['is_host'];
        $out[$p['seat']] = $p;
    }
    return $out;
}

function findMe(array $players, string $token): ?array
{
    if ($token === '') {
        return null;
    }
    foreach ($players as $p) {
        if (!$p['is_bot'] && hash_equals($p['token'], $token)) {
            return $p;
        }
    }
    return null;
}

function freeSeat(array $players): int
{
    for ($s = 0; ; $s++) {
        if (!isset($players[$s])) {
            return $s;
        }
    }
}

function insertPlayer(int $roomId, string $name, int $seat, bool $bot, bool $host): string
{
    $token = newToken();
    $now = gp_now();
    gp_db()->prepare(
        'INSERT INTO gp_players (room_id, token, name, seat, is_bot, is_host, last_seen, joined_at) VALUES (?,?,?,?,?,?,?,?)'
    )->execute([$roomId, $token, $name, $seat, $bot ? 1 : 0, $host ? 1 : 0, $now, $now]);
    return $token;
}

function saveRoom(array $room): void
{
    // Results announced by the game (a match won, a word found…) go to the evening history.
    if (is_array($room['state']) && !empty($room['state']['_events'])) {
        saveHistory($room, $room['state']['_events']);
        unset($room['state']['_events']);
    }
    gp_db()->prepare('UPDATE gp_rooms SET status = ?, state = ?, version = version + 1, updated_at = ? WHERE id = ?')
        ->execute([
            $room['status'],
            $room['state'] === null ? null : json_encode($room['state'], JSON_UNESCAPED_UNICODE),
            gp_now(),
            $room['id'],
        ]);
}

function isBotSeat(array $players, ?int $seat): bool
{
    return $seat !== null && isset($players[$seat]) && $players[$seat]['is_bot'];
}

/** Start a game: build its state from the seats currently in the room. */
function startGame(array &$room, array $players): void
{
    $engine = gp_engine($room['game']);
    $meta = $engine->meta();
    if (count($players) < $meta['min']) {
        throw new GameError('not_enough_players');
    }
    $room['state'] = $engine->init(array_keys($players), $room['options']);
    $room['status'] = 'playing';
}

// --- actions ---------------------------------------------------------------------------------

function actionCreate(array $in): array
{
    $engine = gp_engine((string)($in['game'] ?? ''));
    $game = (string)$in['game'];
    if (!gp_game_enabled($game)) {
        throw new GameError('game_disabled');
    }
    $meta = $engine->meta();
    $name = cleanName($in['name'] ?? '');
    $options = $engine->options(is_array($in['options'] ?? null) ? $in['options'] : []);
    $bots = $meta['bots'] ? max(0, min($meta['max'] - 1, (int)($in['bots'] ?? 0))) : 0;

    $db = gp_db();
    // Housekeeping: forget old rooms (players are deleted by the foreign key cascade).
    $ttl = max(1, (int)gp_setting('room_ttl_hours'));
    $db->prepare('DELETE FROM gp_rooms WHERE updated_at < ?')->execute([gmdate('Y-m-d H:i:s', time() - $ttl * 3600)]);

    ensureHistoryTable(); // before the transaction (see saveHistory)
    $db->beginTransaction();
    $code = newCode();
    $now = gp_now();
    $db->prepare('INSERT INTO gp_rooms (code, game, status, options, created_at, updated_at) VALUES (?,?,?,?,?,?)')
        ->execute([$code, $game, 'lobby', json_encode($options, JSON_UNESCAPED_UNICODE), $now, $now]);
    $roomId = (int)$db->lastInsertId();
    $token = insertPlayer($roomId, $name, 0, false, true);

    // Solo: games that can be played alone (Mot mystère) start right away.
    $soloStart = !empty($in['solo']) && $meta['min'] <= 1;
    if ($bots > 0 || $soloStart) {
        // Solo mode: fill the table with bots (if any) and start right away.
        for ($i = 1; $i <= $bots; $i++) {
            insertPlayer($roomId, botName(loadPlayers($roomId)), $i, true, false);
        }
        $room = loadRoom($code, true);
        startGame($room, loadPlayers($roomId));
        saveRoom($room);
    }
    $db->commit();
    return ['ok' => true, 'code' => $code, 'token' => $token];
}

function actionJoin(array $in): array
{
    $code = cleanCode($in['code'] ?? '');
    $name = cleanName($in['name'] ?? '');
    $db = gp_db();
    $db->beginTransaction();
    $room = loadRoom($code, true);
    $engine = gp_engine($room['game']);
    $meta = $engine->meta();
    $players = loadPlayers((int)$room['id']);

    if ($room['status'] === 'closed') {
        throw new GameError('room_closed');
    }
    if ($room['status'] === 'playing' && !$meta['joinMidGame']) {
        throw new GameError('game_started');
    }
    if (count($players) >= $meta['max']) {
        throw new GameError('room_full');
    }
    $seat = freeSeat($players);
    $token = insertPlayer((int)$room['id'], $name, $seat, false, false);
    if ($room['status'] === 'playing') {
        $engine->addSeat($room['state'], $seat);
    }
    saveRoom($room);
    $db->commit();
    return ['ok' => true, 'code' => $code, 'token' => $token];
}

/** Sit next to the table as a spectator: always allowed, even when the game has started or the table is full. */
function actionWatch(array $in): array
{
    $code = cleanCode($in['code'] ?? '');
    $name = cleanName($in['name'] ?? '');
    $db = gp_db();
    $db->beginTransaction();
    $room = loadRoom($code, true);
    if ($room['status'] === 'closed') {
        throw new GameError('room_closed');
    }
    $spectators = loadPlayers((int)$room['id'], true);
    if (count($spectators) >= 30) {
        throw new GameError('room_full');
    }
    $seat = GP_SPECTATOR_SEAT;
    while (isset($spectators[$seat])) {
        $seat++;
    }
    $token = insertPlayer((int)$room['id'], $name, $seat, false, false);
    saveRoom($room);
    $db->commit();
    return ['ok' => true, 'code' => $code, 'token' => $token];
}

/** Polling endpoint. Also advances bots one move at a time so their moves are visible. */
function actionState(array $in): array
{
    $code = cleanCode($in['code'] ?? '');
    $token = (string)($in['token'] ?? '');
    $since = (int)($in['v'] ?? 0);
    $db = gp_db();

    if ($token !== '') {
        $db->prepare('UPDATE gp_players SET last_seen = ? WHERE token = ?')->execute([gp_now(), $token]);
    }

    $room = loadRoom($code, false);
    $players = loadPlayers((int)$room['id']);

    if ($room['status'] === 'playing' && strtotime($room['updated_at'] . ' UTC') <= time() - GP_BOT_DELAY) {
        $engine = gp_engine($room['game']);
        if (isBotSeat($players, $engine->currentSeat($room['state']))) {
            $db->beginTransaction();
            $locked = loadRoom($code, true);
            $seat = $engine->currentSeat($locked['state']);
            if ((int)$locked['version'] === (int)$room['version'] && isBotSeat($players, $seat)) {
                $engine->botMove($locked['state'], $seat);
                saveRoom($locked);
                $db->commit();
                $room = loadRoom($code, false);
            } else {
                $db->rollBack();
            }
        }
    }

    // Games with a clock (Dessine et devine) move on by themselves when time runs out.
    if ($room['status'] === 'playing' && gp_engine($room['game'])->tickDue($room['state'])) {
        $engine = gp_engine($room['game']);
        $db->beginTransaction();
        $locked = loadRoom($code, true);
        if ($locked['status'] === 'playing' && $engine->tick($locked['state'])) {
            saveRoom($locked);
            $db->commit();
            $room = loadRoom($code, false);
        } else {
            $db->rollBack();
        }
    }

    $presence = [];
    foreach ($players as $p) {
        $presence[$p['seat']] = isOnline($p);
    }
    $extra = $room['game'] === 'drawguess' && $room['status'] === 'playing'
        ? ['strokes' => recentStrokes($room, isset($in['st']) ? (int)$in['st'] : -1, (int)($in['sk'] ?? 0))]
        : [];
    $emotes = $extra + recentEmotes((int)$room['id'], isset($in['e']) ? (int)$in['e'] : -1);
    if ($since > 0 && (int)$room['version'] === $since) {
        return ['ok' => true, 'same' => true, 'presence' => $presence] + $emotes;
    }
    return snapshot($room, $players, findMe($players, $token) ?? findMe(loadPlayers((int)$room['id'], true), $token)) + $emotes;
}

// --- reactions (emotes) ----------------------------------------------------------------------

function ensureEmoteTable(): void
{
    gp_db()->exec("CREATE TABLE IF NOT EXISTS gp_emotes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        room_id INT UNSIGNED NOT NULL,
        seat TINYINT UNSIGNED NOT NULL,
        emote VARCHAR(20) NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_room (room_id, id),
        CONSTRAINT fk_emotes_room FOREIGN KEY (room_id) REFERENCES gp_rooms (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Reactions newer than $after. With $after = -1 (the page's first poll) nothing is replayed:
 * the client only learns where the list currently ends.
 */
function recentEmotes(int $roomId, int $after): array
{
    try {
        if ($after < 0) {
            $st = gp_db()->prepare('SELECT COALESCE(MAX(id), 0) FROM gp_emotes WHERE room_id = ?');
            $st->execute([$roomId]);
            return ['emotes' => [], 'emoteCursor' => (int)$st->fetchColumn()];
        }
        $st = gp_db()->prepare('SELECT id, seat, emote FROM gp_emotes WHERE room_id = ? AND id > ? AND created_at >= ? ORDER BY id LIMIT 20');
        $st->execute([$roomId, $after, gmdate('Y-m-d H:i:s', time() - 15)]);
        $list = [];
        $cursor = $after;
        foreach ($st->fetchAll() as $r) {
            $list[] = ['id' => (int)$r['id'], 'seat' => (int)$r['seat'], 'emote' => $r['emote']];
            $cursor = max($cursor, (int)$r['id']);
        }
        return ['emotes' => $list, 'emoteCursor' => $cursor];
    } catch (PDOException $e) {
        return ['emotes' => [], 'emoteCursor' => $after]; // table not created yet
    }
}

function actionEmote(array $in): array
{
    $emote = (string)($in['emote'] ?? '');
    if (!gp_setting('reactions_on')) {
        throw new GameError('reactions_off');
    }
    if (!in_array($emote, gp_reaction_ids(), true)) {
        throw new GameError('invalid_move');
    }
    $room = loadRoom(cleanCode($in['code'] ?? ''), false);
    $players = loadPlayers((int)$room['id']);
    $token = (string)($in['token'] ?? '');
    $me = findMe($players, $token) ?? findMe(loadPlayers((int)$room['id'], true), $token);
    if (!$me) {
        throw new GameError('not_in_room');
    }
    ensureEmoteTable();
    $db = gp_db();
    // One reaction per second per player is plenty, and keeps the free hosting quota safe.
    $st = $db->prepare('SELECT 1 FROM gp_emotes WHERE room_id = ? AND seat = ? AND created_at > ?');
    $st->execute([$room['id'], $me['seat'], gmdate('Y-m-d H:i:s', time() - 1)]);
    if ($st->fetchColumn()) {
        throw new GameError('too_fast');
    }
    $db->prepare('INSERT INTO gp_emotes (room_id, seat, emote, created_at) VALUES (?,?,?,?)')
        ->execute([$room['id'], $me['seat'], $emote, gp_now()]);
    $id = (int)$db->lastInsertId();
    if ($id % 20 === 0) {
        $db->prepare('DELETE FROM gp_emotes WHERE created_at < ?')->execute([gmdate('Y-m-d H:i:s', time() - 60)]);
    }
    return ['ok' => true, 'id' => $id];
}

// --- drawings (Dessine et devine) ------------------------------------------------------------
// Strokes are not part of the game state (that would resend the whole drawing on every change):
// they are appended to gp_strokes and each player fetches what is new since their last poll.


function ensureStrokeTable(): void
{
    gp_db()->exec("CREATE TABLE IF NOT EXISTS gp_strokes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        room_id INT UNSIGNED NOT NULL,
        turn INT UNSIGNED NOT NULL,
        data MEDIUMTEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_room_turn (room_id, turn, id),
        CONSTRAINT fk_strokes_room FOREIGN KEY (room_id) REFERENCES gp_rooms (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Drawing items for the current turn: all of them if the client is on another turn, else the new ones. */
function recentStrokes(array $room, int $clientTurn, int $after): array
{
    $turn = (int)($room['state']['turnNo'] ?? 0);
    if ($clientTurn !== $turn) {
        $after = 0;
    }
    $items = [];
    $cursor = $after;
    try {
        $st = gp_db()->prepare('SELECT id, data FROM gp_strokes WHERE room_id = ? AND turn = ? AND id > ? ORDER BY id LIMIT 300');
        $st->execute([$room['id'], $turn, $after]);
        foreach ($st->fetchAll() as $r) {
            foreach (json_decode($r['data'], true) ?: [] as $it) {
                $items[] = $it;
            }
            $cursor = (int)$r['id'];
        }
    } catch (PDOException $e) {
        // table not created yet: nothing drawn so far
    }
    return ['turn' => $turn, 'cursor' => $cursor, 'items' => $items, 'reset' => $clientTurn !== $turn];
}

function actionDraw(array $in): array
{
    $code = cleanCode($in['code'] ?? '');
    $room = loadRoom($code, false);
    if ($room['game'] !== 'drawguess' || $room['status'] !== 'playing') {
        throw new GameError('invalid_move');
    }
    $players = loadPlayers((int)$room['id']);
    $me = findMe($players, (string)($in['token'] ?? ''));
    $s = $room['state'];
    if (!$me || $s['phase'] !== 'drawing' || (int)$s['drawer'] !== $me['seat'] || (int)($in['turn'] ?? -1) !== (int)$s['turnNo']) {
        throw new GameError('not_drawer');
    }
    $clean = [];
    foreach (array_slice((array)($in['items'] ?? []), 0, 40) as $it) {
        if (!is_array($it)) {
            continue;
        }
        if (in_array($it['t'] ?? '', ['undo', 'clear'], true)) {
            $clean[] = ['t' => $it['t']];
            continue;
        }
        $c = strtolower((string)($it['c'] ?? ''));
        $w = (int)($it['w'] ?? 0);
        $p = array_slice((array)($it['p'] ?? []), 0, 600);
        if (!in_array($c, GP_DRAW_COLORS, true) || !in_array($w, GP_DRAW_WIDTHS, true) || count($p) < 2) {
            continue;
        }
        $pts = [];
        foreach ($p as $i => $v) {
            $pts[] = max(0, min($i % 2 ? 600 : 800, (int)$v));
        }
        if (count($pts) % 2) {
            array_pop($pts);
        }
        $clean[] = ['k' => (int)($it['k'] ?? 0), 'c' => $c, 'w' => $w, 'p' => $pts];
    }
    if (!$clean) {
        return ['ok' => true, 'id' => 0];
    }
    ensureStrokeTable();
    $db = gp_db();
    $db->prepare('INSERT INTO gp_strokes (room_id, turn, data, created_at) VALUES (?,?,?,?)')
        ->execute([$room['id'], (int)$s['turnNo'], json_encode($clean), gp_now()]);
    $id = (int)$db->lastInsertId();
    if ($id % 25 === 0) {
        // drawings of earlier turns are no longer needed
        $db->prepare('DELETE FROM gp_strokes WHERE room_id = ? AND turn < ?')->execute([$room['id'], (int)$s['turnNo']]);
    }
    return ['ok' => true, 'id' => $id];
}

// --- evening history -----------------------------------------------------------------------

function ensureHistoryTable(): void
{
    gp_db()->exec("CREATE TABLE IF NOT EXISTS gp_history (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        room_id INT UNSIGNED NOT NULL,
        game VARCHAR(20) NOT NULL,
        data TEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_room (room_id, id),
        CONSTRAINT fk_history_room FOREIGN KEY (room_id) REFERENCES gp_rooms (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/**
 * Store results with the players' names (seats change between games, names are what people remember).
 * Event: ['winners' => [seat...], 'ranking' => [[[seat...], score], ...], 'unit' => 'points'|'rounds'|..., 'draw' => bool]
 */
function saveHistory(array $room, array $events): void
{
    $players = loadPlayers((int)$room['id']);
    $names = function (array $seats) use ($players): array {
        $out = [];
        foreach ($seats as $s) {
            $out[] = $players[(int)$s]['name'] ?? '?';
        }
        return $out;
    };
    // No CREATE TABLE here: we are inside a transaction and MySQL would commit it early.
    // The table is created when a table is opened (actionCreate) and by install.sql.
    try {
        $ins = gp_db()->prepare('INSERT INTO gp_history (room_id, game, data, created_at) VALUES (?,?,?,?)');
    } catch (PDOException $e) {
        return;
    }
    foreach ($events as $e) {
        $data = [
            'winners' => $names($e['winners'] ?? []),
            'draw'    => !empty($e['draw']),
            'unit'    => $e['unit'] ?? 'points',
            'ranking' => array_map(function ($r) use ($names) {
                return [$names((array)$r[0]), $r[1]];
            }, $e['ranking'] ?? []),
            'note'    => $e['note'] ?? null,
        ];
        try {
            $ins->execute([$room['id'], $room['game'], json_encode($data, JSON_UNESCAPED_UNICODE), gp_now()]);
        } catch (PDOException $ex) {
            return; // table missing: the game goes on without history
        }
    }
}

function loadHistory(int $roomId): array
{
    try {
        $st = gp_db()->prepare('SELECT id, game, data, created_at FROM gp_history WHERE room_id = ? ORDER BY id DESC LIMIT 40');
        $st->execute([$roomId]);
        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[] = ['id' => (int)$r['id'], 'game' => $r['game'], 'at' => strtotime($r['created_at'] . ' UTC')] + (json_decode($r['data'], true) ?: []);
        }
        return $out;
    } catch (PDOException $e) {
        return []; // table not created yet: no game finished so far
    }
}

function isOnline(array $p): bool
{
    return $p['is_bot'] || strtotime($p['last_seen'] . ' UTC') >= time() - GP_ONLINE_SECONDS;
}

function snapshot(array $room, array $players, ?array $me): array
{
    $engine = gp_engine($room['game']);
    $spectators = [];
    foreach (loadPlayers((int)$room['id'], true) as $p) {
        $spectators[] = ['seat' => $p['seat'], 'name' => $p['name'], 'online' => isOnline($p)];
    }
    $isSpectator = $me !== null && $me['seat'] >= GP_SPECTATOR_SEAT;
    $list = [];
    foreach ($players as $p) {
        $list[] = [
            'seat'   => $p['seat'],
            'name'   => $p['name'],
            'bot'    => $p['is_bot'],
            'host'   => $p['is_host'],
            'online' => isOnline($p),
        ];
    }
    $mySeat = $me && !$isSpectator ? $me['seat'] : null;
    return [
        'ok'      => true,
        'room'    => [
            'code'    => $room['code'],
            'game'    => $room['game'],
            'status'  => $room['status'],
            'version' => (int)$room['version'],
            'options' => $room['options'],
            'meta'    => $engine->meta(),
        ],
        'players' => $list,
        'spectators' => $spectators,
        'you'     => $mySeat,
        'watching' => $isSpectator ? $me['seat'] : null,
        'isHost'  => $me && !$isSpectator ? $me['is_host'] : false,
        'history' => loadHistory((int)$room['id']),
        'game'    => $room['status'] === 'playing' ? $engine->view($room['state'], $mySeat) : null,
    ];
}

/** Every action that changes a room goes through here, inside a transaction with the room row locked. */
function actionMutate(string $action, array $in): array
{
    $code = cleanCode($in['code'] ?? '');
    $token = (string)($in['token'] ?? '');
    $db = gp_db();
    $db->beginTransaction();
    try {
        $room = loadRoom($code, true);
        $players = loadPlayers((int)$room['id']);
        $spectators = loadPlayers((int)$room['id'], true);
        $me = findMe($players, $token);
        $engine = gp_engine($room['game']);
        $meta = $engine->meta();

        // Spectators can only leave, or take a free seat when the game allows it.
        if (!$me) {
            $watcher = findMe($spectators, $token);
            if (!$watcher) {
                throw new GameError('not_in_room');
            }
            if ($action === 'leave') {
                $db->prepare('DELETE FROM gp_players WHERE id = ?')->execute([$watcher['id']]);
            } elseif ($action === 'sit') {
                if ($room['status'] === 'playing' && !$meta['joinMidGame']) {
                    throw new GameError('game_started');
                }
                if (count($players) >= $meta['max']) {
                    throw new GameError('room_full');
                }
                $seat = freeSeat($players);
                $db->prepare('UPDATE gp_players SET seat = ? WHERE id = ?')->execute([$seat, $watcher['id']]);
                if ($room['status'] === 'playing') {
                    $engine->addSeat($room['state'], $seat);
                }
            } else {
                throw new GameError('spectator');
            }
            saveRoom($room);
            $db->commit();
            if ($action === 'leave') {
                return ['ok' => true, 'left' => true];
            }
            $room = loadRoom($code, false);
            $players = loadPlayers((int)$room['id']);
            return snapshot($room, $players, findMe($players, $token));
        }
        $isHost = $me['is_host'];

        switch ($action) {
            case 'sit':
                throw new GameError('invalid_move');

            case 'switch_game': // same table, same players, another game
                if (!$isHost) {
                    throw new GameError('host_only');
                }
                $game = (string)($in['game'] ?? '');
                $next = gp_engine($game);
                if (!gp_game_enabled($game)) {
                    throw new GameError('game_disabled');
                }
                $nextMeta = $next->meta();
                if (!$nextMeta['bots']) {
                    foreach ($players as $seat => $p) {
                        if ($p['is_bot']) {
                            $db->prepare('DELETE FROM gp_players WHERE id = ?')->execute([$p['id']]);
                            unset($players[$seat]);
                        }
                    }
                }
                if (count($players) > $nextMeta['max']) {
                    throw new GameError('too_many_players');
                }
                $options = $next->options(is_array($in['options'] ?? null) ? $in['options'] : []);
                $db->prepare('UPDATE gp_rooms SET game = ?, options = ? WHERE id = ?')
                    ->execute([$game, json_encode($options, JSON_UNESCAPED_UNICODE), $room['id']]);
                $room['status'] = 'lobby';
                $room['state'] = null;
                break;

            case 'start':
                if (!$isHost) {
                    throw new GameError('host_only');
                }
                if ($room['status'] !== 'lobby') {
                    throw new GameError('game_started');
                }
                startGame($room, $players);
                break;

            case 'lobby': // host sends everyone back to the waiting room (to change players)
                if (!$isHost) {
                    throw new GameError('host_only');
                }
                $room['status'] = 'lobby';
                $room['state'] = null;
                break;

            case 'add_bot':
                if (!$isHost) {
                    throw new GameError('host_only');
                }
                if (!$meta['bots'] || $room['status'] !== 'lobby') {
                    throw new GameError('invalid_move');
                }
                if (count($players) >= $meta['max']) {
                    throw new GameError('room_full');
                }
                insertPlayer((int)$room['id'], botName($players), freeSeat($players), true, false);
                break;

            case 'kick':
                if (!$isHost) {
                    throw new GameError('host_only');
                }
                $seat = (int)($in['seat'] ?? -1);
                if (isset($spectators[$seat])) {
                    $db->prepare('DELETE FROM gp_players WHERE id = ?')->execute([$spectators[$seat]['id']]);
                    break;
                }
                if (!isset($players[$seat]) || $seat === $me['seat']) {
                    throw new GameError('invalid_move');
                }
                removePlayer($room, $players, $players[$seat], $engine);
                break;

            case 'leave':
                removePlayer($room, $players, $me, $engine);
                break;

            case 'move':
                if ($room['status'] !== 'playing') {
                    throw new GameError('game_not_started');
                }
                $move = is_array($in['move'] ?? null) ? $in['move'] : [];
                $engine->move($room['state'], $me['seat'], $move, $isHost);
                break;
        }

        saveRoom($room);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }

    if ($action === 'leave') {
        return ['ok' => true, 'left' => true];
    }
    $room = loadRoom($code, false);
    $players = loadPlayers((int)$room['id']);
    return snapshot($room, $players, findMe($players, $token));
}

/**
 * Remove someone from a room. In the waiting room, or in games people can join at any time,
 * the seat is freed. In a running game of dominoes/tic-tac-toe a bot takes over the seat
 * so the others can keep playing.
 */
function removePlayer(array &$room, array $players, array $who, GameEngine $engine): void
{
    $db = gp_db();
    $meta = $engine->meta();
    $seat = $who['seat'];
    $playing = $room['status'] === 'playing';

    if ($playing && !$meta['joinMidGame']) {
        if ($who['is_bot']) {
            throw new GameError('invalid_move');
        }
        $db->prepare('UPDATE gp_players SET is_bot = 1, is_host = 0, token = ? WHERE id = ?')
            ->execute([newToken(), $who['id']]);
        $players[$seat]['is_bot'] = true;
    } else {
        if ($playing) {
            $engine->removeSeat($room['state'], $seat);
        }
        $db->prepare('DELETE FROM gp_players WHERE id = ?')->execute([$who['id']]);
        unset($players[$seat]);
        if ($playing && count($players) < $meta['min']) {
            $room['status'] = 'lobby';
            $room['state'] = null;
        }
    }

    // Hand the host role to the next human if needed; close the room if no humans remain.
    $humans = array_filter($players, function ($p) {
        return !$p['is_bot'];
    });
    if (!$humans) {
        $room['status'] = 'closed';
        return;
    }
    if ($who['is_host']) {
        $next = reset($humans);
        $db->prepare('UPDATE gp_players SET is_host = 1 WHERE id = ?')->execute([$next['id']]);
    }
}
