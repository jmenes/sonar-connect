<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_API {
    const NAMESPACE = 'sonar/v1';

    public static function register_routes() {
        // 1. PING & TELEMETRÍA
        register_rest_route(self::NAMESPACE, '/ping', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'endpoint_ping'],
            'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/logs', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'endpoint_logs'],
            'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
        ]);

        // 2. POSTS Y PÁGINAS (CON METAS SEO YOAST/RANKMATH)
        register_rest_route(self::NAMESPACE, '/posts', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'endpoint_get_posts'],
                'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/posts/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'endpoint_get_post'],
                'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'endpoint_update_post'],
                'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
            ],
        ]);

        // 3. CACHÉ PURGE
        register_rest_route(self::NAMESPACE, '/cache/purge', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'endpoint_purge_cache'],
            'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
        ]);

        // 4. REDIRECCIONES 301 / 410
        register_rest_route(self::NAMESPACE, '/redirects', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'endpoint_get_redirects'],
                'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'endpoint_save_redirect'],
                'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [__CLASS__, 'endpoint_delete_redirect'],
                'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
            ],
        ]);

        // 5. MEDIOS / BIBLIOTECA & OPTIMIZACIÓN WEBP
        register_rest_route(self::NAMESPACE, '/media', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'endpoint_get_media'],
                'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/media/(?P<id>\d+)', [
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'endpoint_update_media'],
                'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/media/replace', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'endpoint_replace_media'],
            'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
        ]);

        // 6. ENLACES INTERNOS
        register_rest_route(self::NAMESPACE, '/internal-links/insert', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'endpoint_insert_internal_link'],
            'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
        ]);

        // 7. ARCHIVOS (ROBOTS.TXT / ADS.TXT)
        register_rest_route(self::NAMESPACE, '/files/(?P<type>[a-zA-Z0-9_-]+)', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'endpoint_get_file'],
                'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'endpoint_save_file'],
                'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
            ],
        ]);

        // 8. SNIPPETS HEAD / BODY / FOOTER
        register_rest_route(self::NAMESPACE, '/snippets', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'endpoint_get_snippets'],
                'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'endpoint_save_snippets'],
                'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
            ],
        ]);

        // 9. LIMPIEZA BASE DE DATOS
        register_rest_route(self::NAMESPACE, '/database/cleanup', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'endpoint_db_cleanup'],
            'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
        ]);

        // 10. MODO MANTENIMIENTO
        register_rest_route(self::NAMESPACE, '/maintenance', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'endpoint_toggle_maintenance'],
            'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
        ]);

        // 11. WOOCOMMERCE
        register_rest_route(self::NAMESPACE, '/woocommerce/products', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'endpoint_wc_get_products'],
                'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/woocommerce/products/(?P<id>\d+)', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'endpoint_wc_update_product'],
            'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
        ]);

        // 12. CRO / EVENTOS STATS
        register_rest_route(self::NAMESPACE, '/events/stats', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'endpoint_get_event_stats'],
            'permission_callback' => ['Sonar_Connector_Auth', 'check_permission'],
        ]);
    }

    public static function endpoint_ping($request) {
        return rest_ensure_response(Sonar_Connector_Core::get_system_info());
    }

    public static function endpoint_logs($request) {
        return rest_ensure_response(Sonar_Connector_Core::get_recent_logs());
    }

    public static function endpoint_get_posts($request) {
        $per_page = (int) ($request->get_param('per_page') ?: 20);
        $page = (int) ($request->get_param('page') ?: 1);
        $search = $request->get_param('search') ?: '';
        $type = $request->get_param('type') ?: 'any';

        // Si es 'any', obtener todos los tipos de post públicos registrados en este WordPress
        if ($type === 'any') {
            $public_types = get_post_types(['public' => true, 'show_in_rest' => true], 'names');
            // Asegurar que post y page siempre estén incluidos
            $public_types['post'] = 'post';
            $public_types['page'] = 'page';
            // Excluir adjuntos multimedia del listado de contenidos
            unset($public_types['attachment']);
            $post_types = array_values($public_types);
        } else {
            $post_types = [$type];
        }

        $args = [
            'post_type' => $post_types,
            'post_status' => ['publish', 'draft', 'pending'],
            'posts_per_page' => $per_page,
            'paged' => $page,
            'orderby' => 'modified',
            'order' => 'DESC',
        ];

        if (!empty($search)) {
            $args['s'] = sanitize_text_field($search);
        }

        $query = new WP_Query($args);
        $posts = [];

        foreach ($query->posts as $p) {
            $seo_metas = Sonar_Connector_SEO::get_post_seo_metas($p->ID);
            $posts[] = [
                'id' => $p->ID,
                'title' => $p->post_title,
                'slug' => $p->post_name,
                'link' => get_permalink($p->ID),
                'excerpt' => !empty($p->post_excerpt) ? wp_strip_all_tags($p->post_excerpt) : '',
                'status' => $p->post_status,
                'type' => $p->post_type,
                'modified' => $p->post_modified,
                'seoMetas' => $seo_metas,
            ];
        }

        return rest_ensure_response([
            'posts' => $posts,
            'total' => $query->found_posts,
            'totalPages' => $query->max_num_pages,
            'currentPage' => $page,
        ]);
    }

    public static function endpoint_get_post($request) {
        $id = (int) $request['id'];
        $p = get_post($id);
        if (!$p) {
            return new WP_Error('not_found', 'Entrada no encontrada.', ['status' => 404]);
        }

        return rest_ensure_response([
            'id' => $p->ID,
            'title' => $p->post_title,
            'slug' => $p->post_name,
            'link' => get_permalink($p->ID),
            'status' => $p->post_status,
            'type' => $p->post_type,
            'content' => $p->post_content,
            'excerpt' => $p->post_excerpt,
            'modified' => $p->post_modified,
            'seoMetas' => Sonar_Connector_SEO::get_post_seo_metas($p->ID),
        ]);
    }

    public static function endpoint_update_post($request) {
        $id = (int) $request['id'];
        $params = $request->get_json_params();

        $post_data = ['ID' => $id];
        if (isset($params['title'])) $post_data['post_title'] = sanitize_text_field($params['title']);
        if (isset($params['slug'])) $post_data['post_name'] = sanitize_title($params['slug']);
        if (isset($params['status'])) $post_data['post_status'] = sanitize_text_field($params['status']);
        if (isset($params['content'])) $post_data['post_content'] = $params['content'];
        if (isset($params['excerpt'])) $post_data['post_excerpt'] = sanitize_textarea_field($params['excerpt']);

        if (count($post_data) > 1) {
            $updated = wp_update_post($post_data, true);
            if (is_wp_error($updated)) return $updated;
        }

        if (isset($params['seoMetas']) && is_array($params['seoMetas'])) {
            Sonar_Connector_SEO::update_post_seo_metas($id, $params['seoMetas']);
        }

        // Auto purga de caché para este post si se solicita
        if (!empty($params['purgeCache'])) {
            Sonar_Connector_Cache::purge($id);
        }

        return rest_ensure_response(self::endpoint_get_post($request));
    }

    public static function endpoint_purge_cache($request) {
        $params = $request->get_json_params() ?: [];
        $post_id = !empty($params['postId']) ? (int) $params['postId'] : null;
        return rest_ensure_response(Sonar_Connector_Cache::purge($post_id));
    }

    public static function endpoint_get_redirects($request) {
        return rest_ensure_response(Sonar_Connector_Redirects::get_all());
    }

    public static function endpoint_save_redirect($request) {
        $params = $request->get_json_params();
        if (empty($params['source']) || empty($params['target'])) {
            return new WP_Error('missing_params', 'Faltan parámetros source o target.', ['status' => 400]);
        }
        $code = !empty($params['code']) ? (int) $params['code'] : 301;
        $res = Sonar_Connector_Redirects::save_redirect($params['source'], $params['target'], $code);
        return rest_ensure_response($res);
    }

    public static function endpoint_delete_redirect($request) {
        $params = $request->get_json_params();
        if (empty($params['source'])) {
            return new WP_Error('missing_source', 'Falta el parámetro source a eliminar.', ['status' => 400]);
        }
        $res = Sonar_Connector_Redirects::delete_redirect($params['source']);
        return rest_ensure_response(['success' => $res]);
    }

    public static function endpoint_get_media($request) {
        $page = (int) ($request->get_param('page') ?: 1);
        $per_page = (int) ($request->get_param('per_page') ?: 100);
        $search = $request->get_param('search') ?: '';
        return rest_ensure_response(Sonar_Connector_Media::get_media_items($page, $per_page, $search));
    }

    public static function endpoint_update_media($request) {
        $id = (int) $request['id'];
        $params = $request->get_json_params() ?: [];
        return rest_ensure_response(Sonar_Connector_Media::update_media_item($id, $params));
    }

    public static function endpoint_replace_media($request) {
        $params = $request->get_json_params();
        if (empty($params['attachmentId']) || empty($params['fileBase64'])) {
            return new WP_Error('missing_params', 'Se requiere attachmentId y fileBase64.', ['status' => 400]);
        }
        $res = Sonar_Connector_Media::replace_attachment_file(
            (int) $params['attachmentId'],
            $params['fileBase64'],
            $params['newFilename'] ?? null
        );
        if (is_wp_error($res)) return $res;

        // Limpiar caché de post si se proporciona
        if (!empty($params['purgeCache'])) {
            Sonar_Connector_Cache::purge();
        }

        return rest_ensure_response($res);
    }

    public static function endpoint_insert_internal_link($request) {
        $params = $request->get_json_params();
        if (empty($params['postId']) || empty($params['anchorText']) || empty($params['targetUrl'])) {
            return new WP_Error('missing_params', 'Faltan postId, anchorText o targetUrl.', ['status' => 400]);
        }
        $res = Sonar_Connector_Internal_Links::insert_link(
            (int) $params['postId'],
            $params['anchorText'],
            $params['targetUrl']
        );
        return rest_ensure_response($res);
    }

    public static function endpoint_get_file($request) {
        return rest_ensure_response(Sonar_Connector_Files::get_file($request['type']));
    }

    public static function endpoint_save_file($request) {
        $params = $request->get_json_params();
        $content = $params['content'] ?? '';
        return rest_ensure_response(Sonar_Connector_Files::save_file($request['type'], $content));
    }

    public static function endpoint_get_snippets($request) {
        return rest_ensure_response(Sonar_Connector_Snippets::get_all());
    }

    public static function endpoint_save_snippets($request) {
        $params = $request->get_json_params();
        return rest_ensure_response(Sonar_Connector_Snippets::update_all($params));
    }

    public static function endpoint_db_cleanup($request) {
        return rest_ensure_response(Sonar_Connector_Database::cleanup());
    }

    public static function endpoint_toggle_maintenance($request) {
        $params = $request->get_json_params();
        $enabled = !empty($params['enabled']);
        $message = !empty($params['message']) ? sanitize_textarea_field($params['message']) : 'Sitio en mantenimiento temporal.';

        update_option('sonar_maintenance_mode', $enabled);
        update_option('sonar_maintenance_message', $message);

        return rest_ensure_response([
            'success' => true,
            'maintenanceMode' => $enabled,
            'message' => $message,
        ]);
    }

    public static function endpoint_wc_get_products($request) {
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 20;
        $search = $request->get_param('search') ?: '';
        return rest_ensure_response(Sonar_Connector_WooCommerce::get_products($page, $per_page, $search));
    }

    public static function endpoint_wc_update_product($request) {
        return rest_ensure_response(Sonar_Connector_WooCommerce::update_product((int) $request['id'], $request->get_json_params()));
    }

    public static function endpoint_get_event_stats($request) {
        return rest_ensure_response(Sonar_Connector_Tracker::get_stats());
    }
}

