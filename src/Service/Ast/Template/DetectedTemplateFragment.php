<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Ast\Template;

readonly class DetectedTemplateFragment
{
    public function __construct(
        public string $kind,
        public string $status,
        public int $startOffset,
        public int $endOffset,
        public string $raw,
        public ?string $role = null,
        public ?string $openDelimiter = null,
        public ?string $closeDelimiter = null,
        public ?string $inner = null,
        public ?string $normalizedRaw = null,
        public ?string $normalizedInner = null,
    ) {
    }
}
