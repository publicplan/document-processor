<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service\Converter;

use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Paragraph;
use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Service\Converter\FontScaleHelper;

class FontScaleHelperTest extends TestCase
{
    protected function tearDown(): void
    {
        Style::resetStyles();
    }

    public function testCreateScaleAttributeReturnsNullForMatchingSize(): void
    {
        $attribute = FontScaleHelper::createScaleAttribute(10.0, 10.0);

        $this->assertNull($attribute);
    }

    public function testCreateScaleAttributeReturnsNormalizedScale(): void
    {
        $attribute = FontScaleHelper::createScaleAttribute(12.0, 10.0);

        $this->assertSame('data-font-scale="1.2"', $attribute);
    }

    public function testResolveFontSizeFromDirectFontStyle(): void
    {
        $font = new Font();
        $font->setSize(14);

        $size = FontScaleHelper::resolveFontSize($font);

        $this->assertSame(14.0, $size);
    }

    public function testResolveFontSizeFromNamedStyle(): void
    {
        Style::addFontStyle('customStyle', ['size' => 15]);

        $size = FontScaleHelper::resolveFontSize('customStyle');

        $this->assertSame(15.0, $size);
    }

    public function testResolveFontSizeThroughParagraphBasedOnChain(): void
    {
        Style::addFontStyle('Normal', ['size' => 10]);
        Style::addFontStyle('HeadingBase', ['size' => 16], ['basedOn' => 'Normal']);
        Style::addParagraphStyle('HeadingChild', ['basedOn' => 'HeadingBase']);

        $paragraph = new Paragraph();
        $paragraph->setStyleName('HeadingChild');

        $size = FontScaleHelper::resolveFontSize(null, $paragraph);

        $this->assertSame(16.0, $size);
    }

    public function testResolveFontSizeFromFontParagraphFallback(): void
    {
        Style::addFontStyle('Normal', ['size' => 10]);
        Style::addFontStyle('ParentStyle', ['size' => 13], ['basedOn' => 'Normal']);
        Style::addParagraphStyle('ChildStyle', ['basedOn' => 'ParentStyle']);

        $font = new Font();
        $font->setParagraph('ChildStyle');

        $size = FontScaleHelper::resolveFontSize($font);

        $this->assertSame(13.0, $size);
    }
}
