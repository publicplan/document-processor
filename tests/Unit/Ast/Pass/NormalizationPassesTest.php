<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Unit\Ast\Pass;

use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Ast\Node\BorderGroupNode;
use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\ListItemNode;
use Publicplan\DocumentProcessor\Ast\Node\ListNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\TextNode;
use Publicplan\DocumentProcessor\Ast\Pass\BorderGroupingPass;
use Publicplan\DocumentProcessor\Ast\Metadata\ListFormat;
use Publicplan\DocumentProcessor\Ast\Pass\EmptyParagraphPass;
use Publicplan\DocumentProcessor\Ast\Pass\ListContinuationPass;
use Publicplan\DocumentProcessor\Ast\Pass\ListNormalizationPass;
use Publicplan\DocumentProcessor\Ast\Pass\ListSpacerPass;
use Publicplan\DocumentProcessor\Ast\Pass\NormalizationPipelineFactory;

class NormalizationPassesTest extends TestCase
{
    public function test_list_normalization_pass_groups_adjacent_items(): void
    {
        // Gegeben: zwei aufeinanderfolgende ListItemNodes mit gleicher numId
        $doc = new DocumentNode();
        $section = new SectionNode();
        
        $item1 = new ListItemNode(numId: 1, depth: 0, numFormat: ListFormat::Bullet);
        $item1->addChild(new TextNode('Item 1'));
        
        $item2 = new ListItemNode(numId: 1, depth: 0, numFormat: ListFormat::Bullet);
        $item2->addChild(new TextNode('Item 2'));
        
        $section->addParagraph($item1);
        $section->addParagraph($item2);
        $doc->addSection($section);

        // Wenn: ListNormalizationPass ausgeführt wird
        $pass = new ListNormalizationPass();
        $result = $pass->apply($doc);

        // Dann: Items sind in einer ListNode gruppiert
        $paragraphs = $result->getSections()[0]->getParagraphs();
        $this->assertCount(1, $paragraphs);
        $this->assertInstanceOf(ListNode::class, $paragraphs[0]);
        $this->assertCount(2, $paragraphs[0]->getItems());
    }

    public function test_list_normalization_pass_creates_separate_lists_for_different_numids(): void
    {
        // Gegeben: ListItemNodes mit verschiedenen numIds
        $doc = new DocumentNode();
        $section = new SectionNode();
        
        $item1 = new ListItemNode(numId: 1, depth: 0, numFormat: ListFormat::Bullet);
        $item1->addChild(new TextNode('Item 1'));
        
        $item2 = new ListItemNode(numId: 2, depth: 0, numFormat: ListFormat::Number);
        $item2->addChild(new TextNode('Item 1'));
        
        $section->addParagraph($item1);
        $section->addParagraph($item2);
        $doc->addSection($section);

        // Wenn: ListNormalizationPass ausgeführt wird
        $pass = new ListNormalizationPass();
        $result = $pass->apply($doc);

        // Dann: Es werden zwei separate ListNodes erstellt
        $paragraphs = $result->getSections()[0]->getParagraphs();
        $this->assertCount(2, $paragraphs);
        $this->assertInstanceOf(ListNode::class, $paragraphs[0]);
        $this->assertInstanceOf(ListNode::class, $paragraphs[1]);
    }

    public function test_empty_paragraph_pass_removes_trailing_empty_paragraphs(): void
    {
        // Gegeben: Dokument mit leeren Absätzen am Ende
        $doc = new DocumentNode();
        $section = new SectionNode();
        
        $para1 = new ParagraphNode();
        $para1->addChild(new TextNode('Content'));
        
        $emptyPara = new ParagraphNode(); // Leer
        
        $section->addParagraph($para1);
        $section->addParagraph($emptyPara);
        $doc->addSection($section);

        // Wenn: EmptyParagraphPass ausgeführt wird
        $pass = new EmptyParagraphPass();
        $result = $pass->apply($doc);

        // Dann: Leere Absätze am Ende werden entfernt
        $paragraphs = $result->getSections()[0]->getParagraphs();
        $this->assertCount(1, $paragraphs);
        $this->assertInstanceOf(ParagraphNode::class, $paragraphs[0]);
    }

    public function test_normalization_pipeline_factory_creates_all_passes(): void
    {
        // Gegeben: Standard-Pipeline
        $pipeline = NormalizationPipelineFactory::createStandardPipeline();

        // Wenn: Pipeline beschrieben wird
        $description = $pipeline->describeOrder();

        // Dann: Alle 7 Passes sind dokumentiert
        $this->assertStringContainsString('ListNormalization', $description);
        $this->assertStringContainsString('ListContinuation', $description);
        $this->assertStringContainsString('ListSpacer', $description);
        $this->assertStringContainsString('BorderGrouping', $description);
        $this->assertStringContainsString('EmptyParagraph', $description);
        $this->assertStringContainsString('InlineScale', $description);
        $this->assertStringContainsString('HangingIndent', $description);
    }

    public function test_pipeline_normalizes_document_end_to_end(): void
    {
        // Gegeben: Dokument mit Mixed-Content (Listen, Absätze, leere Absätze)
        $doc = new DocumentNode();
        $section = new SectionNode();
        
        $item1 = new ListItemNode(numId: 1, depth: 0, numFormat: ListFormat::Bullet);
        $item1->addChild(new TextNode('List Item 1'));
        
        $item2 = new ListItemNode(numId: 1, depth: 0, numFormat: ListFormat::Bullet);
        $item2->addChild(new TextNode('List Item 2'));
        
        $para = new ParagraphNode();
        $para->addChild(new TextNode('Normal paragraph'));
        
        $section->addParagraph($item1);
        $section->addParagraph($item2);
        $section->addParagraph($para);
        $doc->addSection($section);

        // Wenn: Komplette Pipeline ausgeführt wird
        $pipeline = NormalizationPipelineFactory::createStandardPipeline();
        $result = $pipeline->normalize($doc);

        // Dann: AST ist normalisiert
        $this->assertTrue($result['document'] instanceof DocumentNode);
        $this->assertTrue(count($result['passes']) > 0);
        
        // Und: Alle Passes waren erfolgreich
        foreach ($result['passes'] as $pass) {
            $this->assertTrue($pass['success'], "Pass {$pass['name']} failed: {$pass['error']}");
        }
    }

    public function test_text_coalescing_pass_reaches_paragraphs_inside_border_groups(): void
    {
        $paragraph = new ParagraphNode(children: [
            new TextNode('[', bold: true),
            new TextNode('Einrichtungsname', bold: true),
            new TextNode(']', bold: true),
        ]);

        $doc = new DocumentNode([
            new SectionNode([
                new BorderGroupNode([$paragraph]),
            ]),
        ]);

        $pipeline = NormalizationPipelineFactory::createStandardPipeline();
        $result = $pipeline->normalize($doc);

        $borderGroup = $result['document']->getSections()[0]->getParagraphs()[0];
        $normalizedParagraph = $borderGroup->getChildren()[0];

        $this->assertInstanceOf(BorderGroupNode::class, $borderGroup);
        $this->assertInstanceOf(ParagraphNode::class, $normalizedParagraph);
        $this->assertCount(1, $normalizedParagraph->getChildren());
        $this->assertSame('[Einrichtungsname]', $normalizedParagraph->getChildren()[0]->getContent());
    }

    public function test_border_grouping_pass_keeps_styled_spacer_paragraphs_in_group(): void
    {
        $style = [
            'borderTop' => ['size' => 4, 'color' => '000000', 'style' => 'single'],
            'borderLeft' => ['size' => 4, 'color' => '000000', 'style' => 'single'],
            'borderRight' => ['size' => 4, 'color' => '000000', 'style' => 'single'],
            'borderBottom' => ['size' => 4, 'color' => '000000', 'style' => 'single'],
        ];

        $doc = new DocumentNode([
            new SectionNode([
                new ParagraphNode(children: [new TextNode('Border 1')], resolvedStyle: $style),
                new ParagraphNode(children: [new TextNode('&nbsp;')], resolvedStyle: $style),
                new ParagraphNode(children: [new TextNode('Border 2')], resolvedStyle: $style),
            ]),
        ]);

        $result = (new BorderGroupingPass())->apply($doc);
        $paragraphs = $result->getSections()[0]->getParagraphs();

        $this->assertCount(1, $paragraphs);
        $this->assertInstanceOf(BorderGroupNode::class, $paragraphs[0]);
        $this->assertCount(3, $paragraphs[0]->getChildren());
    }

    public function test_list_normalization_pass_attaches_nested_items_as_children(): void
    {
        // Gegeben: Depth-0 Items mit nachfolgenden Depth-1 (nested) Items mit gleicher numId
        $doc = new DocumentNode();
        $section = new SectionNode();
        
        // Top-level item 1
        $item1 = new ListItemNode(numId: 1, depth: 0, numFormat: ListFormat::Number);
        $item1->addChild(new TextNode('Item 1'));
        
        // Top-level item 2
        $item2 = new ListItemNode(numId: 1, depth: 0, numFormat: ListFormat::Number);
        $item2->addChild(new TextNode('Item 2'));
        
        // Nested items under item 2 (with different format to differentiate)
        $nestedItem1 = new ListItemNode(numId: 1, depth: 1, numFormat: ListFormat::LetterLower);
        $nestedItem1->addChild(new TextNode('Item 2a'));
        
        $nestedItem2 = new ListItemNode(numId: 1, depth: 1, numFormat: ListFormat::LetterLower);
        $nestedItem2->addChild(new TextNode('Item 2b'));
        
        $section->addParagraph($item1);
        $section->addParagraph($item2);
        $section->addParagraph($nestedItem1);
        $section->addParagraph($nestedItem2);
        $doc->addSection($section);

        // Wenn: ListNormalizationPass ausgeführt wird
        $pass = new ListNormalizationPass();
        $result = $pass->apply($doc);

        // Dann: Es gibt eine ListNode mit 2 Top-Level-Items
        $paragraphs = $result->getSections()[0]->getParagraphs();
        $this->assertCount(1, $paragraphs);
        $this->assertInstanceOf(ListNode::class, $paragraphs[0]);
        $this->assertCount(2, $paragraphs[0]->getItems());
        
        // Und: Item 1 hat keine Kinder-ListItems
        $item1Result = $paragraphs[0]->getItems()[0];
        $this->assertInstanceOf(ListItemNode::class, $item1Result);
        $this->assertEquals(0, $item1Result->getDepth());
        $this->assertEmpty(array_filter($item1Result->getChildren(), 
            fn($child) => $child instanceof ListItemNode));
        
        // Und: Item 2 hat die 2 nested Items als Kinder
        $item2Result = $paragraphs[0]->getItems()[1];
        $this->assertInstanceOf(ListItemNode::class, $item2Result);
        $this->assertEquals(0, $item2Result->getDepth());
        $nestedListItems = array_filter($item2Result->getChildren(), 
            fn($child) => $child instanceof ListItemNode);
        $this->assertCount(2, $nestedListItems);
        
        // Und: Die nested Items haben depth=1
        foreach ($nestedListItems as $nested) {
            $this->assertEquals(1, $nested->getDepth());
        }
    }

    public function test_list_normalization_pass_attaches_deep_nested_items_recursively(): void
    {
        $doc = new DocumentNode();
        $section = new SectionNode();

        $item1 = new ListItemNode(numId: 1, depth: 0, numFormat: ListFormat::Number);
        $item1->addChild(new TextNode('Item 1'));

        $nestedItem = new ListItemNode(numId: 1, depth: 1, numFormat: ListFormat::LetterLower);
        $nestedItem->addChild(new TextNode('Item 1a'));

        $deepNestedItem = new ListItemNode(numId: 1, depth: 2, numFormat: ListFormat::RomanLower);
        $deepNestedItem->addChild(new TextNode('Item 1a.i'));

        $section->addParagraph($item1);
        $section->addParagraph($nestedItem);
        $section->addParagraph($deepNestedItem);
        $doc->addSection($section);

        $pass = new ListNormalizationPass();
        $result = $pass->apply($doc);

        $paragraphs = $result->getSections()[0]->getParagraphs();
        $this->assertCount(1, $paragraphs);
        $this->assertInstanceOf(ListNode::class, $paragraphs[0]);

        $topItem = $paragraphs[0]->getItems()[0];
        $this->assertInstanceOf(ListItemNode::class, $topItem);

        $firstLevelNestedItems = array_values(array_filter(
            $topItem->getChildren(),
            static fn ($child): bool => $child instanceof ListItemNode
        ));
        $this->assertCount(1, $firstLevelNestedItems);
        $this->assertSame(1, $firstLevelNestedItems[0]->getDepth());

        $secondLevelNestedItems = array_values(array_filter(
            $firstLevelNestedItems[0]->getChildren(),
            static fn ($child): bool => $child instanceof ListItemNode
        ));
        $this->assertCount(1, $secondLevelNestedItems);
        $this->assertSame(2, $secondLevelNestedItems[0]->getDepth());
    }
}
