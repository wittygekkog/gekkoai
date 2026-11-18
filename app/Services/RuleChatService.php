<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Turns a typed or spoken instruction into a rule title and statement.
 * This drafter does not call an outside model and does not grant a tool.
 */
final class RuleChatService
{
    /**
     * @return array{reply: string, title: string, body: string}
     */
    public function draft(string $message, ?string $currentTitle = null, ?string $currentBody = null): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $message));
        $editing = is_string($currentTitle) && $currentTitle !== '';

        if (preg_match('/^(?:rename(?: it)? to|call it|title(?: is)?[:\s]+)\s*(.+)$/iu', $text, $match) === 1) {
            $title = $this->title($match[1]);
            $body = is_string($currentBody) && $currentBody !== '' ? $currentBody : $this->statement($match[1]);

            return $this->pack($title, $body, true);
        }

        if ($editing && preg_match('/^(?:change|update|replace) (?:the )?(?:statement|rule|text) (?:to|with)\s+(.+)$/iu', $text, $match) === 1) {
            return $this->pack((string) $currentTitle, $this->statement($match[1]), true);
        }

        $cleaned = preg_replace('/^(?:please\s+)?(?:add|create|make|write)\s+(?:a\s+)?rule\s+(?:that\s+|to\s+|:)?/iu', '', $text);
        $cleaned = trim(is_string($cleaned) ? $cleaned : $text, " \t.");
        if ($cleaned === '') {
            $cleaned = $text;
        }

        if ($editing) {
            return $this->pack((string) $currentTitle, $this->statement($cleaned), true);
        }

        return $this->pack($this->title($cleaned), $this->statement($cleaned), false);
    }

    private function title(string $text): string
    {
        $text = rtrim($text, '.');
        if (mb_strlen($text) <= 80) {
            return $text;
        }

        $cut = mb_substr($text, 0, 80);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space === false ? $cut : mb_substr($cut, 0, $space), '.,;:');
    }

    private function statement(string $text): string
    {
        $text = rtrim($text, ". \t");

        return $text === '' ? '' : $text.'.';
    }

    /**
     * @return array{reply: string, title: string, body: string}
     */
    private function pack(string $title, string $body, bool $editing): array
    {
        return [
            'reply' => $editing
                ? 'Updated draft. Save it, or tell me what else to change.'
                : 'Draft ready. Save it, or tell me what to change.',
            'title' => mb_substr($title, 0, 180),
            'body' => mb_substr($body, 0, 5000),
        ];
    }
}
