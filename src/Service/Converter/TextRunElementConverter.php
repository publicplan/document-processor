<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Model\ParserError;
use PhpOffice\PhpWord\Element\TextRun as DocTextRun;
use PhpOffice\PhpWord\Element\FormField;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Konvertiert TextRun-Elemente (Absätze mit Formatierung) in HTML.
 */
class TextRunElementConverter implements ElementConverterInterface
{
    public function supports(object $element): bool
    {
        return $element instanceof DocTextRun;
    }

    public function convert(object $element, ConversionContext $context): string
    {
        /** @var DocTextRun $element */
        $text = $this->convertSubElements($element, $context);

        if ($text === '') {
            return '';
        }

        return $this->wrapWithParagraphStyles($element, $text);
    }

    /**
     * Konvertiert alle Unter-Elemente des TextRuns.
     */
    private function convertSubElements(DocTextRun $element, ConversionContext $context): string
    {
        $text = '';
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
        object $textElement,
        DocTextRun $parentElement,
        ConversionContext $context
    ): void {
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
     * Wendet Paragraph-Styles an und wrappend in <p>.
     */
    private function wrapWithParagraphStyles(DocTextRun $element, string $text): string
    {
        $blockClasses = [];
        $blockStyles = [];
        $pStyle = $element->getParagraphStyle();

        // Ausrichtung
        if ($pStyle->getAlignment() === Jc::CENTER) {
            $blockStyles[] = 'text-align: center;';
        }

        if ($pStyle->getAlignment() === Jc::BOTH) {
            $blockStyles[] = 'text-align: justify;';
        }

        // Paragraph-Abstand
        $spaceAfter = $pStyle->getSpaceAfter();
        $blockStyles[] = sprintf('margin-bottom: %scm;', $this->twipsToCm($spaceAfter));

        $indentLeft = $pStyle->getIndentLeft();
        $hanging = $pStyle->getHanging();

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
        
        $twips = (float) $twips;
        
        return round($twips / 1440 * 2.54, 2);
    }

    public function getPriority(): int
    {
        return 15; // Zwischen Text und Liste
    }
}
