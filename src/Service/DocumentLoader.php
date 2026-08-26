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
     * Extrahiert style-relevante Snapshot-Daten aus styles.xml/numbering.xml für AST-Mapping.
     *
     * @return array<string, mixed>|null
     */
    public function extractAstStyleSnapshot(string $filePath): ?array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            return null;
        }

        try {
            $snapshot = [
                'styles' => [
                    'paragraph' => [],
                    'table' => [],
                    'defaults' => [
                        'paragraph' => [],
                    ],
                ],
                'numbering' => [
                    'numMap' => [],
                    'levels' => [],
                ],
            ];

            $stylesXml = $zip->getFromName('word/styles.xml');
            if (is_string($stylesXml) && $stylesXml !== '') {
                $stylesDoc = new DOMDocument();
                $previous = libxml_use_internal_errors(true);
                try {
                    if ($stylesDoc->loadXML($stylesXml)) {
                        $xpath = new DOMXPath($stylesDoc);
                        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                        $snapshot['styles'] = $this->extractStylesSnapshot($xpath);
                    }
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
            }

            $numberingXml = $zip->getFromName('word/numbering.xml');
            if (is_string($numberingXml) && $numberingXml !== '') {
                $numberingDoc = new DOMDocument();
                $previous = libxml_use_internal_errors(true);
                try {
                    if ($numberingDoc->loadXML($numberingXml)) {
                        $xpath = new DOMXPath($numberingDoc);
                        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
                        $snapshot['numbering'] = $this->extractNumberingSnapshot($xpath);
                    }
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($previous);
                }
            }

            return $snapshot;
        } finally {
            $zip->close();
        }
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
                $styles = [];
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
                $right       = $this->readTwips($xpath->evaluate('string(w:pPr/w:ind/@w:right)', $styleNode));
                $hanging     = $this->readTwips($xpath->evaluate('string(w:pPr/w:ind/@w:hanging)', $styleNode));
                $firstLine   = $this->readTwips($xpath->evaluate('string(w:pPr/w:ind/@w:firstLine)', $styleNode));
                $spaceBefore = $this->readTwips($xpath->evaluate('string(w:pPr/w:spacing/@w:before)', $styleNode));
                $spaceAfter  = $this->readTwips($xpath->evaluate('string(w:pPr/w:spacing/@w:after)', $styleNode));
                $line        = $this->readTwips($xpath->evaluate('string(w:pPr/w:spacing/@w:line)', $styleNode));
                $alignment   = trim((string)$xpath->evaluate('string(w:pPr/w:jc/@w:val)', $styleNode));

                if ($left !== null) {
                    $indentation['left'] = $left;
                }
                if ($right !== null) {
                    $indentation['right'] = $right;
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
                if ($spaceBefore !== null) {
                    $styleDefinition['spaceBefore'] = $spaceBefore;
                }
                if ($spaceAfter !== null) {
                    $styleDefinition['spaceAfter'] = $spaceAfter;
                }
                if ($line !== null && $line > 0) {
                    $styleDefinition['lineHeight'] = round($line / 240, 2);
                }
                if ($alignment !== '') {
                    $styleDefinition['alignment'] = $alignment;
                }

                Style::addParagraphStyle($styleId, $styleDefinition);
            }

            $this->registerTableStyles($xpath);
        } finally {
            $zip->close();
        }
    }

    private function registerTableStyles(DOMXPath $xpath): void
    {
        $styles = $xpath->query('//w:style[@w:type="table"]');
        if ($styles === false) {
            return;
        }

        foreach ($styles as $styleNode) {
            $styleId = trim((string)$xpath->evaluate('string(@w:styleId)', $styleNode));
            if ($styleId === '' || Style::getStyle($styleId) !== null) {
                continue;
            }

            $styleDefinition = $this->extractTableStyleDefinition($xpath, $styleNode);
            if ($styleDefinition === []) {
                continue;
            }

            Style::addTableStyle($styleId, $styleDefinition);
        }
    }

    private function extractTableStyleDefinition(DOMXPath $xpath, \DOMNode $styleNode): array
    {
        $mapping = [
            'top' => 'Top',
            'right' => 'Right',
            'bottom' => 'Bottom',
            'left' => 'Left',
        ];

        $definition = [];
        $alignment = trim((string)$xpath->evaluate('string(w:tblPr/w:jc/@w:val)', $styleNode));
        if ($alignment !== '') {
            $definition['alignment'] = $alignment;
        }
        // NOTE: Table indent is handled via indentLeft in AST's resolvedLayout.
        // Skipping direct float assignment to avoid PhpWord TblWidth type mismatch.
        // The value is captured in WordToAstConverter::extractTableLayout() instead.
        $cellSpacing = $this->readTwips($xpath->evaluate('string(w:tblPr/w:tblCellSpacing/@w:w)', $styleNode));
        if ($cellSpacing !== null) {
            $definition['cellSpacing'] = $cellSpacing;
        }

        $cellMarginTop = $this->readTwips($xpath->evaluate('string(w:tblPr/w:tblCellMar/w:top/@w:w)', $styleNode));
        $cellMarginRight = $this->readTwips($xpath->evaluate('string(w:tblPr/w:tblCellMar/w:right/@w:w)', $styleNode));
        $cellMarginBottom = $this->readTwips($xpath->evaluate('string(w:tblPr/w:tblCellMar/w:bottom/@w:w)', $styleNode));
        $cellMarginLeft = $this->readTwips($xpath->evaluate('string(w:tblPr/w:tblCellMar/w:left/@w:w)', $styleNode));
        if ($cellMarginTop !== null) {
            $definition['cellMarginTop'] = $cellMarginTop;
        }
        if ($cellMarginRight !== null) {
            $definition['cellMarginRight'] = $cellMarginRight;
        }
        if ($cellMarginBottom !== null) {
            $definition['cellMarginBottom'] = $cellMarginBottom;
        }
        if ($cellMarginLeft !== null) {
            $definition['cellMarginLeft'] = $cellMarginLeft;
        }

        foreach ($mapping as $wordSide => $suffix) {
            $border = $this->extractBorderNodeAttributes($xpath, sprintf('w:tblPr/w:tblBorders/w:%s', $wordSide), $styleNode);
            if ($border === null) {
                continue;
            }

            if ($border['size'] !== null) {
                $definition['border' . $suffix . 'Size'] = $border['size'];
            }
            if ($border['color'] !== null) {
                $definition['border' . $suffix . 'Color'] = $border['color'];
            }
            if ($border['style'] !== null) {
                $definition['border' . $suffix . 'Style'] = $border['style'];
            }
        }

        $insideH = $this->extractBorderNodeAttributes($xpath, 'w:tblPr/w:tblBorders/w:insideH', $styleNode);
        if ($insideH !== null) {
            if ($insideH['size'] !== null) {
                $definition['borderInsideHSize'] = $insideH['size'];
            }
            if ($insideH['color'] !== null) {
                $definition['borderInsideHColor'] = $insideH['color'];
            }
        }

        $insideV = $this->extractBorderNodeAttributes($xpath, 'w:tblPr/w:tblBorders/w:insideV', $styleNode);
        if ($insideV !== null) {
            if ($insideV['size'] !== null) {
                $definition['borderInsideVSize'] = $insideV['size'];
            }
            if ($insideV['color'] !== null) {
                $definition['borderInsideVColor'] = $insideV['color'];
            }
        }

        return $definition;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractStylesSnapshot(DOMXPath $xpath): array
    {
        $styles = [
            'paragraph' => [],
            'table' => [],
            'defaults' => [
                'paragraph' => [],
            ],
        ];

        $defaultsNode = $xpath->query('/w:styles/w:docDefaults')?->item(0);
        if ($defaultsNode instanceof \DOMNode) {
            $styles['defaults']['paragraph'] = [
                'alignment' => $this->readStringOrNull($xpath->evaluate('string(w:pPrDefault/w:pPr/w:jc/@w:val)', $defaultsNode)),
                'indentLeft' => $this->readTwips($xpath->evaluate('string(w:pPrDefault/w:pPr/w:ind/@w:left)', $defaultsNode)),
                'indentRight' => $this->readTwips($xpath->evaluate('string(w:pPrDefault/w:pPr/w:ind/@w:right)', $defaultsNode)),
                'indentFirstLine' => $this->readTwips($xpath->evaluate('string(w:pPrDefault/w:pPr/w:ind/@w:firstLine)', $defaultsNode)),
                'indentHanging' => $this->readTwips($xpath->evaluate('string(w:pPrDefault/w:pPr/w:ind/@w:hanging)', $defaultsNode)),
                'spacingBefore' => $this->readTwips($xpath->evaluate('string(w:pPrDefault/w:pPr/w:spacing/@w:before)', $defaultsNode)),
                'spacingAfter' => $this->readTwips($xpath->evaluate('string(w:pPrDefault/w:pPr/w:spacing/@w:after)', $defaultsNode)),
                'line' => $this->readTwips($xpath->evaluate('string(w:pPrDefault/w:pPr/w:spacing/@w:line)', $defaultsNode)),
            ];
        }

        $paragraphStyles = $xpath->query('//w:style[@w:type="paragraph"]');
        if ($paragraphStyles !== false) {
            foreach ($paragraphStyles as $styleNode) {
                $styleId = trim((string)$xpath->evaluate('string(@w:styleId)', $styleNode));
                if ($styleId === '') {
                    continue;
                }

                $styles['paragraph'][$styleId] = [
                    'styleId' => $styleId,
                    'styleName' => $this->readStringOrNull($xpath->evaluate('string(w:name/@w:val)', $styleNode)),
                    'basedOn' => $this->readStringOrNull($xpath->evaluate('string(w:basedOn/@w:val)', $styleNode)),
                    'alignment' => $this->readStringOrNull($xpath->evaluate('string(w:pPr/w:jc/@w:val)', $styleNode)),
                    'indentLeft' => $this->readTwips($xpath->evaluate('string(w:pPr/w:ind/@w:left)', $styleNode)),
                    'indentRight' => $this->readTwips($xpath->evaluate('string(w:pPr/w:ind/@w:right)', $styleNode)),
                    'indentFirstLine' => $this->readTwips($xpath->evaluate('string(w:pPr/w:ind/@w:firstLine)', $styleNode)),
                    'indentHanging' => $this->readTwips($xpath->evaluate('string(w:pPr/w:ind/@w:hanging)', $styleNode)),
                    'spacingBefore' => $this->readTwips($xpath->evaluate('string(w:pPr/w:spacing/@w:before)', $styleNode)),
                    'spacingAfter' => $this->readTwips($xpath->evaluate('string(w:pPr/w:spacing/@w:after)', $styleNode)),
                    'line' => $this->readTwips($xpath->evaluate('string(w:pPr/w:spacing/@w:line)', $styleNode)),
                ];
            }
        }

        $tableStyles = $xpath->query('//w:style[@w:type="table"]');
        if ($tableStyles !== false) {
            foreach ($tableStyles as $styleNode) {
                $styleId = trim((string)$xpath->evaluate('string(@w:styleId)', $styleNode));
                if ($styleId === '') {
                    continue;
                }

                $styles['table'][$styleId] = [
                    'styleId' => $styleId,
                    'styleName' => $this->readStringOrNull($xpath->evaluate('string(w:name/@w:val)', $styleNode)),
                    'basedOn' => $this->readStringOrNull($xpath->evaluate('string(w:basedOn/@w:val)', $styleNode)),
                    'alignment' => $this->readStringOrNull($xpath->evaluate('string(w:tblPr/w:jc/@w:val)', $styleNode)),
                    'indent' => $this->readTwips($xpath->evaluate('string(w:tblPr/w:tblInd/@w:w)', $styleNode)),
                    'cellSpacing' => $this->readTwips($xpath->evaluate('string(w:tblPr/w:tblCellSpacing/@w:w)', $styleNode)),
                    'layout' => $this->readStringOrNull($xpath->evaluate('string(w:tblPr/w:tblLayout/@w:type)', $styleNode)),
                    'cellMargins' => [
                        'top' => $this->readTwips($xpath->evaluate('string(w:tblPr/w:tblCellMar/w:top/@w:w)', $styleNode)),
                        'right' => $this->readTwips($xpath->evaluate('string(w:tblPr/w:tblCellMar/w:right/@w:w)', $styleNode)),
                        'bottom' => $this->readTwips($xpath->evaluate('string(w:tblPr/w:tblCellMar/w:bottom/@w:w)', $styleNode)),
                        'left' => $this->readTwips($xpath->evaluate('string(w:tblPr/w:tblCellMar/w:left/@w:w)', $styleNode)),
                    ],
                ];
            }
        }

        return $styles;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractNumberingSnapshot(DOMXPath $xpath): array
    {
        $numbering = [
            'numMap' => [],
            'levels' => [],
        ];

        $numNodes = $xpath->query('/w:numbering/w:num');
        if ($numNodes !== false) {
            foreach ($numNodes as $numNode) {
                $numId = $this->readIntOrNull($xpath->evaluate('string(@w:numId)', $numNode));
                if ($numId === null) {
                    continue;
                }

                $numbering['numMap'][(string)$numId] = [
                    'numId' => $numId,
                    'abstractNumId' => $this->readIntOrNull($xpath->evaluate('string(w:abstractNumId/@w:val)', $numNode)),
                    'levelOverrides' => $this->extractLevelOverrides($xpath, $numNode),
                ];
            }
        }

        $abstractNodes = $xpath->query('/w:numbering/w:abstractNum');
        if ($abstractNodes !== false) {
            foreach ($abstractNodes as $abstractNode) {
                $abstractNumId = $this->readIntOrNull($xpath->evaluate('string(@w:abstractNumId)', $abstractNode));
                if ($abstractNumId === null) {
                    continue;
                }

                $levels = [];
                $levelNodes = $xpath->query('w:lvl', $abstractNode);
                if ($levelNodes !== false) {
                    foreach ($levelNodes as $levelNode) {
                        $level = $this->readIntOrNull($xpath->evaluate('string(@w:ilvl)', $levelNode));
                        if ($level === null) {
                            continue;
                        }

                        $levels[(string)$level] = $this->extractNumberingLevelSnapshot($xpath, $levelNode, $level);
                    }
                }

                $numbering['levels'][(string)$abstractNumId] = $levels;
            }
        }

        return $numbering;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function extractLevelOverrides(DOMXPath $xpath, \DOMNode $numNode): array
    {
        $overrides = [];
        $overrideNodes = $xpath->query('w:lvlOverride', $numNode);

        if ($overrideNodes === false) {
            return $overrides;
        }

        foreach ($overrideNodes as $overrideNode) {
            $level = $this->readIntOrNull($xpath->evaluate('string(@w:ilvl)', $overrideNode));
            if ($level === null) {
                continue;
            }

            $startOverride = $this->readIntOrNull($xpath->evaluate('string(w:startOverride/@w:val)', $overrideNode));
            $levelNode = $xpath->query('w:lvl', $overrideNode)?->item(0);

            if ($levelNode instanceof \DOMElement) {
                $overrides[(string)$level] = $this->extractNumberingLevelSnapshot(
                    $xpath,
                    $levelNode,
                    $level,
                    $startOverride
                );
                continue;
            }

            $overrides[(string)$level] = ['start' => $startOverride];
        }

        return $overrides;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractNumberingLevelSnapshot(
        DOMXPath $xpath,
        \DOMElement $levelNode,
        int $level,
        ?int $startOverride = null
    ): array {
        $start = $this->readIntOrNull($xpath->evaluate('string(w:start/@w:val)', $levelNode));
        $rawNumFmt = $this->readStringOrNull($xpath->evaluate('string(w:numFmt/@w:val)', $levelNode));
        $lvlText = $this->readRawStringOrNull($xpath->evaluate('string(w:lvlText/@w:val)', $levelNode));
        $lvlSuffix = $this->readStringOrNull($xpath->evaluate('string(w:suff/@w:val)', $levelNode));
        $lvlJc = $this->readStringOrNull($xpath->evaluate('string(w:lvlJc/@w:val)', $levelNode));

        return [
            'level' => $level,
            'start' => $startOverride ?? $start,
            'format' => $rawNumFmt,
            'text' => $lvlText,
            'suffix' => $lvlSuffix,
            'alignment' => $lvlJc,
            'rawNumFmt' => $rawNumFmt,
            'lvlText' => $lvlText,
            'lvlSuffix' => $lvlSuffix,
            'lvlJc' => $lvlJc,
            'font' => $this->extractMarkerFontSnapshot($xpath, $levelNode),
            'restart' => $this->readIntOrNull($xpath->evaluate('string(w:lvlRestart/@w:val)', $levelNode)),
            'left' => $this->readTwips($xpath->evaluate('string(w:pPr/w:ind/@w:left)', $levelNode)),
            'hanging' => $this->readTwips($xpath->evaluate('string(w:pPr/w:ind/@w:hanging)', $levelNode)),
            'tabStop' => $this->readTwips($xpath->evaluate('string(w:pPr/w:tabs/w:tab/@w:pos)', $levelNode)),
        ];
    }

    /**
     * @return array<string, string>|null
     */
    private function extractMarkerFontSnapshot(DOMXPath $xpath, \DOMElement $levelNode): ?array
    {
        $font = [
            'ascii' => $this->readMarkerFontAttribute($xpath, $levelNode, 'ascii'),
            'hAnsi' => $this->readMarkerFontAttribute($xpath, $levelNode, 'hAnsi'),
            'eastAsia' => $this->readMarkerFontAttribute($xpath, $levelNode, 'eastAsia'),
            'cs' => $this->readMarkerFontAttribute($xpath, $levelNode, 'cs'),
            'hint' => $this->readMarkerFontAttribute($xpath, $levelNode, 'hint'),
        ];

        $font = array_filter(
            $font,
            static fn (?string $value): bool => $value !== null
        );

        return $font === [] ? null : $font;
    }

    private function readMarkerFontAttribute(DOMXPath $xpath, \DOMElement $levelNode, string $attributeName): ?string
    {
        $valueWithNamespace = $this->readStringOrNull(
            $xpath->evaluate('string(w:rPr/w:rFonts/@w:' . $attributeName . ')', $levelNode)
        );
        if ($valueWithNamespace !== null) {
            return $valueWithNamespace;
        }

        return $this->readStringOrNull(
            $xpath->evaluate('string(w:rPr/w:rFonts/@' . $attributeName . ')', $levelNode)
        );
    }

    private function readIntOrNull(mixed $rawValue): ?int
    {
        if (!is_string($rawValue) || $rawValue === '' || !is_numeric($rawValue)) {
            return null;
        }

        return (int)$rawValue;
    }

    private function readStringOrNull(mixed $rawValue): ?string
    {
        if (!is_string($rawValue)) {
            return null;
        }

        $value = trim($rawValue);
        return $value === '' ? null : $value;
    }

    private function readRawStringOrNull(mixed $rawValue): ?string
    {
        if (!is_string($rawValue) || $rawValue === '') {
            return null;
        }

        return $rawValue;
    }

    private function extractBorderNodeAttributes(DOMXPath $xpath, string $query, \DOMNode $contextNode): ?array
    {
        $borderNode = $xpath->query($query, $contextNode)?->item(0);
        if (!$borderNode instanceof \DOMElement) {
            return null;
        }

        $size = $this->readBorderSize($borderNode->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'sz'));
        $color = $this->readBorderColor($borderNode->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'color'));
        $style = $this->readBorderStyle($borderNode->getAttributeNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'val'));

        return [
            'size' => $size,
            'color' => $color,
            'style' => $style,
        ];
    }

    private function readBorderSize(string $rawValue): ?int
    {
        if ($rawValue === '' || !is_numeric($rawValue)) {
            return null;
        }

        return (int)$rawValue;
    }

    private function readBorderColor(string $rawValue): ?string
    {
        $normalized = trim($rawValue);
        if ($normalized === '' || strcasecmp($normalized, 'auto') === 0) {
            return null;
        }

        return strtoupper(ltrim($normalized, '#'));
    }

    private function readBorderStyle(string $rawValue): ?string
    {
        $normalized = trim($rawValue);
        return $normalized === '' ? null : $normalized;
    }

    private function readTwips(mixed $rawValue): ?float
    {
        if (!is_string($rawValue) || $rawValue === '' || !is_numeric($rawValue)) {
            return null;
        }

        return (float)$rawValue;
    }
}
