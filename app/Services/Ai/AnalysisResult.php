<?php

declare(strict_types=1);

namespace App\Services\Ai;

/**
 * Structured reading of one instruction. Risk is intentionally absent:
 * the operation registry is the only authority for risk.
 */
final readonly class AnalysisResult
{
    /**
     * @param  array<string, mixed>  $parameters
     * @param  list<string>  $missingFields
     * @param  list<string>  $ambiguities
     * @param  list<string>  $candidateOperations
     */
    public function __construct(
        public string $intent,
        public array $parameters,
        public array $missingFields,
        public array $ambiguities,
        public array $candidateOperations,
        public bool $requiresValidation,
        public bool $supported,
        public bool $extractionConsistent,
        public bool $cancelDraft,
        public bool $promptInjectionDetected,
        public bool $isQuestion,
        public string $analyzer = 'deterministic',
    ) {}

    /**
     * @param  array<string, mixed>  $parameters
     * @param  list<string>  $missingFields
     * @param  list<string>  $ambiguities
     */
    public function withFields(array $parameters, array $missingFields, array $ambiguities): self
    {
        return new self(
            $this->intent,
            $parameters,
            $missingFields,
            $ambiguities,
            $this->candidateOperations,
            $this->requiresValidation,
            $this->supported,
            $this->extractionConsistent && $ambiguities === [],
            $this->cancelDraft,
            $this->promptInjectionDetected,
            $this->isQuestion,
            $this->analyzer,
        );
    }
}
