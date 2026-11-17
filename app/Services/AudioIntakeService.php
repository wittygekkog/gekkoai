<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiAttachment;
use App\Models\AiConversation;
use App\Models\AiTranscription;
use Illuminate\Http\UploadedFile;

/**
 * Stores a voice recording and a transcription row.
 * The platform does not invent a transcript when no speech model is configured.
 */
final class AudioIntakeService
{
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

        $transcription = new AiTranscription();
        $transcription->tenant_id = $conversation->tenant_id;
        $transcription->attachment_id = $attachment->id;
        $transcription->language = $language;
        $transcription->model_name = 'unconfigured';
        $transcription->transcription_metadata = [
            'status' => 'provider_unconfigured',
            'provider_configured' => false,
            'supported_languages' => ['en', 'tl'],
            'detail' => 'No speech model is configured. Type the confirmed transcript to continue.',
        ];
        $transcription->save();

        return $transcription;
    }
}
