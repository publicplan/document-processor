<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Exception;

use Exception;

/**
 * Basis-Exception für alle DocumentProcessor-Fehler.
 */
class DocumentProcessorException extends Exception
{
    public function __construct(
        string $message,
        private readonly ?string $documentPath = null,
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Gibt den Pfad zum betroffenen Dokument zurück.
     */
    public function getDocumentPath(): ?string
    {
        return $this->documentPath;
    }
}
