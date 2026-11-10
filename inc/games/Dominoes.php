<?php
declare(strict_types=1);

/**
 * Double-six dominoes, 2 to 4 players, 7 tiles each.
 *  - With fewer than 4 players the remaining tiles form a boneyard you must draw from when stuck.
 *  - A round ends when someone plays their last tile ("Domino!") or when nobody can play (blocked).
 *  - The round winner scores the pips left in the other players' hands. First to the target wins.
 *  - Round 1 is started by the holder of the highest double, later rounds by the previous winner.
 */
class Dominoes extends GameEngine
{
    public function meta(): array
    {
        return ['min' => 2, 'max' => 4, 'bots' => true, 'joinMidGame' => false];
    }

    public function options(array $in): array
    {
        $target = (int)($in['target'] ?? 100);
        if (!in_array($target, [50, 100, 150, 200], true)) {
            $target = 100;
        }
        return ['target' => $target];
    }

    public function init(array $seats, array $options): array
    {
        sort($seats);
        $state = [
            'seats'  => $seats,
            'target' => $options['target'] ?? 100,
            'scores' => array_fill_keys($seats, 0),
            'round'  => 0,
            'winner' => null,
        ];
        $this->deal($state, null);
        return $state;
    }

    private function deal(array &$state, ?int $starter): void
    {
        $tiles = [];
        for ($a = 0; $a <= 6; $a++) {
            for ($b = $a; $b <= 6; $b++) {
                $tiles[] = [$a, $b];
            }
        }
        shuffle($tiles);
        $hands = [];
        foreach ($state['seats'] as $s) {
            $hands[$s] = array_splice($tiles, 0, 7);
        }
        if ($starter === null) {
            $starter = $this->highestDoubleHolder($hands);
        }
        $state['round']++;
        $state['hands'] = $hands;
        $state['boneyard'] = $tiles;
        $state['chain'] = [];
        $state['turn'] = $starter;
        $state['passes'] = 0;
        $state['phase'] = 'playing';
        $state['result'] = null;
        $state['last'] = null;
        $state['log'] = [['t' => 'round', 'n' => $state['round'], 'seat' => $starter]];
    }

    private function highestDoubleHolder(array $hands): int
    {
        $best = -1;
        $bestSeat = (int)array_key_first($hands);
        foreach ($hands as $seat => $hand) {
            foreach ($hand as $t) {
                // doubles rank above everything, then by pip count
                $rank = ($t[0] === $t[1] ? 100 : 0) + $t[0] + $t[1];
                if ($rank > $best) {
                    $best = $rank;
                    $bestSeat = (int)$seat;
                }
            }
        }
        return $bestSeat;
    }

    private function ends(array $state): ?array
    {
        if (!$state['chain']) {
            return null;
        }
        $last = end($state['chain']);
        return [$state['chain'][0][0], $last[1]];
    }

    /** Sides ('L','R') where $tile fits. Empty chain => ['L']. */
    private function fits(array $state, array $tile): array
    {
        $ends = $this->ends($state);
        if ($ends === null) {
            return ['L'];
        }
        $sides = [];
        if ($tile[0] === $ends[0] || $tile[1] === $ends[0]) {
            $sides[] = 'L';
        }
        if ($tile[0] === $ends[1] || $tile[1] === $ends[1]) {
            $sides[] = 'R';
        }
        return $sides;
    }

    private function canPlay(array $state, int $seat): bool
    {
        foreach ($state['hands'][$seat] as $t) {
            if ($this->fits($state, $t)) {
                return true;
            }
        }
        return false;
    }

    private static function pips(array $hand): int
    {
        $n = 0;
        foreach ($hand as $t) {
            $n += $t[0] + $t[1];
        }
        return $n;
    }

    private function addLog(array &$state, array $entry): void
    {
        $state['log'][] = $entry;
        if (count($state['log']) > 12) {
            $state['log'] = array_slice($state['log'], -12);
        }
    }

    public function currentSeat(array $state): ?int
    {
        return $state['phase'] === 'playing' ? (int)$state['turn'] : null;
    }

    public function move(array &$state, int $seat, array $move, bool $isHost): void
    {
        $type = (string)($move['type'] ?? '');

        if ($type === 'next_round') {
            if ($state['phase'] === 'round_over') {
                $this->deal($state, $state['result']['winner'] ?? $state['turn']);
                return;
            }
            if ($state['phase'] === 'match_over') {
                if (!$isHost) {
                    throw new GameError('host_only');
                }
                $state['scores'] = array_fill_keys($state['seats'], 0);
                $state['round'] = 0;
                $state['winner'] = null;
                $this->deal($state, null);
                return;
            }
            throw new GameError('invalid_move');
        }

        if ($state['phase'] !== 'playing') {
            throw new GameError('round_over');
        }
        if ($seat !== (int)$state['turn']) {
            throw new GameError('not_your_turn');
        }

        if ($type === 'play') {
            $tile = $move['tile'] ?? null;
            if (!is_array($tile) || count($tile) !== 2) {
                throw new GameError('invalid_move');
            }
            $tile = [(int)$tile[0], (int)$tile[1]];
            $idx = $this->findTile($state['hands'][$seat], $tile);
            if ($idx === null) {
                throw new GameError('invalid_move');
            }
            $tile = $state['hands'][$seat][$idx]; // canonical orientation
            $sides = $this->fits($state, $tile);
            if (!$sides) {
                throw new GameError('tile_does_not_fit');
            }
            $side = in_array($move['side'] ?? '', $sides, true) ? $move['side'] : $sides[0];
            $this->place($state, $tile, $side);
            array_splice($state['hands'][$seat], $idx, 1);
            $state['passes'] = 0;
            $state['last'] = ['seat' => $seat, 'tile' => $tile, 'side' => $side];
            $this->addLog($state, ['t' => 'play', 'seat' => $seat, 'tile' => $tile]);

            if (!$state['hands'][$seat]) {
                $this->endRound($state, $seat, 'domino');
                return;
            }
            $this->advance($state);
            return;
        }

        if ($type === 'draw') {
            if ($this->canPlay($state, $seat)) {
                throw new GameError('you_can_play');
            }
            if (!$state['boneyard']) {
                throw new GameError('boneyard_empty');
            }
            $state['hands'][$seat][] = array_pop($state['boneyard']);
            $this->addLog($state, ['t' => 'draw', 'seat' => $seat]);
            // Still the same player's turn: they play the drawn tile or draw again.
            return;
        }

        if ($type === 'pass') {
            if ($this->canPlay($state, $seat) || $state['boneyard']) {
                throw new GameError('cannot_pass');
            }
            $this->doPass($state, $seat);
            if ($state['phase'] === 'playing') {
                $this->advance($state, false);
            }
            return;
        }

        throw new GameError('invalid_move');
    }

    private function doPass(array &$state, int $seat): void
    {
        $state['passes']++;
        $this->addLog($state, ['t' => 'pass', 'seat' => $seat]);
        if ($state['passes'] >= count($state['seats'])) {
            $this->endBlocked($state);
        }
    }

    /**
     * Move the turn to the next player. Players who cannot play and cannot draw pass automatically
     * so nobody has to click "pass" over and over.
     */
    private function advance(array &$state, bool $fromPlay = true): void
    {
        $state['turn'] = self::nextInOrder($state['seats'], (int)$state['turn']);
        $guard = 0;
        while ($state['phase'] === 'playing' && $guard++ < 10) {
            $s = (int)$state['turn'];
            if ($this->canPlay($state, $s) || $state['boneyard']) {
                return;
            }
            $this->doPass($state, $s);
            if ($state['phase'] !== 'playing') {
                return;
            }
            $state['turn'] = self::nextInOrder($state['seats'], $s);
        }
    }

    private function findTile(array $hand, array $tile): ?int
    {
        foreach ($hand as $i => $t) {
            if (($t[0] === $tile[0] && $t[1] === $tile[1]) || ($t[0] === $tile[1] && $t[1] === $tile[0])) {
                return $i;
            }
        }
        return null;
    }

    private function place(array &$state, array $tile, string $side): void
    {
        $ends = $this->ends($state);
        if ($ends === null) {
            $state['chain'][] = $tile;
            return;
        }
        if ($side === 'L') {
            // the right half of the new tile must touch the left end
            $t = $tile[1] === $ends[0] ? $tile : [$tile[1], $tile[0]];
            array_unshift($state['chain'], $t);
        } else {
            $t = $tile[0] === $ends[1] ? $tile : [$tile[1], $tile[0]];
            $state['chain'][] = $t;
        }
    }

    private function endBlocked(array &$state): void
    {
        $low = null;
        $winner = null;
        $tie = false;
        foreach ($state['seats'] as $s) {
            $p = self::pips($state['hands'][$s]);
            if ($low === null || $p < $low) {
                $low = $p;
                $winner = $s;
                $tie = false;
            } elseif ($p === $low) {
                $tie = true;
            }
        }
        $this->endRound($state, $tie ? null : $winner, 'blocked');
    }

    private function endRound(array &$state, ?int $winner, string $reason): void
    {
        $points = 0;
        $pips = [];
        foreach ($state['seats'] as $s) {
            $pips[$s] = self::pips($state['hands'][$s]);
            if ($winner !== null && $s !== $winner) {
                $points += $pips[$s];
            }
        }
        if ($winner !== null) {
            $state['scores'][$winner] += $points;
        }
        $state['result'] = ['winner' => $winner, 'reason' => $reason, 'points' => $points, 'pips' => $pips];
        $this->addLog($state, ['t' => 'end', 'seat' => $winner, 'reason' => $reason, 'points' => $points]);
        $state['phase'] = 'round_over';
        if ($winner !== null && $state['scores'][$winner] >= $state['target']) {
            $state['phase'] = 'match_over';
            $state['winner'] = $winner;
        }
    }

    public function botMove(array &$state, int $seat): void
    {
        if ($state['phase'] !== 'playing' || (int)$state['turn'] !== $seat) {
            return;
        }
        // Draw until a tile fits (or the boneyard is empty).
        while (!$this->canPlay($state, $seat) && $state['boneyard']) {
            $this->move($state, $seat, ['type' => 'draw'], false);
        }
        if (!$this->canPlay($state, $seat)) {
            $this->move($state, $seat, ['type' => 'pass'], false);
            return;
        }
        // Count how many of each number the bot holds: prefer leaving ends it can follow.
        $count = array_fill(0, 7, 0);
        foreach ($state['hands'][$seat] as $t) {
            $count[$t[0]]++;
            $count[$t[1]]++;
        }
        $best = null;
        $bestScore = -1;
        foreach ($state['hands'][$seat] as $t) {
            foreach ($this->fits($state, $t) as $side) {
                $ends = $this->ends($state);
                $open = $t[0];
                if ($ends !== null) {
                    $touch = $side === 'L' ? $ends[0] : $ends[1];
                    $open = $t[0] === $touch ? $t[1] : $t[0];
                }
                $score = ($t[0] + $t[1]) * 3 + ($t[0] === $t[1] ? 8 : 0) + $count[$open] * 2;
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = ['type' => 'play', 'tile' => $t, 'side' => $side];
                }
            }
        }
        $this->move($state, $seat, $best, false);
    }

    public function view(array $state, ?int $seat): array
    {
        $counts = [];
        foreach ($state['seats'] as $s) {
            $counts[$s] = count($state['hands'][$s]);
        }
        $showAll = $state['phase'] !== 'playing';
        $myTurn = $seat !== null && $state['phase'] === 'playing' && (int)$state['turn'] === $seat;
        $playable = [];
        if ($seat !== null && isset($state['hands'][$seat]) && $myTurn) {
            foreach ($state['hands'][$seat] as $i => $t) {
                $f = $this->fits($state, $t);
                if ($f) {
                    $playable[$i] = $f;
                }
            }
        }
        return [
            'seats'    => $state['seats'],
            'target'   => $state['target'],
            'scores'   => $state['scores'],
            'round'    => $state['round'],
            'phase'    => $state['phase'],
            'turn'     => $state['turn'],
            'chain'    => $state['chain'],
            'boneyard' => count($state['boneyard']),
            'counts'   => $counts,
            'hand'     => ($seat !== null && isset($state['hands'][$seat])) ? $state['hands'][$seat] : [],
            'playable' => (object)$playable,
            'canDraw'  => $myTurn && !$playable && $state['boneyard'],
            'hands'    => $showAll ? $state['hands'] : null,
            'result'   => $state['result'],
            'winner'   => $state['winner'],
            'last'     => $state['last'],
            'log'      => $state['log'],
        ];
    }
}
