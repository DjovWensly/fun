# Game Party / Soirée Jeux 🎲

Small multiplayer party games for friends, written in plain **PHP + MySQL + JavaScript** (no framework,
no build step) so it runs on free shared hosting like **InfinityFree**.

| Game | Players | Modes |
|---|---|---|
| **Dominoes / Dominos** (double-six) | 2–4 | Online table or solo against 1–3 bots. Variants: **draw game** (draw from the boneyard when stuck), **block game** (no drawing, you pass), **all fives** (score when the open ends add up to a multiple of 5). Optional **2 vs 2 teams** with 4 players. Win at a number of points or of rounds won |
| **Truth or Dare / Action ou Vérité** | 2+ | Three levels: tame, party, and **very spicy (18+)** for consenting adults (flirty and suggestive, never crude; anyone can skip). Online table (join any time, custom cards) · One phone, pass it around |
| **Tic-Tac-Toe / Morpion** | 2 | Online or against a bot. Classic **3×3** (three in a row) or a big squared-paper grid of **10×10, 15×15, 19×19 or 25×25** (five in a row), with zoom and scrolling |
| **Qui de nous deux ? / Which of us?** | 2+ | A question appears (“…cuisine le mieux ?”), everyone secretly picks a player, then the answers are revealed. Two players get a *complicité* score; in a group the most-picked player scores |
| **Tu préfères… ? / Would you rather…?** | 2+ | Two choices, everyone votes in secret, then you see who picked what and how often the table agrees |
| **Dessine et devine / Draw & guess** | 2–10 | Each player draws a word (picked from 3) while the others type guesses. Timer, letter hints at half and three-quarter time, points for speed, “so close!” hints, players can join mid-game |
| **Mot mystère / Mystery word** | 1–8 | Find a hidden 5-, 6- or 7-letter word in 6 tries (right place / elsewhere / absent). Alone, or racing the table on the same word; you see the others' marks but not their letters. Optional first letter, FR/EN words editable in the admin (type **mystery**) |

During a game, the round button in the bottom corner sends a **reaction**: 8 emoji and 6 quick phrases
(“Bien joué !”, “Oups…”, “On en refait une ?”…). It pops up as a bubble over your name on everyone's screen,
in the player's own language. Each player can mute other people's reactions.

The interface is in **French, English and Haitian Creole** (FR / EN / KR buttons; a phone set to Kreyòl gets it automatically) (FR/EN switch at the top).

## At the table

- **Switch game, same players**: the host picks another game (Settings window included) and everyone moves to it, no new code needed. Bots leave if the new game has none.
- **Spectators**: anyone with the link can **watch** a game, even when it has started or the table is full, send reactions, and **take a seat** when the game allows it.
- **Tonight's results**: every finished match (dominoes, tic-tac-toe, draw & guess, mystery word) is recorded with the players' names, with a wins tally, and survives game switches.
- **Look and sound** (button *Apparence*): sounds and vibration on/off, domino style (classic, coloured dots, night, red dots), table colour (green, blue, burgundy, black, light wood) and tic-tac-toe paper (squared, yellow notepad, blackboard). Stored on each device.

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
   `room.php`, `local.php`, `admin.php`, `check.php`, `config.php`, `.htaccess`, and the folders `assets/`, `data/`, `inc/`.
   (`install.sql`, `README.md` and `config.sample.php` don't need to be online.)
5. Open `https://dwens.gamer.gd/games/check.php`: every line should be green.
6. **Link it from your main page**, e.g. in `htdocs/index.html` / `index.php`:

   ```html
   <a href="/games/">🎲 Jeux entre amis</a>
   ```

   You can also link straight to a game's join box: `/games/index.php?join=K7XQM`.

7. **Set an admin password** (optional): put `'admin_password' => '...'` in `config.php`, then open
   `https://dwens.gamer.gd/games/admin.php` to manage the Truth or Dare questions.

Changed CSS/JS but phones still show the old version? Increase `GP_ASSET_VERSION` in `inc/layout.php`.

## Admin page (`admin.php`)

Three tabs, behind the `admin_password` from `config.php`:

- **Questions**: the Truth or Dare cards (see below).
- **Reactions**: the emoji and phrases players can send during a game. Add, edit, reorder or remove them
  (up to 16), with a French and an English text for each phrase. Defaults live in `data/reactions.json`,
  and "Reset to the defaults" brings them back. Reactions can also be switched off.
- **Settings**: site name, home page title and sentence (FR and EN, empty = built-in text), language for
  new visitors, which games appear on the home page, the link back to your main site, how often screens
  refresh (to save InfinityFree's daily quota) and how long idle tables are kept.

Settings are stored in the `gp_settings` table, created automatically on the first save.

## Managing Truth or Dare questions (`admin.php`)

Log in with the `admin_password` from `config.php` (a plain password or a `password_hash()` value). From there you can:

- **Add** questions for a language (fr/en), level (soft/party) and type (truth/dare). Paste several lines to add many at once (one question per line).
- **Edit** the text, move a question to another language/level/type, or **disable** it without deleting it.
- **Delete** one question, or select several and disable/enable/delete them together.
- **Filter** by language, level, type and status, or **search** the text.
- **Export** everything as JSON (same format as `data/prompts.json`) as a backup, and **re-import** the built-in questions.

The Questions tab also holds the cards of *Qui de nous deux* (type **who**: the end of the question,
e.g. “cuisine le mieux ?”), *Tu préfères* (type **wyr**: two choices separated by `||`) and the words of
*Dessine et devine* (type **word**).

Questions are stored in the `gp_prompts` table, which `admin.php` creates on its own if you haven't re-imported `install.sql`.
Until you add your first question, the game uses `data/prompts.json`. The first time you add one, the built-in questions are
copied into the table automatically, so you can edit or delete them as well. Changes apply to the next card drawn, even in games already running.

### Keeping within the free plan

*Dessine et devine* refreshes twice as often while a game is running so the drawing appears quickly,
and the drawer sends strokes about twice a second while drawing: count roughly 3,600 requests per
player per hour for that game.


InfinityFree counts every request ("hits", ~50,000/day). One player polling for an hour is about
1,800 hits, so a 4-player hour of dominoes is roughly 7,000. That's plenty for friends; if you ever get
close, raise the delays in `schedule()` in `assets/js/room.js`. Rooms with no activity for 24 h are
deleted automatically (`room_ttl_hours` in `config.php`).

## Something doesn't work? Open `check.php`

`check.php` checks the PHP version and extensions, `config.php`, the connection to MySQL, the database,
the tables, and the game API. It explains in plain words what to fix for each red line, and it can create
missing tables with one click. Your password is never shown. You can delete it once everything is green.

If the database can't be reached, the games themselves now show the reason in a red box at the top of
the page instead of a generic "connection problem".

## Running it on your computer

With **XAMPP** (Windows/Mac/Linux):

1. Start **Apache** and **MySQL** in the XAMPP Control Panel (both must be green).
2. Put the project in `C:\xampp\htdocs\fun\`.
3. In `http://localhost/phpmyadmin`: **New** → create a database `games` → **Import** → `install.sql`.
4. Create `config.php`:

   ```php
   <?php
   return [
       'db_host' => 'localhost',
       'db_name' => 'games',
       'db_user' => 'root',
       'db_pass' => '',
       'admin_password' => 'choose-one',
       'home_url' => 'https://dwens.gamer.gd/',
       'room_ttl_hours' => 24,
   ];
   ```

5. Open `http://localhost/fun/check.php`; when everything is green, go to `http://localhost/fun/`.
To test with several players on one computer, use different browsers or private windows (each one is a
separate player).

## Changing the name, footer, icon and texts

`index.php` only holds the middle of the home page. The rest lives here:

| What | Easiest way | In the code |
|---|---|---|
| Site name (top bar, tab title, footer) | admin.php → Settings → Site name | `site_title` in `assets/js/common.js` |
| Home page title and sentence | admin.php → Settings | `hero_title`, `tagline` in `assets/js/common.js` |
| Footer text (`{year}`, `{site}` are replaced) | admin.php → Settings → Footer text | `footer_text` in `assets/js/common.js`; HTML in `gp_footer()` in `inc/layout.php` |
| Link back to the main site | admin.php → Settings | `gp_footer()` / `gp_header()` in `inc/layout.php` |
| Tab icon | replace `img/favicon.ico` (or put `img/favicon.png` / `img/favicon.svg`) | `gp_icon_tags()` in `inc/layout.php` |
| Phone home-screen icon | replace `img/apple-touch-icon.png` (180 × 180) | same |
| Every other text (buttons, games…) | — | the `fr` and `en` lists at the top of `assets/js/common.js` |
| Colours and fonts | — | the variables at the top of `assets/css/style.css` |

## Project layout

```
index.php            home: pick a name, join with a code, create a room
room.php             the room page (waiting room + game)
local.php            Truth or Dare on a single phone (no database)
check.php            setup check (PHP, config, database, tables, API)
img/                 favicon.ico and apple-touch-icon.png (replace with your own)
assets/js/options.js settings window for each game (home page and "switch game")
assets/js/sound.js   sounds made with Web Audio (no audio files)
assets/js/look.js    "Apparence" panel (domino style, table colour, sound)
api.php              JSON API: create, join, state (polling), start, add_bot, kick, leave, move, lobby, prompts
install.sql          database tables (gp_rooms, gp_players)
config.sample.php    copy to config.php
inc/bootstrap.php    database connection, helpers, game registry
inc/layout.php       page header/footer, game catalogue (icon, colour)
inc/games/*.php      game rules (server-side, authoritative)
assets/js/common.js  translations (FR/EN), API helper, small UI helpers
assets/js/room.js    waiting room, polling, sharing
assets/js/games/*.js how each game is drawn in the browser
admin.php            password-protected page to manage Truth or Dare questions
inc/prompts.php      loads questions from gp_prompts (or data/prompts.json while empty)
data/prompts.json    built-in cards (fr/en × soft/party): truth, dare, who, wyr ("A || B"), word
data/reactions.json  built-in reactions (emoji + phrases)
inc/settings.php     site settings (gp_settings table) with defaults
inc/admin_tabs.php   admin tabs Reactions and Settings
```

## After changing files

Run `php tools/manifest.php`. It refreshes `data/manifest.json`, the fingerprints `check.php` uses to say
which files on the server are missing or still an older version after an upload. (`tools/` itself is not
uploaded.)

## Adding a new game

1. Create `inc/games/MyGame.php` extending `GameEngine` (`meta`, `init`, `move`, `view`, and optionally
   `currentSeat` + `botMove` for bots, `addSeat`/`removeSeat` for drop-in games).
2. Register it in `gp_games()` (`inc/bootstrap.php`) and `gp_catalog()` (`inc/layout.php`).
3. Create `assets/js/games/mygame.js` that registers `GP.games.mygame = { mount, update }`.
4. Add `g_mygame` / `g_mygame_d` (name + description) in both languages in `assets/js/common.js`.

Ideas that fit this design: Connect Four, Ludo, Hangman/Pendu, "Never have I ever", 4-player team dominoes.
