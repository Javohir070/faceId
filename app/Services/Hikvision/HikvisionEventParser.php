<?php

namespace App\Services\Hikvision;

use SimpleXMLElement;

class HikvisionEventParser
{
    /**
     * @return array<string, mixed>|null
     */
    public function parseRaw(string $raw, ?string $fallbackDeviceIp = null): ?array
    {
        $raw = trim($raw);

        if ($raw === '') {
            return null;
        }

        if (str_starts_with($raw, '{') || str_starts_with($raw, '[')) {
            $decoded = json_decode($raw, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $this->normalizePayload($decoded, $fallbackDeviceIp);
            }
        }

        if (str_starts_with($raw, '<')) {
            return $this->parseXml($raw, $fallbackDeviceIp);
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function extractEventsFromMultipart(string $chunk, ?string &$boundary, string &$buffer): array
    {
        $buffer .= $chunk;
        $events = [];

        if ($boundary === null) {
            $boundary = $this->detectBoundary($buffer);

            if ($boundary === null) {
                return $this->extractStandaloneXmlEvents($buffer, $events);
            }
        }

        $delimiter = '--'.$boundary;
        $closingDelimiter = $delimiter.'--';

        while (true) {
            $start = strpos($buffer, $delimiter);

            if ($start === false) {
                break;
            }

            $partStart = $start + strlen($delimiter);

            if (str_starts_with(substr($buffer, $partStart), '--')) {
                $buffer = substr($buffer, $partStart + 2);

                break;
            }

            $partStart = $this->skipLineBreak($buffer, $partStart);
            $nextBoundary = strpos($buffer, $delimiter, $partStart);

            if ($nextBoundary === false) {
                if (strlen($buffer) - $start > 1024 * 1024) {
                    $buffer = substr($buffer, $start);
                }

                break;
            }

            $part = substr($buffer, $partStart, $nextBoundary - $partStart);
            $buffer = substr($buffer, $nextBoundary);

            $body = $this->extractMultipartBody($part);

            if ($body === null) {
                continue;
            }

            $parsed = $this->parseRaw($body);

            if ($parsed !== null) {
                $events[] = $parsed;
            }
        }

        if ($events === [] && $boundary === null) {
            return $this->extractStandaloneXmlEvents($buffer, $events);
        }

        return $events;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>|null
     */
    public function normalizePayload(array $event, ?string $fallbackDeviceIp = null): ?array
    {
        if (isset($event['EventNotificationAlert']) && is_array($event['EventNotificationAlert'])) {
            $event = $event['EventNotificationAlert'];
        }

        if (! isset($event['AccessControllerEvent']) && $this->looksLikeAccessControllerEvent($event)) {
            $event = [
                'ipAddress' => $fallbackDeviceIp,
                'dateTime' => $event['dateTime'] ?? now()->toIso8601String(),
                'AccessControllerEvent' => $event,
            ];
        }

        if (! isset($event['AccessControllerEvent']) || ! is_array($event['AccessControllerEvent'])) {
            return null;
        }

        if (! isset($event['ipAddress']) && $fallbackDeviceIp !== null) {
            $event['ipAddress'] = $fallbackDeviceIp;
        }

        if (! $this->isRelevantAccessEvent($event)) {
            return null;
        }

        return $event;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parseXml(string $xml, ?string $fallbackDeviceIp = null): ?array
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NOCDATA);

        libxml_use_internal_errors($previous);

        if ($document === false) {
            return null;
        }

        $root = json_decode(json_encode($document), true);

        if (! is_array($root)) {
            return null;
        }

        return $this->normalizePayload($root, $fallbackDeviceIp);
    }

    private function detectBoundary(string $buffer): ?string
    {
        if (preg_match('/boundary=(?:"([^"]+)"|([^;\r\n]+))/i', $buffer, $matches)) {
            return trim($matches[1] !== '' ? $matches[1] : $matches[2]);
        }

        if (preg_match('/^--([^\r\n]+)/', $buffer, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array<string, mixed>>
     */
    private function extractStandaloneXmlEvents(string &$buffer, array $events): array
    {
        while (preg_match('/<EventNotificationAlert[\s\S]*?<\/EventNotificationAlert>/i', $buffer, $matches, PREG_OFFSET_CAPTURE)) {
            $xml = $matches[0][0];
            $offset = $matches[0][1] + strlen($xml);
            $buffer = substr($buffer, $offset);

            $parsed = $this->parseXml($xml);

            if ($parsed !== null) {
                $events[] = $parsed;
            }
        }

        if (strlen($buffer) > 512 * 1024) {
            $buffer = substr($buffer, -256 * 1024);
        }

        return $events;
    }

    private function extractMultipartBody(string $part): ?string
    {
        $separator = str_contains($part, "\r\n\r\n") ? "\r\n\r\n" : "\n\n";
        $parts = explode($separator, $part, 2);

        if (count($parts) !== 2) {
            return trim($part) !== '' ? trim($part) : null;
        }

        $body = trim($parts[1]);

        return $body !== '' ? $body : null;
    }

    private function skipLineBreak(string $buffer, int $offset): int
    {
        if (str_starts_with(substr($buffer, $offset), "\r\n")) {
            return $offset + 2;
        }

        if (str_starts_with(substr($buffer, $offset), "\n")) {
            return $offset + 1;
        }

        return $offset;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function looksLikeAccessControllerEvent(array $event): bool
    {
        return isset($event['employeeNoString'])
            || isset($event['employeeNo'])
            || isset($event['cardNo'])
            || isset($event['majorEventType'])
            || isset($event['major']);
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function isRelevantAccessEvent(array $event): bool
    {
        $accessEvent = $event['AccessControllerEvent'];

        if (! is_array($accessEvent)) {
            return false;
        }

        $employeeNo = data_get($accessEvent, 'employeeNoString')
            ?? data_get($accessEvent, 'employeeNo');

        if (is_string($employeeNo) && $employeeNo !== '') {
            return true;
        }

        $major = (int) (data_get($accessEvent, 'majorEventType') ?? data_get($accessEvent, 'major') ?? 0);
        $minor = (int) (data_get($accessEvent, 'subEventType') ?? data_get($accessEvent, 'minor') ?? 0);

        return $major === 5 && $minor === 75;
    }
}
