<?php

namespace Tests\Feature\Api;

use App\Models\AccessControllerEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HikvisionWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'hikvision.webhook_secret' => 'test-webhook-secret',
        ]);
    }

    public function test_webhook_rejects_missing_secret(): void
    {
        $this->postJson('/api/webhooks/hikvision/events', [
            'event_log' => json_encode($this->sampleEvent()),
        ])->assertUnauthorized();

        $this->assertDatabaseCount('access_controller_events', 0);
    }

    public function test_webhook_rejects_invalid_secret(): void
    {
        $this->postJson('/api/webhooks/hikvision/events', [
            'event_log' => json_encode($this->sampleEvent()),
        ], [
            'X-Webhook-Secret' => 'wrong-secret',
        ])->assertUnauthorized();
    }

    public function test_webhook_requires_configured_secret(): void
    {
        config(['hikvision.webhook_secret' => '']);

        $this->postJson('/api/webhooks/hikvision/events', [
            'event_log' => json_encode($this->sampleEvent()),
        ], [
            'X-Webhook-Secret' => 'anything',
        ])->assertStatus(503);
    }

    public function test_webhook_rejects_invalid_json_payload(): void
    {
        $this->postJson('/api/webhooks/hikvision/events', [
            'event_log' => '{not-json',
        ], [
            'X-Webhook-Secret' => 'test-webhook-secret',
        ])->assertStatus(422);

        $this->assertDatabaseCount('access_controller_events', 0);
    }

    public function test_webhook_stores_valid_event(): void
    {
        $payload = $this->sampleEvent();

        $this->postJson('/api/webhooks/hikvision/events', [
            'event_log' => json_encode($payload),
        ], [
            'X-Webhook-Secret' => 'test-webhook-secret',
        ])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('access_controller_events', [
            'employee_no' => '1001',
            'card_no' => '998877',
            'full_name' => 'Ali Valiyev',
            'device_ip' => '10.0.0.8',
        ]);
    }

    public function test_webhook_accepts_secret_via_query_string(): void
    {
        $this->postJson('/api/webhooks/hikvision/events?secret=test-webhook-secret', [
            'event_log' => json_encode($this->sampleEvent()),
        ])
            ->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_webhook_accepts_secret_via_url_path(): void
    {
        $this->postJson('/api/webhooks/hikvision/events/test-webhook-secret', [
            'event_log' => json_encode($this->sampleEvent()),
        ])
            ->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_webhook_accepts_trusted_device_ip_without_secret(): void
    {
        config([
            'hikvision.base_uri' => 'http://192.168.100.115/',
            'hikvision.webhook_trust_device_ip' => true,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.100.115'])
            ->postJson('/api/webhooks/hikvision/events', [
                'event_log' => json_encode($this->sampleEvent()),
            ])
            ->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_webhook_accepts_access_controller_event_field_from_device(): void
    {
        config([
            'hikvision.base_uri' => 'http://192.168.100.115/',
            'hikvision.webhook_trust_device_ip' => true,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.100.115'])
            ->post('/api/webhooks/hikvision/events', [
                'AccessControllerEvent' => json_encode($this->sampleEvent()),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('access_controller_events', [
            'employee_no' => '1001',
            'device_ip' => '10.0.0.8',
        ]);
    }

    public function test_webhook_accepts_inner_access_controller_event_object(): void
    {
        config([
            'hikvision.base_uri' => 'http://192.168.100.115/',
            'hikvision.webhook_trust_device_ip' => true,
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.100.115'])
            ->post('/api/webhooks/hikvision/events', [
                'AccessControllerEvent' => json_encode([
                    'employeeNoString' => '2002',
                    'cardNo' => '445566',
                    'name' => 'Device Payload',
                    'majorEventType' => 5,
                    'subEventType' => 75,
                ]),
            ], [
                'Accept' => 'application/json',
            ])
            ->assertOk();

        $this->assertDatabaseHas('access_controller_events', [
            'employee_no' => '2002',
            'full_name' => 'Device Payload',
            'device_ip' => '192.168.100.115',
        ]);
    }

    public function test_webhook_stores_valid_image_and_rejects_non_image(): void
    {
        Storage::fake('public');

        $this->post('/api/webhooks/hikvision/events', [
            'event_log' => json_encode($this->sampleEvent()),
            'Picture' => UploadedFile::fake()->image('face.jpg', 320, 240),
        ], [
            'X-Webhook-Secret' => 'test-webhook-secret',
            'Accept' => 'application/json',
        ])->assertOk();

        $event = AccessControllerEvent::query()->first();
        $this->assertNotNull($event?->picture);
        Storage::disk('public')->assertExists($event->picture);

        $this->post('/api/webhooks/hikvision/events', [
            'event_log' => json_encode($this->sampleEvent([
                'AccessControllerEvent' => [
                    'employeeNoString' => '1002',
                    'cardNo' => '112233',
                    'name' => 'Second User',
                ],
            ])),
            'Picture' => UploadedFile::fake()->create('payload.exe', 100, 'application/octet-stream'),
        ], [
            'X-Webhook-Secret' => 'test-webhook-secret',
            'Accept' => 'application/json',
        ])->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function sampleEvent(array $overrides = []): array
    {
        return array_replace_recursive([
            'ipAddress' => '10.0.0.8',
            'dateTime' => '2026-07-17T10:00:00+05:00',
            'AccessControllerEvent' => [
                'employeeNoString' => '1001',
                'cardNo' => '998877',
                'name' => 'Ali Valiyev',
                'majorEventType' => 5,
                'subEventType' => 75,
                'currentVerifyMode' => 'face',
                'userType' => 'normal',
                'attendanceStatus' => 'checkIn',
            ],
        ], $overrides);
    }
}
