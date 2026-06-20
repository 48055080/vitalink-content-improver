# Changelog

All notable changes to Vitalink Content Improver will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-06-21

### Added

- Initial release of Vitalink Content Improver
- Four content-improvement features:
  - **Improve** — rewrite selected text (clearer / shorter / more formal)
  - **Summarize** — condense long posts (50 / 150 / 300 words)
  - **Translate** — translate post or selection to any language
  - **Alt Text** — auto-generate alt text for uploaded images
- Three AI providers out of the box:
  - **OpenAI** (GPT-4o, GPT-4o-mini, GPT-4 Turbo)
  - **Anthropic** (Claude Sonnet 4, Claude Opus 4)
  - **Ollama** (self-hosted open models — Llama 3.1, Mistral, etc.)
- Gutenberg sidebar UI with the same UX across all four features
- Response caching (1h TTL, opt-in) — repeat requests cost zero API calls
- Encrypted API key storage using libsodium with OpenSSL fallback
- WP-CLI commands: `wp vitalink ci {improve,summarize,translate,alt-text}`
- REST API endpoints: `POST /wp-json/vitalink-ci/v1/{feature}`
- Provider interface + factory, custom providers via `vitalink_ci_register_providers` filter
- PHPUnit + WP test framework setup
- PHPCS (WordPress coding standards) + PHPStan static analysis
- CI matrix: PHP 8.1 / 8.2 / 8.3 × WordPress 6.4 / 6.5 / 6.6
- Documentation: architecture overview, hooks & filters reference, custom provider guide

[0.1.0]: https://github.com/48055080/vitalink-content-improver/releases/tag/v0.1.0