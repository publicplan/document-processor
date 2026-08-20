<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service\Converter;

use Publicplan\DocumentProcessor\Model\ConversionContext;

/**
 * Hilfsfunktionen für gelöschte Inhalte.
 */
final class DeletedContentHelper
{
    public const string DELETED_MARKER = '##deleted##';

    public static function renderDeletedHtml(string $html, ConversionContext $context): string
    {
        if ($html === '') {
            return '';
        }

        if ($context->shouldRemoveDeletedContent()) {
            return self::DELETED_MARKER;
        }

        return sprintf('<del>%s</del>', $html);
    }

    public static function renderDeletedBreak(ConversionContext $context): string
    {
        if ($context->shouldRemoveDeletedContent()) {
            return '';
        }

        return '<br>' . PHP_EOL;
    }
}
