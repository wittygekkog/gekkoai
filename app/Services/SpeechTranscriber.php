<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/**
 * Sends a recording to the configured speech model.
 * An empty or failed response never becomes invented words.
 */
final class SpeechTranscriber
{
    public function configured(): bool
    {
        return $this->provider() !== null;
    }

    /**
     * @return array{text: ?string, model: string, provider: string, status: string, detail: string}
     */
    public function transcribe(UploadedFile $file, ?string $language): array
    {
        $provider = $this->provider();
        if ($provider === null) {
            return [
                'text' => null,
                'model' => 'unconfigured',
                'provider' => 'none',
                'status' => 'provider_unconfigured',
                'detail' => 'No speech model is configured. Type the confirmed transcript to continue.',
            ];
        }

        try {
            $text = $provider === 'openai'
                ? $this->openai($file, $language)
                : $this->huggingface($file);
        } catch (ConnectionException) {
            return $this->failed($provider, 'The speech service could not be reached. Type the words to continue.');
        }

        $text = is_string($text) ? trim($text) : '';
        if ($text === '') {
            return $this->failed($provider, 'The speech service did not return words. Type the transcript to continue.');
        }

        return [
            'text' => mb_substr($text, 0, 4000),
            'model' => $provider === 'openai'
                ? (string) config('services.openai.speech_model')
                : (string) config('services.huggingface.speech_model'),
            'provider' => $provider,
            'status' => 'transcribed',
            'detail' => 'Review the transcript, then send it. A recording is not an instruction until you confirm the words.',
        ];
    }

    private function provider(): ?string
    {
        $provider = strtolower(trim((string) config('gekko.speech_provider')));
        if ($provider === 'openai' && filled(config('services.openai.key'))) {
            return 'openai';
        }
        if ($provider === 'huggingface' && filled(config('services.huggingface.token'))) {
            return 'huggingface';
        }

        return null;
    }

    private function openai(UploadedFile $file, ?string $language): ?string
    {
        $payload = ['model' => (string) config('services.openai.speech_model')];
        if (in_array($language, ['en', 'tl'], true)) {
            $payload['language'] = $language;
        }

        $response = Http::withToken((string) config('services.openai.key'))
            ->withOptions(['verify' => $this->sslVerify()])
            ->timeout(60)
            ->attach('file', (string) file_get_contents($file->getRealPath()), $this->filename($file))
            ->post(rtrim((string) config('services.openai.base_url'), '/').'/audio/transcriptions', $payload);

        if (! $response->successful()) {
            return null;
        }

        $text = $response->json('text');

        return is_string($text) ? $text : null;
    }

    private function huggingface(UploadedFile $file): ?string
    {
        $model = trim((string) config('services.huggingface.speech_model'), '/');
        $url = rtrim((string) config('services.huggingface.inference_url'), '/').'/'.$model;
        $mime = $this->mime($file);

        $response = Http::withToken((string) config('services.huggingface.token'))
            ->timeout(60)
            ->withBody((string) file_get_contents($file->getRealPath()), $mime)
            ->post($url);

        if (! $response->successful()) {
            return null;
        }

        $text = $response->json('text');

        return is_string($text) ? $text : null;
    }

    /**
     * OPENAI_SSL=true checks the certificate. Use the CA bundle when Windows PHP has an empty curl.cainfo.
     */
    private function sslVerify(): bool|string
    {
        if (! (bool) config('services.openai.verify_ssl')) {
            return false;
        }

        $bundle = trim((string) config('services.openai.ca_bundle'));

        return ($bundle !== '' && is_file($bundle)) ? $bundle : true;
    }

    private function filename(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['webm', 'wav', 'mp3', 'mpeg', 'ogg', 'm4a', 'mp4'], true)) {
            $extension = 'webm';
        }

        return 'speech.'.$extension;
    }

    private function mime(UploadedFile $file): string
    {
        $mime = strtolower(trim(strtok((string) $file->getMimeType(), ';') ?: ''));

        return str_starts_with($mime, 'audio/') || $mime === 'video/webm' ? $mime : 'application/octet-stream';
    }

    /**
     * @return array{text: null, model: string, provider: string, status: string, detail: string}
     */
    private function failed(string $provider, string $detail): array
    {
        return [
            'text' => null,
            'model' => $provider === 'openai'
                ? (string) config('services.openai.speech_model')
                : (string) config('services.huggingface.speech_model'),
            'provider' => $provider,
            'status' => 'provider_error',
            'detail' => $detail,
        ];
    }
}
