<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiKnowledgeChunk;
use App\Models\AiKnowledgeDocument;
use App\Models\Tenant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Tenant-filtered lookup and document storage.
 * Retrieved text is untrusted and cannot grant tools.
 * Uploaded files stay on the private disk.
 */
final class KnowledgeService
{
    /**
     * Save a new document for one SBU. Text can come from the form or a text file.
     */
    public function store(Tenant $tenant, string $title, ?string $content, ?UploadedFile $file): AiKnowledgeDocument
    {
        $path = null;
        $extracted = $file instanceof UploadedFile ? $this->extract($file) : '';
        $text = $this->text($content, $extracted);
        if ($text === '') {
            throw ValidationException::withMessages([
                'content' => 'Add the text, or upload a TXT, MD, CSV, JSON, or DOCX file that contains text.',
            ]);
        }

        if ($file instanceof UploadedFile) {
            $path = $this->storeFile($tenant, $file);
        }

        $document = new AiKnowledgeDocument();
        $document->tenant_id = $tenant->id;
        $document->title = $title;
        $document->source_type = $path === null ? 'text' : 'file';
        $document->source_reference = $path;
        $document->content = $text;
        $document->approval_status = 'pending';
        $document->version = 1;
        $document->save();
        $this->replaceChunks($document);

        return $document;
    }

    /**
     * Update title, text, or the attached file. The document stays on its SBU.
     * Changed text goes back to pending so retrieval does not use it early.
     */
    public function update(AiKnowledgeDocument $document, string $title, ?string $content, ?UploadedFile $file): AiKnowledgeDocument
    {
        $extracted = $file instanceof UploadedFile ? $this->extract($file) : '';
        $text = trim((string) $content);
        if ($text === '' && $extracted !== '') {
            $text = $extracted;
        }
        if ($text === '') {
            $text = (string) $document->content;
        }
        if (mb_strlen($text) > 50000) {
            throw ValidationException::withMessages([
                'content' => 'The text must be 50,000 characters or fewer.',
            ]);
        }

        $previousPath = $document->source_reference;
        $previousText = (string) $document->content;
        $path = $previousPath;
        if ($file instanceof UploadedFile) {
            $path = $this->storeFile($document->tenant, $file);
        }

        $textChanged = $text !== $previousText;
        $fileChanged = $path !== $previousPath;
        $document->title = $title;
        $document->content = $text;
        $document->source_reference = $path;
        $document->source_type = $path === null || $path === '' ? 'text' : 'file';
        $document->version = (int) $document->version + 1;
        $document->approval_status = 'pending';
        $document->save();

        if ($fileChanged && is_string($previousPath) && $previousPath !== '') {
            Storage::disk('local')->delete($previousPath);
        }
        if ($textChanged) {
            $this->replaceChunks($document);
        }

        return $document;
    }

    public function delete(AiKnowledgeDocument $document): void
    {
        $path = $document->source_reference;
        $document->delete();
        if (is_string($path) && $path !== '') {
            Storage::disk('local')->delete($path);
        }
    }

    /**
     * Private path for a stored file. Rejects anything outside the knowledge folder.
     */
    public function storedPath(AiKnowledgeDocument $document): string
    {
        $path = (string) $document->source_reference;
        if ($path === '' || str_contains($path, '..') || ! str_starts_with($path, 'knowledge/')) {
            abort(404);
        }
        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return $path;
    }
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

    private function text(?string $typed, string $extracted): string
    {
        $typed = trim((string) $typed);
        $value = $typed !== '' ? $typed : trim($extracted);
        if (mb_strlen($value) > 50000) {
            throw ValidationException::withMessages([
                'content' => 'The text must be 50,000 characters or fewer.',
            ]);
        }

        return $value;
    }

    /**
     * Read text we can use for retrieval. PDF and legacy DOC stay as files unless the form has text.
     */
    private function extract(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $raw = (string) file_get_contents($file->getRealPath());
        if (in_array($extension, ['txt', 'md', 'csv', 'json'], true)) {
            return $this->plain($raw);
        }
        if ($extension === 'docx') {
            return $this->docx($file->getRealPath());
        }

        return '';
    }

    private function docx(string $path): string
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages([
                'file' => 'That Word file could not be read. Paste the text instead.',
            ]);
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (! is_string($xml) || $xml === '') {
            throw ValidationException::withMessages([
                'file' => 'That Word file has no document text. Paste the text instead.',
            ]);
        }
        $xml = str_replace(['</w:p>', '</w:tr>'], "\n", $xml);

        return $this->plain(strip_tags($xml));
    }

    private function plain(string $raw): string
    {
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string) mb_convert_encoding($raw, 'UTF-8', 'ISO-8859-1');
        }

        return trim(str_replace(["\r\n", "\r"], "\n", $raw));
    }

    private function storeFile(Tenant $tenant, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $name = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs('knowledge/'.$tenant->id, $name, 'local');
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'file' => 'The file could not be stored. Try again.',
            ]);
        }

        return $path;
    }

    private function replaceChunks(AiKnowledgeDocument $document): void
    {
        $document->chunks()->delete();
        $parts = mb_str_split((string) $document->content, 800);
        foreach ($parts as $index => $part) {
            $chunk = new AiKnowledgeChunk();
            $chunk->tenant_id = $document->tenant_id;
            $chunk->document_id = $document->id;
            $chunk->chunk_index = $index;
            $chunk->content = $part;
            $chunk->embedding = $this->embed($part);
            $chunk->embedding_reference = 'hashing-v1';
            $chunk->created_at = now();
            $chunk->save();
        }
    }
}
