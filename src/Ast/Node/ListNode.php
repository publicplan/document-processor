<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class ListNode extends AstNode
{
    /**
     * @param AstNode[] $items
     */
    public function __construct(
        private array $items = [],
        ?\Publicplan\DocumentProcessor\Ast\Metadata\SourceReference $sourceRef = null,
        ?string $phpWordType = null,
        ?array $resolvedStyle = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\RenderHints $renderHints = null,
        array $whitespaceFlags = [],
        array $originFlags = [],
    ) {
        parent::__construct($sourceRef, $phpWordType, $resolvedStyle, $renderHints, $whitespaceFlags, $originFlags);
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function addItem(AstNode $item): self
    {
        $this->items[] = $item;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'type' => 'list',
            'items' => array_map(fn($i) => $i->toArray(), $this->items),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
