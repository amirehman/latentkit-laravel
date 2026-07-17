# LatentKit for Laravel

Status: `0.1.0-alpha.1` source alpha

Official Laravel integration for LatentKit's canonical, route-based `/v1` API.
It provides an auto-discovered SDK binding, facade, connection-check command,
network-free fake, and a non-streaming text provider for the official Laravel
AI SDK.

## Requirements

- PHP 8.3+
- Laravel 12 or 13
- Laravel AI SDK 0.9.1+
- LatentKit PHP SDK 0.1.0-beta.3

The source alpha is not published on Packagist yet. Development remains in the
LatentKit monorepo and approved releases are split automatically into the
public [Laravel package repository](https://github.com/amirehman/latentkit-laravel).
Do not add either repository as a production Composer repository.

Because this alpha depends on the prerelease PHP SDK, its first Packagist
install will explicitly request both prereleases:

```bash
composer require \
  latentkit/latentkit-php:0.1.0-beta.3 \
  latentkit/laravel:0.1.0-alpha.1
```

This command becomes usable only after `0.1.0-alpha.1` is visible on
Packagist. A stable Laravel release will use the normal single-package install
command after the PHP SDK is stable.

## Configure

Set an app-scoped runtime key in the server environment:

```dotenv
LATENTKIT_API_KEY=lk_...
```

Optional server-owned settings are `LATENTKIT_BASE_URL`, `LATENTKIT_TIMEOUT`,
and `LATENTKIT_RESPONSE_PROFILE`. Never commit the key or put it in client-side
Laravel configuration. The package reads these values through
`config/latentkit.php`, so `php artisan config:cache` remains supported.

Publish the configuration only when it needs to be customized:

```bash
php artisan vendor:publish --tag=latentkit-config
```

## Check the connection

```bash
php artisan latentkit:check
```

The command uses the PHP SDK's typed `/v1/me` context. It displays workspace,
app, assigned route, model count, credits, and the latest winning
provider/model without printing the API key, prompts, generated content, or
provider response bodies.

## Use the PHP SDK directly

Inject the framework-neutral client:

```php
use LatentKit\LatentKit;

final class SummarizeReport
{
    public function __construct(private readonly LatentKit $latentKit) {}

    public function __invoke(string $report): string
    {
        $response = $this->latentKit->chat->create([
            ['role' => 'user', 'content' => 'Summarize this report: '.$report],
        ]);

        return $response['content'];
    }
}
```

The facade exposes the same bound client:

```php
use LatentKit\Laravel\Facades\LatentKit;

$context = LatentKit::client()->me->retrieveContext();
```

## Use Laravel AI

The package registers the `latentkit` driver. Laravel AI uses one logical
model, `latentkit-route`; the integration consumes that label and never sends
it to the gateway. The app key's assigned published route selects the live
provider and model.

```php
use Laravel\Ai\AnonymousAgent;

$response = (new AnonymousAgent(
    instructions: 'Answer clearly and briefly.',
    messages: [],
    tools: [],
))->prompt(
    'Summarize the deployment status.',
    provider: 'latentkit',
);

echo $response->text;
```

Set `AI_PROVIDER=latentkit` if LatentKit should be Laravel AI's default text
provider. Passing any model other than `latentkit-route` fails before a network
request. Do not configure an upstream provider or model in Laravel.

Version `0.1.0-alpha.1` supports non-streaming, text-only prompts. Streaming,
tools, structured output, and attachments fail with an explicit unsupported
feature exception. The package does not use Laravel AI's OpenAI-compatible
driver because LatentKit does not expose `/v1/chat/completions` and remains
route-controlled.

## Queued generation

Laravel AI agents can use their normal `queue()` flow. The serialized job holds
the agent and prompt; the worker resolves the LatentKit provider and credential
from cached Laravel configuration when it handles the job. Do not serialize an
SDK client or API key into a custom job.

The LatentKit PHP SDK queue is enqueue-only and has no public result-retrieval
method. A Laravel job that needs a result must call the synchronous chat or
other supported SDK resource from its `handle()` method and store the result in
the application.

## Testing

Use the package facade fake for code that calls the PHP SDK directly:

```php
use LatentKit\Laravel\Facades\LatentKit;

$fake = LatentKit::fake([
    ['content' => 'Test summary'],
]);

$result = LatentKit::client()->chat->create([
    ['role' => 'user', 'content' => 'Summarize this'],
]);

$fake->assertChatSent(
    fn (array $request): bool => $request['messages'][0]['content'] === 'Summarize this',
);
```

Laravel AI agents retain their official `Agent::fake()` and assertion APIs.
Neither fake contacts LatentKit.

## Errors and privacy

Direct SDK calls expose normalized `ApiException` status, code, and request ID.
The Laravel AI driver wraps API failures in `LatentKitAiException`, whose public
message contains only the HTTP status and request ID. It does not include the
provider response body.

Never log API keys, prompts, generated content, or provider response bodies.
Use the LatentKit request ID when support needs to correlate a failed request.

## Development

```bash
composer validate --strict --no-check-publish
composer check
composer audit
scripts/smoke-clean-app.sh '^12.0'
scripts/smoke-clean-app.sh '^13.0'
```
