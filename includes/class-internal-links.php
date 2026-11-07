<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_Internal_Links {
    /**
     * Inserta un hipervínculo en el primer texto ancla que coincida dentro del contenido de un post
     */
    public static function insert_link($post_id, $anchor_text, $target_url) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('post_not_found', 'Post no encontrado.', ['status' => 404]);
        }

        $content = $post->post_content;
        $anchor_escaped = preg_quote(trim($anchor_text), '/');
        $target_url = esc_url_raw($target_url);

        // Expresión regular que busca la palabra sin estar ya dentro de una etiqueta <a> o atributo HTML
        $pattern = '/(?!(?:[^<]+>|[^>]+<\/a>))\b(' . $anchor_escaped . ')\b/iu';
        $replacement = '<a href="' . $target_url . '">$1</a>';

        $new_content = preg_replace($pattern, $replacement, $content, 1, $count);

        if ($count === 0) {
            return new WP_Error('anchor_not_found', 'No se encontró el texto ancla en el contenido o ya estaba enlazado.', ['status' => 400]);
        }

        wp_update_post([
            'ID' => $post_id,
            'post_content' => $new_content,
        ]);

        return [
            'success' => true,
            'postId' => $post_id,
            'anchorText' => $anchor_text,
            'targetUrl' => $target_url,
            'replacementsCount' => $count,
        ];
    }
}

