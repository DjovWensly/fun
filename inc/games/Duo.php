<?php
declare(strict_types=1);

/**
 * Action ou Vérité · à distance (duo) — a remote, two-player variant for couples and flirts.
 *
 * - A thermometer: Tiède (1) → Chaud (2) → Brûlant (3). Going up needs BOTH players to ask for it,
 *   stays under each player's secret limit, and needs both to have confirmed they are 18+.
 *   Either player can cool it down alone, at any time.
 * - On your turn: Truth, Dare, or spin the Bonus/Trap wheel (double, mirror, reverse, secret card,
 *   "guess my answer", gift). The other player can instead throw you a challenge they wrote; you
 *   accept it, negotiate once, or play a joker.
 * - After a dare the other player validates it ("Validé" / "Pas convaincu") and points are scored.
 *   3 points buy a joker. An optional timer ends a dare that takes too long. "Pause" stops any card.
 * Photos and voice notes go through the players' own messaging app, never through this server.
 */
class Duo extends GameEngine
{
    public const BUY_JOKER = 3;
    private const WHEEL = [
        // result => weight
        'truth' => 16, 'dare' => 18, 'double' => 14, 'mirror' => 12,
        'reverse' => 12, 'secret' => 10, 'guess' => 12, 'gift' => 6,
    ];

    public function meta(): array
    {
        return ['min' => 2, 'max' => 2, 'bots' => false, 'joinMidGame' => false];
    }

    public function options(array $in): array
    {
        $lang = in_array($in['lang'] ?? '', ['fr', 'en'], true) ? $in['lang'] : 'fr';
        $timer = (int)($in['timer'] ?? 0);
        $jokers = (int)($in['jokers'] ?? 3);
        $timer = in_array($timer, [0, 120, 300], true) ? $timer : 0;
        $jokers = in_array($jokers, [2, 3, 5], true) ? $jokers : 3;
        return ['lang' => $lang, 'timer' => $timer, 'jokers' => $jokers];
    }

    public function init(array $seats, array $options): array
    {
        sort($seats);
        $options += ['lang' => 'fr', 'timer' => 0, 'jokers' => 3];
        return [
            'order'   => $seats,
            'opts'    => $options,
            'heat'    => 1,
            'limits'  => array_fill_keys($seats, 3),
            'adult'   => array_fill_keys($seats, false),
            'votesUp' => [],
            'turn'    => $seats[random_int(0, 1)],
            'phase'   => 'choose',
            'wheel'   => null,
            'wheelN'  => 0,
            'card'    => null,
            'offer'   => null,
            'points'  => array_fill_keys($seats, 0),
            'jokers'  => array_fill_keys($seats, (int)$options['jokers']),
            'endsAt'  => 0,
            'round'   => 1,
            'used'    => [],
            'chat'    => [['k' => 'sys', 'key' => 'duo_welcome']],
        ];
    }

    // ------------------------------------------------------------------ helpers

    private function other(array $state, int $seat): int
    {
        return $state['order'][0] === $seat ? $state['order'][1] : $state['order'][0];
    }

    private function sys(array &$state, string $key, array $vars = []): void
    {
        $this->push($state, ['k' => 'sys', 'key' => $key, 'vars' => (object)$vars]);
    }

    private function push(array &$state, array $msg): void
    {
        $state['chat'][] = $msg;
        if (count($state['chat']) > 60) {
            $state['chat'] = array_slice($state['chat'], -60);
        }
    }

    private static function clean($text, int $max = 240): string
    {
        $t = trim(preg_replace('/\s+/u', ' ', (string)$text));
        return mb_substr($t, 0, $max);
    }

    private static function levelName(int $heat): string
    {
        return $heat >= 3 ? 'hot' : ($heat === 2 ? 'party' : 'soft');
    }

    /** Draw a card text of a type (rtruth, rdare, rguess) at the current heat, avoiding repeats. */
    private function drawText(array &$state, string $type): string
    {
        $all = gp_prompts_load();
        $lv = self::levelName((int)$state['heat']);
        $pool = $all[$state['opts']['lang']][$lv][$type] ?? [];
        if (!$pool) {
            $pool = $all['fr'][$lv][$type] ?? [];
        }
        if (!$pool) {
            throw new GameError('no_cards');
        }
        $key = $lv . '/' . $type;
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

    private function startCard(array &$state, string $kind, string $text, array $doers, array $mods = [], string $from = 'deck'): void
    {
        $state['card'] = [
            'kind'  => $kind,              // truth | dare | guess
            'text'  => $text,
            'heat'  => (int)$state['heat'],
            'doers' => array_values($doers),
            'mods'  => (object)$mods,      // double, mirror, secret, reverse, bonus
            'from'  => $from,
            'done'  => (object)[],
            'answer' => null,
            'guess' => null,
        ];
        $state['phase'] = 'card';
        $state['endsAt'] = $state['opts']['timer'] > 0 && $kind !== 'guess' ? time() + (int)$state['opts']['timer'] : 0;
    }

    private function points(array $state): int
    {
        $c = $state['card'];
        $mods = (array)$c['mods'];
        $p = (int)$c['heat'];
        if (!empty($mods['double'])) {
            $p *= 2;
        }
        if (!empty($mods['bonus'])) {
            $p += 1;
        }
        return $p;
    }

    private function nextTurn(array &$state): void
    {
        $state['turn'] = $this->other($state, (int)$state['turn']);
        $state['phase'] = 'choose';
        $state['card'] = null;
        $state['offer'] = null;
        $state['endsAt'] = 0;
        $state['round']++;
    }

    private function allowedUpTo(array $state): int
    {
        $max = min(array_map('intval', $state['limits']));
        if (in_array(false, $state['adult'], true)) {
            $max = min($max, 1); // above "Tiède" both must have confirmed they are 18+
        }
        return max(1, $max);
    }

    // ------------------------------------------------------------------ engine API

    public function currentSeat(array $state): ?int
    {
        return (int)$state['turn'];
    }

    public function tickDue(array $state): bool
    {
        return $state['phase'] === 'card' && (int)$state['endsAt'] > 0 && time() >= (int)$state['endsAt'];
    }

    public function tick(array &$state): bool
    {
        if (!$this->tickDue($state)) {
            return false;
        }
        $this->sys($state, 'duo_timeout', ['seat' => $state['card']['doers'][0]]);
        $this->nextTurn($state);
        return true;
    }

    public function move(array &$state, int $seat, array $move, bool $isHost): void
    {
        if (!in_array($seat, $state['order'], true)) {
            throw new GameError('invalid_move');
        }
        $type = (string)($move['type'] ?? '');
        $other = $this->other($state, $seat);
        $isActor = $seat === (int)$state['turn'];
        $card = $state['card'];
        $doers = $card ? array_map('intval', $card['doers']) : [];

        switch ($type) {
            // ---- chat, settings and thermometer: any time
            case 'say':
                $t = self::clean($move['text'] ?? '');
                if ($t === '') {
                    throw new GameError('invalid_move');
                }
                $this->push($state, ['k' => 'msg', 's' => $seat, 't' => $t]);
                return;

            case 'limit':
                $v = max(1, min(3, (int)($move['value'] ?? 3)));
                $state['limits'][$seat] = $v;
                if ((int)$state['heat'] > $this->allowedUpTo($state)) {
                    $state['heat'] = $this->allowedUpTo($state);
                    $this->sys($state, 'duo_heat_down', ['n' => $state['heat']]);
                }
                $state['votesUp'] = [];
                return;

            case 'adult':
                $state['adult'][$seat] = !empty($move['value']);
                if ((int)$state['heat'] > $this->allowedUpTo($state)) {
                    $state['heat'] = $this->allowedUpTo($state);
                    $this->sys($state, 'duo_heat_down', ['n' => $state['heat']]);
                }
                return;

            case 'heat_up':
                if ((int)$state['heat'] >= 3) {
                    throw new GameError('invalid_move');
                }
                $state['votesUp'][$seat] = true;
                if (count($state['votesUp']) < 2) {
                    $this->sys($state, 'duo_wants_up', ['seat' => $seat]);
                    return;
                }
                $state['votesUp'] = [];
                if ((int)$state['heat'] + 1 <= $this->allowedUpTo($state)) {
                    $state['heat']++;
                    $this->sys($state, 'duo_heat_up', ['n' => $state['heat']]);
                } else {
                    // somebody's secret limit (or a missing 18+ confirmation): nobody learns who
                    $this->sys($state, 'duo_heat_stay', []);
                }
                return;

            case 'heat_down':
                if ((int)$state['heat'] <= 1) {
                    throw new GameError('invalid_move');
                }
                $state['heat']--;
                $state['votesUp'] = [];
                $this->sys($state, 'duo_heat_down', ['n' => $state['heat']]);
                return;

            case 'buy_joker':
                if ($state['points'][$seat] < self::BUY_JOKER) {
                    throw new GameError('duo_not_enough_points');
                }
                $state['points'][$seat] -= self::BUY_JOKER;
                $state['jokers'][$seat]++;
                $this->sys($state, 'duo_bought_joker', ['seat' => $seat]);
                return;

            case 'pause':
                // Either player stops the current card at once, no reason needed, no penalty.
                if (!in_array($state['phase'], ['card', 'offer', 'counter', 'judge', 'secret_guess', 'secret_check', 'guess_check'], true)) {
                    throw new GameError('invalid_move');
                }
                $this->sys($state, 'duo_paused', []);
                $this->nextTurn($state);
                return;

            // ---- the turn
            case 'choose':
                if (!$isActor || $state['phase'] !== 'choose') {
                    throw new GameError('not_your_turn');
                }
                $kind = (string)($move['kind'] ?? '');
                if ($kind === 'truth') {
                    $this->startCard($state, 'truth', $this->drawText($state, 'rtruth'), [$seat]);
                } elseif ($kind === 'dare') {
                    $this->startCard($state, 'dare', $this->drawText($state, 'rdare'), [$seat]);
                } elseif ($kind === 'wheel') {
                    $this->spin($state, $seat, $other);
                } else {
                    throw new GameError('invalid_move');
                }
                return;

            case 'challenge':
                // the other player writes a dare for the one whose turn it is
                if ($isActor || $state['phase'] !== 'choose') {
                    throw new GameError('invalid_move');
                }
                $t = self::clean($move['text'] ?? '', 200);
                if (mb_strlen($t) < 3) {
                    throw new GameError('card_too_short');
                }
                $state['offer'] = ['from' => $seat, 'text' => $t, 'counter' => null];
                $state['phase'] = 'offer';
                return;

            case 'offer_accept':
                if (!$isActor || $state['phase'] !== 'offer') {
                    throw new GameError('invalid_move');
                }
                $this->startCard($state, 'dare', $state['offer']['text'], [$seat], ['bonus' => true], 'offer');
                return;

            case 'offer_counter':
                if (!$isActor || $state['phase'] !== 'offer') {
                    throw new GameError('invalid_move');
                }
                $t = self::clean($move['text'] ?? '', 200);
                if (mb_strlen($t) < 3) {
                    throw new GameError('card_too_short');
                }
                $state['offer']['counter'] = $t;
                $state['phase'] = 'counter';
                return;

            case 'counter_reply':
                // the challenger takes the counter-proposal or insists on the original
                if ($isActor || $state['phase'] !== 'counter') {
                    throw new GameError('invalid_move');
                }
                $text = !empty($move['ok']) ? $state['offer']['counter'] : $state['offer']['text'];
                $this->startCard($state, 'dare', $text, [(int)$state['turn']], ['bonus' => true], 'offer');
                return;

            case 'joker':
                $mine = ($state['phase'] === 'card' && in_array($seat, $doers, true))
                    || ($isActor && in_array($state['phase'], ['offer', 'counter'], true));
                if (!$mine) {
                    throw new GameError('invalid_move');
                }
                if ($state['jokers'][$seat] <= 0) {
                    throw new GameError('duo_no_jokers');
                }
                $state['jokers'][$seat]--;
                $this->sys($state, 'duo_joker_used', ['seat' => $seat]);
                $this->nextTurn($state);
                return;

            case 'done':
                if ($state['phase'] !== 'card' || !in_array($seat, $doers, true) || $card['kind'] === 'guess') {
                    throw new GameError('invalid_move');
                }
                $note = self::clean($move['note'] ?? '');
                $done = (array)$card['done'];
                $done[$seat] = $note;
                $state['card']['done'] = (object)$done;
                if ($note !== '') {
                    $this->push($state, ['k' => 'proof', 's' => $seat, 't' => $note]);
                }
                $mods = (array)$card['mods'];
                if (!empty($mods['mirror'])) {
                    if (count($done) >= count($doers)) {
                        $p = $this->points($state);
                        foreach ($doers as $d) {
                            $state['points'][$d] += $p;
                        }
                        $this->sys($state, 'duo_mirror_done', ['n' => $p]);
                        $this->nextTurn($state);
                    }
                    return;
                }
                $state['endsAt'] = 0;
                $state['phase'] = !empty($mods['secret']) ? 'secret_guess' : 'judge';
                return;

            case 'judge':
                if ($state['phase'] !== 'judge' || in_array($seat, $doers, true)) {
                    throw new GameError('invalid_move');
                }
                if (!empty($move['ok'])) {
                    $p = $this->points($state);
                    $state['points'][$doers[0]] += $p;
                    $this->sys($state, 'duo_validated', ['seat' => $doers[0], 'n' => $p]);
                } else {
                    $this->sys($state, 'duo_rejected', ['seat' => $doers[0]]);
                }
                $this->nextTurn($state);
                return;

            case 'secret_guess':
                // the other player guesses what the secret card was
                if ($state['phase'] !== 'secret_guess' || in_array($seat, $doers, true)) {
                    throw new GameError('invalid_move');
                }
                $t = self::clean($move['text'] ?? '', 200);
                if ($t === '') {
                    throw new GameError('invalid_move');
                }
                $state['card']['guess'] = $t;
                $state['phase'] = 'secret_check';
                return;

            case 'secret_check':
                if ($state['phase'] !== 'secret_check' || !in_array($seat, $doers, true)) {
                    throw new GameError('invalid_move');
                }
                $p = $this->points($state);
                $state['points'][$seat] += $p;
                if (!empty($move['ok'])) {
                    $state['points'][$other] += 1;
                }
                $this->sys($state, !empty($move['ok']) ? 'duo_secret_found' : 'duo_secret_missed', ['seat' => $other, 'text' => $state['card']['text']]);
                $this->nextTurn($state);
                return;

            case 'answer':
                // "guess my answer": the doer answers in secret, the other guesses
                if ($state['phase'] !== 'card' || $card['kind'] !== 'guess') {
                    throw new GameError('invalid_move');
                }
                $t = self::clean($move['text'] ?? '', 200);
                if ($t === '') {
                    throw new GameError('invalid_move');
                }
                if (in_array($seat, $doers, true)) {
                    $state['card']['answer'] = $t;
                } else {
                    $state['card']['guess'] = $t;
                }
                if ($state['card']['answer'] !== null && $state['card']['guess'] !== null) {
                    $state['phase'] = 'guess_check';
                }
                return;

            case 'guess_check':
                if ($state['phase'] !== 'guess_check' || !in_array($seat, $doers, true)) {
                    throw new GameError('invalid_move');
                }
                if (!empty($move['ok'])) {
                    $p = $this->points($state);
                    $state['points'][$seat] += $p;
                    $state['points'][$other] += $p;
                    $this->sys($state, 'duo_guess_match', ['n' => $p]);
                } else {
                    $this->sys($state, 'duo_guess_miss', []);
                }
                $this->nextTurn($state);
                return;
        }
        throw new GameError('invalid_move');
    }

    /** The Bonus/Trap wheel. */
    private function spin(array &$state, int $actor, int $other): void
    {
        $total = array_sum(self::WHEEL);
        $r = random_int(1, $total);
        $result = 'dare';
        foreach (self::WHEEL as $k => $w) {
            $r -= $w;
            if ($r <= 0) {
                $result = $k;
                break;
            }
        }
        $state['wheelN']++;
        $state['wheel'] = ['result' => $result, 'n' => $state['wheelN']];
        $kind = random_int(0, 1) ? 'dare' : 'truth';
        $type = $kind === 'dare' ? 'rdare' : 'rtruth';
        switch ($result) {
            case 'truth':
                $this->startCard($state, 'truth', $this->drawText($state, 'rtruth'), [$actor]);
                break;
            case 'dare':
                $this->startCard($state, 'dare', $this->drawText($state, 'rdare'), [$actor]);
                break;
            case 'double':
                $this->startCard($state, $kind, $this->drawText($state, $type), [$actor], ['double' => true]);
                break;
            case 'mirror':
                $this->startCard($state, 'dare', $this->drawText($state, 'rdare'), [$actor, $other], ['mirror' => true]);
                break;
            case 'reverse':
                $this->startCard($state, $kind, $this->drawText($state, $type), [$other], ['reverse' => true]);
                break;
            case 'secret':
                $this->startCard($state, 'dare', $this->drawText($state, 'rdare'), [$actor], ['secret' => true]);
                break;
            case 'guess':
                $this->startCard($state, 'guess', $this->drawText($state, 'rguess'), [$actor]);
                break;
            case 'gift':
                $state['points'][$actor] += 1;
                $this->sys($state, 'duo_gift', ['seat' => $actor]);
                $this->nextTurn($state);
                break;
        }
    }

    public function view(array $state, ?int $seat): array
    {
        $card = $state['card'];
        if ($card) {
            $doers = array_map('intval', $card['doers']);
            $mods = (array)$card['mods'];
            $card['mods'] = (object)$mods;
            $card['done'] = (object)(array)$card['done'];
            $isDoer = $seat !== null && in_array($seat, $doers, true);
            // a secret card stays hidden from the other player until the reveal
            if (!empty($mods['secret']) && !$isDoer && $state['phase'] !== 'secret_check') {
                $card['text'] = null;
            }
            // "guess my answer": nobody sees the other's answer before both are in
            if ($card['kind'] === 'guess' && $state['phase'] !== 'guess_check') {
                $mine = $isDoer ? 'answer' : 'guess';
                $theirs = $isDoer ? 'guess' : 'answer';
                $card[$theirs . 'Given'] = $card[$theirs] !== null;
                $card[$theirs] = null;
                $card[$mine . 'Given'] = $card[$mine] !== null;
            }
        }
        $partnerWantsUp = false;
        if ($seat !== null) {
            $o = $this->other($state, $seat);
            $partnerWantsUp = !empty($state['votesUp'][$o]);
        }
        return [
            'order'   => $state['order'],
            'opts'    => $state['opts'],
            'heat'    => (int)$state['heat'],
            'turn'    => (int)$state['turn'],
            'phase'   => $state['phase'],
            'wheel'   => $state['wheel'],
            'card'    => $card,
            'offer'   => $state['offer'],
            'points'  => (object)$state['points'],
            'jokers'  => (object)$state['jokers'],
            'endsAt'  => (int)$state['endsAt'],
            'now'     => time(),
            'round'   => $state['round'],
            'chat'    => array_slice($state['chat'], -40),
            // only your own settings: the other's limit stays secret
            'myLimit' => $seat !== null ? (int)$state['limits'][$seat] : null,
            'myAdult' => $seat !== null ? (bool)$state['adult'][$seat] : null,
            'iWantUp' => $seat !== null && !empty($state['votesUp'][$seat]),
            'partnerWantsUp' => $partnerWantsUp,
            'buyJoker' => self::BUY_JOKER,
        ];
    }
}
