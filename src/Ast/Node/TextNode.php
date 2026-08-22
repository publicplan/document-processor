<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Node;

use Publicplan\DocumentProcessor\Ast\Metadata\TrackChangeType;

class TextNode extends AstNode
{
    public function __construct(
        private string $content = '',
        private bool $bold = false,
        private bool $italic = false,
        private bool $underline = false,
        private ?float $fontSize = null,
        private bool $preserveSpace = false,
        private TrackChangeType $trackChange = TrackChangeType::None,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\SourceReference $sourceRef = null,
        ?string $phpWordType = null,
        ?array $resolvedStyle = null,
        ?\Publicplan\DocumentProcessor\Ast\Metadata\RenderHints $renderHints = null,
        array $whitespaceFlags = [],
        array $originFlags = [],
    ) {
        parent::__construct($sourceRef, $phpWordType, $resolvedStyle, $renderHints, $whitespaceFlags, $originFlags);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function isBold(): bool
    {
        return $this->bold;
    }

    public function isItalic(): bool
    {
        return $this->italic;
    }

    public function isUnderline(): bool
    {
        return $this->underline;
    }

    public function getFontSize(): ?float
    {
        return $this->fontSize;
    }

    public function isPreserveSpace(): bool
    {
        return $this->preserveSpace;
    }

    public function getTrackChange(): TrackChangeType
    {
        return $this->trackChange;
    }

    public function toArray(): array
    {
        return [
            'type' => 'text',
            'content' => $this->content,
            'formatting' => [
                'bold' => $this->bold,
                'italic' => $this->italic,
                'underline' => $this->underline,
                'fontSize' => $this->fontSize,
            ],
            'preserveSpace' => $this->preserveSpace,
            'trackChange' => $this->trackChange->value,
            'metadata' => $this->metadataToArray(),
        ];
    }
}
