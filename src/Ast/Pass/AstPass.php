<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Pass;

use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;

/**
 * Schnittstelle für AST-Normalisierungs-Passes.
 * 
 * Jeder Pass führt eine einzelne, dokumentierte Strukturtransformation durch.
 * Passes werden in definierter Reihenfolge ausgeführt und sollen idempotent sein.
 */
interface AstPass
{
    /**
     * Führt die Normalisierungstransformation durch.
     *
     * @param DocumentNode $document Der zu transformierende AST
     * @return DocumentNode Der transformierte AST (kann dasselbe Objekt sein)
     */
    public function apply(DocumentNode $document): DocumentNode;

    /**
     * Eindeutiger Name dieses Passes.
     * Wird für Debugging und Dokumentation verwendet.
     */
    public function getName(): string;

    /**
     * Menschenlesbare Beschreibung, was dieser Pass tut.
     */
    public function getDescription(): string;
}
