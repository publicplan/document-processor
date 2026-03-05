<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service;

use Publicplan\DocumentProcessor\Service\DocumentLoader;
use Publicplan\DocumentProcessor\Exception\DocumentLoadException;
use PHPUnit\Framework\TestCase;

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
}
