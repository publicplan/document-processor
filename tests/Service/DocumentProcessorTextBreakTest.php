<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service;

use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style\Paragraph;
use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Service\DocumentLoader;
use Publicplan\DocumentProcessor\Service\DocumentProcessor;

/**
 * Tests für TextBreak-Verarbeitung als leere Absätze.
 */
class DocumentProcessorTextBreakTest extends TestCase
{
    private DocumentProcessor $processor;

    protected function setUp(): void
    {
        $documentLoader  = $this->createMock(DocumentLoader::class);
        $this->processor = new DocumentProcessor($documentLoader);
    }

    /**
     * Test: TextBreak zwischen zwei Border-Absätzen wird als leerer Absatz erkannt.
     */
    public function testTextBreakBetweenBorderParagraphsBecomesEmptyParagraph(): void
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

        $tr1 = $section->addTextRun($borderStyle);
        $tr1->addText('Absatz 1');
        $section->addTextBreak();
        $tr2 = $section->addTextRun($borderStyle);
        $tr2->addText('Absatz 2');

        $result = $this->invokeConvertToHtml($phpWord);

        $this->assertStringContainsString('<div', $result);
        $this->assertStringContainsString('</div>', $result);
        $this->assertStringContainsString('>&#32;</p>', $result);
        $this->assertStringNotContainsString('<br>', $result);
    }

    /**
     * Test: TextBreak innerhalb Border-Gruppe wird als leerer Absatz ausgegeben.
     */
    public function testTextBreakInsideBorderGroupBecomesEmptyParagraph(): void
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

        $tr1 = $section->addTextRun($borderStyle);
        $tr1->addText('Absatz 1');
        $section->addTextBreak();
        $tr2 = $section->addTextRun($borderStyle);
        $tr2->addText('Absatz 2');
        $section->addTextBreak();
        $tr3 = $section->addTextRun($borderStyle);
        $tr3->addText('Absatz 3');

        $result = $this->invokeConvertToHtml($phpWord);

        $this->assertSame(1, substr_count($result, '<div'));
        $this->assertSame(1, substr_count($result, '</div>'));
        $this->assertSame(2, substr_count($result, '>&#32;</p>'));
        $this->assertStringNotContainsString('<br>', $result);
    }

    /**
     * Test: TextBreak zwischen normalen Absätzen wird als <p>&#32;</p> ausgegeben.
     */
    public function testTextBreakBetweenNormalParagraphsBecomesEmptyParagraph(): void
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();

        $tr1 = $section->addTextRun();
        $tr1->addText('Absatz 1');
        $section->addTextBreak();
        $tr2 = $section->addTextRun();
        $tr2->addText('Absatz 2');

        $result = $this->invokeConvertToHtml($phpWord);

        $this->assertStringNotContainsString('<div', $result);
        $this->assertStringContainsString('>&#32;</p>', $result);
        $this->assertStringNotContainsString('<br>', $result);
    }

    /**
     * Test: Mehrere TextBreaks hintereinander.
     */
    public function testMultipleTextBreaksHandledIndividually(): void
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

        $tr1 = $section->addTextRun($borderStyle);
        $tr1->addText('Absatz 1');
        $section->addTextBreak();
        $section->addTextBreak();
        $tr2 = $section->addTextRun($borderStyle);
        $tr2->addText('Absatz 2');

        $result = $this->invokeConvertToHtml($phpWord);

        $this->assertSame(2, substr_count($result, '>&#32;</p>'));
    }

    /**
     * Test: TextBreak nach Border-Gruppe wird nicht in die Gruppe aufgenommen.
     */
    public function testTextBreakAfterBorderGroupIsOutsideGroup(): void
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

        // Border-Gruppe
        $tr1 = $section->addTextRun($borderStyle);
        $tr1->addText('Absatz mit Border');

        // TextBreak nach der Gruppe (vor normalem Absatz)
        $section->addTextBreak();

        // Normaler Absatz ohne Borders
        $tr2 = $section->addTextRun();
        $tr2->addText('Normaler Absatz');

        $result = $this->invokeConvertToHtml($phpWord);

        // Es sollte nur ein div geben (für den ersten Absatz)
        $this->assertSame(1, substr_count($result, '<div'));
        $this->assertSame(1, substr_count($result, '</div>'));

        // Der TextBreak sollte außerhalb des div sein
        $this->assertStringContainsString('</div>', $result);
        $this->assertStringContainsString('>&#32;</p>', $result);

        // Keine <br> Tags
        $this->assertStringNotContainsString('<br>', $result);
    }

    /**
     * Test: Ein leerer Absatz innerhalb einer Liste wird außerhalb der Liste gerendert.
     */
    public function testEmptyTextRunInsideListIsRenderedOutsideList(): void
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addListItem('Listenpunkt', 0, null, 'Numbering');

        $filepath = sys_get_temp_dir() . '/document-processor-list-' . uniqid() . '.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filepath);

        try {
            $loadedDoc = IOFactory::load($filepath);
            $loadedSection = $loadedDoc->getSections()[0];

            $emptyTextRun = new TextRun();
            $this->insertSectionElement($loadedSection, 1, $emptyTextRun);

            $result = $this->invokeConvertToHtml($loadedDoc);

            $listTag = str_contains($result, '<ol') ? 'ol' : 'ul';
            $this->assertStringContainsString('<' . $listTag, $result);
            $this->assertStringContainsString('</' . $listTag . '>', $result);
            $this->assertStringContainsString('<p style="margin-bottom: 0cm;">&#32;</p>', $result);

            $listStart = strpos($result, '<' . $listTag);
            $listEnd = strpos($result, '</' . $listTag . '>');
            $listSegment = substr($result, $listStart, $listEnd - $listStart + strlen('</' . $listTag . '>'));

            $this->assertStringNotContainsString('<p style="margin-bottom: 0cm;">&#32;</p>', $listSegment);
        } finally {
            @unlink($filepath);
        }
    }

    private function invokeConvertToHtml(PhpWord $phpWord): string
    {
        $reflection = new \ReflectionClass($this->processor);
        $method     = $reflection->getMethod('convertToHtml');
        $method->setAccessible(true);
        $context = new ConversionContext();
        return $method->invoke($this->processor, $phpWord, $context);
    }

    private function insertSectionElement(object $section, int $position, object $element): void
    {
        $reflection = new \ReflectionClass($section);
        $property = $reflection->getProperty('elements');
        $property->setAccessible(true);

        $elements = $property->getValue($section);
        array_splice($elements, $position, 0, [$element]);
        $property->setValue($section, $elements);
    }
}
