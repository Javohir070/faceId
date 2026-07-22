<?php

namespace Tests\Unit\Hikvision;

use App\Services\Hikvision\HikvisionEventParser;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HikvisionEventParserTest extends TestCase
{
    private HikvisionEventParser $parser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->parser = new HikvisionEventParser;
    }

    public function test_parses_json_access_controller_event(): void
    {
        $payload = [
            'ipAddress' => '192.168.1.64',
            'dateTime' => '2026-07-22T12:00:00+05:00',
            'AccessControllerEvent' => [
                'employeeNoString' => '1001',
                'majorEventType' => 5,
                'subEventType' => 75,
                'currentVerifyMode' => 'face',
            ],
        ];

        $parsed = $this->parser->normalizePayload($payload, '10.0.0.1');

        $this->assertNotNull($parsed);
        $this->assertSame('1001', data_get($parsed, 'AccessControllerEvent.employeeNoString'));
        $this->assertSame('192.168.1.64', $parsed['ipAddress']);
    }

    public function test_parses_xml_event_notification_alert(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<EventNotificationAlert version="2.0">
    <ipAddress>192.168.1.64</ipAddress>
    <dateTime>2026-07-22T12:00:00+05:00</dateTime>
    <eventType>AccessControllerEvent</eventType>
    <AccessControllerEvent>
        <employeeNoString>1002</employeeNoString>
        <majorEventType>5</majorEventType>
        <subEventType>75</subEventType>
        <currentVerifyMode>face</currentVerifyMode>
    </AccessControllerEvent>
</EventNotificationAlert>
XML;

        $parsed = $this->parser->parseXml($xml, '192.168.1.64');

        $this->assertNotNull($parsed);
        $this->assertSame('1002', data_get($parsed, 'AccessControllerEvent.employeeNoString'));
    }

    public function test_extracts_events_from_multipart_stream(): void
    {
        $boundary = 'MIME_boundary';
        $chunk = <<<'BODY'
--MIME_boundary
Content-Type: application/xml; charset="UTF-8"

<?xml version="1.0" encoding="UTF-8"?>
<EventNotificationAlert version="2.0">
    <ipAddress>192.168.1.64</ipAddress>
    <dateTime>2026-07-22T12:00:00+05:00</dateTime>
    <AccessControllerEvent>
        <employeeNoString>1003</employeeNoString>
        <majorEventType>5</majorEventType>
        <subEventType>75</subEventType>
    </AccessControllerEvent>
</EventNotificationAlert>
--MIME_boundary--

BODY;

        $buffer = '';
        $detectedBoundary = null;
        $events = $this->parser->extractEventsFromMultipart($chunk, $detectedBoundary, $buffer);

        $this->assertCount(1, $events);
        $this->assertSame('1003', data_get($events[0], 'AccessControllerEvent.employeeNoString'));
    }

    #[DataProvider('irrelevantEventProvider')]
    public function test_ignores_irrelevant_events(array $payload): void
    {
        $this->assertNull($this->parser->normalizePayload($payload));
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public static function irrelevantEventProvider(): array
    {
        return [
            'heartbeat' => [[
                'eventType' => 'heartBeat',
                'dateTime' => '2026-07-22T12:00:00+05:00',
            ]],
            'access_without_employee_or_face_event' => [[
                'AccessControllerEvent' => [
                    'majorEventType' => 1,
                    'subEventType' => 1,
                ],
            ]],
        ];
    }
}
