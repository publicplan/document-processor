<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service\Ast;

use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Ast\Metadata\ListFormat;
use Publicplan\DocumentProcessor\Ast\Metadata\RenderHints;
use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;
use Publicplan\DocumentProcessor\Ast\Node\ListItemNode;
use Publicplan\DocumentProcessor\Ast\Node\ListNode;
use Publicplan\DocumentProcessor\Ast\Node\SectionNode;
use Publicplan\DocumentProcessor\Ast\Node\TextNode;
use Publicplan\DocumentProcessor\Service\Ast\AstHtmlRenderer;

final class AstHtmlRendererTest extends TestCase
{
    public function test_render_list_merges_container_styles_without_duplicate_style_attribute(): void
    {
        $hints = new RenderHints([
            'list_tag' => 'ol',
            'list_sequence_key' => 'numId:1|depth:0|format:number',
            'list_docx_id' => 1,
        ]);
        $item = new ListItemNode(
            numId: 1,
            depth: 0,
            numFormat: ListFormat::Number,
            children: [new TextNode('Eintrag')],
            renderHints: $hints
        );
        $list = new ListNode(
            items: [$item],
            spacingBefore: 0.42,
            spacingAfter: 0.84,
            indentLeft: 1.27
        );
        $document = new DocumentNode([new SectionNode([$list])]);

        $renderer = new AstHtmlRenderer();
        $html = $renderer->render($document);

        $this->assertMatchesRegularExpression('/<(ol|ul)\b[^>]*>/', $html);
        preg_match('/<(ol|ul)\b[^>]*>/', $html, $matches);
        $this->assertNotEmpty($matches);
        $startTag = $matches[0];

        $this->assertSame(1, preg_match_all('/\bstyle="/', $startTag));
        $this->assertStringContainsString('margin-bottom: 0;', $startTag);
        $this->assertStringContainsString('margin-top: 0.42cm;', $startTag);
        $this->assertStringContainsString('margin-bottom: 0.84cm;', $startTag);
        $this->assertStringContainsString('margin-left: 1.27cm;', $startTag);
    }
}
