<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$prefill = preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['join'] ?? ''));

gp_header('site_title');
?>
<main class="wrap">
  <section class="hero">
    <h1 data-i18n="hero_title"></h1>
    <p data-i18n="tagline"></p>
  </section>

  <section class="start-grid">
    <div class="panel" id="me-panel">
      <label class="field">
        <span data-i18n="your_name"></span>
        <input id="player-name" maxlength="20" autocomplete="nickname" data-i18n-ph="name_ph">
      </label>
    </div>
    <div class="panel ticket">
      <h2 data-i18n="join_title"></h2>
      <p class="muted small" data-i18n="join_hint"></p>
      <form id="join-form" class="row">
        <input id="join-code" class="code-input" maxlength="5" autocapitalize="characters" autocomplete="off"
               spellcheck="false" data-i18n-ph="code_ph" value="<?= gp_h(strtoupper($prefill)) ?>">
        <button class="btn primary" type="submit" data-i18n="join"></button>
      </form>
    </div>
  </section>

  <h2 class="section-title" data-i18n="choose_game"></h2>
  <section class="games">
    <?php foreach (gp_catalog() as $id => $g): if (!gp_game_enabled($id)) { continue; } $meta = gp_engine($id)->meta(); ?>
      <article class="game-card">
        <div class="game-art <?= gp_h($g['art_class']) ?>"><?= $g['art'] ?></div>
        <div class="game-body">
          <h3 data-i18n="g_<?= gp_h($id) ?>"></h3>
          <p data-i18n="g_<?= gp_h($id) ?>_d"></p>
          <p class="players-count" data-min="<?= $meta['min'] ?>" data-max="<?= $meta['max'] ?>"></p>
          <div class="row wrap-row">
            <button class="btn primary" data-create="<?= gp_h($id) ?>" data-i18n="create_room"></button>
            <?php if ($meta['bots']): ?>
              <button class="btn" data-solo="<?= gp_h($id) ?>" data-i18n="solo_bots"></button>
            <?php endif; ?>
            <?php if ($id === 'truthdare'): ?>
              <a class="btn" href="local.php" data-i18n="one_phone"></a>
            <?php endif; ?>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </section>
</main>

<dialog id="options-dialog">
  <form method="dialog" id="options-form">
    <h2 id="options-title"></h2>
    <div id="options-fields"></div>
    <div class="row end">
      <button class="btn ghost" value="cancel" data-i18n="cancel"></button>
      <button class="btn primary" value="ok" id="options-ok" data-i18n="create"></button>
    </div>
  </form>
</dialog>

<script src="assets/js/common.js?v=<?= GP_ASSET_VERSION ?>"></script>
<script src="assets/js/home.js?v=<?= GP_ASSET_VERSION ?>"></script>
<?php gp_footer(); ?>
