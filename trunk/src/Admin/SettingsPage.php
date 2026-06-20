<?php
/**
 * Settings page — Vitalink configuration.
 *
 * Registers the Settings API page, handles form submission, encrypts
 * API keys before storage, and exposes a "Test connection" button.
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover\Admin;

use Vitalink\ContentImprover\Providers\ProviderFactory;
use Vitalink\ContentImprover\Support\Encryption;

final class SettingsPage {

	public const OPTION_GROUP = 'vitalink_ci_settings';
	public const PAGE_SLUG    = 'vitalink-content-improver';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_post_vitalink_ci_test', array( $this, 'handle_test_request' ) );
		add_action( 'admin_post_vitalink_ci_clear_cache', array( $this, 'handle_clear_cache' ) );
	}

	public function add_menu(): void {
		add_options_page(
			__( 'Vitalink Settings', 'vitalink-content-improver' ),
			__( 'Vitalink', 'vitalink-content-improver' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function register_settings(): void {
		$register = function ( string $name, array $args = array() ): void {
			register_setting( self::OPTION_GROUP, $name, $args );
		};

		$register( 'vitalink_ci_provider', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_key',
			'default'           => 'openai',
		) );

		$register( 'vitalink_ci_openai_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_secret' ),
			'default'           => '',
		) );

		$register( 'vitalink_ci_openai_model', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'gpt-4o-mini',
		) );

		$register( 'vitalink_ci_anthropic_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_secret' ),
			'default'           => '',
		) );

		$register( 'vitalink_ci_anthropic_model', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'claude-sonnet-4-5',
		) );

		$register( 'vitalink_ci_ollama_base_url', array(
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => 'http://localhost:11434',
		) );

		$register( 'vitalink_ci_ollama_model', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'llama3.1',
		) );

		$register( 'vitalink_ci_cache_enabled', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'on',
		) );

		$register( 'vitalink_ci_cache_ttl', array(
			'type'              => 'integer',
			'sanitize_callback' => 'absint',
			'default'           => DAY_IN_SECONDS * 7,
		) );

		$register( 'vitalink_ci_default_target_language', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'English',
		) );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'vitalink-content-improver' ) );
		}

		$provider_id = ProviderFactory::get_active_provider_id();
		$providers   = ProviderFactory::list_providers();
		$action_url  = admin_url( 'options.php' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Vitalink Settings', 'vitalink-content-improver' ); ?></h1>
			<p><?php esc_html_e( 'Connect your AI provider. Switch any time — your existing cached responses survive provider changes.', 'vitalink-content-improver' ); ?></p>

			<form method="post" action="<?php echo esc_url( $action_url ); ?>">
				<?php settings_fields( self::OPTION_GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Provider', 'vitalink-content-improver' ); ?></th>
						<td>
							<select name="vitalink_ci_provider">
								<?php foreach ( $providers as $p ) : ?>
									<option value="<?php echo esc_attr( $p['id'] ); ?>" <?php selected( $provider_id, $p['id'] ); ?>>
										<?php echo esc_html( $p['label'] ); ?>
										<?php echo $p['configured'] ? '✓' : '— not configured'; ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'OpenAI', 'vitalink-content-improver' ); ?></h2>
				<table class="form-table">
					<tr>
						<th>API Key</th>
						<td><input type="password" name="vitalink_ci_openai_api_key" value="" placeholder="<?php esc_attr_e( 'sk-...', 'vitalink-content-improver' ); ?>" class="regular-text" autocomplete="off" /></td>
					</tr>
					<tr>
						<th>Model</th>
						<td>
							<select name="vitalink_ci_openai_model">
								<?php foreach ( ( new \Vitalink\ContentImprover\Providers\OpenAIProvider() )->get_available_models() as $m ) : ?>
									<option value="<?php echo esc_attr( $m ); ?>" <?php selected( get_option( 'vitalink_ci_openai_model' ), $m ); ?>><?php echo esc_html( $m ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Anthropic', 'vitalink-content-improver' ); ?></h2>
				<table class="form-table">
					<tr>
						<th>API Key</th>
						<td><input type="password" name="vitalink_ci_anthropic_api_key" value="" placeholder="<?php esc_attr_e( 'sk-ant-...', 'vitalink-content-improver' ); ?>" class="regular-text" autocomplete="off" /></td>
					</tr>
					<tr>
						<th>Model</th>
						<td>
							<select name="vitalink_ci_anthropic_model">
								<?php foreach ( ( new \Vitalink\ContentImprover\Providers\AnthropicProvider() )->get_available_models() as $m ) : ?>
									<option value="<?php echo esc_attr( $m ); ?>" <?php selected( get_option( 'vitalink_ci_anthropic_model' ), $m ); ?>><?php echo esc_html( $m ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Ollama (self-hosted)', 'vitalink-content-improver' ); ?></h2>
				<table class="form-table">
					<tr>
						<th>Base URL</th>
						<td><input type="url" name="vitalink_ci_ollama_base_url" value="<?php echo esc_attr( get_option( 'vitalink_ci_ollama_base_url' ) ); ?>" class="regular-text" /></td>
					</tr>
					<tr>
						<th>Model</th>
						<td>
							<select name="vitalink_ci_ollama_model">
								<?php foreach ( ( new \Vitalink\ContentImprover\Providers\OllamaProvider() )->get_available_models() as $m ) : ?>
									<option value="<?php echo esc_attr( $m ); ?>" <?php selected( get_option( 'vitalink_ci_ollama_model' ), $m ); ?>><?php echo esc_html( $m ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Cache &amp; Defaults', 'vitalink-content-improver' ); ?></h2>
				<table class="form-table">
					<tr>
						<th>Response cache</th>
						<td>
							<label><input type="radio" name="vitalink_ci_cache_enabled" value="on" <?php checked( get_option( 'vitalink_ci_cache_enabled', 'on' ), 'on' ); ?>> On</label>
							<label><input type="radio" name="vitalink_ci_cache_enabled" value="off" <?php checked( get_option( 'vitalink_ci_cache_enabled', 'on' ), 'off' ); ?>> Off</label>
						</td>
					</tr>
					<tr>
						<th>Cache TTL (seconds)</th>
						<td><input type="number" name="vitalink_ci_cache_ttl" value="<?php echo esc_attr( get_option( 'vitalink_ci_cache_ttl' ) ); ?>" min="60" /></td>
					</tr>
					<tr>
						<th>Default translation target</th>
						<td><input type="text" name="vitalink_ci_default_target_language" value="<?php echo esc_attr( get_option( 'vitalink_ci_default_target_language' ) ); ?>" class="regular-text" /></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Maintenance', 'vitalink-content-improver' ); ?></h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
				<input type="hidden" name="action" value="vitalink_ci_test" />
				<?php wp_nonce_field( 'vitalink_ci_test' ); ?>
				<?php submit_button( __( 'Test connection', 'vitalink-content-improver' ), 'secondary', 'submit', false ); ?>
			</form>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
				<input type="hidden" name="action" value="vitalink_ci_clear_cache" />
				<?php wp_nonce_field( 'vitalink_ci_clear_cache' ); ?>
				<?php submit_button( __( 'Clear cache', 'vitalink-content-improver' ), 'secondary', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Sanitize callback for secret option. Encrypts before storage.
	 */
	public function sanitize_secret( $value ) {
		$value = (string) $value;
		if ( '' === $value ) {
			// Empty submit means "leave existing value".
			return get_option( 'vitalink_ci_openai_api_key', '' );
		}
		$cipher = Encryption::encrypt( $value );
		return false === $cipher ? '' : $cipher;
	}

	public function handle_test_request(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'vitalink-content-improver' ) );
		}
		check_admin_referer( 'vitalink_ci_test' );

		try {
			$provider = ProviderFactory::create( ProviderFactory::get_active_provider_id() );
			$reply    = $provider->complete( 'Reply with the single word: pong', array( 'max_tokens' => 5 ) );
			add_settings_error(
				'vitalink_ci',
				'vitalink_ci_test_ok',
				sprintf( /* translators: %s provider reply */ __( 'Connection OK. Provider replied: %s', 'vitalink-content-improver' ), esc_html( $reply ) ),
				'success'
			);
		} catch ( \Throwable $e ) {
			add_settings_error(
				'vitalink_ci',
				'vitalink_ci_test_fail',
				sprintf( /* translators: %s error message */ __( 'Connection failed: %s', 'vitalink-content-improver' ), esc_html( $e->getMessage() ) ),
				'error'
			);
		}
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	public function handle_clear_cache(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Forbidden', 'vitalink-content-improver' ) );
		}
		check_admin_referer( 'vitalink_ci_clear_cache' );
		$cache = new \Vitalink\ContentImprover\Cache\ResponseCache();
		$count = $cache->flush();
		add_settings_error(
			'vitalink_ci',
			'vitalink_ci_cleared',
			sprintf( /* translators: %d number of entries */ __( 'Cleared %d cache entries.', 'vitalink-content-improver' ), (int) $count ),
			'updated'
		);
		set_transient( 'settings_errors', get_settings_errors(), 30 );
		wp_safe_redirect( admin_url( 'options-general.php?page=' . self::PAGE_SLUG ) );
		exit;
	}
}
