<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use Publicplan\DocumentProcessor\Model\ConversionContext;

/**
 * Registry für alle Element-Converter.
 * Verwaltet die Converter und delegiert die Konvertierung.
 */
class ElementConverterRegistry
{
    /** @var ElementConverterInterface[] */
    private array $converters = [];

    public function __construct()
    {
    }

    /**
     * Registriert einen Converter.
     */
    public function register(ElementConverterInterface $converter): void
    {
        $this->converters[] = $converter;
        $this->sortConverters();
    }

    /**
     * Registriert die Standard-Converter.
     */
    public function registerDefaultConverters(): void
    {
        $this->register(new TextElementConverter());
        $this->register(new BreakElementConverter());
        $this->register(new LinkElementConverter());
        $this->register(new TextRunElementConverter());
        $this->register(new TextBoxElementConverter());
        $this->register(new ListElementConverter());
        $this->register(new PageBreakElementConverter());
        $this->register(new TableElementConverter());
    }

    /**
     * Findet den passenden Converter für ein Element.
     */
    public function findConverter(object $element): ?ElementConverterInterface
    {
        return array_find($this->converters, static fn($converter) => $converter->supports($element));

    }

    /**
     * Konvertiert ein Element.
     */
    public function convert(object $element, ConversionContext $context): ?string
    {
        return $this->findConverter($element)?->convert($element, $context);

    }

    /**
     * Sortiert die Converter nach Priorität (absteigend).
     */
    private function sortConverters(): void
    {
        usort($this->converters, static function (ElementConverterInterface $a, ElementConverterInterface $b): int {
            return $b->getPriority() <=> $a->getPriority();
        });
    }

    /**
     * Gibt alle registrierten Converter zurück.
     *
     * @return ElementConverterInterface[]
     */
    public function getConverters(): array
    {
        return $this->converters;
    }
}
