<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class PageBreakNode extends AstNode
{
    public function __construct(
        ?\Publicplan\DocumentProcessor\Ast\Metadata\SourceReference $sourceRef = null,
        ?string $phpWordType = null,
        ?array $resolvedStyle = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\RenderHints $renderHints = null,
        array $whitespaceFlags = [],
        array $originFlags = [],
    ) {
        parent::__construct($sourceRef, $phpWordType, $resolvedStyle, $renderHints, $whitespaceFlags, $originFlags);
    }

    public function toArray(): array
    {
        return [
            'type' => 'pageBreak',
            'metadata' => $this->metadataToArray(),
        ];
    }
}
