<?php
/**
 * Plugin Name:       Vitalink Content Improver
 * Plugin URI:        https://github.com/vitalink/content-improver
 * Description:       Multi-provider AI content improvement for the WordPress block editor: improve, summarize, translate, and auto-generate alt text. Works with OpenAI, Anthropic, or self-hosted Ollama.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Jason G.
 * Author URI:        https://upwork.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vitalink-content-improver
 * Domain Path:       /languages
 * Update URI:        https://github.com/vitalink/content-improver
 *
 * @package Vitalink\ContentImprover
 */

declare(strict_types=1);

namespace Vitalink\ContentImprover;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'VITALINK_CI_VERSION', '0.1.0' );
define( 'VITALINK_CI_FILE', __FILE__ );
define( 'VITALINK_CI_PATH', plugin_dir_path( __FILE__ ) );
define( 'VITALINK_CI_URL', plugin_dir_url( __FILE__ ) );
define( 'VITALINK_CI_SLUG', 'vitalink-content-improver' );
define( 'VITALINK_CI_REST_NAMESPACE', 'vitalink-ci/v1' );
define( 'VITALINK_CI_CACHE_GROUP', 'vitalink_ci' );
define( 'VITALINK_CI_OPTION_PREFIX', 'vitalink_ci_' );

require_once VITALINK_CI_PATH . 'src/Support/Encryption.php';
require_once VITALINK_CI_PATH . 'src/Providers/ProviderException.php';
require_once VITALINK_CI_PATH . 'src/Providers/ProviderInterface.php';
require_once VITALINK_CI_PATH . 'src/Providers/OpenAIProvider.php';
require_once VITALINK_CI_PATH . 'src/Providers/AnthropicProvider.php';
require_once VITALINK_CI_PATH . 'src/Providers/OllamaProvider.php';
require_once VITALINK_CI_PATH . 'src/Providers/ProviderFactory.php';
require_once VITALINK_CI_PATH . 'src/Cache/ResponseCache.php';
require_once VITALINK_CI_PATH . 'src/Features/ContentImprover.php';
require_once VITALINK_CI_PATH . 'src/Features/Summarizer.php';
require_once VITALINK_CI_PATH . 'src/Features/Translator.php';
require_once VITALINK_CI_PATH . 'src/Features/AltTextGenerator.php';
require_once VITALINK_CI_PATH . 'src/Admin/SettingsPage.php';
require_once VITALINK_CI_PATH . 'src/Admin/GutenbergSidebar.php';
require_once VITALINK_CI_PATH . 'src/REST/ContentController.php';
require_once VITALINK_CI_PATH . 'src/CLI/ContentCommand.php';
require_once VITALINK_CI_PATH . 'src/Core/Plugin.php';
require_once VITALINK_CI_PATH . 'src/Core/Activator.php';

register_activation_hook( __FILE__, array( Activator::class, 'activate' ) );

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->boot();
	}
);
