<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Ast\Pass;

use Publicplan\DocumentProcessor\Ast\Node\DocumentNode;

/**
 * Orchestriert die Ausführung von AST-Normalisierungs-Passes in definierter Reihenfolge.
 * 
 * Die Pass-Reihenfolge ist kritisch und dokumentiert in Run 04.
 * Jeder Pass muss abgeschlossen sein, bevor der nächste beginnt.
 */
class AstNormalizationPipeline
{
    /** @var AstPass[] */
    private array $passes = [];

    /**
     * Registriert einen Pass in der Pipeline.
     * Passes werden in der Registrierungsreihenfolge ausgeführt.
     */
    public function addPass(AstPass $pass): self
    {
        $this->passes[] = $pass;
        return $this;
    }

    /**
     * Führt alle registrierten Passes der Reihe nach auf dem AST aus.
     *
     * @param DocumentNode $document Der zu normalisierenden AST
     * @return array {
     *     'document': DocumentNode (normalisierter AST),
     *     'passes': array<string, array> (pro Pass: name, description, applied_successfully)
     * }
     */
    public function normalize(DocumentNode $document): array
    {
        $result = [
            'document' => $document,
            'passes' => []
        ];

        foreach ($this->passes as $pass) {
            try {
                $result['document'] = $pass->apply($result['document']);
                $result['passes'][] = [
                    'name' => $pass->getName(),
                    'description' => $pass->getDescription(),
                    'success' => true,
                    'error' => null
                ];
            } catch (\Exception $e) {
                $result['passes'][] = [
                    'name' => $pass->getName(),
                    'description' => $pass->getDescription(),
                    'success' => false,
                    'error' => $e->getMessage()
                ];
                // Pipeline stoppt bei Fehler
                throw new AstNormalizationException(
                    "Pass '{$pass->getName()}' fehlgeschlagen: {$e->getMessage()}",
                    previous: $e
                );
            }
        }

        return $result;
    }

    /**
     * Gibt eine Textbeschreibung der konfigurierten Pass-Reihenfolge aus.
     */
    public function describeOrder(): string
    {
        if (empty($this->passes)) {
            return "Keine Passes konfiguriert.";
        }

        $lines = ["Normalisierungs-Pass-Reihenfolge:"];
        foreach ($this->passes as $i => $pass) {
            $lines[] = sprintf(
                "%d. %s: %s",
                $i + 1,
                $pass->getName(),
                $pass->getDescription()
            );
        }
        return implode("\n", $lines);
    }
}
