<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class FormatNode extends AstNode
{
    /**
     * @param AstNode[] $children
     */
    public function __construct(
        private array $children = [],
        private string $formatType = 'span',
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

    public function getFormatType(): string
    {
        return $this->formatType;
    }

    public function toArray(): array
    {
        return [
            'type' => 'format',
            'formatType' => $this->formatType,
            'children' => array_map(fn($c) => $c->toArray(), $this->children),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
