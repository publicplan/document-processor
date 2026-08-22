<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Ast;

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
            return $this->legacyHtml($node) ?? '<div class="page-break">Seitenwechsel</div>';
        }

        if ($node instanceof TextNode) {
            return $this->renderTextNode($node);
        }

        if ($node instanceof LinkNode) {
            return $this->renderLinkNode($node);
        }

        if ($node instanceof FieldTextNode) {
            return $this->legacyHtml($node) ?? ($node->getFieldResult() ?? $node->getFieldCode());
        }

        return $this->legacyHtml($node) ?? '';
    }

    private function renderParagraph(ParagraphNode $paragraph, bool $insideBorderGroup): string
    {
        $legacyHtml = $this->legacyHtml($paragraph);
        if ($legacyHtml !== null) {
            if ($insideBorderGroup) {
                $legacyWithoutBorder = $paragraph->getRenderHints()->getHint('legacy_html_no_border');
                if (is_string($legacyWithoutBorder)) {
                    return $legacyWithoutBorder;
                }

                return $this->removeBorderStyles($legacyHtml);
            }

            return $legacyHtml;
        }

        $text = $this->renderInlineNodes($paragraph->getChildren());
        if ($text === '') {
            $text = '&nbsp;';
        }

        $styles = [];
        $styles[] = sprintf('margin-bottom: %scm;', $paragraph->getSpacingAfter() ?? 0);
        $styleAttr = sprintf(' style="%s"', implode(' ', $styles));

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

        $html = $config->renderStartTag($startOverride) . PHP_EOL;
        foreach ($items as $item) {
            $html .= $this->renderListItem($item);
        }
        $html .= $config->renderEndTag() . PHP_EOL;

        return $html;
    }

    private function renderListItem(ListItemNode $item): string
    {
        $legacyHtml = $this->legacyHtml($item);
        if ($legacyHtml !== null) {
            return $legacyHtml;
        }

        $content = $this->renderInlineNodes($item->getChildren());
        if ($content === '') {
            return '';
        }

        return sprintf("    <li>%s</li>%s", $content, PHP_EOL);
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
        $legacyHtml = $this->legacyHtml($table);
        if ($legacyHtml !== null) {
            return $legacyHtml;
        }

        $html = sprintf('<table class="table jrvTable">%s', PHP_EOL);
        foreach ($table->getRows() as $row) {
            if (!$row instanceof TableRowNode) {
                continue;
            }

            $html .= '    <tr>' . PHP_EOL;
            foreach ($row->getCells() as $cell) {
                if (!$cell instanceof TableCellNode) {
                    continue;
                }

                $html .= '        <td>' . PHP_EOL;
                foreach ($cell->getChildren() as $child) {
                    if ($child instanceof AstNode) {
                        $rendered = $this->renderBlockNode($child, false);
                        $html .= '            ' . $rendered;
                    }
                }
                $html .= '        </td>' . PHP_EOL;
            }
            $html .= '    </tr>' . PHP_EOL;
        }
        $html .= '</table>' . PHP_EOL . PHP_EOL;

        return $html;
    }

    private function renderTextBox(TextBoxNode $box): string
    {
        $legacyHtml = $this->legacyHtml($box);
        if ($legacyHtml !== null) {
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
        $legacyHtml = $this->legacyHtml($break);
        if ($legacyHtml !== null) {
            return $legacyHtml;
        }

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

            $legacyHtml = $this->legacyHtml($child);
            if ($legacyHtml !== null) {
                $html .= $legacyHtml;
            }
        }

        return $html;
    }

    private function renderTextNode(TextNode $textNode): string
    {
        $legacyHtml = $this->legacyHtml($textNode);
        if ($legacyHtml !== null) {
            return $legacyHtml;
        }

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
        $legacyHtml = $this->legacyHtml($linkNode);
        if ($legacyHtml !== null) {
            return $legacyHtml;
        }

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

    private function legacyHtml(AstNode $node): ?string
    {
        $legacy = $node->getRenderHints()->getHint('legacy_html');
        return is_string($legacy) ? $legacy : null;
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
