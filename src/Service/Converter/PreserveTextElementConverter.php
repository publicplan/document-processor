<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Model\ParserError;
use PhpOffice\PhpWord\Element\PreserveText;

/**
 * Konvertiert PreserveText-Elemente (z.B. Felder) in HTML.
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
        $text = implode(' ', $element->getText());

        return $text;
    }

    public function getPriority(): int
    {
        return 13; // Zwischen Link und TextRun
    }
}
