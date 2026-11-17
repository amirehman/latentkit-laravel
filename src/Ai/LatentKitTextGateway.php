<?php

declare(strict_types=1);

namespace LatentKit\Laravel\Ai;

use Generator;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\ToolResultMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\FinishReason;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Streaming\Events\StreamEvent;
use LatentKit\Exceptions\ApiException;
use LatentKit\Exceptions\LatentKitException;
use LatentKit\Laravel\ClientFactory;
use LatentKit\Laravel\Exceptions\LatentKitAiException;
use LatentKit\Laravel\Exceptions\UnsupportedFeatureException;

final class LatentKitTextGateway implements StepTextGateway
{
    public function __construct(
        private readonly ClientFactory $clientFactory,
        private readonly ?string $responseProfile = null,
    ) {}

    /**
     * @param  Message[]  $messages
     * @param  Tool[]  $tools
     * @param  array<string, Type>|null  $schema
     */
    public function generateTextStep(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): StepResponse {
        $this->assertSupportedRequest($model, $tools, $schema, $stepContext);

        $extraBody = [];
        if ($options?->topP !== null) {
            $extraBody['top_p'] = $options->topP;
        }

        try {
            $response = $this->clientFactory
                ->make($timeout !== null ? (float) $timeout : null)
                ->chat
                ->create(
                    messages: $this->mapMessages($instructions, $messages),
                    maxTokens: max(1, $options->maxTokens ?? 1000),
                    temperature: $options->temperature ?? 0.7,
                    responseProfile: $this->responseProfile,
                    extraBody: $extraBody,
                );
        } catch (ApiException $exception) {
            throw LatentKitAiException::fromApiException($exception);
        } catch (LatentKitException $exception) {
            throw new LatentKitAiException(
                'LatentKit could not complete the Laravel AI request.',
                previous: $exception,
            );
        }

        $content = $response['content'] ?? null;
        if (! is_string($content)) {
            throw new LatentKitAiException('LatentKit returned a response without text content.');
        }

        $usage = is_array($response['usage'] ?? null) ? $response['usage'] : [];

        return new StepResponse(
            text: $content,
            toolCalls: [],
            finishReason: $this->finishReason($response['finish_reason'] ?? null),
            usage: new TextUsage(
                inputTokens: $this->integer(
                    $usage['input_tokens'] ?? $usage['prompt_tokens'] ?? $response['prompt_tokens'] ?? null,
                ),
                outputTokens: $this->integer(
                    $usage['output_tokens'] ?? $usage['completion_tokens'] ?? $response['completion_tokens'] ?? null,
                ),
                cacheReadInputTokens: $this->optionalInteger(
                    $usage['cached_input_tokens'] ?? $response['cached_input_tokens'] ?? null,
                ),
                reasoningTokens: $this->optionalInteger(
                    $usage['reasoning_tokens'] ?? $response['reasoning_tokens'] ?? null,
                ),
            ),
            meta: new Meta(
                provider: $this->optionalString($response['provider'] ?? null) ?? 'latentkit',
                model: $this->optionalString($response['model'] ?? null) ?? LatentKitProvider::ROUTE_MODEL_ID,
            ),
        );
    }

    /**
     * @param  Message[]  $messages
     * @param  Tool[]  $tools
     * @param  array<string, Type>|null  $schema
     * @return Generator<int, StreamEvent, mixed, StepResponse|null>
     */
    public function generateStreamStep(
        string $invocationId,
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): Generator {
        throw new UnsupportedFeatureException(
            'LatentKit streaming is not available in this Laravel integration release.',
        );
    }

    /**
     * @param  Tool[]  $tools
     * @param  array<string, Type>|null  $schema
     */
    private function assertSupportedRequest(
        string $model,
        array $tools,
        ?array $schema,
        StepContext $stepContext,
    ): void {
        if ($model !== LatentKitProvider::ROUTE_MODEL_ID) {
            throw new \InvalidArgumentException(
                'LatentKit exposes one logical Laravel AI model: latentkit-route. The assigned route selects the provider and model.',
            );
        }
        if ($tools !== []) {
            throw new UnsupportedFeatureException('LatentKit tool calls are not available in this Laravel integration release.');
        }
        if ($schema !== null) {
            throw new UnsupportedFeatureException('LatentKit structured output is not available in this Laravel integration release.');
        }
        if ($stepContext->stepNumber > 0) {
            throw new UnsupportedFeatureException('LatentKit multi-step tool execution is not available in this Laravel integration release.');
        }
    }

    /**
     * @param  array<array-key, mixed>  $messages
     * @return list<array{role: string, content: string}>
     */
    private function mapMessages(?string $instructions, array $messages): array
    {
        $mapped = [];
        $messageCount = 0;
        if ($instructions !== null && trim($instructions) !== '') {
            $mapped[] = ['role' => 'system', 'content' => $instructions];
        }

        foreach ($messages as $message) {
            if (! $message instanceof Message) {
                throw new \InvalidArgumentException('Laravel AI messages must use the official Message types.');
            }
            if ($message instanceof UserMessage && $message->attachments->isNotEmpty()) {
                throw new UnsupportedFeatureException('LatentKit attachments are not available in this Laravel integration release.');
            }
            if ($message instanceof AssistantMessage && $message->toolCalls->isNotEmpty()) {
                throw new UnsupportedFeatureException('LatentKit tool history is not available in this Laravel integration release.');
            }
            if ($message instanceof ToolResultMessage) {
                throw new UnsupportedFeatureException('LatentKit tool results are not available in this Laravel integration release.');
            }
            if (! in_array($message->role->value, ['user', 'assistant'], true) || ! is_string($message->content)) {
                throw new UnsupportedFeatureException('LatentKit supports text-only Laravel AI user and assistant messages.');
            }

            $mapped[] = ['role' => $message->role->value, 'content' => $message->content];
            $messageCount++;
        }

        if ($messageCount === 0) {
            throw new \InvalidArgumentException('At least one Laravel AI chat message is required.');
        }

        return $mapped;
    }

    private function finishReason(mixed $value): FinishReason
    {
        return match (is_string($value) ? strtolower($value) : null) {
            'length', 'max_tokens' => FinishReason::Length,
            'content_filter' => FinishReason::ContentFilter,
            'error' => FinishReason::Error,
            default => FinishReason::Stop,
        };
    }

    private function integer(mixed $value): int
    {
        return is_numeric($value) ? max(0, (int) $value) : 0;
    }

    /** Null means LatentKit did not report the count, which is different from zero. */
    private function optionalInteger(mixed $value): ?int
    {
        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    private function optionalString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
