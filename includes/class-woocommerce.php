<?php
if (!defined('ABSPATH')) {
    exit;
}

class Sonar_Connector_WooCommerce {
    public static function is_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Obtiene productos de WooCommerce con sus datos comerciales y SEO
     */
    public static function get_products($page = 1, $per_page = 20, $search = '') {
        if (!self::is_active()) {
            return [
                'active' => false,
                'products' => [],
                'total' => 0,
            ];
        }

        $args = [
            'status' => 'publish',
            'limit' => (int) $per_page,
            'page' => (int) $page,
            'paginate' => true,
        ];

        if (!empty($search)) {
            $args['s'] = sanitize_text_field($search);
        }

        $query = wc_get_products($args);
        $products = [];

        foreach ($query->products as $p) {
            $products[] = [
                'id' => $p->get_id(),
                'name' => $p->get_name(),
                'sku' => $p->get_sku(),
                'price' => $p->get_price(),
                'regularPrice' => $p->get_regular_price(),
                'salePrice' => $p->get_sale_price(),
                'stockStatus' => $p->get_stock_status(),
                'stockQuantity' => $p->get_stock_quantity(),
                'permalink' => get_permalink($p->get_id()),
                'image' => wp_get_attachment_image_url($p->get_image_id(), 'medium'),
                'seoMetas' => Sonar_Connector_SEO::get_post_seo_metas($p->get_id()),
            ];
        }

        return [
            'active' => true,
            'products' => $products,
            'total' => $query->total,
            'totalPages' => $query->max_num_pages,
            'currentPage' => (int) $page,
        ];
    }

    /**
     * Actualización rápida de precios, stock o SEO de un producto
     */
    public static function update_product($product_id, $data) {
        if (!self::is_active()) {
            return new WP_Error('wc_not_active', 'WooCommerce no está activo en este sitio.', ['status' => 400]);
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            return new WP_Error('product_not_found', 'Producto no encontrado.', ['status' => 404]);
        }

        if (isset($data['regularPrice'])) {
            $product->set_regular_price(wc_clean($data['regularPrice']));
        }
        if (isset($data['salePrice'])) {
            $product->set_sale_price(wc_clean($data['salePrice']));
        }
        if (isset($data['stockStatus']) && in_array($data['stockStatus'], ['instock', 'outofstock', 'onbackorder'])) {
            $product->set_stock_status($data['stockStatus']);
        }
        if (isset($data['stockQuantity'])) {
            $product->set_stock_quantity((int) $data['stockQuantity']);
        }

        $product->save();

        if (isset($data['seoMetas']) && is_array($data['seoMetas'])) {
            Sonar_Connector_SEO::update_post_seo_metas($product_id, $data['seoMetas']);
        }

        return [
            'success' => true,
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price(),
            'stockStatus' => $product->get_stock_status(),
        ];
    }
}

