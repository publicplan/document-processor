<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Unit\Ast\Pass;

use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Ast\Pass\TemplateAnnotationPass;
use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\TabNode;
use Publicplan\DocumentProcessor\Ast\Node\TextNode;
use Publicplan\DocumentProcessor\Service\Ast\Template\GenericTemplateSyntaxProfile;

class TemplateAnnotationPassTest extends TestCase
{
    public function test_it_annotates_placeholder_fragments_across_multiple_text_nodes(): void
    {
        $paragraph = new ParagraphNode(children: [
            new TextNode('Vor '),
            new TextNode('{{na'),
            new TextNode('me}}'),
            new TabNode(),
            new TextNode('nachher'),
        ]);

        $section = new SectionNode([$paragraph]);
        $document = new DocumentNode([$section]);

        $pass = new TemplateAnnotationPass(new GenericTemplateSyntaxProfile());
        $pass->apply($document);

        $children = $paragraph->getChildren();
        $firstFragment = $children[1]->getSourceRef()?->toArray()['xmlAttributes']['templateAnnotations'][0] ?? null;
        $secondFragment = $children[2]->getSourceRef()?->toArray()['xmlAttributes']['templateAnnotations'][0] ?? null;

        $this->assertNotNull($firstFragment);
        $this->assertNotNull($secondFragment);
        $this->assertSame('placeholder', $firstFragment['kind']);
        $this->assertSame('complete', $firstFragment['status']);
        $this->assertSame('{{name}}', $firstFragment['raw']);
        $this->assertSame('{{name}}', $firstFragment['normalizedRaw']);
        $this->assertSame('name', $firstFragment['fragment']['inner']);
        $this->assertSame('{{', $firstFragment['fragment']['openDelimiter']);
        $this->assertSame('}}', $firstFragment['fragment']['closeDelimiter']);
        $this->assertSame(0, $firstFragment['partIndex']);
        $this->assertSame(2, $firstFragment['partCount']);
        $this->assertTrue($firstFragment['isStart']);
        $this->assertFalse($firstFragment['isEnd']);
        $this->assertSame($firstFragment['matchId'], $secondFragment['matchId']);
        $this->assertSame(1, $secondFragment['partIndex']);
        $this->assertSame(2, $secondFragment['partCount']);
        $this->assertFalse($secondFragment['isStart']);
        $this->assertTrue($secondFragment['isEnd']);
        $this->assertSame(['start' => 0, 'end' => 4], $firstFragment['nodeRange']);
        $this->assertSame(['start' => 0, 'end' => 4], $secondFragment['nodeRange']);
        $this->assertSame(['start' => 4, 'end' => 12], $firstFragment['fragmentRange']);
        $this->assertSame(['start' => 4, 'end' => 12], $firstFragment['sequenceRange']);
        $this->assertSame(['start' => 4, 'end' => 8], $firstFragment['sliceRange']);
    }

    public function test_it_marks_malformed_control_fragments_without_repairing_them(): void
    {
        $paragraph = new ParagraphNode(children: [
            new TextNode('Vorlage {% wenn kunde'),
        ]);

        $section = new SectionNode([$paragraph]);
        $document = new DocumentNode([$section]);

        $pass = new TemplateAnnotationPass(new GenericTemplateSyntaxProfile());
        $pass->apply($document);

        $annotation = $paragraph->getChildren()[0]->getSourceRef()?->toArray()['xmlAttributes']['templateAnnotations'][0] ?? null;

        $this->assertNotNull($annotation);
        $this->assertSame('control', $annotation['kind']);
        $this->assertSame('when', $annotation['role']);
        $this->assertSame('malformed', $annotation['status']);
        $this->assertSame('{% wenn kunde', $annotation['raw']);
        $this->assertSame('{% wenn kunde', $annotation['normalizedRaw']);
        $this->assertSame(' wenn kunde', $annotation['fragment']['inner']);
        $this->assertSame('wenn kunde', $annotation['fragment']['normalizedInner']);
        $this->assertSame('{%', $annotation['fragment']['openDelimiter']);
    }

    public function test_it_marks_empty_or_uninterpretable_default_delimiter_tags_as_malformed(): void
    {
        $paragraph = new ParagraphNode(children: [
            new TextNode('Prüfung {{ }} und {% Leerzeile löschen %}'),
        ]);

        $section = new SectionNode([$paragraph]);
        $document = new DocumentNode([$section]);

        $pass = new TemplateAnnotationPass(new GenericTemplateSyntaxProfile());
        $pass->apply($document);

        $annotations = $paragraph->getChildren()[0]->getSourceRef()?->toArray()['xmlAttributes']['templateAnnotations'] ?? [];

        $this->assertCount(2, $annotations);
        $this->assertSame('placeholder', $annotations[0]['kind']);
        $this->assertSame('malformed', $annotations[0]['status']);
        $this->assertSame('{{ }}', $annotations[0]['raw']);
        $this->assertSame('{{ }}', $annotations[0]['normalizedRaw']);
        $this->assertSame('control', $annotations[1]['kind']);
        $this->assertSame('malformed', $annotations[1]['status']);
        $this->assertSame('{% Leerzeile löschen %}', $annotations[1]['raw']);
        $this->assertSame('{% Leerzeile löschen %}', $annotations[1]['normalizedRaw']);
    }

    public function test_it_annotates_block_level_controls_across_paragraph_boundaries(): void
    {
        $document = new DocumentNode([
            new SectionNode([
                new ParagraphNode([
                    new TextNode('{% wenn kunde > 0 %}'),
                ]),
                new ParagraphNode([
                    new TextNode('sichtbar'),
                ]),
                new ParagraphNode([
                    new TextNode('{% ende %}'),
                ]),
            ]),
        ]);

        (new TemplateAnnotationPass(new GenericTemplateSyntaxProfile()))->apply($document);

        $whenAnnotation = $document->toArray()['sections'][0]['paragraphs'][0]['children'][0]['metadata']['sourceRef']['xmlAttributes']['templateAnnotations'][0] ?? null;
        $endAnnotation = $document->toArray()['sections'][0]['paragraphs'][2]['children'][0]['metadata']['sourceRef']['xmlAttributes']['templateAnnotations'][0] ?? null;

        $this->assertNotNull($whenAnnotation);
        $this->assertNotNull($endAnnotation);
        $this->assertSame('when', $whenAnnotation['role']);
        $this->assertSame('complete', $whenAnnotation['status']);
        $this->assertSame('{% wenn kunde > 0 %}', $whenAnnotation['normalizedRaw']);
        $this->assertSame('wenn kunde > 0', $whenAnnotation['fragment']['normalizedInner']);
        $this->assertTrue($whenAnnotation['isStart']);
        $this->assertTrue($whenAnnotation['isEnd']);
        $this->assertSame('end', $endAnnotation['role']);
        $this->assertSame('{% ende %}', $endAnnotation['normalizedRaw']);
    }

    public function test_it_keeps_nested_inline_conditions_and_trailing_literal_text_stable(): void
    {
        $paragraph = new ParagraphNode(children: [
            new TextNode('Prefix '),
            new TextNode('{% we'),
            new TextNode('nn amount > 0 %}A{% sonst wenn amount <= 0 %}B{% sonst %}C{% ende %} Suffix'),
        ]);

        $section = new SectionNode([$paragraph]);
        $document = new DocumentNode([$section]);

        (new TemplateAnnotationPass(new GenericTemplateSyntaxProfile()))->apply($document);

        $annotations = $paragraph->getChildren()[1]->getSourceRef()?->toArray()['xmlAttributes']['templateAnnotations'] ?? [];
        $thirdNodeAnnotations = $paragraph->getChildren()[2]->getSourceRef()?->toArray()['xmlAttributes']['templateAnnotations'] ?? [];

        $this->assertCount(1, $annotations);
        $this->assertSame('when', $annotations[0]['role']);
        $this->assertSame(2, $annotations[0]['partCount']);
        $this->assertSame(0, $annotations[0]['partIndex']);
        $this->assertFalse($annotations[0]['hasTrailingLiteral']);
        $this->assertFalse($annotations[0]['hasLeadingLiteral']);
        $this->assertFalse($annotations[0]['isEnd']);

        $this->assertCount(4, $thirdNodeAnnotations);
        $this->assertSame(['when', 'else_if', 'else', 'end'], array_column($thirdNodeAnnotations, 'role'));
        $this->assertSame('when', $thirdNodeAnnotations[0]['role']);
        $this->assertSame(1, $thirdNodeAnnotations[0]['partIndex']);
        $this->assertSame(2, $thirdNodeAnnotations[0]['partCount']);
        $this->assertFalse($thirdNodeAnnotations[0]['hasLeadingLiteral']);
        $this->assertTrue($thirdNodeAnnotations[0]['hasTrailingLiteral']);
        $this->assertTrue($thirdNodeAnnotations[0]['slice']['hasTrailingLiteral']);
        $this->assertSame('{% wenn amount > 0 %}', $thirdNodeAnnotations[0]['normalizedRaw']);
        $this->assertSame('{% sonst wenn amount <= 0 %}', $thirdNodeAnnotations[1]['normalizedRaw']);
        $this->assertSame('{% sonst %}', $thirdNodeAnnotations[2]['normalizedRaw']);
        $this->assertSame('{% ende %}', $thirdNodeAnnotations[3]['normalizedRaw']);
    }

    public function test_it_recognizes_comparison_operators_in_control_fragments(): void
    {
        $paragraph = new ParagraphNode(children: [
            new TextNode('{% wenn value > 0 %}'),
            new TextNode('{% wenn value < 0 %}'),
            new TextNode('{% wenn value <= 0 %}'),
        ]);

        $section = new SectionNode([$paragraph]);
        $document = new DocumentNode([$section]);

        (new TemplateAnnotationPass(new GenericTemplateSyntaxProfile()))->apply($document);

        $annotations = $paragraph->getChildren()[0]->getSourceRef()?->toArray()['xmlAttributes']['templateAnnotations'] ?? [];

        $this->assertCount(1, $annotations);
        $this->assertSame('when', $annotations[0]['role']);
        $this->assertSame('{% wenn value > 0 %}', $annotations[0]['normalizedRaw']);

        $annotations = $paragraph->getChildren()[1]->getSourceRef()?->toArray()['xmlAttributes']['templateAnnotations'] ?? [];
        $this->assertCount(1, $annotations);
        $this->assertSame('when', $annotations[0]['role']);
        $this->assertSame('{% wenn value < 0 %}', $annotations[0]['normalizedRaw']);

        $annotations = $paragraph->getChildren()[2]->getSourceRef()?->toArray()['xmlAttributes']['templateAnnotations'] ?? [];
        $this->assertCount(1, $annotations);
        $this->assertSame('when', $annotations[0]['role']);
        $this->assertSame('{% wenn value <= 0 %}', $annotations[0]['normalizedRaw']);
    }
}
