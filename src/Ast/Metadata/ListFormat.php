<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Metadata;

enum ListFormat: string
{
    case Bullet = 'bullet';
    case Number = 'number';
    case Roman = 'roman';
    case RomanLower = 'roman-lower';
    case Letter = 'letter';
    case LetterLower = 'letter-lower';
}
