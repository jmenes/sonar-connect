<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_Redirects {
    const OPTION_KEY = 'sonar_custom_redirects';

    public static function init_hooks() {
        add_action('template_redirect', [__CLASS__, 'handle_redirections'], 1);
    }

    /**
     * Obtiene el listado de redirecciones
     */
    public static function get_all() {
        $redirects = get_option(self::OPTION_KEY, []);
        return is_array($redirects) ? $redirects : [];
    }

    /**
     * Añade o actualiza una regla de redirección
     */
    public static function save_redirect($source_path, $target_url, $status_code = 301) {
        $redirects = self::get_all();
        $source_clean = '/' . ltrim(parse_url($source_path, PHP_URL_PATH), '/');

        $redirects[$source_clean] = [
            'source' => $source_clean,
            'target' => esc_url_raw($target_url),
            'code' => in_array($status_code, [301, 302, 410]) ? (int) $status_code : 301,
            'createdAt' => current_time('mysql'),
        ];

        update_option(self::OPTION_KEY, $redirects);
        return $redirects[$source_clean];
    }

    /**
     * Elimina una redirección
     */
    public static function delete_redirect($source_path) {
        $redirects = self::get_all();
        $source_clean = '/' . ltrim(parse_url($source_path, PHP_URL_PATH), '/');

        if (isset($redirects[$source_clean])) {
            unset($redirects[$source_clean]);
            update_option(self::OPTION_KEY, $redirects);
            return true;
        }

        return false;
    }

    /**
     * Ejecuta la redirección si la URL coincide
     */
    public static function handle_redirections() {
        $current_path = '/' . ltrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $redirects = self::get_all();

        if (isset($redirects[$current_path])) {
            $rule = $redirects[$current_path];
            if ($rule['code'] === 410) {
                status_header(410);
                nocache_headers();
                include(get_query_template('404'));
                exit;
            } else {
                wp_redirect($rule['target'], $rule['code']);
                exit;
            }
        }
    }
}

Sonar_Connector_Redirects::init_hooks();

