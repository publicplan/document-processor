<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use PhpOffice\PhpWord\Element\Cell as DocCell;
use PhpOffice\PhpWord\Element\Table as DocTable;
use PhpOffice\PhpWord\Element\TextBreak as DocBreak;
use PhpOffice\PhpWord\Element\TextRun as DocTextRun;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Table as TableStyle;
use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Model\ParserError;

/**
 * Konvertiert Tabellen-Elemente in HTML.
 */
class TableElementConverter implements ElementConverterInterface
{
    /** @var string[] */
    private const BORDER_SIDES = ['top', 'right', 'bottom', 'left'];

    public function supports(object $element): bool
    {
        return $element instanceof DocTable;
    }

    public function convert(object $element, ConversionContext $context): string
    {
        /** @var DocTable $element */
        $rows             = $element->getRows();
        $totalRows        = count($rows);
        $totalColumns     = $this->countTableColumns($rows);
        $tableStyle       = $this->resolveTableStyle($element->getStyle());
        $tableBorderStyle = $tableStyle !== null ? $this->extractTableBorderContext($tableStyle) : null;

        $tableStyles = [];
        if ($this->hasAnyTableBorder($tableBorderStyle)) {
            $tableStyles[] = 'border-collapse: collapse;';
            $outerBorderStyles = $this->buildBorderCss($tableBorderStyle['outer']);
            if ($outerBorderStyles !== '') {
                $tableStyles[] = $outerBorderStyles;
            }
        }

        $tableStyleAttr = !empty($tableStyles) ? sprintf(' style="%s"', implode(' ', $tableStyles)) : '';
        $text           = sprintf('<table class="table jrvTable"%s>%s', $tableStyleAttr, PHP_EOL);

        foreach ($rows as $rowIndex => $row) {
            $text .= '    <tr>' . PHP_EOL;
            $columnIndex = 0;

            foreach ($row->getCells() as $cell) {
                $text .= $this->convertCell($cell, $context, $tableBorderStyle, $rowIndex, $totalRows, $columnIndex, $totalColumns);
                $columnIndex += max(1, (int)($cell->getStyle()?->getGridSpan() ?? 1));
            }

            $text .= '    </tr>' . PHP_EOL;
        }

        $text .= '</table>' . PHP_EOL . PHP_EOL;

        return $text;
    }

    /**
     * Konvertiert eine Tabellenzelle in HTML.
     */
    private function convertCell(
        DocCell           $cell,
        ConversionContext $context,
        ?array            $tableBorderStyle,
        int               $rowIndex,
        int               $totalRows,
        int               $columnIndex,
        int               $totalColumns
    ): string
    {
        $cellStyle = $cell->getStyle();
        $colspan   = $cellStyle?->getGridSpan() ?? 1;
        $bgColor   = $cellStyle?->getBgColor() ?? '';
        $styles    = [];

        if ($bgColor) {
            $styles[] = 'background-color: #' . $bgColor . ';';
        }

        $effectiveBorders = $this->resolveCellBorders(
            $cell,
            $tableBorderStyle,
            $rowIndex,
            $totalRows,
            $columnIndex,
            max(0, $totalColumns - 1)
        );
        $borderStyles = $this->buildBorderCss($effectiveBorders);
        if ($borderStyles !== '') {
            $styles[] = $borderStyles;
        }

        $text = sprintf(
            '        <td%s%s>%s',
            $colspan > 1 ? ' colspan="' . $colspan . '"' : '',
            !empty($styles) ? ' style="' . implode(' ', $styles) . '"' : '',
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
            return '            ' . $this->convertBreakElement($cellElement, $context);
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

    private function countTableColumns(array $rows): int
    {
        $maxColumns = 0;
        foreach ($rows as $row) {
            $rowColumns = 0;
            foreach ($row->getCells() as $cell) {
                $rowColumns += max(1, (int)($cell->getStyle()?->getGridSpan() ?? 1));
            }
            $maxColumns = max($maxColumns, $rowColumns);
        }

        return $maxColumns;
    }

    private function resolveTableStyle(null|string|TableStyle $tableStyle): ?TableStyle
    {
        if ($tableStyle instanceof TableStyle) {
            return $tableStyle;
        }

        if (is_string($tableStyle)) {
            $resolved = Style::getStyle($tableStyle);
            if ($resolved instanceof TableStyle) {
                return $resolved;
            }
        }

        return null;
    }

    private function hasAnyTableBorder(?array $tableBorderStyle): bool
    {
        if ($tableBorderStyle === null) {
            return false;
        }

        foreach (self::BORDER_SIDES as $side) {
            if ($this->isBorderDefined($tableBorderStyle['outer'][$side] ?? null)) {
                return true;
            }
        }

        return $this->isBorderDefined($tableBorderStyle['inside']['horizontal'] ?? null)
            || $this->isBorderDefined($tableBorderStyle['inside']['vertical'] ?? null);
    }

    private function extractTableBorderContext(TableStyle $tableStyle): array
    {
        return [
            'outer' => [
                'top' => $this->readBorderFromStyle($tableStyle, 'top'),
                'right' => $this->readBorderFromStyle($tableStyle, 'right'),
                'bottom' => $this->readBorderFromStyle($tableStyle, 'bottom'),
                'left' => $this->readBorderFromStyle($tableStyle, 'left'),
            ],
            'inside' => [
                'horizontal' => [
                    'size' => $tableStyle->getBorderInsideHSize(),
                    'color' => $tableStyle->getBorderInsideHColor(),
                    'style' => $this->resolveInsideBorderStyle($tableStyle, true),
                ],
                'vertical' => [
                    'size' => $tableStyle->getBorderInsideVSize(),
                    'color' => $tableStyle->getBorderInsideVColor(),
                    'style' => $this->resolveInsideBorderStyle($tableStyle, false),
                ],
            ],
        ];
    }

    private function resolveInsideBorderStyle(TableStyle $tableStyle, bool $horizontal): string
    {
        $sides = $horizontal ? ['top', 'bottom'] : ['left', 'right'];
        foreach ($sides as $side) {
            $style = $this->readBorderFromStyle($tableStyle, $side)['style'] ?? null;
            if (is_string($style) && $style !== '') {
                return $style;
            }
        }

        return 'single';
    }

    private function resolveCellBorders(
        DocCell $cell,
        ?array $tableBorderStyle,
        int $rowIndex,
        int $totalRows,
        int $columnIndex,
        int $lastColumnIndex
    ): array {
        $cellStyle = $cell->getStyle();
        $colspan   = max(1, (int)($cellStyle?->getGridSpan() ?? 1));
        $cellEnd   = $columnIndex + $colspan - 1;

        $resolvedBorders = [];
        foreach (self::BORDER_SIDES as $side) {
            $cellBorder = $cellStyle !== null ? $this->readBorderFromStyle($cellStyle, $side) : null;
            if ($this->isBorderDefined($cellBorder)) {
                $resolvedBorders[$side] = $cellBorder;
                continue;
            }

            $resolvedBorders[$side] = $this->resolveTableFallbackBorder(
                $tableBorderStyle,
                $side,
                $rowIndex,
                $totalRows,
                $columnIndex,
                $cellEnd,
                $lastColumnIndex
            );
        }

        return $resolvedBorders;
    }

    private function resolveTableFallbackBorder(
        ?array $tableBorderStyle,
        string $side,
        int $rowIndex,
        int $totalRows,
        int $cellStartColumn,
        int $cellEndColumn,
        int $lastColumnIndex
    ): ?array {
        if ($tableBorderStyle === null) {
            return null;
        }

        $isFirstRow = $rowIndex === 0;
        $isLastRow  = $rowIndex === $totalRows - 1;
        $isFirstCol = $cellStartColumn === 0;
        $isLastCol  = $cellEndColumn === $lastColumnIndex;

        return match ($side) {
            'top' => $isFirstRow ? ($tableBorderStyle['outer']['top'] ?? null) : ($tableBorderStyle['inside']['horizontal'] ?? null),
            'bottom' => $isLastRow ? ($tableBorderStyle['outer']['bottom'] ?? null) : ($tableBorderStyle['inside']['horizontal'] ?? null),
            'left' => $isFirstCol ? ($tableBorderStyle['outer']['left'] ?? null) : ($tableBorderStyle['inside']['vertical'] ?? null),
            'right' => $isLastCol ? ($tableBorderStyle['outer']['right'] ?? null) : ($tableBorderStyle['inside']['vertical'] ?? null),
            default => null,
        };
    }

    private function readBorderFromStyle(object $style, string $side): ?array
    {
        $suffix = ucfirst($side);
        $sizeGetter  = 'getBorder' . $suffix . 'Size';
        $colorGetter = 'getBorder' . $suffix . 'Color';
        $styleGetter = 'getBorder' . $suffix . 'Style';

        if (!method_exists($style, $sizeGetter) || !method_exists($style, $colorGetter) || !method_exists($style, $styleGetter)) {
            return null;
        }

        return [
            'size' => $style->{$sizeGetter}(),
            'color' => $style->{$colorGetter}(),
            'style' => $style->{$styleGetter}(),
        ];
    }

    private function isBorderDefined(?array $border): bool
    {
        if ($border === null) {
            return false;
        }

        $size = $border['size'] ?? null;

        if ($size === null || $size === '') {
            return false;
        }

        if (is_numeric($size)) {
            return (float)$size > 0.0;
        }

        return true;
    }

    private function buildBorderCss(array $borders): string
    {
        $normalizedBorders = [];
        foreach (self::BORDER_SIDES as $side) {
            $border = $borders[$side] ?? null;
            if ($this->isBorderDefined($border)) {
                $normalizedBorders[$side] = $this->normalizeBorderForCss($border);
            }
        }

        if (empty($normalizedBorders)) {
            return '';
        }

        if (count($normalizedBorders) === 4 && $this->allBordersIdentical($normalizedBorders)) {
            $border = $normalizedBorders['top'];
            return sprintf(
                'border: %scm %s%s;',
                $this->formatLength($border['widthCm']),
                $border['style'],
                $border['color'] !== null ? ' ' . $border['color'] : ''
            );
        }

        $styles = [];
        foreach (self::BORDER_SIDES as $side) {
            if (!isset($normalizedBorders[$side])) {
                continue;
            }

            $border   = $normalizedBorders[$side];
            $styles[] = sprintf(
                'border-%s: %scm %s%s;',
                $side,
                $this->formatLength($border['widthCm']),
                $border['style'],
                $border['color'] !== null ? ' ' . $border['color'] : ''
            );
        }

        return implode(' ', $styles);
    }

    private function normalizeBorderForCss(array $border): array
    {
        $widthCm = BorderStyleHelper::normalizeBorderWidthCm(
            $this->convertBorderWidthToCm((float)($border['size'] ?? 0))
        );

        return [
            'widthCm' => $widthCm,
            'style' => $this->mapWordBorderStyleToCss($border['style'] ?? null),
            'color' => BorderStyleHelper::formatCssHexColor($border['color'] ?? null),
        ];
    }

    private function allBordersIdentical(array $normalizedBorders): bool
    {
        $first = $normalizedBorders['top'] ?? null;
        if ($first === null) {
            return false;
        }

        foreach (['right', 'bottom', 'left'] as $side) {
            if (!isset($normalizedBorders[$side])) {
                return false;
            }

            $candidate = $normalizedBorders[$side];
            if ($candidate['widthCm'] !== $first['widthCm']
                || $candidate['style'] !== $first['style']
                || $candidate['color'] !== $first['color']) {
                return false;
            }
        }

        return true;
    }

    private function convertBorderWidthToCm(float $size): float
    {
        // OOXML table border sizes are stored in eighths of a point.
        return $size * 2.54 / 576;
    }

    private function mapWordBorderStyleToCss(?string $wordStyle): string
    {
        return match ($wordStyle) {
            'double' => 'double',
            'dotted' => 'dotted',
            'dashed' => 'dashed',
            'none',
            'nil' => 'none',
            default => 'solid',
        };
    }

    private function formatLength(float $length): string
    {
        return rtrim(rtrim(sprintf('%.4f', $length), '0'), '.');
    }

    /**
     * Konvertiert einen Break in HTML.
     */
    private function convertBreakElement(DocBreak $element, ConversionContext $context): string
    {
        if ($element->getFontStyle() instanceof Font && $element->getFontStyle()->isStrikethrough()) {
            return DeletedContentHelper::renderDeletedBreak($context);
        }

        return '<br>' . PHP_EOL;
    }

    public function getPriority(): int
    {
        return 30; // Höchste Priorität unter den komplexen Elementen
    }
}
