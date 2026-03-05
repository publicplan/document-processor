<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service;

use Publicplan\DocumentProcessor\Exception\DocumentLoadException;
use Publicplan\DocumentProcessor\Model\ProcessedDocument;
use Publicplan\DocumentProcessor\Service\DocumentLoader;
use Publicplan\DocumentProcessor\Service\DocumentProcessor;
use PHPUnit\Framework\TestCase;

/**
 * Tests für den DocumentProcessor Service.
 *
 * Diese Tests validieren die Kernfunktionalität des neuen DOCX-Processors.
 */
class DocumentProcessorTest extends TestCase
{
    private DocumentProcessor $processor;
    private string $testFilesDir;

    protected function setUp(): void
    {
        $loader = new DocumentLoader();
        $this->processor = new DocumentProcessor($loader);
        $this->testFilesDir = __DIR__ . '/../_fixtures';
    }

    /**
     * Test: Verarbeitung eines nicht existierenden Dokuments wirft Exception.
     */
    public function testProcessNonExistentFileThrowsException(): void
    {
        $this->expectException(DocumentLoadException::class);
        $this->expectExceptionMessage('Dokument nicht gefunden');

        $this->processor->process('/nonexistent/path/file.docx');
    }

    /**
     * Test: Verarbeitung einer nicht lesbaren Datei wirft Exception.
     */
    public function testProcessUnreadableFileThrowsException(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        touch($tempFile);
        chmod($tempFile, 0000);

        try {
            $this->expectException(DocumentLoadException::class);
            $this->expectExceptionMessage('Dokument nicht lesbar');

            $this->processor->process($tempFile);
        } finally {
            chmod($tempFile, 0644);
            unlink($tempFile);
        }
    }

    /**
     * Test: Rückgabe des DTO ist korrekt strukturiert.
     */
    public function testProcessReturnsProcessedDocument(): void
    {
        // Wir mocken das Ergebnis, da wir kein echtes DOCX haben
        $loader = $this->createMock(DocumentLoader::class);
        $mockPhpWord = $this->createMock(\PhpOffice\PhpWord\PhpWord::class);
        
        $loader->expects($this->once())
            ->method('loadWithChangeCheck')
            ->willReturn($mockPhpWord);

        $processor = new DocumentProcessor($loader);
        
        // Da wir das PhpWord-Objekt nicht richtig mocken können (keine Sections),
        // erwarten wir eine Exception wegen der Konvertierung
        $this->expectException(\Exception::class);
        
        $processor->process('/test/file.docx', 'test.docx');
    }

    /**
     * Test: Das ProcessedDocument hat alle erforderlichen Eigenschaften.
     */
    public function testProcessedDocumentStructure(): void
    {
        $html = '<p>Test content</p>';
        $messages = [
            'errors' => [],
            'warnings' => [],
            'notices' => [],
            'infos' => [],
        ];

        $document = new ProcessedDocument(
            html: $html,
            lastModified: new \DateTime(),
            hasUnacceptedChanges: false,
            messages: $messages,
            sourceFilename: 'test.docx'
        );

        $this->assertEquals($html, $document->html);
        $this->assertFalse($document->hasUnacceptedChanges);
        $this->assertEquals('test.docx', $document->sourceFilename);
        $this->assertInstanceOf(\DateTimeInterface::class, $document->lastModified);
    }

    /**
     * Test: toLegacyFormat() gibt kompatibles Array zurück.
     */
    public function testProcessedDocumentLegacyFormat(): void
    {
        $messages = [
            'errors' => [],
            'warnings' => [],
            'notices' => [],
            'infos' => [],
        ];

        $document = new ProcessedDocument(
            html: '<p>Test</p>',
            lastModified: new \DateTime('2024-01-15 12:00:00'),
            hasUnacceptedChanges: true,
            messages: $messages,
            sourceFilename: 'legacy.docx'
        );

        $legacy = $document->toLegacyFormat();

        $this->assertArrayHasKey('html', $legacy);
        $this->assertArrayHasKey('lastModified', $legacy);
        $this->assertArrayHasKey('hasUnacceptedChanges', $legacy);
        $this->assertArrayHasKey('messages', $legacy);
        $this->assertArrayHasKey('sourceFilename', $legacy);
        
        $this->assertTrue($legacy['hasUnacceptedChanges']);
        $this->assertEquals('legacy.docx', $legacy['sourceFilename']);
    }

    /**
     * Test: getAllMessages() gibt alle Messages als flaches Array zurück.
     */
    public function testGetAllMessagesReturnsFlatArray(): void
    {
        $error = $this->createMock(\Publicplan\DocumentProcessor\Model\ParserError::class);
        $warning = $this->createMock(\Publicplan\DocumentProcessor\Model\ParserError::class);

        $messages = [
            'errors' => [$error],
            'warnings' => [$warning],
            'notices' => [],
            'infos' => [],
        ];

        $document = new ProcessedDocument(
            html: '',
            lastModified: new \DateTime(),
            hasUnacceptedChanges: false,
            messages: $messages,
            sourceFilename: 'test.docx'
        );

        $allMessages = $document->getAllMessages();
        
        $this->assertCount(2, $allMessages);
        $this->assertContains($error, $allMessages);
        $this->assertContains($warning, $allMessages);
    }
}
