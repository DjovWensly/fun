<?php
declare(strict_types=1);

/**
 * Site settings edited from admin.php (tab "Réglages" and "Réactions").
 * Stored in the gp_settings table as name => JSON value. Anything not saved yet falls back to
 * the defaults below, config.php, and data/reactions.json, so the site works before any change.
 */

const GP_REACTIONS_MAX = 16;

function gp_settings_defaults(): array
{
    $cfg = gp_config();
    return [
        'site_title'     => ['fr' => '', 'en' => ''],   // empty = built-in "Soirée Jeux" / "Game Night"
        'hero_title'     => ['fr' => '', 'en' => ''],
        'tagline'        => ['fr' => '', 'en' => ''],
        'default_lang'   => 'auto',                      // auto (from the browser) | fr | en
        'games'          => ['dominoes' => true, 'truthdare' => true, 'tictactoe' => true, 'whoofus' => true, 'wouldrather' => true, 'drawguess' => true],
        'home_url'       => (string)($cfg['home_url'] ?? ''),
        'home_label'     => '',
        'poll_ms'        => 2000,
        'room_ttl_hours' => (int)($cfg['room_ttl_hours'] ?? 24),
        'reactions_on'   => true,
        'reactions'      => gp_reactions_defaults(),
    ];
}

function gp_reactions_defaults(): array
{
    $json = json_decode((string)@file_get_contents(GP_ROOT . '/data/reactions.json'), true);
    return is_array($json['reactions'] ?? null) ? $json['reactions'] : [];
}

function gp_settings_ensure_table(): void
{
    gp_db()->exec("CREATE TABLE IF NOT EXISTS gp_settings (
        name VARCHAR(50) NOT NULL,
        value MEDIUMTEXT NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** All settings (saved values over defaults). Never throws: a database problem just means defaults. */
function gp_settings(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = gp_settings_defaults();
    try {
        foreach (gp_db()->query('SELECT name, value FROM gp_settings') as $row) {
            if (array_key_exists($row['name'], $cache)) {
                $v = json_decode($row['value'], true);
                if ($v !== null) {
                    $cache[$row['name']] = is_array($cache[$row['name']]) && is_array($v) && !array_is_list_compat($cache[$row['name']])
                        ? array_merge($cache[$row['name']], $v)
                        : $v;
                }
            }
        }
    } catch (Throwable $e) {
        // table not created yet, or database unavailable: keep the defaults
    }
    return $cache;
}

function gp_setting(string $name)
{
    return gp_settings()[$name] ?? null;
}

function gp_settings_save(array $values): void
{
    gp_settings_ensure_table();
    $st = gp_db()->prepare('REPLACE INTO gp_settings (name, value, updated_at) VALUES (?,?,?)');
    foreach ($values as $name => $value) {
        $st->execute([$name, json_encode($value, JSON_UNESCAPED_UNICODE), gp_now()]);
    }
}

function gp_settings_reset(string $name): void
{
    try {
        gp_db()->prepare('DELETE FROM gp_settings WHERE name = ?')->execute([$name]);
    } catch (PDOException $e) {
    }
}

/** array_is_list() exists only since PHP 8.1. */
function array_is_list_compat(array $a): bool
{
    return $a === [] || array_keys($a) === range(0, count($a) - 1);
}

function gp_game_enabled(string $game): bool
{
    $games = gp_setting('games');
    return !isset($games[$game]) || (bool)$games[$game];
}

function gp_reaction_ids(): array
{
    return array_column(gp_setting('reactions') ?: [], 'id');
}

/** What the browser needs to know, printed once per page as window.GP_CONFIG. */
function gp_public_config(): array
{
    $s = gp_settings();
    $nonEmpty = function ($v): bool {
        return (string)$v !== '';
    };
    return [
        'texts' => [
            // (object) so an empty list is sent as {} and not [] (see common.js t())
            'fr' => (object)array_filter(['site_title' => $s['site_title']['fr'] ?? '', 'hero_title' => $s['hero_title']['fr'] ?? '', 'tagline' => $s['tagline']['fr'] ?? '', 'back_home' => $s['home_label']], $nonEmpty),
            'en' => (object)array_filter(['site_title' => $s['site_title']['en'] ?? '', 'hero_title' => $s['hero_title']['en'] ?? '', 'tagline' => $s['tagline']['en'] ?? '', 'back_home' => $s['home_label']], $nonEmpty),
        ],
        'lang'         => $s['default_lang'],
        'pollMs'       => max(1000, min(6000, (int)$s['poll_ms'])),
        'reactionsOn'  => (bool)$s['reactions_on'],
        'reactions'    => array_values($s['reactions'] ?: []),
    ];
}
