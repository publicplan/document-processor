<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class TextBoxNode extends AstNode
{
    /**
     * @param AstNode[] $children
     */
    public function __construct(
        private array $children = [],
        private ?float $posX = null,
        private ?float $posY = null,
        private ?float $width = null,
        private ?float $height = null,
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

    public function getPosX(): ?float
    {
        return $this->posX;
    }

    public function getPosY(): ?float
    {
        return $this->posY;
    }

    public function getWidth(): ?float
    {
        return $this->width;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function toArray(): array
    {
        return [
            'type' => 'textBox',
            'position' => ['x' => $this->posX, 'y' => $this->posY],
            'size' => ['width' => $this->width, 'height' => $this->height],
            'children' => array_map(fn($c) => $c->toArray(), $this->children),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
