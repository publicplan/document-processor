<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Service;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\ListItemRun as DocList;
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
        $text           = '';
        $openListConfig = null; // Trackt die aktuell geöffnete Liste

        foreach ($doc->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                // Listen-Handling
                if ($element instanceof DocList) {
                    $html           = $this->handleListElement($element, $context, $openListConfig, $text);
                    $openListConfig = $html['listConfig'];
                    $text           .= $html['content'];
                } else {
                    // Nicht-Listen-Element: Schließe offene Liste, falls vorhanden
                    if ($openListConfig !== null) {
                        $text           .= $openListConfig->renderEndTag() . PHP_EOL;
                        $openListConfig = null;
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

        // Am Ende: Schließe noch offene Listen
        if ($openListConfig !== null) {
            $text .= $openListConfig->renderEndTag() . PHP_EOL;
        }

        return $text;
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
