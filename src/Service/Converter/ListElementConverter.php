<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use PhpOffice\PhpWord\Element\Link as DocLink;
use PhpOffice\PhpWord\Element\ListItemRun as DocList;
use PhpOffice\PhpWord\Element\Text as DocText;
use PhpOffice\PhpWord\Element\TextBreak as DocBreak;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Numbering;
use Publicplan\DocumentProcessor\Enum\ListConfigType;
use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Model\ListConfig;
use Publicplan\DocumentProcessor\Model\ParserError;

/**
 * Konvertiert Listen-Elemente in HTML.
 */
class ListElementConverter implements ElementConverterInterface
{
    public function supports(object $element): bool
    {
        return $element instanceof DocList;
    }

    public function convert(object $element, ConversionContext $context): string
    {
        /** @var DocList $element */
        $text = '';

        foreach ($element->getElements() as $textElement) {
            $elementText = $this->convertSubElement($textElement);

            if ($elementText !== null) {
                $text .= $elementText;
            } else {
                $this->addUnhandledElementMessage($textElement, $element, $context);
            }
        }

        if ($text === '') {
            return '';
        }

        // Warnung über Listen-Parsing-Probleme (nur einmal)
        $context->addMessage(
            ParserError::create(
                ParserError::LIST_INFO,
                ParserError::SEVERITY_INFO,
                'Das Dokument enthält Listen, welche in der aktuellen Version des Parsers nicht in jedem Fall korrekt interpretiert werden können. Die hier dargestellte Abfolge, Nummerierung oder Hierarchie von Listen kann von der Vorlage abweichen und ist mit dieser abzugleichen. Dies gilt insbesondere bei der Benutzung der Exportfunktionen.'
            ),
            true
        );

        $text = $this->applyListStyles($element, $text);

        return sprintf("    <li>%s</li>%s", $text, PHP_EOL);
    }

    /**
     * Konvertiert ein Listen-Element mit explizitem bottom spacing.
     */
    public function convertWithSpacing(DocList $element, ConversionContext $context, float $bottomSpacingCm): string
    {
        $text = '';

        foreach ($element->getElements() as $textElement) {
            $elementText = $this->convertSubElement($textElement);

            if ($elementText !== null) {
                $text .= $elementText;
            } else {
                $this->addUnhandledElementMessage($textElement, $element, $context);
            }
        }

        if ($text === '') {
            return '';
        }

        // Warnung über Listen-Parsing-Probleme (nur einmal)
        $context->addMessage(
            ParserError::create(
                ParserError::LIST_INFO,
                ParserError::SEVERITY_INFO,
                'Das Dokument enthält Listen, welche in der aktuellen Version des Parsers nicht in jedem Fall korrekt interpretiert werden können. Die hier dargestellte Abfolge, Nummerierung oder Hierarchie von Listen kann von der Vorlage abweichen und ist mit dieser abzugleichen. Dies gilt insbesondere bei der Benutzung der Exportfunktionen.'
            ),
            true
        );

        $text = $this->applyListStyles($element, $text);

        // Bottom spacing als style auf das li-Element anwenden
        if ($bottomSpacingCm > 0) {
            return sprintf('    <li style="margin-bottom: %scm;">%s</li>%s', $bottomSpacingCm, $text, PHP_EOL);
        }

        return sprintf("    <li>%s</li>%s", $text, PHP_EOL);
    }

    /**
     * Konvertiert ein Unter-Element der Liste.
     */
    private function convertSubElement(object $textElement): ?string
    {
        if ($textElement instanceof DocBreak) {
            return $this->convertBreakElement($textElement);
        }

        if ($textElement instanceof DocText) {
            return $this->convertTextElement($textElement);
        }

        if ($textElement instanceof DocLink) {
            return $this->convertLinkElement($textElement);
        }

        return null;
    }

    /**
     * Konvertiert einen Break in HTML.
     */
    private function convertBreakElement(DocBreak $element): string
    {
        if ($element->getFontStyle()?->isStrikethrough()) {
            return '';
        }

        return '<br>' . PHP_EOL;
    }

    /**
     * Konvertiert Text in HTML mit Formatierung.
     */
    private function convertTextElement(DocText $element): string
    {
        $text = $element->getText() ?? '';

        if ($text === '' || $element->getFontStyle()?->isStrikethrough()) {
            return '##deleted##';
        }

        return new TextElementConverter()->convert($element, new ConversionContext());
    }

    /**
     * Konvertiert einen Link in HTML.
     */
    private function convertLinkElement(DocLink $element): string
    {
        if ($element->getFontStyle()?->isStrikethrough()) {
            return '##deleted##';
        }

        /** @noinspection HtmlUnknownTarget */
        return sprintf(
            '<a href="%s">%s</a>',
            htmlspecialchars($element->getSource(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($element->getText(), ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Wendet Listen-Styles auf das li-Element an.
     */
    private function applyListStyles(DocList $element, string $text): string
    {
        $blockStyles = [];
        $pStyle      = $element->getParagraphStyle();

        if ($pStyle->getAlignment() === Jc::CENTER) {
            $blockStyles[] = 'text-align: center;';
        }

        if ($pStyle->getAlignment() === Jc::BOTH) {
            $blockStyles[] = 'text-align: justify;';
        }

        if (!empty($blockStyles)) {
            $text = str_replace(
                '<li>',
                sprintf('<li style="%s">', implode(' ', $blockStyles)),
                $text
            );
        }

        return $text;
    }

    /**
     * Fügt eine Fehlermeldung für nicht unterstützte Elemente hinzu.
     */
    private function addUnhandledElementMessage(
        object            $textElement,
        DocList           $parentElement,
        ConversionContext $context
    ): void
    {
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

    /**
     * Erstellt eine ListConfig basierend auf dem List-Format.
     */
    public function createListConfig(DocList $element, float $bottomSpacingCm = 0.0): ListConfig
    {
        $styleName = $element->getStyle()?->getNumStyle();
        /** @var Numbering|null $numStyleObject */
        $numStyleObject = Style::getStyle($styleName);
        $numLevels      = $numStyleObject?->getLevels();
        $firstItem      = $numLevels ? reset($numLevels) : null;

        $listFormat = $firstItem?->getFormat();

        return match ($listFormat) {
            ListConfigType::Decimal->value => new ListConfig(tag: 'ol', type: null, bottomSpacingCm: $bottomSpacingCm),
            ListConfigType::UpperRoman->value => new ListConfig(tag: 'ol', type: 'I', bottomSpacingCm: $bottomSpacingCm),
            ListConfigType::LowerRoman->value => new ListConfig(tag: 'ol', type: 'i', bottomSpacingCm: $bottomSpacingCm),
            ListConfigType::UpperLetter->value => new ListConfig(tag: 'ol', type: 'A', bottomSpacingCm: $bottomSpacingCm),
            ListConfigType::LowerLetter->value => new ListConfig(tag: 'ol', type: 'a', bottomSpacingCm: $bottomSpacingCm),
            default => new ListConfig(tag: 'ul', type: null, bottomSpacingCm: $bottomSpacingCm)
        };
    }

    public function getPriority(): int
    {
        return 20; // Höhere Priorität als Text
    }
}
