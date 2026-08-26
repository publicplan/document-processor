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
        private ?float $spacingBefore = null,
        private ?float $spacingAfter = null,
        private ?float $indentLeft = null,
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

    public function getSpacingBefore(): ?float
    {
        return $this->spacingBefore;
    }

    public function getSpacingAfter(): ?float
    {
        return $this->spacingAfter;
    }

    public function getIndentLeft(): ?float
    {
        return $this->indentLeft;
    }

    public function toArray(): array
    {
        return [
            'type' => 'list',
            ...$this->styleContextToArray(),
            'spacing' => [
                'before' => $this->spacingBefore,
                'after' => $this->spacingAfter,
            ],
            'indent' => [
                'left' => $this->indentLeft,
            ],
            'items' => array_map(fn($i) => $i->toArray(), $this->items),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
