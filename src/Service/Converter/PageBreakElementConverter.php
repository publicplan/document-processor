<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use Publicplan\DocumentProcessor\Model\ConversionContext;
use PhpOffice\PhpWord\Element\PageBreak;

/**
 * Konvertiert Seitenumbruch-Elemente in HTML.
 */
class PageBreakElementConverter implements ElementConverterInterface
{
    public function supports(object $element): bool
    {
        return $element instanceof PageBreak;
    }

    public function convert(object $element, ConversionContext $context): string
    {
        return '<div class="page-break">Seitenwechsel</div>';
    }

    public function getPriority(): int
    {
        return 25; // Nach Listen, vor komplexeren Elementen
    }
}
