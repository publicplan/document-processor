<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service;

use Publicplan\DocumentProcessor\Exception\DocumentLoadException;
use Publicplan\DocumentProcessor\Model\ParserError;
use Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use ZipArchive;

/**
 * Service zum Laden und Validieren von Word-Dokumenten.
 */
class DocumentLoader
{
    public function __construct()
    {
    }

    /**
     * Lädt ein Word-Dokument und validiert es.
     *
     * @param string $filePath Absoluter Pfad zur .docx Datei
     * @return PhpWord Das geladene Dokument
     * @throws DocumentLoadException Wenn das Dokument nicht geladen werden kann
     */
    public function load(string $filePath): PhpWord
    {
        if (!file_exists($filePath)) {
            throw new DocumentLoadException(
                'Dokument nicht gefunden',
                $filePath,
                'Die Datei existiert nicht'
            );
        }

        if (!is_readable($filePath)) {
            throw new DocumentLoadException(
                'Dokument nicht lesbar',
                $filePath,
                'Keine Leserechte für die Datei'
            );
        }

        try {
            $doc = IOFactory::load($filePath);
        } catch (Exception $exception) {
            $message = $exception->getMessage();

            // Spezifische Fehlermeldung für oMath-Formeln
            if (str_contains($message, ' oMath ')) {
                $message = 'Wurden in dem Dokument evtl. mathematische Formeln verwendet? Meldung: ' . $message;
            }

            throw new DocumentLoadException(
                'Fehler beim Laden des Dokuments',
                $filePath,
                $message,
                0,
                $exception
            );
        }

        return $doc;
    }

    /**
     * Prüft, ob das Dokument nicht übernommene Änderungen (Track Changes) enthält.
     *
     * @param string $filePath Absoluter Pfad zur .docx Datei
     * @return bool True, wenn offene Änderungen gefunden wurden
     * @throws DocumentLoadException Wenn die Datei nicht geöffnet werden kann
     */
    public function hasUnacceptedChanges(string $filePath): bool
    {
        $zip = new ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw new DocumentLoadException(
                'Konnte die Datei nicht öffnen',
                $filePath,
                'ZIP-Archiv konnte nicht geöffnet werden'
            );
        }

        $hasChanges = false;

        // Wir suchen explizit nach den öffnenden Tags der Revisionen
        // Das Leerzeichen oder die schließende Klammer verhindert RSID-Fehler
        $patterns = [
            '/<w:ins\s/',
            '/<w:del\s/',
            '/<w:moveFrom\s/',
            '/<w:moveTo\s/',
        ];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);

            if (preg_match('/^word\/(document|header|footer)\d*\.xml$/', $entryName)) {
                $content = $zip->getFromIndex($i);

                foreach ($patterns as $pattern) {
                    if (preg_match($pattern, $content)) {
                        $hasChanges = true;
                        break 2;
                    }
                }
            }
        }

        $zip->close();

        return $hasChanges;
    }

    /**
     * Lädt das Dokument und prüft gleichzeitig auf unübernommene Änderungen.
     *
     * @param string $filePath Absoluter Pfad zur .docx Datei
     * @param bool|null $hasChanges Output-Parameter: True wenn Track-Changes vorhanden
     * @return PhpWord Das geladene Dokument
     * @throws DocumentLoadException Wenn das Dokument nicht geladen werden kann
     */
    public function loadWithChangeCheck(string $filePath, ?bool &$hasChanges = null): PhpWord
    {
        $doc = $this->load($filePath);
        $hasChanges = $this->hasUnacceptedChanges($filePath);

        return $doc;
    }
}
