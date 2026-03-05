<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Tests\Service\Converter;

use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Service\Converter\ElementConverterRegistry;
use Publicplan\DocumentProcessor\Service\Converter\TextElementConverter;
use Publicplan\DocumentProcessor\Service\Converter\BreakElementConverter;
use Publicplan\DocumentProcessor\Service\Converter\LinkElementConverter;
use PHPUnit\Framework\TestCase;

/**
 * Tests für die Element Converter.
 */
class ElementConverterTest extends TestCase
{
    /**
     * Test: TextElementConverter erkennt Text-Elemente korrekt.
     */
    public function testTextConverterSupportsTextElements(): void
    {
        $converter = new TextElementConverter();
        $textElement = $this->createMock(\PhpOffice\PhpWord\Element\Text::class);

        $this->assertTrue($converter->supports($textElement));
    }

    /**
     * Test: TextElementConverter ignoriert nicht-Text-Elemente.
     */
    public function testTextConverterDoesNotSupportOtherElements(): void
    {
        $converter = new TextElementConverter();
        $otherElement = $this->createMock(\PhpOffice\PhpWord\Element\Table::class);

        $this->assertFalse($converter->supports($otherElement));
    }

    /**
     * Test: BreakElementConverter erkennt Break-Elemente.
     */
    public function testBreakConverterSupportsBreakElements(): void
    {
        $converter = new BreakElementConverter();
        $breakElement = $this->createMock(\PhpOffice\PhpWord\Element\TextBreak::class);

        $this->assertTrue($converter->supports($breakElement));
    }

    /**
     * Test: LinkElementConverter erkennt Link-Elemente.
     */
    public function testLinkConverterSupportsLinkElements(): void
    {
        $converter = new LinkElementConverter();
        $linkElement = $this->createMock(\PhpOffice\PhpWord\Element\Link::class);

        $this->assertTrue($converter->supports($linkElement));
    }

    /**
     * Test: Converter Registry findet passenden Converter.
     */
    public function testRegistryFindsCorrectConverter(): void
    {
        $registry = new ElementConverterRegistry();
        $registry->registerDefaultConverters();

        $textElement = $this->createMock(\PhpOffice\PhpWord\Element\Text::class);
        $converter = $registry->findConverter($textElement);

        $this->assertInstanceOf(TextElementConverter::class, $converter);
    }

    /**
     * Test: Converter Registry gibt null zurück für unbekannte Elemente.
     */
    public function testRegistryReturnsNullForUnknownElements(): void
    {
        $registry = new ElementConverterRegistry();
        $registry->registerDefaultConverters();

        // Ein Element, das nicht registriert ist
        $unknownElement = new \stdClass();
        $converter = $registry->findConverter($unknownElement);

        $this->assertNull($converter);
    }

    /**
     * Test: Converter haben korrekte Prioritäten.
     */
    public function testConverterPriorities(): void
    {
        $textConverter = new TextElementConverter();
        $breakConverter = new BreakElementConverter();
        $linkConverter = new LinkElementConverter();

        // Text hat niedrigste Priorität
        $this->assertEquals(10, $textConverter->getPriority());
        // Break kommt danach
        $this->assertEquals(11, $breakConverter->getPriority());
        // Link kommt danach
        $this->assertEquals(12, $linkConverter->getPriority());
    }

    /**
     * Test: Registry sortiert Converter nach Priorität.
     */
    public function testRegistrySortsByPriority(): void
    {
        $registry = new ElementConverterRegistry();
        
        $lowPriority = new TextElementConverter();
        $highPriority = new LinkElementConverter();
        
        // Zuerst niedrige, dann hohe Priorität registrieren
        $registry->register($lowPriority);
        $registry->register($highPriority);

        $converters = $registry->getConverters();
        
        // Hohe Priorität sollte zuerst kommen
        $this->assertSame($highPriority, $converters[0]);
        $this->assertSame($lowPriority, $converters[1]);
    }
}
