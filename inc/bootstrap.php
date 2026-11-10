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
    }
    return $cfg;
}

function gp_db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = gp_config();
        $pdo = new PDO(
            'mysql:host=' . $c['db_host'] . ';dbname=' . $c['db_name'] . ';charset=utf8mb4',
            $c['db_user'],
            $c['db_pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );
        // Keep all timestamps in UTC regardless of the server setting.
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}

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
require_once __DIR__ . '/games/GameEngine.php';
require_once __DIR__ . '/games/Dominoes.php';
require_once __DIR__ . '/games/TruthDare.php';
require_once __DIR__ . '/games/TicTacToe.php';

/** Registry of available games. To add a game: write an engine + a JS file, then register it here. */
function gp_games(): array
{
    return [
        'dominoes'  => new Dominoes(),
        'truthdare' => new TruthDare(),
        'tictactoe' => new TicTacToe(),
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
