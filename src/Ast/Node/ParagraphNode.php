<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class ParagraphNode extends AstNode
{
    /**
     * @param AstNode[] $children
     */
    public function __construct(
        private array $children = [],
        private ?string $alignment = null,
        private ?float $indentLeft = null,
        private ?float $indentRight = null,
        private ?float $indentFirstLine = null,
        private ?float $spacingBefore = null,
        private ?float $spacingAfter = null,
        private ?float $lineHeight = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\SourceReference $sourceRef = null,
        ?string $phpWordType = null,
        ?array $resolvedStyle = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\RenderHints $renderHints = null,
        array $whitespaceFlags = [],
        array $originFlags = [],
    ) {
        parent::__construct($sourceRef, $phpWordType, $resolvedStyle, $renderHints, $whitespaceFlags, $originFlags);
    }

    public function getChildren(): array
    {
        return $this->children;
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

    public function toArray(): array
    {
        return [
            'type' => 'paragraph',
            'alignment' => $this->alignment,
            'indent' => [
                'left' => $this->indentLeft,
                'right' => $this->indentRight,
                'firstLine' => $this->indentFirstLine,
            ],
            'spacing' => [
                'before' => $this->spacingBefore,
                'after' => $this->spacingAfter,
                'line' => $this->lineHeight,
            ],
            'children' => array_map(fn($c) => $c->toArray(), $this->children),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
