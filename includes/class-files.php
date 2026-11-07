<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_Files {
    /**
     * Lee el contenido físico de robots.txt o ads.txt
     */
    public static function get_file($type) {
        $filename = ($type === 'ads') ? 'ads.txt' : 'robots.txt';
        $filepath = ABSPATH . $filename;

        if (file_exists($filepath)) {
            return [
                'type' => $type,
                'filename' => $filename,
                'exists' => true,
                'content' => file_get_contents($filepath),
                'isWritable' => is_writable($filepath),
            ];
        }

        // Si es robots.txt y no existe como archivo físico, WordPress lo genera dinámicamente
        if ($type === 'robots') {
            ob_start();
            do_action('do_robots');
            $dynamic_content = ob_get_clean();
            return [
                'type' => 'robots',
                'filename' => 'robots.txt',
                'exists' => false,
                'isDynamic' => true,
                'content' => $dynamic_content,
                'isWritable' => is_writable(ABSPATH),
            ];
        }

        return [
            'type' => $type,
            'filename' => $filename,
            'exists' => false,
            'content' => '',
            'isWritable' => is_writable(ABSPATH),
        ];
    }

    /**
     * Guarda el contenido de robots.txt o ads.txt
     */
    public static function save_file($type, $content) {
        $filename = ($type === 'ads') ? 'ads.txt' : 'robots.txt';
        $filepath = ABSPATH . $filename;

        if (!is_writable(ABSPATH) && (!file_exists($filepath) || !is_writable($filepath))) {
            return new WP_Error('not_writable', 'El directorio raíz de WordPress no tiene permisos de escritura para guardar ' . $filename, ['status' => 500]);
        }

        $res = file_put_contents($filepath, $content);
        if ($res === false) {
            return new WP_Error('write_error', 'Ocurrió un error al escribir el archivo ' . $filename, ['status' => 500]);
        }

        return [
            'success' => true,
            'filename' => $filename,
            'size' => filesize($filepath),
            'updatedAt' => current_time('mysql'),
        ];
    }
}

