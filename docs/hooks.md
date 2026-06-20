# Hooks & Filters Reference

Vitalink exposes a small, stable set of hooks and filters. If you find
yourself needing more, open an issue before extending a class — the public
surface is the API.

## Actions

### `vitalink_ci_init`
Fired after the plugin is fully loaded. Use for feature module init.
Args: none.

```php
add_action( 'vitalink_ci_init', function (): void {
    // Your code.
} );
```

### `vitalink_ci_daily_cleanup`
Fired once per day. Receives the active `ResponseCache` instance.
Args: `ResponseCache $cache`.

```php
add_action( 'vitalink_ci_daily_cleanup', function ( ResponseCache $cache ): void {
    // Custom cleanup logic.
} );
```

### `vitalink_ci_before_request`
Fired right before a provider call. Args: `string $prompt`, `array $options`.
Use for logging, rate limiting, or test mocking.

```php
add_action( 'vitalink_ci_before_request', function ( string $prompt, array $options ): void {
    error_log( sprintf( '[vitalink] prompt: %s chars', strlen( $prompt ) ) );
}, 10, 2 );
```

### `vitalink_ci_after_request`
Fired after a successful provider call. Args: `string $prompt`, `array $options`, `string $response`, `float $duration_ms`.

```php
add_action( 'vitalink_ci_after_request', function ( $prompt, $options, $response, $duration_ms ): void {
    // Send to your analytics.
}, 10, 4 );
```

## Filters

### `vitalink_ci_register_providers`
Register custom AI provider implementations.

```php
add_filter( 'vitalink_ci_register_providers', function ( array $map ): array {
    $map['mistral'] = MyMistralProvider::class;
    return $map;
} );
```

The provider class must implement `Vitalink\ContentImprover\Providers\ProviderInterface`.

### `vitalink_ci_request_options`
Modify the options bag passed to the provider.

```php
add_filter( 'vitalink_ci_request_options', function ( array $options ): array {
    $options['temperature'] = 0.3; // Force lower temperature everywhere.
    return $options;
} );
```

### `vitalink_ci_response`
Modify the response before it is returned to the caller (also before caching).

```php
add_filter( 'vitalink_ci_response', function ( string $text, string $prompt ): string {
    return trim( $text );
}, 10, 2 );
```

### `vitalink_ci_default_target_language`
Override the default translation target language.

```php
add_filter( 'vitalink_ci_default_target_language', function (): string {
    return 'Simplified Chinese';
} );
```

### `vitalink_ci_cache_ttl`
Override the cache TTL for a specific request.

```php
add_filter( 'vitalink_ci_cache_ttl', function ( int $ttl, string $prompt, array $options ): int {
    if ( str_contains( $prompt, 'translate' ) ) {
        return 0; // Don't cache translations.
    }
    return $ttl;
}, 10, 3 );
```
