<?php
declare(strict_types=1);

/**
 * Dessine et devine / Draw & guess.
 *
 * Every player draws once per round. The drawer picks one of three words, then has a limited time
 * to draw it while the others type guesses. A right guess scores more the faster it comes (and the
 * earlier compared to the others); the drawer scores for each player who finds the word.
 * Letters of the word are revealed as hints when half and three quarters of the time have gone by.
 *
 * Phases: choose (15 s) -> drawing (60-100 s) -> reveal (6 s) -> next drawer ... -> over.
 * Time is moved forward by tick(), which api.php calls while players poll.
 * The drawing itself is not in this state: strokes go to the gp_strokes table (see api.php).
 */
class DrawGuess extends GameEngine
{
    private const CHOOSE_SECONDS = 15;
    private const REVEAL_SECONDS = 6;

    public function meta(): array
    {
        return ['min' => 2, 'max' => 10, 'bots' => false, 'joinMidGame' => true];
    }

    public function options(array $in): array
    {
        $lang = in_array($in['lang'] ?? '', ['fr', 'en'], true) ? $in['lang'] : 'fr';
        $level = in_array($in['level'] ?? '', ['soft', 'party'], true) ? $in['level'] : 'soft';
        $rounds = in_array((int)($in['rounds'] ?? 0), [1, 2, 3, 4], true) ? (int)$in['rounds'] : 2;
        $time = in_array((int)($in['time'] ?? 0), [60, 80, 100, 120], true) ? (int)$in['time'] : 80;
        return ['lang' => $lang, 'level' => $level, 'rounds' => $rounds, 'time' => $time];
    }

    public function init(array $seats, array $options): array
    {
        sort($seats);
        $options += ['lang' => 'fr', 'level' => 'soft', 'rounds' => 2, 'time' => 80];
        $state = [
            'order'   => $seats,
            'opts'    => $options,
            'round'   => 1,
            'queue'   => $seats,
            'turnNo'  => 0,
            'phase'   => 'choose',
            'drawer'  => null,
            'choices' => [],
            'word'    => null,
            'hintOrder' => [],
            'startedAt' => 0,
            'endsAt'  => 0,
            'guessed' => [],
            'scores'  => array_fill_keys($seats, 0),
            'chat'    => [],
            'used'    => [],
            'lastWord' => null,
        ];
        $this->nextTurn($state);
        return $state;
    }

    private function words(array $state): array
    {
        $all = gp_prompts_load();
        $levels = $state['opts']['level'] === 'party' ? ['party', 'soft'] : ['soft'];
        $out = [];
        foreach ($levels as $lv) {
            foreach ($all[$state['opts']['lang']][$lv]['word'] ?? [] as $w) {
                $out[] = $w;
            }
        }
        if (!$out) {
            throw new GameError('no_cards');
        }
        return $out;
    }

    private function pickChoices(array &$state): array
    {
        $words = $this->words($state);
        $free = array_values(array_diff(array_keys($words), $state['used']));
        if (count($free) < 3) {
            $state['used'] = [];
            $free = array_keys($words);
        }
        shuffle($free);
        $pick = array_slice($free, 0, min(3, count($free)));
        foreach ($pick as $i) {
            $state['used'][] = $i;
        }
        return array_map(function ($i) use ($words) {
            return $words[$i];
        }, $pick);
    }

    private function nextTurn(array &$state): void
    {
        // Next drawer: someone still in the game who hasn't drawn this round.
        while (true) {
            while ($state['queue'] && !in_array($state['queue'][0], $state['order'], true)) {
                array_shift($state['queue']);
            }
            if ($state['queue']) {
                break;
            }
            $state['round']++;
            if ($state['round'] > $state['opts']['rounds'] || !$state['order']) {
                $state['round'] = $state['opts']['rounds'];
                $state['phase'] = 'over';
                $state['drawer'] = null;
                $state['endsAt'] = 0;
                return;
            }
            $state['queue'] = $state['order'];
        }
        $state['drawer'] = array_shift($state['queue']);
        $state['turnNo']++;
        $state['phase'] = 'choose';
        $state['choices'] = $this->pickChoices($state);
        $state['word'] = null;
        $state['guessed'] = [];
        $state['startedAt'] = time();
        $state['endsAt'] = time() + self::CHOOSE_SECONDS;
    }

    private function startDrawing(array &$state, int $choice): void
    {
        $state['word'] = $state['choices'][$choice] ?? $state['choices'][0];
        $state['choices'] = [];
        $state['phase'] = 'drawing';
        $state['startedAt'] = time();
        $state['endsAt'] = time() + (int)$state['opts']['time'];
        // Which letters get revealed as hints, in order.
        $positions = [];
        $chars = self::chars($state['word']);
        foreach ($chars as $i => $ch) {
            if (preg_match('/\p{L}|\p{N}/u', $ch)) {
                $positions[] = $i;
            }
        }
        shuffle($positions);
        $state['hintOrder'] = $positions;
        $state['hintsShown'] = 0;
    }

    private function endTurn(array &$state, string $why): void
    {
        $n = count($state['guessed']);
        if ($state['drawer'] !== null && $n > 0 && isset($state['scores'][$state['drawer']])) {
            $state['scores'][$state['drawer']] += min(120, 30 + 25 * $n);
        }
        $state['lastWord'] = $state['word'];
        $this->say($state, ['kind' => 'word', 'text' => $state['word'], 'why' => $why]);
        $state['phase'] = 'reveal';
        $state['endsAt'] = time() + self::REVEAL_SECONDS;
    }

    private function say(array &$state, array $msg): void
    {
        $state['chat'][] = $msg;
        if (count($state['chat']) > 40) {
            $state['chat'] = array_slice($state['chat'], -40);
        }
    }

    public function currentSeat(array $state): ?int
    {
        return $state['drawer'];
    }

    public function tickDue(array $state): bool
    {
        if (!in_array($state['phase'], ['choose', 'drawing', 'reveal'], true)) {
            return false;
        }
        return time() >= (int)$state['endsAt']
            || ($state['phase'] === 'drawing' && $this->hintLevel($state) > (int)($state['hintsShown'] ?? 0));
    }

    /** How many letters should be visible now: 1 after half the time, 2 after three quarters. */
    private function hintLevel(array $state): int
    {
        $total = max(1, (int)$state['endsAt'] - (int)$state['startedAt']);
        $elapsed = (time() - (int)$state['startedAt']) / $total;
        $letters = count($state['hintOrder']);
        $n = 0;
        if ($elapsed >= .5 && $letters >= 3) {
            $n = 1;
        }
        if ($elapsed >= .75 && $letters >= 5) {
            $n = 2;
        }
        return $n;
    }

    public function tick(array &$state): bool
    {
        if (!$this->tickDue($state)) {
            return false;
        }
        if ($state['phase'] === 'drawing' && time() < (int)$state['endsAt']) {
            $state['hintsShown'] = $this->hintLevel($state); // time for a new hint letter
            return true;
        }
        if ($state['phase'] === 'choose') {
            $this->startDrawing($state, random_int(0, max(0, count($state['choices']) - 1)));
        } elseif ($state['phase'] === 'drawing') {
            $this->endTurn($state, 'time');
        } else {
            $this->nextTurn($state);
        }
        return true;
    }

    public function move(array &$state, int $seat, array $move, bool $isHost): void
    {
        $type = (string)($move['type'] ?? '');
        $isDrawer = $seat === $state['drawer'];

        if ($type === 'pick') {
            if (!$isDrawer || $state['phase'] !== 'choose') {
                throw new GameError('invalid_move');
            }
            $this->startDrawing($state, max(0, min(count($state['choices']) - 1, (int)($move['i'] ?? 0))));
            return;
        }

        if ($type === 'guess') {
            $text = trim(preg_replace('/\s+/u', ' ', (string)($move['text'] ?? '')));
            $text = mb_substr($text, 0, 60);
            if ($text === '') {
                throw new GameError('invalid_move');
            }
            if ($state['phase'] !== 'drawing') {
                $this->say($state, ['kind' => 'chat', 'seat' => $seat, 'text' => $text]);
                return;
            }
            if ($isDrawer || isset($state['guessed'][$seat])) {
                throw new GameError('no_spoilers');
            }
            $guess = self::norm($text);
            $word = self::norm((string)$state['word']);
            if ($guess === $word) {
                $k = count($state['guessed']);
                $total = max(1, (int)$state['endsAt'] - (int)$state['startedAt']);
                $left = max(0, (int)$state['endsAt'] - time());
                $points = max(30, 100 - 10 * $k) + (int)round(50 * $left / $total);
                $state['guessed'][$seat] = $points;
                $state['scores'][$seat] = ($state['scores'][$seat] ?? 0) + $points;
                $this->say($state, ['kind' => 'found', 'seat' => $seat, 'points' => $points]);
                $others = array_filter($state['order'], function ($s) use ($state) {
                    return $s !== $state['drawer'];
                });
                if (count($state['guessed']) >= count($others)) {
                    $this->endTurn($state, 'all');
                }
                return;
            }
            $this->say($state, ['kind' => 'guess', 'seat' => $seat, 'text' => $text]);
            if (strlen($word) >= 4 && levenshtein($guess, $word) <= 1) {
                $this->say($state, ['kind' => 'close', 'to' => $seat]);
            }
            return;
        }

        if ($type === 'skip') {
            if (!$isDrawer && !$isHost) {
                throw new GameError('invalid_move');
            }
            if ($state['phase'] === 'choose') {
                $state['word'] = $state['choices'][0] ?? '';
                $this->endTurn($state, 'skip');
                return;
            }
            if ($state['phase'] === 'drawing') {
                $this->endTurn($state, 'skip');
                return;
            }
            if ($state['phase'] === 'reveal' && $isHost) {
                $this->nextTurn($state);
                return;
            }
            throw new GameError('invalid_move');
        }

        if ($type === 'restart') {
            if (!$isHost || $state['phase'] !== 'over') {
                throw new GameError('host_only');
            }
            $fresh = $this->init($state['order'], $state['opts']);
            $fresh['turnNo'] = $state['turnNo'] + 1; // keep turn ids growing for the drawing storage
            $state = $fresh;
            return;
        }

        throw new GameError('invalid_move');
    }

    public function addSeat(array &$state, int $seat): void
    {
        if (!in_array($seat, $state['order'], true)) {
            $state['order'][] = $seat;
            $state['queue'][] = $seat;
            $state['scores'][$seat] = $state['scores'][$seat] ?? 0;
        }
    }

    public function removeSeat(array &$state, int $seat): void
    {
        $keep = function ($s) use ($seat) {
            return $s !== $seat;
        };
        $state['order'] = array_values(array_filter($state['order'], $keep));
        $state['queue'] = array_values(array_filter($state['queue'], $keep));
        unset($state['guessed'][$seat]);
        if ($state['drawer'] === $seat && in_array($state['phase'], ['choose', 'drawing'], true)) {
            if ($state['word'] === null) {
                $state['word'] = $state['choices'][0] ?? '';
            }
            $this->endTurn($state, 'left');
        }
    }

    /** Hidden word for guessers: "_ _ _ _" with hint letters filled in as time passes. */
    private function mask(array $state): string
    {
        $chars = self::chars((string)$state['word']);
        $show = (int)($state['hintsShown'] ?? 0);
        $open = array_flip(array_slice($state['hintOrder'], 0, $show));
        $out = [];
        foreach ($chars as $i => $ch) {
            $out[] = isset($open[$i]) || !preg_match('/\p{L}|\p{N}/u', $ch) ? $ch : '_';
        }
        return implode('', $out);
    }

    public function view(array $state, ?int $seat): array
    {
        $isDrawer = $seat !== null && $seat === $state['drawer'];
        $knows = $isDrawer || ($seat !== null && isset($state['guessed'][$seat])) || in_array($state['phase'], ['reveal', 'over'], true);
        $chat = [];
        foreach ($state['chat'] as $m) {
            if (($m['kind'] ?? '') === 'close' && ($m['to'] ?? -1) !== $seat) {
                continue;
            }
            unset($m['to']);
            $chat[] = $m;
        }
        $word = null;
        $mask = null;
        if ($state['phase'] === 'drawing') {
            if ($knows) {
                $word = $state['word'];
            }
            $mask = $this->mask($state);
        } elseif (in_array($state['phase'], ['reveal', 'over'], true)) {
            $word = $state['lastWord'];
        }
        return [
            'order'    => $state['order'],
            'opts'     => $state['opts'],
            'round'    => $state['round'],
            'rounds'   => $state['opts']['rounds'],
            'turnNo'   => $state['turnNo'],
            'phase'    => $state['phase'],
            'turn'     => $state['drawer'],
            'drawer'   => $state['drawer'],
            'choices'  => $isDrawer ? $state['choices'] : [],
            'choosing' => $state['phase'] === 'choose',
            'word'     => $word,
            'mask'     => $mask,
            'guessed'  => array_map('intval', array_keys($state['guessed'])),
            'scores'   => (object)$state['scores'],
            'chat'     => array_slice($chat, -25),
            'now'      => time(),
            'endsAt'   => (int)$state['endsAt'],
        ];
    }

    /** @return string[] the characters of a UTF-8 string */
    private static function chars(string $s): array
    {
        return preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /** Lower case, no accents, single spaces: "Arc-en-ciel" and "arc en ciel" match. */
    public static function norm(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e',
            'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ì' => 'i', 'ô' => 'o', 'ö' => 'o',
            'ò' => 'o', 'ó' => 'o', 'õ' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y',
            'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae', '’' => "'",
        ]);
        $s = preg_replace('/[^a-z0-9]+/', ' ', $s);
        return trim($s);
    }
}
