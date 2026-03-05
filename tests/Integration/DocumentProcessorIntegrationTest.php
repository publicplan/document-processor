<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Integration;

use Publicplan\DocumentProcessor\Service\DocumentProcessor;
use Publicplan\DocumentProcessor\Service\DocumentLoader;
use Publicplan\DocumentProcessor\Service\TwigTranspilerService;
use Publicplan\DocumentProcessor\Service\ProsaExpressionLinter;
use PHPUnit\Framework\TestCase;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

/**
 * Integrationstests für den DocumentProcessor mit echten DOCX-Dateien.
 * 
 * Diese Tests erstellen temporär DOCX-Dateien, verarbeiten sie und validieren
 * das Ergebnis. Sie testen den vollständigen Workflow.
 */
class DocumentProcessorIntegrationTest extends TestCase
{
    private DocumentProcessor $processor;
    private string $tempDir;

    protected function setUp(): void
    {
        $loader = new DocumentLoader();
        $this->processor = new DocumentProcessor($loader);
        $this->tempDir = sys_get_temp_dir() . '/jarvis_docprocessor_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        // Aufräumen: Temp-Verzeichnis löschen
        $this->recursiveDelete($this->tempDir);
    }

    /**
     * Rekursives Löschen eines Verzeichnisses.
     */
    private function recursiveDelete(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    $path = $dir . '/' . $object;
                    if (is_dir($path)) {
                        $this->recursiveDelete($path);
                    } else {
                        unlink($path);
                    }
                }
            }
            rmdir($dir);
        }
    }

    /**
     * Hilfsmethode: Erstellt eine einfache DOCX-Datei.
     */
    private function createSimpleDocx(string $filename, string $content): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText($content);

        $filepath = $this->tempDir . '/' . $filename;
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);

        return $filepath;
    }

    /**
     * Hilfsmethode: Erstellt eine DOCX mit Formatierungen.
     */
    private function createFormattedDocx(string $filename): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Normaler Text
        $section->addText('Normaler Text');

        // Fetter Text
        $section->addText('Fetter Text', ['bold' => true]);

        // Kursiver Text
        $section->addText('Kursiver Text', ['italic' => true]);

        // Unterstrichener Text
        $section->addText('Unterstrichener Text', ['underline' => 'single']);

        // Kombinierte Formatierung
        $section->addText('Fett und Kursiv', ['bold' => true, 'italic' => true]);

        $filepath = $this->tempDir . '/' . $filename;
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);

        return $filepath;
    }

    /**
     * Hilfsmethode: Erstellt eine DOCX mit Listen.
     */
    private function createListDocx(string $filename): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Ungeordnete Liste
        $section->addText('Ungeordnete Liste:');
        $section->addListItem('Erster Punkt', 0);
        $section->addListItem('Zweiter Punkt', 0);
        $section->addListItem('Dritter Punkt', 0);

        // Geordnete Liste
        $section->addText('Geordnete Liste:');
        $section->addListItem('Erster Punkt', 0, null, 'Numbering');
        $section->addListItem('Zweiter Punkt', 0, null, 'Numbering');

        $filepath = $this->tempDir . '/' . $filename;
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);

        return $filepath;
    }

    /**
     * Hilfsmethode: Erstellt eine DOCX mit einer Tabelle.
     */
    private function createTableDocx(string $filename): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $section->addText('Tabelle:');

        $table = $section->addTable();
        $table->addRow();
        $table->addCell(2000)->addText('Zelle 1');
        $table->addCell(2000)->addText('Zelle 2');
        $table->addCell(2000)->addText('Zelle 3');

        $table->addRow();
        $table->addCell(2000)->addText('Zelle 4');
        $table->addCell(2000)->addText('Zelle 5');
        $table->addCell(2000)->addText('Zelle 6');

        $filepath = $this->tempDir . '/' . $filename;
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);

        return $filepath;
    }

    /**
     * Hilfsmethode: Erstellt eine DOCX mit Links.
     */
    private function createLinkDocx(string $filename): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $section->addText('Hier ist ein Link: ');
        $section->addLink('https://example.com', 'Example Website');

        $filepath = $this->tempDir . '/' . $filename;
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);

        return $filepath;
    }

    /**
     * Test: Verarbeitung einer einfachen DOCX-Datei.
     */
    public function testProcessSimpleDocument(): void
    {
        $filepath = $this->createSimpleDocx('simple.docx', 'Hallo Welt');

        $result = $this->processor->process($filepath, 'simple.docx');

        $this->assertInstanceOf(\Publicplan\DocumentProcessor\Model\ProcessedDocument::class, $result);
        // Leerzeichen werden als &nbsp; kodiert
        $this->assertStringContainsString('Hallo', $result->html);
        $this->assertStringContainsString('Welt', $result->html);
        $this->assertFalse($result->hasUnacceptedChanges);
        $this->assertEquals('simple.docx', $result->sourceFilename);
    }

    /**
     * Test: Verarbeitung einer DOCX mit Formatierungen.
     */
    public function testProcessFormattedDocument(): void
    {
        $filepath = $this->createFormattedDocx('formatted.docx');

        $result = $this->processor->process($filepath, 'formatted.docx');

        $html = $result->html;

        // Prüfe Formatierungen
        $this->assertStringContainsString('<strong>', $html);
        $this->assertStringContainsString('<em>', $html);
        $this->assertStringContainsString('<u>', $html);
    }

    /**
     * Test: Verarbeitung einer DOCX mit Listen.
     */
    public function testProcessListDocument(): void
    {
        $filepath = $this->createListDocx('list.docx');

        $result = $this->processor->process($filepath, 'list.docx');

        $html = $result->html;

        // Prüfe Listen-Struktur
        $this->assertStringContainsString('<li>', $html);
    }

    /**
     * Test: Aufeinanderfolgende Listen-Elemente werden zu einer Liste zusammengefasst.
     */
    public function testConsecutiveListItemsAreGrouped(): void
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Füge 3 aufeinanderfolgende Listenelemente hinzu
        $section->addListItem('Punkt 1', 0);
        $section->addListItem('Punkt 2', 0);
        $section->addListItem('Punkt 3', 0);

        $filepath = $this->tempDir . '/consecutive_list.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);

        $result = $this->processor->process($filepath, 'consecutive_list.docx');
        $html = $result->html;

        // Sollte nur ein öffnendes <ul> oder <ol> haben
        $ulCount = substr_count($html, '<ul');
        $olCount = substr_count($html, '<ol');
        $totalLists = $ulCount + $olCount;

        $this->assertEquals(1, $totalLists, 'Es sollte genau eine Liste geben, nicht ' . $totalLists);

        // Sollte 3 <li> Elemente haben
        $liCount = substr_count($html, '<li>');
        $this->assertEquals(3, $liCount, 'Es sollte genau 3 Listenelemente geben');
    }

    /**
     * Test: Liste wird korrekt geschlossen wenn normaler Text folgt.
     */
    public function testListIsClosedWhenTextFollows(): void
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Liste gefolgt von normalem Text
        $section->addListItem('Listenpunkt', 0);
        $section->addText('Normaler Text nach der Liste');

        $filepath = $this->tempDir . '/list_then_text.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);

        $result = $this->processor->process($filepath, 'list_then_text.docx');
        $html = $result->html;

        // Sollte schließendes </ul> oder </ol> haben
        $this->assertStringContainsString('</ul>', $html);

        // Normaler Text sollte außerhalb der Liste sein
        $this->assertStringContainsString('Normaler Text nach der Liste', $html);
    }

    /**
     * Test: Verarbeitung einer DOCX mit Tabelle.
     */
    public function testProcessTableDocument(): void
    {
        $filepath = $this->createTableDocx('table.docx');

        $result = $this->processor->process($filepath, 'table.docx');

        $html = $result->html;

        // Prüfe Tabellen-Struktur
        $this->assertStringContainsString('<table', $html);
        $this->assertStringContainsString('<tr>', $html);
        $this->assertStringContainsString('<td>', $html);
    }

    /**
     * Test: Verarbeitung einer DOCX mit Links.
     */
    public function testProcessLinkDocument(): void
    {
        $filepath = $this->createLinkDocx('link.docx');

        $result = $this->processor->process($filepath, 'link.docx');

        $html = $result->html;

        // Prüfe Link-Struktur
        $this->assertStringContainsString('<a href=', $html);
        $this->assertStringContainsString('https://example.com', $html);
    }

    /**
     * Test: TwigTranspiler kann den Output verarbeiten.
     */
    public function testOutputIsCompatibleWithTwigTranspiler(): void
    {
        $filepath = $this->createSimpleDocx('for_transpiler.docx', 'Test mit Platzhalter');

        $result = $this->processor->process($filepath, 'for_transpiler.docx');
        $html = $result->html;

        // TwigTranspiler initialisieren
        $linter = new ProsaExpressionLinter();
        $transpiler = new TwigTranspilerService($linter);

        // Sollte keine Exception werfen
        try {
            $transpiled = $transpiler->transpile($html);
            $this->assertIsString($transpiled);
        } catch (\Exception $e) {
            $this->fail('TwigTranspiler konnte HTML nicht verarbeiten: ' . $e->getMessage());
        }
    }

    /**
     * Test: Verarbeitung eines komplexen Dokuments (alles zusammen).
     */
    public function testProcessComplexDocument(): void
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Überschrift
        $section->addText('Komplexes Testdokument', ['bold' => true, 'size' => 16]);
        $section->addTextBreak();

        // Text mit Formatierung
        $section->addText('Dies ist ein ');
        $section->addText('fetter', ['bold' => true]);
        $section->addText(' und ');
        $section->addText('kursiver', ['italic' => true]);
        $section->addText(' Text.');
        $section->addTextBreak(2);

        // Liste
        $section->addText('Eine Liste:');
        $section->addListItem('Punkt 1', 0);
        $section->addListItem('Punkt 2', 0);
        $section->addListItem('Punkt 3', 0);
        $section->addTextBreak();

        // Tabelle
        $section->addText('Eine Tabelle:');
        $table = $section->addTable();
        $table->addRow();
        $table->addCell(3000)->addText('Name');
        $table->addCell(3000)->addText('Wert');
        $table->addRow();
        $table->addCell(3000)->addText('Test');
        $table->addCell(3000)->addText('123');

        $filepath = $this->tempDir . '/complex.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);

        $result = $this->processor->process($filepath, 'complex.docx');

        $html = $result->html;

        // Validierungen (Leerzeichen werden als &nbsp; kodiert)
        $this->assertStringContainsString('Komplexes', $html);
        $this->assertStringContainsString('Testdokument', $html);
        $this->assertStringContainsString('<strong>', $html);
        $this->assertStringContainsString('<em>', $html);
        $this->assertStringContainsString('<li>', $html);
        $this->assertStringContainsString('<table', $html);

        $this->assertFalse($result->hasErrors());
    }

    /**
     * Test: Message-Sammlung bei Warnungen.
     */
    public function testMessageCollection(): void
    {
        // Erstelle ein Dokument, das Warnungen produziert
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText('Normaler Text');
        // Formularfelder würden Warnungen produzieren, aber die sind schwer zu erstellen

        $filepath = $this->tempDir . '/messages.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);

        $result = $this->processor->process($filepath, 'messages.docx');

        // Mindestens das Modification-Datum sollte da sein
        $this->assertInstanceOf(\DateTimeInterface::class, $result->lastModified);
    }
}
