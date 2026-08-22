<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class BreakNode extends AstNode
{
    public function __construct(
        private string $type = 'line', // line, page, column
        ?\Publicplan\DocumentProcessor\Ast\Metadata\SourceReference $sourceRef = null,
        ?string $phpWordType = null,
        ?array $resolvedStyle = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\RenderHints $renderHints = null,
        array $whitespaceFlags = [],
        array $originFlags = [],
    ) {
        parent::__construct($sourceRef, $phpWordType, $resolvedStyle, $renderHints, $whitespaceFlags, $originFlags);
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function toArray(): array
    {
        return [
            'type' => 'break',
            'breakType' => $this->type,
            'metadata' => $this->metadataToArray(),
        ];
    }
}
