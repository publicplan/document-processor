<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use PhpOffice\PhpWord\Element\FormField;
use PhpOffice\PhpWord\Element\TextRun as DocTextRun;
use PhpOffice\PhpWord\SimpleType\Jc;
use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Model\ParserError;

/**
 * Konvertiert TextRun-Elemente (Absätze mit Formatierung) in HTML.
 */
class TextRunElementConverter implements ElementConverterInterface
{
    private const DEFAULT_BORDER_PADDING = '0.2'; // cm

    public function supports(object $element): bool
    {
        return $element instanceof DocTextRun;
    }

    public function convert(object $element, ConversionContext $context): string
    {
        /** @var DocTextRun $element */
        $text = $this->convertSubElements($element, $context);

        if ($text === '') {
            // Leere Absätze als <p>&#32;</p> mit Styles ausgeben (wie in Word)
            return $this->wrapWithParagraphStyles($element, '&#32;');
        }

        return $this->wrapWithParagraphStyles($element, $text);
    }

    /**
     * Konvertiert alle Unter-Elemente des TextRuns.
     */
    private function convertSubElements(DocTextRun $element, ConversionContext $context): string
    {
        $text             = '';
        $elementConverter = new ElementConverterRegistry();
        $elementConverter->registerDefaultConverters();

        foreach ($element->getElements() as $textElement) {
            $elementText = $elementConverter->convert($textElement, $context);

            if ($elementText !== null) {
                $text .= $elementText;
            } else {
                $this->handleInvalidElement($textElement, $element, $context);
            }
        }

        return $text;
    }

    /**
     * Behandelt ungültige/unbekannte Elemente.
     */
    private function handleInvalidElement(
        object            $textElement,
        DocTextRun        $parentElement,
        ConversionContext $context
    ): void
    {
        if ($textElement instanceof FormField) {
            $context->addMessage(
                ParserError::create(
                    ParserError::CONTAINS_FORM_FIELDS,
                    ParserError::SEVERITY_ERROR,
                    'Im Dokument definierte Formularfelder führen zur Fehlinterpretation der Vorlage durch den Parser und müssen daher in Word entfernt werden.'
                ),
                true
            );
        } else {
            $context->addMessage(
                ParserError::create(
                    ParserError::CONTAINS_UNHANDLED_ELEMENTS,
                    ParserError::SEVERITY_ERROR,
                    sprintf(
                        'Nicht unterstütztes Element in %s: %s)',
                        get_class($parentElement),
                        get_class($textElement)
                    )
                ),
                true
            );
        }
    }

    /**
     * Wendet Paragraph-Styles an und wrappend in &lt;p&gt;.
     */
    private function wrapWithParagraphStyles(DocTextRun $element, string $text): string
    {
        $blockClasses = [];
        $blockStyles  = [];
        $pStyle       = $element->getParagraphStyle();

        // Border-Styles
        $borderStyle = $this->buildBorderStyle($pStyle);
        if ($borderStyle !== null) {
            $blockStyles[] = $borderStyle;
        }

        // Ausrichtung
        if ($pStyle->getAlignment() === Jc::CENTER) {
            $blockStyles[] = 'text-align: center;';
        }
        if ($pStyle->getAlignment() === Jc::BOTH) {
            $blockStyles[] = 'text-align: justify;';
        }

        // Paragraph-Abstand
        $spaceAfter    = $pStyle->getSpaceAfter();
        $blockStyles[] = sprintf('margin-bottom: %scm;', $this->twipsToCm($spaceAfter));

        $indentLeft = $pStyle->getIndentLeft();
        $hanging    = $pStyle->getHanging();

        // Spezialfall: Hanging Indent mit Tab
        if ($indentLeft && $hanging && $indentLeft === $hanging && str_contains($text, "\t")) {
            return $this->buildHangingIndentHtml($text, $blockClasses, $blockStyles);
        }

        // Standard-Indent
        if ($indentLeft) {
            $blockStyles[] = sprintf('padding-left: %scm;', $this->twipsToCm($indentLeft));
        }
        if ($hanging) {
            $blockStyles[] = sprintf('text-indent: -%scm;', $this->twipsToCm($hanging));
        }

        $result = sprintf(
            '<p%s%s>%s</p>%s',
            !empty($blockClasses) ? sprintf(' class="%s"', implode(' ', $blockClasses)) : '',
            !empty($blockStyles) ? sprintf(' style="%s"', implode(' ', $blockStyles)) : '',
            trim($text),
            PHP_EOL
        );

        // Entferne überflüssige aufeinanderfolgende Tags
        return $this->cleanupConsecutiveTags($result);
    }

    /**
     * Baut Hanging-Indent HTML mit Tabelle.
     */
    private function buildHangingIndentHtml(string $text, array $blockClasses, array $blockStyles): string
    {
        [$title, $items] = explode("\t", $text, 2);

        /** @noinspection HtmlUnknownAttribute */
        return sprintf(
            '<div class="hangingIndent%s"%s><table style="border-collapse: collapse; border-width: 0;"><tr><td style="vertical-align: top; padding-right: 1ex;">%s</td><td style="vertical-align: top;">%s</td></tr></table></div>%s',
            !empty($blockClasses) ? sprintf(' %s', implode(' ', $blockClasses)) : '',
            !empty($blockStyles) ? sprintf(' style="%s"', implode(' ', $blockStyles)) : '',
            $title,
            $items,
            PHP_EOL . PHP_EOL
        );
    }

    /**
     * Entfernt aufeinanderfolgende gleiche Tags.
     */
    private function cleanupConsecutiveTags(string $text): string
    {
        $searchReplace = [
            '</' . TextElementConverter::DEFAULT_TAG_BOLD . '><' . TextElementConverter::DEFAULT_TAG_BOLD . '>',
            '</' . TextElementConverter::DEFAULT_TAG_ITALIC . '><' . TextElementConverter::DEFAULT_TAG_ITALIC . '>',
            '</' . TextElementConverter::DEFAULT_TAG_UNDERLINE . '><' . TextElementConverter::DEFAULT_TAG_UNDERLINE . '>',
        ];

        return str_replace($searchReplace, '', $text);
    }

    /**
     * Konvertiert Twips in Zentimeter.
     *
     * @param float|string|null $twips Twips-Wert (kann auch String oder null sein)
     */
    private function twipsToCm(float|string|null $twips): float
    {
        if ($twips === null || $twips === '') {
            return 0.0;
        }

        $twips = (float)$twips;

        return round($twips / 1440 * 2.54, 2);
    }

    /**
     * Baut den Border-Style aus den Paragraph-Styles.
     */
    private function buildBorderStyle($pStyle): ?string
    {
        $borders = [
            'top'    => [
                'size'  => $pStyle->getBorderTopSize(),
                'color' => $pStyle->getBorderTopColor(),
                'style' => $pStyle->getBorderTopStyle(),
            ],
            'left'   => [
                'size'  => $pStyle->getBorderLeftSize(),
                'color' => $pStyle->getBorderLeftColor(),
                'style' => $pStyle->getBorderLeftStyle(),
            ],
            'right'  => [
                'size'  => $pStyle->getBorderRightSize(),
                'color' => $pStyle->getBorderRightColor(),
                'style' => $pStyle->getBorderRightStyle(),
            ],
            'bottom' => [
                'size'  => $pStyle->getBorderBottomSize(),
                'color' => $pStyle->getBorderBottomColor(),
                'style' => $pStyle->getBorderBottomStyle(),
            ],
        ];

        // Prüfe ob überhaupt Borders gesetzt sind
        $hasBorders = false;
        foreach ($borders as $border) {
            if ($border['size'] !== null && $border['size'] !== '') {
                $hasBorders = true;
                break;
            }
        }

        if (!$hasBorders) {
            return null;
        }

        // Prüfe ob alle Borders identisch sind
        $allIdentical = $this->areAllBordersIdentical($borders);

        $padding = sprintf('padding: %scm;', self::DEFAULT_BORDER_PADDING);

        if ($allIdentical) {
            // Einheitlicher Border
            $width = $this->twipsToCm($borders['top']['size']);
            $style = $this->convertWordStyleToCss($borders['top']['style']);
            $color = '#' . $borders['top']['color'];

            return sprintf('border: %scm %s %s; %s', $width, $style, $color, $padding);
        }

        // Individuelle Borders
        $styles = [];
        foreach ($borders as $side => $border) {
            if ($border['size'] !== null && $border['size'] !== '') {
                $width    = $this->twipsToCm($border['size']);
                $style    = $this->convertWordStyleToCss($border['style']);
                $color    = '#' . $border['color'];
                $styles[] = sprintf('border-%s: %scm %s %s;', $side, $width, $style, $color);
            }
        }

        if (!empty($styles)) {
            $styles[] = $padding;
            return implode(' ', $styles);
        }

        return null;
    }

    /**
     * Prüft ob alle vier Borders identisch sind.
     */
    private function areAllBordersIdentical(array $borders): bool
    {
        $first = $borders['top'];

        foreach (['left', 'right', 'bottom'] as $side) {
            if ($borders[$side]['size'] !== $first['size'] ||
                $borders[$side]['color'] !== $first['color'] ||
                $borders[$side]['style'] !== $first['style']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Konvertiert Word Border-Styles zu CSS.
     */
    private function convertWordStyleToCss(?string $wordStyle): string
    {
        $mapping = [
            'single' => 'solid',
            'double' => 'double',
            'dotted' => 'dotted',
            'dashed' => 'dashed',
            'none'   => 'none',
        ];

        return $mapping[$wordStyle] ?? 'solid';
    }

    public function getPriority(): int
    {
        return 15; // Zwischen Text und Liste
    }
}
