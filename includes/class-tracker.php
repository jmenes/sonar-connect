<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_Tracker {
    const OPTION_STATS = 'sonar_cro_events_stats';

    /**
     * Inyecta el script frontend ultraligero (<1.5KB) para capturar clics en WhatsApp, teléfonos y envíos de formularios
     */
    public static function inject_frontend_script() {
        if (is_admin()) {
            return;
        }

        $ajax_url = admin_url('admin-ajax.php');
        $nonce = wp_create_nonce('sonar_tracker_nonce');
        ?>
        <script>
        (function() {
            var ajaxUrl = "<?php echo esc_url($ajax_url); ?>";
            var nonce = "<?php echo esc_js($nonce); ?>";

            function trackEvent(type, label) {
                try {
                    if (navigator.sendBeacon) {
                        var fd = new FormData();
                        fd.append('action', 'sonar_track_event');
                        fd.append('type', type);
                        fd.append('label', label || window.location.pathname);
                        fd.append('nonce', nonce);
                        navigator.sendBeacon(ajaxUrl, fd);
                    } else {
                        var xhr = new XMLHttpRequest();
                        xhr.open('POST', ajaxUrl, true);
                        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                        xhr.send('action=sonar_track_event&type=' + encodeURIComponent(type) + '&label=' + encodeURIComponent(label || window.location.pathname) + '&nonce=' + encodeURIComponent(nonce));
                    }
                } catch(e) {}
            }

            document.addEventListener('click', function(e) {
                var target = e.target.closest('a');
                if (!target || !target.href) return;
                var href = target.href.toLowerCase();

                if (href.indexOf('wa.me') !== -1 || href.indexOf('api.whatsapp.com') !== -1 || href.indexOf('whatsapp:') !== -1) {
                    trackEvent('whatsapp', target.innerText.trim() || 'WhatsApp Button');
                } else if (href.indexOf('tel:') === 0) {
                    trackEvent('phone', href.replace('tel:', '').trim());
                }
            }, true);

            // Escuchar formularios comunes (CF7, WPForms, etc.)
            document.addEventListener('wpcf7mailsent', function(e) {
                trackEvent('form', 'Contact Form 7 (ID: ' + e.detail.contactFormId + ')');
            }, false);

            document.addEventListener('submit', function(e) {
                var form = e.target;
                if (form.classList.contains('wpforms-form') || form.classList.contains('elementor-form') || form.classList.contains('gform_wrapper')) {
                    trackEvent('form', form.getAttribute('name') || form.id || 'Web Form');
                }
            }, true);
        })();
        </script>
        <?php
    }

    /**
     * Recibe los eventos desde el frontend y los acumula por tipo y mes
     */
    public static function handle_ajax_event() {
        check_ajax_referer('sonar_tracker_nonce', 'nonce');

        $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
        if (!in_array($type, ['whatsapp', 'phone', 'form'])) {
            wp_send_json_error();
        }

        $stats = get_option(self::OPTION_STATS, []);
        $current_month = current_time('Y-m');

        if (!isset($stats[$current_month])) {
            $stats[$current_month] = ['whatsapp' => 0, 'phone' => 0, 'form' => 0, 'total' => 0];
        }

        $stats[$current_month][$type] = ($stats[$current_month][$type] ?? 0) + 1;
        $stats[$current_month]['total'] = ($stats[$current_month]['total'] ?? 0) + 1;

        update_option(self::OPTION_STATS, $stats);
        wp_send_json_success();
    }

    public static function get_stats() {
        $stats = get_option(self::OPTION_STATS, []);
        return is_array($stats) ? $stats : [];
    }
}

add_action('wp_ajax_sonar_track_event', ['Sonar_Connector_Tracker', 'handle_ajax_event']);
add_action('wp_ajax_nopriv_sonar_track_event', ['Sonar_Connector_Tracker', 'handle_ajax_event']);

