<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class TableRowNode extends AstNode
{
    /**
     * @param AstNode[] $cells
     */
    public function __construct(
        private array $cells = [],
        private bool $isHeader = false,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\SourceReference $sourceRef = null,
        ?string $phpWordType = null,
        ?array $resolvedStyle = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\RenderHints $renderHints = null,
        array $whitespaceFlags = [],
        array $originFlags = [],
    ) {
        parent::__construct($sourceRef, $phpWordType, $resolvedStyle, $renderHints, $whitespaceFlags, $originFlags);
    }

    public function getCells(): array
    {
        return $this->cells;
    }

    public function addCell(AstNode $cell): self
    {
        $this->cells[] = $cell;
        return $this;
    }

    public function isHeader(): bool
    {
        return $this->isHeader;
    }

    public function toArray(): array
    {
        return [
            'type' => 'tableRow',
            'isHeader' => $this->isHeader,
            'cells' => array_map(fn($c) => $c->toArray(), $this->cells),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
