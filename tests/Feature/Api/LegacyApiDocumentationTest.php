<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * /docs/api sahifasidagi eski API (/api/legacy) hujjati: har bir action uchun so‘rov va javob namunasi.
 */
class LegacyApiDocumentationTest extends TestCase
{
    use RefreshDatabase;

    private const ACTIONS = ['add_employee', 'delete_employee', 'attendance', 'add_note', 'get_reports', 'get_work_time'];

    private const CONSUMER_IP = '203.0.113.5';

    protected function setUp(): void
    {
        parent::setUp();

        Gate::define('viewApiDocs', fn ($user = null): bool => true);
    }

    public function test_legacy_api_is_documented_as_post_with_an_example_for_every_action(): void
    {
        $operation = $this->legacyOperation();

        $body = $operation['requestBody']['content']['application/json'];

        $this->assertTrue($operation['requestBody']['required']);
        $this->assertSame(self::ACTIONS, $body['schema']['properties']['action']['enum']);
        $this->assertSame(self::ACTIONS, array_keys($body['examples']));

        foreach ($body['examples'] as $action => $example) {
            $this->assertSame($action, $example['value']['action']);
        }
    }

    public function test_legacy_api_documents_responses_and_the_ip_filter(): void
    {
        $responses = $this->legacyOperation()['responses'];

        $examples = $responses['200']['content']['application/json']['examples'];
        $this->assertSame(['status' => true, 'alerts' => ['Saved successfully']], $examples['add_employee']['value']);
        $this->assertArrayHasKey('attendance', $examples['attendance']['value']);
        $this->assertArrayHasKey('reports', $examples['get_reports']['value']);
        $this->assertArrayHasKey('work_time', $examples['get_work_time']['value']);

        $this->assertSame(
            ['status' => false, 'alerts' => ['Access denied']],
            $responses['403']['content']['application/json']['example'],
        );
    }

    public function test_documented_read_only_examples_are_accepted_by_the_api(): void
    {
        config(['legacy_api.allowed_ips' => [self::CONSUMER_IP]]);

        $examples = $this->legacyOperation()['requestBody']['content']['application/json']['examples'];

        foreach (['attendance', 'get_reports', 'get_work_time'] as $action) {
            $this->call('POST', '/api/legacy', server: [
                'REMOTE_ADDR' => self::CONSUMER_IP,
                'CONTENT_TYPE' => 'application/json',
            ], content: json_encode($examples[$action]['value']))
                ->assertOk()
                ->assertJsonPath('status', true);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function legacyOperation(): array
    {
        $paths = $this->getJson('/docs/api.json')->assertOk()->json('paths');

        $this->assertArrayHasKey('/legacy', $paths);
        $this->assertArrayNotHasKey('get', $paths['/legacy']);

        return $paths['/legacy']['post'];
    }
}
