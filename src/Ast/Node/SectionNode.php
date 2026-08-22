<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

class SectionNode extends AstNode
{
    /**
     * @param AstNode[] $paragraphs
     */
    public function __construct(
        private array $paragraphs = [],
        ?\Publicplan\DocumentProcessor\Ast\Metadata\SourceReference $sourceRef = null,
        ?string $phpWordType = null,
        ?array $resolvedStyle = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\RenderHints $renderHints = null,
        array $whitespaceFlags = [],
        array $originFlags = [],
    ) {
        parent::__construct($sourceRef, $phpWordType, $resolvedStyle, $renderHints, $whitespaceFlags, $originFlags);
    }

    public function getParagraphs(): array
    {
        return $this->paragraphs;
    }

    public function addParagraph(AstNode $paragraph): self
    {
        $this->paragraphs[] = $paragraph;
        return $this;
    }

    /**
     * Setzt alle Absätze zurück. Wird bei AST-Normalisierung verwendet.
     */
    public function setParagraphs(array $paragraphs): self
    {
        $this->paragraphs = $paragraphs;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'type' => 'section',
            'paragraphs' => array_map(fn($p) => $p->toArray(), $this->paragraphs),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
