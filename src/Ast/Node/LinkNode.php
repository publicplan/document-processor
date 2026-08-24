<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class LinkNode extends AstNode
{
    /**
     * @param AstNode[] $children
     */
    public function __construct(
        private array $children = [],
        private ?string $href = null,
        private ?string $anchor = null,
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

    public function getHref(): ?string
    {
        return $this->href;
    }

    public function getAnchor(): ?string
    {
        return $this->anchor;
    }

    public function toArray(): array
    {
        return [
            'type' => 'link',
            'href' => $this->href,
            'anchor' => $this->anchor,
            'children' => array_map(fn($c) => $c->toArray(), $this->children),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
