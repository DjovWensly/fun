<?php
declare(strict_types=1);

/**
 * Setup check: open this page in the browser (XAMPP or InfinityFree) to see what is missing.
 * It never shows your password. You can delete this file once everything is green.
 */

ini_set('display_errors', '0');
$root = __DIR__;
$results = [];   // [status ok|warn|fail, title, explanation]
$canFixTables = false;
$pdo = null;

function add(string $status, string $title, string $text = ''): void
{
    global $results;
    $results[] = [$status, $title, $text];
}

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// 1. PHP itself
if (version_compare(PHP_VERSION, '7.4', '>=')) {
    add('ok', 'PHP ' . PHP_VERSION);
} else {
    add('fail', 'PHP ' . PHP_VERSION . ' is too old', 'PHP 7.4 or newer is needed. In XAMPP, install a recent version; on InfinityFree, pick PHP 8.x in the control panel.');
}
foreach (['pdo_mysql' => 'extension=pdo_mysql', 'mbstring' => 'extension=mbstring', 'json' => ''] as $ext => $ini) {
    if (extension_loaded($ext)) {
        add('ok', "PHP extension $ext");
    } else {
        add('fail', "PHP extension $ext is missing", $ini ? "Open php.ini (XAMPP Control Panel > Apache > Config > php.ini), remove the ';' before \"$ini\", save, and restart Apache." : '');
    }
}

// 2. config.php
$cfg = null;
if (!is_file("$root/config.php")) {
    add('fail', 'config.php not found', 'Copy config.sample.php to config.php (in the same folder as this page) and fill in your database settings.');
} else {
    $raw = (string)file_get_contents("$root/config.php");
    if (strncmp(ltrim($raw, "\xEF\xBB\xBF"), '<?php', 5) !== 0) {
        add('fail', 'config.php does not start with <?php', 'The very first characters of the file must be <?php (no space or empty line before it).');
    } else {
        ob_start();
        try {
            $cfg = include "$root/config.php";
        } catch (Throwable $ex) {
            $cfg = null;
            add('fail', 'config.php has a PHP error', $ex->getMessage() . ' (line ' . $ex->getLine() . ')');
        }
        $junk = ob_get_clean();
        if ($junk !== '' && $junk !== false) {
            add('warn', 'config.php prints text', 'Remove anything outside the <?php ... ; block (for example text after "?>").');
        }
        if (!is_array($cfg)) {
            if ($cfg !== null) {
                add('fail', 'config.php does not return the settings', 'The settings must be wrapped like this: <?php return [ \'db_host\' => ..., ... ];');
            }
            $cfg = null;
        } else {
            $missing = array_diff(['db_host', 'db_name', 'db_user', 'db_pass'], array_keys($cfg));
            if ($missing) {
                add('fail', 'config.php is missing: ' . implode(', ', $missing));
                $cfg = null;
            } else {
                add('ok', 'config.php', 'host = ' . $cfg['db_host'] . ', database = ' . $cfg['db_name'] . ', user = ' . $cfg['db_user'] . ', password ' . ($cfg['db_pass'] === '' ? 'empty' : 'set'));
                if (empty($cfg['admin_password'])) {
                    add('warn', 'No admin_password in config.php', 'Only needed for admin.php (managing Truth or Dare questions).');
                }
            }
        }
    }
}

// 3. Files: a partial upload (one file missing or still in an old version) is the most common problem.
$required = [
    'admin.php',
    'api.php',
    'index.php',
    'local.php',
    'room.php',
    'assets/css/style.css',
    'assets/js/common.js',
    'assets/js/emotes.js',
    'assets/js/home.js',
    'assets/js/local-truthdare.js',
    'assets/js/look.js',
    'assets/js/options.js',
    'assets/js/room.js',
    'assets/js/sound.js',
    'assets/js/games/dominoes.js',
    'assets/js/games/drawguess.js',
    'assets/js/games/mystery.js',
    'assets/js/games/poll-common.js',
    'assets/js/games/tictactoe.js',
    'assets/js/games/truthdare.js',
    'assets/js/games/whoofus.js',
    'assets/js/games/wouldrather.js',
    'data/prompts.json',
    'data/reactions.json',
    'inc/admin_tabs.php',
    'inc/bootstrap.php',
    'inc/layout.php',
    'inc/prompts.php',
    'inc/settings.php',
    'inc/games/Dominoes.php',
    'inc/games/DrawGuess.php',
    'inc/games/GameEngine.php',
    'inc/games/Mystery.php',
    'inc/games/PollGame.php',
    'inc/games/TicTacToe.php',
    'inc/games/TruthDare.php',
    'inc/games/WhoOfUs.php',
    'inc/games/WouldRather.php',
];
$missing = [];
foreach ($required as $f) {
    if (!is_file("$root/$f")) {
        $missing[] = $f;
    }
}
if ($missing) {
    add('fail', count($missing) . ' file(s) missing on the server', 'Upload them again: ' . implode(', ', $missing));
} else {
    add('ok', 'All ' . count($required) . ' site files are present');
}
foreach (['img/favicon.ico', 'inc/.htaccess', '.htaccess'] as $f) {
    if (!is_file("$root/$f")) {
        add('warn', "$f is missing", $f === 'img/favicon.ico' ? 'Only the tab icon is affected.' : 'Upload it: it protects private files (show hidden files in FileZilla to see it).');
    }
}

// 4. MySQL server, then the database, then the tables
if ($cfg && extension_loaded('pdo_mysql')) {
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5];
    try {
        $server = new PDO('mysql:host=' . $cfg['db_host'] . ';charset=utf8mb4', $cfg['db_user'], $cfg['db_pass'], $opts);
        add('ok', 'Connected to the MySQL server', 'Version ' . $server->query('SELECT VERSION()')->fetchColumn());
        try {
            $pdo = new PDO('mysql:host=' . $cfg['db_host'] . ';dbname=' . $cfg['db_name'] . ';charset=utf8mb4', $cfg['db_user'], $cfg['db_pass'], $opts);
            add('ok', 'Database "' . $cfg['db_name'] . '" exists');
        } catch (PDOException $ex) {
            add('fail', 'Database "' . $cfg['db_name'] . '" not found or not allowed',
                'Create it in phpMyAdmin (XAMPP: http://localhost/phpmyadmin > New > name it "' . $cfg['db_name'] . '"; InfinityFree: Control Panel > MySQL Databases), or fix db_name in config.php. Details: ' . $ex->getMessage());
        }
    } catch (PDOException $ex) {
        $m = $ex->getMessage();
        if (strpos($m, '[1045]') !== false) {
            add('fail', 'MySQL rejected the user or password', 'Check db_user and db_pass in config.php. XAMPP default: user "root", empty password \'\'. Details: ' . $m);
        } else {
            add('fail', 'Cannot reach the MySQL server', 'XAMPP: open the XAMPP Control Panel and click Start next to MySQL (it must turn green). InfinityFree: db_host must be like sqlXXX.infinityfree.com, not localhost. Details: ' . $m);
        }
    }
}

if ($pdo) {
    // Offer to create the tables from install.sql (safe: CREATE TABLE IF NOT EXISTS).
    if (($_POST['create_tables'] ?? '') === '1' && is_file("$root/install.sql")) {
        try {
            $sql = (string)file_get_contents("$root/install.sql");
            $sql = preg_replace('/^\s*--.*$/m', '', $sql);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                $pdo->exec($stmt);
            }
            add('ok', 'Tables created from install.sql');
        } catch (PDOException $ex) {
            add('fail', 'Could not create the tables', $ex->getMessage());
        }
    }
    $have = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['gp_rooms', 'gp_players'] as $t) {
        if (in_array($t, $have, true)) {
            add('ok', "Table $t");
        } else {
            add('fail', "Table $t is missing", 'Click "Create the tables" below, or import install.sql with phpMyAdmin.');
            $canFixTables = true;
        }
    }
    if (in_array('gp_prompts', $have, true)) {
        $n = (int)$pdo->query('SELECT COUNT(*) FROM gp_prompts')->fetchColumn();
        add('ok', 'Table gp_prompts', $n ? "$n Truth or Dare questions" : 'Empty: the built-in questions from data/prompts.json are used.');
    } else {
        add('ok', 'Table gp_prompts not created yet', 'Normal: it is created when you first open admin.php. Until then the built-in questions are used.');
    }
}

if (!is_file("$root/data/prompts.json")) {
    add('fail', 'data/prompts.json is missing', 'Upload the data folder too.');
}


// 5. Run what the home page runs, and show the real error if it fails (instead of "Server error").
if ($pdo && !$missing && $cfg) {
    ob_start();
    try {
        require_once "$root/inc/bootstrap.php";
        require_once "$root/inc/layout.php";
        restore_exception_handler();
        $n = 0;
        foreach (gp_catalog() as $id => $g) {
            gp_engine($id)->meta();
            $n++;
        }
        gp_public_config();
        gp_icon_tags();
        add('ok', 'Home page code runs', "$n games");
    } catch (Throwable $ex) {
        $where = str_replace($root . DIRECTORY_SEPARATOR, '', $ex->getFile()) . ':' . $ex->getLine();
        $hint = $ex->getMessage() === 'unknown_game'
            ? ' Some files are older than others: upload inc/bootstrap.php, inc/layout.php and the whole inc/games/ folder again.'
            : '';
        add('fail', 'The home page code fails (' . get_class($ex) . ')', $ex->getMessage() . ' — in ' . $where . '.' . $hint);
    }
    ob_end_clean();
}

$failed = count(array_filter($results, function ($r) {
    return $r[0] === 'fail';
}));
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Setup check · Game Party</title>
<style>
  body { font: 16px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; max-width: 720px; margin: 0 auto; padding: 20px 16px 60px; background: #f6f6fb; color: #1f2937; }
  h1 { margin: 0 0 4px; }
  .summary { padding: 12px 16px; border-radius: 10px; font-weight: 700; margin: 16px 0; }
  .summary.good { background: #dcfce7; color: #14532d; }
  .summary.bad { background: #fee2e2; color: #7f1d1d; }
  ul { list-style: none; padding: 0; margin: 0; }
  li { background: #fff; border: 1px solid #e5e7eb; border-left-width: 5px; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px; overflow-wrap: anywhere; }
  li.ok { border-left-color: #22c55e; }
  li.warn { border-left-color: #f59e0b; }
  li.fail { border-left-color: #ef4444; }
  li small { display: block; color: #4b5563; margin-top: 2px; }
  button, .btn { font: inherit; font-weight: 600; padding: 10px 16px; border-radius: 8px; border: 0; background: #7c3aed; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
  code { background: #eef; padding: 1px 4px; border-radius: 4px; }
  @media (prefers-color-scheme: dark) {
    body { background: #13112b; color: #f4f3ff; }
    li { background: #262357; border-color: #3f3c80; }
    li small { color: #c7c4ee; }
    code { background: #312e6b; }
  }
</style>
</head>
<body>
<h1>Setup check</h1>
<p>Checks PHP, <code>config.php</code>, the database and the game API. Your password is never shown.</p>

<div class="summary <?= $failed ? 'bad' : 'good' ?>">
  <?= $failed ? "✗ $failed problem(s) to fix. See the red lines below." : '✓ Server side looks good. Checking the game API…' ?>
</div>

<ul>
  <?php foreach ($results as [$status, $title, $text]): ?>
    <li class="<?= e($status) ?>"><?= $status === 'ok' ? '✓' : ($status === 'warn' ? '!' : '✗') ?> <?= e($title) ?>
      <?php if ($text !== ''): ?><small><?= e($text) ?></small><?php endif; ?></li>
  <?php endforeach; ?>
  <li id="api-check" class="warn">… Game API (api.php) <small>Testing…</small></li>
</ul>

<?php if ($canFixTables): ?>
  <form method="post"><input type="hidden" name="create_tables" value="1"><button>Create the tables</button></form>
<?php endif; ?>

<p style="margin-top:24px"><a class="btn" href="index.php">Open the games</a></p>
<p><small>Once everything is green you can delete check.php from the server.</small></p>

<script>
  // Same kind of request the games make (it reads the database), to catch PHP warnings or errors that break the JSON answer.
  // Asking for a room that doesn't exist is fine: a correct "room_not_found" answer proves the whole chain works.
  fetch('api.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{"action":"state","code":"ZZZZZ"}' })
    .then(function (r) { return r.text().then(function (t) { return { status: r.status, text: t }; }); })
    .then(function (res) {
      var li = document.getElementById('api-check');
      var j = null;
      try { j = JSON.parse(res.text); } catch (e) {}
      if (j && (j.ok || j.error === 'room_not_found')) {
        li.className = 'ok';
        li.innerHTML = '✓ Game API (api.php) answers correctly';
        var sum = document.querySelector('.summary.good');
        if (sum) sum.textContent = '✓ Everything is ready. You can play!';
      } else {
        li.className = 'fail';
        var plain = res.text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 400);
        li.textContent = '✗ Game API (api.php) did not answer correctly (HTTP ' + res.status + ')';
        var s = document.createElement('small');
        s.textContent = j && j.help ? j.help : 'Answer: ' + (plain || '(empty)');
        li.appendChild(s);
      }
    })
    .catch(function (e) {
      var li = document.getElementById('api-check');
      li.className = 'fail';
      li.textContent = '✗ Could not call api.php: ' + e;
    });
</script>
</body>
</html>
