<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Model;

use DateTimeInterface;

readonly class ProcessedAstAndHtmlDocument
{
    /**
     * @param array<string, mixed>         $ast
     * @param array<string, ParserError[]> $messages
     */
    public function __construct(
        public string            $astVersion,
        public array             $ast,
        public string            $html,
        public DateTimeInterface $lastModified,
        public bool              $hasUnacceptedChanges,
        public array             $messages,
        public string            $sourceFilename,
        public ?bool             $isHtmlFragmentValid = null
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
