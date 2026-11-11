<?php
declare(strict_types=1);

// Bump this when you change CSS/JS so browsers (and InfinityFree's cache) fetch the new files.
const GP_ASSET_VERSION = '7';

/** Display info for each game (the rules live in inc/games/*.php). 'art' is a small SVG drawing. */
function gp_catalog(): array
{
    $tile = function (int $x, int $y, int $rot, array $top, array $bottom): string {
        $pip = function (array $pos, int $oy): string {
            $map = [1 => [9, 9], 2 => [17, 9], 3 => [25, 9], 4 => [9, 17], 5 => [17, 17], 6 => [25, 17], 7 => [9, 25], 8 => [17, 25], 9 => [25, 25]];
            $s = '';
            foreach ($pos as $p) {
                $s .= '<circle cx="' . $map[$p][0] . '" cy="' . ($map[$p][1] + $oy) . '" r="3"/>';
            }
            return $s;
        };
        return '<g transform="translate(' . $x . ' ' . $y . ') rotate(' . $rot . ' 17 34)">'
            . '<rect x="1" y="1" width="32" height="66" rx="5" fill="#fbf7ec" stroke="#14110d" stroke-width="2"/>'
            . '<line x1="6" y1="34" x2="28" y2="34" stroke="#6a6255" stroke-width="1.5"/>'
            . '<g fill="#14110d">' . $pip($top, 0) . $pip($bottom, 33) . '</g></g>';
    };

    return [
        'dominoes' => [
            'art_class' => 'art-felt',
            'art' => '<svg viewBox="0 0 90 90" width="86" height="86" aria-hidden="true">'
                . $tile(12, 10, -12, [1, 5, 9], [1, 3, 7, 9]) . $tile(44, 12, 14, [1, 3, 4, 6, 7, 9], [5]) . '</svg>',
        ],
        'truthdare' => [
            'art_class' => 'art-cards',
            'art' => '<svg viewBox="0 0 90 90" width="86" height="86" aria-hidden="true" font-family="Archivo, Arial, sans-serif" font-weight="900">'
                . '<g transform="rotate(-10 30 48)"><rect x="10" y="16" width="40" height="58" rx="5" fill="#edf2fb" stroke="#14110d" stroke-width="2"/>'
                . '<text x="30" y="56" text-anchor="middle" font-size="30" fill="#1f4592">?</text></g>'
                . '<g transform="rotate(9 60 44)"><rect x="40" y="12" width="40" height="58" rx="5" fill="#fbece6" stroke="#14110d" stroke-width="2"/>'
                . '<text x="60" y="52" text-anchor="middle" font-size="30" fill="#c4381d">!</text></g></svg>',
        ],
        'tictactoe' => [
            'art_class' => 'art-grid',
            'art' => '<svg viewBox="0 0 90 90" width="86" height="86" aria-hidden="true" stroke-linecap="round">'
                . '<g stroke="#1f4592" stroke-width="5"><path d="M18 18l14 14M32 18l-14 14"/><path d="M42 42l14 14M56 42l-14 14"/><path d="M66 66l10 10M76 66l-10 10"/></g>'
                . '<g fill="none" stroke="#c4381d" stroke-width="5"><circle cx="49" cy="25" r="8"/><circle cx="25" cy="61" r="8"/></g>'
                . '<path d="M12 12L80 80" stroke="#e3a21a" stroke-width="3" opacity=".8"/></svg>',
        ],
    ];
}

/** The little logo next to the site name: a domino tile, slightly tilted. */
function gp_logo(): string
{
    return '<svg width="18" height="30" viewBox="0 0 18 30" aria-hidden="true"><g transform="rotate(-8 9 15)">'
        . '<rect x="1.5" y="1.5" width="15" height="27" rx="3" fill="#f2ebdd" stroke="#f2ebdd"/>'
        . '<line x1="4" y1="15" x2="14" y2="15" stroke="#1c1915" stroke-width="1.2"/>'
        . '<g fill="#1c1915"><circle cx="9" cy="8" r="1.8"/><circle cx="5.8" cy="19.5" r="1.6"/><circle cx="12.2" cy="24" r="1.6"/></g></g></svg>';
}

function gp_header(string $titleKey, string $extraTitle = ''): void
{
    $home = (string)gp_setting('home_url');
    ?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#1c1915">
  <title><?= $extraTitle !== '' ? gp_h($extraTitle) . ' · ' : '' ?><?= gp_h((gp_setting('site_title')['fr'] ?? '') ?: 'Soirée Jeux') ?></title>
  <link rel="icon" href="data:image/svg+xml,<?= rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 18 30"><rect x="1" y="1" width="16" height="28" rx="3" fill="#fbf7ec" stroke="#1c1915" stroke-width="2"/><line x1="4" y1="15" x2="14" y2="15" stroke="#1c1915"/><g fill="#1c1915"><circle cx="9" cy="8" r="2"/><circle cx="6" cy="20" r="2"/><circle cx="12" cy="24" r="2"/></g></svg>') ?>">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wdth,wght@62..125,400..900&display=swap">
  <link rel="stylesheet" href="assets/css/style.css?v=<?= GP_ASSET_VERSION ?>">
  <script>window.GP_CONFIG = <?= json_encode(gp_public_config(), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
</head>
<body>
<header class="topbar">
  <a class="brand" href="index.php"><?= gp_logo() ?><span data-i18n="<?= gp_h($titleKey) ?>"></span></a>
  <div class="topbar-right">
    <?php if ($home !== ''): ?><a class="home-link" href="<?= gp_h($home) ?>" data-i18n="back_home"></a><?php endif; ?>
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
