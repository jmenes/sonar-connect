<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_Cache {
    /**
     * Purga la caché global o la de un post específico
     */
    public static function purge($post_id = null) {
        $purged_engines = [];

        // 1. WP Rocket
        if (function_exists('rocket_clean_domain')) {
            if ($post_id && function_exists('rocket_clean_post')) {
                rocket_clean_post($post_id);
                $purged_engines[] = 'wp-rocket (post)';
            } else {
                rocket_clean_domain();
                $purged_engines[] = 'wp-rocket (domain)';
            }
        }

        // 2. LiteSpeed Cache
        if (defined('LSCWP_V')) {
            if ($post_id) {
                do_action('litespeed_purge_post', $post_id);
                $purged_engines[] = 'litespeed (post)';
            } else {
                do_action('litespeed_purge_all');
                $purged_engines[] = 'litespeed (all)';
            }
        }

        // 3. W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            if ($post_id && function_exists('w3tc_flush_post')) {
                w3tc_flush_post($post_id);
                $purged_engines[] = 'w3-total-cache (post)';
            } else {
                w3tc_flush_all();
                $purged_engines[] = 'w3-total-cache (all)';
            }
        }

        // 4. WP Super Cache
        if (function_exists('wp_cache_clean_cache')) {
            global $file_prefix;
            wp_cache_clean_cache($file_prefix, true);
            $purged_engines[] = 'wp-super-cache';
        }

        // 5. Autoptimize
        if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) {
            autoptimizeCache::clearall();
            $purged_engines[] = 'autoptimize';
        }

        // 6. Fallback nativo de WordPress
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
            $purged_engines[] = 'wp_cache_flush';
        }

        return [
            'success' => true,
            'purgedEngines' => array_unique($purged_engines),
            'target' => $post_id ? 'post_' . $post_id : 'full_site',
            'timestamp' => current_time('mysql'),
        ];
    }
}

