<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Enum;

enum RenderMode: string
{
    case Legacy = 'legacy';
    case Ast = 'ast';
    case Compare = 'compare';
}
