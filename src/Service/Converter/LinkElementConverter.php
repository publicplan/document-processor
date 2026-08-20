<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use PhpOffice\PhpWord\Element\Link as DocLink;
use PhpOffice\PhpWord\Style\Font;
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

        /** @noinspection HtmlUnknownTarget */
        $linkHtml = sprintf(
            '<a href="%s">%s</a>',
            htmlspecialchars($element->getSource(), ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($element->getText(), ENT_QUOTES, 'UTF-8')
        );

        if ($element->getFontStyle() instanceof Font && $element->getFontStyle()->isStrikethrough()) {
            return DeletedContentHelper::renderDeletedHtml($linkHtml, $context);
        }

        return $linkHtml;
    }

    public function getPriority(): int
    {
        return 12; // Zwischen Text und TextRun
    }
}
