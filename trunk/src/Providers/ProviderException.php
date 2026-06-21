<?php
/**
 * Provider exception — thrown by any Provider implementation on failure.
 *
 * Always carries a stable error code so the UI layer can render
 * actionable messages without parsing strings.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Providers;

use RuntimeException;

final class ProviderException extends RuntimeException {

	public const CODE_NOT_CONFIGURED  = 'not_configured';
	public const CODE_INVALID_REQUEST = 'invalid_request';
	public const CODE_AUTH            = 'auth_failed';
	public const CODE_RATE_LIMIT      = 'rate_limited';
	public const CODE_SERVER          = 'server_error';
	public const CODE_TIMEOUT         = 'timeout';
	public const CODE_NETWORK         = 'network';
	public const CODE_UNKNOWN         = 'unknown';

	private string $error_code;
	private ?int $http_status;

	public function __construct( string $message, string $error_code = self::CODE_UNKNOWN, ?int $http_status = null, ?\Throwable $previous = null ) {
		parent::__construct( $message, 0, $previous );
		$this->error_code  = $error_code;
		$this->http_status = $http_status;
	}

	public function get_error_code(): string {
		return $this->error_code;
	}

	public function get_http_status(): ?int {
		return $this->http_status;
	}
}
