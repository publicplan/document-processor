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
        $renderData = $this->convertSubElements($element, $context);
        $text       = $renderData['html'];

        if ($text === '') {
            // Leere Absätze als <p>&nbsp;</p> mit Styles ausgeben (wie in Word)
            return $this->wrapWithParagraphStyles($element, '&nbsp;');
        }

        return $this->wrapWithParagraphStyles($element, $text, $renderData['attributes']);
    }

    /**
     * Konvertiert alle Unter-Elemente des TextRuns und gruppiert sie nach Schriftgröße.
     * Aufeinanderfolgende Elemente mit gleicher Schriftgröße werden zusammengefasst.
     */
    private function convertSubElements(DocTextRun $element, ConversionContext $context): array
    {
        $elementConverter = new ElementConverterRegistry();
        $elementConverter->registerDefaultConverters();

        // Phase 1: Sammle alle Elemente mit ihrer aufgelösten Schriftgröße
        $annotatedElements = [];
        foreach ($element->getElements() as $textElement) {
            $elementText = $elementConverter->convert($textElement, $context);

            if ($elementText !== null) {
                // Bestimme die Schriftgröße für dieses Element
                $fontSize = $this->extractFontSize($textElement, $element);
                $annotatedElements[] = [
                    'html'     => $elementText,
                    'fontSize' => $fontSize,
                ];
            } else {
                $this->handleInvalidElement($textElement, $element, $context);
            }
        }

        if (empty($annotatedElements)) {
            return [
                'html'       => '',
                'attributes' => [],
            ];
        }

        // Phase 2: Gruppiere aufeinanderfolgende Elemente mit gleicher Schriftgröße
        $groups = $this->groupElementsByFontSize($annotatedElements);

        if (count($groups) === 1) {
            return $this->renderSingleGroupForParagraph($groups[0], $context->getDefaultFontSize());
        }

        // Phase 3: Rendere Gruppen, wrappen nur wenn Schriftgröße vom Default abweicht
        $text = '';
        foreach ($groups as $group) {
            $text .= $this->renderFontGroup($group, $context->getDefaultFontSize());
        }

        return [
            'html'       => $text,
            'attributes' => [],
        ];
    }

    /**
     * Extrahiert die Schriftgröße aus einem Element.
     * Bevorzugt explizite Font-Größen, fällt sonst auf Paragraph-Style des TextRuns zurück.
     */
    private function extractFontSize(object $textElement, DocTextRun $parentRun): ?float
    {
        // Für Text und Link: Versuche zuerst, direkte Font-Größe zu resolven
        // Aber nutze das Paragraph-Style des TextRuns, nicht das eingebettete Paragraph der Font
        if ($textElement instanceof \PhpOffice\PhpWord\Element\Text) {
            $textFont = $textElement->getFontStyle();
            // Nur wenn Font direktes Size hat, nutze es
            if ($textFont instanceof \PhpOffice\PhpWord\Style\Font && $textFont->getSize() !== null) {
                return (float)$textFont->getSize();
            }
            // Sonst: nutze Paragraph-Style des TextRuns
            return FontScaleHelper::resolveFontSize(null, $parentRun->getParagraphStyle());
        }

        // Link-Elemente
        if ($textElement instanceof \PhpOffice\PhpWord\Element\Link) {
            $linkFont = $textElement->getFontStyle();
            // Nur wenn Font direktes Size hat, nutze es
            if ($linkFont instanceof \PhpOffice\PhpWord\Style\Font && $linkFont->getSize() !== null) {
                return (float)$linkFont->getSize();
            }
            // Sonst: nutze Paragraph-Style des TextRuns
            return FontScaleHelper::resolveFontSize(null, $parentRun->getParagraphStyle());
        }

        // Andere Element-Typen: nutze Paragraph-Stil
        return FontScaleHelper::resolveFontSize(null, $parentRun->getParagraphStyle());
    }

    /**
     * Gruppiert annotierte Elemente: aufeinanderfolgende mit gleicher Schriftgröße werden zusammengefasst.
     */
    private function groupElementsByFontSize(array $annotatedElements): array
    {
        $groups = [];
        $currentGroup = null;

        foreach ($annotatedElements as $item) {
            if ($currentGroup === null || $currentGroup['fontSize'] !== $item['fontSize']) {
                // Neue Gruppe starten
                if ($currentGroup !== null) {
                    $groups[] = $currentGroup;
                }
                $currentGroup = [
                    'fontSize' => $item['fontSize'],
                    'htmlParts' => [$item['html']],
                ];
            } else {
                // Zur aktuellen Gruppe hinzufügen
                $currentGroup['htmlParts'][] = $item['html'];
            }
        }

        if ($currentGroup !== null) {
            $groups[] = $currentGroup;
        }

        return $groups;
    }

    /**
     * Rendert eine Gruppe von Elementen mit gleicher Schriftgröße.
     * Wrappet nur mit Span, wenn Schriftgröße vom Default abweicht.
     */
    private function renderFontGroup(array $group, ?float $defaultFontSize): string
    {
        $html = implode('', $group['htmlParts']);
        $fontSize = $group['fontSize'];

        // Erstelle Scale-Attribut - nur wenn Schriftgröße vom Default abweicht
        $scaleAttr = FontScaleHelper::createScaleAttribute($fontSize, $defaultFontSize);

        if ($scaleAttr === null) {
            // Kein Override - plain HTML ohne Span
            return $html;
        }

        // Override erkannt - wrappen in Span mit Attribut
        return sprintf('<span %s>%s</span>', $scaleAttr, $html);
    }

    /**
     * Rendert eine einzelne Font-Gruppe direkt für einen Absatz.
     * Wenn die Gruppe skaliert ist, wird das Attribut auf <p> gezogen statt einen äußeren Span zu erzeugen.
     */
    private function renderSingleGroupForParagraph(array $group, ?float $defaultFontSize): array
    {
        $html      = implode('', $group['htmlParts']);
        $scaleAttr = FontScaleHelper::createScaleAttribute($group['fontSize'], $defaultFontSize);

        return [
            'html'       => $html,
            'attributes' => $scaleAttr !== null ? [$scaleAttr] : [],
        ];
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
    private function wrapWithParagraphStyles(DocTextRun $element, string $text, array $additionalAttributes = []): string
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
        $textAlign = $this->mapParagraphAlignmentToCss($pStyle->getAlignment());
        if ($textAlign !== null) {
            $blockStyles[] = sprintf('text-align: %s;', $textAlign);
        }

        // Paragraph-Abstand
        $spaceAfter    = $pStyle->getSpaceAfter();
        $blockStyles[] = sprintf('margin-bottom: %scm;', $this->twipsToCm($spaceAfter));

        $indentation = ParagraphIndentHelper::resolveEffectiveIndentation($pStyle);
        $indentLeft  = $indentation['indentLeft'];
        $hanging     = $indentation['hanging'];
        $firstLine   = $indentation['firstLine'];

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
        } elseif ($firstLine) {
            $blockStyles[] = sprintf('text-indent: %scm;', $this->twipsToCm($firstLine));
        }

        $result = sprintf(
            '<p%s%s%s>%s</p>%s',
            !empty($blockClasses) ? sprintf(' class="%s"', implode(' ', $blockClasses)) : '',
            !empty($blockStyles) ? sprintf(' style="%s"', implode(' ', $blockStyles)) : '',
            !empty($additionalAttributes) ? ' ' . implode(' ', $additionalAttributes) : '',
            trim($text),
            PHP_EOL
        );

        // Entferne überflüssige aufeinanderfolgende Tags
        return $this->cleanupConsecutiveTags($result);
    }

    /**
     * Mappt Word-Paragraph-Ausrichtung auf CSS text-align.
     */
    private function mapParagraphAlignmentToCss(?string $alignment): ?string
    {
        if ($alignment === Jc::CENTER) {
            return 'center';
        }

        if ($alignment === Jc::BOTH) {
            return 'justify';
        }

        if ($alignment === 'right' || $alignment === 'end') {
            return 'right';
        }

        return null;
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
            $width = BorderStyleHelper::normalizeBorderWidthCm($this->twipsToCm($borders['top']['size']));
            $style = $this->convertWordStyleToCss($borders['top']['style']);
            $color = BorderStyleHelper::formatCssHexColor($borders['top']['color']);

            return sprintf('border: %scm %s%s; %s', $width, $style, $color !== null ? ' ' . $color : '', $padding);
        }

        // Individuelle Borders
        $styles = [];
        foreach ($borders as $side => $border) {
            if ($border['size'] !== null && $border['size'] !== '') {
                $width    = BorderStyleHelper::normalizeBorderWidthCm($this->twipsToCm($border['size']));
                $style    = $this->convertWordStyleToCss($border['style']);
                $color    = BorderStyleHelper::formatCssHexColor($border['color']);
                $styles[] = sprintf(
                    'border-%s: %scm %s%s;',
                    $side,
                    $width,
                    $style,
                    $color !== null ? ' ' . $color : ''
                );
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
