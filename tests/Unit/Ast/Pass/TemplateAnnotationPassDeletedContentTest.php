<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Unit\Ast\Pass;

use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Ast\Metadata\TrackChangeType;
use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\ParagraphNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\TextNode;
use Publicplan\DocumentProcessor\Ast\Pass\TemplateAnnotationPass;
use Publicplan\DocumentProcessor\Service\Ast\Template\GenericTemplateSyntaxProfile;

class TemplateAnnotationPassDeletedContentTest extends TestCase
{
    public function test_deleted_text_is_not_annotated_but_active_text_is_annotated(): void
    {
        $document = new DocumentNode([
            new SectionNode([
                new ParagraphNode([
                    new TextNode(content: '{{name}}', trackChange: TrackChangeType::Deleted),
                ]),
                new ParagraphNode([
                    new TextNode(content: '{{name}}'),
                ]),
            ]),
        ]);

        (new TemplateAnnotationPass(new GenericTemplateSyntaxProfile()))->apply($document);

        $documentArray = $document->toArray();
        $deletedNode = $documentArray['sections'][0]['paragraphs'][0]['children'][0];
        $activeNode = $documentArray['sections'][0]['paragraphs'][1]['children'][0];

        self::assertSame('deleted', $deletedNode['trackChange']);
        self::assertNull($deletedNode['metadata']['sourceRef']);

        self::assertNotNull($activeNode['metadata']['sourceRef']);
        self::assertArrayHasKey('templateAnnotations', $activeNode['metadata']['sourceRef']['xmlAttributes']);
        self::assertNotEmpty($activeNode['metadata']['sourceRef']['xmlAttributes']['templateAnnotations']);
    }
}
