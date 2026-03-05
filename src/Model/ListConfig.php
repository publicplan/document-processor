<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Model;

/**
 * DTO für Listen-Konfiguration.
 * 
 * Enthält die Konfiguration für Listen-Rendering (Tag-Typ, Nummerierung, Spacing).
 */
readonly class ListConfig
{
    public function __construct(
        public string $tag, 
        public ?string $type, 
        public float $bottomSpacingCm = 0.0
    ) {
    }

    public function renderStartTag(): string
    {
        $attributes = [];

        $styles   = [];
        $styles[] = sprintf(
            'margin-bottom: %s',
            $this->bottomSpacingCm ? $this->bottomSpacingCm . 'cm' : '0'
        );

        $attributes[] = 'style="' . implode('; ', $styles) . ';"';
        if ($this->type) {
            $attributes[] = 'type="' . $this->type . '"';
        }
        return sprintf(
            "<%s%s>", $this->tag,
            $attributes ? ' ' . implode(' ', $attributes) : ''
        );
    }

    public function renderEndTag(): string
    {
        return "</{$this->tag}>";
    }
}
