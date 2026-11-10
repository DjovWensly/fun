<?php
declare(strict_types=1);

/** Tic-Tac-Toe / Morpion: 2 players (or 1 vs bot), endless rematches with a running score. */
class TicTacToe extends GameEngine
{
    private const LINES = [[0, 1, 2], [3, 4, 5], [6, 7, 8], [0, 3, 6], [1, 4, 7], [2, 5, 8], [0, 4, 8], [2, 4, 6]];

    public function meta(): array
    {
        return ['min' => 2, 'max' => 2, 'bots' => true, 'joinMidGame' => false];
    }

    public function init(array $seats, array $options): array
    {
        sort($seats);
        return [
            'seats'  => $seats,
            'marks'  => [$seats[0] => 'X', $seats[1] => 'O'],
            'board'  => array_fill(0, 9, null),
            'turn'   => $seats[0],
            'first'  => $seats[0],
            'winner' => null,
            'line'   => null,
            'over'   => false,
            'scores' => [$seats[0] => 0, $seats[1] => 0, 'draw' => 0],
        ];
    }

    public function currentSeat(array $state): ?int
    {
        return $state['over'] ? null : (int)$state['turn'];
    }

    public function move(array &$state, int $seat, array $move, bool $isHost): void
    {
        $type = (string)($move['type'] ?? '');
        if ($type === 'rematch') {
            if (!$state['over']) {
                throw new GameError('invalid_move');
            }
            // Loser of the previous game (or the other player after a draw) starts.
            $first = self::nextInOrder($state['seats'], (int)$state['first']);
            $state['board'] = array_fill(0, 9, null);
            $state['first'] = $first;
            $state['turn'] = $first;
            $state['winner'] = null;
            $state['line'] = null;
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
        $i = (int)($move['cell'] ?? -1);
        if ($i < 0 || $i > 8 || $state['board'][$i] !== null) {
            throw new GameError('invalid_move');
        }
        $state['board'][$i] = $state['marks'][$seat];

        $line = self::winningLine($state['board']);
        if ($line !== null) {
            $state['over'] = true;
            $state['winner'] = $seat;
            $state['line'] = $line;
            $state['scores'][$seat]++;
        } elseif (!in_array(null, $state['board'], true)) {
            $state['over'] = true;
            $state['scores']['draw']++;
        } else {
            $state['turn'] = self::nextInOrder($state['seats'], $seat);
        }
    }

    private static function winningLine(array $b): ?array
    {
        foreach (self::LINES as $l) {
            if ($b[$l[0]] !== null && $b[$l[0]] === $b[$l[1]] && $b[$l[1]] === $b[$l[2]]) {
                return $l;
            }
        }
        return null;
    }

    public function botMove(array &$state, int $seat): void
    {
        $me = $state['marks'][$seat];
        $them = $me === 'X' ? 'O' : 'X';
        $b = $state['board'];
        $free = array_keys(array_filter($b, function ($v) {
            return $v === null;
        }));
        $pick = null;
        // 1. win, 2. block
        foreach ([$me, $them] as $who) {
            foreach ($free as $i) {
                $try = $b;
                $try[$i] = $who;
                if (self::winningLine($try) !== null) {
                    $pick = $i;
                    break 2;
                }
            }
        }
        // 3. a little randomness keeps the bot beatable, then center, corners, anything
        if ($pick === null && random_int(1, 100) <= 20) {
            $pick = $free[array_rand($free)];
        }
        if ($pick === null) {
            foreach ([4, 0, 2, 6, 8, 1, 3, 5, 7] as $i) {
                if ($b[$i] === null) {
                    $pick = $i;
                    break;
                }
            }
        }
        $this->move($state, $seat, ['type' => 'place', 'cell' => $pick], false);
    }

    public function view(array $state, ?int $seat): array
    {
        return $state;
    }
}
