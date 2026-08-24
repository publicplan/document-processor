<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use DOMDocument;
use LibXMLError;
use Exception;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\ListItemRun as DocList;
use PhpOffice\PhpWord\Element\TextBreak as DocBreak;
use PhpOffice\PhpWord\Element\TextRun as DocTextRun;
use PhpOffice\PhpWord\PhpWord;
use Publicplan\DocumentProcessor\Enum\ControlCharacter;
use Publicplan\DocumentProcessor\Exception\DocumentLoadException;
use Publicplan\DocumentProcessor\Exception\DocumentProcessorException;
use Publicplan\DocumentProcessor\Model\ConversionContext;
use Publicplan\DocumentProcessor\Model\ListConfig;
use Publicplan\DocumentProcessor\Model\ParserError;
use Publicplan\DocumentProcessor\Model\ProcessingOptions;
use Publicplan\DocumentProcessor\Model\ProcessedDocument;
use Publicplan\DocumentProcessor\Service\Converter\BorderStyleHelper;
use Publicplan\DocumentProcessor\Service\Converter\DeletedContentHelper;
use Publicplan\DocumentProcessor\Service\Converter\ElementConverterRegistry;
use Publicplan\DocumentProcessor\Service\Converter\ListElementConverter;

/**
 * Haupt-Facade für die Dokumentenverarbeitung.
 * Orchestriert Loader, Converter und Validierung.
 */
class DocumentProcessor
{
    private ElementConverterRegistry $converterRegistry;
    private int|string|null $lastListNumId = null;

    public function __construct(
        private readonly DocumentLoader $documentLoader
    )
    {
        $this->converterRegistry = new ElementConverterRegistry();
        $this->converterRegistry->registerDefaultConverters();
    }

    /**
     * Verarbeitet ein Word-Dokument vollständig.
     *
     * @param string $filePath       Absoluter Pfad zur .docx Datei
     * @param string $sourceFilename Ursprünglicher Dateiname für Referenz
     * @param ProcessingOptions|null $processingOptions Optionen für die HTML-Verarbeitung
     *
     * @return ProcessedDocument Das verarbeitete Dokument
     * @throws DocumentProcessorException Wenn ein unerwarteter Fehler auftritt
     */
    public function process(
        string $filePath,
        string $sourceFilename = '',
        ?ProcessingOptions $processingOptions = null
    ): ProcessedDocument
    {
        try {
            $processingOptions ??= new ProcessingOptions();
            $hasChanges      = false;
            $defaultFontSize = null;
            $result          = $this->documentLoader->loadWithDocumentMetadata($filePath, $hasChanges, $defaultFontSize);
            $context         = new ConversionContext();
            $context->setDefaultFontSize($defaultFontSize);
            $context->setRemoveDeletedContent($processingOptions->removeDeletedContent);

            $html                = $this->convertToHtml($result, $context);
            $html                = $this->postProcessHtml($html, $processingOptions->removeDeletedContent);
            $isHtmlFragmentValid = null;

            if ($processingOptions->validateHtml) {
                $isHtmlFragmentValid = $this->validateHtmlFragment($html, $context);
            }

            if ($hasChanges) {
                $context->addMessage(
                    ParserError::create(
                        ParserError::CONTAINS_UNACCEPTED_CHANGES,
                        ParserError::SEVERITY_ERROR,
                        'Das Dokument enthält nicht übernommene Änderungen (Änderungsverfolgung).'
                    ),
                    true
                );
            }

            return new ProcessedDocument(
                html: $html,
                lastModified: $this->extractLastModified($result),
                hasUnacceptedChanges: $hasChanges,
                messages: $context->getMessages(),
                sourceFilename: $sourceFilename ?: basename($filePath),
                isHtmlFragmentValid: $isHtmlFragmentValid
            );
        } catch (DocumentLoadException $e) {
            // Ladefehler weitergeben
            throw $e;
        } catch (Exception $e) {
            // Unerwartete Fehler
            throw new DocumentProcessorException(
                'Fehler bei der Dokumentverarbeitung: ' . $e->getMessage(),
                $filePath,
                0,
                $e
            );
        }
    }

    /**
     * Konvertiert ein PhpWord-Dokument in HTML.
     */
    private function convertToHtml(PhpWord $doc, ConversionContext $context): string
    {
        $text                = '';
        $openListConfig      = null;   // Trackt die aktuell geöffnete Liste
        $listContinuationMap = [];     // Trackt die nächste Startnummer je logischer Liste
        $openBorderSignature = null;   // Trackt die aktuelle Border-Gruppe
        $openBorderStyle     = '';     // CSS-Styles für die aktuelle Border-Gruppe
        $lastTextRun         = null;   // Merkt sich den letzten TextRun für aufeinanderfolgende TextBreaks

        foreach ($doc->getSections() as $section) {
            $elements = $section->getElements();

            // Nutze for-Schleife statt foreach, damit wir den Index manuell erhöhen können (für Spacer-Skipping)
            for ($i = 0; $i < count($elements); $i++) {
                $element = $elements[$i];

                // Spezialbehandlung für TextBreak (könnte leerer Absatz sein)
                if ($element instanceof DocBreak) {
                    $wasInsideList = $openListConfig !== null;
                    $this->closeOpenList($openListConfig, $text);

                    // Am Anfang des Dokuments -> <br>
                    if ($lastTextRun === null) {
                        if ($wasInsideList) {
                            $text .= '<p style="margin-bottom: 0cm;">&nbsp;</p>' . PHP_EOL;
                        } else {
                            $text .= '<br>' . PHP_EOL;
                        }
                        continue;
                    }

                    // Margin-bottom vom letzten TextRun holen
                    $marginStyle = $this->getMarginBottomFromElement($lastTextRun);
                    $styleAttr   = $marginStyle ? sprintf(' style="%s"', $marginStyle) : '';

                    // Border-Gruppen-Handling
                    $nextElement = $elements[$i + 1] ?? null;
                    if ($openBorderSignature !== null) {
                        // Bereits in Gruppe -> prüfe ob fortgesetzt werden soll
                        $lastBorders = $this->getBorderSignature($lastTextRun);

                        if (!$nextElement instanceof DocTextRun || $lastBorders === null || $lastBorders !== $this->getBorderSignature($nextElement)) {
                            // Nächstes Element hat keine/andere Borders -> Gruppe schließen
                            $text                .= '</div>' . PHP_EOL;
                            $openBorderSignature = null;
                        }
                    } else {
                        // Außerhalb -> prüfe ob Gruppe geöffnet werden muss
                        $prevBorders = $this->getBorderSignature($lastTextRun);

                        if ($nextElement instanceof DocTextRun
                            && $prevBorders !== null
                            && $prevBorders === $this->getBorderSignature($nextElement)) {
                            // Gruppe öffnen
                            $openBorderStyle     = $this->buildBorderStyle($lastTextRun);
                            $text                .= sprintf('<div style="%s">', $openBorderStyle) . PHP_EOL;
                            $openBorderSignature = $prevBorders;
                        }

                        // <p> ausgeben
                    }
                    $text .= sprintf('<p%s>&nbsp;</p>', $styleAttr) . PHP_EOL;
                    continue;
                }

                // Wenn es ein TextRun ist, merken wir es uns für mögliche folgende TextBreaks
                // Bei anderen Elementen (außer DocBreak) wird es zurückgesetzt
                if ($element instanceof DocTextRun) {
                    $lastTextRun = $element;
                } elseif (!$element instanceof DocBreak) {
                    $lastTextRun = null;
                }

                // Listen-Handling
                if ($element instanceof DocList) {
                    // Border-Gruppe schließen wenn nötig
                    if ($openBorderSignature !== null) {
                        $text                .= '</div>' . PHP_EOL;
                        $openBorderSignature = null;
                        $openBorderStyle     = '';
                    }

                    $html           = $this->handleListElement($element, $context, $openListConfig, $text, $listContinuationMap, $elements, $i);
                    $openListConfig = $html['listConfig'];
                    $text           .= $html['content'];
                } else {
                    // Nicht-Listen-Element: Schließe offene Liste, falls vorhanden
                    $this->closeOpenList($openListConfig, $text);

                    // Border-Gruppen-Handling für TextRun-Elemente
                    if ($element instanceof DocTextRun) {
                        // Prüfe ob dies ein Spacer-Absatz ist (leerer Absatz zwischen Listenpunkten gleicher numId)
                        if ($element->isEmpty() && $this->isSpacerParagraph($element, $elements, $i)) {
                            // Spacer wird übersprungen - sein Spacing wurde bereits auf den vorherigen <li> übertragen
                            continue;
                        }

                        $borderSignature = $this->getBorderSignature($element);

                        if ($borderSignature !== null) {
                            // Element hat Borders
                            if ($openBorderSignature === null) {
                                // Neue Border-Gruppe öffnen
                                $openBorderStyle     = $this->buildBorderStyle($element);
                                $text                .= sprintf('<div style="%s">', $openBorderStyle) . PHP_EOL;
                                $openBorderSignature = $borderSignature;
                            } elseif ($openBorderSignature !== $borderSignature) {
                                // Verschiedene Borders: Alte Gruppe schließen, neue öffnen
                                $text                .= '</div>' . PHP_EOL;
                                $openBorderStyle     = $this->buildBorderStyle($element);
                                $text                .= sprintf('<div style="%s">', $openBorderStyle) . PHP_EOL;
                                $openBorderSignature = $borderSignature;
                            }
                            // Sonst: Selbe Border-Gruppe, nichts zu tun

                            // Element OHNE Border-Styles konvertieren (da vom Container)
                            $elementHtml = $this->converterRegistry->convert($element, $context);
                            if ($elementHtml !== null) {
                                // Entferne Border-Styles aus dem HTML (wir haben sie im Container)
                                $elementHtml = $this->removeBorderStyles($elementHtml);
                                $text        .= $elementHtml;
                            }
                        } else {
                            // Keine Borders: Ggf. Border-Gruppe schließen
                            if ($openBorderSignature !== null) {
                                $text                .= '</div>' . PHP_EOL;
                                $openBorderSignature = null;
                                $openBorderStyle     = '';
                            }

                            // Normal konvertieren
                            $elementHtml = $this->converterRegistry->convert($element, $context);
                            if ($elementHtml !== null) {
                                $text .= $elementHtml;
                            } else {
                                $this->handleUnknownElement($element, $context);
                            }
                        }
                    } else {
                        // Andere Elemente: Border-Gruppe schließen wenn nötig
                        if ($openBorderSignature !== null) {
                            $text                .= '</div>' . PHP_EOL;
                            $openBorderSignature = null;
                            $openBorderStyle     = '';
                        }

                        // Element konvertieren
                        $elementHtml = $this->converterRegistry->convert($element, $context);
                        if ($elementHtml !== null) {
                            $text .= $elementHtml;
                        } else {
                            $this->handleUnknownElement($element, $context);
                        }
                    }
                }
            }
        }

        // Am Ende: Schließe noch offene Listen und Border-Gruppen
        if ($openListConfig !== null) {
            $text .= $openListConfig->renderEndTag() . PHP_EOL;
        }
        if ($openBorderSignature !== null) {
            $text .= '</div>' . PHP_EOL;
        }

        return $text;
    }

    /**
     * Erstellt eine eindeutige Signatur für die Border-Styles eines TextRun-Elements.
     * Gibt null zurück wenn keine Borders gesetzt sind.
     */
    private function getBorderSignature(DocTextRun $element): ?string
    {
        $pStyle = $element->getParagraphStyle();

        $borders = [
            'top'    => [
                'size'  => $pStyle->getBorderTopSize(),
                'color' => $pStyle->getBorderTopColor(),
                'style' => $pStyle->getBorderTopStyle(),
            ],
            'left'   => [
                'size'  => $pStyle->getBorderLeftSize(),
                'color' => $pStyle->getBorderLeftColor(),
                'style' => $pStyle->getBorderLeftStyle(),
            ],
            'right'  => [
                'size'  => $pStyle->getBorderRightSize(),
                'color' => $pStyle->getBorderRightColor(),
                'style' => $pStyle->getBorderRightStyle(),
            ],
            'bottom' => [
                'size'  => $pStyle->getBorderBottomSize(),
                'color' => $pStyle->getBorderBottomColor(),
                'style' => $pStyle->getBorderBottomStyle(),
            ],
        ];

        // Prüfe ob überhaupt Borders gesetzt sind
        $hasBorders = false;
        foreach ($borders as $border) {
            if ($border['size'] !== null && $border['size'] !== '') {
                $hasBorders = true;
                break;
            }
        }

        if (!$hasBorders) {
            return null;
        }

        return md5(serialize($borders));
    }

    /**
     * Baut die CSS Border-Styles für ein Element.
     */
    private function buildBorderStyle(DocTextRun $element): string
    {
        $pStyle = $element->getParagraphStyle();
        $styles = [];

        $borders = [
            'top'    => [
                'size'  => $pStyle->getBorderTopSize(),
                'color' => $pStyle->getBorderTopColor(),
                'style' => $pStyle->getBorderTopStyle(),
            ],
            'left'   => [
                'size'  => $pStyle->getBorderLeftSize(),
                'color' => $pStyle->getBorderLeftColor(),
                'style' => $pStyle->getBorderLeftStyle(),
            ],
            'right'  => [
                'size'  => $pStyle->getBorderRightSize(),
                'color' => $pStyle->getBorderRightColor(),
                'style' => $pStyle->getBorderRightStyle(),
            ],
            'bottom' => [
                'size'  => $pStyle->getBorderBottomSize(),
                'color' => $pStyle->getBorderBottomColor(),
                'style' => $pStyle->getBorderBottomStyle(),
            ],
        ];

        // Prüfe ob alle Borders identisch sind
        $first        = $borders['top'];
        $allIdentical = true;
        foreach (['left', 'right', 'bottom'] as $side) {
            if ($borders[$side]['size'] !== $first['size'] ||
                $borders[$side]['color'] !== $first['color'] ||
                $borders[$side]['style'] !== $first['style']) {
                $allIdentical = false;
                break;
            }
        }

        // Mapping Word-Styles zu CSS
        $styleMapping = [
            'single' => 'solid',
            'double' => 'double',
            'dotted' => 'dotted',
            'dashed' => 'dashed',
            'none'   => 'none',
        ];

        if ($allIdentical && $first['size'] !== null && $first['size'] !== '') {
            // Einheitlicher Border
            $width    = BorderStyleHelper::normalizeBorderWidthCm($this->twipsToCm($first['size']));
            $style    = $styleMapping[$first['style']] ?? 'solid';
            $color    = BorderStyleHelper::formatCssHexColor($first['color']);
            $styles[] = sprintf('border: %scm %s%s;', $width, $style, $color !== null ? ' ' . $color : '');
        } else {
            // Individuelle Borders
            foreach ($borders as $side => $border) {
                if ($border['size'] !== null && $border['size'] !== '') {
                    $width    = BorderStyleHelper::normalizeBorderWidthCm($this->twipsToCm($border['size']));
                    $style    = $styleMapping[$border['style']] ?? 'solid';
                    $color    = BorderStyleHelper::formatCssHexColor($border['color']);
                    $styles[] = sprintf(
                        'border-%s: %scm %s%s;',
                        $side,
                        $width,
                        $style,
                        $color !== null ? ' ' . $color : ''
                    );
                }
            }
        }

        // Padding innerhalb des Borders
        $styles[] = 'padding: 0.2cm;';

        return implode(' ', $styles);
    }

    /**
     * Entfernt Border-Styles aus HTML (da sie vom Container kommen).
     */
    private function removeBorderStyles(string $html): string
    {
        // Entferne border:* Styles
        $html = preg_replace('/\s*border:\s*[^;]+;/', '', $html);
        $html = preg_replace('/\s*border-top:\s*[^;]+;/', '', $html);
        $html = preg_replace('/\s*border-left:\s*[^;]+;/', '', $html);
        $html = preg_replace('/\s*border-right:\s*[^;]+;/', '', $html);
        $html = preg_replace('/\s*border-bottom:\s*[^;]+;/', '', $html);

        // Entferne padding:* Styles (da wir es im Container haben)
        return preg_replace('/\s*padding:\s*[^;]+;/', '', $html);
    }

    /**
     * Behandelt ein Listen-Element (öffnet neue Liste oder fügt zu bestehender hinzu).
     * Der bottomSpacing wird auf jedes <li> Element angewendet.
     */
    private function handleListElement(
        DocList           $element,
        ConversionContext $context,
        ?ListConfig       $openListConfig,
        string            &$accumulatedText,
        array             &$listContinuationMap,
        array             $elements,
        int               &$currentIndex
    ): array
    {
        $listConverter = $this->converterRegistry->findConverter($element);

        if (!$listConverter instanceof ListElementConverter) {
            return ['listConfig' => $openListConfig, 'content' => ''];
        }

        // Speichere die numId dieses Listenpunktes für Spacer-Tracking
        $this->setLastListNumId($element->getStyle()?->getNumId());

        // Bottom spacing aus dem aktuellen Listenelement holen (in cm)
        $spaceAfter      = $element->getParagraphStyle()?->getSpaceAfter();
        $bottomSpacingCm = $spaceAfter ? $this->twipsToCm($spaceAfter) : 0.0;

        // Sammle Spacing von nachfolgenden Spacer-Absätzen
        $nextIndex = $currentIndex + 1;
        $spacerSpacingCm = $this->accumulateSpacingCm($elements, $nextIndex);

        // Erhöhe den Index, damit die Spacer übersprungen werden
        $currentIndex = $nextIndex - 1;

        $listConfig = $listConverter->createListConfig($element); // Liste selbst hat keinen bottom spacing
        $html       = '';
        $startValue = $this->resolveListStartValue($listConfig, $listContinuationMap);

        // Prüfe, ob wir eine neue Liste öffnen müssen
        if ($openListConfig === null) {
            // Neue Liste öffnen
            $html .= $listConfig->renderStartTag($startValue) . PHP_EOL;
        } elseif (!$openListConfig->isSameList($listConfig)) {
            // Verschiedener Listentyp: Alte schließen, neue öffnen
            $accumulatedText .= $openListConfig->renderEndTag() . PHP_EOL;
            $html            .= $listConfig->renderStartTag($startValue) . PHP_EOL;
        }
        // Sonst: Liste ist bereits offen, füge nur <li> hinzu

        // Listen-Item mit bottom spacing + Spacer-Spacing hinzufügen
        $html .= $listConverter->convertWithSpacerSpacing($element, $context, $bottomSpacingCm, $spacerSpacingCm);
        $this->advanceListContinuation($listConfig, $listContinuationMap);

        return ['listConfig' => $listConfig, 'content' => $html];
    }

    /**
     * Ermittelt den Startwert für eine geordnete Liste auf Basis bereits gerenderter Elemente.
     */
    private function resolveListStartValue(ListConfig $listConfig, array $listContinuationMap): int
    {
        if (!$listConfig->isOrdered()) {
            return 1;
        }

        return $listContinuationMap[$listConfig->sequenceKey] ?? $listConfig->start;
    }

    /**
     * Erhöht den nächsten Startwert für eine geordnete Liste um ein Element.
     */
    private function advanceListContinuation(ListConfig $listConfig, array &$listContinuationMap): void
    {
        if (!$listConfig->isOrdered()) {
            return;
        }

        $currentStart = $listContinuationMap[$listConfig->sequenceKey] ?? $listConfig->start;
        $listContinuationMap[$listConfig->sequenceKey] = $currentStart + 1;
    }

    /**
     * Schließt eine offene Liste, falls vorhanden.
     */
    private function closeOpenList(?ListConfig &$openListConfig, string &$text): void
    {
        if ($openListConfig === null) {
            return;
        }

        $text .= $openListConfig->renderEndTag() . PHP_EOL;
        $openListConfig = null;
    }

    /**
     * Konvertiert Twips in Zentimeter.
     */
    private function twipsToCm(float|string|null $twips): float
    {
        if ($twips === null || $twips === '') {
            return 0.0;
        }
        return round((float)$twips / 1440 * 2.54, 2);
    }

    /**
     * Behandelt unbekannte Elemente.
     */
    private function handleUnknownElement(AbstractElement $element, ConversionContext $context): void
    {
        $context->addMessage(
            ParserError::create(
                ParserError::CONTAINS_UNHANDLED_ELEMENTS,
                ParserError::SEVERITY_ERROR,
                sprintf('Nicht unterstütztes Element %s', get_class($element))
            ),
            true
        );
    }

    /**
     * Prüft, ob ein TextBreak-Element ein leerer Absatz ist (statt manuellem Umbruch).
     *
     * Ein TextBreak ist ein leerer Absatz wenn:
     * - Wir in einer Border-Gruppe sind, ODER
     * - Vorheriges und nächstes Element sind TextRuns mit identischen Borders
     */
    private function isEmptyParagraphBreak(array $elements, int $currentIndex, ?string $openBorderSignature): bool
    {
        // Wenn wir in einer Border-Gruppe sind: immer leerer Absatz
        if ($openBorderSignature !== null) {
            return true;
        }

        $prevElement = $elements[$currentIndex - 1] ?? null;
        $nextElement = $elements[$currentIndex + 1] ?? null;

        // Beide müssen TextRuns sein
        if (!$prevElement instanceof DocTextRun || !$nextElement instanceof DocTextRun) {
            return false;
        }

        // Beide müssen identische Borders haben
        $prevBorders = $this->getBorderSignature($prevElement);
        $nextBorders = $this->getBorderSignature($nextElement);

        return $prevBorders !== null && $prevBorders === $nextBorders;
    }

    /**
     * Extrahiert das margin-bottom (spaceAfter) aus einem TextRun-Element.
     *
     * @param DocTextRun|null $element Das Element, aus dem der Abstand extrahiert werden soll
     *
     * @return string CSS margin-bottom Style (immer vorhanden, Default: 0)
     */
    private function getMarginBottomFromElement(?DocTextRun $element): string
    {
        if ($element === null) {
            return 'margin-bottom: 0cm;';
        }

        $spaceAfter = $element->getParagraphStyle()?->getSpaceAfter();
        if ((float)$spaceAfter === 0.0) {
            return 'margin-bottom: 0cm;';
        }

        return sprintf('margin-bottom: %scm;', $this->twipsToCm($spaceAfter));
    }

    /**
     * Post-Processing des HTML.
     */
    private function postProcessHtml(string $html, bool $removeDeletedContent): string
    {
        if ($removeDeletedContent) {
            $html = preg_replace(
                sprintf(
                    '/(<p.*>)?(%s)+(%s|%s)?(<br\h?\/>)?(<\/p>)?\v?/',
                    preg_quote(DeletedContentHelper::DELETED_MARKER, '/'),
                    preg_quote(ControlCharacter::BREAK->value, '/'),
                    preg_quote(ControlCharacter::PARAGRAPH->value, '/')
                ),
                '',
                $html
            ) ?? $html;
        }

        $html = (new HtmlInlineTagSimplifier())->simplify($html);

        // Füge Zeilenumbruch nach </p> ein
        return str_replace('</p>', '</p>' . PHP_EOL, $html);
    }

    /**
     * Prüft, ob das erzeugte HTML-Fragment ohne Parser-Fehler gelesen werden kann.
     */
    private function validateHtmlFragment(string $html, ConversionContext $context): bool
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $wrappedHtml = sprintf('<div>%s</div>', $html);
            $document->loadHTML($wrappedHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

            $errors = array_filter(
                libxml_get_errors(),
                static fn (LibXMLError $error): bool => $error->level >= LIBXML_ERR_WARNING
            );

            foreach ($errors as $error) {
                $context->addMessage(
                    ParserError::create(
                        ParserError::CONTAINS_INVALID_HTML,
                        ParserError::SEVERITY_WARNING,
                        $this->formatHtmlValidationMessage($error)
                    ),
                    true
                );
            }

            return $errors === [];
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function formatHtmlValidationMessage(LibXMLError $error): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $error->message) ?? $error->message);

        if ($error->line > 0 && $error->column > 0) {
            return sprintf(
                'Das erzeugte HTML-Fragment ist nicht parser-tauglich: %s (Zeile %d, Spalte %d).',
                $message,
                $error->line,
                $error->column
            );
        }

        return sprintf(
            'Das erzeugte HTML-Fragment ist nicht parser-tauglich: %s.',
            $message
        );
    }

    /**
     * Extrahiert das Änderungsdatum des Dokuments.
     */
    private function extractLastModified(PhpWord $doc): DateTimeInterface
    {
        $modified = $doc->getDocInfo()->getModified();
        $dateTime = DateTime::createFromFormat('U', (string)$modified);

        if ($dateTime === false) {
            throw new DocumentProcessorException(
                'Konnte Änderungsdatum nicht parsen',
                ''
            );
        }

        return $dateTime->setTimezone(new DateTimeZone('Europe/Berlin'));
    }

    /**
     * Prüft, ob ein Dokument nicht übernommene Änderungen hat.
     *
     * @param string $filePath Absoluter Pfad zur .docx Datei
     *
     * @return bool True, wenn offene Änderungen gefunden wurden
     * @throws DocumentLoadException Wenn die Datei nicht geöffnet werden kann
     */
    public function hasUnacceptedChanges(string $filePath): bool
    {
        return $this->documentLoader->hasUnacceptedChanges($filePath);
    }

    /**
     * Setzt die Nummerierungs-ID des letzten Listenpunktes (für Spacer-Tracking).
     */
    private function setLastListNumId(int|string|null $numId): void
    {
        $this->lastListNumId = $numId;
    }

    /**
     * Gibt die Nummerierungs-ID des letzten Listenpunktes zurück.
     */
    private function getLastListNumId(): int|string|null
    {
        return $this->lastListNumId;
    }

    /**
     * Prüft, ob ein leerer Absatz (TextBreak/DocBreak) ein Spacer-Absatz zwischen Listenpunkten ist.
     *
     * Ein Spacer ist ein leerer Absatz (DocBreak), der unmittelbar vor einem Listenpunkt mit
     * der gleichen numId wie der letzte Listenpunkt folgt (und damit nur für Abstände genutzt wird).
     *
     * @param object $element Das zu prüfende Element
     * @param array $elements Alle Elemente des Dokuments
     * @param int $currentIndex Index von $element in $elements
     *
     * @return bool True, wenn $element ein Spacer ist
     */
    private function isSpacerParagraph(object $element, array $elements, int $currentIndex): bool
    {
        // Element muss ein DocBreak sein (leerer Absatz)
        if (!$element instanceof DocBreak) {
            return false;
        }

        // Nächstes Element muss existieren und ein Listenpunkt sein
        $nextElement = $elements[$currentIndex + 1] ?? null;
        if (!$nextElement instanceof DocList) {
            return false;
        }

        // Der Listenpunkt muss die gleiche numId wie der letzte Listenpunkt haben
        $lastNumId = $this->getLastListNumId();
        $nextNumId = $nextElement->getStyle()?->getNumId();

        return $lastNumId !== null && $lastNumId === $nextNumId;
    }

    /**
     * Berechnet die Höhe eines Spacer-Absatzes (DocBreak) in cm.
     *
     * Nutzt zuerst explizites Spacing (spaceAfter), sonst Fallback von 0.42cm (1 Zeile).
     *
     * @param object $element Der Spacer-Absatz (DocBreak)
     *
     * @return float Höhe in cm (gerundet auf 2 Dezimalstellen)
     */
    private function calculateSpacerHeightCm(object $element): float
    {
        // Versuche explizites Spacing aus dem Absatz zu lesen
        if (method_exists($element, 'getParagraphStyle')) {
            $pStyle = $element->getParagraphStyle();
            $spaceAfter = $pStyle?->getSpaceAfter();

            if ($spaceAfter) {
                return $this->twipsToCm($spaceAfter);
            }
        }

        // Fallback: 1 Zeile ≈ 0.42cm (Standard-Zeilenhöhe in Word)
        return 0.42;
    }

    /**
     * Sammelt das Spacing von aufeinanderfolgenden Spacer-Absätzen.
     *
     * Prüft ab dem aktuellen Index alle folgenden Elemente. Wenn sie Spacer sind,
     * werden sie gezählt und ihr Spacing addiert. Der $currentIndex wird erhöht.
     *
     * @param array $elements Alle Elemente des Dokuments
     * @param int $currentIndex [OUT] Index wird auf das erste Nicht-Spacer-Element gesetzt
     *
     * @return float Kumuliertes Spacing aller Spacer in cm
     */
    private function accumulateSpacingCm(array $elements, int &$currentIndex): float
    {
        $totalSpacing = 0.0;
        $index = $currentIndex;

        while ($index < count($elements)) {
            $element = $elements[$index];

            // Prüfe ob Spacer (DocBreak zwischen Listenpunkten gleicher numId)
            if ($this->isSpacerParagraph($element, $elements, $index)) {
                $totalSpacing += $this->calculateSpacerHeightCm($element);
                $index++;
            } else {
                break;
            }
        }

        // Index erhöhen, damit aufrufender Code die Spacer überspringt
        $currentIndex = $index;

        return $totalSpacing;
    }
}
