<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Turns a typed or spoken note into one SBU objective.
 * The wording is tightened locally. No outside model is called, and the result cannot override Logic.
 */
final class ObjectiveChatService
{
    /**
     * @return array{reply: string, title: string, body: string}
     */
    public function draft(string $message, ?string $currentTitle = null, ?string $currentBody = null): array
    {
        $text = $this->collapse($message);
        $editing = is_string($currentBody) && trim($currentBody) !== '';
        $title = is_string($currentTitle) ? trim($currentTitle) : '';

        if (preg_match('/^(?:rename(?: it)? to|call it|title(?: is)?[:\s]+)\s*(.+)$/iu', $text, $match) === 1) {
            $body = $editing ? trim((string) $currentBody) : $this->polish($match[1])['text'];

            return $this->pack($this->title($match[1]), $body, 'Updated the title. Save it, or tell me what else to change.');
        }

        if ($this->isImproveOnly($text)) {
            if (! $editing) {
                return $this->pack('', '', 'Tell me the outcome in one sentence. I will tighten it.');
            }
            $polished = $this->polish((string) $currentBody);

            return $this->pack($title !== '' ? $title : $this->title($polished['text']), $polished['text'], $this->reply(true, $polished['blocked']));
        }

        $source = preg_replace('/^(?:please\s+)?(?:add|create|make|write|set)\s+(?:an\s+)?objective\s+(?:that\s+|to\s+|:)?/iu', '', $text);
        $polished = $this->polish(is_string($source) ? $source : $text);
        if ($polished['text'] === '') {
            return $this->pack($title, $editing ? trim((string) $currentBody) : '', 'Tell me the outcome you want for this SBU.');
        }

        $nextTitle = $editing && $title !== '' ? $title : $this->title($polished['text']);

        return $this->pack($nextTitle, $polished['text'], $this->reply($editing, $polished['blocked']));
    }

    private function isImproveOnly(string $text): bool
    {
        return preg_match('/^(?:please\s+)?(?:improve|rewrite|clarify|tighten|shorten|make (?:this|it) (?:clearer|better|shorter|stronger))(?:\s+(?:this|it|the objective))?[.!\s]*$/iu', $text) === 1;
    }

    /**
     * @return array{text: string, blocked: bool}
     */
    private function polish(string $text): array
    {
        $text = $this->collapse($text);
        $text = (string) preg_replace('/^(?:please\s+)?(?:the objective is(?: to)?|our (?:goal|objective) is(?: to)?|we (?:really\s+)?(?:just\s+)?want(?: to)?|i want(?: to)?|we should|let\'?s)\s+/iu', '', $text);
        $blocked = preg_match('/\b(?:skip|ignore|bypass)\b.{0,48}\b(?:rules?|logic|approval)\b|\bwithout approval\b/iu', $text) === 1;
        if ($blocked) {
            $text = (string) preg_replace('/(?:\s*(?:,|and|or)\s+)?\b(?:skip|ignore|bypass)\b.{0,48}\b(?:rules?|logic|approval)\b/iu', '', $text);
            $text = (string) preg_replace('/\bwithout approval\b/iu', '', $text);
            $text = (string) preg_replace('/\s+\b(?:and|or)\b\s*$/iu', '', $text);
        }
        $text = (string) preg_replace('/\b(?:really|very|just|basically|actually|simply)\b/iu', '', $text);
        $text = trim($this->collapse($text), " \t,;:-");
        if ($text === '') {
            return ['text' => '', 'blocked' => $blocked];
        }

        $text = mb_strtoupper(mb_substr($text, 0, 1)).mb_substr($text, 1);
        $text = rtrim($text, '.').'.';

        return ['text' => mb_substr($text, 0, 5000), 'blocked' => $blocked];
    }

    private function title(string $text): string
    {
        $text = rtrim($this->collapse($text), '.');
        if (mb_strlen($text) <= 60) {
            return $text;
        }
        $cut = mb_substr($text, 0, 60);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space === false ? $cut : mb_substr($cut, 0, $space), '.,;:');
    }

    private function reply(bool $editing, bool $blocked): string
    {
        if ($blocked) {
            return 'I kept this to one outcome and removed the part that would skip Rules or approval.';
        }

        return $editing
            ? 'Updated objective. It stays one outcome and does not override Rules. Save it, or tell me what to change.'
            : 'Draft ready. I wrote it as one outcome. Save it, or tell me what to change.';
    }

    /**
     * @return array{reply: string, title: string, body: string}
     */
    private function pack(string $title, string $body, string $reply): array
    {
        return [
            'reply' => $reply,
            'title' => mb_substr($title, 0, 180),
            'body' => mb_substr($body, 0, 5000),
        ];
    }

    private function collapse(string $text): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($text));

        return is_string($collapsed) ? trim($collapsed) : trim($text);
    }
}
