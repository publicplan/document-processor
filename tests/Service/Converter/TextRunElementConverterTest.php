<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service\Converter;

use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Style\Paragraph;
use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Service\Converter\TextRunElementConverter;

/**
 * Tests für den TextRunElementConverter.
 */
class TextRunElementConverterTest extends TestCase
{
    private TextRunElementConverter $converter;
    private ConversionContext       $context;

    protected function setUp(): void
    {
        $this->converter = new TextRunElementConverter();
        $this->context   = new ConversionContext();
    }

    /**
     * Test: Leerer Absatz wird als <p>&#32;</p> ausgegeben.
     */
    public function testEmptyParagraphOutputsSpace(): void
    {
        $textRun = new TextRun();
        // Kein Text hinzugefügt - bleibt leer

        $result = $this->converter->convert($textRun, $this->context);

        $this->assertStringContainsString('>&#32;</p>', $result);
    }

    /**
     * Test: Leerer Absatz mit Borders wird als <p>&#32;</p> mit Border-Styles ausgegeben.
     */
    public function testEmptyParagraphWithBordersOutputsSpaceWithStyles(): void
    {
        $paragraphStyle = new Paragraph();
        $paragraphStyle->setBorderTopSize(4);
        $paragraphStyle->setBorderTopColor('000000');
        $paragraphStyle->setBorderTopStyle('single');
        $paragraphStyle->setBorderLeftSize(4);
        $paragraphStyle->setBorderLeftColor('000000');
        $paragraphStyle->setBorderLeftStyle('single');
        $paragraphStyle->setBorderRightSize(4);
        $paragraphStyle->setBorderRightColor('000000');
        $paragraphStyle->setBorderRightStyle('single');
        $paragraphStyle->setBorderBottomSize(4);
        $paragraphStyle->setBorderBottomColor('000000');
        $paragraphStyle->setBorderBottomStyle('single');

        $textRun = new TextRun($paragraphStyle);
        // Kein Text hinzugefügt - bleibt leer

        $result = $this->converter->convert($textRun, $this->context);

        $this->assertStringContainsString('>&#32;</p>', $result);
        $this->assertStringContainsString('border:', $result);
        $this->assertStringContainsString('padding:', $result);
    }

    /**
     * Test: Einheitlicher Border rundherum wird zu border shorthand.
     */
    public function testUniformBorderCreatesShorthand(): void
    {
        $textRun = $this->createTextRunWithBorders([
            'top'    => ['size' => 4, 'color' => '000000', 'style' => 'single'],
            'left'   => ['size' => 4, 'color' => '000000', 'style' => 'single'],
            'right'  => ['size' => 4, 'color' => '000000', 'style' => 'single'],
            'bottom' => ['size' => 4, 'color' => '000000', 'style' => 'single'],
        ]);

        $result = $this->converter->convert($textRun, $this->context);

        $this->assertStringContainsString('border: 0.01cm solid #000000;', $result);
        $this->assertStringContainsString('padding: 0.2cm;', $result);
        $this->assertStringNotContainsString('border-top:', $result);
    }

    /**
     * Test: Unterschiedliche Borders werden individuell gesetzt.
     */
    public function testDifferentBordersCreateIndividualStyles(): void
    {
        $textRun = $this->createTextRunWithBorders([
            'top'    => ['size' => 8, 'color' => 'FF0000', 'style' => 'single'],
            'bottom' => ['size' => 8, 'color' => 'FF0000', 'style' => 'single'],
        ]);

        $result = $this->converter->convert($textRun, $this->context);

        $this->assertStringContainsString('border-top: 0.01cm solid #FF0000;', $result);
        $this->assertStringContainsString('border-bottom: 0.01cm solid #FF0000;', $result);
        $this->assertStringContainsString('padding: 0.2cm;', $result);
        $this->assertStringNotContainsString('border-left:', $result);
        $this->assertStringNotContainsString('border-right:', $result);
    }

    /**
     * Test: Keine Borders setzt keinen Border-Style (und kein Padding).
     */
    public function testNoBordersSetsNoBorderStyle(): void
    {
        $textRun = new TextRun();
        $textRun->addText('Test text');

        $result = $this->converter->convert($textRun, $this->context);

        $this->assertStringNotContainsString('border', $result);
        $this->assertStringNotContainsString('padding', $result);
    }

    /**
     * Test: Border wird mit anderen Styles kombiniert.
     */
    public function testBorderCombinedWithOtherStyles(): void
    {
        $paragraphStyle = new Paragraph();
        $paragraphStyle->setBorderTopSize(4);
        $paragraphStyle->setBorderTopColor('000000');
        $paragraphStyle->setBorderTopStyle('single');
        $paragraphStyle->setBorderLeftSize(4);
        $paragraphStyle->setBorderLeftColor('000000');
        $paragraphStyle->setBorderLeftStyle('single');
        $paragraphStyle->setBorderRightSize(4);
        $paragraphStyle->setBorderRightColor('000000');
        $paragraphStyle->setBorderRightStyle('single');
        $paragraphStyle->setBorderBottomSize(4);
        $paragraphStyle->setBorderBottomColor('000000');
        $paragraphStyle->setBorderBottomStyle('single');
        $paragraphStyle->setAlignment(\PhpOffice\PhpWord\SimpleType\Jc::CENTER);

        $textRun = new TextRun($paragraphStyle);
        $textRun->addText('Test text');

        $result = $this->converter->convert($textRun, $this->context);

        $this->assertStringContainsString('border: 0.01cm solid #000000;', $result);
        $this->assertStringContainsString('padding: 0.2cm;', $result);
        $this->assertStringContainsString('text-align: center;', $result);
    }

    /**
     * Test: Word Style "double" wird zu CSS "double".
     */
    public function testDoubleBorderStyleMapping(): void
    {
        $textRun = $this->createTextRunWithBorders([
            'top'    => ['size' => 4, 'color' => '000000', 'style' => 'double'],
            'left'   => ['size' => 4, 'color' => '000000', 'style' => 'double'],
            'right'  => ['size' => 4, 'color' => '000000', 'style' => 'double'],
            'bottom' => ['size' => 4, 'color' => '000000', 'style' => 'double'],
        ]);

        $result = $this->converter->convert($textRun, $this->context);

        $this->assertStringContainsString('border: 0.01cm double #000000;', $result);
        $this->assertStringContainsString('padding: 0.2cm;', $result);
    }

    /**
     * Test: Unbekannter Word Style fällt auf "solid" zurück.
     */
    public function testUnknownStyleDefaultsToSolid(): void
    {
        $textRun = $this->createTextRunWithBorders([
            'top'    => ['size' => 4, 'color' => '000000', 'style' => 'unknown'],
            'left'   => ['size' => 4, 'color' => '000000', 'style' => 'unknown'],
            'right'  => ['size' => 4, 'color' => '000000', 'style' => 'unknown'],
            'bottom' => ['size' => 4, 'color' => '000000', 'style' => 'unknown'],
        ]);

        $result = $this->converter->convert($textRun, $this->context);

        $this->assertStringContainsString('border: 0.01cm solid #000000;', $result);
        $this->assertStringContainsString('padding: 0.2cm;', $result);
    }

    /**
     * Hilfsmethode zum Erstellen eines TextRuns mit Borders.
     */
    private function createTextRunWithBorders(array $borders): TextRun
    {
        $paragraphStyle = new Paragraph();

        foreach ($borders as $side => $border) {
            $setterSize  = 'setBorder' . ucfirst($side) . 'Size';
            $setterColor = 'setBorder' . ucfirst($side) . 'Color';
            $setterStyle = 'setBorder' . ucfirst($side) . 'Style';

            if (isset($border['size'])) {
                $paragraphStyle->$setterSize($border['size']);
            }
            if (isset($border['color'])) {
                $paragraphStyle->$setterColor($border['color']);
            }
            if (isset($border['style'])) {
                $paragraphStyle->$setterStyle($border['style']);
            }
        }

        $textRun = new TextRun($paragraphStyle);
        $textRun->addText('Test text');

        return $textRun;
    }
}
