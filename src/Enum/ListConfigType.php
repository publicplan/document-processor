<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Enum;

use PhpOffice\PhpWord\SimpleType\NumberFormat;

/**
 * Enum für Listen-Format-Typen.
 *
 * Mappt PhpOffice NumberFormat auf interne Konstanten.
 */
enum ListConfigType: string
{
    case Bullet      = NumberFormat::BULLET;
    case Decimal     = NumberFormat::DECIMAL;
    case UpperRoman  = NumberFormat::UPPER_ROMAN;
    case LowerRoman  = NumberFormat::LOWER_ROMAN;
    case UpperLetter = NumberFormat::UPPER_LETTER;
    case LowerLetter = NumberFormat::LOWER_LETTER;
}
