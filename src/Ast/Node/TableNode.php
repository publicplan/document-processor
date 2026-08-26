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
        private ?string $alignment = null,
        private ?float $indentLeft = null,
        private ?float $spacingBefore = null,
        private ?float $spacingAfter = null,
        private ?float $cellSpacing = null,
        private ?string $layout = null,
        private ?array $cellMargins = null,
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

    public function getAlignment(): ?string
    {
        return $this->alignment;
    }

    public function getIndentLeft(): ?float
    {
        return $this->indentLeft;
    }

    public function getSpacingBefore(): ?float
    {
        return $this->spacingBefore;
    }

    public function getSpacingAfter(): ?float
    {
        return $this->spacingAfter;
    }

    public function getCellSpacing(): ?float
    {
        return $this->cellSpacing;
    }

    public function getLayout(): ?string
    {
        return $this->layout;
    }

    public function getCellMargins(): ?array
    {
        return $this->cellMargins;
    }

    public function toArray(): array
    {
        return [
            'type' => 'table',
            ...$this->styleContextToArray(),
            'width' => $this->width,
            'widthUnit' => $this->widthUnit,
            'alignment' => $this->alignment,
            'indent' => [
                'left' => $this->indentLeft,
            ],
            'spacing' => [
                'before' => $this->spacingBefore,
                'after' => $this->spacingAfter,
            ],
            'cellSpacing' => $this->cellSpacing,
            'layout' => $this->layout,
            'cellMargins' => $this->cellMargins,
            'rows' => array_map(fn($r) => $r->toArray(), $this->rows),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
