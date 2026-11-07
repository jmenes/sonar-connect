<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_Core {
    public static function get_system_info() {
        global $wpdb;

        // Detección de plugins SEO
        $seo_plugin = 'none';
        if (defined('WPSEO_VERSION')) {
            $seo_plugin = 'yoast';
        } elseif (defined('RANK_MATH_VERSION')) {
            $seo_plugin = 'rank_math';
        } elseif (defined('SEOPRESS_VERSION')) {
            $seo_plugin = 'seopress';
        } elseif (defined('AIOSEO_VERSION')) {
            $seo_plugin = 'aioseo';
        }

        // Detección de plugins de caché
        $cache_plugins = [];
        if (defined('WP_ROCKET_VERSION')) $cache_plugins[] = 'wp-rocket';
        if (defined('LSCWP_V')) $cache_plugins[] = 'litespeed';
        if (defined('W3TC')) $cache_plugins[] = 'w3-total-cache';
        if (function_exists('wp_cache_clean_cache')) $cache_plugins[] = 'wp-super-cache';

        // Comprobar plugins con actualizaciones pendientes
        if (!function_exists('get_plugin_updates')) {
            require_once ABSPATH . 'wp-admin/includes/update.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $updates = get_plugin_updates();
        $pending_updates = is_array($updates) ? count($updates) : 0;

        return [
            'siteName' => get_bloginfo('name'),
            'siteUrl' => get_site_url(),
            'homeUrl' => get_home_url(),
            'adminEmail' => get_bloginfo('admin_email'),
            'wpVersion' => get_bloginfo('version'),
            'pluginVersion' => SONAR_CONNECTOR_VERSION,
            'phpVersion' => phpversion(),
            'memoryLimit' => ini_get('memory_limit'),
            'maxExecutionTime' => ini_get('max_execution_time'),
            'uploadMaxFilesize' => ini_get('upload_max_filesize'),
            'seoPlugin' => $seo_plugin,
            'cachePlugins' => $cache_plugins,
            'woocommerceActive' => class_exists('WooCommerce'),
            'pendingPluginUpdates' => $pending_updates,
            'maintenanceMode' => (bool) get_option('sonar_maintenance_mode', false),
            'timestamp' => current_time('mysql'),
        ];
    }

    public static function get_recent_logs() {
        $log_file = WP_CONTENT_DIR . '/debug.log';
        if (!file_exists($log_file) || !is_readable($log_file)) {
            return [
                'hasLogs' => false,
                'message' => 'No se encontró archivo debug.log o no tiene permisos de lectura.',
                'lines' => [],
            ];
        }

        // Leer las últimas 50 líneas de forma eficiente
        $lines = [];
        $file = new SplFileObject($log_file, 'r');
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();

        $start = max(0, $total_lines - 50);
        $file->seek($start);

        while (!$file->eof()) {
            $line = trim($file->current());
            if (!empty($line)) {
                $lines[] = $line;
            }
            $file->next();
        }

        return [
            'hasLogs' => true,
            'lines' => array_reverse($lines),
            'totalLines' => $total_lines,
        ];
    }
}

