<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use Publicplan\DocumentProcessor\Model\ConversionContext;

/**
 * Interface für alle Element-Converter.
 * Jeder Converter ist für die Umwandlung eines bestimmten Word-Element-Typs in HTML zuständig.
 */
interface ElementConverterInterface
{
    /**
     * Prüft, ob dieser Converter für das gegebene Element zuständig ist.
     *
     * @param object $element Das zu prüfende Word-Element
     */
    public function supports(object $element): bool;

    /**
     * Konvertiert das Word-Element in HTML.
     *
     * @param object $element Das zu konvertierende Word-Element
     * @param ConversionContext $context Kontext mit Einstellungen und Parser-Messages
     * @return string Das generierte HTML
     */
    public function convert(object $element, ConversionContext $context): string;

    /**
     * Gibt die Priorität des Converters zurück (höher = früher geprüft).
     * Nützlich für komplexe Elemente, die vor einfachen geprüft werden müssen.
     */
    public function getPriority(): int;
}
