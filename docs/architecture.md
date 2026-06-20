# Architecture

This document describes how the pieces of Vitalink Content Improver fit
together. It is the long-form counterpart to the diagram in the plugin
README.

## Layer diagram

```
┌─────────────────────────────────────────────────────────────┐
│  Gutenberg Sidebar (assets/js/sidebar.js)                    │
│  ── Improve / Summarize / Translate                         │
└──────────────────────────┬──────────────────────────────────┘
                           │ wp.apiFetch
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  REST API (src/REST/ContentController.php)                  │
│  /vitalink-ci/v1/{improve,summarize,translate,alt-text}     │
└──────────────────────────┬──────────────────────────────────┘
                           │ Feature classes
                           ▼
┌─────────────────────────────────────────────────────────────┐
│  Features (src/Features/*)                                  │
│  ── ContentImprover, Summarizer, Translator, AltTextGen     │
│                                                              │
│  Each feature:                                               │
│    1. Checks the ResponseCache.                              │
│    2. On miss, builds a prompt and calls the provider.       │
│    3. On success, stores the result in the cache.            │
└──────────┬───────────────────────────────────┬──────────────┘
           │                                   │
           ▼                                   ▼
┌────────────────────────┐         ┌────────────────────────┐
│  Cache                 │         │  Provider Layer         │
│  (src/Cache/Response   │         │  (src/Providers/*)      │
│   Cache.php)           │         │                         │
│  ── transient-backed   │         │  OpenAIProvider         │
│  ── TTL configurable   │         │  AnthropicProvider      │
└────────────────────────┘         │  OllamaProvider         │
                                   │  + custom via filter    │
                                   └────────────────────────┘
```

## Plugin lifecycle

```
plugins_loaded
  └── Plugin::instance()->boot()
        ├── register SettingsPage
        ├── register GutenbergSidebar
        │     └── enqueue_block_editor_assets → load sidebar.js
        │     └── rest_api_init → register ContentController routes
        ├── schedule vitalink_ci_daily_cleanup cron
        └── (if WP_CLI) register `wp vitalink ci` command
```

`vitalink_ci_init` is fired at the end of `boot()` so third-party code
can hook in after the plugin is fully loaded.

## Why a single provider abstraction

Most "AI for WordPress" plugins hard-code OpenAI in their business
logic. The first time a customer asks for Anthropic, the maintainer
shims it in with `if ( $provider === 'anthropic' )` branches that
multiply on every feature.

Vitalink's approach:

1. One `ProviderInterface` with four methods.
2. A `ProviderFactory` that builds the active provider from options.
3. Feature classes that depend on `ProviderInterface`, not on a
   concrete class.

This means adding a new feature (e.g. "Generate SEO title") does NOT
require touching any provider code. And adding a new provider does NOT
require touching any feature code.

## Cache strategy

The cache is keyed by `md5(json_encode([prompt, sorted_options]))`.
This means:

- The same prompt with the same options is a hit.
- Changing the model, temperature, or style produces a miss.
- Caching is shared across providers — switching from OpenAI to
  Ollama reuses cached responses where the prompt matches.

TTL is configurable per site (default 7 days). Disable for development
or for highly dynamic content.

## Extensibility points

See [hooks.md](./hooks.md) for the full list. The high-value ones:

- `vitalink_ci_register_providers` — add a custom provider.
- `vitalink_ci_request_options` — force temperature/model globally.
- `vitalink_ci_response` — post-process the model output.
- `vitalink_ci_before_request` / `vitalink_ci_after_request` — logging,
  analytics, rate limiting.
