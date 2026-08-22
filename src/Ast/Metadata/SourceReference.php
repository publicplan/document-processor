<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Metadata;

/**
 * SourceReference provides traceback from AST nodes to their OOXML origins.
 * Fully optional but enables debugging and future template annotation.
 */
class SourceReference
{
    /**
     * @param 'document'|'styles'|'numbering' $part
     * @param int $sectionIndex 0-based index
     * @param int $elementIndex 0-based index within section
     * @param ?string $xmlPath optional XPath to element
     * @param array<string, mixed> $xmlAttributes critical attributes as JSON
     */
    public function __construct(
        private readonly string $part,
        private readonly int $sectionIndex,
        private readonly int $elementIndex,
        private readonly ?string $xmlPath = null,
        private readonly array $xmlAttributes = [],
    ) {}

    public function getPart(): string
    {
        return $this->part;
    }

    public function getSectionIndex(): int
    {
        return $this->sectionIndex;
    }

    public function getElementIndex(): int
    {
        return $this->elementIndex;
    }

    public function getXmlPath(): ?string
    {
        return $this->xmlPath;
    }

    public function getXmlAttributes(): array
    {
        return $this->xmlAttributes;
    }

    public function toArray(): array
    {
        return [
            'part' => $this->part,
            'sectionIndex' => $this->sectionIndex,
            'elementIndex' => $this->elementIndex,
            'xmlPath' => $this->xmlPath,
            'xmlAttributes' => $this->xmlAttributes,
        ];
    }
}
