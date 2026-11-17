# Changelog

## Unreleased

- Require Laravel AI SDK 1.x (`laravel/ai` `^1.0`), verified on 1.0.0 and
  1.4.0. Laravel AI 0.9.x is no longer supported by the source;
  `0.1.0-alpha.1` remains the release for that line.
- Report token usage through Laravel AI's `TextUsage` (`inputTokens` and
  `outputTokens`), which replaced `promptTokens` and `completionTokens`.
- Keep the provider usable after Laravel AI restores it from a queue payload.
- Refresh the locked development dependencies, including Larastan 3.15,
  Pint 1.32, and Testbench 11.3.
- `LatentKitAiException` exposes `apiCode` and names it in the message, and
  `latentkit:check` prints it, so `KEY_DISABLED`, `KEY_BUDGET_EXHAUSTED`,
  `RATE_LIMITED`, and similar failures are distinguishable without the response
  body. Error message text is still withheld, and a value that does not look
  like an error code is not shown.
- Report cached input tokens and reasoning tokens on Laravel AI usage, leaving
  them null when LatentKit did not report them, and fall back to the top-level
  token counts when a response has no `usage` object.
- The testing fake's `chat->create()` accepts the `purpose` argument.

## 0.1.0-alpha.1 - 2026-07-15

- Add the auto-discovered LatentKit SDK binding and facade.
- Add `latentkit:check` with typed, secret-free connection and route status.
- Add the Laravel AI `latentkit` text provider with one logical
  `latentkit-route` model.
- Add safe request-ID exceptions and explicit unsupported-feature failures.
- Add a network-free direct-SDK fake and Laravel 12/13 package tests.
- Add Larastan level 7 analysis and clean Laravel 12/13 application smokes for
  package discovery, config caching, direct and Laravel AI calls, and a real
  database queue worker.
- Add dedicated-repository CI and approval-gated Packagist mirror automation.
