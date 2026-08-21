<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service\Converter;

use PhpOffice\PhpWord\Element\Link;
use PhpOffice\PhpWord\Element\Text;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\Style;
use PHPUnit\Framework\TestCase;
use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Service\Converter\TextElementConverter;
use Publicplan\DocumentProcessor\Service\Converter\TextRunElementConverter;
use Publicplan\DocumentProcessor\Service\Converter\LinkElementConverter;

class TextAndLinkFontScaleTest extends TestCase
{
    protected function tearDown(): void
    {
        Style::resetStyles();
    }

    /**
     * Text-Element allein gibt kein Wrapper-Span zurück (nur raw HTML).
     * Der Wrapper wird von TextRunElementConverter hinzugefügt.
     */
    public function testTextElementNeverAddsOwnScaleWrapper(): void
    {
        $context = new ConversionContext();
        $context->setDefaultFontSize(10.0);

        $element   = new Text('Hallo', ['size' => 12]);
        $converter = new TextElementConverter();

        $result = $converter->convert($element, $context);

        // TextElementConverter gibt nur raw HTML zurück, kein Span-Wrapper
        $this->assertSame('Hallo', $result);
    }

    /**
     * Link-Element allein gibt kein data-font-scale-Attribut zurück.
     * Der Wrapper wird von TextRunElementConverter hinzugefügt.
     */
    public function testLinkElementNeverAddsOwnScaleAttribute(): void
    {
        $context = new ConversionContext();
        $context->setDefaultFontSize(10.0);

        $element   = new Link('https://example.org', 'Link', ['size' => 11]);
        $converter = new LinkElementConverter();

        $result = $converter->convert($element, $context);

        // LinkElementConverter gibt nur reines <a>-Tag zurück
        $this->assertSame('<a href="https://example.org">Link</a>', $result);
    }

    /**
     * TextRun mit genau einer skalierten Font-Gruppe zieht data-font-scale auf den Absatz.
     */
    public function testTextRunMovesOverrideSizedTextScaleToParagraph(): void
    {
        $context = new ConversionContext();
        $context->setDefaultFontSize(10.0);

        $textRun = new TextRun();
        $textRun->addText('Hallo', ['size' => 12]);

        $converter = new TextRunElementConverter();
        $result = $converter->convert($textRun, $context);

        $this->assertStringContainsString('<p style="margin-bottom: 0cm;" data-font-scale="1.2">Hallo</p>', $result);
        $this->assertStringNotContainsString('<span', $result);
    }

    /**
     * TextRun mit Text-Element (kein Override) wird OHNE Span-Wrapper.
     */
    public function testTextRunSkipsSpanWrapperWhenNoOverride(): void
    {
        $context = new ConversionContext();
        $context->setDefaultFontSize(12.0);

        $textRun = new TextRun();
        $textRun->addText('Hallo', ['size' => 12]);

        $converter = new TextRunElementConverter();
        $result = $converter->convert($textRun, $context);

        // Kein Override = kein Span-Wrapper
        $this->assertStringNotContainsString('<span', $result);
        $this->assertStringContainsString('Hallo', $result);
    }

    /**
     * TextRun mit genau einer skalierten Link-Gruppe zieht data-font-scale auf den Absatz.
     */
    public function testTextRunMovesOverrideSizedLinkScaleToParagraph(): void
    {
        $context = new ConversionContext();
        $context->setDefaultFontSize(10.0);

        $textRun = new TextRun();
        $textRun->addLink('https://example.org', 'Link', ['size' => 11]);

        $converter = new TextRunElementConverter();
        $result = $converter->convert($textRun, $context);

        $this->assertStringContainsString('<p style="margin-bottom: 0cm;" data-font-scale="1.1">', $result);
        $this->assertStringContainsString('<a href="https://example.org">Link</a>', $result);
        $this->assertStringNotContainsString('<span', $result);
    }

    /**
     * TextRun mit mehreren Elementen gleicher Schriftgröße wird zusammengefasst.
     */
    public function testTextRunGroupsConsecutiveElementsWithSameFontSize(): void
    {
        $context = new ConversionContext();
        $context->setDefaultFontSize(10.0);

        $textRun = new TextRun();
        $textRun->addText('Erstes ', ['size' => 12]);
        $textRun->addText('Zweites', ['size' => 12]);

        $converter = new TextRunElementConverter();
        $result = $converter->convert($textRun, $context);

        $this->assertStringContainsString('<p style="margin-bottom: 0cm;" data-font-scale="1.2">Erstes Zweites</p>', $result);
        $this->assertStringNotContainsString('<span', $result);
    }

    /**
     * TextRun mit unterschiedlichen Schriftgrößen erzeugt mehrere Spans.
     */
    public function testTextRunCreatesMultipleSpansForDifferentFontSizes(): void
    {
        $context = new ConversionContext();
        $context->setDefaultFontSize(10.0);

        $textRun = new TextRun();
        $textRun->addText('Kleine ', ['size' => 9]);
        $textRun->addText('Große', ['size' => 14]);

        $converter = new TextRunElementConverter();
        $result = $converter->convert($textRun, $context);

        // Zwei separate Spans für zwei unterschiedliche Schriftgrößen
        $this->assertStringContainsString('<span data-font-scale="0.9">Kleine </span>', $result);
        $this->assertStringContainsString('<span data-font-scale="1.4">Große</span>', $result);
    }

    /**
     * TextRun mit Paragraph-Style Vererbung.
     */
    public function testTextRunResolvesSizeThroughParagraphStyleInheritance(): void
    {
        Style::addFontStyle('Normal', ['size' => 10]);
        Style::addFontStyle('HeadingBase', ['size' => 14], ['basedOn' => 'Normal']);
        Style::addParagraphStyle('HeadingChild', ['basedOn' => 'HeadingBase']);

        $context = new ConversionContext();
        $context->setDefaultFontSize(10.0);

        // Erstelle Paragraph-Style-Objekt
        $paragraphStyle = Style::getStyle('HeadingChild');
        
        $textRun = new TextRun($paragraphStyle);
        $textRun->addText('Ueberschrift');

        $converter = new TextRunElementConverter();
        $result = $converter->convert($textRun, $context);

        $this->assertStringContainsString('<p style="margin-bottom: 0cm;" data-font-scale="1.4">Ueberschrift</p>', $result);
        $this->assertStringNotContainsString('<span', $result);
    }

    /**
     * TextRun mit Link und Paragraph-Style Vererbung.
     */
    public function testTextRunLinkResolvesSizeThroughParagraphStyleInheritance(): void
    {
        Style::addFontStyle('Normal', ['size' => 10]);
        Style::addFontStyle('LinkBase', ['size' => 12], ['basedOn' => 'Normal']);
        Style::addParagraphStyle('LinkChild', ['basedOn' => 'LinkBase']);

        $context = new ConversionContext();
        $context->setDefaultFontSize(10.0);

        // Erstelle Paragraph-Style-Objekt
        $paragraphStyle = Style::getStyle('LinkChild');
        
        $textRun = new TextRun($paragraphStyle);
        $textRun->addLink('https://example.org', 'Link');

        $converter = new TextRunElementConverter();
        $result = $converter->convert($textRun, $context);

        $this->assertStringContainsString('<p style="margin-bottom: 0cm;" data-font-scale="1.2">', $result);
        $this->assertStringContainsString('<a href="https://example.org">Link</a>', $result);
        $this->assertStringNotContainsString('<span', $result);
    }

    /**
     * Inline-Markup innerhalb einer einzigen skalierten Gruppe bleibt erhalten, ohne äußeren Span.
     */
    public function testTextRunKeepsInlineMarkupWhenMovingScaleToParagraph(): void
    {
        $context = new ConversionContext();
        $context->setDefaultFontSize(10.0);

        $textRun = new TextRun();
        $textRun->addText('Fett', ['size' => 12, 'bold' => true]);
        $textRun->addText(' normal', ['size' => 12]);

        $converter = new TextRunElementConverter();
        $result = $converter->convert($textRun, $context);

        $this->assertStringContainsString('<p style="margin-bottom: 0cm;" data-font-scale="1.2"><strong>Fett</strong> normal</p>', $result);
        $this->assertStringNotContainsString('<span', $result);
    }
}
