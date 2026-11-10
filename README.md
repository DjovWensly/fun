# Game Party / Soirée Jeux 🎲

Small multiplayer party games for friends, written in plain **PHP + MySQL + JavaScript** (no framework,
no build step) so it runs on free shared hosting like **InfinityFree**.

| Game | Players | Modes |
|---|---|---|
| **Dominoes / Dominos** (double-six) | 2–4 | Online room · Solo vs 1–3 bots |
| **Truth or Dare / Action ou Vérité** | 2+ | Online room (join any time, custom cards) · One phone, pass it around (no internet needed after the page loads) |
| **Tic-Tac-Toe / Morpion** | 2 | Online room · Solo vs bot |

The interface is in **French and English** (FR/EN switch at the top).

## How playing together works

1. A player picks a game and clicks **Create a room**. The server gives the room a **5-character code** (for example `K7XQM`).
2. They share it: the code itself, a link, a **WhatsApp** button or a **QR code** for friends in the same place.
3. Friends open the link (or type the code on the home page), enter their name and land in the waiting room.
4. The host can add bots, remove people, then **Start**.

No accounts: each browser gets a random secret token for the room (kept in `localStorage`), so refreshing
the page keeps your seat. If someone leaves during dominoes or tic-tac-toe, a bot takes over their seat so
the game can go on; the host can also replace an absent player with a bot.

### Why polling and not WebSockets?

InfinityFree (and most free PHP hosts) can't run WebSockets or long-lived processes. Each browser asks
the server for news every 2 seconds (every 6 s when the tab is hidden). Every room has a `version` number,
so when nothing changed the answer is a tiny `{"same":true}`. The game state is kept on the server and
every move is checked there, so nobody can cheat by editing the JavaScript.

Bots play one move per second or so, which lets everyone see what happened.

## Install on InfinityFree

1. **Create a database**: Control Panel → *MySQL Databases* → create one (e.g. `games`).
   Note the host (`sqlXXX.infinityfree.com`), database name (`if0_XXXXXXX_games`), user (`if0_XXXXXXX`)
   and your hosting password.
2. **Create the tables**: open *phpMyAdmin* for that database → *Import* → choose `install.sql` → *Go*.
3. **Configure**: copy `config.sample.php` to `config.php` and fill in the values from step 1.
4. **Upload** everything with the *Online File Manager* or FileZilla into
   `htdocs/games/` (so it lives at `https://dwens.gamer.gd/games/`). Upload: `api.php`, `index.php`,
   `room.php`, `local.php`, `config.php`, `.htaccess`, and the folders `assets/`, `data/`, `inc/`.
   (`install.sql`, `README.md` and `config.sample.php` don't need to be online.)
5. **Link it from your main page**, e.g. in `htdocs/index.html` / `index.php`:

   ```html
   <a href="/games/">🎲 Jeux entre amis</a>
   ```

   You can also link straight to a game's join box: `/games/index.php?join=K7XQM`.

Changed CSS/JS but phones still show the old version? Increase `GP_ASSET_VERSION` in `inc/layout.php`.

### Keeping within the free plan

InfinityFree counts every request ("hits", ~50,000/day). One player polling for an hour is about
1,800 hits, so a 4-player hour of dominoes is roughly 7,000. That's plenty for friends; if you ever get
close, raise the delays in `schedule()` in `assets/js/room.js`. Rooms with no activity for 24 h are
deleted automatically (`room_ttl_hours` in `config.php`).

## Running it on your computer

Use XAMPP/WAMP/Laragon (or `php -S localhost:8080` with a local MySQL/MariaDB), import `install.sql`,
create `config.php` with `db_host => 'localhost'`, and open `http://localhost:8080/`.
To test with several players on one computer, use different browsers or private windows (each one is a
separate player).

## Project layout

```
index.php            home: pick a name, join with a code, create a room
room.php             the room page (waiting room + game)
local.php            Truth or Dare on a single phone (no database)
api.php              JSON API: create, join, state (polling), start, add_bot, kick, leave, move, lobby
install.sql          database tables (gp_rooms, gp_players)
config.sample.php    copy to config.php
inc/bootstrap.php    database connection, helpers, game registry
inc/layout.php       page header/footer, game catalogue (icon, colour)
inc/games/*.php      game rules (server-side, authoritative)
assets/js/common.js  translations (FR/EN), API helper, small UI helpers
assets/js/room.js    waiting room, polling, sharing
assets/js/games/*.js how each game is drawn in the browser
data/prompts.json    Truth or Dare cards (fr/en × soft/party × truth/dare) — edit freely
```

## Adding a new game

1. Create `inc/games/MyGame.php` extending `GameEngine` (`meta`, `init`, `move`, `view`, and optionally
   `currentSeat` + `botMove` for bots, `addSeat`/`removeSeat` for drop-in games).
2. Register it in `gp_games()` (`inc/bootstrap.php`) and `gp_catalog()` (`inc/layout.php`).
3. Create `assets/js/games/mygame.js` that registers `GP.games.mygame = { mount, update }`.
4. Add `g_mygame` / `g_mygame_d` (name + description) in both languages in `assets/js/common.js`.

Ideas that fit this design: Connect Four, Ludo, Hangman/Pendu, "Never have I ever", 4-player team dominoes.
