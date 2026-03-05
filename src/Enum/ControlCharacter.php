<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Enum;

/**
 * Enum für visuelle Steuerzeichen im HTML-Output.
 * 
 * Diese Zeichen werden im Frontend als visuelle Hilfe angezeigt.
 */
enum ControlCharacter: string
{
    case NONBREAKING_SPACE = '<span class="jrvControlCharacter">&deg;</span>';
    case BREAK             = '<span class="jrvControlCharacter">&#x21b5;</span>';
    case PARAGRAPH         = '<span class="jrvControlCharacter">&para;</span>';
}
