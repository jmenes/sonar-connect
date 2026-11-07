<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_Media {
    /**
     * Reemplaza el archivo físico de un adjunto con un archivo WebP optimizado
     */
    public static function replace_attachment_file($attachment_id, $file_base64, $new_filename = null) {
        $attachment = get_post($attachment_id);
        if (!$attachment || $attachment->post_type !== 'attachment') {
            return new WP_Error('invalid_attachment', 'El ID proporcionado no corresponde a un archivo adjunto.', ['status' => 404]);
        }

        $decoded_data = base64_decode($file_base64);
        if (!$decoded_data) {
            return new WP_Error('invalid_base64', 'Los datos del archivo base64 no son válidos.', ['status' => 400]);
        }

        $current_file = get_attached_file($attachment_id);
        if (!$current_file || !file_exists($current_file)) {
            return new WP_Error('file_not_found', 'No se encontró el archivo original en el servidor.', ['status' => 404]);
        }

        $dir = dirname($current_file);
        $old_filename = basename($current_file);

        if (empty($new_filename)) {
            $new_filename = pathinfo($old_filename, PATHINFO_FILENAME) . '.webp';
        }

        $target_path = $dir . '/' . sanitize_file_name($new_filename);

        // Guardar nuevo archivo
        $written = file_put_contents($target_path, $decoded_data);
        if ($written === false) {
            return new WP_Error('write_failed', 'No se pudo escribir el archivo optimizado en disco.', ['status' => 500]);
        }

        // Si el archivo cambió de nombre/extensión, limpiar miniaturas antiguas y actualizar metadatos
        if ($target_path !== $current_file) {
            // Limpiar miniaturas del archivo anterior para no dejar basura en uploads
            $old_metadata = wp_get_attachment_metadata($attachment_id);
            if (!empty($old_metadata['sizes']) && is_array($old_metadata['sizes'])) {
                foreach ($old_metadata['sizes'] as $size_info) {
                    if (!empty($size_info['file'])) {
                        $old_thumb_path = $dir . '/' . $size_info['file'];
                        if (file_exists($old_thumb_path) && $old_thumb_path !== $target_path) {
                            @unlink($old_thumb_path);
                        }
                    }
                }
            }

            // Eliminar archivo original antiguo
            if (file_exists($current_file) && $current_file !== $target_path) {
                @unlink($current_file);
            }

            update_attached_file($attachment_id, $target_path);
            
            // Actualizar mime type al nuevo formato
            $ext = strtolower(pathinfo($target_path, PATHINFO_EXTENSION));
            $mime_types = [
                'webp' => 'image/webp',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'png'  => 'image/png',
            ];
            $new_mime = isset($mime_types[$ext]) ? $mime_types[$ext] : 'image/webp';

            wp_update_post([
                'ID' => $attachment_id,
                'post_mime_type' => $new_mime,
            ]);
        }

        // Regenerar metadatos y todas las miniaturas registradas en el tema (thumbnail, medium, large, etc.)
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $metadata = wp_generate_attachment_metadata($attachment_id, $target_path);
        if (!is_wp_error($metadata) && !empty($metadata)) {
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        // Actualizar URL en post_content, _elementor_data y CSS generado por Elementor
        if ($target_path !== $current_file) {
            $old_url = wp_get_attachment_url($attachment_id); // Antes de que cambie o usando el path anterior
            // Si $old_url ya apuntaba al nuevo o era el original:
            $upload_dir = wp_upload_dir();
            $old_url_calc = $upload_dir['baseurl'] . '/' . _wp_relative_upload_path($current_file);
            $new_url_calc = wp_get_attachment_url($attachment_id);

            self::replace_url_in_elementor_and_posts($old_url_calc, $new_url_calc, $attachment_id);
        }

        // Regenerar CSS de Elementor y limpiar caché de WordPress
        self::regenerate_elementor_css();
        clean_post_cache($attachment_id);
        if (class_exists('Sonar_Connector_Cache')) {
            Sonar_Connector_Cache::purge();
        }

        return [
            'success' => true,
            'id' => $attachment_id,
            'newFile' => basename($target_path),
            'newUrl' => wp_get_attachment_url($attachment_id),
            'newSize' => file_exists($target_path) ? filesize($target_path) : 0,
            'mimeType' => get_post_mime_type($attachment_id),
            'sizes' => !empty($metadata['sizes']) ? array_keys($metadata['sizes']) : [],
        ];
    }

    /**
     * Reemplaza las URLs en contenidos y metadatos JSON de Elementor (_elementor_data)
     */
    public static function replace_url_in_elementor_and_posts($old_url, $new_url, $attachment_id) {
        global $wpdb;

        if (empty($old_url) || empty($new_url) || $old_url === $new_url) {
            return;
        }

        // Variaciones de URL (con http, https, con barras escapadas para JSON \/ y sin extensión)
        $old_clean = preg_replace('/^https?:/', '', $old_url);
        $new_clean = preg_replace('/^https?:/', '', $new_url);

        $old_escaped_json = str_replace('/', '\/', $old_url);
        $new_escaped_json = str_replace('/', '\/', $new_url);

        // 1. Reemplazar en post_content estándar
        $wpdb->query($wpdb->prepare(
            "UPDATE {$wpdb->posts} SET post_content = REPLACE(post_content, %s, %s) WHERE post_content LIKE %s",
            $old_url,
            $new_url,
            '%' . $wpdb->esc_like($old_url) . '%'
        ));

        // 2. Reemplazar en _elementor_data (donde Elementor guarda los fondos de sections, columnas y widgets)
        $elementor_metas = $wpdb->get_results($wpdb->prepare(
            "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND (meta_value LIKE %s OR meta_value LIKE %s)",
            '%' . $wpdb->esc_like($old_url) . '%',
            '%' . $wpdb->esc_like($old_escaped_json) . '%'
        ));

        foreach ($elementor_metas as $row) {
            $val = $row->meta_value;
            $val = str_replace($old_url, $new_url, $val);
            $val = str_replace($old_escaped_json, $new_escaped_json, $val);
            // También formato sin protocolo
            $val = str_replace(str_replace('/', '\/', $old_clean), str_replace('/', '\/', $new_clean), $val);

            $wpdb->update(
                $wpdb->postmeta,
                ['meta_value' => $val],
                ['post_id' => $row->post_id, 'meta_key' => '_elementor_data']
            );
        }
    }

    /**
     * Fuerza la regeneración de archivos CSS estáticos de Elementor para que tomen el nuevo fondo
     */
    public static function regenerate_elementor_css() {
        if (class_exists('\Elementor\Plugin')) {
            try {
                \Elementor\Plugin::$instance->files_manager->clear_cache();
            } catch (\Exception $e) {
                // ignore
            }
        }
    }

    /**
     * Obtiene el listado completo de medios con su salud SEO (ALT, dimensiones, peso)
     */
    public static function get_media_items($page = 1, $per_page = 100, $search = '') {
        $args = [
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'posts_per_page' => (int) $per_page,
            'paged' => (int) $page,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if (!empty($search)) {
            $args['s'] = sanitize_text_field($search);
        }

        $query = new WP_Query($args);
        $items = [];

        foreach ($query->posts as $m) {
            $meta = wp_get_attachment_metadata($m->ID);
            $file_path = get_attached_file($m->ID);
            $file_size = ($file_path && file_exists($file_path)) ? filesize($file_path) : 0;
            $alt_text = get_post_meta($m->ID, '_wp_attachment_image_alt', true);

            $items[] = [
                'id' => $m->ID,
                'title' => $m->post_title,
                'altText' => (string) $alt_text,
                'caption' => $m->post_excerpt,
                'description' => $m->post_content,
                'sourceUrl' => wp_get_attachment_url($m->ID) ?: '',
                'mediaType' => wp_attachment_is_image($m->ID) ? 'image' : 'file',
                'mimeType' => $m->post_mime_type,
                'fileSize' => $file_size,
                'dimensions' => [
                    'width' => !empty($meta['width']) ? (int) $meta['width'] : null,
                    'height' => !empty($meta['height']) ? (int) $meta['height'] : null,
                ],
                'date' => $m->post_date,
            ];
        }

        return [
            'items' => $items,
            'total' => $query->found_posts,
            'totalPages' => $query->max_num_pages,
            'currentPage' => (int) $page,
        ];
    }

    /**
     * Actualiza atributos ALT, Título, Caption o Descripción de un medio
     */
    public static function update_media_item($attachment_id, $data) {
        $post_data = ['ID' => $attachment_id];
        if (isset($data['title'])) $post_data['post_title'] = sanitize_text_field($data['title']);
        if (isset($data['caption'])) $post_data['post_excerpt'] = sanitize_textarea_field($data['caption']);
        if (isset($data['description'])) $post_data['post_content'] = $data['description'];

        if (count($post_data) > 1) {
            wp_update_post($post_data);
        }

        if (isset($data['altText'])) {
            update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($data['altText']));
        }

        return ['success' => true, 'id' => $attachment_id];
    }
}

