<?php

declare(strict_types=1);

namespace LatentKit\Laravel\Tests;

use Illuminate\Support\Facades\Facade;
use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Jobs\InvokeAgent;
use LatentKit\Laravel\Ai\LatentKitProvider;
use LatentKit\Laravel\ClientFactory;
use LatentKit\Laravel\Exceptions\LatentKitAiException;
use LatentKit\Laravel\Exceptions\UnsupportedFeatureException;
use LatentKit\Laravel\Facades\LatentKit as LatentKitFacade;
use LatentKit\Laravel\LatentKitServiceProvider;
use LatentKit\LatentKit;
use LatentKit\Transport\HttpResponse;

final class LatentKitIntegrationTest extends TestCase
{
    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        parent::tearDown();
    }

    public function test_package_binding_uses_cached_configuration_and_tool_attribution(): void
    {
        $this->transport->push($this->jsonResponse(['content' => 'Direct response']));

        $response = $this->app->make(LatentKit::class)->chat->create([
            ['role' => 'user', 'content' => 'Hello'],
        ]);

        self::assertSame('Direct response', $response['content']);
        self::assertSame('https://example.test/v1/chat', $this->transport->requests[0]['url']);
        self::assertSame('latentkit-laravel', $this->transport->requests[0]['headers']['X-LK-Tool-Slug']);
        self::assertSame(LatentKitServiceProvider::VERSION, $this->transport->requests[0]['headers']['X-LK-Tool-Version']);
        self::assertSame(120.0, $this->transport->requests[0]['timeout']);
    }

    public function test_laravel_ai_driver_maps_text_without_forwarding_route_control(): void
    {
        $this->transport->push($this->jsonResponse([
            'content' => 'Laravel response',
            'provider' => 'anthropic',
            'model' => 'claude-haiku-4-5',
            'finish_reason' => 'stop',
            'usage' => ['prompt_tokens' => 11, 'completion_tokens' => 7],
            'request_id' => 'req_success',
        ]));

        $response = (new AnonymousAgent('Answer briefly.', [], []))->prompt(
            'Hello from Laravel.',
            provider: 'latentkit',
        );

        self::assertSame('Laravel response', $response->text);
        self::assertSame(11, $response->usage->promptTokens);
        self::assertSame(7, $response->usage->completionTokens);
        self::assertSame('anthropic', $response->meta->provider);
        self::assertSame('claude-haiku-4-5', $response->meta->model);

        $body = json_decode((string) $this->transport->requests[0]['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame([
            ['role' => 'system', 'content' => 'Answer briefly.'],
            ['role' => 'user', 'content' => 'Hello from Laravel.'],
        ], $body['messages']);
        self::assertArrayNotHasKey('model', $body);
        self::assertArrayNotHasKey('provider', $body);
        self::assertArrayNotHasKey('route', $body);
        self::assertArrayNotHasKey('policy', $body);
    }

    public function test_laravel_ai_driver_rejects_non_logical_models_before_transport(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(LatentKitProvider::ROUTE_MODEL_ID);

        try {
            (new AnonymousAgent('', [], []))->prompt(
                'Hello',
                provider: 'latentkit',
                model: 'gpt-4.1',
            );
        } finally {
            self::assertSame([], $this->transport->requests);
        }
    }

    public function test_provider_errors_expose_only_status_and_request_id(): void
    {
        $this->transport->push($this->jsonResponse([
            'error' => [
                'message' => 'provider response body must stay hidden',
                'request_id' => 'req_safe_123',
            ],
        ], 429));

        try {
            (new AnonymousAgent('', [], []))->prompt('Hello', provider: 'latentkit');
            self::fail('Expected a safe LatentKit AI exception.');
        } catch (LatentKitAiException $exception) {
            self::assertSame(429, $exception->statusCode);
            self::assertSame('req_safe_123', $exception->requestId);
            self::assertStringContainsString('Request ID: req_safe_123', $exception->getMessage());
            self::assertStringNotContainsString('provider response body', $exception->getMessage());
        }
    }

    public function test_streaming_fails_explicitly_without_a_network_request(): void
    {
        $stream = (new AnonymousAgent('', [], []))->stream('Hello', provider: 'latentkit');

        $this->expectException(UnsupportedFeatureException::class);
        $this->expectExceptionMessage('streaming');

        try {
            foreach ($stream as $event) {
                unset($event);
            }
        } finally {
            self::assertSame([], $this->transport->requests);
        }
    }

    public function test_health_command_uses_typed_connection_context_without_secrets(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(__DIR__.'/fixtures/me-context.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        $this->transport->push($this->jsonResponse($fixture));

        $this->artisan('latentkit:check')
            ->expectsOutput('LatentKit connection is healthy.')
            ->assertSuccessful();

        self::assertSame('GET', $this->transport->requests[0]['method']);
        self::assertSame('https://example.test/v1/me', $this->transport->requests[0]['url']);
    }

    public function test_facade_fake_records_requests_without_network_access(): void
    {
        $fake = LatentKitFacade::fake([
            ['content' => 'Fake response'],
        ]);

        $response = LatentKitFacade::client()->chat->create([
            ['role' => 'user', 'content' => 'Queue-safe request'],
        ]);

        self::assertSame('Fake response', $response['content']);
        $fake->assertChatSent(fn (array $request): bool => $request['messages'][0]['content'] === 'Queue-safe request');
        self::assertSame([], $this->transport->requests);
    }

    public function test_queued_agent_job_serializes_without_the_client_or_credential(): void
    {
        $job = new InvokeAgent(
            agent: new AnonymousAgent('Queued instruction.', [], []),
            prompt: 'Queued prompt.',
            provider: 'latentkit',
        );

        $serialized = serialize($job);
        self::assertStringNotContainsString('test-key', $serialized);
        self::assertStringNotContainsString(ClientFactory::class, $serialized);

        $this->transport->push($this->jsonResponse(['content' => 'Queued response']));

        /** @var InvokeAgent $restored */
        $restored = unserialize($serialized, ['allowed_classes' => true]);
        $restored->handle();

        self::assertCount(1, $this->transport->requests);
        self::assertSame('https://example.test/v1/chat', $this->transport->requests[0]['url']);
    }

    private function jsonResponse(array $body, int $status = 200): HttpResponse
    {
        return new HttpResponse($status, json_encode($body, JSON_THROW_ON_ERROR));
    }
}
