<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Model;

use Publicplan\DocumentProcessor\Service\Ast\Template\TemplateSyntaxProfile;

/**
 * Optionen für die Verarbeitung eines Dokuments.
 */
readonly class ProcessingOptions
{
    public function __construct(
        public bool $removeDeletedContent = true,
        public bool $validateHtml = false,
        public ?TemplateSyntaxProfile $templateSyntaxProfile = null,
    )
    {
    }
}
