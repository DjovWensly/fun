<?php
declare(strict_types=1);

// Bump this when you change CSS/JS so browsers (and InfinityFree's cache) fetch the new files.
const GP_ASSET_VERSION = '2';

/** Display info for each game (the rules live in inc/games/*.php). */
function gp_catalog(): array
{
    return [
        'dominoes'  => ['icon' => '<svg viewBox="0 0 40 64" width="30" height="48"><rect x="1" y="1" width="38" height="62" rx="6" fill="#fff" stroke="#111" stroke-width="2"/><line x1="6" y1="32" x2="34" y2="32" stroke="#111" stroke-width="2"/><g fill="#111"><circle cx="12" cy="10" r="3.5"/><circle cx="28" cy="22" r="3.5"/><circle cx="20" cy="16" r="3.5"/><circle cx="12" cy="40" r="3.5"/><circle cx="28" cy="40" r="3.5"/><circle cx="12" cy="54" r="3.5"/><circle cx="28" cy="54" r="3.5"/></g></svg>', 'color' => '#f59e0b'],
        'truthdare' => ['icon' => '🎭', 'color' => '#ec4899'],
        'tictactoe' => ['icon' => '❌', 'color' => '#22c55e'],
    ];
}

function gp_header(string $titleKey, string $extraTitle = ''): void
{
    $home = gp_config()['home_url'] ?? '/';
    ?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#1e1b4b">
  <title><?= $extraTitle !== '' ? gp_h($extraTitle) . ' · ' : '' ?>Game Party</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎲</text></svg>">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= GP_ASSET_VERSION ?>">
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php"><span aria-hidden="true">🎲</span> <span data-i18n="<?= gp_h($titleKey) ?>"></span></a>
  <div class="topbar-right">
    <a class="home-link" href="<?= gp_h($home) ?>" data-i18n="back_home"></a>
    <div class="lang-switch">
      <button type="button" data-lang="fr">FR</button>
      <button type="button" data-lang="en">EN</button>
    </div>
  </div>
</header>
<?php
}

function gp_footer(): void
{
    echo "</body>\n</html>\n";
}
