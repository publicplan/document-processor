<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class ScaleNode extends AstNode
{
    /**
     * @param AstNode[] $children
     */
    public function __construct(
        private array $children = [],
        private float $scaleX = 1.0,
        private float $scaleY = 1.0,
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

    public function getScaleX(): float
    {
        return $this->scaleX;
    }

    public function getScaleY(): float
    {
        return $this->scaleY;
    }

    public function toArray(): array
    {
        return [
            'type' => 'scale',
            'scaleX' => $this->scaleX,
            'scaleY' => $this->scaleY,
            'children' => array_map(fn($c) => $c->toArray(), $this->children),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
