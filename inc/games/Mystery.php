<?php
declare(strict_types=1);

/**
 * Mot mystère / Mystery word.
 * Find a hidden word (5, 6 or 7 letters) in 6 tries. After each try every letter is marked:
 *   2 = right letter in the right place, 1 = in the word but elsewhere, 0 = not in the word.
 * Alone, or as a race at the table on the same word: you see the others' marks but not their letters.
 * Accents don't matter (É = E). Words come from data/prompts.json (type "mystery"), editable in admin.php.
 */
class Mystery extends GameEngine
{
    public const TRIES = 6;

    public function meta(): array
    {
        return ['min' => 1, 'max' => 8, 'bots' => false, 'joinMidGame' => true];
    }

    public function options(array $in): array
    {
        $lang = in_array($in['lang'] ?? '', ['fr', 'en'], true) ? $in['lang'] : 'fr';
        $len = $in['length'] ?? 5;
        $len = in_array((string)$len, ['5', '6', '7', 'mix'], true) ? (string)$len : '5';
        $level = ($in['level'] ?? '') === 'party' ? 'party' : 'soft';
        return ['lang' => $lang, 'length' => $len, 'first' => !empty($in['first']), 'level' => $level];
    }

    /** A-Z only, upper case: "École" -> "ECOLE". */
    public static function norm(string $s): string
    {
        $s = mb_strtoupper($s, 'UTF-8');
        $s = strtr($s, [
            'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Á' => 'A', 'Ã' => 'A', 'Ç' => 'C', 'É' => 'E', 'È' => 'E', 'Ê' => 'E',
            'Ë' => 'E', 'Î' => 'I', 'Ï' => 'I', 'Í' => 'I', 'Ì' => 'I', 'Ô' => 'O', 'Ö' => 'O', 'Ò' => 'O', 'Ó' => 'O',
            'Õ' => 'O', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ú' => 'U', 'Ÿ' => 'Y', 'Ñ' => 'N', 'Œ' => 'OE', 'Æ' => 'AE',
        ]);
        return preg_replace('/[^A-Z]/', '', $s);
    }

    private function words(array $state): array
    {
        $all = gp_prompts_load();
        $levels = $state['opts']['level'] === 'party' ? ['soft', 'party'] : ['soft'];
        $want = $state['opts']['length'];
        $out = [];
        foreach ($levels as $lv) {
            foreach ($all[$state['opts']['lang']][$lv]['mystery'] ?? [] as $w) {
                $n = self::norm($w);
                $len = strlen($n);
                if ($len >= 5 && $len <= 7 && ($want === 'mix' || $len === (int)$want) && preg_match('/^[\p{L}\'’ -]+$/u', $w)) {
                    $out[$n] = $w; // keep the written form (with accents) to show at the end
                }
            }
        }
        if (!$out) {
            throw new GameError('no_cards');
        }
        return $out;
    }

    public function init(array $seats, array $options): array
    {
        sort($seats);
        $state = [
            'order'  => $seats,
            'opts'   => $options + ['lang' => 'fr', 'length' => '5', 'first' => false, 'level' => 'soft'],
            'round'  => 0,
            'scores' => array_fill_keys($seats, 0),
            'used'   => [],
        ];
        $this->newWord($state);
        return $state;
    }

    private function newWord(array &$state): void
    {
        $written = $this->words($state);
        $words = array_keys($written);
        $free = array_values(array_diff($words, $state['used']));
        if (!$free) {
            $state['used'] = [];
            $free = $words;
        }
        $word = $free[array_rand($free)];
        $state['display'] = mb_strtoupper($written[$word], 'UTF-8');
        $state['used'][] = $word;
        $state['used'] = array_slice($state['used'], -200);
        $state['word'] = $word;
        $state['round']++;
        $state['phase'] = 'playing';
        $state['boards'] = array_fill_keys($state['order'], []);
        $state['done'] = [];       // seat => number of tries if found, 0 if failed
        $state['found'] = [];      // seats in the order they found the word
    }

    /** Marks for a guess, handling repeated letters correctly. */
    public static function marks(string $guess, string $word): string
    {
        $n = strlen($word);
        $res = array_fill(0, $n, 0);
        $left = [];
        for ($i = 0; $i < $n; $i++) {
            if ($guess[$i] === $word[$i]) {
                $res[$i] = 2;
            } else {
                $left[$word[$i]] = ($left[$word[$i]] ?? 0) + 1;
            }
        }
        for ($i = 0; $i < $n; $i++) {
            if ($res[$i] === 0 && !empty($left[$guess[$i]])) {
                $res[$i] = 1;
                $left[$guess[$i]]--;
            }
        }
        return implode('', $res);
    }

    public function move(array &$state, int $seat, array $move, bool $isHost): void
    {
        $type = (string)($move['type'] ?? '');

        if ($type === 'guess') {
            if ($state['phase'] !== 'playing' || array_key_exists($seat, $state['done']) || !isset($state['boards'][$seat])) {
                throw new GameError('invalid_move');
            }
            $g = self::norm((string)($move['text'] ?? ''));
            if (strlen($g) !== strlen($state['word'])) {
                throw new GameError('wrong_length');
            }
            $m = self::marks($g, $state['word']);
            $state['boards'][$seat][] = ['g' => $g, 'm' => $m];
            $tries = count($state['boards'][$seat]);
            if ($g === $state['word']) {
                $state['done'][$seat] = $tries;
                $points = (self::TRIES - $tries + 1) * 10 + (!$state['found'] ? 10 : 0);
                $state['found'][] = $seat;
                $state['scores'][$seat] = ($state['scores'][$seat] ?? 0) + $points;
            } elseif ($tries >= self::TRIES) {
                $state['done'][$seat] = 0;
            }
            $this->maybeEnd($state);
            return;
        }

        if ($type === 'giveup') {
            if ($state['phase'] !== 'playing' || array_key_exists($seat, $state['done'])) {
                throw new GameError('invalid_move');
            }
            $state['done'][$seat] = 0;
            $this->maybeEnd($state);
            return;
        }

        if ($type === 'next') {
            if ($state['phase'] !== 'reveal' && !$isHost) {
                throw new GameError('invalid_move');
            }
            $this->newWord($state);
            return;
        }

        throw new GameError('invalid_move');
    }

    /** Everybody found or ran out of tries: show the word and record who won this word. */
    private function maybeEnd(array &$state): void
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
            $winners = array_keys(array_filter($solved, function ($t) use ($best) {
                return $t === $best;
            }));
            $ranking = [];
            asort($solved);
            foreach ($solved as $s => $t) {
                $ranking[] = [[(int)$s], $t];
            }
            self::announce($state, array_map('intval', $winners), $ranking, 'tries');
        }
    }

    public function addSeat(array &$state, int $seat): void
    {
        if (!in_array($seat, $state['order'], true)) {
            $state['order'][] = $seat;
            $state['boards'][$seat] = [];
            $state['scores'][$seat] = $state['scores'][$seat] ?? 0;
        }
    }

    public function removeSeat(array &$state, int $seat): void
    {
        $state['order'] = array_values(array_filter($state['order'], function ($s) use ($seat) {
            return $s !== $seat;
        }));
        unset($state['boards'][$seat], $state['done'][$seat]);
        if ($state['phase'] === 'playing' && $state['order']) {
            $this->maybeEnd($state);
        }
    }

    public function view(array $state, ?int $seat): array
    {
        $over = $state['phase'] === 'reveal';
        $finished = $seat !== null && array_key_exists($seat, $state['done']);
        $boards = [];
        foreach ($state['boards'] as $s => $rows) {
            // Other players' letters stay hidden until the word is revealed: only their marks show.
            $boards[$s] = ($over || $s === $seat) ? $rows : array_map(function ($r) {
                return ['m' => $r['m']];
            }, $rows);
        }
        return [
            'order'   => $state['order'],
            'opts'    => $state['opts'],
            'round'   => $state['round'],
            'phase'   => $state['phase'],
            'length'  => strlen($state['word']),
            'tries'   => self::TRIES,
            'first'   => $state['opts']['first'] ? $state['word'][0] : null,
            'word'    => ($over || $finished) ? $state['word'] : null,
            'display' => ($over || $finished) ? ($state['display'] ?? $state['word']) : null,
            'boards'  => (object)$boards,
            'done'    => (object)$state['done'],
            'found'   => $state['found'],
            'scores'  => (object)$state['scores'],
        ];
    }
}
