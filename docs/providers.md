# Provider Authoring Guide

This document explains how to add a new AI provider to Vitalink Content
Improver. Adding a provider is intentionally a small surface — one class
and one filter call.

## The `ProviderInterface` contract

Every provider implements:

```php
namespace MyPlugin\Providers;

use Vitalink\ContentImprover\Providers\ProviderInterface;
use Vitalink\ContentImprover\Providers\ProviderException;

final class MistralProvider implements ProviderInterface {

    public function __construct( private array $config = [] ) {}

    public function get_id(): string {
        return 'mistral';
    }

    public function get_label(): string {
        return 'Mistral AI';
    }

    public function is_configured(): bool {
        return ! empty( $this->config['api_key'] ?? get_option( 'mistral_api_key' ) );
    }

    public function get_available_models(): array {
        return [ 'mistral-large-latest', 'mistral-small-latest', 'codestral-latest' ];
    }

    public function complete( string $prompt, array $options = [] ): string {
        if ( ! $this->is_configured() ) {
            throw new ProviderException( 'Mistral API key missing.', ProviderException::CODE_NOT_CONFIGURED );
        }

        $response = wp_remote_post( 'https://api.mistral.ai/v1/chat/completions', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . ( $this->config['api_key'] ?? get_option( 'mistral_api_key' ) ),
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'       => $options['model'] ?? 'mistral-small-latest',
                'temperature' => $options['temperature'] ?? 0.7,
                'max_tokens'  => $options['max_tokens'] ?? 1024,
                'messages'    => [
                    [ 'role' => 'system', 'content' => $options['system'] ?? 'You are a helpful assistant.' ],
                    [ 'role' => 'user',   'content' => $prompt ],
                ],
            ] ),
        ] );

        if ( is_wp_error( $response ) ) {
            throw new ProviderException( $response->get_error_message(), ProviderException::CODE_NETWORK, null, $response );
        }

        $status = (int) wp_remote_retrieve_response_code( $response );
        $body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );

        if ( $status >= 400 ) {
            throw new ProviderException(
                sprintf( 'Mistral error (%d): %s', $status, wp_remote_retrieve_body( $response ) ),
                $this->map_status( $status ),
                $status
            );
        }

        return (string) ( $body['choices'][0]['message']['content'] ?? '' );
    }

    public function stream( string $prompt, array $options = [] ): \Generator {
        yield $this->complete( $prompt, $options );
    }

    private function map_status( int $status ): string {
        return match ( true ) {
            401 === $status, 403 === $status => ProviderException::CODE_AUTH,
            429 === $status                  => ProviderException::CODE_RATE_LIMIT,
            $status >= 500                   => ProviderException::CODE_SERVER,
            default                          => ProviderException::CODE_INVALID_REQUEST,
        };
    }
}
```

## Registering the provider

```php
add_filter( 'vitalink_ci_register_providers', function ( array $map ): array {
    $map['mistral'] = \MyPlugin\Providers\MistralProvider::class;
    return $map;
} );
```

That's it. Vitalink will:

1. Show the provider in the settings dropdown.
2. Encrypt the API key when the user submits the form.
3. Construct your provider with the right config on every call.
4. Map exceptions to user-facing error messages in the sidebar.

## Error codes you should use

Always throw `ProviderException` with a stable code from
`ProviderException::CODE_*`. The UI layer renders different toasts based on
the code, so a clear contract matters.

| Code | When |
|---|---|
| `not_configured` | API key missing, or required config absent |
| `invalid_request` | Bad prompt, model not found, etc. |
| `auth_failed` | 401, 403 |
| `rate_limited` | 429 |
| `server_error` | 5xx |
| `timeout` | Request exceeded timeout |
| `network` | DNS / connection error |
| `unknown` | Catch-all |

## Testing your provider

Use a mock that implements `ProviderInterface` and inject it into the
feature class:

```php
use PHPUnit\Framework\TestCase;
use Vitalink\ContentImprover\Features\ContentImprover;
use Vitalink\ContentImprover\Providers\ProviderInterface;

final class FakeProvider implements ProviderInterface {
    public function __construct( private string $reply ) {}
    public function get_id(): string { return 'fake'; }
    public function get_label(): string { return 'Fake'; }
    public function is_configured(): bool { return true; }
    public function get_available_models(): array { return [ 'fake' ]; }
    public function complete( string $p, array $o = [] ): string { return $this->reply; }
    public function stream( string $p, array $o = [] ): \Generator { yield $this->reply; }
}

final class ContentImproverTest extends TestCase {
    public function test_improve_uses_provider(): void {
        $feature = new ContentImprover( null, new FakeProvider( 'Cleaner text.' ) );
        $this->assertSame( 'Cleaner text.', $feature->improve( 'messy text' ) );
    }
}
```
