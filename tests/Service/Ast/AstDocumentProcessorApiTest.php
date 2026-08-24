<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service\Ast;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Model\ProcessedAstAndHtmlDocument;
use Publicplan\DocumentProcessor\Model\ProcessedAstDocument;
use Publicplan\DocumentProcessor\Model\ProcessedDocument;
use Publicplan\DocumentProcessor\Model\ProcessingOptions;
use Publicplan\DocumentProcessor\Service\Ast\AstDocumentProcessor;
use Publicplan\DocumentProcessor\Service\Ast\PublicAstSerializer;
use Publicplan\DocumentProcessor\Service\Ast\Template\GenericTemplateSyntaxProfile;
use Publicplan\DocumentProcessor\Service\DocumentLoader;

class AstDocumentProcessorApiTest extends TestCase
{
    public function test_process_to_html_returns_processed_document(): void
    {
        $processor = $this->createProcessorWithSimpleDocument();

        $result = $processor->processToHtml('/test/file.docx', 'test.docx');

        $this->assertInstanceOf(ProcessedDocument::class, $result);
        $this->assertSame('test.docx', $result->sourceFilename);
        $this->assertStringContainsString('Hallo', $result->html);
    }

    public function test_process_to_ast_returns_public_contract_without_internal_metadata(): void
    {
        $processor = $this->createProcessorWithSimpleDocument();

        $result = $processor->processToAst('/test/file.docx', 'test.docx');

        $this->assertInstanceOf(ProcessedAstDocument::class, $result);
        $this->assertSame(PublicAstSerializer::AST_VERSION, $result->astVersion);
        $this->assertSame('document', $result->ast['type']);
        $this->assertSame(['sourceRef' => null], $result->ast['metadata']);

        $paragraph = $result->ast['sections'][0]['paragraphs'][0];
        $this->assertSame(['sourceRef' => null], $paragraph['metadata']);
        $this->assertArrayNotHasKey('renderHints', $paragraph['metadata']);
        $this->assertArrayNotHasKey('resolvedStyle', $paragraph['metadata']);
        $this->assertArrayNotHasKey('whitespaceFlags', $paragraph['metadata']);
        $this->assertArrayNotHasKey('originFlags', $paragraph['metadata']);
    }

    public function test_process_to_ast_and_html_returns_both_outputs(): void
    {
        $processor = $this->createProcessorWithSimpleDocument();

        $result = $processor->processToAstAndHtml('/test/file.docx', 'test.docx');

        $this->assertInstanceOf(ProcessedAstAndHtmlDocument::class, $result);
        $this->assertSame(PublicAstSerializer::AST_VERSION, $result->astVersion);
        $this->assertSame('document', $result->ast['type']);
        $this->assertStringContainsString('Hallo', $result->html);
    }

    public function test_process_to_ast_keeps_template_detection_disabled_by_default(): void
    {
        $loader = $this->createMock(DocumentLoader::class);
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Hallo {{kunde}}');

        $loader->expects($this->once())
            ->method('loadWithDocumentMetadata')
            ->willReturn($phpWord);

        $processor = new AstDocumentProcessor($loader);
        $result = $processor->processToAst('/test/file.docx', 'test.docx');

        $paragraph = $result->ast['sections'][0]['paragraphs'][0];
        $this->assertSame(['sourceRef' => null], $paragraph['metadata']);
    }

    public function test_process_to_ast_can_optionally_include_template_annotations(): void
    {
        $loader = $this->createMock(DocumentLoader::class);
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Hallo {{kunde}}');

        $loader->expects($this->once())
            ->method('loadWithDocumentMetadata')
            ->willReturn($phpWord);

        $processor = new AstDocumentProcessor($loader);
        $result = $processor->processToAst(
            '/test/file.docx',
            'test.docx',
            new ProcessingOptions(templateSyntaxProfile: new GenericTemplateSyntaxProfile())
        );

        $annotation = $result->ast['sections'][0]['paragraphs'][0]['metadata']['sourceRef']['xmlAttributes']['templateAnnotations'][0] ?? null;

        $this->assertNotNull($annotation);
        $this->assertSame('placeholder', $annotation['kind']);
        $this->assertSame('complete', $annotation['status']);
        $this->assertSame('{{kunde}}', $annotation['raw']);
    }

    public function test_process_to_html_simplifies_adjacent_identical_inline_tags(): void
    {
        $loader = $this->createMock(DocumentLoader::class);
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $paragraph = $section->addTextRun();
        $paragraph->addText('[', ['bold' => true]);
        $paragraph->addText('Einrichtungsname', ['bold' => true]);
        $paragraph->addText(']', ['bold' => true]);

        $loader->expects($this->once())
            ->method('loadWithDocumentMetadata')
            ->willReturn($phpWord);

        $processor = new AstDocumentProcessor($loader);
        $result = $processor->processToHtml('/test/file.docx', 'test.docx');

        $this->assertStringContainsString('<strong>[Einrichtungsname]</strong>', $result->html);
        $this->assertStringNotContainsString('</strong><strong>', $result->html);
    }

    public function test_process_to_ast_normalizes_list_depth_to_int(): void
    {
        $loader = $this->createMock(DocumentLoader::class);
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $listItem = $section->addListItemRun('0');
        $listItem->addText('Listenpunkt');

        $loader->expects($this->once())
            ->method('loadWithDocumentMetadata')
            ->willReturn($phpWord);

        $processor = new AstDocumentProcessor($loader);
        $result = $processor->processToAst('/test/file.docx', 'test.docx');

        $paragraph = $result->ast['sections'][0]['paragraphs'][0];
        $this->assertSame('list', $paragraph['type']);
        $this->assertSame(0, $paragraph['items'][0]['depth']);
    }

    public function test_process_to_html_preserves_center_and_justify_alignment(): void
    {
        $loader = $this->createMock(DocumentLoader::class);
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $centered = $section->addTextRun(['alignment' => 'center']);
        $centered->addText('Zentriert');
        $justified = $section->addTextRun(['alignment' => 'both']);
        $justified->addText('Blocksatz');

        $loader->expects($this->once())
            ->method('loadWithDocumentMetadata')
            ->willReturn($phpWord);

        $processor = new AstDocumentProcessor($loader);
        $result = $processor->processToHtml('/test/file.docx', 'test.docx');

        $this->assertStringContainsString('text-align: center;', $result->html);
        $this->assertStringContainsString('text-align: justify;', $result->html);
    }

    public function test_process_to_html_renders_nested_list_items(): void
    {
        $loader = $this->createMock(DocumentLoader::class);
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $top = $section->addListItemRun(0);
        $top->addText('Top 1');
        $nested1 = $section->addListItemRun(1);
        $nested1->addText('Sub 1');
        $nested2 = $section->addListItemRun(1);
        $nested2->addText('Sub 2');

        $loader->expects($this->once())
            ->method('loadWithDocumentMetadata')
            ->willReturn($phpWord);

        $processor = new AstDocumentProcessor($loader);
        $result = $processor->processToHtml('/test/file.docx', 'test.docx');

        $this->assertMatchesRegularExpression(
            '/<li>Top 1\s*<(ul|ol)[^>]*>\s*<li>Sub 1<\/li>\s*<li>Sub 2<\/li>\s*<\/\1>\s*<\/li>/s',
            $result->html
        );
    }

    public function test_process_to_html_renders_table_borders_from_style_name(): void
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $phpWord->addTableStyle('Tabellenraster', [
            'borderTopSize' => 4,
            'borderTopColor' => '000000',
            'borderRightSize' => 4,
            'borderRightColor' => '000000',
            'borderBottomSize' => 4,
            'borderBottomColor' => '000000',
            'borderLeftSize' => 4,
            'borderLeftColor' => '000000',
            'borderInsideHSize' => 4,
            'borderInsideHColor' => '000000',
            'borderInsideVSize' => 4,
            'borderInsideVColor' => '000000',
        ]);

        $table = $section->addTable('Tabellenraster');
        $table->addRow();
        $table->addCell(2000)->addText('A1');
        $table->addCell(2000)->addText('A2');

        $filePath = tempnam(sys_get_temp_dir(), 'ast-table-style-');
        if ($filePath === false) {
            $this->fail('Temp file could not be created.');
        }

        $docxPath = $filePath . '.docx';
        unlink($filePath);
        IOFactory::createWriter($phpWord, 'Word2007')->save($docxPath);

        try {
            $processor = new AstDocumentProcessor(new DocumentLoader());
            $result = $processor->processToHtml($docxPath, 'table-style.docx');

            $this->assertStringContainsString('border-collapse: collapse;', $result->html);
            $this->assertStringContainsString('border: 0.0264cm solid #000000;', $result->html);
            $this->assertStringContainsString('<td style="border: 0.0264cm solid #000000;">', $result->html);
        } finally {
            @unlink($docxPath);
        }
    }

    private function createProcessorWithSimpleDocument(): AstDocumentProcessor
    {
        $loader = $this->createMock(DocumentLoader::class);
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Hallo Welt');

        $loader->expects($this->once())
            ->method('loadWithDocumentMetadata')
            ->willReturn($phpWord);

        return new AstDocumentProcessor($loader);
    }
}
