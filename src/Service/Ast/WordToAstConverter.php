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
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Cell as CellStyle;
use PhpOffice\PhpWord\Style\Table as TableStyle;
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
use Publicplan\DocumentProcessor\Service\Converter\ParagraphIndentHelper;
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
                            ])));
                        } else {
                            $breakParagraph = new ParagraphNode(
                                children: [new TextNode('&nbsp;')],
                                spacingAfter: 0.0,
                                renderHints: new RenderHints([
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
                    $listParagraphLayout = $this->extractListParagraphLayout($element);
                    $listLevelLayout = $this->extractListLevelLayout($element);
                    $computedSpacingAfter = $bottomSpacingCm + $spacerSpacingCm;
                    $listItemNode = new ListItemNode(
                        numId: (int)($element->getStyle()?->getNumId() ?? 0),
                        depth: (int)$element->getDepth(),
                        numFormat: $this->mapListFormat($listConfig->tag, $listConfig->type),
                        children: $this->convertInlineElements($element->getElements(), $context),
                        alignment: $listParagraphLayout['alignment'],
                        indentLeft: $listParagraphLayout['indentLeft'],
                        indentRight: $listParagraphLayout['indentRight'],
                        indentFirstLine: $listParagraphLayout['indentFirstLine'],
                        indentHanging: $listParagraphLayout['indentHanging'],
                        spacingBefore: $listParagraphLayout['spacingBefore'],
                        spacingAfter: $computedSpacingAfter > 0 ? $computedSpacingAfter : $listParagraphLayout['spacingAfter'],
                        lineHeight: $listParagraphLayout['lineHeight'],
                        levelIndentLeft: $listLevelLayout['indentLeft'],
                        levelIndentHanging: $listLevelLayout['indentHanging'],
                        levelTabStop: $listLevelLayout['tabStop'],
                        levelMarkerOffset: $this->computeMarkerOffset(
                            $listLevelLayout['tabStop'],
                            $listLevelLayout['indentLeft']
                        ),
                        resolvedStyle: $this->extractParagraphStyle($element->getParagraphStyle()),
                        renderHints: new RenderHints([
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
                        ])
                    ));
                    continue;
                }

                if ($element instanceof DocLink) {
                    $sectionNode->addParagraph(new LinkNode(
                        href: $element->getSource(),
                        children: [new TextNode($element->getText())],
                        renderHints: new RenderHints([
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
                        lineHeight: $this->extractLineHeight($element->getParagraphStyle()),
                        resolvedStyle: $this->extractParagraphStyle($element->getParagraphStyle()),
                        renderHints: new RenderHints([
                        ])
                    );
                    $sectionNode->addParagraph($paragraph);
                    continue;
                }

                if ($element instanceof DocTable) {
                    $tableNode = $this->convertTable($element, $context);
                    $sectionNode->addParagraph($tableNode);
                    continue;
                }

                if ($element instanceof DocTextBox) {
                    $textBoxNode = $this->convertTextBox($element, $context);
                    $sectionNode->addParagraph($textBoxNode);
                    continue;
                }

                if ($element instanceof PageBreak) {
                    $sectionNode->addParagraph(new PageBreakNode(
                        renderHints: new RenderHints([
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
                    ])
                );
                $nodes[] = $linkNode;
                continue;
            }

            if ($element instanceof DocBreak) {
                $nodes[] = new BreakNode('line', renderHints: new RenderHints([
                ]));
                continue;
            }

            if ($element instanceof PreserveText) {
                $nodes[] = new FieldTextNode(
                    fieldCode: implode(' ', $element->getText()),
                    fieldResult: implode(' ', $element->getText()),
                    renderHints: new RenderHints([
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
        $tableStyle = $this->resolveTableStyle($table->getStyle());
        $tableLayout = $this->extractTableLayout($tableStyle);
        $tableNode = new TableNode(
            width: $this->nullableNumericToInt($tableStyle?->getWidth()),
            widthUnit: is_string($tableStyle?->getUnit()) ? $tableStyle->getUnit() : null,
            alignment: $tableLayout['alignment'],
            indentLeft: $tableLayout['indentLeft'],
            spacingBefore: $tableLayout['spacingBefore'],
            spacingAfter: $tableLayout['spacingAfter'],
            cellSpacing: $tableLayout['cellSpacing'],
            layout: $tableLayout['layout'],
            cellMargins: $tableLayout['cellMargins'],
            resolvedStyle: $tableStyle !== null ? ['borders' => $this->extractTableBorderContext($tableStyle)] : null
        );

        foreach ($table->getRows() as $row) {
            $rowNode = new TableRowNode();
            foreach ($row->getCells() as $cell) {
                $cellStyle = $cell->getStyle();
                $cellNode = new TableCellNode(
                    width: $this->nullableNumericToInt($cell->getWidth()),
                    columnSpan: $this->nullableNumericToInt($cellStyle?->getGridSpan()),
                    rowSpan: $this->nullableNumericToInt($cellStyle?->getVMerge()),
                    resolvedStyle: $this->extractCellStyle($cellStyle)
                );

                foreach ($cell->getElements() as $cellElement) {
                    if ($cellElement instanceof DocTextRun) {
                        $legacyHtml = $this->textRunConverter->convert($cellElement, $context);
                        $cellNode->addChild(new ParagraphNode(
                            children: $this->convertInlineElements($cellElement->getElements(), $context),
                            resolvedStyle: $this->extractParagraphStyle($cellElement->getParagraphStyle()),
                            renderHints: new RenderHints([
                            ])
                        ));
                        continue;
                    }

                    if ($cellElement instanceof DocBreak) {
                        $cellNode->addChild(new BreakNode('line', renderHints: new RenderHints([
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
        $children = [];

        foreach ($textBox->getElements() as $element) {
            if ($element instanceof DocTextRun) {
                $children = array_merge($children, $this->convertInlineElements($element->getElements(), $context));
            } elseif ($element instanceof DocText) {
                $fontStyle = $element->getFontStyle();
                $children[] = new TextNode(
                    content: $element->getText() ?? '',
                    bold: (bool)$fontStyle?->isBold(),
                    italic: (bool)$fontStyle?->isItalic(),
                    underline: ($fontStyle?->getUnderline() ?? 'none') !== 'none',
                    fontSize: $fontStyle?->getSize() !== null ? (float)$fontStyle->getSize() : null,
                    preserveSpace: false,
                    trackChange: (bool)$fontStyle?->isStrikethrough() ? TrackChangeType::Deleted : TrackChangeType::None,
                    renderHints: new RenderHints([])
                );
            }
        }

        if (!empty($children)) {
            $node->addChild(new ParagraphNode(
                children: $children,
                renderHints: new RenderHints([])
            ));
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

    private function extractLineHeight(?object $paragraphStyle): ?float
    {
        if ($paragraphStyle === null) {
            return null;
        }

        $lineHeight = $paragraphStyle->getLineHeight();
        if ($lineHeight === null) {
            return null;
        }

        return (float)$lineHeight;
    }

    private function nullableNumericToInt(float|int|string|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int)$value : null;
    }

    /**
     * @return array{
     *     alignment:?string,
     *     indentLeft:?float,
     *     indentRight:?float,
     *     indentFirstLine:?float,
     *     indentHanging:?float,
     *     spacingBefore:?float,
     *     spacingAfter:?float,
     *     lineHeight:?float
     * }
     */
    private function extractListParagraphLayout(DocList $element): array
    {
        $paragraphStyle = $element->getParagraphStyle();
        $resolvedIndentation = ParagraphIndentHelper::resolveEffectiveIndentation($paragraphStyle);
        $paragraphStyleObject = is_object($paragraphStyle) ? $paragraphStyle : null;

        return [
            'alignment' => $paragraphStyleObject !== null && method_exists($paragraphStyleObject, 'getAlignment')
                ? $paragraphStyleObject->getAlignment()
                : null,
            'indentLeft' => $this->nullableTwipsToCm($resolvedIndentation['indentLeft']),
            'indentRight' => $paragraphStyleObject !== null && method_exists($paragraphStyleObject, 'getIndentRight')
                ? $this->nullableTwipsToCm($paragraphStyleObject->getIndentRight())
                : null,
            'indentFirstLine' => $this->nullableTwipsToCm($resolvedIndentation['firstLine']),
            'indentHanging' => $this->nullableTwipsToCm($resolvedIndentation['hanging']),
            'spacingBefore' => $paragraphStyleObject !== null && method_exists($paragraphStyleObject, 'getSpaceBefore')
                ? $this->nullableTwipsToCm($paragraphStyleObject->getSpaceBefore())
                : null,
            'spacingAfter' => $paragraphStyleObject !== null && method_exists($paragraphStyleObject, 'getSpaceAfter')
                ? $this->nullableTwipsToCm($paragraphStyleObject->getSpaceAfter())
                : null,
            'lineHeight' => $this->extractLineHeight($paragraphStyleObject),
        ];
    }

    /**
     * @return array{indentLeft:?float, indentHanging:?float, tabStop:?float}
     */
    private function extractListLevelLayout(DocList $element): array
    {
        $styleName = $element->getStyle()?->getNumStyle();
        if (!is_string($styleName) || $styleName === '') {
            return ['indentLeft' => null, 'indentHanging' => null, 'tabStop' => null];
        }

        $style = Style::getStyle($styleName);
        if (!$style instanceof \PhpOffice\PhpWord\Style\Numbering) {
            return ['indentLeft' => null, 'indentHanging' => null, 'tabStop' => null];
        }

        $levels = $style->getLevels();
        $currentLevel = $levels[$element->getDepth()] ?? null;
        $fallbackLevel = $levels ? reset($levels) : null;
        $resolvedLevel = $currentLevel ?? $fallbackLevel;

        if (!is_object($resolvedLevel)) {
            return ['indentLeft' => null, 'indentHanging' => null, 'tabStop' => null];
        }

        return [
            'indentLeft' => $this->nullableTwipsToCm(
                $this->readNumericValueFromLevel($resolvedLevel, ['getLeft', 'getIndentLeft'])
            ),
            'indentHanging' => $this->nullableTwipsToCm(
                $this->readNumericValueFromLevel($resolvedLevel, ['getHanging', 'getIndentHanging'])
            ),
            'tabStop' => $this->nullableTwipsToCm(
                $this->readNumericValueFromLevel($resolvedLevel, ['getTabPos', 'getTabStop'])
            ),
        ];
    }

    private function readNumericValueFromLevel(object $level, array $getters): float|int|string|null
    {
        foreach ($getters as $getter) {
            if (!method_exists($level, $getter)) {
                continue;
            }

            $value = $level->{$getter}();
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function computeMarkerOffset(?float $tabStop, ?float $indentLeft): ?float
    {
        if ($tabStop === null || $indentLeft === null) {
            return null;
        }

        return round($tabStop - $indentLeft, 2);
    }

    /**
     * @return array{
     *     alignment:?string,
     *     indentLeft:?float,
     *     spacingBefore:?float,
     *     spacingAfter:?float,
     *     cellSpacing:?float,
     *     layout:?string,
     *     cellMargins:?array{top:?float,right:?float,bottom:?float,left:?float}
     * }
     */
    private function extractTableLayout(?TableStyle $tableStyle): array
    {
        if ($tableStyle === null) {
            return [
                'alignment' => null,
                'indentLeft' => null,
                'spacingBefore' => null,
                'spacingAfter' => null,
                'cellSpacing' => null,
                'layout' => null,
                'cellMargins' => null,
            ];
        }

        $cellMargins = [
            'top' => $this->nullableTwipsToCm($this->readNumericStyleValue($tableStyle, ['getCellMarginTop', 'getMarginTop'])),
            'right' => $this->nullableTwipsToCm($this->readNumericStyleValue($tableStyle, ['getCellMarginRight', 'getMarginRight'])),
            'bottom' => $this->nullableTwipsToCm($this->readNumericStyleValue($tableStyle, ['getCellMarginBottom', 'getMarginBottom'])),
            'left' => $this->nullableTwipsToCm($this->readNumericStyleValue($tableStyle, ['getCellMarginLeft', 'getMarginLeft'])),
        ];
        if ($cellMargins['top'] === null
            && $cellMargins['right'] === null
            && $cellMargins['bottom'] === null
            && $cellMargins['left'] === null) {
            $cellMargins = null;
        }

        return [
            'alignment' => $this->readStringStyleValue($tableStyle, ['getAlignment']),
            'indentLeft' => $this->nullableTwipsToCm($this->readNumericStyleValue($tableStyle, ['getIndent', 'getIndentLeft', 'getTblInd'])),
            'spacingBefore' => $this->nullableTwipsToCm($this->readNumericStyleValue($tableStyle, ['getSpaceBefore', 'getSpacingBefore'])),
            'spacingAfter' => $this->nullableTwipsToCm($this->readNumericStyleValue($tableStyle, ['getSpaceAfter', 'getSpacingAfter'])),
            'cellSpacing' => $this->nullableTwipsToCm($this->readNumericStyleValue($tableStyle, ['getCellSpacing'])),
            'layout' => $this->readStringStyleValue($tableStyle, ['getLayout']),
            'cellMargins' => $cellMargins,
        ];
    }

    private function readNumericStyleValue(object $style, array $getters): float|int|string|null
    {
        foreach ($getters as $getter) {
            if (!method_exists($style, $getter)) {
                continue;
            }

            $value = $style->{$getter}();
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function readStringStyleValue(object $style, array $getters): ?string
    {
        foreach ($getters as $getter) {
            if (!method_exists($style, $getter)) {
                continue;
            }

            $value = $style->{$getter}();
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
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

    private function extractCellStyle(?CellStyle $cellStyle): ?array
    {
        if ($cellStyle === null) {
            return null;
        }

        $borders = [];
        foreach (['top', 'right', 'bottom', 'left'] as $side) {
            $border = $this->readBorderFromStyle($cellStyle, $side);
            if ($this->isBorderDefined($border)) {
                $borders[$side] = $border;
            }
        }

        $backgroundColor = $cellStyle->getBgColor();
        if ($backgroundColor === '' || $backgroundColor === null) {
            $backgroundColor = null;
        }

        if ($borders === [] && $backgroundColor === null) {
            return null;
        }

        return [
            'borders' => $borders,
            'backgroundColor' => $backgroundColor,
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

    private function readBorderFromStyle(object $style, string $side): ?array
    {
        $suffix = ucfirst($side);
        $sizeGetter = 'getBorder' . $suffix . 'Size';
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

        return is_numeric($size) ? (float)$size > 0.0 : true;
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
