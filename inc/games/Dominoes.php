<?php
declare(strict_types=1);

/**
 * Double-six dominoes, 2 to 4 players, 7 tiles each.
 *
 * Modes:
 *  - draw  : classic. When you can't play you draw from the boneyard (if any tiles are left).
 *  - block : no drawing. When you can't play you pass. Common in Haiti and the Caribbean.
 *  - fives : "All fives". Like draw, but each time the two open ends add up to a multiple of 5
 *            you score that number right away.
 * Options:
 *  - teams : 2 vs 2 with 4 players. Partners sit across (1st+3rd vs 2nd+4th) and share a score.
 *  - goal  : 'points' (first to N points) or 'rounds' (first to win N rounds).
 *
 * A round ends when someone plays their last tile, or when nobody can play (blocked). The round
 * winner scores the pips left in the opponents' hands (rounded to 5 in "fives").
 */
class Dominoes extends GameEngine
{
    public const MODES = ['draw', 'block', 'fives'];

    public function meta(): array
    {
        return ['min' => 2, 'max' => 4, 'bots' => true, 'joinMidGame' => false];
    }

    public function options(array $in): array
    {
        $mode = in_array($in['mode'] ?? '', self::MODES, true) ? $in['mode'] : 'draw';
        $goal = ($in['goal'] ?? '') === 'rounds' ? 'rounds' : 'points';
        $target = (int)($in['target'] ?? 0);
        $allowed = $goal === 'rounds' ? [3, 5, 7, 10] : [50, 100, 150, 200, 250];
        if (!in_array($target, $allowed, true)) {
            $target = $goal === 'rounds' ? 5 : ($mode === 'fives' ? 150 : 100);
        }
        return ['mode' => $mode, 'teams' => !empty($in['teams']), 'goal' => $goal, 'target' => $target];
    }

    public function init(array $seats, array $options): array
    {
        sort($seats);
        $options += ['mode' => 'draw', 'teams' => false, 'goal' => 'points', 'target' => 100];
        $teams = !empty($options['teams']);
        if ($teams && count($seats) !== 4) {
            throw new GameError('need_four');
        }
        // A "side" scores together: the two partners in team play, otherwise each player alone.
        $sideOf = [];
        foreach ($seats as $i => $s) {
            $sideOf[$s] = $teams ? $i % 2 : $s;
        }
        $sides = array_values(array_unique($sideOf));
        $state = [
            'seats'  => $seats,
            'mode'   => $options['mode'],
            'teams'  => $teams,
            'goal'   => $options['goal'],
            'target' => $options['target'],
            'sideOf' => $sideOf,
            'scores' => array_fill_keys($sides, 0),
            'wins'   => array_fill_keys($sides, 0),
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
        $state['boneyard'] = $tiles; // in block mode these tiles just sit out ("sleeping")
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

    /** Rooms created before game modes existed: fill in the new fields. */
    private function upgrade(array &$state): void
    {
        if (isset($state['sideOf'])) {
            return;
        }
        $state += ['mode' => 'draw', 'teams' => false, 'goal' => 'points'];
        foreach ($state['seats'] as $s) {
            $state['sideOf'][$s] = $s;
            $state['wins'][$s] = $state['wins'][$s] ?? 0;
        }
        if (is_array($state['result'] ?? null)) {
            $state['result'] += ['side' => $state['result']['winner'], 'starter' => $state['result']['winner']];
        }
    }

    private function side(array $state, int $seat): int
    {
        return (int)$state['sideOf'][$seat];
    }

    private function ends(array $state): ?array
    {
        if (!$state['chain']) {
            return null;
        }
        $last = end($state['chain']);
        return [$state['chain'][0][0], $last[1]];
    }

    /** Count of the open ends for "fives" (a double at an end counts both halves). */
    private function endsCount(array $chain): int
    {
        if (!$chain) {
            return 0;
        }
        if (count($chain) === 1) {
            return $chain[0][0] + $chain[0][1];
        }
        $l = $chain[0];
        $r = $chain[count($chain) - 1];
        return ($l[0] === $l[1] ? $l[0] * 2 : $l[0]) + ($r[0] === $r[1] ? $r[1] * 2 : $r[1]);
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

    private function canDraw(array $state): bool
    {
        return $state['mode'] !== 'block' && !empty($state['boneyard']);
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
        if (count($state['log']) > 14) {
            $state['log'] = array_slice($state['log'], -14);
        }
    }

    public function currentSeat(array $state): ?int
    {
        return $state['phase'] === 'playing' ? (int)$state['turn'] : null;
    }

    public function move(array &$state, int $seat, array $move, bool $isHost): void
    {
        $this->upgrade($state);
        $type = (string)($move['type'] ?? '');

        if ($type === 'next_round') {
            if ($state['phase'] === 'round_over') {
                $this->deal($state, $state['result']['starter'] ?? null);
                return;
            }
            if ($state['phase'] === 'match_over') {
                if (!$isHost) {
                    throw new GameError('host_only');
                }
                foreach ($state['scores'] as $k => $_) {
                    $state['scores'][$k] = 0;
                    $state['wins'][$k] = 0;
                }
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
            $scored = 0;
            if ($state['mode'] === 'fives') {
                $count = $this->endsCount($state['chain']);
                if ($count > 0 && $count % 5 === 0) {
                    $scored = $count;
                    $state['scores'][$this->side($state, $seat)] += $count;
                }
            }
            $state['last'] = ['seat' => $seat, 'tile' => $tile, 'side' => $side, 'scored' => $scored];
            $this->addLog($state, ['t' => 'play', 'seat' => $seat, 'tile' => $tile, 'scored' => $scored]);

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
            if (!$this->canDraw($state)) {
                throw new GameError('boneyard_empty');
            }
            $state['hands'][$seat][] = array_pop($state['boneyard']);
            $this->addLog($state, ['t' => 'draw', 'seat' => $seat]);
            // Still the same player's turn: they play the drawn tile or draw again.
            return;
        }

        if ($type === 'pass') {
            if ($this->canPlay($state, $seat) || $this->canDraw($state)) {
                throw new GameError('cannot_pass');
            }
            $this->doPass($state, $seat);
            if ($state['phase'] === 'playing') {
                $this->advance($state);
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
    private function advance(array &$state): void
    {
        $state['turn'] = self::nextInOrder($state['seats'], (int)$state['turn']);
        $guard = 0;
        while ($state['phase'] === 'playing' && $guard++ < 10) {
            $s = (int)$state['turn'];
            if ($this->canPlay($state, $s) || $this->canDraw($state)) {
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

    /** Nobody can play: the side with the fewest pips wins (a tie means nobody scores). */
    private function endBlocked(array &$state): void
    {
        $sidePips = [];
        foreach ($state['seats'] as $s) {
            $k = $this->side($state, $s);
            $sidePips[$k] = ($sidePips[$k] ?? 0) + self::pips($state['hands'][$s]);
        }
        asort($sidePips);
        $keys = array_keys($sidePips);
        if (count($keys) > 1 && $sidePips[$keys[0]] === $sidePips[$keys[1]]) {
            $this->endRound($state, null, 'blocked');
            return;
        }
        // The player who starts next round: the lowest hand within the winning side.
        $winnerSeat = null;
        $low = PHP_INT_MAX;
        foreach ($state['seats'] as $s) {
            $p = self::pips($state['hands'][$s]);
            if ($this->side($state, $s) === $keys[0] && $p < $low) {
                $low = $p;
                $winnerSeat = $s;
            }
        }
        $this->endRound($state, $winnerSeat, 'blocked');
    }

    private function endRound(array &$state, ?int $winner, string $reason): void
    {
        $pips = [];
        $points = 0;
        $winSide = $winner === null ? null : $this->side($state, $winner);
        foreach ($state['seats'] as $s) {
            $pips[$s] = self::pips($state['hands'][$s]);
            if ($winSide !== null && $this->side($state, $s) !== $winSide) {
                $points += $pips[$s];
            }
        }
        if ($state['mode'] === 'fives') {
            $points = (int)(round($points / 5) * 5);
        }
        if ($winSide !== null) {
            $state['scores'][$winSide] += $points;
            $state['wins'][$winSide]++;
        }
        $starter = $winner ?? self::nextInOrder($state['seats'], (int)$state['turn']);
        $state['result'] = [
            'winner' => $winner, 'side' => $winSide, 'reason' => $reason,
            'points' => $points, 'pips' => $pips, 'starter' => $starter,
        ];
        $this->addLog($state, ['t' => 'end', 'seat' => $winner, 'reason' => $reason, 'points' => $points]);
        $state['phase'] = 'round_over';

        // Match over? (checked at the end of each round, in every mode)
        $board = $state['goal'] === 'rounds' ? $state['wins'] : $state['scores'];
        arsort($board);
        $top = array_key_first($board);
        if ($board[$top] >= $state['target']) {
            $state['phase'] = 'match_over';
            $state['winner'] = $top; // a side id
        }
    }

    public function botMove(array &$state, int $seat): void
    {
        $this->upgrade($state);
        if ($state['phase'] !== 'playing' || (int)$state['turn'] !== $seat) {
            return;
        }
        // Draw until a tile fits (or no more drawing is possible).
        while (!$this->canPlay($state, $seat) && $this->canDraw($state)) {
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
                if ($state['mode'] === 'fives') {
                    $try = $state;
                    $this->place($try, $t, $side);
                    $c = $this->endsCount($try['chain']);
                    if ($c > 0 && $c % 5 === 0) {
                        $score += $c * 6; // scoring now beats almost everything
                    }
                }
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
        $this->upgrade($state);
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
        // Who belongs to which side, for the scoreboard.
        $sides = [];
        foreach ($state['seats'] as $s) {
            $sides[$state['sideOf'][$s]][] = $s;
        }
        $list = [];
        foreach ($sides as $id => $members) {
            $list[] = ['id' => $id, 'seats' => $members];
        }
        return [
            'seats'    => $state['seats'],
            'mode'     => $state['mode'],
            'teams'    => $state['teams'],
            'goal'     => $state['goal'],
            'target'   => $state['target'],
            'sides'    => $list,
            'sideOf'   => (object)$state['sideOf'],
            'scores'   => (object)$state['scores'],
            'wins'     => (object)$state['wins'],
            'round'    => $state['round'],
            'phase'    => $state['phase'],
            'turn'     => $state['turn'],
            'chain'    => $state['chain'],
            'ends'     => $this->endsCount($state['chain']),
            'boneyard' => count($state['boneyard']),
            'counts'   => (object)$counts,
            'hand'     => ($seat !== null && isset($state['hands'][$seat])) ? $state['hands'][$seat] : [],
            'playable' => (object)$playable,
            'canDraw'  => $myTurn && !$playable && $this->canDraw($state),
            'hands'    => $showAll ? $state['hands'] : null,
            'result'   => $state['result'],
            'winner'   => $state['winner'],
            'last'     => $state['last'],
            'log'      => $state['log'],
        ];
    }
}
