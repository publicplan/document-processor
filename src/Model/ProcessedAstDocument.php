<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Model;

use DateTimeInterface;

readonly class ProcessedAstDocument
{
    /**
     * @param array<string, mixed>         $ast
     * @param array<string, ParserError[]> $messages
     */
    public function __construct(
        public string            $astVersion,
        public array             $ast,
        public DateTimeInterface $lastModified,
        public bool              $hasUnacceptedChanges,
        public array             $messages,
        public string            $sourceFilename
    ) {
    }

    /**
     * @return ParserError[]
     */
    public function getErrors(): array
    {
        return $this->messages['errors'] ?? [];
    }

    /**
     * @return ParserError[]
     */
    public function getWarnings(): array
    {
        return $this->messages['warnings'] ?? [];
    }

    /**
     * @return ParserError[]
     */
    public function getInfos(): array
    {
        return $this->messages['infos'] ?? [];
    }
}
