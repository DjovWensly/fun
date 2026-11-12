<?php
declare(strict_types=1);

/**
 * Truth or Dare / Action ou Vérité.
 * The current player picks truth, dare or random; the server draws a card from data/prompts.json
 * (plus any custom cards the group added). Players can join or leave at any moment.
 */
class TruthDare extends GameEngine
{
    public function meta(): array
    {
        return ['min' => 2, 'max' => 16, 'bots' => false, 'joinMidGame' => true];
    }

    public function options(array $in): array
    {
        $lang = in_array($in['lang'] ?? '', ['fr', 'en'], true) ? $in['lang'] : 'fr';
        $level = in_array($in['level'] ?? '', ['soft', 'party', 'hot'], true) ? $in['level'] : 'soft';
        $order = ($in['order'] ?? '') === 'random' ? 'random' : 'circle';
        return ['lang' => $lang, 'level' => $level, 'order' => $order];
    }

    public static function prompts(string $lang, string $level): array
    {
        $all = gp_prompts_load();
        $out = ['truth' => [], 'dare' => []];
        // 'hot' (18+) mixes the bold and the very spicy cards, without the tame ones.
        $levels = $level === 'hot' ? ['party', 'hot'] : ($level === 'party' ? ['soft', 'party'] : ['soft']);
        foreach (['truth', 'dare'] as $type) {
            foreach ($levels as $lv) {
                foreach ($all[$lang][$lv][$type] ?? [] as $p) {
                    $out[$type][] = $p;
                }
            }
        }
        return $out;
    }

    public function init(array $seats, array $options): array
    {
        sort($seats);
        $stats = [];
        foreach ($seats as $s) {
            $stats[$s] = ['truth' => 0, 'dare' => 0, 'skip' => 0];
        }
        return [
            'order'   => $seats,
            'turn'    => $options['order'] === 'random' ? $seats[array_rand($seats)] : $seats[0],
            'phase'   => 'choose',          // choose -> card -> (next player) choose
            'card'    => null,              // ['type' => 'truth', 'text' => '...', 'custom' => bool]
            'opts'    => $options,
            'used'    => ['truth' => [], 'dare' => []],
            'custom'  => ['truth' => [], 'dare' => []],
            'stats'   => $stats,
            'count'   => 0,
            'last'    => null,
        ];
    }

    public function currentSeat(array $state): ?int
    {
        return (int)$state['turn'];
    }

    public function move(array &$state, int $seat, array $move, bool $isHost): void
    {
        $type = (string)($move['type'] ?? '');

        if ($type === 'add_card') {
            $kind = ($move['kind'] ?? '') === 'dare' ? 'dare' : 'truth';
            $text = trim((string)($move['text'] ?? ''));
            $text = mb_substr(preg_replace('/\s+/u', ' ', $text), 0, 200);
            if (mb_strlen($text) < 3) {
                throw new GameError('card_too_short');
            }
            if (count($state['custom'][$kind]) >= 100) {
                throw new GameError('too_many_cards');
            }
            $state['custom'][$kind][] = $text;
            return;
        }

        $isTurn = $seat === (int)$state['turn'];

        if ($type === 'choose') {
            if (!$isTurn) {
                throw new GameError('not_your_turn');
            }
            if ($state['phase'] !== 'choose') {
                throw new GameError('invalid_move');
            }
            $kind = (string)($move['kind'] ?? 'random');
            if (!in_array($kind, ['truth', 'dare'], true)) {
                $kind = random_int(0, 1) ? 'truth' : 'dare';
            }
            $state['card'] = $this->draw($state, $kind);
            $state['phase'] = 'card';
            $state['stats'][$seat][$kind]++;
            $state['count']++;
            return;
        }

        if ($type === 'reroll') {
            // Swap the card for another of the same type (once per turn).
            if (!$isTurn || $state['phase'] !== 'card' || !empty($state['card']['rerolled'])) {
                throw new GameError('invalid_move');
            }
            $state['card'] = $this->draw($state, $state['card']['type']);
            $state['card']['rerolled'] = true;
            return;
        }

        if ($type === 'done' || $type === 'skip') {
            // The current player or the host can move the game on (useful if someone is AFK).
            if (!$isTurn && !$isHost) {
                throw new GameError('not_your_turn');
            }
            if ($type === 'skip' || $state['phase'] === 'choose') {
                $state['stats'][(int)$state['turn']]['skip']++;
            }
            $state['last'] = ['seat' => (int)$state['turn'], 'card' => $state['card'], 'skipped' => $type === 'skip'];
            $this->nextPlayer($state);
            return;
        }

        throw new GameError('invalid_move');
    }

    private function draw(array &$state, string $kind): array
    {
        $pool = self::prompts($state['opts']['lang'], $state['opts']['level'])[$kind];
        $custom = $state['custom'][$kind];
        // Custom cards are indexed after the built-in ones.
        $total = count($pool) + count($custom);
        if ($total === 0) {
            throw new GameError('no_cards');
        }
        $unused = array_values(array_diff(range(0, max(0, $total - 1)), $state['used'][$kind]));
        if (!$unused) {
            $state['used'][$kind] = [];
            $unused = range(0, $total - 1);
        }
        $i = $unused[array_rand($unused)];
        $state['used'][$kind][] = $i;
        $isCustom = $i >= count($pool);
        $text = $isCustom ? $custom[$i - count($pool)] : $pool[$i];
        return ['type' => $kind, 'text' => $text, 'custom' => $isCustom];
    }

    private function nextPlayer(array &$state): void
    {
        $order = $state['order'];
        $cur = (int)$state['turn'];
        if ($state['opts']['order'] === 'random' && count($order) > 1) {
            $others = array_values(array_filter($order, function ($s) use ($cur) {
                return $s !== $cur;
            }));
            $state['turn'] = $others[array_rand($others)];
        } else {
            $state['turn'] = self::nextInOrder($order, $cur);
        }
        $state['phase'] = 'choose';
        $state['card'] = null;
    }

    public function addSeat(array &$state, int $seat): void
    {
        if (!in_array($seat, $state['order'], true)) {
            $state['order'][] = $seat;
            $state['stats'][$seat] = ['truth' => 0, 'dare' => 0, 'skip' => 0];
        }
    }

    public function removeSeat(array &$state, int $seat): void
    {
        $order = $state['order'];
        if (!in_array($seat, $order, true)) {
            return;
        }
        if ((int)$state['turn'] === $seat && count($order) > 1) {
            $this->nextPlayer($state);
        }
        $state['order'] = array_values(array_filter($order, function ($s) use ($seat) {
            return $s !== $seat;
        }));
    }

    public function view(array $state, ?int $seat): array
    {
        $state['customCount'] = count($state['custom']['truth']) + count($state['custom']['dare']);
        unset($state['used'], $state['custom']);
        return $state;
    }
}
