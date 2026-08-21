<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service;

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Paragraph;
use Publicplan\DocumentProcessor\Service\DocumentLoader;
use Publicplan\DocumentProcessor\Exception\DocumentLoadException;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Tests für den DocumentLoader Service.
 */
class DocumentLoaderTest extends TestCase
{
    private DocumentLoader $loader;
    private string $testFilesDir;

    protected function setUp(): void
    {
        $this->loader = new DocumentLoader();
        $this->testFilesDir = __DIR__ . '/../_fixtures';
    }

    protected function tearDown(): void
    {
        Style::resetStyles();
    }

    /**
     * Test: Nicht existierende Datei wirft Exception.
     */
    public function testLoadNonExistentFileThrowsException(): void
    {
        $this->expectException(DocumentLoadException::class);
        $this->expectExceptionMessage('Dokument nicht gefunden');

        $this->loader->load('/nonexistent/file.docx');
    }

    /**
     * Test: Nicht lesbare Datei wirft Exception.
     */
    public function testLoadUnreadableFileThrowsException(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        touch($tempFile);
        chmod($tempFile, 0000);

        try {
            $this->expectException(DocumentLoadException::class);
            $this->expectExceptionMessage('Dokument nicht lesbar');

            $this->loader->load($tempFile);
        } finally {
            chmod($tempFile, 0644);
            unlink($tempFile);
        }
    }

    /**
     * Test: Ungültige DOCX-Datei wirft Exception.
     */
    public function testLoadInvalidDocxThrowsException(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        file_put_contents($tempFile, 'This is not a valid DOCX file');

        try {
            $this->expectException(DocumentLoadException::class);
            $this->expectExceptionMessage('Fehler beim Laden des Dokuments');

            $this->loader->load($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    /**
     * Test: hasUnacceptedChanges wirft Exception für nicht existierende Datei.
     */
    public function testHasUnacceptedChangesNonExistentFileThrowsException(): void
    {
        $this->expectException(DocumentLoadException::class);
        $this->expectExceptionMessage('Konnte die Datei nicht öffnen');

        $this->loader->hasUnacceptedChanges('/nonexistent/file.docx');
    }

    /**
     * Test: hasUnacceptedChanges mit ungültiger Datei.
     */
    public function testHasUnacceptedChangesInvalidFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        file_put_contents($tempFile, 'Not a valid zip/docx');

        try {
            $this->expectException(DocumentLoadException::class);
            
            $this->loader->hasUnacceptedChanges($tempFile);
        } finally {
            unlink($tempFile);
        }
    }

    public function testExtractDocumentDefaultFontSizeFromStylesXml(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        assert($tempFile !== false);

        $phpWord = new PhpWord();
        $phpWord->setDefaultFontSize(14);
        $phpWord->addSection()->addText('Test');

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempFile);

        try {
            $size = $this->loader->extractDocumentDefaultFontSize($tempFile);
            $this->assertSame(14.0, $size);
        } finally {
            unlink($tempFile);
        }
    }

    public function testLoadRegistersParagraphStyleIndentationFromStylesXml(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'test_') . '.docx';
        assert($tempFile !== false);

        $phpWord = new PhpWord();
        $phpWord->addSection()->addText('Test');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempFile);

        $zip = new ZipArchive();
        try {
            $this->assertTrue($zip->open($tempFile) === true);
            $stylesXml = $zip->getFromName('word/styles.xml');
            $this->assertIsString($stylesXml);

            $customStyle = '<w:style w:type="paragraph" w:styleId="Listenabsatz">'
                . '<w:name w:val="List Paragraph"/>'
                . '<w:basedOn w:val="Standard"/>'
                . '<w:pPr><w:ind w:left="720" w:hanging="180" w:firstLine="0"/></w:pPr>'
                . '</w:style>';
            $patchedStylesXml = str_replace('</w:styles>', $customStyle . '</w:styles>', $stylesXml);
            $this->assertTrue($zip->addFromString('word/styles.xml', $patchedStylesXml));
        } finally {
            $zip->close();
        }

        Style::resetStyles();
        $this->loader->load($tempFile);
        $style = Style::getStyle('Listenabsatz');

        try {
            $this->assertInstanceOf(Paragraph::class, $style);
            $this->assertSame(720.0, $style->getIndentLeft());
            $this->assertSame(180.0, $style->getHanging());
            $this->assertSame('Standard', $style->getBasedOn());
        } finally {
            unlink($tempFile);
        }
    }
}
