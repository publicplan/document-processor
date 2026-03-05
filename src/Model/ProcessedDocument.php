<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Model;

use Publicplan\DocumentProcessor\Model\ParserError;
use DateTimeInterface;

/**
 * DTO für das Ergebnis der Dokumentverarbeitung.
 * Enthält HTML, Metadaten und Parser-Messages.
 */
readonly class ProcessedDocument
{
    /**
     * @param string $html Das generierte HTML
     * @param DateTimeInterface $lastModified Zeitpunkt der letzten Änderung
     * @param bool $hasUnacceptedChanges Ob Track-Changes vorhanden sind
     * @param array<string, ParserError[]> $messages Nach Typ gruppierte Parser-Messages
     * @param string $sourceFilename Ursprünglicher Dateiname
     */
    public function __construct(
        public string $html,
        public DateTimeInterface $lastModified,
        public bool $hasUnacceptedChanges,
        public array $messages,
        public string $sourceFilename
    ) {
    }

    /**
     * Gibt alle Fehlermeldungen zurück.
     *
     * @return ParserError[]
     */
    public function getErrors(): array
    {
        return $this->messages['errors'] ?? [];
    }

    /**
     * Gibt alle Warnungen zurück.
     *
     * @return ParserError[]
     */
    public function getWarnings(): array
    {
        return $this->messages['warnings'] ?? [];
    }

    /**
     * Gibt alle Infos zurück.
     *
     * @return ParserError[]
     */
    public function getInfos(): array
    {
        return $this->messages['infos'] ?? [];
    }

    /**
     * Gibt alle Messages als flaches Array zurück.
     *
     * @return ParserError[]
     */
    public function getAllMessages(): array
    {
        $all = [];
        foreach ($this->messages as $severity => $messages) {
            $all = array_merge($all, $messages);
        }
        return $all;
    }

    /**
     * Konvertiert zu Legacy-Format für Controller-Kompatibilität.
     *
     * @return array<string, mixed>
     */
    public function toLegacyFormat(): array
    {
        $legacyMessages = [];
        foreach ($this->messages as $type => $errors) {
            $legacyMessages = array_merge($legacyMessages, $errors);
        }

        return [
            'html' => $this->html,
            'lastModified' => $this->lastModified,
            'hasUnacceptedChanges' => $this->hasUnacceptedChanges,
            'messages' => $legacyMessages,
            'sourceFilename' => $this->sourceFilename,
        ];
    }

    /**
     * Prüft, ob das Dokument Fehler enthält.
     */
    public function hasErrors(): bool
    {
        return !empty($this->getErrors());
    }

    /**
     * Prüft, ob das Dokument Warnungen enthält.
     */
    public function hasWarnings(): bool
    {
        return !empty($this->getWarnings());
    }
}
