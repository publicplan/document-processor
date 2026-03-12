<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
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
use Publicplan\DocumentProcessor\Model\ProcessedDocument;
use Publicplan\DocumentProcessor\Service\Converter\ElementConverterRegistry;
use Publicplan\DocumentProcessor\Service\Converter\ListElementConverter;

/**
 * Haupt-Facade für die Dokumentenverarbeitung.
 * Orchestriert Loader, Converter und Validierung.
 */
class DocumentProcessor
{
    private ElementConverterRegistry $converterRegistry;

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
     *
     * @return ProcessedDocument Das verarbeitete Dokument
     * @throws DocumentProcessorException Wenn ein unerwarteter Fehler auftritt
     */
    public function process(string $filePath, string $sourceFilename = ''): ProcessedDocument
    {
        try {
            $result     = $this->documentLoader->loadWithChangeCheck($filePath, $hasChanges);
            $hasChanges = $hasChanges ?? false;
            $context    = new ConversionContext();

            $html = $this->convertToHtml($result, $context);
            $html = $this->postProcessHtml($html);

            return new ProcessedDocument(
                html: $html,
                lastModified: $this->extractLastModified($result),
                hasUnacceptedChanges: $hasChanges,
                messages: $context->getMessages(),
                sourceFilename: $sourceFilename ?: basename($filePath)
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
        $openBorderSignature = null;   // Trackt die aktuelle Border-Gruppe
        $openBorderStyle     = '';     // CSS-Styles für die aktuelle Border-Gruppe
        $lastTextRun         = null;   // Merkt sich den letzten TextRun für aufeinanderfolgende TextBreaks

        foreach ($doc->getSections() as $section) {
            $elements = $section->getElements();

            foreach ($elements as $i => $iValue) {
                $element = $iValue;

                // Spezialbehandlung für TextBreak (könnte leerer Absatz sein)
                if ($element instanceof DocBreak) {
                    // Am Anfang des Dokuments -> <br>
                    if ($lastTextRun === null) {
                        $text .= '<br>' . PHP_EOL;
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
                    $text .= sprintf('<p%s>&#32;</p>', $styleAttr) . PHP_EOL;
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

                    $html           = $this->handleListElement($element, $context, $openListConfig, $text);
                    $openListConfig = $html['listConfig'];
                    $text           .= $html['content'];
                } else {
                    // Nicht-Listen-Element: Schließe offene Liste, falls vorhanden
                    if ($openListConfig !== null) {
                        $text           .= $openListConfig->renderEndTag() . PHP_EOL;
                        $openListConfig = null;
                    }

                    // Border-Gruppen-Handling für TextRun-Elemente
                    if ($element instanceof DocTextRun) {
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
            $width    = $this->twipsToCm($first['size']);
            $style    = $styleMapping[$first['style']] ?? 'solid';
            $color    = '#' . ($first['color'] ?? '000000');
            $styles[] = sprintf('border: %scm %s %s;', $width, $style, $color);
        } else {
            // Individuelle Borders
            foreach ($borders as $side => $border) {
                if ($border['size'] !== null && $border['size'] !== '') {
                    $width    = $this->twipsToCm($border['size']);
                    $style    = $styleMapping[$border['style']] ?? 'solid';
                    $color    = '#' . ($border['color'] ?? '000000');
                    $styles[] = sprintf('border-%s: %scm %s %s;', $side, $width, $style, $color);
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
        string            &$accumulatedText
    ): array
    {
        $listConverter = $this->converterRegistry->findConverter($element);

        if (!$listConverter instanceof ListElementConverter) {
            return ['listConfig' => $openListConfig, 'content' => ''];
        }

        // Bottom spacing aus dem aktuellen Listenelement holen (in cm)
        $spaceAfter      = $element->getParagraphStyle()?->getSpaceAfter();
        $bottomSpacingCm = $spaceAfter ? $this->twipsToCm($spaceAfter) : 0.0;

        $listConfig = $listConverter->createListConfig($element); // Liste selbst hat keinen bottom spacing
        $html       = '';

        // Prüfe, ob wir eine neue Liste öffnen müssen
        if ($openListConfig === null) {
            // Neue Liste öffnen
            $html .= $listConfig->renderStartTag() . PHP_EOL;
        } elseif ($openListConfig->tag !== $listConfig->tag || $openListConfig->type !== $listConfig->type) {
            // Verschiedener Listentyp: Alte schließen, neue öffnen
            $accumulatedText .= $openListConfig->renderEndTag() . PHP_EOL;
            $html            .= $listConfig->renderStartTag() . PHP_EOL;
        }
        // Sonst: Liste ist bereits offen, füge nur <li> hinzu

        // Listen-Item mit bottom spacing hinzufügen
        $html .= $listConverter->convertWithSpacing($element, $context, $bottomSpacingCm);

        return ['listConfig' => $listConfig, 'content' => $html];
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
    private function postProcessHtml(string $html): string
    {
        // Entferne ##deleted## Marker mit Umgebung
        $html = preg_replace(
            sprintf(
                '/(<p.*>)?(##deleted##)+(%s|%s)?(<br\h?\/>)?(<\/p>)?\v?/',
                preg_quote(ControlCharacter::BREAK->value, '/'),
                preg_quote(ControlCharacter::PARAGRAPH->value, '/')
            ),
            '',
            $html
        );

        // Füge Zeilenumbruch nach </p> ein
        return str_replace('</p>', '</p>' . PHP_EOL, $html);
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
}
