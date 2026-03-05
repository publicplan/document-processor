<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Model\ParserError;
use PhpOffice\PhpWord\Element\Table as DocTable;
use PhpOffice\PhpWord\Element\TextBreak as DocBreak;
use PhpOffice\PhpWord\Element\TextRun as DocTextRun;

/**
 * Konvertiert Tabellen-Elemente in HTML.
 */
class TableElementConverter implements ElementConverterInterface
{
    public function supports(object $element): bool
    {
        return $element instanceof DocTable;
    }

    public function convert(object $element, ConversionContext $context): string
    {
        /** @var DocTable $element */
        $text = '<table class="table jrvTable">' . PHP_EOL;

        foreach ($element->getRows() as $row) {
            $text .= '    <tr>' . PHP_EOL;

            foreach ($row->getCells() as $cell) {
                $text .= $this->convertCell($cell, $context);
            }

            $text .= '    </tr>' . PHP_EOL;
        }

        $text .= '</table>' . PHP_EOL . PHP_EOL;

        return $text;
    }

    /**
     * Konvertiert eine Tabellenzelle in HTML.
     */
    private function convertCell($cell, ConversionContext $context): string
    {
        $colspan = $cell->getStyle()?->getGridSpan() ?? 1;
        $bgColor = $cell->getStyle()?->getBgColor() ?? '';

        $text = sprintf(
            '        <td%s%s>%s',
            $colspan > 1 ? ' colspan="' . $colspan . '"' : '',
            $bgColor ? ' style="background-color: #' . $bgColor . '"' : '',
            PHP_EOL
        );

        foreach ($cell->getElements() as $cellElement) {
            $text .= $this->convertCellElement($cellElement, $context);
        }

        $text .= '        </td>' . PHP_EOL;

        return $text;
    }

    /**
     * Konvertiert ein Element innerhalb einer Tabellenzelle.
     */
    private function convertCellElement(object $cellElement, ConversionContext $context): string
    {
        if ($cellElement instanceof DocBreak) {
            return '            ' . $this->convertBreakElement($cellElement);
        }

        if ($cellElement instanceof DocTextRun) {
            $converter = new TextRunElementConverter();
            return '            ' . $converter->convert($cellElement, $context);
        }

        // Nicht unterstütztes Element
        $context->addMessage(
            ParserError::create(
                ParserError::CONTAINS_UNHANDLED_ELEMENTS,
                ParserError::SEVERITY_ERROR,
                sprintf('Nicht unterstütztes Element in Tabellenzelle: %s)', get_class($cellElement))
            )
        );

        return '';
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

    public function getPriority(): int
    {
        return 30; // Höchste Priorität unter den komplexen Elementen
    }
}
