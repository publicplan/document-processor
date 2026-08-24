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
            'children' => array_map(fn($c) => $c->toArray(), $this->children),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
