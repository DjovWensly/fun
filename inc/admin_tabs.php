<?php
declare(strict_types=1);

/** admin.php tabs "Reactions" and "Settings": form handlers and pages. */

// ------------------------------------------------------------------------------------ handlers

/** Handle a POST for these tabs. Returns a flash message, or null if the action is not ours. */
function gp_admin_tabs_post(string $action): ?array
{
    if ($action === 'save_reactions') {
        $ids = (array)($_POST['r_id'] ?? []);
        $types = (array)($_POST['r_type'] ?? []);
        $emojis = (array)($_POST['r_emoji'] ?? []);
        $fr = (array)($_POST['r_fr'] ?? []);
        $en = (array)($_POST['r_en'] ?? []);
        $list = [];
        $seen = [];
        $skipped = 0;
        foreach ($types as $i => $type) {
            $type = $type === 'emoji' ? 'emoji' : 'text';
            $item = [
                'id'   => (string)($ids[$i] ?? ''),
                'type' => $type,
                'fr'   => gp_admin_short($fr[$i] ?? '', 40),
                'en'   => gp_admin_short($en[$i] ?? '', 40),
            ];
            if ($type === 'emoji') {
                $item['emoji'] = gp_admin_short($emojis[$i] ?? '', 16);
                if ($item['emoji'] === '') {
                    $skipped++;
                    continue;
                }
            } elseif ($item['fr'] === '' && $item['en'] === '') {
                $skipped++;
                continue;
            }
            if (!preg_match('/^[a-z0-9_]{1,20}$/', $item['id']) || isset($seen[$item['id']])) {
                $item['id'] = 'c' . bin2hex(random_bytes(4));
            }
            $seen[$item['id']] = true;
            $list[] = $item;
            if (count($list) >= GP_REACTIONS_MAX) {
                break;
            }
        }
        gp_settings_save([
            'reactions'    => $list,
            'reactions_on' => !empty($_POST['reactions_on']),
        ]);
        $msg = count($list) . ' reaction(s) saved.';
        if ($skipped) {
            $msg .= " $skipped empty line(s) ignored.";
        }
        return ['ok', $msg];
    }

    if ($action === 'reset_reactions') {
        gp_settings_reset('reactions');
        return ['info', 'Reactions reset to the defaults from data/reactions.json.'];
    }

    if ($action === 'save_settings') {
        $texts = function (string $name): array {
            return [
                'fr' => gp_admin_short($_POST[$name . '_fr'] ?? '', 80),
                'en' => gp_admin_short($_POST[$name . '_en'] ?? '', 80),
                'ht' => gp_admin_short($_POST[$name . '_ht'] ?? '', 80),
            ];
        };
        $games = [];
        foreach (array_keys(gp_games()) as $g) {
            $games[$g] = !empty($_POST['game_' . $g]);
        }
        if (!in_array(true, $games, true)) {
            return ['error', 'Keep at least one game switched on.'];
        }
        $url = trim((string)($_POST['home_url'] ?? ''));
        if ($url !== '' && !preg_match('~^(https?://|/)~i', $url)) {
            $url = 'https://' . $url;
        }
        $lang = (string)($_POST['default_lang'] ?? 'auto');
        $lang = in_array($lang, ['auto', 'fr', 'en', 'ht'], true) ? $lang : 'auto';
        $poll = (int)($_POST['poll_ms'] ?? 2000);
        $ttl = (int)($_POST['room_ttl_hours'] ?? 24);
        gp_settings_save([
            'site_title'     => $texts('site_title'),
            'hero_title'     => $texts('hero_title'),
            'tagline'        => $texts('tagline'),
            'footer_text'    => $texts('footer_text'),
            'default_lang'   => $lang,
            'games'          => $games,
            'home_url'       => mb_substr($url, 0, 200),
            'home_label'     => gp_admin_short($_POST['home_label'] ?? '', 40),
            'poll_ms'        => in_array($poll, [1500, 2000, 3000, 5000], true) ? $poll : 2000,
            'room_ttl_hours' => in_array($ttl, [6, 12, 24, 48, 72, 168], true) ? $ttl : 24,
        ]);
        return ['ok', 'Settings saved.'];
    }

    return null;
}

function gp_admin_short($s, int $max): string
{
    $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
    return mb_substr($s, 0, $max);
}

// ------------------------------------------------------------------------------------ pages

function gp_admin_tabs_nav(string $tab): string
{
    $tabs = ['questions' => 'Questions', 'reactions' => 'Reactions', 'settings' => 'Settings'];
    $h = '<nav class="admin-tabs">';
    foreach ($tabs as $k => $label) {
        $h .= '<a href="admin.php?tab=' . $k . '"' . ($k === $tab ? ' class="active" aria-current="page"' : '') . '>' . $label . '</a>';
    }
    return $h . '</nav>';
}

function gp_admin_reactions_page(string $csrf): void
{
    $s = gp_settings();
    $rows = $s['reactions'] ?: [];
    $row = function (array $r): string {
        $type = ($r['type'] ?? 'text') === 'emoji' ? 'emoji' : 'text';
        return '<li class="reaction-row" data-type="' . $type . '">'
            . '<input type="hidden" name="r_id[]" value="' . gp_h($r['id'] ?? '') . '">'
            . '<select name="r_type[]" aria-label="Type"><option value="emoji"' . ($type === 'emoji' ? ' selected' : '') . '>Emoji</option>'
            . '<option value="text"' . ($type === 'text' ? ' selected' : '') . '>Phrase</option></select>'
            . '<input class="r-emoji" name="r_emoji[]" maxlength="16" placeholder="😀" aria-label="Emoji" value="' . gp_h($r['emoji'] ?? '') . '">'
            . '<input class="r-text" name="r_fr[]" maxlength="40" placeholder="Français" aria-label="Français" value="' . gp_h($r['fr'] ?? '') . '">'
            . '<input class="r-text" name="r_en[]" maxlength="40" placeholder="English" aria-label="English" value="' . gp_h($r['en'] ?? '') . '">'
            . '<span class="r-tools">'
            . '<button type="button" class="btn tiny" data-move="-1" aria-label="Move up">↑</button>'
            . '<button type="button" class="btn tiny" data-move="1" aria-label="Move down">↓</button>'
            . '<button type="button" class="btn tiny danger" data-remove aria-label="Remove">Remove</button>'
            . '</span></li>';
    };
    ?>
  <form method="post" class="panel" id="reactions-form">
    <?= $csrf ?>
    <input type="hidden" name="action" value="save_reactions">
    <input type="hidden" name="tab" value="reactions">
    <h2>Reactions</h2>
    <p class="muted small">What players can send during a game. <strong>Emoji</strong> shows the emoji (the French/English
      fields are its name for screen readers). <strong>Phrase</strong> shows the French or English text, in each player's
      own language. Up to <?= GP_REACTIONS_MAX ?> reactions, 40 characters per text.</p>
    <label class="check"><input type="checkbox" name="reactions_on" value="1" <?= $s['reactions_on'] ? 'checked' : '' ?>> Reactions are switched on</label>
    <ul class="reaction-list" id="reaction-list">
      <?php foreach ($rows as $r) {
          echo $row($r);
      } ?>
    </ul>
    <template id="reaction-template"><?= $row(['type' => 'text']) ?></template>
    <div class="row wrap-row">
      <button type="button" class="btn" id="add-emoji">Add an emoji</button>
      <button type="button" class="btn" id="add-phrase">Add a phrase</button>
      <span class="muted small" id="reaction-count"></span>
    </div>
    <div class="row end" style="margin-top:14px"><button class="btn primary">Save reactions</button></div>
  </form>
  <form method="post" onsubmit="return confirm('Replace your reactions with the defaults?');">
    <?= $csrf ?><input type="hidden" name="action" value="reset_reactions"><input type="hidden" name="tab" value="reactions">
    <button class="btn small ghost">Reset to the defaults (data/reactions.json)</button>
  </form>
  <script>
  (function () {
    var list = document.getElementById('reaction-list');
    var tpl = document.getElementById('reaction-template');
    var max = <?= GP_REACTIONS_MAX ?>;
    function sync(row) { row.setAttribute('data-type', row.querySelector('select').value); }
    function count() {
      var n = list.children.length;
      document.getElementById('reaction-count').textContent = n + ' / ' + max;
      document.getElementById('add-emoji').disabled = document.getElementById('add-phrase').disabled = n >= max;
    }
    function add(type) {
      var row = tpl.content.firstElementChild.cloneNode(true);
      row.querySelector('select').value = type;
      sync(row);
      list.appendChild(row);
      row.querySelector(type === 'emoji' ? '.r-emoji' : '.r-text').focus();
      count();
    }
    list.addEventListener('change', function (e) { if (e.target.tagName === 'SELECT') sync(e.target.closest('li')); });
    list.addEventListener('click', function (e) {
      var b = e.target.closest('button'); if (!b) return;
      var row = b.closest('li');
      if (b.hasAttribute('data-remove')) { row.remove(); count(); return; }
      var dir = parseInt(b.getAttribute('data-move'), 10);
      if (dir < 0 && row.previousElementSibling) list.insertBefore(row, row.previousElementSibling);
      if (dir > 0 && row.nextElementSibling) list.insertBefore(row.nextElementSibling, row);
    });
    document.getElementById('add-emoji').addEventListener('click', function () { add('emoji'); });
    document.getElementById('add-phrase').addEventListener('click', function () { add('text'); });
    count();
  })();
  </script>
<?php
}

function gp_admin_settings_page(string $csrf): void
{
    $s = gp_settings();
    $text = function (string $name, string $label, string $phFr, string $phEn) use ($s): string {
        return '<fieldset class="pair"><legend>' . gp_h($label) . '</legend>'
            . '<label class="field"><span>Français</span><input name="' . $name . '_fr" maxlength="80" placeholder="' . gp_h($phFr) . '" value="' . gp_h($s[$name]['fr'] ?? '') . '"></label>'
            . '<label class="field"><span>English</span><input name="' . $name . '_en" maxlength="80" placeholder="' . gp_h($phEn) . '" value="' . gp_h($s[$name]['en'] ?? '') . '"></label>'
            . '<label class="field"><span>Kreyòl</span><input name="' . $name . '_ht" maxlength="80" placeholder="(French text if empty)" value="' . gp_h($s[$name]['ht'] ?? '') . '"></label>'
            . '</fieldset>';
    };
    $select = function (string $name, array $options, $value): string {
        $h = '<select name="' . $name . '">';
        foreach ($options as $k => $label) {
            $h .= '<option value="' . gp_h((string)$k) . '"' . ((string)$k === (string)$value ? ' selected' : '') . '>' . gp_h($label) . '</option>';
        }
        return $h . '</select>';
    };
    $names = [
        'dominoes'    => 'Dominoes / Dominos',
        'truthdare'   => 'Truth or Dare / Action ou Vérité',
        'tictactoe'   => 'Tic-Tac-Toe / Morpion',
        'whoofus'     => 'Which of us? / Qui de nous deux ?',
        'wouldrather' => 'Would you rather? / Tu préfères… ?',
        'drawguess'   => 'Draw & guess / Dessine et devine',
        'mystery'     => 'Mystery word / Mot mystère',
    ];
    ?>
  <form method="post" class="settings-form">
    <?= $csrf ?>
    <input type="hidden" name="action" value="save_settings">
    <input type="hidden" name="tab" value="settings">

    <section class="panel">
      <h2>Texts</h2>
      <p class="muted small">Leave a field empty to keep the built-in text (shown in grey).</p>
      <?= $text('site_title', 'Site name (top bar)', 'Soirée Jeux', 'Game Night') ?>
      <?= $text('hero_title', 'Home page title', 'Ce soir, on joue.', 'Tonight, we play.') ?>
      <?= $text('tagline', 'Home page sentence', 'Choisis un jeu, envoie le code à tes amis, et c\'est parti.', 'Pick a game, send the code to your friends, and you\'re in.') ?>
      <?= $text('footer_text', 'Footer text (bottom of every page; {year} = current year, {site} = site name)', '© {year} {site} · Des jeux à partager entre amis.', '© {year} {site} · Games to share with friends.') ?>
      <label class="field"><span>Language for new visitors</span>
        <?= $select('default_lang', ['auto' => 'Same as their phone or browser', 'fr' => 'Français', 'en' => 'English', 'ht' => 'Kreyòl ayisyen'], $s['default_lang']) ?>
      </label>
    </section>

    <section class="panel">
      <h2>Games</h2>
      <p class="muted small">Switched-off games disappear from the home page. Tables already open keep working.</p>
      <?php foreach ($names as $id => $label): ?>
        <label class="check"><input type="checkbox" name="game_<?= $id ?>" value="1" <?= gp_game_enabled($id) ? 'checked' : '' ?>> <?= gp_h($label) ?></label><br>
      <?php endforeach; ?>
    </section>

    <section class="panel">
      <h2>Link to your main site</h2>
      <label class="field"><span>Address (leave empty to hide the link)</span>
        <input name="home_url" maxlength="200" placeholder="https://dwens.gamer.gd/" value="<?= gp_h($s['home_url']) ?>">
      </label>
      <label class="field"><span>Text of the link</span>
        <input name="home_label" maxlength="40" placeholder="dwens.gamer.gd" value="<?= gp_h($s['home_label']) ?>">
      </label>
    </section>

    <section class="panel">
      <h2>Hosting</h2>
      <label class="field"><span>How often each player's screen checks for news</span>
        <?= $select('poll_ms', [
            1500 => 'Every 1.5 s (snappier, ~2,400 requests per player per hour)',
            2000 => 'Every 2 s (recommended, ~1,800 per player per hour)',
            3000 => 'Every 3 s (saves quota, ~1,200 per player per hour)',
            5000 => 'Every 5 s (lowest use, ~720 per player per hour)',
        ], $s['poll_ms']) ?>
      </label>
      <p class="muted small">InfinityFree allows about 50,000 requests a day for the whole site.</p>
      <label class="field"><span>Delete tables with no activity after</span>
        <?= $select('room_ttl_hours', [6 => '6 hours', 12 => '12 hours', 24 => '1 day', 48 => '2 days', 72 => '3 days', 168 => '1 week'], $s['room_ttl_hours']) ?>
      </label>
    </section>

    <div class="row end"><button class="btn primary">Save settings</button></div>
  </form>
<?php
}
