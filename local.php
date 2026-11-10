<?php
declare(strict_types=1);
// One-phone Truth or Dare: runs entirely in the browser, no room or database needed.
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
gp_header('site_title');
?>
<main class="wrap room">
  <h1 class="room-title" data-i18n="local_title"></h1>

  <section class="panel" id="setup">
    <p class="muted" data-i18n="local_hint"></p>
    <form id="add-player" class="row">
      <input id="player-input" maxlength="20" data-i18n-ph="player_ph">
      <button class="btn" type="submit" data-i18n="add_player"></button>
    </form>
    <ul class="player-list" id="local-players"></ul>
    <label class="field"><span data-i18n="opt_level"></span>
      <select id="opt-level"><option value="soft" data-i18n="lvl_soft"></option><option value="party" data-i18n="lvl_party"></option></select>
    </label>
    <label class="field"><span data-i18n="opt_order"></span>
      <select id="opt-order"><option value="circle" data-i18n="order_circle"></option><option value="random" data-i18n="order_random"></option></select>
    </label>
    <label class="field"><span data-i18n="opt_lang"></span>
      <select id="opt-lang"><option value="fr">Français</option><option value="en">English</option></select>
    </label>
    <button class="btn primary block" id="local-start" data-i18n="play"></button>
  </section>

  <section id="local-game" hidden>
    <div id="local-card"></div>
    <p class="center"><button class="btn small ghost" id="local-setup" data-i18n="players"></button></p>
  </section>
</main>
<script src="assets/js/common.js?v=<?= GP_ASSET_VERSION ?>"></script>
<script src="assets/js/games/truthdare.js?v=<?= GP_ASSET_VERSION ?>"></script>
<script src="assets/js/local-truthdare.js?v=<?= GP_ASSET_VERSION ?>"></script>
<?php gp_footer(); ?>
