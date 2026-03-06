<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use PhpOffice\PhpWord\Element\Link as DocLink;
use Publicplan\DocumentProcessor\Model\ConversionContext;

/**
 * Konvertiert Link-Elemente in HTML.
 */
class LinkElementConverter implements ElementConverterInterface
{
    public function supports(object $element): bool
    {
        return $element instanceof DocLink;
    }

    public function convert(object $element, ConversionContext $context): string
    {
        /** @var DocLink $element */

        // Gelöschte Links ignorieren
        if ($element->getFontStyle()?->isStrikethrough()) {
            return '##deleted##';
        }

        /** @noinspection HtmlUnknownTarget */
        return sprintf(
            '<a href="%s">%s</a>',
            htmlspecialchars($element->getSource(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($element->getText(), ENT_QUOTES, 'UTF-8')
        );
    }

    public function getPriority(): int
    {
        return 12; // Zwischen Text und TextRun
    }
}
