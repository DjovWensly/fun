<?php
declare(strict_types=1);

/**
 * Tu préfères… ? / Would you rather…?
 * Each card holds two choices written "choice A || choice B". Everyone picks one in secret,
 * then the reveal shows who chose what and how often the table agrees.
 */
class WouldRather extends PollGame
{
    protected function cardType(): string
    {
        return 'wyr';
    }

    protected function makeCard(string $text): array
    {
        $parts = array_map('trim', explode('||', $text, 2));
        if (count($parts) < 2 || $parts[1] === '') {
            // Written without "||": try " ou " / " or ", else keep it as a yes/no question.
            $parts = preg_split('/\s+(ou|or)\s+/iu', $text, 2) ?: [$text];
            if (count($parts) < 2) {
                $parts = [$text, ''];
            }
        }
        return ['a' => $parts[0], 'b' => $parts[1] !== '' ? $parts[1] : null];
    }

    protected function cleanVote(array $state, $value)
    {
        return in_array($value, ['a', 'b'], true) ? $value : null;
    }
}
