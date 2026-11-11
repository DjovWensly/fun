<?php
declare(strict_types=1);

/**
 * Shared rules for "everybody answers in secret, then we reveal" games
 * (Qui de nous deux ?, Tu préfères… ?).
 *
 * Phases: 'vote' (each player answers; answers stay hidden) -> 'reveal' (everyone sees them)
 * -> next card. The reveal happens as soon as every player has answered, or when the host
 * forces it (someone away). Players can join or leave at any time.
 */
abstract class PollGame extends GameEngine
{
    /** Which card type in data/prompts.json / gp_prompts this game uses. */
    abstract protected function cardType(): string;

    /** Turn the stored text into the card the players see. */
    abstract protected function makeCard(string $text): array;

    /** Normalise an answer, or return null if it is not allowed. */
    abstract protected function cleanVote(array $state, $value);

    public function meta(): array
    {
        return ['min' => 2, 'max' => 12, 'bots' => false, 'joinMidGame' => true];
    }

    public function options(array $in): array
    {
        $lang = in_array($in['lang'] ?? '', ['fr', 'en'], true) ? $in['lang'] : 'fr';
        $level = in_array($in['level'] ?? '', ['soft', 'party'], true) ? $in['level'] : 'soft';
        return ['lang' => $lang, 'level' => $level];
    }

    protected function pool(array $state): array
    {
        $all = gp_prompts_load();
        $levels = $state['opts']['level'] === 'party' ? ['soft', 'party'] : ['soft'];
        $out = [];
        foreach ($levels as $lv) {
            foreach ($all[$state['opts']['lang']][$lv][$this->cardType()] ?? [] as $t) {
                $out[] = $t;
            }
        }
        return $out;
    }

    public function init(array $seats, array $options): array
    {
        sort($seats);
        $state = [
            'order'   => $seats,
            'opts'    => $options,
            'phase'   => 'vote',
            'card'    => null,
            'votes'   => [],
            'used'    => [],
            'round'   => 0,
            'agreed'  => 0,     // rounds where everybody gave the same answer
            'tally'   => [],    // seat => how many times the group picked them (Qui de nous)
            'history' => [],
        ];
        $this->nextCard($state);
        return $state;
    }

    protected function nextCard(array &$state): void
    {
        $pool = $this->pool($state);
        if (!$pool) {
            throw new GameError('no_cards');
        }
        $unused = array_values(array_diff(array_keys($pool), $state['used']));
        if (!$unused) {
            $state['used'] = [];
            $unused = array_keys($pool);
        }
        $i = $unused[array_rand($unused)];
        $state['used'][] = $i;
        $state['card'] = $this->makeCard($pool[$i]);
        $state['votes'] = [];
        $state['phase'] = 'vote';
        $state['round']++;
    }

    public function move(array &$state, int $seat, array $move, bool $isHost): void
    {
        $type = (string)($move['type'] ?? '');

        if ($type === 'vote') {
            if ($state['phase'] !== 'vote') {
                throw new GameError('invalid_move');
            }
            $v = $this->cleanVote($state, $move['value'] ?? null);
            if ($v === null) {
                throw new GameError('invalid_move');
            }
            $state['votes'][$seat] = $v;
            if ($this->everyoneVoted($state)) {
                $this->reveal($state);
            }
            return;
        }

        if ($type === 'reveal') {
            // The host can reveal without waiting for someone who is away.
            if (!$isHost) {
                throw new GameError('host_only');
            }
            if ($state['phase'] !== 'vote' || !$state['votes']) {
                throw new GameError('invalid_move');
            }
            $this->reveal($state);
            return;
        }

        if ($type === 'next') {
            if ($state['phase'] !== 'reveal' && !$isHost) {
                throw new GameError('invalid_move');
            }
            if ($state['phase'] === 'vote') {
                $state['round']--; // host skipped this card: it does not count
            }
            $this->nextCard($state);
            return;
        }

        throw new GameError('invalid_move');
    }

    protected function everyoneVoted(array $state): bool
    {
        foreach ($state['order'] as $s) {
            if (!array_key_exists($s, $state['votes'])) {
                return false;
            }
        }
        return true;
    }

    protected function reveal(array &$state): void
    {
        $state['phase'] = 'reveal';
        $values = array_values($state['votes']);
        $agree = count($values) >= 2 && count(array_unique(array_map('strval', $values))) === 1;
        if ($agree) {
            $state['agreed']++;
        }
        $this->afterReveal($state);
        $state['history'][] = ['card' => $state['card'], 'votes' => $state['votes'], 'agree' => $agree];
        $state['history'] = array_slice($state['history'], -5);
    }

    protected function afterReveal(array &$state): void
    {
    }

    public function addSeat(array &$state, int $seat): void
    {
        if (!in_array($seat, $state['order'], true)) {
            $state['order'][] = $seat;
        }
    }

    public function removeSeat(array &$state, int $seat): void
    {
        $state['order'] = array_values(array_filter($state['order'], function ($s) use ($seat) {
            return $s !== $seat;
        }));
        unset($state['votes'][$seat]);
        if ($state['phase'] === 'vote' && $state['votes'] && $this->everyoneVoted($state)) {
            $this->reveal($state);
        }
    }

    public function view(array $state, ?int $seat): array
    {
        $revealed = $state['phase'] === 'reveal';
        return [
            'order'   => $state['order'],
            'opts'    => $state['opts'],
            'phase'   => $state['phase'],
            'card'    => $state['card'],
            'round'   => $state['round'],
            'agreed'  => $state['agreed'],
            'tally'   => (object)$state['tally'],
            // While voting, players only learn who has answered, plus their own answer.
            'voted'   => array_map('intval', array_keys($state['votes'])),
            'mine'    => $seat !== null && array_key_exists($seat, $state['votes']) ? $state['votes'][$seat] : null,
            'votes'   => $revealed ? (object)$state['votes'] : null,
            'agree'   => $revealed ? (end($state['history'])['agree'] ?? false) : null,
        ];
    }
}
