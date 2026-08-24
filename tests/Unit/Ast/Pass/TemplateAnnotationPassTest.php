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
        $this->assertSame($firstFragment['matchId'], $secondFragment['matchId']);
        $this->assertSame(['start' => 0, 'end' => 4], $firstFragment['nodeRange']);
        $this->assertSame(['start' => 0, 'end' => 4], $secondFragment['nodeRange']);
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
        $this->assertSame('control', $annotations[1]['kind']);
        $this->assertSame('malformed', $annotations[1]['status']);
        $this->assertSame('{% Leerzeile löschen %}', $annotations[1]['raw']);
    }
}
