<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_SEO {
    /**
     * Obtiene los metadatos SEO de un post (Yoast, RankMath, SEOPress, etc.)
     */
    public static function get_post_seo_metas($post_id) {
        $metas = [
            'seoTitle' => '',
            'seoDescription' => '',
            'canonicalUrl' => '',
            'isNoindex' => false,
            'isNofollow' => false,
            'targetKeyword' => '',
        ];

        // 1. Yoast SEO
        if (defined('WPSEO_VERSION')) {
            $metas['seoTitle'] = (string) get_post_meta($post_id, '_yoast_wpseo_title', true);
            $metas['seoDescription'] = (string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
            $metas['canonicalUrl'] = (string) get_post_meta($post_id, '_yoast_wpseo_canonical', true);
            $metas['targetKeyword'] = (string) get_post_meta($post_id, '_yoast_wpseo_focuskw', true);
            $noindex = get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
            $metas['isNoindex'] = ($noindex === '1' || $noindex === 1);
            $nofollow = get_post_meta($post_id, '_yoast_wpseo_meta-robots-nofollow', true);
            $metas['isNofollow'] = ($nofollow === '1' || $nofollow === 1);
        }
        // 2. Rank Math
        elseif (defined('RANK_MATH_VERSION')) {
            $metas['seoTitle'] = (string) get_post_meta($post_id, 'rank_math_title', true);
            $metas['seoDescription'] = (string) get_post_meta($post_id, 'rank_math_description', true);
            $metas['canonicalUrl'] = (string) get_post_meta($post_id, 'rank_math_canonical_url', true);
            $metas['targetKeyword'] = (string) get_post_meta($post_id, 'rank_math_focus_keyword', true);
            $robots = get_post_meta($post_id, 'rank_math_robots', true);
            if (is_array($robots)) {
                $metas['isNoindex'] = in_array('noindex', $robots);
                $metas['isNofollow'] = in_array('nofollow', $robots);
            }
        }
        // 3. Fallback / SEOPress
        elseif (defined('SEOPRESS_VERSION')) {
            $metas['seoTitle'] = (string) get_post_meta($post_id, '_seopress_titles_title', true);
            $metas['seoDescription'] = (string) get_post_meta($post_id, '_seopress_titles_desc', true);
            $metas['targetKeyword'] = (string) get_post_meta($post_id, '_seopress_analysis_target_kw', true);
        }
        // 4. All in One SEO (AIOSEO)
        elseif (defined('AIOSEO_VERSION')) {
            $metas['seoTitle'] = (string) get_post_meta($post_id, '_aioseo_title', true);
            $metas['seoDescription'] = (string) get_post_meta($post_id, '_aioseo_description', true);
        }
        // 5. Fallback general a cualquier postmeta SEO común existente
        else {
            $metas['seoTitle'] = (string) (get_post_meta($post_id, 'rank_math_title', true) ?: get_post_meta($post_id, '_yoast_wpseo_title', true) ?: get_post_meta($post_id, '_seopress_titles_title', true));
            $metas['seoDescription'] = (string) (get_post_meta($post_id, 'rank_math_description', true) ?: get_post_meta($post_id, '_yoast_wpseo_metadesc', true) ?: get_post_meta($post_id, '_seopress_titles_desc', true));
        }

        // Obtener objeto post
        $post = get_post($post_id);

        // Fallback 1: Si no hay descripción explícita, usar extracto (post_excerpt)
        if (empty($metas['seoDescription']) && $post && !empty($post->post_excerpt)) {
            $metas['seoDescription'] = wp_strip_all_tags($post->post_excerpt);
        }

        // Fallback 2: Si aún no hay descripción, extraer automáticamente de los primeros 160 caracteres del contenido limpio
        if (empty($metas['seoDescription']) && $post && !empty($post->post_content)) {
            $clean_text = strip_shortcodes($post->post_content);
            $clean_text = preg_replace('/<!--(.|\s)*?-->/', '', $clean_text);
            $clean_text = wp_strip_all_tags($clean_text);
            $clean_text = preg_replace('/\s+/', ' ', $clean_text);
            $clean_text = trim($clean_text);
            if (!empty($clean_text)) {
                $metas['seoDescription'] = mb_substr($clean_text, 0, 155) . (mb_strlen($clean_text) > 155 ? '...' : '');
            }
        }

        // Si el título SEO está vacío, usar el título original del post
        if (empty($metas['seoTitle']) && $post) {
            $metas['seoTitle'] = $post->post_title;
        }

        // Reemplazo y expansión automática de variables de plantilla de Rank Math y Yoast
        // para que Sonar reciba siempre el texto final renderizado exactamente como se ve en Google
        $metas['seoTitle'] = self::expand_seo_template_vars($metas['seoTitle'], $post);
        $metas['seoDescription'] = self::expand_seo_template_vars($metas['seoDescription'], $post);

        return $metas;
    }

    /**
     * Expande etiquetas dinámicas como %title%, %sep%, %sitename%, %%title%%, etc.
     */
    private static function expand_seo_template_vars($text, $post) {
        if (empty($text)) {
            return '';
        }

        $sitename = get_bloginfo('name');
        $post_title = $post ? $post->post_title : '';
        $sep = '-';

        $replacements = [
            '%title%' => $post_title,
            '%%title%%' => $post_title,
            '%sitename%' => $sitename,
            '%%sitename%%' => $sitename,
            '%sep%' => $sep,
            '%%sep%%' => $sep,
            '%page%' => '',
            '%%page%%' => '',
            '%excerpt%' => $post ? wp_strip_all_tags($post->post_excerpt) : '',
            '%%excerpt%%' => $post ? wp_strip_all_tags($post->post_excerpt) : '',
            '%date%' => $post ? get_the_date('', $post) : '',
        ];

        $expanded = str_ireplace(array_keys($replacements), array_values($replacements), $text);
        // Limpiar espacios duplicados o guiones flotantes
        $expanded = preg_replace('/\s+/', ' ', $expanded);
        return trim($expanded, " -–|");
    }

    /**
     * Guarda los metadatos SEO en el plugin correspondiente
     */
    public static function update_post_seo_metas($post_id, $data) {
        // Yoast SEO
        if (isset($data['seoTitle'])) {
            update_post_meta($post_id, '_yoast_wpseo_title', sanitize_text_field($data['seoTitle']));
            update_post_meta($post_id, 'rank_math_title', sanitize_text_field($data['seoTitle']));
            update_post_meta($post_id, '_seopress_titles_title', sanitize_text_field($data['seoTitle']));
        }

        if (isset($data['seoDescription'])) {
            update_post_meta($post_id, '_yoast_wpseo_metadesc', sanitize_textarea_field($data['seoDescription']));
            update_post_meta($post_id, 'rank_math_description', sanitize_textarea_field($data['seoDescription']));
            update_post_meta($post_id, '_seopress_titles_desc', sanitize_textarea_field($data['seoDescription']));
        }

        if (isset($data['targetKeyword'])) {
            update_post_meta($post_id, '_yoast_wpseo_focuskw', sanitize_text_field($data['targetKeyword']));
            update_post_meta($post_id, 'rank_math_focus_keyword', sanitize_text_field($data['targetKeyword']));
        }

        if (isset($data['canonicalUrl'])) {
            update_post_meta($post_id, '_yoast_wpseo_canonical', esc_url_raw($data['canonicalUrl']));
            update_post_meta($post_id, 'rank_math_canonical_url', esc_url_raw($data['canonicalUrl']));
        }

        if (isset($data['isNoindex'])) {
            $is_noindex = (bool) $data['isNoindex'];
            update_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', $is_noindex ? '1' : '2');
            
            $current_robots = get_post_meta($post_id, 'rank_math_robots', true);
            $current_robots = is_array($current_robots) ? $current_robots : [];
            if ($is_noindex && !in_array('noindex', $current_robots)) {
                $current_robots[] = 'noindex';
            } elseif (!$is_noindex) {
                $current_robots = array_diff($current_robots, ['noindex']);
            }
            update_post_meta($post_id, 'rank_math_robots', array_values($current_robots));
        }

        return true;
    }
}

