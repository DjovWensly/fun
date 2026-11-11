<?php
declare(strict_types=1);

/**
 * Admin: manage Truth or Dare questions (add, bulk add, edit, enable/disable, delete, import, export).
 * Protected by 'admin_password' in config.php.
 */

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/admin_tabs.php';

session_name('gp_admin');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();

$cfgPass = (string)(gp_config()['admin_password'] ?? '');

function admin_password_ok(string $given, string $configured): bool
{
    if ($configured === '' || $given === '') {
        return false;
    }
    // Accept either a password_hash() value or a plain password.
    if (strpos($configured, '$2y$') === 0 || strpos($configured, '$argon2') === 0) {
        return password_verify($given, $configured);
    }
    return hash_equals($configured, $given);
}

function csrf(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

/** Keep the current filters when redirecting after a POST. */
function back(array $extra = []): void
{
    $keep = array_intersect_key($_POST + $_GET, array_flip(['f_lang', 'f_level', 'f_type', 'f_status', 'q', 'tab']));
    $qs = http_build_query(array_filter($keep + $extra, 'admin_nonempty'));
    header('Location: admin.php' . ($qs ? '?' . $qs : ''));
    exit;
}

function admin_nonempty($v): bool
{
    return (string)$v !== '';
}

function clean_text($s): string
{
    $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
    return mb_substr($s, 0, 300);
}

function pick(string $v, array $allowed): string
{
    if (!isset($allowed[$v])) {
        throw new GameError('invalid value');
    }
    return $v;
}

function prompt_count(): int
{
    return (int)gp_db()->query('SELECT COUNT(*) FROM gp_prompts')->fetchColumn();
}

/** The first time someone edits the list, copy the built-in questions so they don't disappear. */
function ensure_seeded(): void
{
    if (prompt_count() === 0) {
        $n = gp_prompts_import_defaults();
        if ($n > 0) {
            flash("The $n built-in questions were imported first, so you can edit them too.", 'info');
        }
    }
}

$loggedIn = !empty($_SESSION['admin']);

// ---------------------------------------------------------------------------------------- POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'login') {
        if (admin_password_ok((string)($_POST['password'] ?? ''), $cfgPass)) {
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
        } else {
            sleep(1); // slow down password guessing
            flash('Wrong password.', 'error');
        }
        back();
    }

    if (!$loggedIn || !hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Session expired. <a href="admin.php">Reload</a>');
    }

    // Reactions and Settings tabs
    $tabResult = gp_admin_tabs_post($action);
    if ($tabResult !== null) {
        flash($tabResult[1], $tabResult[0]);
        back();
    }

    gp_prompts_ensure_table();
    $db = gp_db();
    $now = gp_now();

    try {
        switch ($action) {
            case 'logout':
                $_SESSION = [];
                session_destroy();
                header('Location: admin.php');
                exit;

            case 'add':
                ensure_seeded();
                $lang = pick((string)$_POST['lang'], GP_PROMPT_LANGS);
                $level = pick((string)$_POST['level'], GP_PROMPT_LEVELS);
                $type = pick((string)$_POST['type'], GP_PROMPT_TYPES);
                $lines = preg_split('/\R/u', (string)($_POST['text'] ?? ''));
                $ins = $db->prepare('INSERT INTO gp_prompts (lang, level, type, text, active, created_at, updated_at) VALUES (?,?,?,?,1,?,?)');
                $n = 0;
                foreach ($lines as $line) {
                    $text = clean_text($line);
                    if (mb_strlen($text) >= 3) {
                        $ins->execute([$lang, $level, $type, $text, $now, $now]);
                        $n++;
                    }
                }
                flash($n ? "$n question(s) added." : 'Nothing added: write at least 3 characters per line.', $n ? 'ok' : 'error');
                back();

            case 'update':
                $id = (int)($_POST['id'] ?? 0);
                $text = clean_text($_POST['text'] ?? '');
                if (mb_strlen($text) < 3) {
                    flash('The question is too short.', 'error');
                    back(['edit' => $id]);
                }
                $db->prepare('UPDATE gp_prompts SET lang = ?, level = ?, type = ?, text = ?, active = ?, updated_at = ? WHERE id = ?')
                    ->execute([
                        pick((string)$_POST['lang'], GP_PROMPT_LANGS),
                        pick((string)$_POST['level'], GP_PROMPT_LEVELS),
                        pick((string)$_POST['type'], GP_PROMPT_TYPES),
                        $text,
                        empty($_POST['active']) ? 0 : 1,
                        $now,
                        $id,
                    ]);
                flash('Question updated.');
                back();

            case 'toggle':
                $db->prepare('UPDATE gp_prompts SET active = 1 - active, updated_at = ? WHERE id = ?')
                    ->execute([$now, (int)($_POST['id'] ?? 0)]);
                back();

            case 'delete':
                $db->prepare('DELETE FROM gp_prompts WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
                flash('Question deleted.');
                back();

            case 'bulk':
                $ids = array_values(array_filter(array_map('intval', (array)($_POST['ids'] ?? []))));
                if (!$ids) {
                    flash('Select at least one question first.', 'error');
                    back();
                }
                $in = implode(',', array_fill(0, count($ids), '?'));
                $op = (string)($_POST['bulk_op'] ?? '');
                if ($op === 'delete') {
                    $db->prepare("DELETE FROM gp_prompts WHERE id IN ($in)")->execute($ids);
                    flash(count($ids) . ' question(s) deleted.');
                } elseif ($op === 'enable' || $op === 'disable') {
                    $db->prepare("UPDATE gp_prompts SET active = ?, updated_at = ? WHERE id IN ($in)")
                        ->execute(array_merge([$op === 'enable' ? 1 : 0, $now], $ids));
                    flash(count($ids) . ' question(s) ' . ($op === 'enable' ? 'enabled' : 'disabled') . '.');
                }
                back();

            case 'import':
                $n = gp_prompts_import_defaults();
                flash($n ? "$n built-in question(s) imported." : 'All built-in questions are already in the list.', 'info');
                back();
        }
    } catch (GameError $e) {
        flash('Invalid value.', 'error');
        back();
    }
    back();
}

// ---------------------------------------------------------------------------------------- export
if ($loggedIn && isset($_GET['export'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="prompts-' . gmdate('Y-m-d') . '.json"');
    echo json_encode(gp_prompts_load(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ---------------------------------------------------------------------------------------- page
gp_header('site_title', 'Admin');
$flashes = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

function select_html(string $name, array $opts, string $value, bool $withAll = false): string
{
    $h = '<select name="' . gp_h($name) . '">';
    if ($withAll) {
        $h .= '<option value="">All</option>';
    }
    foreach ($opts as $k => $label) {
        $h .= '<option value="' . gp_h($k) . '"' . ($k === $value ? ' selected' : '') . '>' . gp_h($label) . '</option>';
    }
    return $h . '</select>';
}
?>
<main class="wrap admin">
  <h1 class="room-title">Admin</h1>
  <?php foreach ($flashes as [$type, $msg]): ?>
    <p class="flash <?= gp_h($type) ?>"><?= gp_h($msg) ?></p>
  <?php endforeach; ?>

<?php if ($cfgPass === ''): ?>
  <div class="panel">
    <h2>Admin is disabled</h2>
    <p>Add a password to <code>config.php</code>, for example:</p>
    <pre>'admin_password' => 'choose-a-long-password',</pre>
  </div>
<?php elseif (!$loggedIn): ?>
  <form class="panel login" method="post">
    <input type="hidden" name="action" value="login">
    <label class="field"><span>Admin password</span>
      <input type="password" name="password" autocomplete="current-password" required autofocus>
    </label>
    <button class="btn primary">Log in</button>
  </form>
<?php else:
    $tab = in_array($_GET['tab'] ?? '', ['reactions', 'settings'], true) ? $_GET['tab'] : 'questions';
    echo gp_admin_tabs_nav($tab);
    if ($tab !== 'questions'):
        $csrfField = '<input type="hidden" name="csrf" value="' . gp_h(csrf()) . '">';
        if ($tab === 'reactions') {
            gp_admin_reactions_page($csrfField);
        } else {
            gp_admin_settings_page($csrfField);
        }
    else:
    gp_prompts_ensure_table();
    $db = gp_db();
    $total = prompt_count();
    $f = [
        'lang'   => isset(GP_PROMPT_LANGS[$_GET['f_lang'] ?? '']) ? $_GET['f_lang'] : '',
        'level'  => isset(GP_PROMPT_LEVELS[$_GET['f_level'] ?? '']) ? $_GET['f_level'] : '',
        'type'   => isset(GP_PROMPT_TYPES[$_GET['f_type'] ?? '']) ? $_GET['f_type'] : '',
        'status' => in_array($_GET['f_status'] ?? '', ['active', 'inactive'], true) ? $_GET['f_status'] : '',
        'q'      => trim((string)($_GET['q'] ?? '')),
    ];
    $where = [];
    $args = [];
    foreach (['lang', 'level', 'type'] as $k) {
        if ($f[$k] !== '') {
            $where[] = "$k = ?";
            $args[] = $f[$k];
        }
    }
    if ($f['status'] !== '') {
        $where[] = 'active = ' . ($f['status'] === 'active' ? 1 : 0);
    }
    if ($f['q'] !== '') {
        $where[] = 'text LIKE ?';
        $args[] = '%' . addcslashes($f['q'], '%_\\') . '%';
    }
    $st = $db->prepare('SELECT * FROM gp_prompts' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY lang, level, type, id');
    $st->execute($args);
    $rows = $st->fetchAll();

    $counts = [];
    foreach ($db->query('SELECT lang, level, type, SUM(active) AS a, COUNT(*) AS n FROM gp_prompts GROUP BY lang, level, type') as $c) {
        $counts[$c['lang']][$c['level']][$c['type']] = [(int)$c['a'], (int)$c['n']];
    }

    $editRow = null;
    if (isset($_GET['edit'])) {
        $e = $db->prepare('SELECT * FROM gp_prompts WHERE id = ?');
        $e->execute([(int)$_GET['edit']]);
        $editRow = $e->fetch() ?: null;
    }
    $filterInputs = '';
    foreach (['f_lang' => $f['lang'], 'f_level' => $f['level'], 'f_type' => $f['type'], 'f_status' => $f['status'], 'q' => $f['q']] as $k => $v) {
        if ($v !== '') {
            $filterInputs .= '<input type="hidden" name="' . $k . '" value="' . gp_h($v) . '">';
        }
    }
    $csrf = '<input type="hidden" name="csrf" value="' . gp_h(csrf()) . '">';
?>
  <div class="row wrap-row admin-bar">
    <a class="btn small" href="admin.php?export=1">Export JSON</a>
    <form method="post"><?= $csrf ?><input type="hidden" name="action" value="import">
      <button class="btn small" title="Adds the questions from data/prompts.json that are not in the list yet">Import built-in questions</button></form>
    <form method="post"><?= $csrf ?><input type="hidden" name="action" value="logout"><button class="btn small ghost">Log out</button></form>
  </div>

  <?php if ($total === 0): ?>
    <p class="flash info">The game is currently using the built-in questions from <code>data/prompts.json</code>.
      As soon as you add a question they are imported here automatically, so you can edit or delete them.</p>
  <?php else: ?>
    <div class="panel counts">
      <table>
        <tr><th></th><?php foreach (GP_PROMPT_LEVELS as $lv => $lvl): foreach (GP_PROMPT_TYPES as $ty => $tyl): ?>
          <th><?= gp_h($lv) ?><br><?= gp_h($ty) ?></th><?php endforeach; endforeach; ?></tr>
        <?php foreach (GP_PROMPT_LANGS as $l => $ll): ?>
          <tr><th><?= gp_h($l) ?></th><?php foreach (GP_PROMPT_LEVELS as $lv => $_): foreach (GP_PROMPT_TYPES as $ty => $_2):
            $c = $counts[$l][$lv][$ty] ?? [0, 0]; ?>
            <td class="<?= $c[0] === 0 ? 'zero' : '' ?>"><?= $c[0] ?><?= $c[1] !== $c[0] ? '<small> /' . $c[1] . '</small>' : '' ?></td>
          <?php endforeach; endforeach; ?></tr>
        <?php endforeach; ?>
      </table>
      <p class="muted small">Active questions per deck (/ total when some are disabled). "Party" games use soft + party questions.</p>
    </div>
  <?php endif; ?>

  <?php if ($editRow): ?>
    <form class="panel edit-form" method="post" id="edit">
      <h2>Edit question #<?= (int)$editRow['id'] ?></h2>
      <?= $csrf . $filterInputs ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$editRow['id'] ?>">
      <div class="admin-selects">
        <?= select_html('lang', GP_PROMPT_LANGS, $editRow['lang']) ?>
        <?= select_html('level', GP_PROMPT_LEVELS, $editRow['level']) ?>
        <?= select_html('type', GP_PROMPT_TYPES, $editRow['type']) ?>
      </div>
      <textarea name="text" rows="3" maxlength="300" required><?= gp_h($editRow['text']) ?></textarea>
      <label class="check"><input type="checkbox" name="active" value="1" <?= $editRow['active'] ? 'checked' : '' ?>> Active (can be drawn in games)</label>
      <div class="row end">
        <a class="btn ghost" href="admin.php?<?= gp_h(http_build_query(array_filter(['f_lang' => $f['lang'], 'f_level' => $f['level'], 'f_type' => $f['type'], 'f_status' => $f['status'], 'q' => $f['q']], 'admin_nonempty'))) ?>">Cancel</a>
        <button class="btn primary">Save</button>
      </div>
    </form>
  <?php endif; ?>

  <details class="panel" <?= $editRow ? '' : 'open' ?>>
    <summary>Add questions</summary>
    <form method="post" class="add-form">
      <?= $csrf . $filterInputs ?>
      <input type="hidden" name="action" value="add">
      <div class="admin-selects">
        <?= select_html('lang', GP_PROMPT_LANGS, $f['lang'] ?: 'fr') ?>
        <?= select_html('level', GP_PROMPT_LEVELS, $f['level'] ?: 'soft') ?>
        <?= select_html('type', GP_PROMPT_TYPES, $f['type'] ?: 'truth') ?>
      </div>
      <textarea name="text" rows="4" required placeholder="One question per line. Paste a whole list to add many at once."></textarea>
      <p class="muted small">Formats: <strong>Qui de nous</strong> is the end of the question, e.g. <em>cuisine le mieux ?</em>
        (the game adds "Qui de nous deux"). <strong>Tu préfères</strong> is two choices separated by <code>||</code>,
        e.g. <em>La mer || La montagne</em>. <strong>Mot à dessiner</strong> is one word or short expression.</p>
      <div class="row end"><button class="btn primary">Add</button></div>
    </form>
  </details>

  <form class="panel filters" method="get">
    <div class="admin-selects">
      <?= select_html('f_lang', GP_PROMPT_LANGS, $f['lang'], true) ?>
      <?= select_html('f_level', GP_PROMPT_LEVELS, $f['level'], true) ?>
      <?= select_html('f_type', GP_PROMPT_TYPES, $f['type'], true) ?>
      <?= select_html('f_status', ['active' => 'Active', 'inactive' => 'Disabled'], $f['status'], true) ?>
    </div>
    <div class="row">
      <input type="search" name="q" value="<?= gp_h($f['q']) ?>" placeholder="Search text…">
      <button class="btn">Filter</button>
      <a class="btn ghost" href="admin.php">Reset</a>
    </div>
  </form>

  <form method="post" id="bulk-form" onsubmit="return this.bulk_op.value !== 'delete' || confirm('Delete the selected questions?');">
    <?= $csrf . $filterInputs ?>
    <input type="hidden" name="action" value="bulk">
    <div class="row wrap-row bulk-bar">
      <label class="check"><input type="checkbox" id="check-all"> <span><?= count($rows) ?> shown</span></label>
      <select name="bulk_op">
        <option value="disable">Disable selected</option>
        <option value="enable">Enable selected</option>
        <option value="delete">Delete selected</option>
      </select>
      <button class="btn small">Apply</button>
    </div>

    <ul class="prompt-list">
      <?php foreach ($rows as $r): ?>
        <li class="<?= $r['active'] ? '' : 'disabled' ?> <?= $editRow && (int)$editRow['id'] === (int)$r['id'] ? 'editing' : '' ?>">
          <input type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" aria-label="select">
          <div class="prompt-body">
            <div class="prompt-tags">
              <span class="tag"><?= gp_h($r['lang']) ?></span>
              <span class="tag"><?= gp_h($r['level']) ?></span>
              <span class="tag <?= gp_h($r['type']) ?>"><?= $r['type'] === 'truth' ? 'truth' : 'dare' ?></span>
              <?php if (!$r['active']): ?><span class="tag muted">disabled</span><?php endif; ?>
            </div>
            <p><?= gp_h($r['text']) ?></p>
          </div>
          <div class="prompt-actions">
            <a class="btn tiny" href="admin.php?<?= gp_h(http_build_query(array_filter(['f_lang' => $f['lang'], 'f_level' => $f['level'], 'f_type' => $f['type'], 'f_status' => $f['status'], 'q' => $f['q']], 'admin_nonempty') + ['edit' => $r['id']])) ?>#edit">Edit</a>
            <button class="btn tiny" form="row-<?= (int)$r['id'] ?>-toggle"><?= $r['active'] ? 'Disable' : 'Enable' ?></button>
            <button class="btn tiny danger" form="row-<?= (int)$r['id'] ?>-delete">Delete</button>
          </div>
        </li>
      <?php endforeach; ?>
      <?php if (!$rows): ?><li class="muted">No questions match.</li><?php endif; ?>
    </ul>
  </form>

  <?php // Per-row forms live outside the bulk form (forms can't be nested); buttons point at them with form="..." ?>
  <?php foreach ($rows as $r): ?>
    <form id="row-<?= (int)$r['id'] ?>-toggle" method="post" hidden><?= $csrf . $filterInputs ?>
      <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"></form>
    <form id="row-<?= (int)$r['id'] ?>-delete" method="post" hidden onsubmit="return confirm('Delete this question?');"><?= $csrf . $filterInputs ?>
      <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"></form>
  <?php endforeach; ?>

  <script>
    document.getElementById('check-all').addEventListener('change', function (e) {
      document.querySelectorAll('.prompt-list input[type=checkbox]').forEach(function (c) { c.checked = e.target.checked; });
    });
  </script>
<?php endif; // questions tab ?>
<?php endif; ?>
</main>
<script src="assets/js/common.js?v=<?= GP_ASSET_VERSION ?>"></script>
<?php gp_footer(); ?>
