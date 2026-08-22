<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Unit\Ast\Pass;

use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;
use Publicplan\DocumentProcessor\Ast\Node\TextNode;
use Publicplan\DocumentProcessor\Ast\Node\ListItemNode;
use Publicplan\DocumentProcessor\Ast\Node\ListNode;
use Publicplan\DocumentProcessor\Ast\Metadata\ListFormat;
use Publicplan\DocumentProcessor\Ast\Pass\ListNormalizationPass;
use Publicplan\DocumentProcessor\Ast\Pass\ListContinuationPass;
use Publicplan\DocumentProcessor\Ast\Pass\ListSpacerPass;
use Publicplan\DocumentProcessor\Ast\Pass\EmptyParagraphPass;
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
}
