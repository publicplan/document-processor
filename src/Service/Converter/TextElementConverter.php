<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Model\ParserError;
use PhpOffice\PhpWord\Element\Text as DocText;
use PhpOffice\PhpWord\Style\Font;

/**
 * Konvertiert Text-Elemente in HTML.
 */
class TextElementConverter implements ElementConverterInterface
{
    public const string DEFAULT_TAG_UNDERLINE = 'u';
    public const string DEFAULT_TAG_ITALIC = 'em';
    public const string DEFAULT_TAG_BOLD = 'strong';

    public function supports(object $element): bool
    {
        return $element instanceof DocText;
    }

    public function convert(object $element, ConversionContext $context): string
    {
        /** @var DocText $element */
        $text = $element->getText() ?? '';

        // Gelöschter Text (Strikethrough) wird als Marker zurückgegeben
        if ($text === '' || $element->getFontStyle()?->isStrikethrough()) {
            return '##deleted##';
        }

        $tags = $this->extractFormatTags($element);

        // Nicht-umbrechende Leerzeichen (Unicode U+00A0) korrigieren
        // Word verwendet echte non-breaking spaces, die als ° angezeigt werden sollen
        $text = str_replace("\xC2\xA0", '&nbsp;', $text);

        return $this->wrapWithTags($text, $tags);
    }

    /**
     * Extrahiert HTML-Tags basierend auf der Formatierung.
     *
     * @param DocText $element
     * @return string[]
     */
    private function extractFormatTags(DocText $element): array
    {
        $tags = [];
        $fontStyle = $element->getFontStyle();

        if (!$fontStyle) {
            return $tags;
        }

        if ($fontStyle->getUnderline() !== Font::UNDERLINE_NONE) {
            $tags[] = self::DEFAULT_TAG_UNDERLINE;
        }

        if ($fontStyle->isBold()) {
            $tags[] = self::DEFAULT_TAG_BOLD;
        }

        if ($fontStyle->isItalic()) {
            $tags[] = self::DEFAULT_TAG_ITALIC;
        }

        return $tags;
    }

    /**
     * Wrappt Text mit HTML-Tags.
     *
     * @param string $text Der zu wrappende Text
     * @param string[] $tags Die HTML-Tags
     */
    private function wrapWithTags(string $text, array $tags): string
    {
        if (empty($tags)) {
            return $text;
        }

        $prefix = '<' . implode('><', $tags) . '>';
        $suffix = '</' . implode('></', array_reverse($tags)) . '>';

        return $prefix . $text . $suffix;
    }

    public function getPriority(): int
    {
        return 10; // Niedrige Priorität, da Text einfach ist
    }
}
