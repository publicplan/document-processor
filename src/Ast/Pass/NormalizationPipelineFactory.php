<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Pass;

/**
 * Factory für die Standard-Normalisierungs-Pipeline.
 * 
 * Diese Klasse orchestriert die Erstellung der completten Pass-Pipeline
 * in der korrekten Reihenfolge wie in Run 04 dokumentiert.
 */
class NormalizationPipelineFactory
{
    /**
     * Erstellt die Standard-Normalisierungs-Pipeline.
     *
     * **Pass-Reihenfolge (kritisch, nicht veränderbar)**:
     * 
     * 1. ListNormalizationPass
     *    - Gruppiert aufeinanderfolgende ListItemNodes mit gleicher numId in ListNodes
     * 
     * 2. ListContinuationPass
     *    - Markiert Listenfortsetzungen wenn eine numId nach einer Unterbrechung wieder auftritt
     * 
     * 3. ListSpacerPass
     *    - Entfernt leere Absätze, die als Spacer zwischen Listenelementen dienen
     * 
     * 4. BorderGroupingPass
     *    - Gruppiert aufeinanderfolgende Absätze mit identischen Border-Stilen
     * 
     * 5. EmptyParagraphPass
     *    - Normalisiert leere Absätze, entfernt Trailing-Absätze
     * 
     * 6. InlineScalePass
     *    - Verhindert Verschachtelung von ScaleNodes
     * 
     * 7. HangingIndentPass
     *    - Markiert Absätze mit Hanging-Indent-Struktur
     */
    public static function createStandardPipeline(): AstNormalizationPipeline
    {
        $pipeline = new AstNormalizationPipeline();

        $pipeline->addPass(new ListNormalizationPass());
        $pipeline->addPass(new ListContinuationPass());
        $pipeline->addPass(new ListSpacerPass());
        $pipeline->addPass(new BorderGroupingPass());
        $pipeline->addPass(new EmptyParagraphPass());
        $pipeline->addPass(new InlineScalePass());
        $pipeline->addPass(new HangingIndentPass());

        return $pipeline;
    }
}
