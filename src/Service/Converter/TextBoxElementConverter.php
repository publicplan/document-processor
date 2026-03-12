<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use PhpOffice\PhpWord\Element\TextBox as DocTextBox;
use Publicplan\DocumentProcessor\Model\ConversionContext;

/**
 * Konvertiert TextBox-Elemente (Container mit Rahmen) in HTML.
 */
class TextBoxElementConverter implements ElementConverterInterface
{
    public function supports(object $element): bool
    {
        return $element instanceof DocTextBox;
    }

    public function convert(object $element, ConversionContext $context): string
    {
        /** @var DocTextBox $element */
        $style = $element->getStyle();

        // Border-Styles extrahieren
        $boxStyles = [];

        $borderSize  = $style?->getBorderSize();
        $borderColor = $style?->getBorderColor();

        if ($borderSize !== null && $borderSize > 0) {
            // Border-Größe ist in Points (pt), wir konvertieren zu cm
            // 1 pt = 0.0352778 cm
            $width       = round($borderSize * 0.0352778, 2);
            $color       = $borderColor ?? '000000';
            $boxStyles[] = sprintf('border: %scm solid #%s;', $width, $color);
        }

        // Background color
        $bgColor = $style?->getBgColor();
        if ($bgColor !== null) {
            $boxStyles[] = sprintf('background-color: #%s;', $bgColor);
        }

        // Inner margins (Padding)
        if ($style !== null && $style->hasInnerMargins()) {
            $margins = [
                $this->twipsToCm($style->getInnerMarginTop()),
                $this->twipsToCm($style->getInnerMarginRight()),
                $this->twipsToCm($style->getInnerMarginBottom()),
                $this->twipsToCm($style->getInnerMarginLeft()),
            ];

            // Nur setzen wenn nicht alle 0 sind
            if (array_sum($margins) > 0) {
                $boxStyles[] = sprintf(
                    'padding: %scm %scm %scm %scm;',
                    $margins[0],
                    $margins[1],
                    $margins[2],
                    $margins[3]
                );
            }
        }

        // Child-Elemente konvertieren
        $content          = '';
        $elementConverter = new ElementConverterRegistry();
        $elementConverter->registerDefaultConverters();

        foreach ($element->getElements() as $childElement) {
            $elementText = $elementConverter->convert($childElement, $context);
            if ($elementText !== null) {
                $content .= $elementText;
            }
        }

        // Wenn keine Styles gesetzt sind, einfach den Content zurückgeben
        if (empty($boxStyles)) {
            return $content;
        }

        return sprintf(
            '<div style="%s">%s</div>%s',
            implode(' ', $boxStyles),
            $content,
            PHP_EOL
        );
    }

    /**
     * Konvertiert Twips in Zentimeter.
     *
     * @param int|null $twips Twips-Wert
     */
    private function twipsToCm(?int $twips): float
    {
        if ($twips === null) {
            return 0.0;
        }

        return round($twips / 1440 * 2.54, 2);
    }

    public function getPriority(): int
    {
        return 20; // Höher als TextRunElementConverter (15)
    }
}
