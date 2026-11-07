<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_Snippets {
    const OPTION_HEAD = 'sonar_snippet_head';
    const OPTION_BODY = 'sonar_snippet_body';
    const OPTION_FOOTER = 'sonar_snippet_footer';

    public static function get_all() {
        return [
            'head' => get_option(self::OPTION_HEAD, ''),
            'body' => get_option(self::OPTION_BODY, ''),
            'footer' => get_option(self::OPTION_FOOTER, ''),
        ];
    }

    public static function update_all($snippets) {
        if (isset($snippets['head'])) {
            update_option(self::OPTION_HEAD, $snippets['head']);
        }
        if (isset($snippets['body'])) {
            update_option(self::OPTION_BODY, $snippets['body']);
        }
        if (isset($snippets['footer'])) {
            update_option(self::OPTION_FOOTER, $snippets['footer']);
        }
        return self::get_all();
    }

    public static function inject_head() {
        $code = get_option(self::OPTION_HEAD, '');
        if (!empty($code)) {
            echo "<!-- Sonar Header Snippets -->\n" . $code . "\n<!-- /Sonar Header Snippets -->\n";
        }
    }

    public static function inject_body() {
        $code = get_option(self::OPTION_BODY, '');
        if (!empty($code)) {
            echo "<!-- Sonar Body Snippets -->\n" . $code . "\n<!-- /Sonar Body Snippets -->\n";
        }
    }

    public static function inject_footer() {
        $code = get_option(self::OPTION_FOOTER, '');
        if (!empty($code)) {
            echo "<!-- Sonar Footer Snippets -->\n" . $code . "\n<!-- /Sonar Footer Snippets -->\n";
        }
    }
}

