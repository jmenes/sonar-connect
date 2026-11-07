<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_Updater {
    const PLUGIN_SLUG = 'sonar-connector';
    const PLUGIN_FILE = 'sonar-connector/sonar-connector.php';

    public static function init() {
        // Comprobar actualizaciones contra el servidor de Sonar
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_for_update']);
        // Información detallada del plugin en el modal de WP
        add_filter('plugins_api', [__CLASS__, 'plugin_info'], 20, 3);
        // Limpiar caché tras actualizar
        add_action('upgrader_process_complete', [__CLASS__, 'after_update'], 10, 2);
    }

    /**
     * Obtiene la URL donde consultar actualizaciones.
     * Prioridad:
     * 1. Constante SONAR_CONNECTOR_UPDATE_URL si está definida en wp-config.php
     * 2. URL del servidor Sonar guardada en sonar_server_origin (si es accesible y no es localhost)
     * 3. Fallback directo a GitHub Raw del repositorio oficial
     */
    private static function get_update_endpoint() {
        if (defined('SONAR_CONNECTOR_UPDATE_URL') && !empty(SONAR_CONNECTOR_UPDATE_URL)) {
            return SONAR_CONNECTOR_UPDATE_URL;
        }

        $server_origin = get_option('sonar_server_origin', '');
        // Si el origen de Sonar no es localhost o IP privada, se puede consultar directamente
        if (!empty($server_origin) && !preg_match('#//(localhost|127\.0\.0\.1|0\.0\.0\.0)#i', $server_origin)) {
            return rtrim($server_origin, '/') . '/api/plugins/sonar-connector/info';
        }

        // Fallback al repositorio oficial en GitHub (GitHub Raw)
        return 'https://raw.githubusercontent.com/jmenes/sonar-connect/main/info.json';
    }

    public static function check_for_update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $endpoint = self::get_update_endpoint();
        $response = wp_remote_get($endpoint, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $transient;
        }

        $info = json_decode(wp_remote_retrieve_body($response));
        if ($info && isset($info->version)) {
            if (version_compare(SONAR_CONNECTOR_VERSION, $info->version, '<')) {
                $package_url = $info->download_url;
                if (!preg_match('#^https?://#i', $package_url)) {
                    $package_url = rtrim($server_url, '/') . '/' . ltrim($package_url, '/');
                }

                $obj = new stdClass();
                $obj->slug = self::PLUGIN_SLUG;
                $obj->plugin = self::PLUGIN_FILE;
                $obj->new_version = $info->version;
                $obj->url = $info->author_profile ?? 'https://menes.studio';
                $obj->package = $package_url;
                $obj->tested = $info->tested ?? '6.7';
                $obj->requires_php = $info->requires_php ?? '7.4';

                $transient->response[self::PLUGIN_FILE] = $obj;
            }
        }

        return $transient;
    }

    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information' || !isset($args->slug) || $args->slug !== self::PLUGIN_SLUG) {
            return $result;
        }

        $endpoint = self::get_update_endpoint();
        $response = wp_remote_get($endpoint, [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/json'],
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return $result;
        }

        $info = json_decode(wp_remote_retrieve_body($response));
        if ($info) {
            $package_url = $info->download_url ?? '';
            if (!empty($package_url) && !preg_match('#^https?://#i', $package_url)) {
                $package_url = rtrim($server_url, '/') . '/' . ltrim($package_url, '/');
            }

            $res = new stdClass();
            $res->name = $info->name ?? 'Sonar Connector';
            $res->slug = self::PLUGIN_SLUG;
            $res->version = $info->version ?? SONAR_CONNECTOR_VERSION;
            $res->author = $info->author ?? 'menes.studio';
            $res->homepage = $info->author_profile ?? 'https://menes.studio';
            $res->download_link = $package_url;
            $res->tested = $info->tested ?? '6.7';
            $res->requires_php = $info->requires_php ?? '7.4';
            $res->sections = [
                'description' => $info->sections->description ?? '',
                'changelog' => $info->sections->changelog ?? '',
            ];

            return $res;
        }

        return $result;
    }

    public static function after_update($upgrader_object, $options) {
        if ($options['action'] === 'update' && $options['type'] === 'plugin') {
            if (isset($options['plugins']) && in_array(self::PLUGIN_FILE, $options['plugins'], true)) {
                delete_transient('update_plugins');
            }
        }
    }
}

