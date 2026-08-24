<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service\Ast;

use PhpOffice\PhpWord\PhpWord;
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
