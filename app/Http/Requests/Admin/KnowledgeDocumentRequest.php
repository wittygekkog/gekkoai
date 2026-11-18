<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Title plus text, a file, or both. The SBU comes from the workspace selection, not this form.
 */
class KnowledgeDocumentRequest extends FormRequest
{
    /** @var array<string, list<string>> */
    private const FILES = [
        'txt' => ['text/plain'],
        'md' => ['text/plain', 'text/markdown'],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/vnd.ms-excel'],
        'json' => ['application/json', 'text/plain', 'text/json'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    ];

    public function authorize(): bool
    {
        return $this->user('admin') !== null;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'content' => ['nullable', 'string', 'max:50000'],
            'file' => ['nullable', 'file', 'max:10240'],
            'document_id' => ['nullable', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Add a title for this document.',
            'content.max' => 'The text must be 50,000 characters or fewer.',
            'file.max' => 'The file must be 10 MB or smaller.',
            'file.file' => 'Choose a valid file.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->isMethod('POST') && ! $this->filled('content') && ! $this->hasFile('file')) {
                $validator->errors()->add('content', 'Add the text or upload a file.');
            }

            $file = $this->file('file');
            if ($file === null) {
                return;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $mime = (string) $file->getMimeType();
            $allowed = self::FILES[$extension] ?? null;
            if ($allowed === null || ! in_array($mime, $allowed, true)) {
                $validator->errors()->add('file', 'Upload a TXT, MD, CSV, JSON, DOC, DOCX, or PDF file.');
            }
        });
    }
}
