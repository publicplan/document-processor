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
    ) {
    }
}
