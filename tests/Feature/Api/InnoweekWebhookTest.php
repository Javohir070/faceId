<?php

namespace Tests\Feature\Api;

use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InnoweekWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'innoweek.api_token' => 'secret-token',
            'innoweek.department' => 'Mehmonlar',
        ]);
    }

    public function test_participant_from_webhook_becomes_visitor_immediately(): void
    {
        $this->withToken('secret-token')
            ->postJson('/api/webhooks/innoweek/participants', ['data' => [[
                'id' => 77,
                'card' => '0003711111',
                'first_name' => 'Joyida',
                'last_name' => 'Ro‘yxatdan',
                'gender' => 2,
                'country' => ['name_uz' => 'Qozog‘iston', 'name_en' => 'Kazakhstan'],
                'profession' => 'OAV (Media)',
                'year' => 2026,
                'status' => 'active',
                'registered_at' => '2026-10-26T09:15:02+05:00',
            ]]])
            ->assertOk()
            ->assertJson(['status' => 'ok', 'created' => 1, 'updated' => 0]);

        $visitor = Employee::query()->withVisitors()->where('card', '0003711111')->firstOrFail();
        $this->assertTrue($visitor->is_visitor);
        $this->assertSame('Ro‘yxatdan Joyida', $visitor->name);
        $this->assertSame('KZ', $visitor->country);
        $this->assertSame('OAV (Media)', $visitor->group->name);
        $this->assertSame('Mehmonlar', $visitor->department->name);

        // Takror yuborish — dublikat emas, yangilanadi.
        $this->withToken('secret-token')
            ->postJson('/api/webhooks/innoweek/participants', ['data' => [['card' => '0003711111', 'first_name' => 'Yangi', 'last_name' => 'Ism']]])
            ->assertOk()
            ->assertJson(['created' => 0, 'updated' => 1]);

        $this->assertSame(1, Employee::query()->withVisitors()->count());
        $this->assertSame('Ism Yangi', $visitor->fresh()->name);
    }

    public function test_webhook_rejects_bad_token_and_invalid_payload(): void
    {
        $this->postJson('/api/webhooks/innoweek/participants', ['data' => [['card' => '1']]])
            ->assertUnauthorized();

        $this->withToken('wrong')
            ->postJson('/api/webhooks/innoweek/participants', ['data' => [['card' => '1']]])
            ->assertUnauthorized();

        $this->withToken('secret-token')
            ->postJson('/api/webhooks/innoweek/participants', ['data' => [['first_name' => 'Kartasiz']]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('data.0.card');

        config(['innoweek.api_token' => '']);

        $this->withToken('')
            ->postJson('/api/webhooks/innoweek/participants', ['data' => [['card' => '1']]])
            ->assertUnauthorized();

        $this->assertSame(0, Employee::query()->withVisitors()->count());
    }
}
