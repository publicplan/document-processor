<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Exception;

/**
 * Exception für Fehler beim Laden eines Dokuments.
 */
class DocumentLoadException extends DocumentProcessorException
{
    public function __construct(
        string $message,
        string $documentPath,
        private readonly ?string $reason = null,
        int $code = 0,
        ?\Exception $previous = null
    ) {
        $fullMessage = $reason
            ? sprintf('%s: %s', $message, $reason)
            : $message;

        parent::__construct($fullMessage, $documentPath, $code, $previous);
    }

    /**
     * Gibt den spezifischen Grund für den Ladefehler zurück.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }
}
