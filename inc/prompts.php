<?php
declare(strict_types=1);

/**
 * Cards for the party games: Truth or Dare (truth/dare), Qui de nous deux (who), Tu préfères (wyr,
 * "choice A || choice B") and Dessine et devine (word).
 * They live in the gp_prompts table (edited from admin.php). For any type that has no rows in the
 * table yet, the built-in list in data/prompts.json is used, so every game works right after installation.
 */

const GP_PROMPT_LANGS = ['fr' => 'Français', 'en' => 'English'];
const GP_PROMPT_LEVELS = ['soft' => 'Tranquille / Chill', 'party' => 'Soirée / Party'];
const GP_PROMPT_TYPES = [
    'truth' => 'Vérité / Truth',
    'dare'  => 'Action / Dare',
    'who'   => 'Qui de nous / Which of us',
    'wyr'   => 'Tu préfères / Would you rather',
    'word'  => 'Mot à dessiner / Word to draw',
];

function gp_prompts_ensure_table(): void
{
    gp_db()->exec("CREATE TABLE IF NOT EXISTS gp_prompts (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        lang VARCHAR(5) NOT NULL,
        level VARCHAR(10) NOT NULL,
        type VARCHAR(10) NOT NULL,
        text VARCHAR(300) NOT NULL,
        active TINYINT(1) NOT NULL DEFAULT 1,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_pick (lang, level, type, active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** The default questions shipped in data/prompts.json, as [lang][level][type] => [text, ...]. */
function gp_prompts_defaults(): array
{
    $json = json_decode((string)file_get_contents(GP_ROOT . '/data/prompts.json'), true);
    return is_array($json) ? $json : [];
}

/** Active questions as [lang][level][type] => [text, ...] (same shape as data/prompts.json). */
function gp_prompts_load(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $rows = [];
    try {
        $rows = gp_db()->query('SELECT lang, level, type, text FROM gp_prompts WHERE active = 1 ORDER BY id')->fetchAll();
        $total = (int)gp_db()->query('SELECT COUNT(*) FROM gp_prompts')->fetchColumn();
    } catch (PDOException $e) {
        $total = 0; // table not created yet
    }
    $defaults = gp_prompts_defaults();
    if ($total === 0) {
        return $cache = $defaults;
    }
    $out = [];
    foreach (GP_PROMPT_LANGS as $l => $_) {
        foreach (GP_PROMPT_LEVELS as $lv => $_2) {
            $out[$l][$lv] = array_fill_keys(array_keys(GP_PROMPT_TYPES), []);
        }
    }
    $typesInDb = [];
    try {
        foreach (gp_db()->query('SELECT DISTINCT type FROM gp_prompts') as $t) {
            $typesInDb[$t['type']] = true;
        }
    } catch (PDOException $e) {
    }
    foreach ($rows as $r) {
        if (isset($out[$r['lang']][$r['level']][$r['type']])) {
            $out[$r['lang']][$r['level']][$r['type']][] = $r['text'];
        }
    }
    // Types never edited in the admin (e.g. a game added later) keep their built-in cards.
    foreach (array_keys(GP_PROMPT_TYPES) as $type) {
        if (empty($typesInDb[$type])) {
            foreach ($out as $l => $levels) {
                foreach ($levels as $lv => $_) {
                    $out[$l][$lv][$type] = $defaults[$l][$lv][$type] ?? [];
                }
            }
        }
    }
    return $cache = $out;
}

/** Copy data/prompts.json into the table, skipping questions that already exist. Returns how many were added. */
function gp_prompts_import_defaults(): int
{
    gp_prompts_ensure_table();
    $db = gp_db();
    $exists = $db->prepare('SELECT 1 FROM gp_prompts WHERE lang = ? AND level = ? AND type = ? AND text = ?');
    $insert = $db->prepare('INSERT INTO gp_prompts (lang, level, type, text, active, created_at, updated_at) VALUES (?,?,?,?,1,?,?)');
    $now = gp_now();
    $added = 0;
    foreach (gp_prompts_defaults() as $lang => $levels) {
        foreach ($levels as $level => $types) {
            foreach ($types as $type => $texts) {
                foreach ($texts as $text) {
                    $exists->execute([$lang, $level, $type, $text]);
                    if (!$exists->fetchColumn()) {
                        $insert->execute([$lang, $level, $type, $text, $now, $now]);
                        $added++;
                    }
                }
            }
        }
    }
    return $added;
}
