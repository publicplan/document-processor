<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service;

use Exception;
use DOMDocument;
use DOMXPath;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Style;
use Publicplan\DocumentProcessor\Exception\DocumentLoadException;
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
     *
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

        $this->registerParagraphStylesFromStylesXml($filePath);

        return $doc;
    }

    /**
     * Prüft, ob das Dokument nicht übernommene Änderungen (Track Changes) enthält.
     *
     * @param string $filePath Absoluter Pfad zur .docx Datei
     *
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

        // Wir suchen explizit nach den öffnenden Tags der Revisionen.
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
     * Lädt das Dokument und prüft gleichzeitig auf nicht übernommene Änderungen.
     *
     * @param string    $filePath   Absoluter Pfad zur .docx Datei
     * @param bool|null $hasChanges Output-Parameter: True wenn Track-Changes vorhanden
     *
     * @return PhpWord Das geladene Dokument
     * @throws DocumentLoadException Wenn das Dokument nicht geladen werden kann
     */
    public function loadWithChangeCheck(string $filePath, ?bool &$hasChanges = null): PhpWord
    {
        $defaultFontSize = null;
        return $this->loadWithDocumentMetadata($filePath, $hasChanges, $defaultFontSize);
    }

    /**
     * Lädt das Dokument, prüft auf Änderungen und extrahiert Metadaten.
     *
     * @param string      $filePath        Absoluter Pfad zur .docx Datei
     * @param bool|null   $hasChanges      Output-Parameter: True wenn Track-Changes vorhanden
     * @param float|null  $defaultFontSize Output-Parameter: Effektive Dokument-Default-Fontgröße
     *
     * @return PhpWord Das geladene Dokument
     * @throws DocumentLoadException Wenn das Dokument nicht geladen werden kann
     */
    public function loadWithDocumentMetadata(
        string $filePath,
        ?bool &$hasChanges = null,
        ?float &$defaultFontSize = null
    ): PhpWord
    {
        $doc        = $this->load($filePath);
        $hasChanges = $this->hasUnacceptedChanges($filePath);
        $defaultFontSize = $this->extractDocumentDefaultFontSize($filePath) ?? (float)Settings::DEFAULT_FONT_SIZE;

        return $doc;
    }

    /**
     * Extrahiert die in styles.xml definierte Default-Fontgröße in pt.
     *
     * @return float|null Defaultgröße in pt oder null wenn nicht explizit im Dokument gesetzt
     * @throws DocumentLoadException Wenn die DOCX-Datei nicht geöffnet oder styles.xml nicht geparst werden kann
     */
    public function extractDocumentDefaultFontSize(string $filePath): ?float
    {
        $zip = new ZipArchive();

        if ($zip->open($filePath) !== true) {
            throw new DocumentLoadException(
                'Konnte die Datei nicht öffnen',
                $filePath,
                'ZIP-Archiv konnte nicht geöffnet werden'
            );
        }

        try {
            $stylesXml = $zip->getFromName('word/styles.xml');
            if ($stylesXml === false) {
                return null;
            }

            $document = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                if (!$document->loadXML($stylesXml)) {
                    throw new DocumentLoadException(
                        'Styles konnten nicht gelesen werden',
                        $filePath,
                        'styles.xml enthält ungültiges XML'
                    );
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }

            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $value = $this->readDocDefaultFontSizeHalfPoints($xpath, 'w:docDefaults/w:rPrDefault/w:rPr/w:sz/@w:val')
                ?? $this->readDocDefaultFontSizeHalfPoints($xpath, 'w:docDefaults/w:rPrDefault/w:rPr/w:szCs/@w:val');

            return $value !== null ? $value / 2.0 : null;
        } finally {
            $zip->close();
        }
    }

    private function readDocDefaultFontSizeHalfPoints(\DOMXPath $xpath, string $query): ?float
    {
        $value = $xpath->evaluate("string($query)");
        if (!is_string($value) || $value === '' || !is_numeric($value)) {
            return null;
        }

        return (float)$value;
    }

    private function registerParagraphStylesFromStylesXml(string $filePath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return;
        }

        try {
            $stylesXml = $zip->getFromName('word/styles.xml');
            if ($stylesXml === false) {
                return;
            }

            $document = new DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try {
                if (!$document->loadXML($stylesXml)) {
                    return;
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }

            $xpath = new DOMXPath($document);
            $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

            $styles = $xpath->query('//w:style[@w:type="paragraph"]');
            if ($styles === false) {
                return;
            }

            foreach ($styles as $styleNode) {
                $styleId = trim((string)$xpath->evaluate('string(@w:styleId)', $styleNode));
                if ($styleId === '' || Style::getStyle($styleId) !== null) {
                    continue;
                }

                $styleDefinition = [];
                $basedOn         = trim((string)$xpath->evaluate('string(w:basedOn/@w:val)', $styleNode));
                if ($basedOn !== '') {
                    $styleDefinition['basedOn'] = $basedOn;
                }

                $indentation = [];
                $left        = $this->readTwips($xpath->evaluate('string(w:pPr/w:ind/@w:left)', $styleNode));
                $hanging     = $this->readTwips($xpath->evaluate('string(w:pPr/w:ind/@w:hanging)', $styleNode));
                $firstLine   = $this->readTwips($xpath->evaluate('string(w:pPr/w:ind/@w:firstLine)', $styleNode));

                if ($left !== null) {
                    $indentation['left'] = $left;
                }
                if ($hanging !== null) {
                    $indentation['hanging'] = $hanging;
                }
                if ($firstLine !== null) {
                    $indentation['firstLine'] = $firstLine;
                }
                if ($indentation !== []) {
                    $styleDefinition['indentation'] = $indentation;
                }

                Style::addParagraphStyle($styleId, $styleDefinition);
            }
        } finally {
            $zip->close();
        }
    }

    private function readTwips(mixed $rawValue): ?float
    {
        if (!is_string($rawValue) || $rawValue === '' || !is_numeric($rawValue)) {
            return null;
        }

        return (float)$rawValue;
    }
}
