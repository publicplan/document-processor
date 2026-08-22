<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class BorderGroupNode extends AstNode
{
    /**
     * @param AstNode[] $children
     */
    public function __construct(
        private array $children = [],
        private ?string $borderStyle = null,
        private ?int $borderSize = null,
        private ?string $borderColor = null,
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

    public function getBorderStyle(): ?string
    {
        return $this->borderStyle;
    }

    public function getBorderSize(): ?int
    {
        return $this->borderSize;
    }

    public function getBorderColor(): ?string
    {
        return $this->borderColor;
    }

    public function toArray(): array
    {
        return [
            'type' => 'borderGroup',
            'border' => [
                'style' => $this->borderStyle,
                'size' => $this->borderSize,
                'color' => $this->borderColor,
            ],
            'children' => array_map(fn($c) => $c->toArray(), $this->children),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
