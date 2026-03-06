<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use PhpOffice\PhpWord\Element\PreserveText;
use Publicplan\DocumentProcessor\Model\ConversionContext;

/**
 * Konvertiert PreserveText-Elemente (z. B. Felder) in HTML.
 */
class PreserveTextElementConverter implements ElementConverterInterface
{
    public function supports(object $element): bool
    {
        return $element instanceof PreserveText;
    }

    public function convert(object $element, ConversionContext $context): string
    {
        /** @var PreserveText $element */

        // PreserveText enthält oft Feldinhalte wie Seitenzahlen
        return implode(' ', $element->getText());
    }

    public function getPriority(): int
    {
        return 13; // Zwischen Link und TextRun
    }
}
