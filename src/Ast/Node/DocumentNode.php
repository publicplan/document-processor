<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

/**
 * DocumentNode is the root container.
 *
 * @template T of AstNode
 */
class DocumentNode extends AstNode
{
    /**
     * @param AstNode[] $sections
     */
    public function __construct(
        private array $sections = [],
        ?\Publicplan\DocumentProcessor\Ast\Metadata\SourceReference $sourceRef = null,
        ?string $phpWordType = null,
        ?array $resolvedStyle = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\RenderHints $renderHints = null,
        array $whitespaceFlags = [],
        array $originFlags = [],
    ) {
        parent::__construct($sourceRef, $phpWordType, $resolvedStyle, $renderHints, $whitespaceFlags, $originFlags);
    }

    public function getSections(): array
    {
        return $this->sections;
    }

    public function addSection(AstNode $section): self
    {
        $this->sections[] = $section;
        return $this;
    }

    public function toArray(): array
    {
        return [
            'type' => 'document',
            'sections' => array_map(fn($s) => $s->toArray(), $this->sections),
            'metadata' => $this->metadataToArray(),
        ];
    }
}
