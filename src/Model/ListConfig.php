<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Model;

/**
 * DTO für Listen-Konfiguration.
 *
 * Enthält die Konfiguration für Listen-Rendering (Tag-Typ, Nummerierung, Startwert, Spacing).
 */
readonly class ListConfig
{
    public function __construct(
        public string  $tag,
        public ?string $type,
        public float   $bottomSpacingCm = 0.0,
        public int     $start = 1,
        public string  $sequenceKey = '',
        public int|string|null $docxListId = null
    )
    {
    }

    public function renderStartTag(?int $startOverride = null): string
    {
        $attributes = [];
        $start      = $startOverride ?? $this->start;

        $styles   = [];
        $styles[] = sprintf(
            'margin-bottom: %s',
            $this->bottomSpacingCm ? $this->bottomSpacingCm . 'cm' : '0'
        );

        $attributes[] = 'style="' . implode('; ', $styles) . ';"';
        if ($this->type) {
            $attributes[] = 'type="' . $this->type . '"';
        }
        if ($this->tag === 'ol' && $start > 1) {
            $attributes[] = sprintf('start="%d"', $start);
        }
        if ($this->docxListId !== null) {
            $attributes[] = sprintf(
                'data-docx-list-id="%s"',
                htmlspecialchars((string)$this->docxListId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }
        if ($this->sequenceKey !== '') {
            $attributes[] = sprintf(
                'data-docx-list-key="%s"',
                htmlspecialchars($this->sequenceKey, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            );
        }

        return sprintf(
            "<%s%s>", $this->tag,
            $attributes ? ' ' . implode(' ', $attributes) : ''
        );
    }

    public function renderEndTag(): string
    {
        return "</" . $this->tag . ">";
    }

    public function isSameList(self $other): bool
    {
        return $this->tag === $other->tag
            && $this->type === $other->type
            && $this->sequenceKey === $other->sequenceKey;
    }

    public function isOrdered(): bool
    {
        return $this->tag === 'ol';
    }
}
