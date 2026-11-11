<?php
declare(strict_types=1);

/**
 * Morpion / Tic-Tac-Toe for 2 players (or 1 vs bot), endless rematches with a running score.
 *  - 3x3 grid: line up 3.
 *  - Big grids (10x10 up to 25x25): line up 5, the way morpion is played on squared paper.
 * The board is a string of '.', 'X' and 'O' (row by row) to keep the saved state small.
 */
class TicTacToe extends GameEngine
{
    public const SIZES = [3, 10, 15, 19, 25];

    public function meta(): array
    {
        return ['min' => 2, 'max' => 2, 'bots' => true, 'joinMidGame' => false];
    }

    public function options(array $in): array
    {
        $size = (int)($in['size'] ?? 3);
        return ['size' => in_array($size, self::SIZES, true) ? $size : 3];
    }

    public function init(array $seats, array $options): array
    {
        sort($seats);
        $size = (int)($options['size'] ?? 3);
        return [
            'seats'  => $seats,
            'size'   => $size,
            'need'   => $size === 3 ? 3 : 5,
            'marks'  => [$seats[0] => 'X', $seats[1] => 'O'],
            'board'  => str_repeat('.', $size * $size),
            'turn'   => $seats[0],
            'first'  => $seats[0],
            'winner' => null,
            'line'   => null,
            'lastCell' => null,
            'moves'  => 0,
            'over'   => false,
            'scores' => [$seats[0] => 0, $seats[1] => 0, 'draw' => 0],
        ];
    }

    /** Rooms created before big grids existed stored the board as an array. */
    private function upgrade(array &$state): void
    {
        if (is_array($state['board'])) {
            $b = '';
            foreach ($state['board'] as $v) {
                $b .= $v === null ? '.' : $v;
            }
            $state['board'] = $b;
            $state += ['size' => 3, 'need' => 3, 'lastCell' => null, 'moves' => strlen(str_replace('.', '', $b))];
        }
    }

    public function currentSeat(array $state): ?int
    {
        return $state['over'] ? null : (int)$state['turn'];
    }

    public function move(array &$state, int $seat, array $move, bool $isHost): void
    {
        $this->upgrade($state);
        $type = (string)($move['type'] ?? '');
        if ($type === 'rematch') {
            if (!$state['over']) {
                throw new GameError('invalid_move');
            }
            // Players take turns starting.
            $first = self::nextInOrder($state['seats'], (int)$state['first']);
            $state['board'] = str_repeat('.', $state['size'] * $state['size']);
            $state['first'] = $first;
            $state['turn'] = $first;
            $state['winner'] = null;
            $state['line'] = null;
            $state['lastCell'] = null;
            $state['moves'] = 0;
            $state['over'] = false;
            return;
        }
        if ($type !== 'place') {
            throw new GameError('invalid_move');
        }
        if ($state['over']) {
            throw new GameError('round_over');
        }
        if ($seat !== (int)$state['turn']) {
            throw new GameError('not_your_turn');
        }
        $n = $state['size'] * $state['size'];
        $i = (int)($move['cell'] ?? -1);
        if ($i < 0 || $i >= $n || $state['board'][$i] !== '.') {
            throw new GameError('invalid_move');
        }
        $mark = $state['marks'][$seat];
        $state['board'][$i] = $mark;
        $state['lastCell'] = $i;
        $state['moves']++;

        $line = self::lineThrough($state['board'], $state['size'], $state['need'], $i);
        if ($line !== null) {
            $state['over'] = true;
            $state['winner'] = $seat;
            $state['line'] = $line;
            $state['scores'][$seat]++;
        } elseif ($state['moves'] >= $n) {
            $state['over'] = true;
            $state['scores']['draw']++;
        } else {
            $state['turn'] = self::nextInOrder($state['seats'], $seat);
        }
    }

    private const DIRS = [[0, 1], [1, 0], [1, 1], [1, -1]];

    /** The winning cells through cell $i, or null. Only looks around the last move. */
    private static function lineThrough(string $b, int $size, int $need, int $i): ?array
    {
        $mark = $b[$i];
        $r0 = intdiv($i, $size);
        $c0 = $i % $size;
        foreach (self::DIRS as [$dr, $dc]) {
            $cells = [$i];
            foreach ([1, -1] as $sign) {
                $r = $r0 + $dr * $sign;
                $c = $c0 + $dc * $sign;
                while ($r >= 0 && $r < $size && $c >= 0 && $c < $size && $b[$r * $size + $c] === $mark) {
                    $cells[] = $r * $size + $c;
                    $r += $dr * $sign;
                    $c += $dc * $sign;
                }
            }
            if (count($cells) >= $need) {
                sort($cells);
                return $cells;
            }
        }
        return null;
    }

    public function botMove(array &$state, int $seat): void
    {
        $this->upgrade($state);
        $me = $state['marks'][$seat];
        $them = $me === 'X' ? 'O' : 'X';
        $pick = $state['size'] === 3 ? $this->pickSmall($state, $me, $them) : $this->pickBig($state, $me, $them);
        $this->move($state, $seat, ['type' => 'place', 'cell' => $pick], false);
    }

    private function pickSmall(array $state, string $me, string $them): int
    {
        $b = $state['board'];
        $free = [];
        for ($i = 0; $i < 9; $i++) {
            if ($b[$i] === '.') {
                $free[] = $i;
            }
        }
        // 1. win, 2. block
        foreach ([$me, $them] as $who) {
            foreach ($free as $i) {
                $try = $b;
                $try[$i] = $who;
                if (self::lineThrough($try, 3, 3, $i) !== null) {
                    return $i;
                }
            }
        }
        // 3. a little randomness keeps the bot beatable, then center, corners, anything
        if (random_int(1, 100) <= 20) {
            return $free[array_rand($free)];
        }
        foreach ([4, 0, 2, 6, 8, 1, 3, 5, 7] as $i) {
            if ($b[$i] === '.') {
                return $i;
            }
        }
        return $free[0];
    }

    /** Big grid: score every empty cell near the stones for attack and defence, take the best. */
    private function pickBig(array $state, string $me, string $them): int
    {
        $b = $state['board'];
        $size = $state['size'];
        if ($state['moves'] === 0) {
            $mid = intdiv($size, 2);
            return $mid * $size + $mid;
        }
        $best = -1;
        $bestCells = [];
        for ($i = 0, $n = $size * $size; $i < $n; $i++) {
            if ($b[$i] !== '.' || !self::hasNeighbour($b, $size, $i)) {
                continue;
            }
            $score = self::cellValue($b, $size, $i, $me) * 11 + self::cellValue($b, $size, $i, $them) * 10;
            if ($score > $best) {
                $best = $score;
                $bestCells = [$i];
            } elseif ($score === $best) {
                $bestCells[] = $i;
            }
        }
        return $bestCells[array_rand($bestCells)];
    }

    private static function hasNeighbour(string $b, int $size, int $i): bool
    {
        $r0 = intdiv($i, $size);
        $c0 = $i % $size;
        for ($r = max(0, $r0 - 2); $r <= min($size - 1, $r0 + 2); $r++) {
            for ($c = max(0, $c0 - 2); $c <= min($size - 1, $c0 + 2); $c++) {
                if ($b[$r * $size + $c] !== '.') {
                    return true;
                }
            }
        }
        return false;
    }

    /** How good it would be for $mark to play at $i: the lines it would make in the 4 directions. */
    private static function cellValue(string $b, int $size, int $i, string $mark): int
    {
        $r0 = intdiv($i, $size);
        $c0 = $i % $size;
        $total = 0;
        foreach (self::DIRS as [$dr, $dc]) {
            $count = 1;
            $open = 0;
            foreach ([1, -1] as $sign) {
                $r = $r0 + $dr * $sign;
                $c = $c0 + $dc * $sign;
                while ($r >= 0 && $r < $size && $c >= 0 && $c < $size && $b[$r * $size + $c] === $mark) {
                    $count++;
                    $r += $dr * $sign;
                    $c += $dc * $sign;
                }
                if ($r >= 0 && $r < $size && $c >= 0 && $c < $size && $b[$r * $size + $c] === '.') {
                    $open++;
                }
            }
            if ($count >= 5) {
                $total += 1000000;
            } elseif ($count === 4) {
                $total += $open === 2 ? 100000 : ($open === 1 ? 10000 : 0);
            } elseif ($count === 3) {
                $total += $open === 2 ? 5000 : ($open === 1 ? 300 : 0);
            } elseif ($count === 2) {
                $total += $open === 2 ? 200 : ($open === 1 ? 30 : 0);
            } else {
                $total += $open;
            }
        }
        return $total;
    }

    public function view(array $state, ?int $seat): array
    {
        $this->upgrade($state);
        $state['marks'] = (object)$state['marks'];
        $state['scores'] = (object)$state['scores'];
        return $state;
    }
}
