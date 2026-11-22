<?php
declare(strict_types=1);

/**
 * C'est plus, c'est moins — four ways to play with a hidden number:
 *  - secret   : the server hides a number (1-100, 1-1000, 1-10000). Alone, or a race on the same number:
 *               you only see how many tries the others used. Limited tries or free (best score).
 *  - estimate : a general-knowledge question with a numeric answer ("question || answer || unit").
 *               Everyone answers in secret; points for accuracy, +2 for the closest, a forfeit for the furthest.
 *  - highlow  : playing cards. Higher or lower than the current card? Build a streak, bank it or risk it.
 *               A wrong guess loses the streak (and can give a forfeit).
 *  - couple   : "Guess how much". One player answers a question about themself with a number, in secret;
 *               the others find it with "more / less" in a few tries. Levels Tiède, Chaud, Brûlant (18+).
 * Forfeits are dares from the Truth or Dare deck. Questions are editable in admin.php (types num, numduo).
 */
class PlusMoins extends GameEngine
{
    private const RANGES = [100 => 8, 1000 => 11, 10000 => 15];   // range => limited tries
    private const MODES = ['secret', 'estimate', 'highlow', 'couple'];

    public function meta(): array
    {
        return ['min' => 1, 'max' => 10, 'bots' => false, 'joinMidGame' => true];
    }

    public function options(array $in): array
    {
        $mode = in_array($in['mode'] ?? '', self::MODES, true) ? $in['mode'] : 'secret';
        $lang = in_array($in['lang'] ?? '', ['fr', 'en'], true) ? $in['lang'] : 'fr';
        $range = (int)($in['range'] ?? 100);
        $range = isset(self::RANGES[$range]) ? $range : 100;
        $level = (string)($in['level'] ?? 'soft');
        $level = in_array($level, $mode === 'couple' ? ['soft', 'party', 'hot'] : ['soft', 'party'], true) ? $level : 'soft';
        $gages = in_array($in['gages'] ?? '', ['off', 'soft', 'party'], true) ? $in['gages'] : 'soft';
        $rounds = (int)($in['rounds'] ?? 10);
        $target = (int)($in['target'] ?? 20);
        $ctries = (int)($in['ctries'] ?? 5);
        return [
            'mode'   => $mode,
            'lang'   => $lang,
            'range'  => $range,
            'limited' => !isset($in['limited']) || !empty($in['limited']),
            'hint'   => !isset($in['hint']) || !empty($in['hint']),
            'level'  => $level,
            'gages'  => $gages,
            'rounds' => in_array($rounds, [5, 10, 15], true) ? $rounds : 10,
            'target' => in_array($target, [10, 20, 30], true) ? $target : 20,
            'ctries' => in_array($ctries, [5, 7], true) ? $ctries : 5,
        ];
    }

    public function init(array $seats, array $options): array
    {
        sort($seats);
        $options += $this->options([]);
        if ($options['mode'] === 'couple' && count($seats) < 2) {
            throw new GameError('not_enough_players');
        }
        $state = [
            'order'  => $seats,
            'opts'   => $options,
            'mode'   => $options['mode'],
            'scores' => array_fill_keys($seats, 0),
            'round'  => 0,
            'used'   => [],
            'gage'   => null,
        ];
        switch ($state['mode']) {
            case 'secret':
                $state['best'] = [];
                $this->newSecret($state);
                break;
            case 'estimate':
                $this->newQuestion($state);
                break;
            case 'highlow':
                $state['deck'] = self::shuffledDeck();
                $state['card'] = array_pop($state['deck']);
                $state['turn'] = $seats[0];
                $state['streak'] = 0;
                $state['fails'] = array_fill_keys($seats, 0);
                $state['last'] = null;
                $state['phase'] = 'playing';
                break;
            case 'couple':
                $state['answerer'] = $seats[random_int(0, count($seats) - 1)];
                $this->newCouple($state);
                break;
        }
        return $state;
    }

    // ------------------------------------------------------------------ helpers

    /** "1 234,5" / "1,234.5" / "12k" -> float, or null. */
    public static function parseNumber($v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return (float)$v;
        }
        $s = str_replace(["\u{00A0}", "\u{202F}", ' ', '_', "'"], '', mb_strtolower(trim((string)$v)));
        if ($s === '') {
            return null;
        }
        $mult = 1;
        if (preg_match('/^(.*?)(k|m|md|mrd|bn)$/', $s, $m)) {
            $s = $m[1];
            $mult = $m[2] === 'k' ? 1e3 : ($m[2] === 'm' ? 1e6 : 1e9);
        }
        // a comma followed by exactly 3 digits is a thousands separator, otherwise a decimal one
        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) {
            $s = str_replace(',', '', $s);
        } else {
            $s = str_replace(',', '.', $s);
        }
        if (!preg_match('/^-?\d+(\.\d+)?$/', $s)) {
            return null;
        }
        return (float)$s * $mult;
    }

    private function deckPick(array &$state, string $type, string $level): string
    {
        $all = gp_prompts_load();
        $lang = $state['opts']['lang'];
        $pool = $all[$lang][$level][$type] ?? [];
        if (!$pool) {
            $pool = $all['fr'][$level][$type] ?? [];
        }
        if (!$pool) {
            throw new GameError('no_cards');
        }
        $key = $level . '/' . $type;
        $used = $state['used'][$key] ?? [];
        $free = array_values(array_diff(array_keys($pool), $used));
        if (!$free) {
            $used = [];
            $free = array_keys($pool);
        }
        $i = $free[array_rand($free)];
        $used[] = $i;
        $state['used'][$key] = $used;
        return $pool[$i];
    }

    /** A forfeit (a Truth or Dare dare) for $seat, if forfeits are on. */
    private function giveGage(array &$state, int $seat): void
    {
        if ($state['opts']['gages'] === 'off') {
            return;
        }
        try {
            $state['gage'] = ['seat' => $seat, 'text' => $this->deckPick($state, 'dare', $state['opts']['gages'])];
        } catch (GameError $e) {
            $state['gage'] = null;
        }
    }

    private static function heat(float $guess, float $target, int $range): int
    {
        $d = abs($guess - $target) / $range;
        return $d <= 0.01 ? 3 : ($d <= 0.05 ? 2 : ($d <= 0.15 ? 1 : 0));
    }

    private function players(array $state): array
    {
        return $state['order'];
    }

    // ------------------------------------------------------------------ secret number

    private function newSecret(array &$state): void
    {
        $state['round']++;
        $state['target'] = random_int(1, $state['opts']['range']);
        $state['boards'] = array_fill_keys($state['order'], []);
        $state['done'] = [];
        $state['found'] = [];
        $state['phase'] = 'playing';
    }

    private function secretLimit(array $state): int
    {
        return $state['opts']['limited'] ? self::RANGES[$state['opts']['range']] : 0;
    }

    private function secretGuess(array &$state, int $seat, array $move): void
    {
        if ($state['phase'] !== 'playing' || array_key_exists($seat, $state['done']) || !isset($state['boards'][$seat])) {
            throw new GameError('invalid_move');
        }
        $g = self::parseNumber($move['value'] ?? '');
        $range = (int)$state['opts']['range'];
        if ($g === null || $g < 1 || $g > $range || floor($g) !== $g) {
            throw new GameError('pm_out_of_range');
        }
        $g = (int)$g;
        $t = (int)$state['target'];
        $r = $g === $t ? 'ok' : ($g < $t ? 'up' : 'down');
        $state['boards'][$seat][] = ['g' => $g, 'r' => $r, 'h' => self::heat($g, $t, $range)];
        $tries = count($state['boards'][$seat]);
        $limit = $this->secretLimit($state);
        if ($r === 'ok') {
            $state['done'][$seat] = $tries;
            $ref = $limit ?: self::RANGES[$range] + 4;
            $state['scores'][$seat] += max(1, $ref - $tries + 1) * 10 + (!$state['found'] && count($state['order']) > 1 ? 10 : 0);
            $state['found'][] = $seat;
            $best = $state['best'][$seat] ?? 0;
            if (!$best || $tries < $best) {
                $state['best'][$seat] = $tries;
            }
        } elseif ($limit && $tries >= $limit) {
            $state['done'][$seat] = 0;
        }
        $this->secretMaybeEnd($state);
    }

    private function secretMaybeEnd(array &$state): void
    {
        foreach ($state['order'] as $s) {
            if (!array_key_exists($s, $state['done'])) {
                return;
            }
        }
        $state['phase'] = 'reveal';
        $solved = array_filter($state['done'], function ($t) {
            return $t > 0;
        });
        if ($solved) {
            $best = min($solved);
            asort($solved);
            $ranking = [];
            foreach ($solved as $s => $t) {
                $ranking[] = [[(int)$s], $t];
            }
            self::announce($state, array_map('intval', array_keys(array_filter($solved, function ($t) use ($best) {
                return $t === $best;
            }))), $ranking, 'tries');
        }
    }

    // ------------------------------------------------------------------ estimation

    private function newQuestion(array &$state): void
    {
        $level = $state['opts']['level'];
        for ($i = 0; $i < 30; $i++) {
            $parts = array_map('trim', explode('||', $this->deckPick($state, 'num', $level)));
            $answer = self::parseNumber($parts[1] ?? '');
            if ($parts[0] !== '' && $answer !== null) {
                break;
            }
        }
        if ($answer === null) {
            throw new GameError('no_cards');
        }
        $state['round']++;
        $state['q'] = ['text' => $parts[0], 'answer' => $answer, 'unit' => $parts[2] ?? ''];
        $state['answers'] = [];
        $state['result'] = null;
        $state['gage'] = null;
        $state['phase'] = 'answer';
    }

    private static function accuracyPoints(float $rel): int
    {
        return $rel <= 0.01 ? 5 : ($rel <= 0.05 ? 3 : ($rel <= 0.15 ? 2 : ($rel <= 0.3 ? 1 : 0)));
    }

    private function estimateReveal(array &$state): void
    {
        $ans = (float)$state['q']['answer'];
        $rows = [];
        foreach ($state['answers'] as $s => $v) {
            $diff = abs($v - $ans);
            $rows[] = ['s' => (int)$s, 'v' => $v, 'diff' => round($diff, 6), 'pts' => self::accuracyPoints($diff / max(abs($ans), 1))];
        }
        usort($rows, function ($a, $b) {
            return $a['diff'] <=> $b['diff'];
        });
        if (count($rows) > 1) {
            $bestDiff = $rows[0]['diff'];
            foreach ($rows as &$r) {
                if ($r['diff'] === $bestDiff) {
                    $r['pts'] += 2;
                    $r['closest'] = true;
                }
            }
            unset($r);
        }
        foreach ($rows as $r) {
            $state['scores'][$r['s']] += $r['pts'];
        }
        $state['result'] = $rows;
        if ($rows) {
            $worst = $rows[count($rows) - 1];
            if (count($rows) > 1 ? $worst['diff'] > $rows[0]['diff'] : $worst['pts'] === 0) {
                $this->giveGage($state, $worst['s']);
            }
        }
        $state['phase'] = 'reveal';
    }

    private function finish(array &$state): void
    {
        $state['phase'] = 'over';
        $scores = $state['scores'];
        arsort($scores);
        $ranking = [];
        foreach ($scores as $s => $p) {
            $ranking[] = [[(int)$s], $p];
        }
        $top = $scores ? max($scores) : 0;
        $winners = array_keys(array_filter($scores, function ($p) use ($top) {
            return $p === $top;
        }));
        self::announce($state, array_map('intval', $winners), $ranking, 'points');
    }

    // ------------------------------------------------------------------ higher or lower

    private static function shuffledDeck(): array
    {
        $deck = [];
        foreach (['S', 'H', 'D', 'C'] as $suit) {
            for ($v = 2; $v <= 14; $v++) {
                $deck[] = ['v' => $v, 's' => $suit];
            }
        }
        shuffle($deck);
        return $deck;
    }

    /** 1, 3, 6, 10…: the longer the streak, the more it is worth. */
    public static function streakPoints(int $n): int
    {
        return intdiv($n * ($n + 1), 2);
    }

    private function hlNextTurn(array &$state): void
    {
        $state['streak'] = 0;
        $state['turn'] = self::nextInOrder($state['order'], (int)$state['turn']);
    }

    private function highlow(array &$state, int $seat, string $type, array $move): void
    {
        if ($state['phase'] !== 'playing' || $seat !== (int)$state['turn']) {
            throw new GameError('not_your_turn');
        }
        if ($type === 'bank') {
            if ($state['streak'] <= 0) {
                throw new GameError('invalid_move');
            }
            $pts = self::streakPoints((int)$state['streak']);
            $state['scores'][$seat] += $pts;
            $state['last'] = ['seat' => $seat, 'bank' => $pts];
            $state['gage'] = null;
            if ($state['scores'][$seat] >= $state['opts']['target']) {
                $state['streak'] = 0;
                $this->finish($state);
                return;
            }
            $this->hlNextTurn($state);
            return;
        }
        $dir = (string)($move['dir'] ?? '');
        if (!in_array($dir, ['up', 'down'], true)) {
            throw new GameError('invalid_move');
        }
        if (!$state['deck']) {
            $state['deck'] = self::shuffledDeck();
        }
        $prev = $state['card'];
        $next = array_pop($state['deck']);
        $state['card'] = $next;
        $state['gage'] = null;
        if ($next['v'] === $prev['v']) {
            $state['last'] = ['seat' => $seat, 'dir' => $dir, 'from' => $prev, 'card' => $next, 'res' => 'same'];
            return;
        }
        $ok = $dir === 'up' ? $next['v'] > $prev['v'] : $next['v'] < $prev['v'];
        $state['last'] = ['seat' => $seat, 'dir' => $dir, 'from' => $prev, 'card' => $next, 'res' => $ok ? 'ok' : 'lost', 'streak' => $state['streak'] + ($ok ? 1 : 0)];
        if ($ok) {
            $state['streak']++;
            return;
        }
        $state['fails'][$seat] = ($state['fails'][$seat] ?? 0) + 1;
        $this->giveGage($state, $seat);
        $this->hlNextTurn($state);
    }

    // ------------------------------------------------------------------ couple: guess how much

    private function newCouple(array &$state): void
    {
        $parts = array_map('trim', explode('||', $this->deckPick($state, 'numduo', $state['opts']['level'])));
        $state['round']++;
        $state['q'] = ['text' => $parts[0], 'unit' => $parts[1] ?? ''];
        $state['secret'] = null;
        $state['boards'] = [];
        foreach ($state['order'] as $s) {
            if ($s !== $state['answerer']) {
                $state['boards'][$s] = [];
            }
        }
        $state['done'] = [];
        $state['found'] = [];
        $state['phase'] = 'setting';
    }

    private function coupleMaybeEnd(array &$state): void
    {
        foreach (array_keys($state['boards']) as $s) {
            if (!array_key_exists($s, $state['done'])) {
                return;
            }
        }
        $state['phase'] = 'reveal';
    }

    // ------------------------------------------------------------------ moves

    public function move(array &$state, int $seat, array $move, bool $isHost): void
    {
        if (!in_array($seat, $state['order'], true)) {
            throw new GameError('invalid_move');
        }
        $type = (string)($move['type'] ?? '');
        $mode = $state['mode'];

        if ($mode === 'secret') {
            if ($type === 'guess') {
                $this->secretGuess($state, $seat, $move);
                return;
            }
            if ($type === 'giveup' && $state['phase'] === 'playing' && !array_key_exists($seat, $state['done'])) {
                $state['done'][$seat] = 0;
                $this->secretMaybeEnd($state);
                return;
            }
            if ($type === 'next' && ($state['phase'] === 'reveal' || $isHost)) {
                $this->newSecret($state);
                return;
            }
        }

        if ($mode === 'estimate') {
            if ($type === 'answer' && $state['phase'] === 'answer') {
                $v = self::parseNumber($move['value'] ?? '');
                if ($v === null || abs($v) > 1e15) {
                    throw new GameError('pm_not_a_number');
                }
                $state['answers'][$seat] = $v;
                if (count($state['answers']) >= count($state['order'])) {
                    $this->estimateReveal($state);
                }
                return;
            }
            if ($type === 'reveal' && $state['phase'] === 'answer' && $isHost && $state['answers']) {
                $this->estimateReveal($state);
                return;
            }
            if ($type === 'next' && $state['phase'] === 'reveal') {
                if ($state['round'] >= $state['opts']['rounds']) {
                    $this->finish($state);
                } else {
                    $this->newQuestion($state);
                }
                return;
            }
            if ($type === 'again' && $state['phase'] === 'over') {
                $state['scores'] = array_fill_keys($state['order'], 0);
                $state['round'] = 0;
                $this->newQuestion($state);
                return;
            }
        }

        if ($mode === 'highlow') {
            if ($type === 'guess' || $type === 'bank') {
                $this->highlow($state, $seat, $type, $move);
                return;
            }
            if ($type === 'again' && $state['phase'] === 'over') {
                $state['scores'] = array_fill_keys($state['order'], 0);
                $state['fails'] = array_fill_keys($state['order'], 0);
                $state['deck'] = self::shuffledDeck();
                $state['card'] = array_pop($state['deck']);
                $state['streak'] = 0;
                $state['last'] = null;
                $state['gage'] = null;
                $state['phase'] = 'playing';
                return;
            }
        }

        if ($mode === 'couple') {
            $answerer = (int)$state['answerer'];
            if ($type === 'secret' && $state['phase'] === 'setting' && $seat === $answerer) {
                $v = self::parseNumber($move['value'] ?? '');
                if ($v === null || $v < 0 || $v > 1e9) {
                    throw new GameError('pm_not_a_number');
                }
                $state['secret'] = $v;
                $state['phase'] = 'playing';
                return;
            }
            if ($type === 'skipq' && $state['phase'] === 'setting' && $seat === $answerer) {
                $state['round']--;
                $this->newCouple($state);
                return;
            }
            if ($type === 'guess' && $state['phase'] === 'playing' && isset($state['boards'][$seat]) && !array_key_exists($seat, $state['done'])) {
                $g = self::parseNumber($move['value'] ?? '');
                if ($g === null) {
                    throw new GameError('pm_not_a_number');
                }
                $t = (float)$state['secret'];
                $r = $g == $t ? 'ok' : ($g < $t ? 'up' : 'down');
                $state['boards'][$seat][] = ['g' => $g, 'r' => $r];
                $n = count($state['boards'][$seat]);
                $limit = (int)$state['opts']['ctries'];
                if ($r === 'ok') {
                    $state['done'][$seat] = $n;
                    $state['found'][] = $seat;
                    $state['scores'][$seat] += $limit - $n + 1;
                    $state['scores'][$answerer] += 1;
                } elseif ($n >= $limit) {
                    $state['done'][$seat] = 0;
                }
                $this->coupleMaybeEnd($state);
                return;
            }
            if ($type === 'next' && ($state['phase'] === 'reveal' || ($isHost && $state['phase'] === 'playing'))) {
                $state['answerer'] = self::nextInOrder($state['order'], $answerer);
                $this->newCouple($state);
                return;
            }
        }

        throw new GameError('invalid_move');
    }

    // ------------------------------------------------------------------ seats joining / leaving

    public function addSeat(array &$state, int $seat): void
    {
        if (in_array($seat, $state['order'], true)) {
            return;
        }
        $state['order'][] = $seat;
        sort($state['order']);
        $state['scores'][$seat] = $state['scores'][$seat] ?? 0;
        if ($state['mode'] === 'secret' && $state['phase'] === 'playing') {
            $state['boards'][$seat] = [];
        }
        if ($state['mode'] === 'couple' && $state['phase'] !== 'reveal') {
            $state['boards'][$seat] = [];
        }
        if ($state['mode'] === 'highlow') {
            $state['fails'][$seat] = 0;
        }
    }

    public function removeSeat(array &$state, int $seat): void
    {
        $was = $state['order'];
        $state['order'] = array_values(array_filter($state['order'], function ($s) use ($seat) {
            return $s !== $seat;
        }));
        if (!$state['order']) {
            return;
        }
        unset($state['boards'][$seat], $state['done'][$seat], $state['answers'][$seat]);
        switch ($state['mode']) {
            case 'secret':
                if ($state['phase'] === 'playing') {
                    $this->secretMaybeEnd($state);
                }
                break;
            case 'estimate':
                if ($state['phase'] === 'answer' && $state['answers'] && count($state['answers']) >= count($state['order'])) {
                    $this->estimateReveal($state);
                }
                break;
            case 'highlow':
                if ((int)$state['turn'] === $seat) {
                    $state['streak'] = 0;
                    $state['turn'] = self::nextInOrder($was, $seat);
                }
                break;
            case 'couple':
                if ((int)$state['answerer'] === $seat) {
                    $state['answerer'] = self::nextInOrder($was, $seat);
                    $state['round']--;
                    $this->newCouple($state);
                } elseif ($state['phase'] === 'playing') {
                    $this->coupleMaybeEnd($state);
                }
                break;
        }
    }

    public function currentSeat(array $state): ?int
    {
        return $state['mode'] === 'highlow' ? (int)$state['turn'] : null;
    }

    // ------------------------------------------------------------------ view

    public function view(array $state, ?int $seat): array
    {
        $v = [
            'mode'   => $state['mode'],
            'order'  => $state['order'],
            'opts'   => $state['opts'],
            'phase'  => $state['phase'],
            'round'  => $state['round'],
            'scores' => (object)$state['scores'],
            'gage'   => $state['gage'],
        ];
        $mode = $state['mode'];
        if ($mode === 'secret' || $mode === 'couple') {
            // the answerer of "guess how much" watches every guess live (it is their own number)
            $over = $state['phase'] === 'reveal' || ($mode === 'couple' && $seat === (int)$state['answerer']);
            $boards = [];
            foreach ($state['boards'] as $s => $rows) {
                // the others' numbers stay hidden: only how many tries they used (and whether they found)
                $boards[$s] = ($over || $s === $seat) ? $rows : array_map(function ($r) {
                    return ['r' => $r['r'] === 'ok' ? 'ok' : '?'];
                }, $rows);
            }
            $v['boards'] = (object)$boards;
            $v['done'] = (object)$state['done'];
            $v['found'] = $state['found'];
        }
        if ($mode === 'secret') {
            $finished = $seat !== null && array_key_exists($seat, $state['done']);
            $v['limit'] = $this->secretLimit($state);
            $v['target'] = ($state['phase'] === 'reveal' || $finished) ? $state['target'] : null;
            $v['best'] = (object)($state['best'] ?? []);
        }
        if ($mode === 'estimate') {
            $reveal = $state['phase'] !== 'answer';
            $q = $state['q'];
            if (!$reveal) {
                $q['answer'] = null;
            }
            $v['q'] = $q;
            $v['answered'] = array_map('intval', array_keys($state['answers']));
            $v['mine'] = $seat !== null && isset($state['answers'][$seat]) ? $state['answers'][$seat] : null;
            $v['result'] = $reveal ? $state['result'] : null;
            $v['rounds'] = $state['opts']['rounds'];
        }
        if ($mode === 'highlow') {
            $v['card'] = $state['card'];
            $v['turn'] = (int)$state['turn'];
            $v['streak'] = (int)$state['streak'];
            $v['bankValue'] = self::streakPoints((int)$state['streak']);
            $v['nextValue'] = self::streakPoints((int)$state['streak'] + 1);
            $v['last'] = $state['last'];
            $v['fails'] = (object)$state['fails'];
            $v['left'] = count($state['deck']);
            $v['target'] = $state['opts']['target'];
        }
        if ($mode === 'couple') {
            $v['answerer'] = (int)$state['answerer'];
            $v['q'] = $state['q'];
            $v['limit'] = (int)$state['opts']['ctries'];
            $v['secret'] = ($state['phase'] === 'reveal' || $seat === (int)$state['answerer']) ? $state['secret'] : null;
        }
        return $v;
    }
}
