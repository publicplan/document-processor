<?php

declare(strict_types=1);

namespace Publicplan\DocumentProcessor\Model;

use Publicplan\DocumentProcessor\Model\ParserError;
use Publicplan\DocumentProcessor\Enum\Context;

/**
 * Kontext für die Konvertierung.
 * Enthält Einstellungen und sammelt Parser-Messages.
 */
class ConversionContext
{
    /** @var array<string, ParserError[]> */
    private array $messages = [];

    /** @var array<string, bool> */
    private array $distinctMessageHashes = [];

    public function __construct(
        private readonly bool $trackDistinctMessages = true
    ) {
        $this->messages = [
            'errors' => [],
            'warnings' => [],
            'notices' => [],
            'infos' => [],
        ];
    }

    /**
     * Fügt eine Parser-Message hinzu.
     *
     * @param ParserError $error Die hinzuzufügende Message
     * @param bool $distinct Ob Duplikate verhindert werden sollen
     */
    public function addMessage(ParserError $error, bool $distinct = false): void
    {
        $severity = $error->getSeverity();

        if ($distinct && $this->isDuplicate($error)) {
            return;
        }

        if ($distinct) {
            $this->distinctMessageHashes[$this->calculateHash($error)] = true;
        }

        $this->messages[$severity][] = $error;
    }

    /**
     * Prüft, ob eine Message bereits existiert (nur für distinct).
     */
    private function isDuplicate(ParserError $error): bool
    {
        return isset($this->distinctMessageHashes[$this->calculateHash($error)]);
    }

    /**
     * Berechnet einen Hash für die Message.
     */
    private function calculateHash(ParserError $error): string
    {
        return md5($error->getType() . $error->getSeverity() . $error->getMessage());
    }

    /**
     * Gibt alle Messages zurück.
     *
     * @return array<string, ParserError[]>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Gibt alle Messages als flaches Array zurück (Legacy-Format).
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
     * Prüft, ob Fehler vorhanden sind.
     */
    public function hasErrors(): bool
    {
        return !empty($this->messages['errors']);
    }

    /**
     * Prüft, ob Warnungen vorhanden sind.
     */
    public function hasWarnings(): bool
    {
        return !empty($this->messages['warnings']);
    }
}
