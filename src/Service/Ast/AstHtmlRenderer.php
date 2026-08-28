<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Ast;

use PhpOffice\PhpWord\SimpleType\Jc;
use Publicplan\DocumentProcessor\Ast\Metadata\TrackChangeType;
use Publicplan\DocumentProcessor\Ast\Node\AstNode;
use Publicplan\DocumentProcessor\Ast\Node\BorderGroupNode;
use Publicplan\DocumentProcessor\Ast\Node\BreakNode;
use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\FieldTextNode;
use Publicplan\DocumentProcessor\Ast\Node\FormatNode;
use Publicplan\DocumentProcessor\Ast\Node\LinkNode;
use Publicplan\DocumentProcessor\Ast\Node\ListItemNode;
use Publicplan\DocumentProcessor\Ast\Node\ListNode;
use Publicplan\DocumentProcessor\Ast\Node\PageBreakNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;
use Publicplan\DocumentProcessor\Ast\Node\RevisionNode;
use Publicplan\DocumentProcessor\Ast\Node\ScaleNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\TableCellNode;
use Publicplan\DocumentProcessor\Ast\Node\TableNode;
use Publicplan\DocumentProcessor\Ast\Node\TableRowNode;
use Publicplan\DocumentProcessor\Ast\Node\TabNode;
use Publicplan\DocumentProcessor\Ast\Node\TextBoxNode;
use Publicplan\DocumentProcessor\Ast\Node\TextNode;
use Publicplan\DocumentProcessor\Model\ListConfig;
use Publicplan\DocumentProcessor\Service\Converter\BorderStyleHelper;

final class AstHtmlRenderer
{
    /** @var string[] */
    private const BORDER_SIDES = ['top', 'right', 'bottom', 'left'];

    public function render(DocumentNode $document): string
    {
        $html = '';

        foreach ($document->getSections() as $section) {
            if ($section instanceof SectionNode) {
                $html .= $this->renderSection($section);
            }
        }

        return $html;
    }

    private function renderSection(SectionNode $section): string
    {
        $html = '';

        foreach ($section->getParagraphs() as $node) {
            if ($node instanceof AstNode) {
                $html .= $this->renderBlockNode($node, false);
            }
        }

        return $html;
    }

    private function renderBlockNode(AstNode $node, bool $insideBorderGroup): string
    {
        if ($node instanceof ParagraphNode) {
            return $this->renderParagraph($node, $insideBorderGroup);
        }

        if ($node instanceof ListNode) {
            return $this->renderList($node);
        }

        if ($node instanceof BorderGroupNode) {
            return $this->renderBorderGroup($node);
        }

        if ($node instanceof TableNode) {
            return $this->renderTable($node);
        }

        if ($node instanceof TextBoxNode) {
            return $this->renderTextBox($node);
        }

        if ($node instanceof BreakNode) {
            return $this->renderBreak($node);
        }

        if ($node instanceof PageBreakNode) {
            return '<div class="page-break">Seitenwechsel</div>';
        }

        if ($node instanceof TextNode) {
            return $this->renderTextNode($node);
        }

        if ($node instanceof LinkNode) {
            return $this->renderLinkNode($node);
        }

        if ($node instanceof FieldTextNode) {
            return $node->getFieldResult() ?? $node->getFieldCode();
        }

        return '';
    }

    private function renderParagraph(ParagraphNode $paragraph, bool $insideBorderGroup): string
    {
        $text = $this->renderInlineNodes($paragraph->getChildren());
        if ($text === '') {
            $text = '&nbsp;';
        }

        $styles = [];
        
        $alignment = $this->mapParagraphAlignmentToCss($paragraph->getAlignment());
        if ($alignment !== null) {
            $styles[] = sprintf('text-align: %s;', $alignment);
        }

        if ($paragraph->getSpacingBefore() !== null && $paragraph->getSpacingBefore() > 0) {
            $styles[] = sprintf('margin-top: %scm;', $paragraph->getSpacingBefore());
        }

        $spacingAfter = $paragraph->getSpacingAfter() ?? 0;
        $styles[] = sprintf('margin-bottom: %scm;', $spacingAfter);

        if ($paragraph->getLineHeight() !== null && $paragraph->getLineHeight() > 0) {
            $styles[] = sprintf('line-height: %s;', $paragraph->getLineHeight());
        }

        if ($paragraph->getIndentLeft() !== null && $paragraph->getIndentLeft() > 0) {
            $styles[] = sprintf('margin-left: %scm;', $paragraph->getIndentLeft());
        }

        if ($paragraph->getIndentRight() !== null && $paragraph->getIndentRight() > 0) {
            $styles[] = sprintf('margin-right: %scm;', $paragraph->getIndentRight());
        }

        if ($paragraph->getIndentFirstLine() !== null && $paragraph->getIndentFirstLine() !== 0) {
            $styles[] = sprintf('text-indent: %scm;', $paragraph->getIndentFirstLine());
        }

        $styleAttr = sprintf(' style="%s"', implode(' ', $styles));
        if ($insideBorderGroup) {
            $styleAttr = str_replace('style="', 'style=" ', $styleAttr);
        }

        return sprintf('<p%s>%s</p>%s', $styleAttr, trim($text), PHP_EOL);
    }

    private function renderList(ListNode $list): string
    {
        $items = array_values(array_filter($list->getItems(), static fn (mixed $item): bool => $item instanceof ListItemNode));
        if ($items === []) {
            return '';
        }

        /** @var ListItemNode $first */
        $first = $items[0];

        $tag = $this->listHintString($first, 'list_tag') ?? ($first->getNumFormat()->value === 'bullet' ? 'ul' : 'ol');
        $type = $this->listHintString($first, 'list_type');
        $start = $this->listHintInt($first, 'list_start') ?? 1;
        $sequenceKey = $this->listHintString($first, 'list_sequence_key') ?? '';
        $docxListId = $first->getRenderHints()->getHint('list_docx_id');

        $startOverride = $first->getStartNumeration();
        $config = new ListConfig(
            tag: $tag,
            type: $type,
            start: $start,
            sequenceKey: $sequenceKey,
            docxListId: $docxListId
        );

        $listStyles = [];
        $resolvedLayout = $list->getResolvedLayout();
        $spacingBefore = is_array($resolvedLayout) ? ($resolvedLayout['spacing']['before'] ?? null) : $list->getSpacingBefore();
        $spacingAfter = is_array($resolvedLayout) ? ($resolvedLayout['spacing']['after'] ?? null) : $list->getSpacingAfter();
        $indentLeft = is_array($resolvedLayout) ? ($resolvedLayout['indent']['left'] ?? null) : $list->getIndentLeft();
        if (is_numeric($spacingBefore) && (float)$spacingBefore > 0) {
            $listStyles[] = sprintf('margin-top: %scm;', $spacingBefore);
        }
        if (is_numeric($spacingAfter) && (float)$spacingAfter > 0) {
            $listStyles[] = sprintf('margin-bottom: %scm;', $spacingAfter);
        }
        if (is_numeric($indentLeft) && (float)$indentLeft > 0) {
            $listStyles[] = sprintf('margin-left: %scm;', $indentLeft);
        }

        $listStartTag = $config->renderStartTag($startOverride);
        if ($listStyles !== []) {
            $listStartTag = $this->mergeStyleAttribute($listStartTag, implode(' ', $listStyles));
        }

        $html = $listStartTag . PHP_EOL;
        foreach ($items as $item) {
            $html .= $this->renderListItem($item);
        }
        $html .= $config->renderEndTag() . PHP_EOL;

        return $html;
    }

    private function renderListItem(ListItemNode $item): string
    {
        [$inlineChildren, $nestedItems] = $this->splitListItemChildren($item);

        $content = $this->renderInlineNodes($inlineChildren);
        $nestedLists = $this->renderNestedLists($nestedItems);

        if ($content === '' && $nestedLists === '') {
            return '';
        }

        $styles = [];
        $resolvedLayout = $item->getResolvedLayout();
        $indentLeft = is_array($resolvedLayout) ? ($resolvedLayout['indent']['left'] ?? null) : $item->getIndentLeft();
        $spacingAfter = is_array($resolvedLayout) ? ($resolvedLayout['spacing']['after'] ?? null) : $item->getSpacingAfter();
        if (is_numeric($indentLeft) && (float)$indentLeft > 0) {
            $styles[] = sprintf('margin-left: %scm;', $indentLeft);
        }
        if (is_numeric($spacingAfter) && (float)$spacingAfter > 0) {
            $styles[] = sprintf('margin-bottom: %scm;', $spacingAfter);
        }

        $liHtml = '<li';
        if (!empty($styles)) {
            $liHtml .= sprintf(' style="%s"', implode(' ', $styles));
        }
        $liHtml .= '>';

        if ($nestedLists !== '') {
            return sprintf("%s%s%s%s</li>%s", $liHtml, $content, PHP_EOL, $nestedLists, PHP_EOL);
        }

        return sprintf("%s%s</li>%s", $liHtml, $content, PHP_EOL);
    }

    private function mergeStyleAttribute(string $tag, string $additionalStyles): string
    {
        $additionalStyles = trim($additionalStyles);
        if ($additionalStyles === '') {
            return $tag;
        }

        if (preg_match('/\sstyle="([^"]*)"/', $tag, $matches) === 1) {
            $existingStyles = trim($matches[1]);
            $mergedStyles = trim($existingStyles . ' ' . $additionalStyles);

            return preg_replace(
                '/\sstyle="[^"]*"/',
                sprintf(' style="%s"', $mergedStyles),
                $tag,
                1
            ) ?? $tag;
        }

        return preg_replace('/>$/', sprintf(' style="%s">', $additionalStyles), $tag, 1) ?? $tag;
    }

    private function renderBorderGroup(BorderGroupNode $group): string
    {
        $style = $this->buildBorderStyleFromGroup($group);
        if ($style === null) {
            $html = '';
            foreach ($group->getChildren() as $child) {
                if ($child instanceof AstNode) {
                    $html .= $this->renderBlockNode($child, false);
                }
            }

            return $html;
        }

        $html = sprintf('<div style="%s">%s', $style, PHP_EOL);
        foreach ($group->getChildren() as $child) {
            if ($child instanceof AstNode) {
                $html .= $this->renderBlockNode($child, true);
            }
        }
        $html .= '</div>' . PHP_EOL;

        return $html;
    }

    private function renderTable(TableNode $table): string
    {
        $rows = array_values(array_filter($table->getRows(), static fn (mixed $row): bool => $row instanceof TableRowNode));
        $totalRows = count($rows);
        $totalColumns = $this->countTableColumns($rows);

        $tableBorderStyle = $this->extractTableBorderContextFromNode($table);
        $tableStyles = [];
        $tableLayout = $table->getResolvedLayout();
        $indentLeft = is_array($tableLayout) ? ($tableLayout['indent']['left'] ?? null) : $table->getIndentLeft();
        $spacingBefore = is_array($tableLayout) ? ($tableLayout['spacing']['before'] ?? null) : $table->getSpacingBefore();
        $spacingAfter = is_array($tableLayout) ? ($tableLayout['spacing']['after'] ?? null) : $table->getSpacingAfter();
        $cellSpacing = is_array($tableLayout) ? ($tableLayout['cellSpacing'] ?? null) : $table->getCellSpacing();
        $layout = is_array($tableLayout) ? ($tableLayout['layout'] ?? null) : $table->getLayout();
        if (is_numeric($indentLeft) && (float)$indentLeft > 0) {
            $tableStyles[] = sprintf('margin-left: %scm;', $indentLeft);
        }
        if (is_numeric($spacingBefore) && (float)$spacingBefore > 0) {
            $tableStyles[] = sprintf('margin-top: %scm;', $spacingBefore);
        }
        if (is_numeric($spacingAfter) && (float)$spacingAfter > 0) {
            $tableStyles[] = sprintf('margin-bottom: %scm;', $spacingAfter);
        }
        if (is_numeric($cellSpacing) && (float)$cellSpacing > 0) {
            $tableStyles[] = sprintf('border-spacing: %scm;', $cellSpacing);
        }
        if (is_string($layout) && $layout !== '') {
            $tableStyles[] = sprintf('table-layout: %s;', $layout === 'fixed' ? 'fixed' : 'auto');
        }
        if ($this->hasAnyTableBorder($tableBorderStyle)) {
            $tableStyles[] = 'border-collapse: collapse;';
            $outerBorderStyles = $this->buildBorderCss($tableBorderStyle['outer'] ?? []);
            if ($outerBorderStyles !== '') {
                $tableStyles[] = $outerBorderStyles;
            }
        }

        $tableStyleAttr = $tableStyles !== [] ? sprintf(' style="%s"', implode(' ', $tableStyles)) : '';
        $html = sprintf('<table class="table jrvTable"%s>%s', $tableStyleAttr, PHP_EOL);

        foreach ($rows as $rowIndex => $row) {
            if (!$row instanceof TableRowNode) {
                continue;
            }

            $html .= '    <tr>' . PHP_EOL;
            $columnIndex = 0;
            foreach ($row->getCells() as $cell) {
                if (!$cell instanceof TableCellNode) {
                    continue;
                }

                $colSpan = max(1, (int)($cell->getColumnSpan() ?? 1));
                $styles = [];

                $cellStyle = $cell->getResolvedStyle();
                $cellLayout = $cell->getResolvedLayout();
                $backgroundColor = is_array($cellStyle) && is_string($cellStyle['backgroundColor'] ?? null)
                    ? BorderStyleHelper::formatCssHexColor($cellStyle['backgroundColor'])
                    : null;
                if ($backgroundColor !== null) {
                    $styles[] = 'background-color: ' . $backgroundColor . ';';
                }

                $effectiveBorders = $this->resolveCellBorders(
                    $cellStyle,
                    $tableBorderStyle,
                    $rowIndex,
                    $totalRows,
                    $columnIndex,
                    max(0, $totalColumns - 1),
                    $colSpan
                );
                $borderStyles = $this->buildBorderCss($effectiveBorders);
                if ($borderStyles !== '') {
                    $styles[] = $borderStyles;
                }
                if (is_array($cellLayout)) {
                    $verticalAlign = $cellLayout['verticalAlign'] ?? null;
                    if (is_string($verticalAlign) && $verticalAlign !== '') {
                        $styles[] = sprintf('vertical-align: %s;', $verticalAlign);
                    }
                    $margins = is_array($cellLayout['margins'] ?? null) ? $cellLayout['margins'] : null;
                    if ($margins !== null) {
                        $top = is_numeric($margins['top'] ?? null) ? (float)$margins['top'] : 0.0;
                        $right = is_numeric($margins['right'] ?? null) ? (float)$margins['right'] : 0.0;
                        $bottom = is_numeric($margins['bottom'] ?? null) ? (float)$margins['bottom'] : 0.0;
                        $left = is_numeric($margins['left'] ?? null) ? (float)$margins['left'] : 0.0;
                        if ($top > 0 || $right > 0 || $bottom > 0 || $left > 0) {
                            $styles[] = sprintf('padding: %scm %scm %scm %scm;', $top, $right, $bottom, $left);
                        }
                    }
                }

                $html .= sprintf(
                    '        <td%s%s>%s',
                    $colSpan > 1 ? ' colspan="' . $colSpan . '"' : '',
                    $styles !== [] ? ' style="' . implode(' ', $styles) . '"' : '',
                    PHP_EOL
                );
                foreach ($cell->getChildren() as $child) {
                    if ($child instanceof AstNode) {
                        $rendered = $this->renderBlockNode($child, false);
                        $html .= '            ' . $rendered;
                    }
                }
                $html .= '        </td>' . PHP_EOL;
                $columnIndex += $colSpan;
            }
            $html .= '    </tr>' . PHP_EOL;
        }
        $html .= '</table>' . PHP_EOL . PHP_EOL;

        return $html;
    }

    private function renderTextBox(TextBoxNode $box): string
    {
        $legacyHtml = $box->getRenderHints()->getHint('legacy_html');
        if (is_string($legacyHtml) && $legacyHtml !== '') {
            return $legacyHtml;
        }

        $content = '';
        foreach ($box->getChildren() as $child) {
            if ($child instanceof AstNode) {
                $content .= $this->renderBlockNode($child, false);
            }
        }

        return $content;
    }

    private function renderBreak(BreakNode $break): string
    {
        if ($break->getType() === 'page') {
            return '<div class="page-break">Seitenwechsel</div>';
        }

        return '<br>' . PHP_EOL;
    }

    private function renderInlineNodes(array $children): string
    {
        $html = '';

        foreach ($children as $child) {
            if (!$child instanceof AstNode) {
                continue;
            }

            if ($child instanceof TextNode) {
                $html .= $this->renderTextNode($child);
                continue;
            }

            if ($child instanceof LinkNode) {
                $html .= $this->renderLinkNode($child);
                continue;
            }

            if ($child instanceof BreakNode) {
                $html .= '<br>' . PHP_EOL;
                continue;
            }

            if ($child instanceof TabNode) {
                $html .= "\t";
                continue;
            }

            if ($child instanceof ScaleNode) {
                $inner = $this->renderInlineNodes($child->getChildren());
                $scale = rtrim(rtrim(number_format($child->getScaleX(), 3, '.', ''), '0'), '.');
                if ($scale === '' || $scale === '1') {
                    $html .= $inner;
                } else {
                    $html .= sprintf('<span data-font-scale="%s">%s</span>', $scale, $inner);
                }
                continue;
            }

            if ($child instanceof FormatNode) {
                $inner = $this->renderInlineNodes($child->getChildren());
                $tag = $child->getFormatType();
                $html .= sprintf('<%1$s>%2$s</%1$s>', $tag, $inner);
                continue;
            }

            if ($child instanceof RevisionNode) {
                $inner = $this->renderInlineNodes($child->getChildren());
                if ($child->getChangeType() === TrackChangeType::Deleted) {
                    $html .= sprintf('<del>%s</del>', $inner);
                } else {
                    $html .= $inner;
                }
                continue;
            }

            if ($child instanceof FieldTextNode) {
                $html .= $child->getFieldResult() ?? $child->getFieldCode();
                continue;
            }
        }

        return $html;
    }

    private function renderTextNode(TextNode $textNode): string
    {
        $text = str_replace("\xC2\xA0", '&nbsp;', $textNode->getContent());
        $tags = [];

        if ($textNode->isUnderline()) {
            $tags[] = 'u';
        }
        if ($textNode->isBold()) {
            $tags[] = 'strong';
        }
        if ($textNode->isItalic()) {
            $tags[] = 'em';
        }

        if ($tags !== []) {
            $prefix = '<' . implode('><', $tags) . '>';
            $suffix = '</' . implode('></', array_reverse($tags)) . '>';
            $text = $prefix . $text . $suffix;
        }

        if ($textNode->getTrackChange() === TrackChangeType::Deleted) {
            return sprintf('<del>%s</del>', $text);
        }

        return $text;
    }

    private function renderLinkNode(LinkNode $linkNode): string
    {
        $href = $linkNode->getHref();
        $content = $this->renderInlineNodes($linkNode->getChildren());
        if ($href === null || $href === '') {
            return $content;
        }

        return sprintf('<a href="%s">%s</a>', htmlspecialchars($href, ENT_QUOTES, 'UTF-8'), $content);
    }

    private function buildBorderStyleFromGroup(BorderGroupNode $group): ?string
    {
        foreach ($group->getChildren() as $child) {
            if (!$child instanceof ParagraphNode) {
                continue;
            }

            $style = $this->buildBorderStyleFromResolvedStyle($child->getResolvedStyle());
            if ($style !== null) {
                return $style;
            }
        }

        if ($group->getBorderSize() !== null && $group->getBorderSize() > 0) {
            $width = BorderStyleHelper::normalizeBorderWidthCm($this->twipsToCm($group->getBorderSize()));
            $color = BorderStyleHelper::formatCssHexColor($group->getBorderColor());
            $style = $group->getBorderStyle() ?? 'solid';
            return sprintf('border: %scm %s%s; padding: 0.2cm;', $width, $style, $color !== null ? ' ' . $color : '');
        }

        return null;
    }

    private function buildBorderStyleFromResolvedStyle(?array $style): ?string
    {
        if ($style === null) {
            return null;
        }

        $borders = [
            'top' => $style['borderTop'] ?? null,
            'left' => $style['borderLeft'] ?? null,
            'right' => $style['borderRight'] ?? null,
            'bottom' => $style['borderBottom'] ?? null,
        ];

        $hasBorders = false;
        foreach ($borders as $border) {
            if (is_array($border) && ($border['size'] ?? null) !== null && ($border['size'] ?? '') !== '') {
                $hasBorders = true;
                break;
            }
        }

        if (!$hasBorders) {
            return null;
        }

        $allIdentical = $this->allBordersIdentical($borders);
        $mapping = [
            'single' => 'solid',
            'double' => 'double',
            'dotted' => 'dotted',
            'dashed' => 'dashed',
            'none' => 'none',
        ];

        if ($allIdentical) {
            $top = $borders['top'];
            $width = BorderStyleHelper::normalizeBorderWidthCm($this->twipsToCm((float)($top['size'] ?? 0)));
            $styleName = $mapping[$top['style'] ?? 'single'] ?? 'solid';
            $color = BorderStyleHelper::formatCssHexColor($top['color'] ?? null);

            return sprintf('border: %scm %s%s; padding: 0.2cm;', $width, $styleName, $color !== null ? ' ' . $color : '');
        }

        $styles = [];
        foreach ($borders as $side => $border) {
            if (!is_array($border) || ($border['size'] ?? null) === null || ($border['size'] ?? '') === '') {
                continue;
            }
            $width = BorderStyleHelper::normalizeBorderWidthCm($this->twipsToCm((float)$border['size']));
            $styleName = $mapping[$border['style'] ?? 'single'] ?? 'solid';
            $color = BorderStyleHelper::formatCssHexColor($border['color'] ?? null);
            $styles[] = sprintf('border-%s: %scm %s%s;', $side, $width, $styleName, $color !== null ? ' ' . $color : '');
        }
        $styles[] = 'padding: 0.2cm;';

        return implode(' ', $styles);
    }

    private function allBordersIdentical(array $borders): bool
    {
        $first = $borders['top'];
        if (!is_array($first)) {
            return false;
        }

        foreach (['left', 'right', 'bottom'] as $side) {
            $candidate = $borders[$side];
            if (!is_array($candidate)) {
                return false;
            }

            if (($candidate['size'] ?? null) !== ($first['size'] ?? null)
                || ($candidate['color'] ?? null) !== ($first['color'] ?? null)
                || ($candidate['style'] ?? null) !== ($first['style'] ?? null)) {
                return false;
            }
        }

        return true;
    }

    private function listHintString(ListItemNode $item, string $key): ?string
    {
        $value = $item->getRenderHints()->getHint($key);
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function listHintInt(ListItemNode $item, string $key): ?int
    {
        $value = $item->getRenderHints()->getHint($key);
        return is_int($value) ? $value : (is_numeric($value) ? (int)$value : null);
    }

    private function countTableColumns(array $rows): int
    {
        $maxColumns = 0;
        foreach ($rows as $row) {
            if (!$row instanceof TableRowNode) {
                continue;
            }

            $rowColumns = 0;
            foreach ($row->getCells() as $cell) {
                if (!$cell instanceof TableCellNode) {
                    continue;
                }
                $rowColumns += max(1, (int)($cell->getColumnSpan() ?? 1));
            }

            $maxColumns = max($maxColumns, $rowColumns);
        }

        return $maxColumns;
    }

    private function extractTableBorderContextFromNode(TableNode $table): ?array
    {
        $style = $table->getResolvedStyle();
        if (is_array($style)) {
            $borders = $style['borders'] ?? null;
            if (is_array($borders)) {
                return $borders;
            }
        }

        $layout = $table->getResolvedLayout();
        $borders = is_array($layout) ? ($layout['borders'] ?? null) : null;
        return is_array($borders) ? $borders : null;
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

    private function resolveCellBorders(
        mixed $cellStyle,
        ?array $tableBorderStyle,
        int $rowIndex,
        int $totalRows,
        int $columnIndex,
        int $lastColumnIndex,
        int $colSpan
    ): array {
        $cellBorders = is_array($cellStyle) && is_array($cellStyle['borders'] ?? null) ? $cellStyle['borders'] : [];
        $cellEnd = $columnIndex + $colSpan - 1;

        $resolvedBorders = [];
        foreach (self::BORDER_SIDES as $side) {
            $border = is_array($cellBorders[$side] ?? null) ? $cellBorders[$side] : null;
            if ($this->isBorderDefined($border)) {
                $resolvedBorders[$side] = $border;
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
        $isLastRow = $rowIndex === $totalRows - 1;
        $isFirstCol = $cellStartColumn === 0;
        $isLastCol = $cellEndColumn === $lastColumnIndex;

        return match ($side) {
            'top' => $isFirstRow ? ($tableBorderStyle['outer']['top'] ?? null) : ($tableBorderStyle['inside']['horizontal'] ?? null),
            'bottom' => $isLastRow ? ($tableBorderStyle['outer']['bottom'] ?? null) : ($tableBorderStyle['inside']['horizontal'] ?? null),
            'left' => $isFirstCol ? ($tableBorderStyle['outer']['left'] ?? null) : ($tableBorderStyle['inside']['vertical'] ?? null),
            'right' => $isLastCol ? ($tableBorderStyle['outer']['right'] ?? null) : ($tableBorderStyle['inside']['vertical'] ?? null),
            default => null,
        };
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

        return is_numeric($size) ? (float)$size > 0.0 : true;
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

        if ($normalizedBorders === []) {
            return '';
        }

        if (count($normalizedBorders) === 4 && $this->allTableBordersIdentical($normalizedBorders)) {
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

            $border = $normalizedBorders[$side];
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
        $size = is_numeric($border['size'] ?? null) ? (float)$border['size'] : 0.0;
        $widthCm = BorderStyleHelper::normalizeBorderWidthCm($this->convertBorderWidthToCm($size));

        return [
            'widthCm' => $widthCm,
            'style' => $this->mapWordBorderStyleToCss(is_string($border['style'] ?? null) ? $border['style'] : null),
            'color' => BorderStyleHelper::formatCssHexColor(is_string($border['color'] ?? null) ? $border['color'] : null),
        ];
    }

    private function allTableBordersIdentical(array $normalizedBorders): bool
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
        return $size * 2.54 / 576;
    }

    private function mapWordBorderStyleToCss(?string $wordStyle): string
    {
        return match ($wordStyle) {
            'double' => 'double',
            'dotted' => 'dotted',
            'dashed' => 'dashed',
            'none', 'nil' => 'none',
            default => 'solid',
        };
    }

    private function formatLength(float $length): string
    {
        return rtrim(rtrim(sprintf('%.4f', $length), '0'), '.');
    }

    private function splitListItemChildren(ListItemNode $item): array
    {
        $inlineChildren = [];
        $nestedItems = [];

        foreach ($item->getChildren() as $child) {
            if ($child instanceof ListItemNode) {
                $nestedItems[] = $child;
                continue;
            }

            if ($child instanceof AstNode) {
                $inlineChildren[] = $child;
            }
        }

        return [$inlineChildren, $nestedItems];
    }

    private function renderNestedLists(array $nestedItems): string
    {
        if ($nestedItems === []) {
            return '';
        }

        $html = '';
        $group = [];

        foreach ($nestedItems as $nestedItem) {
            if (!$nestedItem instanceof ListItemNode) {
                continue;
            }

            if ($group === []) {
                $group[] = $nestedItem;
                continue;
            }

            /** @var ListItemNode $lastInGroup */
            $lastInGroup = $group[count($group) - 1];
            if ($this->canGroupListItems($lastInGroup, $nestedItem)) {
                $group[] = $nestedItem;
                continue;
            }

            $html .= $this->renderList(new ListNode(items: $group));
            $group = [$nestedItem];
        }

        if ($group !== []) {
            $html .= $this->renderList(new ListNode(items: $group));
        }

        return $html;
    }

    private function canGroupListItems(ListItemNode $left, ListItemNode $right): bool
    {
        return $left->getNumId() === $right->getNumId()
            && $left->getDepth() === $right->getDepth()
            && $left->getNumFormat() === $right->getNumFormat();
    }

    private function mapParagraphAlignmentToCss(?string $alignment): ?string
    {
        if ($alignment === null || $alignment === '') {
            return null;
        }

        return match ($alignment) {
            Jc::CENTER => 'center',
            Jc::BOTH => 'justify',
            Jc::RIGHT, Jc::END => 'right',
            Jc::LEFT, Jc::START => 'left',
            default => null,
        };
    }

    private function removeBorderStyles(string $html): string
    {
        $html = preg_replace('/\s*border:\s*[^;]+;/', '', $html) ?? $html;
        $html = preg_replace('/\s*border-top:\s*[^;]+;/', '', $html) ?? $html;
        $html = preg_replace('/\s*border-left:\s*[^;]+;/', '', $html) ?? $html;
        $html = preg_replace('/\s*border-right:\s*[^;]+;/', '', $html) ?? $html;
        $html = preg_replace('/\s*border-bottom:\s*[^;]+;/', '', $html) ?? $html;

        return preg_replace('/\s*padding:\s*[^;]+;/', '', $html) ?? $html;
    }

    private function twipsToCm(float|int $twips): float
    {
        return round($twips / 1440 * 2.54, 2);
    }
}
