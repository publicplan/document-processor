<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Paragraph;
use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Service\DocumentLoader;
use Publicplan\DocumentProcessor\Service\DocumentProcessor;

/**
 * Tests für Border-Gruppen im DocumentProcessor.
 */
class DocumentProcessorBorderTest extends TestCase
{
    private DocumentProcessor $processor;

    protected function setUp(): void
    {
        $documentLoader  = $this->createMock(DocumentLoader::class);
        $this->processor = new DocumentProcessor($documentLoader);
    }

    /**
     * Test: Aufeinanderfolgende Absätze mit identischen Borders werden gruppiert.
     */
    public function testConsecutiveParagraphsWithIdenticalBordersAreGrouped(): void
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Border-Style erstellen
        $borderStyle = new Paragraph();
        $borderStyle->setBorderTopSize(4);
        $borderStyle->setBorderTopColor('000000');
        $borderStyle->setBorderTopStyle('single');
        $borderStyle->setBorderLeftSize(4);
        $borderStyle->setBorderLeftColor('000000');
        $borderStyle->setBorderLeftStyle('single');
        $borderStyle->setBorderRightSize(4);
        $borderStyle->setBorderRightColor('000000');
        $borderStyle->setBorderRightStyle('single');
        $borderStyle->setBorderBottomSize(4);
        $borderStyle->setBorderBottomColor('000000');
        $borderStyle->setBorderBottomStyle('single');

        // Drei Absätze mit identischen Borders (als TextRun)
        $textRun1 = $section->addTextRun($borderStyle);
        $textRun1->addText('Absatz 1');

        $textRun2 = $section->addTextRun($borderStyle);
        $textRun2->addText('Absatz 2');

        $textRun3 = $section->addTextRun($borderStyle);
        $textRun3->addText('Absatz 3');

        // Konvertieren
        $result = $this->invokeConvertToHtml($phpWord);

        // Prüfungen
        $this->assertStringContainsString('<div', $result);
        $this->assertStringContainsString('border:', $result);
        $this->assertStringContainsString('</div>', $result);

        // Es sollte nur EIN div mit border geben
        $this->assertSame(1, substr_count($result, '<div'));
        $this->assertSame(1, substr_count($result, '</div>'));

        // Die Absätze sollten innerhalb sein
        $this->assertStringContainsString('Absatz 1', $result);
        $this->assertStringContainsString('Absatz 2', $result);
        $this->assertStringContainsString('Absatz 3', $result);
    }

    /**
     * Test: Absätze mit unterschiedlichen Borders erzeugen separate Container.
     */
    public function testParagraphsWithDifferentBordersCreateSeparateContainers(): void
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Erste Border-Gruppe (schwarz)
        $borderStyle1 = new Paragraph();
        $borderStyle1->setBorderTopSize(4);
        $borderStyle1->setBorderTopColor('000000');
        $borderStyle1->setBorderTopStyle('single');
        $borderStyle1->setBorderLeftSize(4);
        $borderStyle1->setBorderLeftColor('000000');
        $borderStyle1->setBorderLeftStyle('single');
        $borderStyle1->setBorderRightSize(4);
        $borderStyle1->setBorderRightColor('000000');
        $borderStyle1->setBorderRightStyle('single');
        $borderStyle1->setBorderBottomSize(4);
        $borderStyle1->setBorderBottomColor('000000');
        $borderStyle1->setBorderBottomStyle('single');

        // Zweite Border-Gruppe (rot)
        $borderStyle2 = new Paragraph();
        $borderStyle2->setBorderTopSize(4);
        $borderStyle2->setBorderTopColor('FF0000');
        $borderStyle2->setBorderTopStyle('single');
        $borderStyle2->setBorderLeftSize(4);
        $borderStyle2->setBorderLeftColor('FF0000');
        $borderStyle2->setBorderLeftStyle('single');
        $borderStyle2->setBorderRightSize(4);
        $borderStyle2->setBorderRightColor('FF0000');
        $borderStyle2->setBorderRightStyle('single');
        $borderStyle2->setBorderBottomSize(4);
        $borderStyle2->setBorderBottomColor('FF0000');
        $borderStyle2->setBorderBottomStyle('single');

        // Absätze mit unterschiedlichen Borders (als TextRun)
        $tr1 = $section->addTextRun($borderStyle1);
        $tr1->addText('Gruppe 1 - Absatz 1');

        $tr2 = $section->addTextRun($borderStyle1);
        $tr2->addText('Gruppe 1 - Absatz 2');

        $tr3 = $section->addTextRun($borderStyle2);
        $tr3->addText('Gruppe 2 - Absatz 1');

        $tr4 = $section->addTextRun($borderStyle2);
        $tr4->addText('Gruppe 2 - Absatz 2');

        // Konvertieren
        $result = $this->invokeConvertToHtml($phpWord);

        // Es sollten ZWEI divs geben
        $this->assertSame(2, substr_count($result, '<div'));
        $this->assertSame(2, substr_count($result, '</div>'));

        // Beide Farben sollten vorkommen
        $this->assertStringContainsString('#000000', $result);
        $this->assertStringContainsString('#FF0000', $result);
    }

    /**
     * Test: Absätze ohne Borders werden nicht gruppiert.
     */
    public function testParagraphsWithoutBordersAreNotGrouped(): void
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        // Normale Absätze ohne Borders (als TextRun)
        $tr1 = $section->addTextRun();
        $tr1->addText('Normaler Absatz 1');

        $tr2 = $section->addTextRun();
        $tr2->addText('Normaler Absatz 2');

        $tr3 = $section->addTextRun();
        $tr3->addText('Normaler Absatz 3');

        // Konvertieren
        $result = $this->invokeConvertToHtml($phpWord);

        // Keine divs mit borders
        $this->assertStringNotContainsString('<div', $result);
        $this->assertStringNotContainsString('</div>', $result);

        // Aber die Absätze sollten da sein
        $this->assertStringContainsString('Normaler Absatz 1', $result);
        $this->assertStringContainsString('Normaler Absatz 2', $result);
        $this->assertStringContainsString('Normaler Absatz 3', $result);
    }

    /**
     * Test: Mischung aus Absätzen mit und ohne Borders.
     */
    public function testMixedParagraphsWithAndWithoutBorders(): void
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $borderStyle = new Paragraph();
        $borderStyle->setBorderTopSize(4);
        $borderStyle->setBorderTopColor('000000');
        $borderStyle->setBorderTopStyle('single');
        $borderStyle->setBorderLeftSize(4);
        $borderStyle->setBorderLeftColor('000000');
        $borderStyle->setBorderLeftStyle('single');
        $borderStyle->setBorderRightSize(4);
        $borderStyle->setBorderRightColor('000000');
        $borderStyle->setBorderRightStyle('single');
        $borderStyle->setBorderBottomSize(4);
        $borderStyle->setBorderBottomColor('000000');
        $borderStyle->setBorderBottomStyle('single');

        // Reihenfolge: Normal -> Border -> Border -> Normal (als TextRun)
        $tr1 = $section->addTextRun();
        $tr1->addText('Vor dem Rahmen');

        $tr2 = $section->addTextRun($borderStyle);
        $tr2->addText('Im Rahmen 1');

        $tr3 = $section->addTextRun($borderStyle);
        $tr3->addText('Im Rahmen 2');

        $tr4 = $section->addTextRun();
        $tr4->addText('Nach dem Rahmen');

        // Konvertieren
        $result = $this->invokeConvertToHtml($phpWord);

        // Ein div für die Border-Gruppe
        $this->assertSame(1, substr_count($result, '<div'));
        $this->assertSame(1, substr_count($result, '</div>'));

        // Alle Texte sollten da sein
        $this->assertStringContainsString('Vor dem Rahmen', $result);
        $this->assertStringContainsString('Im Rahmen 1', $result);
        $this->assertStringContainsString('Im Rahmen 2', $result);
        $this->assertStringContainsString('Nach dem Rahmen', $result);
    }

    /**
     * Hilfsmethode zum Aufrufen der privaten convertToHtml-Methode.
     */
    private function invokeConvertToHtml(PhpWord $phpWord): string
    {
        $reflection = new \ReflectionClass($this->processor);
        $method     = $reflection->getMethod('convertToHtml');
        $method->setAccessible(true);

        $context = new ConversionContext();
        return $method->invoke($this->processor, $phpWord, $context);
    }
}
