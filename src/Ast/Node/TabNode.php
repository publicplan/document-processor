<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class TabNode extends AstNode
{
    public function __construct(
        private ?string $position = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\SourceReference $sourceRef = null,
        ?string $phpWordType = null,
        ?array $resolvedStyle = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\RenderHints $renderHints = null,
        array $whitespaceFlags = [],
        array $originFlags = [],
    ) {
        parent::__construct($sourceRef, $phpWordType, $resolvedStyle, $renderHints, $whitespaceFlags, $originFlags);
    }

    public function getPosition(): ?string
    {
        return $this->position;
    }

    public function toArray(): array
    {
        return [
            'type' => 'tab',
            'position' => $this->position,
            'metadata' => $this->metadataToArray(),
        ];
    }
}
