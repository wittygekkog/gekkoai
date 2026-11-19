<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiAttachment;
use App\Models\AiConversation;
use App\Models\AiTranscription;
use Illuminate\Http\UploadedFile;

/**
 * Stores a voice recording and asks the configured speech model for the words.
 * A missing key or a failed call leaves the transcript empty. Nothing is invented.
 */
final class AudioIntakeService
{
    public function __construct(private readonly SpeechTranscriber $speech) {}

    public function store(AiConversation $conversation, UploadedFile $file, ?string $language): AiTranscription
    {
        $path = $file->store('audio/'.$conversation->tenant_id);
        $attachment = new AiAttachment();
        $attachment->tenant_id = $conversation->tenant_id;
        $attachment->file_path = $path;
        $attachment->mime_type = (string) $file->getMimeType();
        $attachment->file_size = (int) $file->getSize();
        $attachment->retention_until = now()->addDays((int) config('gekko.audio_retention_days'));
        $attachment->save();

        $result = $this->speech->transcribe($file, $language);

        $transcription = new AiTranscription();
        $transcription->tenant_id = $conversation->tenant_id;
        $transcription->attachment_id = $attachment->id;
        $transcription->language = $language;
        $transcription->transcript = $result['text'];
        $transcription->model_name = $result['model'];
        $transcription->transcription_metadata = [
            'status' => $result['status'],
            'provider' => $result['provider'],
            'provider_configured' => $result['status'] !== 'provider_unconfigured',
            'supported_languages' => ['en', 'tl'],
            'detail' => $result['detail'],
        ];
        $transcription->save();

        return $transcription;
    }
}
