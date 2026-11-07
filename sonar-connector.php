<?php
/**
 * Plugin Name: Sonar Connector
 * Plugin URI: https://menes.studio
 * Description: Conector oficial para la suite Sonar. Permite la sincronización segura de contenidos, optimización SEO, purgado de caché, gestión de redirecciones y telemetría de rendimiento.
 * Version: 1.0.2
 * Author: menes.studio
 * Author URI: https://menes.studio
 * License: GPL v2 or later
 * Text Domain: sonar-connector
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('SONAR_CONNECTOR_VERSION', '1.0.2');
define('SONAR_CONNECTOR_PATH', plugin_dir_path(__FILE__));
define('SONAR_CONNECTOR_URL', plugin_dir_url(__FILE__));

// Cargar módulos internos
require_once SONAR_CONNECTOR_PATH . 'includes/class-auth.php';
require_once SONAR_CONNECTOR_PATH . 'includes/class-core.php';
require_once SONAR_CONNECTOR_PATH . 'includes/class-seo.php';
require_once SONAR_CONNECTOR_PATH . 'includes/class-cache.php';
require_once SONAR_CONNECTOR_PATH . 'includes/class-redirects.php';
require_once SONAR_CONNECTOR_PATH . 'includes/class-media.php';
require_once SONAR_CONNECTOR_PATH . 'includes/class-snippets.php';
require_once SONAR_CONNECTOR_PATH . 'includes/class-files.php';
require_once SONAR_CONNECTOR_PATH . 'includes/class-database.php';
require_once SONAR_CONNECTOR_PATH . 'includes/class-woocommerce.php';
require_once SONAR_CONNECTOR_PATH . 'includes/class-tracker.php';
require_once SONAR_CONNECTOR_PATH . 'includes/class-internal-links.php';
require_once SONAR_CONNECTOR_PATH . 'includes/class-updater.php';
require_once SONAR_CONNECTOR_PATH . 'includes/class-api.php';

/**
 * Inicialización del plugin
 */
class Sonar_Connector_Plugin {
    private static $instance = null;

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Inicializar API REST
        add_action('rest_api_init', ['Sonar_Connector_API', 'register_routes']);

        // Inyectar Snippets de código y Tracker CRO
        add_action('wp_head', ['Sonar_Connector_Snippets', 'inject_head'], 99);
        add_action('wp_body_open', ['Sonar_Connector_Snippets', 'inject_body'], 1);
        add_action('wp_footer', ['Sonar_Connector_Snippets', 'inject_footer'], 99);
        add_action('wp_footer', ['Sonar_Connector_Tracker', 'inject_frontend_script'], 100);

        // Inicializar redirecciones gestionadas por Sonar
        Sonar_Connector_Redirects::init_hooks();

        // Inicializar comprobación automática de actualizaciones desde Sonar
        Sonar_Connector_Updater::init();

        // Hook para modo mantenimiento si está activo
        add_action('template_redirect', [$this, 'check_maintenance_mode']);
    }

    public function check_maintenance_mode() {
        if (is_admin() || current_user_can('manage_options')) {
            return;
        }

        if (get_option('sonar_maintenance_mode', false)) {
            $msg = get_option('sonar_maintenance_message', 'Sitio en mantenimiento temporal por mejoras técnicas. Volvemos enseguida.');
            wp_die($msg, 'Sitio en Mantenimiento', ['response' => 503]);
        }
    }
}

// Arrancar plugin
Sonar_Connector_Plugin::get_instance();
