<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

use Publicplan\DocumentProcessor\Ast\Metadata\SourceReference;
use Publicplan\DocumentProcessor\Ast\Metadata\RenderHints;

abstract class AstNode
{
    /**
     * @param array<string, mixed> $renderHints
     * @param array<string, mixed> $whitespaceFlags
     * @param array<string, mixed> $originFlags
     */
    public function __construct(
        protected ?SourceReference $sourceRef = null,
        protected ?string $phpWordType = null,
        protected ?array $resolvedStyle = null,
        protected ?RenderHints $renderHints = null,
        protected array $whitespaceFlags = [],
        protected array $originFlags = [],
        protected ?array $styleRef = null,
        protected ?array $styleRefs = null,
        protected ?array $styleProvenance = null,
        protected ?array $resolvedLayout = null,
    ) {
        $this->renderHints ??= RenderHints::empty();
    }

    public function getSourceRef(): ?SourceReference
    {
        return $this->sourceRef;
    }

    public function setSourceRef(?SourceReference $sourceRef): self
    {
        $this->sourceRef = $sourceRef;
        return $this;
    }

    public function getPhpWordType(): ?string
    {
        return $this->phpWordType;
    }

    public function getResolvedStyle(): ?array
    {
        return $this->resolvedStyle;
    }

    public function getRenderHints(): RenderHints
    {
        return $this->renderHints;
    }

    public function getWhitespaceFlags(): array
    {
        return $this->whitespaceFlags;
    }

    public function getOriginFlags(): array
    {
        return $this->originFlags;
    }

    public function getStyleRef(): ?array
    {
        return $this->styleRef;
    }

    public function setStyleRef(?array $styleRef): self
    {
        $this->styleRef = $styleRef;
        return $this;
    }

    public function getStyleRefs(): ?array
    {
        return $this->styleRefs;
    }

    public function setStyleRefs(?array $styleRefs): self
    {
        $this->styleRefs = $styleRefs;
        return $this;
    }

    public function getStyleProvenance(): ?array
    {
        return $this->styleProvenance;
    }

    public function setStyleProvenance(?array $styleProvenance): self
    {
        $this->styleProvenance = $styleProvenance;
        return $this;
    }

    public function getResolvedLayout(): ?array
    {
        return $this->resolvedLayout;
    }

    public function setResolvedLayout(?array $resolvedLayout): self
    {
        $this->resolvedLayout = $resolvedLayout;
        return $this;
    }

    abstract public function toArray(): array;

    /**
     * @return array<string, mixed>
     */
    protected function styleContextToArray(): array
    {
        $payload = [];
        if ($this->styleRef !== null) {
            $payload['styleRef'] = $this->styleRef;
        }
        if ($this->styleRefs !== null) {
            $payload['styleRefs'] = $this->styleRefs;
        }
        if ($this->styleProvenance !== null) {
            $payload['styleProvenance'] = $this->styleProvenance;
        }
        if ($this->resolvedLayout !== null) {
            $payload['resolvedLayout'] = $this->resolvedLayout;
        }

        return $payload;
    }

    protected function metadataToArray(): array
    {
        return [
            'sourceRef' => $this->sourceRef?->toArray(),
            'phpWordType' => $this->phpWordType,
            'resolvedStyle' => $this->resolvedStyle,
            'renderHints' => $this->renderHints->toArray(),
            'whitespaceFlags' => $this->whitespaceFlags,
            'originFlags' => $this->originFlags,
        ];
    }
}
