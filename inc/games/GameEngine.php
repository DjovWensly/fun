<?php
declare(strict_types=1);

/**
 * Base class for a server-authoritative game.
 * The whole game state is a plain array stored as JSON in gp_rooms.state.
 * "Seats" are small integers (0,1,2...) identifying players inside a room.
 */
abstract class GameEngine
{
    /** min/max players, whether bots can fill seats, whether people can join after start. */
    abstract public function meta(): array;

    /** Allowed/normalised options chosen at room creation. */
    public function options(array $in): array
    {
        return [];
    }

    /** Build the initial state for these seats. */
    abstract public function init(array $seats, array $options): array;

    /** Apply a move from $seat, or throw GameError. */
    abstract public function move(array &$state, int $seat, array $move, bool $isHost): void;

    /** What $seat is allowed to see (hide other players' hands etc.). $seat is null for spectators. */
    abstract public function view(array $state, ?int $seat): array;

    /** Seat whose turn it is, or null when nobody needs to act. Used to drive bots. */
    public function currentSeat(array $state): ?int
    {
        return null;
    }

    /** Make an automatic move for a bot seat. */
    public function botMove(array &$state, int $seat): void
    {
    }

    /**
     * Games with a clock (Dessine et devine) move on by themselves when time runs out.
     * tickDue() is a cheap check run on every poll; tick() then runs with the room locked
     * and returns true if it changed the state.
     */
    public function tickDue(array $state): bool
    {
        return false;
    }

    public function tick(array &$state): bool
    {
        return false;
    }

    /** A player joined while the game is running (only if meta()['joinMidGame']). */
    public function addSeat(array &$state, int $seat): void
    {
    }

    /** A player left while the game is running (only if meta()['joinMidGame']). */
    public function removeSeat(array &$state, int $seat): void
    {
    }

    protected static function nextInOrder(array $order, int $seat): int
    {
        $i = array_search($seat, $order, true);
        return $order[($i === false ? 0 : $i + 1) % count($order)];
    }
}
