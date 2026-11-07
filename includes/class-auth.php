<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_Auth {
    /**
     * Clave pública Ed25519 oficial de la suite Sonar (32 bytes DER base64)
     * Puede sobrescribirse mediante la constante SONAR_PUBLIC_KEY_DER en wp-config.php o la opción de BD
     */
    const DEFAULT_PUBLIC_KEY_DER = 'MCowBQYDK2VwAyEAdZcQyQ8WmE5LGBdlk0BeLl36eI5J0R3eJ/1m3Tve66I=';

    const HEADER_SIGNATURE = 'HTTP_X_SONAR_SIGNATURE';
    const HEADER_TIMESTAMP = 'HTTP_X_SONAR_TIMESTAMP';
    const HEADER_NONCE     = 'HTTP_X_SONAR_NONCE';
    const HEADER_SERVER    = 'HTTP_X_SONAR_SERVER';

    public static function get_public_key() {
        if (defined('SONAR_PUBLIC_KEY_DER') && !empty(SONAR_PUBLIC_KEY_DER)) {
            return SONAR_PUBLIC_KEY_DER;
        }
        return get_option('sonar_public_key_der', self::DEFAULT_PUBLIC_KEY_DER);
    }

    /**
     * Valida criptográficamente la petición entrante firmada por el backend oficial de Sonar
     */
    public static function check_permission($request) {
        $signature_b64 = isset($_SERVER[self::HEADER_SIGNATURE]) ? trim($_SERVER[self::HEADER_SIGNATURE]) : '';
        $timestamp     = isset($_SERVER[self::HEADER_TIMESTAMP]) ? trim($_SERVER[self::HEADER_TIMESTAMP]) : '';
        $nonce         = isset($_SERVER[self::HEADER_NONCE]) ? trim($_SERVER[self::HEADER_NONCE]) : '';
        $sonar_server  = isset($_SERVER[self::HEADER_SERVER]) ? trim($_SERVER[self::HEADER_SERVER]) : '';

        // Si se recibe la cabecera del origen del servidor de Sonar, guardarla para auto-actualizaciones
        if (!empty($sonar_server) && filter_var($sonar_server, FILTER_VALIDATE_URL)) {
            update_option('sonar_server_origin', esc_url_raw($sonar_server));
        }

        // Si faltan cabeceras de firma criptográfica
        if (empty($signature_b64) || empty($timestamp) || empty($nonce)) {
            return new WP_Error(
                'sonar_unauthorized',
                'Petición no autorizada. Faltan cabeceras criptográficas de Sonar.',
                ['status' => 401]
            );
        }

        // 1. Prevención de ataques de repetición por tiempo (tolerancia de 120 segundos)
        $time_diff = abs(time() - (int)$timestamp);
        if ($time_diff > 120) {
            return new WP_Error(
                'sonar_expired_request',
                'Firma expirada o reloj desincronizado con Sonar.',
                ['status' => 403]
            );
        }

        // 2. Prevención de repetición por Nonce (almacenado durante 180 segundos en transient de WP)
        $nonce_key = 'sonar_nonce_' . md5($nonce);
        if (get_transient($nonce_key)) {
            return new WP_Error(
                'sonar_nonce_replayed',
                'Petición ya procesada (nonce replayed).',
                ['status' => 403]
            );
        }
        set_transient($nonce_key, 1, 180);

        // 3. Reconstruir mensaje firmado canónico: timestamp:nonce:METHOD:path
        $method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper($_SERVER['REQUEST_METHOD']) : 'GET';
        $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $path = strtok($uri, '?'); // ignorar query params

        // Asegurarse de que el path compare la ruta del endpoint REST relativa o absoluta
        // Si WordPress está en un subdirectorio o usa rewrite (/wp-json/sonar/v1/...)
        $rest_prefix = rest_get_url_prefix();
        $subpath = '';
        if (preg_match('#/' . preg_quote($rest_prefix, '#') . '/sonar/v1/.*#', $path, $m)) {
            $subpath = $m[0];
        } else {
            $subpath = $path;
        }

        $message = "{$timestamp}:{$nonce}:{$method}:{$subpath}";
        $signature = base64_decode($signature_b64);
        if (!$signature || strlen($signature) !== 64) {
            return new WP_Error(
                'sonar_invalid_signature_format',
                'Formato de firma inválido.',
                ['status' => 403]
            );
        }

        // 4. Extraer clave pública raw de 32 bytes de la cabecera DER
        $pub_der_str = self::get_public_key();
        $pub_der = base64_decode($pub_der_str);
        $raw_public_key = substr($pub_der, -32);

        // 5. Verificar firma con Libsodium o OpenSSL
        $is_valid = false;
        if (function_exists('sodium_crypto_sign_verify_detached')) {
            $is_valid = sodium_crypto_sign_verify_detached($signature, $message, $raw_public_key);
            // Intentar también con la variante de path original por compatibilidad
            if (!$is_valid && $path !== $subpath) {
                $is_valid = sodium_crypto_sign_verify_detached($signature, "{$timestamp}:{$nonce}:{$method}:{$path}", $raw_public_key);
            }
        } elseif (function_exists('openssl_verify')) {
            $pem = "-----BEGIN PUBLIC KEY-----\n" . chunk_split($pub_der_str, 64, "\n") . "-----END PUBLIC KEY-----";
            $is_valid = (openssl_verify($message, $signature, $pem, OPENSSL_ALGO_SHA512) === 1);
        }

        if (!$is_valid) {
            return new WP_Error(
                'sonar_signature_mismatch',
                'Firma no válida. Solo el servidor oficial de Sonar puede comunicarse con este conector.',
                ['status' => 403]
            );
        }

        return true;
    }
}

