<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Metadata;

enum TrackChangeType: string
{
    case None = 'none';
    case Inserted = 'inserted';
    case Deleted = 'deleted';
}
