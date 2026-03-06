<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Enum;

/**
 * Enum für Parser-Kontexte.
 *
 * Definiert die verschiedenen Kontexte, in denen Parser-Fehler auftreten können.
 */
enum Context: string
{
    case DOCUMENT    = 'document';
    case PLACEHOLDER = 'placeholder';
    case FUNCTIONS   = 'functions';
    case CONDITIONS  = 'conditions';
}
