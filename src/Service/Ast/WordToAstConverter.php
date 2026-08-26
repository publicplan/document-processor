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
use PhpOffice\PhpWord\Style\ListItem as ListItemStyle;
use PhpOffice\PhpWord\Style\Numbering;
use PhpOffice\PhpWord\Style\NumberingLevel;
use PhpOffice\PhpWord\Style\Paragraph as ParagraphStyle;
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
                    $numberingMetadata = $this->extractNumberingMetadata($element, $context);
                    $computedSpacingAfter = $bottomSpacingCm + $spacerSpacingCm;
                    $effectiveSpacingAfter = $computedSpacingAfter > 0 ? $computedSpacingAfter : $listParagraphLayout['spacingAfter'];
                    $listItemNode = new ListItemNode(
                        numId: (int)($element->getStyle()?->getNumId() ?? 0),
                        depth: (int)$element->getDepth(),
                        numFormat: $this->mapListFormat($listConfig->tag, $listConfig->type),
                        startNumeration: $numberingMetadata['start'],
                        children: $this->convertInlineElements($element->getElements(), $context),
                        alignment: $listParagraphLayout['alignment'],
                        indentLeft: $listParagraphLayout['indentLeft'],
                        indentRight: $listParagraphLayout['indentRight'],
                        indentFirstLine: $listParagraphLayout['indentFirstLine'],
                        indentHanging: $listParagraphLayout['indentHanging'],
                        spacingBefore: $listParagraphLayout['spacingBefore'],
                        spacingAfter: $effectiveSpacingAfter,
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
                    $listItemNode
                        ->setStyleRefs($this->buildListStyleRefs($element, $context, $numberingMetadata))
                        ->setStyleProvenance($this->buildListStyleProvenance($element, $context, $listParagraphLayout, $listLevelLayout, $numberingMetadata))
                        ->setResolvedLayout($this->buildListResolvedLayout(
                            array_replace($listParagraphLayout, ['spacingAfter' => $effectiveSpacingAfter]),
                            $listLevelLayout,
                            $numberingMetadata
                        ));
                    $sectionNode->addParagraph($listItemNode);
                    continue;
                }

                if ($element instanceof DocText) {
                    $fontStyle = $element->getFontStyle();
                    $textNode = new TextNode(
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
                    $textNode->setStyleRef($this->extractCharacterStyleRef($fontStyle));
                    $sectionNode->addParagraph($textNode);
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
                    $paragraphStyle = $element->getParagraphStyle();
                    $paragraphLayout = $this->extractParagraphLayout($paragraphStyle, $context);
                    $paragraph = new ParagraphNode(
                        children: $this->convertInlineElements($element->getElements(), $context),
                        alignment: $paragraphLayout['resolved']['alignment'],
                        indentLeft: $paragraphLayout['resolved']['indentLeft'],
                        indentRight: $paragraphLayout['resolved']['indentRight'],
                        indentFirstLine: $paragraphLayout['resolved']['indentFirstLine'],
                        spacingBefore: $paragraphLayout['resolved']['spacingBefore'],
                        spacingAfter: $paragraphLayout['resolved']['spacingAfter'] ?? 0.0,
                        lineHeight: $paragraphLayout['resolved']['lineHeight'],
                        resolvedStyle: $this->extractParagraphStyle($paragraphStyle),
                        renderHints: new RenderHints([
                        ])
                    );
                    $paragraph
                        ->setStyleRefs(['paragraph' => $paragraphLayout['styleRef']])
                        ->setStyleRef($paragraphLayout['styleRef'])
                        ->setStyleProvenance($paragraphLayout['provenance'])
                        ->setResolvedLayout($this->buildParagraphResolvedLayout($paragraphLayout['resolved']));
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
                $textNode = new TextNode(
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
                $textNode->setStyleRef($this->extractCharacterStyleRef($fontStyle));
                $nodes[] = $textNode;
                continue;
            }

            if ($element instanceof DocLink) {
                $linkNode = new LinkNode(
                    href: $element->getSource(),
                    children: [
                        (new TextNode(
                            content: $element->getText(),
                            trackChange: ($element->getFontStyle()?->isStrikethrough() ?? false) ? TrackChangeType::Deleted : TrackChangeType::None
                        ))->setStyleRef($this->extractCharacterStyleRef($element->getFontStyle())),
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
        $rawTableStyle = $table->getStyle();
        $tableStyle = $this->resolveTableStyle($rawTableStyle);
        $tableLayout = $this->extractTableLayout($tableStyle);
        $tableStyleRef = $this->buildTableStyleRef($rawTableStyle, $context);
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
        $tableNode
            ->setStyleRefs(['table' => $tableStyleRef])
            ->setStyleRef($tableStyleRef)
            ->setStyleProvenance($this->buildTableStyleProvenance($tableStyleRef, $tableLayout))
            ->setResolvedLayout($this->buildTableResolvedLayout($tableLayout, $tableStyle));

        foreach ($table->getRows() as $row) {
            $rowStyle = $row->getStyle();
            $rowNode = new TableRowNode(isHeader: (bool)$rowStyle?->isTblHeader());
            $rowNode
                ->setStyleRef($rowStyle !== null ? [
                    'styleType' => 'direct',
                    'source' => 'document.xml',
                ] : null)
                ->setStyleProvenance([
                    'repeatHeader' => ['value' => (bool)$rowStyle?->isTblHeader(), 'source' => $rowStyle !== null ? 'direct' : 'rendererDefault'],
                    'cantSplit' => ['value' => (bool)$rowStyle?->isCantSplit(), 'source' => $rowStyle !== null ? 'direct' : 'rendererDefault'],
                    'height' => ['value' => $this->nullableTwipsToCm($row->getHeight()), 'source' => $row->getHeight() !== null ? 'direct' : 'rendererDefault'],
                ])
                ->setResolvedLayout([
                    'repeatHeader' => (bool)$rowStyle?->isTblHeader(),
                    'cantSplit' => (bool)$rowStyle?->isCantSplit(),
                    'height' => $this->nullableTwipsToCm($row->getHeight()),
                ]);
            foreach ($row->getCells() as $cell) {
                $cellStyle = $cell->getStyle();
                $cellNode = new TableCellNode(
                    width: $this->nullableNumericToInt($cell->getWidth()),
                    columnSpan: $this->nullableNumericToInt($cellStyle?->getGridSpan()),
                    rowSpan: $this->nullableNumericToInt($cellStyle?->getVMerge()),
                    resolvedStyle: $this->extractCellStyle($cellStyle)
                );
                $cellNode
                    ->setStyleRef($cellStyle !== null ? [
                        'styleType' => 'direct',
                        'source' => 'document.xml',
                    ] : null)
                    ->setStyleProvenance([
                        'verticalAlign' => ['value' => $cellStyle?->getVAlign(), 'source' => $cellStyle !== null ? 'direct' : 'rendererDefault'],
                        'textDirection' => ['value' => $cellStyle?->getTextDirection(), 'source' => $cellStyle !== null ? 'direct' : 'rendererDefault'],
                        'shading' => ['value' => $cellStyle?->getBgColor(), 'source' => $cellStyle !== null ? 'direct' : 'rendererDefault'],
                    ])
                    ->setResolvedLayout($this->buildTableCellResolvedLayout($cellStyle));

                foreach ($cell->getElements() as $cellElement) {
                    if ($cellElement instanceof DocTextRun) {
                        $legacyHtml = $this->textRunConverter->convert($cellElement, $context);
                        $paragraphStyle = $cellElement->getParagraphStyle();
                        $paragraphLayout = $this->extractParagraphLayout($paragraphStyle, $context);
                        $cellNode->addChild(new ParagraphNode(
                            children: $this->convertInlineElements($cellElement->getElements(), $context),
                            alignment: $paragraphLayout['resolved']['alignment'],
                            indentLeft: $paragraphLayout['resolved']['indentLeft'],
                            indentRight: $paragraphLayout['resolved']['indentRight'],
                            indentFirstLine: $paragraphLayout['resolved']['indentFirstLine'],
                            spacingBefore: $paragraphLayout['resolved']['spacingBefore'],
                            spacingAfter: $paragraphLayout['resolved']['spacingAfter'] ?? 0.0,
                            lineHeight: $paragraphLayout['resolved']['lineHeight'],
                            resolvedStyle: $this->extractParagraphStyle($paragraphStyle),
                            renderHints: new RenderHints([
                            ])
                        )->setStyleRefs(['paragraph' => $paragraphLayout['styleRef']])
                            ->setStyleRef($paragraphLayout['styleRef'])
                            ->setStyleProvenance($paragraphLayout['provenance'])
                            ->setResolvedLayout($this->buildParagraphResolvedLayout($paragraphLayout['resolved'])));
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
        $node = new TextBoxNode(renderHints: new RenderHints([
            'legacy_html' => $this->textBoxConverter->convert($textBox, $context),
        ]));
        $children = [];

        foreach ($textBox->getElements() as $element) {
            if ($element instanceof DocTextRun) {
                $children = array_merge($children, $this->convertInlineElements($element->getElements(), $context));
            } elseif ($element instanceof DocText) {
                $fontStyle = $element->getFontStyle();
                $children[] = (new TextNode(
                    content: $element->getText() ?? '',
                    bold: (bool)$fontStyle?->isBold(),
                    italic: (bool)$fontStyle?->isItalic(),
                    underline: ($fontStyle?->getUnderline() ?? 'none') !== 'none',
                    fontSize: $fontStyle?->getSize() !== null ? (float)$fontStyle->getSize() : null,
                    preserveSpace: false,
                    trackChange: (bool)$fontStyle?->isStrikethrough() ? TrackChangeType::Deleted : TrackChangeType::None,
                    renderHints: new RenderHints([])
                ))->setStyleRef($this->extractCharacterStyleRef($fontStyle));
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

    /**
     * @return array{
     *   styleRef:?array<string, mixed>,
     *   resolved:array{
     *      alignment:?string,
     *      indentLeft:?float,
     *      indentRight:?float,
     *      indentFirstLine:?float,
     *      indentHanging:?float,
     *      spacingBefore:?float,
     *      spacingAfter:?float,
     *      lineHeight:?float
     *   },
     *   provenance:array<string, array{value:mixed,source:string}>
     * }
     */
    private function extractParagraphLayout(object|string|null $paragraphStyle, ConversionContext $context): array
    {
        $snapshot = $this->getStyleSnapshot($context);
        $styleRef = $this->buildParagraphStyleRef($paragraphStyle, $snapshot);
        $resolvedIndentation = ParagraphIndentHelper::resolveEffectiveIndentation(
            $paragraphStyle instanceof ParagraphStyle || is_string($paragraphStyle) ? $paragraphStyle : null
        );
        $paragraph = $paragraphStyle instanceof ParagraphStyle ? $paragraphStyle : null;

        $resolved = [];
        $provenance = [];

        [$resolved['alignment'], $source] = $this->resolveParagraphField(
            'alignment',
            $paragraph?->getAlignment(),
            $styleRef,
            $snapshot,
            static fn (mixed $value): ?string => is_string($value) && $value !== '' ? $value : null
        );
        $provenance['alignment'] = ['value' => $resolved['alignment'], 'source' => $source];

        [$resolved['indentLeft'], $source] = $this->resolveParagraphField(
            'indentLeft',
            $paragraph?->getIndentLeft(),
            $styleRef,
            $snapshot,
            fn (mixed $value): ?float => $this->nullableTwipsToCm(is_numeric($value) ? (float)$value : null)
        );
        if ($resolved['indentLeft'] === null) {
            $resolved['indentLeft'] = $this->nullableTwipsToCm($resolvedIndentation['indentLeft']);
        }
        $provenance['indent.left'] = ['value' => $resolved['indentLeft'], 'source' => $source];

        [$resolved['indentRight'], $source] = $this->resolveParagraphField(
            'indentRight',
            $paragraph?->getIndentRight(),
            $styleRef,
            $snapshot,
            fn (mixed $value): ?float => $this->nullableTwipsToCm(is_numeric($value) ? (float)$value : null)
        );
        $provenance['indent.right'] = ['value' => $resolved['indentRight'], 'source' => $source];

        [$resolved['indentFirstLine'], $source] = $this->resolveParagraphField(
            'indentFirstLine',
            $paragraph?->getIndentFirstLine(),
            $styleRef,
            $snapshot,
            fn (mixed $value): ?float => $this->nullableTwipsToCm(is_numeric($value) ? (float)$value : null)
        );
        if ($resolved['indentFirstLine'] === null) {
            $resolved['indentFirstLine'] = $this->nullableTwipsToCm($resolvedIndentation['firstLine']);
        }
        $provenance['indent.firstLine'] = ['value' => $resolved['indentFirstLine'], 'source' => $source];

        [$resolved['indentHanging'], $source] = $this->resolveParagraphField(
            'indentHanging',
            $paragraph?->getHanging(),
            $styleRef,
            $snapshot,
            fn (mixed $value): ?float => $this->nullableTwipsToCm(is_numeric($value) ? (float)$value : null)
        );
        if ($resolved['indentHanging'] === null) {
            $resolved['indentHanging'] = $this->nullableTwipsToCm($resolvedIndentation['hanging']);
        }
        $provenance['indent.hanging'] = ['value' => $resolved['indentHanging'], 'source' => $source];

        [$resolved['spacingBefore'], $source] = $this->resolveParagraphField(
            'spacingBefore',
            $paragraph?->getSpaceBefore(),
            $styleRef,
            $snapshot,
            fn (mixed $value): ?float => $this->nullableTwipsToCm(is_numeric($value) ? (float)$value : null)
        );
        $provenance['spacing.before'] = ['value' => $resolved['spacingBefore'], 'source' => $source];

        [$resolved['spacingAfter'], $source] = $this->resolveParagraphField(
            'spacingAfter',
            $paragraph?->getSpaceAfter(),
            $styleRef,
            $snapshot,
            fn (mixed $value): ?float => $this->nullableTwipsToCm(is_numeric($value) ? (float)$value : null),
            0.0
        );
        $provenance['spacing.after'] = ['value' => $resolved['spacingAfter'], 'source' => $source];

        [$resolved['lineHeight'], $source] = $this->resolveParagraphField(
            'line',
            $paragraph?->getLineHeight(),
            $styleRef,
            $snapshot,
            static function (mixed $value): ?float {
                if ($value === null || $value === '') {
                    return null;
                }
                if (!is_numeric($value)) {
                    return null;
                }
                $numeric = (float)$value;
                return $numeric > 10 ? round($numeric / 240, 2) : $numeric;
            }
        );
        $provenance['spacing.line'] = ['value' => $resolved['lineHeight'], 'source' => $source];

        return [
            'styleRef' => $styleRef,
            'resolved' => $resolved,
            'provenance' => $provenance,
        ];
    }

    /**
     * @return array{0:mixed,1:string}
     */
    private function resolveParagraphField(
        string $field,
        mixed $directValue,
        ?array $styleRef,
        array $snapshot,
        callable $transform,
        mixed $rendererDefault = null
    ): array {
        $direct = $transform($directValue);
        if ($direct !== null) {
            return [$direct, 'direct'];
        }

        $styleId = is_array($styleRef) ? ($styleRef['styleId'] ?? null) : null;
        $paragraphStyles = $snapshot['styles']['paragraph'] ?? [];
        if (is_string($styleId) && $styleId !== '' && isset($paragraphStyles[$styleId][$field])) {
            $value = $transform($paragraphStyles[$styleId][$field]);
            if ($value !== null) {
                return [$value, 'style'];
            }
        }

        $chain = is_array($styleRef) && is_array($styleRef['basedOnChain'] ?? null)
            ? $styleRef['basedOnChain']
            : [];
        foreach ($chain as $chainStyleId) {
            if (!is_string($chainStyleId) || $chainStyleId === $styleId || !isset($paragraphStyles[$chainStyleId])) {
                continue;
            }
            $value = $transform($paragraphStyles[$chainStyleId][$field] ?? null);
            if ($value !== null) {
                return [$value, 'basedOn'];
            }
        }

        $defaults = $snapshot['styles']['defaults']['paragraph'] ?? [];
        $defaultValue = $transform($defaults[$field] ?? null);
        if ($defaultValue !== null) {
            return [$defaultValue, 'default'];
        }

        return [$rendererDefault, 'rendererDefault'];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>|null
     */
    private function buildParagraphStyleRef(object|string|null $paragraphStyle, array $snapshot): ?array
    {
        $styleId = null;
        if ($paragraphStyle instanceof ParagraphStyle) {
            $styleId = $paragraphStyle->getStyleName();
        } elseif (is_string($paragraphStyle) && $paragraphStyle !== '') {
            $styleId = $paragraphStyle;
        }

        if (!is_string($styleId) || $styleId === '') {
            return null;
        }

        $styleName = $snapshot['styles']['paragraph'][$styleId]['styleName'] ?? null;
        $basedOnChain = $this->buildBasedOnChain($styleId, 'paragraph', $snapshot);

        return [
            'styleId' => $styleId,
            'styleName' => is_string($styleName) && $styleName !== '' ? $styleName : $styleId,
            'styleType' => 'paragraph',
            'source' => 'styles.xml',
            'basedOnChain' => $basedOnChain,
        ];
    }

    /**
     * @param array<string, mixed> $resolved
     * @return array<string, mixed>
     */
    private function buildParagraphResolvedLayout(array $resolved): array
    {
        return [
            'alignment' => $resolved['alignment'] ?? null,
            'indent' => [
                'left' => $resolved['indentLeft'] ?? null,
                'right' => $resolved['indentRight'] ?? null,
                'firstLine' => $resolved['indentFirstLine'] ?? null,
                'hanging' => $resolved['indentHanging'] ?? null,
            ],
            'spacing' => [
                'before' => $resolved['spacingBefore'] ?? null,
                'after' => $resolved['spacingAfter'] ?? null,
                'line' => $resolved['lineHeight'] ?? null,
            ],
        ];
    }

    private function extractCharacterStyleRef(?object $fontStyle): ?array
    {
        if ($fontStyle === null || !method_exists($fontStyle, 'getStyleName')) {
            return null;
        }

        $styleId = $fontStyle->getStyleName();
        if (!is_string($styleId) || $styleId === '') {
            return null;
        }

        return [
            'styleId' => $styleId,
            'styleName' => $styleId,
            'styleType' => 'character',
            'source' => 'styles.xml',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractNumberingMetadata(DocList $element, ConversionContext $context): array
    {
        $style = $element->getStyle();
        $numId = $this->nullableNumericToInt($style?->getNumId());
        $depth = (int)$element->getDepth();
        $styleName = $style instanceof ListItemStyle ? $style->getNumStyle() : null;

        $numberingStyle = is_string($styleName) && $styleName !== ''
            ? Style::getStyle($styleName)
            : null;
        $numberingStyle = $numberingStyle instanceof Numbering ? $numberingStyle : null;

        $levelObject = null;
        if ($numberingStyle instanceof Numbering) {
            $levels = $numberingStyle->getLevels();
            $levelObject = $levels[$depth] ?? ($levels ? reset($levels) : null);
        }
        $levelObject = $levelObject instanceof NumberingLevel ? $levelObject : null;

        $snapshot = $this->getStyleSnapshot($context);
        $numMap = $snapshot['numbering']['numMap'] ?? [];
        $abstractNumId = $numId !== null ? ($numMap[(string)$numId]['abstractNumId'] ?? null) : null;
        $levelSnapshot = [];
        if ($abstractNumId !== null) {
            $levelSnapshot = $snapshot['numbering']['levels'][(string)$abstractNumId][(string)$depth] ?? [];
        }

        $leftTwips = $levelSnapshot['left'] ?? $levelObject?->getLeft();
        $hangingTwips = $levelSnapshot['hanging'] ?? $levelObject?->getHanging();
        $tabTwips = $levelSnapshot['tabStop'] ?? $levelObject?->getTabPos();

        return [
            'numId' => $numId,
            'numStyleId' => is_string($styleName) && $styleName !== '' ? $styleName : null,
            'abstractNumId' => is_numeric($abstractNumId) ? (int)$abstractNumId : null,
            'level' => $depth,
            'start' => $this->nullableNumericToInt($levelSnapshot['start'] ?? $levelObject?->getStart()),
            'format' => is_string($levelSnapshot['format'] ?? null) ? $levelSnapshot['format'] : $levelObject?->getFormat(),
            'text' => is_string($levelSnapshot['text'] ?? null) ? $levelSnapshot['text'] : $levelObject?->getText(),
            'suffix' => is_string($levelSnapshot['suffix'] ?? null) ? $levelSnapshot['suffix'] : $levelObject?->getSuffix(),
            'justification' => is_string($levelSnapshot['alignment'] ?? null) ? $levelSnapshot['alignment'] : $levelObject?->getAlignment(),
            'restart' => $this->nullableNumericToInt($levelSnapshot['restart'] ?? $levelObject?->getRestart()),
            'indentLeft' => $this->nullableTwipsToCm(is_numeric($leftTwips) ? (float)$leftTwips : null),
            'indentHanging' => $this->nullableTwipsToCm(is_numeric($hangingTwips) ? (float)$hangingTwips : null),
            'tabStop' => $this->nullableTwipsToCm(is_numeric($tabTwips) ? (float)$tabTwips : null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListStyleRefs(
        DocList $element,
        ConversionContext $context,
        array $numberingMetadata
    ): array {
        $paragraphStyleRef = $this->extractParagraphLayout($element->getParagraphStyle(), $context)['styleRef'];

        return [
            'paragraph' => $paragraphStyleRef,
            'numbering' => [
                'numId' => $numberingMetadata['numId'] ?? null,
                'abstractNumId' => $numberingMetadata['abstractNumId'] ?? null,
                'level' => $numberingMetadata['level'] ?? null,
                'numStyleId' => $numberingMetadata['numStyleId'] ?? null,
                'source' => 'numbering.xml',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListStyleProvenance(
        DocList $element,
        ConversionContext $context,
        array $listParagraphLayout,
        array $listLevelLayout,
        array $numberingMetadata
    ): array {
        $paragraphProvenance = $this->extractParagraphLayout($element->getParagraphStyle(), $context)['provenance'];
        $paragraphProvenance['level.indentLeft'] = [
            'value' => $listLevelLayout['indentLeft'],
            'source' => $listLevelLayout['indentLeft'] !== null ? 'numberingLevel' : 'rendererDefault',
        ];
        $paragraphProvenance['level.indentHanging'] = [
            'value' => $listLevelLayout['indentHanging'],
            'source' => $listLevelLayout['indentHanging'] !== null ? 'numberingLevel' : 'rendererDefault',
        ];
        $paragraphProvenance['level.tabStop'] = [
            'value' => $listLevelLayout['tabStop'],
            'source' => $listLevelLayout['tabStop'] !== null ? 'numberingLevel' : 'rendererDefault',
        ];
        $paragraphProvenance['marker.format'] = [
            'value' => $numberingMetadata['format'] ?? null,
            'source' => $numberingMetadata['format'] !== null ? 'numberingLevel' : 'rendererDefault',
        ];
        $paragraphProvenance['marker.text'] = [
            'value' => $numberingMetadata['text'] ?? null,
            'source' => $numberingMetadata['text'] !== null ? 'numberingLevel' : 'rendererDefault',
        ];
        $paragraphProvenance['marker.start'] = [
            'value' => $numberingMetadata['start'] ?? null,
            'source' => $numberingMetadata['start'] !== null ? 'numberingLevel' : 'rendererDefault',
        ];
        $paragraphProvenance['marker.suffix'] = [
            'value' => $numberingMetadata['suffix'] ?? null,
            'source' => $numberingMetadata['suffix'] !== null ? 'numberingLevel' : 'rendererDefault',
        ];
        $paragraphProvenance['marker.justification'] = [
            'value' => $numberingMetadata['justification'] ?? null,
            'source' => $numberingMetadata['justification'] !== null ? 'numberingLevel' : 'rendererDefault',
        ];

        return $paragraphProvenance;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildListResolvedLayout(array $paragraphLayout, array $levelLayout, array $numberingMetadata): array
    {
        return [
            'alignment' => $paragraphLayout['alignment'],
            'indent' => [
                'left' => $paragraphLayout['indentLeft'],
                'right' => $paragraphLayout['indentRight'],
                'firstLine' => $paragraphLayout['indentFirstLine'],
                'hanging' => $paragraphLayout['indentHanging'],
            ],
            'spacing' => [
                'before' => $paragraphLayout['spacingBefore'],
                'after' => $paragraphLayout['spacingAfter'],
                'line' => $paragraphLayout['lineHeight'],
            ],
            'level' => [
                'indentLeft' => $levelLayout['indentLeft'],
                'indentHanging' => $levelLayout['indentHanging'],
                'tabStop' => $levelLayout['tabStop'],
                'markerOffset' => $this->computeMarkerOffset($levelLayout['tabStop'], $levelLayout['indentLeft']),
            ],
            'marker' => [
                'format' => $numberingMetadata['format'] ?? null,
                'text' => $numberingMetadata['text'] ?? null,
                'start' => $numberingMetadata['start'] ?? null,
                'suffix' => $numberingMetadata['suffix'] ?? null,
                'justification' => $numberingMetadata['justification'] ?? null,
                'restart' => $numberingMetadata['restart'] ?? null,
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $tableStyleRef
     * @return array<string, mixed>
     */
    private function buildTableStyleProvenance(?array $tableStyleRef, array $tableLayout): array
    {
        $styleSource = $tableStyleRef !== null ? 'style' : 'direct';
        return [
            'alignment' => ['value' => $tableLayout['alignment'], 'source' => $tableLayout['alignment'] !== null ? $styleSource : 'rendererDefault'],
            'indent.left' => ['value' => $tableLayout['indentLeft'], 'source' => $tableLayout['indentLeft'] !== null ? $styleSource : 'rendererDefault'],
            'spacing.before' => ['value' => $tableLayout['spacingBefore'], 'source' => $tableLayout['spacingBefore'] !== null ? $styleSource : 'rendererDefault'],
            'spacing.after' => ['value' => $tableLayout['spacingAfter'], 'source' => $tableLayout['spacingAfter'] !== null ? $styleSource : 'rendererDefault'],
            'cellSpacing' => ['value' => $tableLayout['cellSpacing'], 'source' => $tableLayout['cellSpacing'] !== null ? $styleSource : 'rendererDefault'],
            'layout' => ['value' => $tableLayout['layout'], 'source' => $tableLayout['layout'] !== null ? $styleSource : 'rendererDefault'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTableResolvedLayout(array $tableLayout, ?TableStyle $tableStyle): array
    {
        return [
            'alignment' => $tableLayout['alignment'],
            'indent' => ['left' => $tableLayout['indentLeft']],
            'spacing' => [
                'before' => $tableLayout['spacingBefore'],
                'after' => $tableLayout['spacingAfter'],
            ],
            'cellSpacing' => $tableLayout['cellSpacing'],
            'layout' => $tableLayout['layout'],
            'cellMargins' => $tableLayout['cellMargins'],
            'borders' => $tableStyle !== null ? $this->extractTableBorderContext($tableStyle) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTableCellResolvedLayout(?CellStyle $cellStyle): array
    {
        return [
            'verticalAlign' => $cellStyle?->getVAlign(),
            'textDirection' => $cellStyle?->getTextDirection(),
            'margins' => [
                'top' => $this->nullableTwipsToCm($cellStyle?->getPaddingTop()),
                'right' => $this->nullableTwipsToCm($cellStyle?->getPaddingRight()),
                'bottom' => $this->nullableTwipsToCm($cellStyle?->getPaddingBottom()),
                'left' => $this->nullableTwipsToCm($cellStyle?->getPaddingLeft()),
            ],
            'borders' => $cellStyle !== null ? [
                'top' => $this->readBorderFromStyle($cellStyle, 'top'),
                'right' => $this->readBorderFromStyle($cellStyle, 'right'),
                'bottom' => $this->readBorderFromStyle($cellStyle, 'bottom'),
                'left' => $this->readBorderFromStyle($cellStyle, 'left'),
            ] : null,
            'shading' => $cellStyle?->getBgColor(),
        ];
    }

    /**
     * @param null|string|TableStyle $rawTableStyle
     * @return array<string, mixed>|null
     */
    private function buildTableStyleRef(null|string|TableStyle $rawTableStyle, ConversionContext $context): ?array
    {
        $snapshot = $this->getStyleSnapshot($context);
        $styleId = null;
        if (is_string($rawTableStyle) && $rawTableStyle !== '') {
            $styleId = $rawTableStyle;
        } elseif ($rawTableStyle instanceof TableStyle && is_string($rawTableStyle->getStyleName()) && $rawTableStyle->getStyleName() !== '') {
            $styleId = $rawTableStyle->getStyleName();
        }

        if (!is_string($styleId) || $styleId === '') {
            return null;
        }

        $styleName = $snapshot['styles']['table'][$styleId]['styleName'] ?? null;
        return [
            'styleId' => $styleId,
            'styleName' => is_string($styleName) && $styleName !== '' ? $styleName : $styleId,
            'styleType' => 'table',
            'source' => 'styles.xml',
            'basedOnChain' => $this->buildBasedOnChain($styleId, 'table', $snapshot),
        ];
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return string[]
     */
    private function buildBasedOnChain(string $styleId, string $styleType, array $snapshot): array
    {
        $chain = [];
        $visited = [];
        $current = $styleId;
        $styles = $snapshot['styles'][$styleType] ?? [];

        while (is_string($current) && $current !== '' && !isset($visited[$current])) {
            $visited[$current] = true;
            $chain[] = $current;
            $next = $styles[$current]['basedOn'] ?? null;
            $current = is_string($next) && $next !== '' ? $next : '';
        }

        return $chain;
    }

    /**
     * @return array<string, mixed>
     */
    private function getStyleSnapshot(ConversionContext $context): array
    {
        $snapshot = $context->getStyleSnapshot();
        return is_array($snapshot) ? $snapshot : [];
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
                // Handle TblWidth objects (returned by getTblInd())
                if ($value instanceof \PhpOffice\PhpWord\ComplexType\TblWidth) {
                    $value = $value->getValue();
                }
                if ($value !== null && $value !== '') {
                    return $value;
                }
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
