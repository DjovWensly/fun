<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';

$code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($_GET['c'] ?? '')));
$st = gp_db()->prepare('SELECT game FROM gp_rooms WHERE code = ?');
$st->execute([$code]);
$game = $st->fetchColumn();

if (!$game || !isset(gp_catalog()[$game])) {
    http_response_code(404);
    gp_header('site_title');
    ?>
    <main class="wrap center">
      <div class="panel">
        <h2 data-i18n="err_room_not_found"></h2>
        <p><a class="btn primary" href="index.php" data-i18n="choose_game"></a></p>
      </div>
    </main>
    <script src="assets/js/common.js?v=<?= GP_ASSET_VERSION ?>"></script>
    <?php
    gp_footer();
    exit;
}

gp_header('site_title', $code);
?>
<main class="wrap room" id="room" data-code="<?= gp_h($code) ?>" data-game="<?= gp_h($game) ?>">
  <div class="room-head">
    <div>
      <h1 class="room-title" data-i18n="g_<?= gp_h($game) ?>"></h1>
      <button type="button" class="room-code" id="code-btn" title="Copy">
        <span data-i18n="code"></span> <strong><?= gp_h($code) ?></strong>
      </button>
    </div>
    <div class="row">
      <button type="button" class="btn small ghost" id="look-btn" data-i18n="look_btn"></button>
      <button type="button" class="btn small" id="share-btn" data-i18n="share" hidden></button>
      <button type="button" class="btn small ghost" id="leave-btn" data-i18n="leave" hidden></button>
    </div>
  </div>

  <div id="net-status" class="net-status" hidden data-i18n="connection_lost"></div>

  <!-- shown when this browser is not (yet) a player in the room -->
  <section class="panel" id="join-section" hidden>
    <h2 data-i18n="join_this"></h2>
    <form id="room-join-form" class="row wrap-row">
      <input id="room-join-name" maxlength="20" autocomplete="nickname" data-i18n-ph="name_ph" required>
      <button class="btn primary" type="submit" id="join-play-btn" data-i18n="join"></button>
      <button class="btn" type="button" id="join-watch-btn" data-i18n="watch"></button>
    </form>
    <p class="muted" id="join-blocked" hidden></p>
  </section>

  <!-- shown to spectators -->
  <div class="spectator-bar" id="spectator-bar" hidden>
    <span data-i18n="you_watch"></span>
    <button type="button" class="btn small" id="sit-btn" data-i18n="take_seat" hidden></button>
  </div>

  <!-- waiting room -->
  <section id="lobby-section" class="lobby-grid" hidden>
    <div class="panel share-panel">
      <p class="label" data-i18n="waiting_players"></p>
      <div class="big-code"><?= gp_h($code) ?></div>
      <div class="row wrap-row center-row">
        <button type="button" class="btn" id="copy-btn" data-i18n="copy_link"></button>
        <a class="btn whatsapp" id="wa-btn" target="_blank" rel="noopener" data-i18n="share_whatsapp"></a>
        <button type="button" class="btn" id="qr-btn" data-i18n="show_qr"></button>
      </div>
      <div id="qr" class="qr" hidden></div>
    </div>
    <div class="panel">
      <h2 data-i18n="players"></h2>
      <p class="mode-summary" id="mode-summary"></p>
      <ul class="player-list" id="lobby-players"></ul>
      <div class="row wrap-row" id="host-controls" hidden>
        <button type="button" class="btn" id="add-bot-btn" data-i18n="add_bot"></button>
        <button type="button" class="btn" id="switch-btn-lobby" data-i18n="switch_game"></button>
        <button type="button" class="btn primary" id="start-btn" data-i18n="start"></button>
      </div>
      <p class="muted" id="lobby-hint"></p>
      <div class="spectators" id="lobby-spectators" hidden></div>
    </div>
  </section>

  <!-- the game itself (drawn by assets/js/games/<game>.js) -->
  <section id="game-section" hidden></section>

  <details class="panel host-menu" id="host-menu" hidden>
    <summary data-i18n="manage_table"></summary>
    <ul class="player-list" id="game-players"></ul>
    <div class="spectators" id="game-spectators" hidden></div>
    <div class="row wrap-row">
      <button type="button" class="btn small" id="switch-btn-game" data-i18n="switch_game"></button>
      <button type="button" class="btn small" id="back-lobby-btn" data-i18n="back_lobby"></button>
    </div>
  </details>

  <!-- results of every game played at this table tonight -->
  <details class="panel history" id="history" hidden>
    <summary><span data-i18n="history"></span> <span class="muted" id="history-count"></span></summary>
    <div id="history-tally" class="history-tally"></div>
    <ul id="history-list" class="history-list"></ul>
  </details>

  <p class="center muted" id="loading" data-i18n="loading"></p>
</main>

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<script src="assets/js/common.js?v=<?= GP_ASSET_VERSION ?>"></script>
<?php foreach (gp_catalog()[$game]['scripts'] ?? [] as $dep): ?>
<script src="<?= gp_h($dep) ?>?v=<?= GP_ASSET_VERSION ?>"></script>
<?php endforeach; ?>
<script src="assets/js/games/<?= gp_h($game) ?>.js?v=<?= GP_ASSET_VERSION ?>"></script>
<script src="assets/js/options.js?v=<?= GP_ASSET_VERSION ?>"></script>
<script src="assets/js/sound.js?v=<?= GP_ASSET_VERSION ?>"></script>
<script src="assets/js/look.js?v=<?= GP_ASSET_VERSION ?>"></script>
<script src="assets/js/emotes.js?v=<?= GP_ASSET_VERSION ?>"></script>
<script src="assets/js/room.js?v=<?= GP_ASSET_VERSION ?>"></script>
<?php gp_footer(); ?>
