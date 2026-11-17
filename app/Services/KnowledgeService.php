<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;

/**
 * Tenant-filtered lookup. Retrieved text is untrusted and cannot grant tools.
 */
final class KnowledgeService
{
    /**
     * @return list<string>
     */
    public function snippets(string $tenantId, string $query): array
    {
        $needles = $this->words($query);
        if ($needles === []) {
            return [];
        }

        return AiKnowledgeChunk::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('document_id', AiKnowledgeDocument::query()
                ->where('tenant_id', $tenantId)
                ->where('approval_status', 'approved')
                ->select('id'))
            ->orderBy('chunk_index')
            ->limit(30)
            ->get()
            ->filter(function (AiKnowledgeChunk $chunk) use ($needles): bool {
                $haystack = $this->words((string) $chunk->content);
                return array_intersect($needles, $haystack) !== [];
            })
            ->take(3)
            ->map(static fn (AiKnowledgeChunk $chunk): string => (string) $chunk->content)
            ->values()
            ->all();
    }

    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $vector = array_fill(0, 16, 0.0);
        foreach ($this->words($text) as $word) {
            $vector[hexdec(substr(hash('sha256', $word), 0, 2)) % 16] += 1.0;
        }

        return $vector;
    }

    /**
     * @return list<string>
     */
    private function words(string $text): array
    {
        preg_match_all('/[a-z0-9]{5,}/i', mb_strtolower($text), $matches);

        return array_values(array_unique($matches[0] ?? []));
    }
}
