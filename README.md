# Vitalink Content Improver

> Multi-provider AI content improvement for the WordPress block editor.

Vitalink adds an AI sidebar to Gutenberg with four features — **Improve**,
**Summarize**, **Translate**, and **Alt Text** — and works with three AI
providers out of the box: **OpenAI**, **Anthropic**, or a self-hosted
**Ollama** instance.

**Differentiators:**

- **Multi-provider** — most AI plugins only support OpenAI. Vitalink
  supports three and growing, switchable any time.
- **Response caching** — identical repeat requests return cached results.
  Zero extra API cost on bulk operations.
- **Self-hostable** — Ollama means no API key, no data leak, no
  subscription. Run Llama 3.1, Mistral, or any other open model on your
  own server.
- **No markup** — pay your AI provider directly. We charge nothing.
- **WordPress-native** — Gutenberg sidebar, REST API, WP-CLI, PHPUnit
  + WP test framework.

## Quick start

```bash
# Clone
git clone https://github.com/vitalink/content-improver.git
cd content-improver

# Install PHP deps
composer install

# Install JS deps
npm install

# Build the editor sidebar bundle
npm run build

# Symlink into a WP install
ln -s ../../path/to/wp-content/plugins/vitalink-content-improver trunk
```

Then in WordPress admin: **Plugins → Activate Vitalink Content
Improver → Settings → Vitalink** to configure your provider.

## WP-CLI

```bash
wp vitalink ci improve "Some clunky sentence." --style=clearer
wp vitalink ci summarize "Long article body..." --length=150
wp vitalink ci translate "Hello." "Simplified Chinese"
wp vitalink ci alt-text 123
```

## REST API

```
POST /wp-json/vitalink-ci/v1/improve
POST /wp-json/vitalink-ci/v1/summarize
POST /wp-json/vitalink-ci/v1/translate
POST /wp-json/vitalink-ci/v1/alt-text
```

All endpoints require `edit_posts` capability and a valid `X-WP-Nonce`.

## Documentation

- [Architecture](./docs/architecture.md)
- [Hooks & filters reference](./docs/hooks.md)
- [Authoring a custom provider](./docs/providers.md)

## Requirements

- WordPress 6.4 or later
- PHP 8.1 or later
- For the editor sidebar: a modern browser
- For self-hosted mode: an Ollama installation (https://ollama.ai)

## License

GPL v2 or later. See [LICENSE.txt](./LICENSE.txt).

## Brand

Vitalink is a family of open-source WordPress plugins. See the
[brand guidelines](../BRAND.md) for naming conventions, shared
architecture, and the plugin roadmap.
