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
        private float $baseFontSizePt = 12.0,
        private string $baseFontSizeSource = 'fallback',
        private ?array $baseFontSizeRaw = null,
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

    public function getBaseFontSizePt(): float
    {
        return $this->baseFontSizePt;
    }

    public function setBaseFontSizePt(float $baseFontSizePt): self
    {
        $this->baseFontSizePt = $baseFontSizePt;
        return $this;
    }

    public function getBaseFontSizeSource(): string
    {
        return $this->baseFontSizeSource;
    }

    public function setBaseFontSizeSource(string $baseFontSizeSource): self
    {
        $this->baseFontSizeSource = $baseFontSizeSource;
        return $this;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBaseFontSizeRaw(): ?array
    {
        return $this->baseFontSizeRaw;
    }

    /**
     * @param array<string, mixed>|null $baseFontSizeRaw
     */
    public function setBaseFontSizeRaw(?array $baseFontSizeRaw): self
    {
        $this->baseFontSizeRaw = $baseFontSizeRaw;
        return $this;
    }

    public function toArray(): array
    {
        $document = [
            'type' => 'document',
            'baseFontSizePt' => $this->baseFontSizePt,
            'baseFontSizeSource' => $this->baseFontSizeSource,
            'sections' => array_map(fn($s) => $s->toArray(), $this->sections),
            'metadata' => $this->metadataToArray(),
        ];

        if ($this->baseFontSizeRaw !== null) {
            $document['baseFontSizeRaw'] = $this->baseFontSizeRaw;
        }

        return $document;
    }
}
