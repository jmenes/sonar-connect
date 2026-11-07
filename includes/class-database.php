<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_Database {
    /**
     * Limpia revisiones huérfanas, borradores automáticos y transitorios caducados
     */
    public static function cleanup() {
        global $wpdb;

        // 1. Eliminar revisiones de posts
        $revisions_deleted = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'revision'");

        // 2. Eliminar borradores automáticos antiguos (> 7 días)
        $drafts_deleted = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_status = 'auto-draft' AND post_modified < DATE_SUB(NOW(), INTERVAL 7 DAY)");

        // 3. Eliminar comentarios en papelera o spam
        $spam_deleted = $wpdb->query("DELETE FROM {$wpdb->comments} WHERE comment_approved IN ('spam', 'trash')");

        // 4. Eliminar transitorios caducados en wp_options
        $time_now = time();
        $transients_deleted = $wpdb->query($wpdb->prepare(
            "DELETE a, b FROM {$wpdb->options} a 
             INNER JOIN {$wpdb->options} b ON b.option_name = CONCAT('_transient_', SUBSTRING(a.option_name, 17))
             WHERE a.option_name LIKE '\_transient\_timeout\_%' AND a.option_value < %d",
            $time_now
        ));

        // 5. Optimizar tablas principales de WordPress
        $tables = [$wpdb->posts, $wpdb->postmeta, $wpdb->comments, $wpdb->commentmeta, $wpdb->options];
        foreach ($tables as $table) {
            $wpdb->query("OPTIMIZE TABLE {$table}");
        }

        return [
            'success' => true,
            'revisionsDeleted' => (int) $revisions_deleted,
            'autoDraftsDeleted' => (int) $drafts_deleted,
            'spamCommentsDeleted' => (int) $spam_deleted,
            'transientsDeleted' => (int) $transients_deleted,
            'timestamp' => current_time('mysql'),
        ];
    }
}

