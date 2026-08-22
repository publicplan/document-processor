<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service\Ast;

use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Model\ProcessedAstAndHtmlDocument;
use Publicplan\DocumentProcessor\Model\ProcessedAstDocument;
use Publicplan\DocumentProcessor\Model\ProcessedDocument;
use Publicplan\DocumentProcessor\Service\Ast\AstDocumentProcessor;
use Publicplan\DocumentProcessor\Service\Ast\PublicAstSerializer;
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
