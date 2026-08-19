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
     * TextRun mit Text-Element (Override) wird mit Span-Wrapper und data-font-scale.
     */
    public function testTextRunWrapsOverrideSizedTextInSpan(): void
    {
        $context = new ConversionContext();
        $context->setDefaultFontSize(10.0);

        $textRun = new TextRun();
        $textRun->addText('Hallo', ['size' => 12]);

        $converter = new TextRunElementConverter();
        $result = $converter->convert($textRun, $context);

        // TextRunElementConverter wrappet mit Span und data-font-scale
        $this->assertStringContainsString('<span data-font-scale="1.2">Hallo</span>', $result);
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
     * TextRun mit Link-Element (Override) wird mit Span-Wrapper und data-font-scale.
     */
    public function testTextRunWrapsOverrideSizedLinkInSpan(): void
    {
        $context = new ConversionContext();
        $context->setDefaultFontSize(10.0);

        $textRun = new TextRun();
        $textRun->addLink('https://example.org', 'Link', ['size' => 11]);

        $converter = new TextRunElementConverter();
        $result = $converter->convert($textRun, $context);

        // TextRunElementConverter wrappet Link mit Span und data-font-scale
        $this->assertStringContainsString('<span data-font-scale="1.1">', $result);
        $this->assertStringContainsString('<a href="https://example.org">Link</a>', $result);
        $this->assertStringContainsString('</span>', $result);
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

        // Beide Texte sind in einem Span zusammengefasst (keine </span><span>-Sequenz)
        $this->assertStringContainsString('<span data-font-scale="1.2">Erstes Zweites</span>', $result);
        $this->assertStringNotContainsString('</span><span', $result);
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

        // Text erbt Schriftgröße 14pt von Paragraph-Style
        $this->assertStringContainsString('data-font-scale="1.4"', $result);
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

        // Link erbt Schriftgröße 12pt von Paragraph-Style
        $this->assertStringContainsString('data-font-scale="1.2"', $result);
        $this->assertStringContainsString('<a href="https://example.org">Link</a>', $result);
    }
}

