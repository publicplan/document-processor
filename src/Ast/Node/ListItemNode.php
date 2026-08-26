<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

use Publicplan\DocumentProcessor\Ast\Metadata\ListFormat;

class ListItemNode extends AstNode
{
    /**
     * @param AstNode[] $children
     */
    public function __construct(
        private int $numId = 0,
        private int $depth = 0,
        private ListFormat $numFormat = ListFormat::Bullet,
        private ?int $startNumeration = null,
        private array $children = [],
        private ?string $alignment = null,
        private ?float $indentLeft = null,
        private ?float $indentRight = null,
        private ?float $indentFirstLine = null,
        private ?float $indentHanging = null,
        private ?float $spacingBefore = null,
        private ?float $spacingAfter = null,
        private ?float $lineHeight = null,
        private ?float $levelIndentLeft = null,
        private ?float $levelIndentHanging = null,
        private ?float $levelTabStop = null,
        private ?float $levelMarkerOffset = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\SourceReference $sourceRef = null,
        ?string $phpWordType = null,
        ?array $resolvedStyle = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\RenderHints $renderHints = null,
        array $whitespaceFlags = [],
        array $originFlags = [],
    ) {
        parent::__construct($sourceRef, $phpWordType, $resolvedStyle, $renderHints, $whitespaceFlags, $originFlags);
    }

    public function getNumId(): int
    {
        return $this->numId;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getNumFormat(): ListFormat
    {
        return $this->numFormat;
    }

    public function getStartNumeration(): ?int
    {
        return $this->startNumeration;
    }

    public function setStartNumeration(?int $numeration): self
    {
        $this->startNumeration = $numeration;
        return $this;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function getAlignment(): ?string
    {
        return $this->alignment;
    }

    public function getIndentLeft(): ?float
    {
        return $this->indentLeft;
    }

    public function getIndentRight(): ?float
    {
        return $this->indentRight;
    }

    public function getIndentFirstLine(): ?float
    {
        return $this->indentFirstLine;
    }

    public function getIndentHanging(): ?float
    {
        return $this->indentHanging;
    }

    public function getSpacingBefore(): ?float
    {
        return $this->spacingBefore;
    }

    public function getSpacingAfter(): ?float
    {
        return $this->spacingAfter;
    }

    public function getLineHeight(): ?float
    {
        return $this->lineHeight;
    }

    public function getLevelIndentLeft(): ?float
    {
        return $this->levelIndentLeft;
    }

    public function getLevelIndentHanging(): ?float
    {
        return $this->levelIndentHanging;
    }

    public function getLevelTabStop(): ?float
    {
        return $this->levelTabStop;
    }

    public function getLevelMarkerOffset(): ?float
    {
        return $this->levelMarkerOffset;
    }

    public function addChild(AstNode $child): self
    {
        $this->children[] = $child;
        return $this;
    }

    public function setChildren(array $children): self
    {
        $this->children = $children;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'type' => 'listItem',
            'numId' => $this->numId,
            'depth' => $this->depth,
            'numFormat' => $this->numFormat->value,
            'startNumeration' => $this->startNumeration,
            'alignment' => $this->alignment,
            'indent' => [
                'left' => $this->indentLeft,
                'right' => $this->indentRight,
                'firstLine' => $this->indentFirstLine,
                'hanging' => $this->indentHanging,
            ],
            'spacing' => [
                'before' => $this->spacingBefore,
                'after' => $this->spacingAfter,
                'line' => $this->lineHeight,
            ],
            'level' => [
                'indentLeft' => $this->levelIndentLeft,
                'indentHanging' => $this->levelIndentHanging,
                'tabStop' => $this->levelTabStop,
                'markerOffset' => $this->levelMarkerOffset,
            ],
            'children' => array_map(fn($c) => $c->toArray(), $this->children),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
