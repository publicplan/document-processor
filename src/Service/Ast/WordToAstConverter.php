<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Ast;

use PhpOffice\PhpWord\Element\FormField;
use PhpOffice\PhpWord\Element\Link as DocLink;
use PhpOffice\PhpWord\Element\ListItemRun as DocList;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\Element\PreserveText;
use PhpOffice\PhpWord\Element\Table as DocTable;
use PhpOffice\PhpWord\Element\Text as DocText;
use PhpOffice\PhpWord\Element\TextBreak as DocBreak;
use PhpOffice\PhpWord\Element\TextBox as DocTextBox;
use PhpOffice\PhpWord\Element\TextRun as DocTextRun;
use PhpOffice\PhpWord\PhpWord;
use Publicplan\DocumentProcessor\Ast\Metadata\ListFormat;
use Publicplan\DocumentProcessor\Ast\Metadata\RenderHints;
use Publicplan\DocumentProcessor\Ast\Metadata\TrackChangeType;
use Publicplan\DocumentProcessor\Ast\Node\BreakNode;
use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\FieldTextNode;
use Publicplan\DocumentProcessor\Ast\Node\LinkNode;
use Publicplan\DocumentProcessor\Ast\Node\ListItemNode;
use Publicplan\DocumentProcessor\Ast\Node\PageBreakNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\TableCellNode;
use Publicplan\DocumentProcessor\Ast\Node\TableNode;
use Publicplan\DocumentProcessor\Ast\Node\TableRowNode;
use Publicplan\DocumentProcessor\Ast\Node\TextBoxNode;
use Publicplan\DocumentProcessor\Ast\Node\TextNode;
use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Model\ParserError;
use Publicplan\DocumentProcessor\Service\Converter\BreakElementConverter;
use Publicplan\DocumentProcessor\Service\Converter\LinkElementConverter;
use Publicplan\DocumentProcessor\Service\Converter\ListElementConverter;
use Publicplan\DocumentProcessor\Service\Converter\PageBreakElementConverter;
use Publicplan\DocumentProcessor\Service\Converter\TableElementConverter;
use Publicplan\DocumentProcessor\Service\Converter\TextBoxElementConverter;
use Publicplan\DocumentProcessor\Service\Converter\TextElementConverter;
use Publicplan\DocumentProcessor\Service\Converter\TextRunElementConverter;

final class WordToAstConverter
{
    private readonly TextRunElementConverter $textRunConverter;
    private readonly TextElementConverter $textConverter;
    private readonly LinkElementConverter $linkConverter;
    private readonly BreakElementConverter $breakConverter;
    private readonly ListElementConverter $listConverter;
    private readonly TableElementConverter $tableConverter;
    private readonly TextBoxElementConverter $textBoxConverter;
    private readonly PageBreakElementConverter $pageBreakConverter;

    private int|string|null $lastListNumId = null;

    public function __construct()
    {
        $this->textRunConverter = new TextRunElementConverter();
        $this->textConverter = new TextElementConverter();
        $this->linkConverter = new LinkElementConverter();
        $this->breakConverter = new BreakElementConverter();
        $this->listConverter = new ListElementConverter();
        $this->tableConverter = new TableElementConverter();
        $this->textBoxConverter = new TextBoxElementConverter();
        $this->pageBreakConverter = new PageBreakElementConverter();
    }

    public function convert(PhpWord $document, ConversionContext $context): DocumentNode
    {
        $root = new DocumentNode();

        foreach ($document->getSections() as $section) {
            $sectionNode = new SectionNode();
            $elements = $section->getElements();
            $lastTextRun = null;

            for ($i = 0; $i < count($elements); $i++) {
                $element = $elements[$i];

                if ($element instanceof DocBreak) {
                    $legacyHtml = $this->renderTopLevelBreakHtml($elements, $i, $lastTextRun);
                    if ($legacyHtml !== null) {
                        if (str_starts_with($legacyHtml, '<br>')) {
                            $sectionNode->addParagraph(new BreakNode('line', renderHints: new RenderHints([
                                'legacy_html' => $legacyHtml,
                            ])));
                        } else {
                            $breakParagraph = new ParagraphNode(
                                children: [new TextNode('&nbsp;')],
                                spacingAfter: 0.0,
                                renderHints: new RenderHints([
                                    'legacy_html' => $legacyHtml,
                                    'legacy_html_no_border' => $legacyHtml,
                                ])
                            );
                            $sectionNode->addParagraph($breakParagraph);
                        }
                    }
                    continue;
                }

                if ($element instanceof DocTextRun) {
                    $lastTextRun = $element;
                } else {
                    $lastTextRun = null;
                }

                if ($element instanceof DocList) {
                    $this->setLastListNumId($element->getStyle()?->getNumId());
                    $spaceAfter = $element->getParagraphStyle()?->getSpaceAfter();
                    $bottomSpacingCm = $spaceAfter ? $this->twipsToCm($spaceAfter) : 0.0;
                    $nextIndex = $i + 1;
                    $spacerSpacingCm = $this->accumulateSpacingCm($elements, $nextIndex);
                    $i = $nextIndex - 1;

                    $listConfig = $this->listConverter->createListConfig($element);
                    $listItemNode = new ListItemNode(
                        numId: (int)($element->getStyle()?->getNumId() ?? 0),
                        depth: $element->getDepth(),
                        numFormat: $this->mapListFormat($listConfig->tag, $listConfig->type),
                        children: $this->convertInlineElements($element->getElements(), $context),
                        resolvedStyle: $this->extractParagraphStyle($element->getParagraphStyle()),
                        renderHints: new RenderHints([
                            'legacy_html' => $this->listConverter->convertWithSpacerSpacing($element, $context, $bottomSpacingCm, $spacerSpacingCm),
                            'list_tag' => $listConfig->tag,
                            'list_type' => $listConfig->type,
                            'list_start' => $listConfig->start,
                            'list_sequence_key' => $listConfig->sequenceKey,
                            'list_docx_id' => $listConfig->docxListId,
                        ])
                    );
                    $sectionNode->addParagraph($listItemNode);
                    continue;
                }

                if ($element instanceof DocText) {
                    $fontStyle = $element->getFontStyle();
                    $sectionNode->addParagraph(new TextNode(
                        content: $element->getText() ?? '',
                        bold: (bool)$fontStyle?->isBold(),
                        italic: (bool)$fontStyle?->isItalic(),
                        underline: ($fontStyle?->getUnderline() ?? 'none') !== 'none',
                        fontSize: $fontStyle?->getSize() !== null ? (float)$fontStyle->getSize() : null,
                        preserveSpace: false,
                        trackChange: (bool)$fontStyle?->isStrikethrough() ? TrackChangeType::Deleted : TrackChangeType::None,
                        renderHints: new RenderHints([
                            'legacy_html' => $this->textConverter->convert($element, $context),
                        ])
                    ));
                    continue;
                }

                if ($element instanceof DocLink) {
                    $sectionNode->addParagraph(new LinkNode(
                        href: $element->getSource(),
                        children: [new TextNode($element->getText())],
                        renderHints: new RenderHints([
                            'legacy_html' => $this->linkConverter->convert($element, $context),
                        ])
                    ));
                    continue;
                }

                if ($element instanceof PreserveText) {
                    $fieldText = implode(' ', $element->getText());
                    $sectionNode->addParagraph(new FieldTextNode(
                        fieldCode: $fieldText,
                        fieldResult: $fieldText,
                        renderHints: new RenderHints([
                            'legacy_html' => $fieldText,
                        ])
                    ));
                    continue;
                }

                if ($element instanceof DocTextRun) {
                    $legacyHtml = $this->textRunConverter->convert($element, $context);
                    $paragraph = new ParagraphNode(
                        children: $this->convertInlineElements($element->getElements(), $context),
                        alignment: $element->getParagraphStyle()?->getAlignment(),
                        indentLeft: $this->nullableTwipsToCm($element->getParagraphStyle()?->getIndentLeft()),
                        indentRight: $this->nullableTwipsToCm($element->getParagraphStyle()?->getIndentRight()),
                        indentFirstLine: $this->nullableTwipsToCm($element->getParagraphStyle()?->getIndentFirstLine()),
                        spacingBefore: $this->nullableTwipsToCm($element->getParagraphStyle()?->getSpaceBefore()),
                        spacingAfter: $this->nullableTwipsToCm($element->getParagraphStyle()?->getSpaceAfter()) ?? 0.0,
                        lineHeight: null,
                        resolvedStyle: $this->extractParagraphStyle($element->getParagraphStyle()),
                        renderHints: new RenderHints([
                            'legacy_html' => $legacyHtml,
                            'legacy_html_no_border' => $this->removeBorderStyles($legacyHtml),
                        ])
                    );
                    $sectionNode->addParagraph($paragraph);
                    continue;
                }

                if ($element instanceof DocTable) {
                    $tableNode = $this->convertTable($element, $context);
                    $tableNode->getRenderHints()->set('legacy_html', $this->tableConverter->convert($element, $context));
                    $sectionNode->addParagraph($tableNode);
                    continue;
                }

                if ($element instanceof DocTextBox) {
                    $textBoxNode = $this->convertTextBox($element, $context);
                    $textBoxNode->getRenderHints()->set('legacy_html', $this->textBoxConverter->convert($element, $context));
                    $sectionNode->addParagraph($textBoxNode);
                    continue;
                }

                if ($element instanceof PageBreak) {
                    $sectionNode->addParagraph(new PageBreakNode(
                        renderHints: new RenderHints([
                            'legacy_html' => $this->pageBreakConverter->convert($element, $context),
                        ])
                    ));
                    continue;
                }

                $this->addUnhandledElementMessage($context, $element, 'Nicht unterstütztes Element auf Section-Ebene');
            }

            $root->addSection($sectionNode);
        }

        return $root;
    }

    private function convertInlineElements(array $elements, ConversionContext $context): array
    {
        $nodes = [];

        foreach ($elements as $element) {
            if ($element instanceof DocText) {
                $fontStyle = $element->getFontStyle();
                $nodes[] = new TextNode(
                    content: $element->getText() ?? '',
                    bold: (bool)$fontStyle?->isBold(),
                    italic: (bool)$fontStyle?->isItalic(),
                    underline: ($fontStyle?->getUnderline() ?? 'none') !== 'none',
                    fontSize: $fontStyle?->getSize() !== null ? (float)$fontStyle->getSize() : null,
                    preserveSpace: false,
                    trackChange: (bool)$fontStyle?->isStrikethrough() ? TrackChangeType::Deleted : TrackChangeType::None,
                    renderHints: new RenderHints([
                        'legacy_html' => $this->textConverter->convert($element, $context),
                    ])
                );
                continue;
            }

            if ($element instanceof DocLink) {
                $linkNode = new LinkNode(
                    href: $element->getSource(),
                    children: [
                        new TextNode(
                            content: $element->getText(),
                            trackChange: ($element->getFontStyle()?->isStrikethrough() ?? false) ? TrackChangeType::Deleted : TrackChangeType::None
                        ),
                    ],
                    renderHints: new RenderHints([
                        'legacy_html' => $this->linkConverter->convert($element, $context),
                    ])
                );
                $nodes[] = $linkNode;
                continue;
            }

            if ($element instanceof DocBreak) {
                $nodes[] = new BreakNode('line', renderHints: new RenderHints([
                    'legacy_html' => $this->breakConverter->convert($element, $context),
                ]));
                continue;
            }

            if ($element instanceof PreserveText) {
                $nodes[] = new FieldTextNode(
                    fieldCode: implode(' ', $element->getText()),
                    fieldResult: implode(' ', $element->getText()),
                    renderHints: new RenderHints([
                        'legacy_html' => implode(' ', $element->getText()),
                    ])
                );
                continue;
            }

            if ($element instanceof FormField) {
                $context->addMessage(
                    ParserError::create(
                        ParserError::CONTAINS_FORM_FIELDS,
                        ParserError::SEVERITY_ERROR,
                        'Im Dokument definierte Formularfelder führen zur Fehlinterpretation der Vorlage durch den Parser und müssen daher in Word entfernt werden.'
                    ),
                    true
                );
                continue;
            }

            $this->addUnhandledElementMessage($context, $element, 'Nicht unterstütztes Inline-Element');
        }

        return $nodes;
    }

    private function convertTable(DocTable $table, ConversionContext $context): TableNode
    {
        $tableNode = new TableNode();

        foreach ($table->getRows() as $row) {
            $rowNode = new TableRowNode();
            foreach ($row->getCells() as $cell) {
                $cellNode = new TableCellNode(
                    width: $cell->getWidth(),
                    columnSpan: $cell->getStyle()?->getGridSpan(),
                    rowSpan: is_numeric($cell->getStyle()?->getVMerge()) ? (int)$cell->getStyle()?->getVMerge() : null
                );

                foreach ($cell->getElements() as $cellElement) {
                    if ($cellElement instanceof DocTextRun) {
                        $legacyHtml = $this->textRunConverter->convert($cellElement, $context);
                        $cellNode->addChild(new ParagraphNode(
                            children: $this->convertInlineElements($cellElement->getElements(), $context),
                            resolvedStyle: $this->extractParagraphStyle($cellElement->getParagraphStyle()),
                            renderHints: new RenderHints([
                                'legacy_html' => $legacyHtml,
                                'legacy_html_no_border' => $this->removeBorderStyles($legacyHtml),
                            ])
                        ));
                        continue;
                    }

                    if ($cellElement instanceof DocBreak) {
                        $cellNode->addChild(new BreakNode('line', renderHints: new RenderHints([
                            'legacy_html' => $this->breakConverter->convert($cellElement, $context),
                        ])));
                    }
                }

                $rowNode->addCell($cellNode);
            }
            $tableNode->addRow($rowNode);
        }

        return $tableNode;
    }

    private function convertTextBox(DocTextBox $textBox, ConversionContext $context): TextBoxNode
    {
        $node = new TextBoxNode();

        foreach ($textBox->getElements() as $element) {
            if ($element instanceof DocTextRun) {
                $legacyHtml = $this->textRunConverter->convert($element, $context);
                $node->addChild(new ParagraphNode(
                    children: $this->convertInlineElements($element->getElements(), $context),
                    resolvedStyle: $this->extractParagraphStyle($element->getParagraphStyle()),
                    renderHints: new RenderHints([
                        'legacy_html' => $legacyHtml,
                        'legacy_html_no_border' => $this->removeBorderStyles($legacyHtml),
                    ])
                ));
            }
        }

        return $node;
    }

    private function renderTopLevelBreakHtml(array $elements, int $index, ?DocTextRun $lastTextRun): ?string
    {
        $previousElement = $elements[$index - 1] ?? null;
        $wasInsideList = $previousElement instanceof DocList;

        if ($lastTextRun === null) {
            if ($wasInsideList) {
                return '<p style="margin-bottom: 0cm;">&nbsp;</p>' . PHP_EOL;
            }

            return '<br>' . PHP_EOL;
        }

        return sprintf('<p style="%s">&nbsp;</p>%s', $this->getMarginBottomFromElement($lastTextRun), PHP_EOL);
    }

    private function mapListFormat(string $tag, ?string $type): ListFormat
    {
        if ($tag === 'ul') {
            return ListFormat::Bullet;
        }

        return match ($type) {
            'I' => ListFormat::Roman,
            'i' => ListFormat::RomanLower,
            'A' => ListFormat::Letter,
            'a' => ListFormat::LetterLower,
            default => ListFormat::Number,
        };
    }

    private function extractParagraphStyle(object|string|null $pStyle): array
    {
        if (!is_object($pStyle)) {
            return [];
        }

        return [
            'borderTop' => [
                'size' => method_exists($pStyle, 'getBorderTopSize') ? $pStyle->getBorderTopSize() : null,
                'color' => method_exists($pStyle, 'getBorderTopColor') ? $pStyle->getBorderTopColor() : null,
                'style' => method_exists($pStyle, 'getBorderTopStyle') ? $pStyle->getBorderTopStyle() : null,
            ],
            'borderLeft' => [
                'size' => method_exists($pStyle, 'getBorderLeftSize') ? $pStyle->getBorderLeftSize() : null,
                'color' => method_exists($pStyle, 'getBorderLeftColor') ? $pStyle->getBorderLeftColor() : null,
                'style' => method_exists($pStyle, 'getBorderLeftStyle') ? $pStyle->getBorderLeftStyle() : null,
            ],
            'borderRight' => [
                'size' => method_exists($pStyle, 'getBorderRightSize') ? $pStyle->getBorderRightSize() : null,
                'color' => method_exists($pStyle, 'getBorderRightColor') ? $pStyle->getBorderRightColor() : null,
                'style' => method_exists($pStyle, 'getBorderRightStyle') ? $pStyle->getBorderRightStyle() : null,
            ],
            'borderBottom' => [
                'size' => method_exists($pStyle, 'getBorderBottomSize') ? $pStyle->getBorderBottomSize() : null,
                'color' => method_exists($pStyle, 'getBorderBottomColor') ? $pStyle->getBorderBottomColor() : null,
                'style' => method_exists($pStyle, 'getBorderBottomStyle') ? $pStyle->getBorderBottomStyle() : null,
            ],
        ];
    }

    private function setLastListNumId(int|string|null $numId): void
    {
        $this->lastListNumId = $numId;
    }

    private function getLastListNumId(): int|string|null
    {
        return $this->lastListNumId;
    }

    private function isSpacerParagraph(object $element, array $elements, int $currentIndex): bool
    {
        if (!$element instanceof DocBreak) {
            return false;
        }

        $nextElement = $elements[$currentIndex + 1] ?? null;
        if (!$nextElement instanceof DocList) {
            return false;
        }

        $lastNumId = $this->getLastListNumId();
        $nextNumId = $nextElement->getStyle()?->getNumId();

        return $lastNumId !== null && $lastNumId === $nextNumId;
    }

    private function accumulateSpacingCm(array $elements, int &$currentIndex): float
    {
        $totalSpacing = 0.0;
        $index = $currentIndex;

        while ($index < count($elements)) {
            $element = $elements[$index];
            if ($this->isSpacerParagraph($element, $elements, $index)) {
                $totalSpacing += $this->calculateSpacerHeightCm($element);
                $index++;
            } else {
                break;
            }
        }

        $currentIndex = $index;

        return $totalSpacing;
    }

    private function calculateSpacerHeightCm(object $element): float
    {
        if (method_exists($element, 'getParagraphStyle')) {
            $pStyle = $element->getParagraphStyle();
            $spaceAfter = $pStyle?->getSpaceAfter();
            if ($spaceAfter) {
                return $this->twipsToCm($spaceAfter);
            }
        }

        return 0.42;
    }

    private function getMarginBottomFromElement(?DocTextRun $element): string
    {
        if ($element === null) {
            return 'margin-bottom: 0cm;';
        }

        $spaceAfter = $element->getParagraphStyle()?->getSpaceAfter();
        if ((float)$spaceAfter === 0.0) {
            return 'margin-bottom: 0cm;';
        }

        return sprintf('margin-bottom: %scm;', $this->twipsToCm($spaceAfter));
    }

    private function twipsToCm(float|string|null $twips): float
    {
        if ($twips === null || $twips === '') {
            return 0.0;
        }

        return round((float)$twips / 1440 * 2.54, 2);
    }

    private function nullableTwipsToCm(float|string|null $twips): ?float
    {
        if ($twips === null || $twips === '') {
            return null;
        }

        return $this->twipsToCm($twips);
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

    private function addUnhandledElementMessage(ConversionContext $context, object $element, string $prefix): void
    {
        $context->addMessage(
            ParserError::create(
                ParserError::CONTAINS_UNHANDLED_ELEMENTS,
                ParserError::SEVERITY_ERROR,
                sprintf('%s: %s', $prefix, get_class($element))
            ),
            true
        );
    }
}
