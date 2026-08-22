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

    abstract public function toArray(): array;

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
