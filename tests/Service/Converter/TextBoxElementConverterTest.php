<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service\Converter;

use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextBox;
use PhpOffice\PhpWord\Style\TextBox as TextBoxStyle;
use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Service\Converter\TextBoxElementConverter;

/**
 * Tests für den TextBoxElementConverter.
 */
class TextBoxElementConverterTest extends TestCase
{
    private TextBoxElementConverter $converter;
    private ConversionContext       $context;

    protected function setUp(): void
    {
        $this->converter = new TextBoxElementConverter();
        $this->context   = new ConversionContext();
    }

    /**
     * Test: TextBox mit Border und Farbe wird korrekt umgewandelt.
     */
    public function testTextBoxWithBorderAndColor(): void
    {
        $style = new TextBoxStyle();
        $style->setBorderSize(4); // 4 pt
        $style->setBorderColor('FF0000');

        $textBox = new TextBox($style);
        $textBox->addText('Text in einer Box');

        $result = $this->converter->convert($textBox, $this->context);

        $this->assertStringContainsString('<div', $result);
        $this->assertStringContainsString('border:', $result);
        $this->assertStringContainsString('#FF0000', $result);
        $this->assertStringContainsString('Text in einer Box', $result);
        $this->assertStringContainsString('</div>', $result);
    }

    /**
     * Test: TextBox ohne Border gibt nur den Inhalt zurück.
     */
    public function testTextBoxWithoutBorderReturnsContentOnly(): void
    {
        $textBox = new TextBox();
        $textBox->addText('Einfacher Text');

        $result = $this->converter->convert($textBox, $this->context);

        $this->assertStringNotContainsString('<div', $result);
        $this->assertStringContainsString('Einfacher Text', $result);
    }

    /**
     * Test: TextBox mit Hintergrundfarbe.
     */
    public function testTextBoxWithBackgroundColor(): void
    {
        $style = new TextBoxStyle();
        $style->setBgColor('FFFF00');

        $textBox = new TextBox($style);
        $textBox->addText('Gelber Hintergrund');

        $result = $this->converter->convert($textBox, $this->context);

        $this->assertStringContainsString('background-color: #FFFF00;', $result);
        $this->assertStringContainsString('Gelber Hintergrund', $result);
    }

    /**
     * Test: TextBox mit inner margins (padding).
     */
    public function testTextBoxWithInnerMargins(): void
    {
        $style = new TextBoxStyle();
        $style->setBorderSize(2);
        $style->setInnerMarginTop(144); // 0.25 cm
        $style->setInnerMarginLeft(144);
        $style->setInnerMarginRight(144);
        $style->setInnerMarginBottom(144);

        $textBox = new TextBox($style);
        $textBox->addText('Text mit Padding');

        $result = $this->converter->convert($textBox, $this->context);

        $this->assertStringContainsString('padding:', $result);
        $this->assertStringContainsString('Text mit Padding', $result);
    }

    /**
     * Test: TextBox mit mehreren Child-Elementen.
     */
    public function testTextBoxWithMultipleChildren(): void
    {
        $style = new TextBoxStyle();
        $style->setBorderSize(2);
        $style->setBorderColor('000000');

        $textBox = new TextBox($style);
        $textBox->addText('Erster Absatz');
        $textBox->addText('Zweiter Absatz');

        $result = $this->converter->convert($textBox, $this->context);

        $this->assertStringContainsString('border:', $result);
        $this->assertStringContainsString('Erster Absatz', $result);
        $this->assertStringContainsString('Zweiter Absatz', $result);
        $this->assertStringContainsString('</div>', $result);
    }

    /**
     * Test: Converter erkennt TextBox-Elemente korrekt.
     */
    public function testConverterSupportsTextBoxElements(): void
    {
        $textBox = new TextBox();

        $this->assertTrue($this->converter->supports($textBox));
    }

    /**
     * Test: Converter ignoriert nicht-TextBox-Elemente.
     */
    public function testConverterDoesNotSupportOtherElements(): void
    {
        $text = new Text('Nur Text');

        $this->assertFalse($this->converter->supports($text));
    }

    /**
     * Test: Converter hat korrekte Priorität.
     */
    public function testConverterPriority(): void
    {
        $this->assertEquals(20, $this->converter->getPriority());
    }
}
