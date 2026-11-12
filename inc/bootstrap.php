<?php
declare(strict_types=1);

// Shared bootstrap: config, database, helpers. PHP 7.4+ compatible (InfinityFree runs PHP 8.x).

define('GP_ROOT', dirname(__DIR__));

function gp_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $file = GP_ROOT . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit('config.php is missing. Copy config.sample.php to config.php and fill in your database credentials.');
        }
        $cfg = require $file;
        if (!is_array($cfg)) {
            http_response_code(500);
            exit('config.php is not valid: it must start with <code>&lt;?php</code> and contain '
                . '<code>return [ ... ];</code> around the settings. Copy config.sample.php again and only change the values.');
        }
    }
    return $cfg;
}

/** The database could not be reached or is not set up. $code is a short key the pages translate. */
class SetupError extends RuntimeException
{
    public $key;

    public function __construct(string $key, string $detail)
    {
        parent::__construct($detail);
        $this->key = $key;
    }
}

/** Turn a PDO error into a setup problem the user can fix, or null if it is a normal error. */
function gp_setup_error(PDOException $e): ?SetupError
{
    $info = $e->errorInfo ?? null;
    $driverCode = (int)($info[1] ?? 0);
    $msg = $e->getMessage();
    if ($driverCode === 0 && preg_match('/\[(\d{4})\]/', $msg, $m)) {
        $driverCode = (int)$m[1]; // connection errors only carry the code in the message
    }
    $map = [
        1049 => 'db_unknown',   // Unknown database
        1044 => 'db_access',    // Access denied to database
        1045 => 'db_access',    // Access denied for user (password)
        1146 => 'db_tables',    // Table doesn't exist
        2002 => 'db_down',      // Can't connect (MySQL not started / wrong host)
        2005 => 'db_down',      // Unknown host
        2006 => 'db_down',
    ];
    return isset($map[$driverCode]) ? new SetupError($map[$driverCode], $msg) : null;
}

function gp_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = gp_config();
        if (!extension_loaded('pdo_mysql')) {
            throw new SetupError('no_pdo', 'The pdo_mysql PHP extension is not enabled.');
        }
        try {
            $pdo = new PDO(
                'mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4',
                $c['db_user'],
                $c['db_pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_TIMEOUT => 5,
                ]
            );
        } catch (PDOException $e) {
            throw gp_setup_error($e) ?? new SetupError('db_down', $e->getMessage());
        }
        // Keep all timestamps in UTC regardless of the server setting.
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

/** Plain-language explanation of a setup problem (shown on pages and by check.php). */
function gp_setup_help(string $key): string
{
    $help = [
        'db_unknown' => 'The database named in config.php (db_name) does not exist. Create it in phpMyAdmin, then import install.sql into it.',
        'db_access'  => 'MySQL refused access: check db_user / db_pass in config.php, and that the database in db_name exists. On XAMPP the default is user "root" with an empty password. On InfinityFree use the values from Control Panel > MySQL Databases.',
        'db_tables'  => 'The database exists but the tables are missing. In phpMyAdmin, select the database, then Import > install.sql > Go.',
        'db_down'    => 'Cannot reach the MySQL server. On XAMPP: open the XAMPP Control Panel and click Start next to MySQL. On InfinityFree: db_host must be the sqlXXX.infinityfree.com address, not "localhost".',
        'no_pdo'     => 'PHP\'s pdo_mysql extension is off. In XAMPP, open php.ini and make sure the line "extension=pdo_mysql" has no ";" in front of it, then restart Apache.',
    ];
    return $help[$key] ?? 'Database error.';
}

/** Friendly page for setup problems on normal (HTML) pages instead of a PHP fatal error. */
function gp_setup_page(SetupError $e): void
{
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>Setup problem</title><body style="font:16px/1.5 system-ui,sans-serif;max-width:640px;margin:40px auto;padding:0 16px">'
        . '<h1>Setup problem</h1><p><strong>' . gp_h(gp_setup_help($e->key)) . '</strong></p>'
        . '<p style="color:#666">Details: ' . gp_h($e->getMessage()) . '</p>'
        . '<p><a href="check.php">Run the setup check (check.php)</a></p></body>';
}

set_exception_handler(function (Throwable $e) {
    if ($e instanceof PDOException && ($s = gp_setup_error($e))) {
        $e = $s;
    }
    if ($e instanceof SetupError) {
        gp_setup_page($e);
        return;
    }
    error_log('[games] ' . $e);
    http_response_code(500);
    // 'debug' => true in config.php shows the real error (useful on hosts without access to the error log).
    if (!empty(gp_config()['debug'])) {
        $where = str_replace(GP_ROOT . DIRECTORY_SEPARATOR, '', $e->getFile()) . ':' . $e->getLine();
        echo '<pre style="white-space:pre-wrap;font:14px/1.4 monospace;padding:16px">' . gp_h(get_class($e) . ': ' . $e->getMessage() . "\n" . $where) . '</pre>';
        return;
    }
    echo 'Server error. Open check.php to find the cause, or set \'debug\' => true in config.php to see it here.';
});

function gp_now(): string
{
    return gmdate('Y-m-d H:i:s');
}

function gp_h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Error that is safe to show to the player (the client translates the code). */
class GameError extends Exception
{
}

require_once __DIR__ . '/prompts.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/games/GameEngine.php';
require_once __DIR__ . '/games/Dominoes.php';
require_once __DIR__ . '/games/TruthDare.php';
require_once __DIR__ . '/games/TicTacToe.php';
require_once __DIR__ . '/games/PollGame.php';
require_once __DIR__ . '/games/WhoOfUs.php';
require_once __DIR__ . '/games/WouldRather.php';
require_once __DIR__ . '/games/DrawGuess.php';
require_once __DIR__ . '/games/Mystery.php';

/** Registry of available games. To add a game: write an engine + a JS file, then register it here. */
function gp_games(): array
{
    return [
        'dominoes'  => new Dominoes(),
        'truthdare' => new TruthDare(),
        'tictactoe' => new TicTacToe(),
        'whoofus'   => new WhoOfUs(),
        'wouldrather' => new WouldRather(),
        'drawguess' => new DrawGuess(),
        'mystery'   => new Mystery(),
    ];
}

function gp_engine(string $game): GameEngine
{
    $all = gp_games();
    if (!isset($all[$game])) {
        throw new GameError('unknown_game');
    }
    return $all[$game];
}
