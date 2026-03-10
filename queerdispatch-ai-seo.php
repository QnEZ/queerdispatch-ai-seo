<?php
/**
 * Plugin Name: QueerDispatch AI SEO
 * Plugin URI:  https://queerdispatch.org/
 * Description: AI-assisted SEO, social metadata, excerpts, internal-link suggestions, disclosure helpers, and social copy tools for QueerDispatch.
 * Version:     0.8.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author:      OpenAI / QueerDispatch
 * Text Domain: queerdispatch-ai-seo
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (version_compare(PHP_VERSION, '8.1', '<')) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p>' . esc_html__('QueerDispatch AI SEO requires PHP 8.1 or newer.', 'queerdispatch-ai-seo') . '</p></div>';
    });
    return;
}

if (! defined('QD_AI_SEO_VERSION')) {
    define('QD_AI_SEO_VERSION', '0.8.0');
}

if (! defined('QD_AI_SEO_FILE')) {
    define('QD_AI_SEO_FILE', __FILE__);
}

if (! defined('QD_AI_SEO_PATH')) {
    define('QD_AI_SEO_PATH', plugin_dir_path(__FILE__));
}

if (! defined('QD_AI_SEO_URL')) {
    define('QD_AI_SEO_URL', plugin_dir_url(__FILE__));
}

require_once QD_AI_SEO_PATH . 'includes/class-plugin.php';

\QueerDispatch\AISEO\Plugin::boot();
