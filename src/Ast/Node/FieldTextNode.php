<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class FieldTextNode extends AstNode
{
    public function __construct(
        private string $fieldCode = '',
        private ?string $fieldResult = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\SourceReference $sourceRef = null,
        ?string $phpWordType = null,
        ?array $resolvedStyle = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\RenderHints $renderHints = null,
        array $whitespaceFlags = [],
        array $originFlags = [],
    ) {
        parent::__construct($sourceRef, $phpWordType, $resolvedStyle, $renderHints, $whitespaceFlags, $originFlags);
    }

    public function getFieldCode(): string
    {
        return $this->fieldCode;
    }

    public function getFieldResult(): ?string
    {
        return $this->fieldResult;
    }

    public function toArray(): array
    {
        return [
            'type' => 'fieldText',
            'fieldCode' => $this->fieldCode,
            'fieldResult' => $this->fieldResult,
            'metadata' => $this->metadataToArray(),
        ];
    }
}
