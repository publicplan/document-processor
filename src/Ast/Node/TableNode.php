<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class TableNode extends AstNode
{
    /**
     * @param AstNode[] $rows
     */
    public function __construct(
        private array $rows = [],
        private ?int $width = null,
        private ?string $widthUnit = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\SourceReference $sourceRef = null,
        ?string $phpWordType = null,
        ?array $resolvedStyle = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\RenderHints $renderHints = null,
        array $whitespaceFlags = [],
        array $originFlags = [],
    ) {
        parent::__construct($sourceRef, $phpWordType, $resolvedStyle, $renderHints, $whitespaceFlags, $originFlags);
    }

    public function getRows(): array
    {
        return $this->rows;
    }

    public function addRow(AstNode $row): self
    {
        $this->rows[] = $row;
        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getWidthUnit(): ?string
    {
        return $this->widthUnit;
    }

    public function toArray(): array
    {
        return [
            'type' => 'table',
            'width' => $this->width,
            'widthUnit' => $this->widthUnit,
            'rows' => array_map(fn($r) => $r->toArray(), $this->rows),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
