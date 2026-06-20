=== Vitalink Content Improver ===
Contributors: vitalink
Tags: ai, openai, anthropic, ollama, gutenberg, content, alt text, translation
Requires at least: 6.4
Tested up to: 6.6
Requires PHP: 8.1
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Multi-provider AI content improvement for the WordPress block editor. Improve, summarize, translate, and generate alt text. Works with OpenAI, Anthropic, or self-hosted Ollama.

== Description ==

Vitalink Content Improver adds an AI sidebar to the WordPress block editor. Four features, one consistent UX, three AI providers.

**Features**

* **Improve** — Rewrite selected text. Three modes: clearer, shorter, more formal.
* **Summarize** — Condense a long post into 50, 150, or 300 words.
* **Translate** — Translate the post or selection to any language. Uses the site's language by default.
* **Alt Text** — Auto-generate alt text when you upload an image.

**Providers (your choice, switch any time)**

* **OpenAI** — GPT-4o, GPT-4o-mini, GPT-4 Turbo
* **Anthropic** — Claude Sonnet 4, Claude Opus 4
* **Ollama** — Run models locally. No data leaves your server. Free forever.

**Differentiators**

* **Multi-provider** — Most AI plugins only support OpenAI. We support three and growing.
* **Response caching** — Identical repeat requests return cached results. Zero extra API cost.
* **Self-hostable** — Ollama means no API key, no data leak, no subscription.
* **No markup** — Pay your AI provider directly. We charge nothing.
* **WordPress-native** — Gutenberg sidebar, REST API, WP-CLI, PHPUnit-tested.

**For developers**

* Hooks and filters documented in `docs/hooks.md`
* Provider interface lets you register your own provider (`vitalink_ci_register_providers`)
* PHPUnit + WP test framework suite included
* PHPCS-clean, PHPStan level 6+, type-safe throughout

== Installation ==

1. Upload the plugin to `wp-content/plugins/vitalink-content-improver/`
2. Activate through the **Plugins** screen
3. Go to **Settings → Vitalink** to configure your provider
4. Open any post in the block editor — the **Vitalink** sidebar appears

For local development with Ollama:

1. Install Ollama: `curl https://ollama.ai/install.sh | sh`
2. Pull a model: `ollama pull llama3.1`
3. Set the Ollama endpoint in Vitalink settings (default: `http://localhost:11434`)

== Frequently Asked Questions ==

= Do I need an OpenAI account? =

No. Vitalink works with OpenAI, Anthropic, or Ollama. Pick whichever fits your privacy and budget.

= Is my content sent to the AI provider? =

Yes, when you click an action button, the selected text is sent to the configured provider. Use Ollama if you need this to stay on your own server.

= Can I switch providers later? =

Yes. Settings → Vitalink → Provider. Existing cached responses survive provider switches.

= Does it work with the Classic Editor? =

No. Vitalink is built for the block editor (Gutenberg). The Classic Editor is not supported.

= Is there a paid version? =

No. The plugin is free, open source, and will stay that way.

== Screenshots ==

1. Vitalink sidebar in the block editor
2. Improve action: rewriting a paragraph
3. Settings page: provider configuration
4. Alt text generation on image upload

== Changelog ==

= 0.1.0 =
* Initial release
* Multi-provider support: OpenAI, Anthropic, Ollama
* Four features: Improve, Summarize, Translate, Alt Text
* Response caching
* Gutenberg sidebar
* REST API
* WP-CLI command
* PHPUnit + WP test framework suite

== Upgrade Notice ==

= 0.1.0 =
Initial release.
