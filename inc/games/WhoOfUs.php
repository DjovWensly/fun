<?php
declare(strict_types=1);

/**
 * Qui de nous deux ? / Which of us?
 * A question ("…cuisine le mieux ?") appears; everyone secretly picks a player (themselves included).
 * Two players: you score "complicité" each time you pick the same person.
 * A group: the player picked by the most people gets a point.
 */
class WhoOfUs extends PollGame
{
    protected function cardType(): string
    {
        return 'who';
    }

    protected function makeCard(string $text): array
    {
        return ['text' => $text];
    }

    protected function cleanVote(array $state, $value)
    {
        $v = is_numeric($value) ? (int)$value : -1;
        return in_array($v, $state['order'], true) ? $v : null;
    }

    protected function afterReveal(array &$state): void
    {
        $counts = array_count_values(array_map('intval', $state['votes']));
        arsort($counts);
        $top = array_key_first($counts);
        $values = array_values($counts);
        // Only a clear winner gets the point.
        if ($top !== null && (count($values) === 1 || $values[0] > $values[1])) {
            $state['tally'][$top] = ($state['tally'][$top] ?? 0) + 1;
        }
    }
}
