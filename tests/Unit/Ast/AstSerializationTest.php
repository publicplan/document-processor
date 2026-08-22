<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Unit\Ast;

use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;
use Publicplan\DocumentProcessor\Ast\Node\TextNode;
use Publicplan\DocumentProcessor\Ast\Node\TabNode;
use Publicplan\DocumentProcessor\Ast\Node\BreakNode;
use Publicplan\DocumentProcessor\Ast\Node\ListItemNode;
use Publicplan\DocumentProcessor\Ast\Node\TableNode;
use Publicplan\DocumentProcessor\Ast\Node\TableRowNode;
use Publicplan\DocumentProcessor\Ast\Node\TableCellNode;
use Publicplan\DocumentProcessor\Ast\Node\LinkNode;
use Publicplan\DocumentProcessor\Ast\Node\TextBoxNode;
use Publicplan\DocumentProcessor\Ast\Metadata\SourceReference;
use Publicplan\DocumentProcessor\Ast\Metadata\TrackChangeType;
use Publicplan\DocumentProcessor\Ast\Metadata\ListFormat;

class AstSerializationTest extends TestCase
{
    public function test_simple_text_node_serializes_to_array(): void
    {
        $text = new TextNode('Hello World');
        $array = $text->toArray();

        $this->assertArrayHasKey('type', $array);
        $this->assertEquals('text', $array['type']);
        $this->assertEquals('Hello World', $array['content']);
    }

    public function test_paragraph_with_children_serializes(): void
    {
        $para = new ParagraphNode();
        $para->addChild(new TextNode('First '));
        $para->addChild(new TabNode());
        $para->addChild(new TextNode('Second'));

        $array = $para->toArray();

        $this->assertEquals('paragraph', $array['type']);
        $this->assertCount(3, $array['children']);
        $this->assertEquals('text', $array['children'][0]['type']);
        $this->assertEquals('tab', $array['children'][1]['type']);
    }

    public function test_break_node_serializes(): void
    {
        $break = new BreakNode('page');
        $array = $break->toArray();

        $this->assertEquals('break', $array['type']);
        $this->assertEquals('page', $array['breakType']);
    }

    public function test_list_item_with_format_serializes(): void
    {
        $item = new ListItemNode(
            numId: 1,
            depth: 0,
            numFormat: ListFormat::Bullet
        );
        $item->addChild(new TextNode('Item 1'));

        $array = $item->toArray();

        $this->assertEquals('listItem', $array['type']);
        $this->assertEquals(1, $array['numId']);
        $this->assertEquals(0, $array['depth']);
        $this->assertEquals('bullet', $array['numFormat']);
    }

    public function test_table_structure_serializes(): void
    {
        $table = new TableNode();

        $row1 = new TableRowNode(isHeader: true);
        $cell1 = new TableCellNode();
        $cell1->addChild(new TextNode('Header 1'));
        $row1->addCell($cell1);

        $row2 = new TableRowNode();
        $cell2 = new TableCellNode();
        $cell2->addChild(new TextNode('Data 1'));
        $row2->addCell($cell2);

        $table->addRow($row1);
        $table->addRow($row2);

        $array = $table->toArray();

        $this->assertEquals('table', $array['type']);
        $this->assertCount(2, $array['rows']);
        $this->assertTrue($array['rows'][0]['isHeader']);
        $this->assertFalse($array['rows'][1]['isHeader']);
    }

    public function test_text_with_track_changes_serializes(): void
    {
        $text = new TextNode(
            content: 'Deleted text',
            trackChange: TrackChangeType::Deleted
        );

        $array = $text->toArray();

        $this->assertEquals('deleted', $array['trackChange']);
    }

    public function test_link_node_serializes(): void
    {
        $link = new LinkNode(
            href: 'https://example.com',
            anchor: 'section-1'
        );
        $link->addChild(new TextNode('Click here'));

        $array = $link->toArray();

        $this->assertEquals('link', $array['type']);
        $this->assertEquals('https://example.com', $array['href']);
        $this->assertEquals('section-1', $array['anchor']);
    }

    public function test_source_reference_metadata_serializes(): void
    {
        $sourceRef = new SourceReference(
            part: 'document',
            sectionIndex: 0,
            elementIndex: 5,
            xmlPath: '/w:document/w:body/w:p[6]',
            xmlAttributes: ['xml:space' => 'preserve']
        );

        $text = new TextNode('Preserved', sourceRef: $sourceRef);
        $array = $text->toArray();

        $this->assertNotNull($array['metadata']['sourceRef']);
        $this->assertEquals('document', $array['metadata']['sourceRef']['part']);
        $this->assertEquals(0, $array['metadata']['sourceRef']['sectionIndex']);
        $this->assertEquals(5, $array['metadata']['sourceRef']['elementIndex']);
    }

    public function test_complete_document_tree_serializes(): void
    {
        $doc = new DocumentNode();

        $section = new SectionNode();
        $para = new ParagraphNode();
        $para->addChild(new TextNode('Hello '));
        $para->addChild(new TextNode('World', bold: true));
        $section->addParagraph($para);

        $doc->addSection($section);

        $array = $doc->toArray();
        $jsonString = json_encode($array);

        $this->assertNotNull($jsonString);
        $this->assertTrue(is_string($jsonString));

        $decoded = json_decode($jsonString, true);
        $this->assertEquals('document', $decoded['type']);
        $this->assertCount(1, $decoded['sections']);
    }
}
