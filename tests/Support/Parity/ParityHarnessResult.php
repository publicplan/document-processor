<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Support\Parity;

use Publicplan\DocumentProcessor\Enum\RenderMode;
use Publicplan\DocumentProcessor\Model\HtmlParityResult;
use Publicplan\DocumentProcessor\Model\ProcessedDocument;

readonly class ParityHarnessResult
{
    public function __construct(
        public RenderMode $renderMode,
        public ?ProcessedDocument $legacyDocument = null,
        public ?ProcessedDocument $astDocument = null,
        public ?HtmlParityResult $comparison = null,
        public ?string $artifactDirectory = null
    )
    {
    }
}
