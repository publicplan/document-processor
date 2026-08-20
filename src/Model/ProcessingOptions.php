<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Model;

/**
 * Optionen für die Verarbeitung eines Dokuments.
 */
readonly class ProcessingOptions
{
    public function __construct(
        public bool $removeDeletedContent = true
    )
    {
    }
}
