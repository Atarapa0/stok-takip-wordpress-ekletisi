<?php
/*
Plugin Name: Dokan Stok Takip
Description: Dokan satıcıları için stok takip sistemini sağlar.
Version: 1.0
Author: Furkan Erdoğan
*/


/*
Kritik stok seviyeleri için uyarılar
Stok hareketleri raporu
En çok ve en az satılan ürünler analizi
*/

// Doğrudan erişimi engelle
if (!defined('ABSPATH')) {
    exit;
}

// Eklenti aktivasyonunda tablo oluştur
register_activation_hook(__FILE__, 'dokan_stock_tracking_activate');

function dokan_stock_tracking_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dokan_stock_movements';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        `id` mediumint(9) NOT NULL AUTO_INCREMENT,
        `vendor_id` bigint(20) NOT NULL,
        `product_id` bigint(20) NOT NULL,
        `quantity` int(11) NOT NULL,
        `movement_type` VARCHAR(20) NOT NULL,
        `movement_date` datetime DEFAULT CURRENT_TIMESTAMP,
        `notes` TEXT NULL,
        `current_stock` int(11) DEFAULT NULL,
        `critical_stock_level` INT DEFAULT 5,
        PRIMARY KEY (`id`)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Hata ayıklama için log
    error_log('Dokan Stock Tracking table creation attempted');
    error_log('SQL: ' . $sql);
    error_log('Last DB Error: ' . $wpdb->last_error);
}

// **Admin menüsüne "Dokan Stok Yönetimi" sayfasını ekle**
function add_dokan_stock_admin_menu() {
    add_menu_page(
        'Dokan Stok Yönetimi',
        'Dokan Stok',
        'manage_options',
        'dokan-stock',
        'render_dokan_stock_admin_page',
        'dashicons-admin-generic',
        20
    );
}
add_action('admin_menu', 'add_dokan_stock_admin_menu');

// **Admin paneli sayfası HTML içeriği**
function render_dokan_stock_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Yetkiniz yok.', 'textdomain'));
    }

    if (isset($_POST['save_api_keys'])) {
        $vendor_id = intval($_POST['vendor_id']);
        $consumer_key = sanitize_text_field($_POST['consumer_key']);
        $consumer_secret = sanitize_text_field($_POST['consumer_secret']);

        // API anahtarlarını kaydet
        update_user_meta($vendor_id, 'dokan_api_consumer_key', $consumer_key);
        update_user_meta($vendor_id, 'dokan_api_consumer_secret', $consumer_secret);

        echo '<div class="updated"><p>API Anahtarları kaydedildi.</p></div>';
    }

    // Dokan satıcılarını getir
    $vendors = get_users(array(
        'role__in' => array('seller', 'vendor', 'wcfm_vendor', 'dc_vendor')
    ));

    echo '<div class="wrap">';
    echo '<h1>Dokan Stok Yönetimi</h1>';
    
    if (empty($vendors)) {
        echo '<p>Henüz satıcı bulunmamaktadır.</p>';
    } else {
        // API Anahtarı atama formu
        echo '<div class="api-form" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">';
        echo '<h2>API Anahtarı Ata</h2>';
        echo '<p>Her satıcı için WooCommerce > Ayarlar > Gelişmiş > REST API\'den aldığınız API anahtarlarını girin.</p>';
        echo '<form method="post">';
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th><label>Satıcı Seç:</label></th>';
        echo '<td><select name="vendor_id" style="width: 300px;">';
        
        foreach ($vendors as $vendor) {
            $store_info = dokan_get_store_info($vendor->ID);
            $store_name = !empty($store_info['store_name']) ? $store_info['store_name'] : $vendor->display_name;
            echo '<option value="' . esc_attr($vendor->ID) . '">' . esc_html($store_name) . ' (' . $vendor->user_email . ')</option>';
        }
        
        echo '</select></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label>Consumer Key:</label></th>';
        echo '<td><input type="text" name="consumer_key" style="width: 300px;" required></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th><label>Consumer Secret:</label></th>';
        echo '<td><input type="text" name="consumer_secret" style="width: 300px;" required></td>';
        echo '</tr>';
        echo '</table>';
        echo '<p class="submit">';
        echo '<input type="submit" name="save_api_keys" value="API Anahtarlarını Kaydet" class="button button-primary">';
        echo '</p>';
        echo '</form>';
        echo '</div>';

        // Mevcut API anahtarlarını listele
        echo '<div class="api-list" style="background: #fff; padding: 20px; margin: 20px 0; border: 1px solid #ccc;">';
        echo '<h2>Mevcut API Anahtarları</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Satıcı</th>';
        echo '<th>Mağaza Adı</th>';
        echo '<th>Consumer Key</th>';
        echo '<th>Consumer Secret</th>'; 
        echo '<th>Durum</th>';
        echo '</tr></thead>';
        echo '<tbody>';
        
        foreach ($vendors as $vendor) {
            $store_info = dokan_get_store_info($vendor->ID);
            $store_name = !empty($store_info['store_name']) ? $store_info['store_name'] : 'Ayarlanmamış';
            $consumer_key = get_user_meta($vendor->ID, 'dokan_api_consumer_key', true);
            $consumer_secret = get_user_meta($vendor->ID, 'dokan_api_consumer_secret', true);
            $status = ($consumer_key && $consumer_secret) ? 
                '<span style="color: green;">✓ Ayarlandı</span>' : 
                '<span style="color: red;">✗ Ayarlanmadı</span>';

            echo '<tr>';
            echo '<td>' . esc_html($vendor->display_name) . '</td>';
            echo '<td>' . esc_html($store_name) . '</td>';
            echo '<td>' . ($consumer_key ? substr($consumer_key, 0, 10) . '...' : '-') . '</td>';
            echo '<td>' . ($consumer_secret ? substr($consumer_secret, 0, 10) . '...' : '-') . '</td>';
            echo '<td>' . $status . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
        echo '</div>';
    }
    echo '</div>';
}

// **Satıcılar için stok takip sayfası**
function dokan_vendor_stock_tracking($atts) {
    // Giriş kontrolü
    if (!is_user_logged_in()) {
        return '<p>Lütfen giriş yapın.</p>';
    }

    $current_user = wp_get_current_user();
    $vendor_id = $current_user->ID;

    // Satıcı rolü kontrolü
    if (!in_array('seller', $current_user->roles) && 
        !in_array('vendor', $current_user->roles) && 
        !in_array('wcfm_vendor', $current_user->roles) && 
        !in_array('dc_vendor', $current_user->roles)) {
        return '<p>Yetkiniz yok.</p>';
    }

    // Satıcının ürünlerini doğrudan WooCommerce'dan al
    $args = array(
        'author' => $vendor_id,
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );
    
    $products_query = new WP_Query($args);
    
    if (!$products_query->have_posts()) {
        return '<p>Ürün bulunamadı.</p>';
    }

    $output = '<div class="dokan-stock-tracking">';
    $output .= '<div class="panel-header">';
    $output .= '<h3>Stok Takip Paneli</h3>';
    $output .= '<div class="action-buttons">';
    $output .= '<button class="button add-new-product">Yeni Ürün Ekle</button>';
    $output .= '<button class="button check-critical-stocks">Kritik Stok Kontrolü</button>';
    $output .= '<button class="button generate-stock-report">Stok Raporu</button>';
    $output .= '<button class="button sales-analysis">Satış Analizi</button>';
    $output .= '</div>';
    $output .= '</div>';
    $output .= '<table class="stock-table widefat">';
    $output .= '<thead><tr>
        <th>Ürün Kodu</th>
        <th>Ürün</th>
        <th>Mevcut Stok</th>
        <th>Online Satış</th>
        <th>Manuel Satış</th>
        <th>Toplam Satış</th>
        <th>İşlemler</th>
    </tr></thead>';
    $output .= '<tbody>';

    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';

    while ($products_query->have_posts()) {
        $products_query->the_post();
        $product = wc_get_product(get_the_ID());
        $product_id = $product->get_id();
        
        // Ürün kodunu al (SKU)
        $sku = $product->get_sku() ? $product->get_sku() : 'Kod yok';
        
        // Manuel satışları getir
        $manual_sales = $wpdb->get_var($wpdb->prepare(
            "SELECT ABS(COALESCE(SUM(quantity), 0)) FROM $movements_table 
            WHERE product_id = %d AND vendor_id = %d AND movement_type = 'sale'",
            $product_id, $vendor_id
        ));

        $manual_sales = intval($manual_sales);
        $online_sales = $product->get_total_sales();
        $total_sales = $online_sales + $manual_sales;

        $output .= '<tr>';
        $output .= '<td>' . esc_html($sku) . '</td>';
        $output .= '<td>' . esc_html($product->get_name()) . '</td>';
        $output .= '<td id="stock_' . $product_id . '">' . $product->get_stock_quantity() . '</td>';
        $output .= '<td>' . $online_sales . '</td>';
        $output .= '<td class="manual-sales-' . $product_id . '">' . $manual_sales . '</td>';
        $output .= '<td class="total-sales-' . $product_id . '">' . $total_sales . '</td>';
        $output .= '<td class="actions">
            <button class="button edit-product-btn" data-product-id="' . $product_id . '">Düzenle</button>
            <button class="button stock-add-btn" data-product-id="' . $product_id . '">Stok Ekle</button>
            <button class="button manual-sale-btn" data-product-id="' . $product_id . '">Manuel Satış</button>
            <button class="button show-history-btn" data-product-id="' . $product_id . '">Geçmiş</button>
            <button class="button set-stock-btn" data-product-id="' . $product_id . '">Stok Güncelleme</button>
            <button class="button delete-stock-btn" data-product-id="' . $product_id . '">Stok Çıkar</button>
            <button class="button delete-product-btn" data-product-id="' . $product_id . '">Ürünü Sil</button>
        </td>';
        $output .= '</tr>';
        
        // Stok hareket geçmişi için gizli div
        $output .= '<tr class="history-row" id="history_' . $product_id . '" style="display:none;">
            <td colspan="7">
                <div class="stock-history">
                    <h4>Stok Hareket Geçmişi</h4>
                    <div class="history-content"></div>
                </div>
            </td>
        </tr>';
    }

    wp_reset_postdata();

    $output .= '</tbody></table>';
    $output .= '</div>';

    // Popup HTML'i
    $output .= '
    <div id="stock-movement-popup" style="display:none;" class="stock-popup">
        <div class="popup-content">
            <h3 class="popup-title">Stok Hareketi</h3>
            <p class="product-name"></p>
            <div class="form-group">
                <label>Miktar:</label>
                <input type="number" id="movement-quantity" min="1" value="1">
            </div>
            <div class="form-group">
                <label>Not:</label>
                <textarea id="movement-notes"></textarea>
            </div>
            <div class="button-group">
                <button class="button save-movement">Kaydet</button>
                <button class="button cancel-movement">İptal</button>
            </div>
        </div>
    </div>';

    // Kritik stok uyarıları için div ekle
    $output .= '<div id="critical-stock-container" style="margin-bottom: 20px; display: none;"></div>';

    // Script ve style dosyalarını ekle
    wp_enqueue_media();
    wp_enqueue_style('dokan-stock-style', plugin_dir_url(__FILE__) . 'css/style.css');
    wp_enqueue_script('dokan-stock-script', plugin_dir_url(__FILE__) . 'js/dokan-stock.js', array('jquery'), '1.0', true);
    
    // AJAX için gerekli verileri ekle
    wp_localize_script('dokan-stock-script', 'dokanStock', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'security' => wp_create_nonce('dokan-stock-security'),
        'strings' => array(
            'savingProduct' => 'Ürün kaydediliyor...',
            'productSaved' => 'Ürün başarıyla eklendi',
            'error' => 'Bir hata oluştu'
        )
    ));

    return $output;
}
add_shortcode('dokan_stok_takip', 'dokan_vendor_stock_tracking');

// **Ajax ile stok güncelleme**
function update_stock_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');

    // Giriş ve yetki kontrolü
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }

    $current_user = wp_get_current_user();
    if (!in_array('seller', $current_user->roles) && 
        !in_array('vendor', $current_user->roles) && 
        !in_array('wcfm_vendor', $current_user->roles) && 
        !in_array('dc_vendor', $current_user->roles)) {
        wp_send_json_error('Bu işlem için yetkiniz yok');
        return;
    }

    if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
        wp_send_json_error('Geçersiz istek');
    }

    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $movement_type = sanitize_text_field($_POST['movement_type']);
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    $vendor_id = get_current_user_id();

    // Ürünü al
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error('Ürün bulunamadı.');
    }

    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';

    // Mevcut stok miktarını al
    $current_stock = $product->get_stock_quantity();
    
    // Yeni stok miktarını hesapla
    if ($movement_type === 'add') {
        $new_stock = $current_stock + abs($quantity);
    } else {
        $new_stock = max(0, $current_stock - abs($quantity));
    }

    // Hareket kaydını ekle
    $result = $wpdb->insert(
        $movements_table,
        array(
            'vendor_id' => $vendor_id,
            'product_id' => $product_id,
            'quantity' => $movement_type === 'add' ? abs($quantity) : -abs($quantity),
            'movement_type' => $movement_type,
            'movement_date' => current_time('mysql'),
            'notes' => $notes,
            'current_stock' => $new_stock
        ),
        array('%d', '%d', '%d', '%s', '%s', '%s', '%d')
    );

    if ($result === false) {
        wp_send_json_error('İşlem kaydedilemedi: ' . $wpdb->last_error);
        return;
    }

    // WooCommerce stok güncelleme
    wc_update_product_stock($product, $new_stock, 'set');

    // Manuel satışları getir
    $manual_sales = $wpdb->get_var($wpdb->prepare(
        "SELECT ABS(COALESCE(SUM(quantity), 0)) FROM $movements_table 
        WHERE product_id = %d AND vendor_id = %d AND movement_type = 'sale'",
        $product_id, $vendor_id
    ));

    $manual_sales = intval($manual_sales);
    $online_sales = $product->get_total_sales();

    wp_send_json_success(array(
        'new_stock' => $new_stock,
        'manual_sales' => $manual_sales,
        'total_sales' => $online_sales + $manual_sales,
        'message' => $movement_type === 'add' ? 
            'Stok başarıyla eklendi.' : 
            'Manuel satış başarıyla kaydedildi.'
    ));
}
add_action('wp_ajax_update_stock', 'update_stock_ajax');

// Stok geçmişi HTML'ini oluşturan yardımcı fonksiyonu güncelle
function get_stock_history_html($product_id, $vendor_id, $start_date = null, $end_date = null, $page = 1) {
    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';
    $output = '';

    // Her sayfada gösterilecek kayıt sayısı
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    // Sorgu için tarih filtresi hazırla
    $where_clause = "WHERE product_id = %d AND vendor_id = %d";
    $query_args = array($product_id, $vendor_id);

    if ($start_date && $end_date) {
        $where_clause .= " AND DATE(movement_date) BETWEEN %s AND %s";
        $query_args[] = $start_date;
        $query_args[] = $end_date;
    }

    // Toplam kayıt sayısını bul
    $total_query = "SELECT COUNT(*) FROM $movements_table $where_clause";
    $total_items = $wpdb->get_var($wpdb->prepare($total_query, $query_args));
    $total_pages = ceil($total_items / $per_page);

    // Stok hareketlerini sorgula
    $movements = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $movements_table $where_clause ORDER BY movement_date DESC LIMIT %d OFFSET %d",
        array_merge($query_args, array($per_page, $offset))
    ));

    // Filtreleme alanı
    $output .= '<div class="history-filter">
        <form class="history-filter-form">
            <input type="hidden" name="product_id" value="' . esc_attr($product_id) . '">
            <div class="date-inputs">
                <label>Başlangıç: <input type="date" name="start_date" value="' . esc_attr($start_date) . '"></label>
                <label>Bitiş: <input type="date" name="end_date" value="' . esc_attr($end_date) . '"></label>
            </div>
            <div class="filter-actions">
                <button type="submit" class="button apply-filter">Filtrele</button>
                <button type="button" class="button clear-filter">Temizle</button>
                <button type="button" class="button export-history-excel" data-product-id="' . esc_attr($product_id) . '">Excel\'e Aktar</button>
            </div>
        </form>
    </div>';

    if (empty($movements)) {
        $output .= '<p>Hareket bulunamadı.</p>';
        return $output;
    }

    // Hareket tablosu
    $output .= '<table class="history-table widefat">
        <thead>
            <tr>
                <th>Tarih</th>
                <th>İşlem</th>
                <th>Miktar</th>
                <th>Stok Durumu</th>
                <th>Not</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($movements as $movement) {
        $type = match($movement->movement_type) {
            'add' => 'Stok Ekleme',
            'sale' => 'Manuel Satış',
            'online_sale' => 'Online Satış', 
            'delete' => 'Stok Silme',
            'set' => 'Stok Güncelleme',
            default => 'Diğer'
        };
        
        $quantity = abs($movement->quantity);
        $date = wp_date('d.m.Y H:i', strtotime($movement->movement_date));
        
        // Online satış için özel stil sınıfı ekle
        $type_class = $movement->movement_type === 'online_sale' ? 'class="online-sale"' : '';
        
        $output .= sprintf(
            '<tr class="history-row">
                <td>%s</td>
                <td><span %s>%s</span></td>
                <td>%d</td>
                <td>%d</td>
                <td>%s</td>
            </tr>',
            esc_html($date),
            $type_class,
            esc_html($type),
            esc_html($quantity),
            esc_html($movement->current_stock),
            esc_html($movement->notes)
        );
    }

    $output .= '</tbody></table>';

    // Sayfalama
    if ($total_pages > 1) {
        $output .= '<div class="history-pagination">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $active_class = ($i == $page) ? 'active' : '';
            $output .= '<span class="page-num ' . $active_class . '" data-page="' . $i . '">' . $i . '</span>';
        }
        $output .= '</div>';
    }

    return $output;
}

// Geçmiş için AJAX handler
function get_stock_history_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }

    $current_user = wp_get_current_user();
    if (!in_array('seller', $current_user->roles) && 
        !in_array('vendor', $current_user->roles) && 
        !in_array('wcfm_vendor', $current_user->roles) && 
        !in_array('dc_vendor', $current_user->roles)) {
        wp_send_json_error('Bu işlem için yetkiniz yok');
        return;
    }

    $product_id = intval($_POST['product_id']);
    $vendor_id = get_current_user_id();
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    
    $history_html = get_stock_history_html($product_id, $vendor_id, $start_date, $end_date, $page);
    wp_send_json_success($history_html);
}
add_action('wp_ajax_get_stock_history', 'get_stock_history_ajax');

// Kategori ve etiketleri getiren AJAX handler
function get_product_taxonomies_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }

    // Kategorileri al
    $categories = get_terms([
        'taxonomy' => 'product_cat',
        'hide_empty' => false,
    ]);

    // Etiketleri al
    $tags = get_terms([
        'taxonomy' => 'product_tag',
        'hide_empty' => false,
    ]);

    wp_send_json_success([
        'categories' => $categories,
        'tags' => $tags
    ]);
}
add_action('wp_ajax_get_product_taxonomies', 'get_product_taxonomies_ajax');

// Ürün ekleme fonksiyonunu güncelle
function add_new_product_ajax() {
    try {
        error_log('ADD NEW PRODUCT - Başlangıç');
        error_log('POST verisi: ' . print_r($_POST, true));
        
        if (!check_ajax_referer('dokan-stock-security', 'security', false)) {
            wp_send_json_error('Güvenlik doğrulaması başarısız');
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error('Oturum açmanız gerekiyor');
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('seller', $current_user->roles) && 
            !in_array('vendor', $current_user->roles) && 
            !in_array('wcfm_vendor', $current_user->roles) && 
            !in_array('dc_vendor', $current_user->roles)) {
            wp_send_json_error('Bu işlem için yetkiniz yok');
            return;
        }

        if (!isset($_POST['product_data'])) {
            wp_send_json_error('Ürün verisi bulunamadı');
            return;
        }

        $product_data = $_POST['product_data'];
        
        if (!class_exists('WC_Product_Simple')) {
            wp_send_json_error('WooCommerce aktif değil');
            return;
        }

        try {
            $product = new WC_Product_Simple();
            
            // Temel bilgiler
            $product->set_name(sanitize_text_field($product_data['name']));
            $product->set_status('publish');
            $product->set_regular_price(strval(floatval($product_data['price'])));
            $product->set_description(wp_kses_post($product_data['description']));
            $product->set_short_description(wp_kses_post($product_data['short_description']));
            $product->set_stock_quantity(intval($product_data['stock']));
            $product->set_manage_stock(true);
            $product->set_stock_status('instock');
            
            if (!empty($product_data['sku'])) {
                $product->set_sku(sanitize_text_field($product_data['sku']));
            }
            
            // Kategoriler
            if (!empty($product_data['categories'])) {
                $product->set_category_ids(array_map('intval', $product_data['categories']));
            }
            
            // Etiketler
            if (!empty($product_data['tags'])) {
                $product->set_tag_ids(array_map('intval', $product_data['tags']));
            }
            
            // Ana görsel
            if (!empty($product_data['image_id'])) {
                $product->set_image_id(intval($product_data['image_id']));
            }
            
            // Galeri görselleri
            if (!empty($product_data['gallery_ids'])) {
                $product->set_gallery_image_ids(array_map('intval', $product_data['gallery_ids']));
            }

            $product_id = $product->save();
            
            wp_send_json_success([
                'message' => 'Ürün başarıyla eklendi',
                'product_id' => $product_id
            ]);

        } catch (Exception $e) {
            error_log('ADD NEW PRODUCT - Ürün oluşturma hatası: ' . $e->getMessage());
            wp_send_json_error('Ürün oluşturulurken hata: ' . $e->getMessage());
        }

    } catch (Exception $e) {
        error_log('ADD NEW PRODUCT - Genel hata: ' . $e->getMessage());
        wp_send_json_error('İşlem sırasında hata: ' . $e->getMessage());
    }
}
add_action('wp_ajax_add_new_product', 'add_new_product_ajax');

// Stok miktarını direkt güncelleme için AJAX handler
function set_stock_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    // Giriş ve yetki kontrolü
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }

    $current_user = wp_get_current_user();
    if (!in_array('seller', $current_user->roles) && 
        !in_array('vendor', $current_user->roles) && 
        !in_array('wcfm_vendor', $current_user->roles) && 
        !in_array('dc_vendor', $current_user->roles)) {
        wp_send_json_error('Bu işlem için yetkiniz yok');
        return;
    }

    if (!isset($_POST['product_id']) || !isset($_POST['new_stock'])) {
        wp_send_json_error('Geçersiz istek');
    }

    $product_id = intval($_POST['product_id']);
    $new_stock = intval($_POST['new_stock']);
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    $vendor_id = get_current_user_id();

    // Ürünü al
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error('Ürün bulunamadı.');
    }

    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';

    // Mevcut stok miktarını al
    $current_stock = $product->get_stock_quantity();
    
    // Hareket kaydını ekle
    $result = $wpdb->insert(
        $movements_table,
        array(
            'vendor_id' => $vendor_id,
            'product_id' => $product_id,
            'quantity' => $new_stock - $current_stock, // Fark kadar hareket
            'movement_type' => 'set',
            'movement_date' => current_time('mysql'),
            'notes' => $notes,
            'current_stock' => $new_stock
        ),
        array('%d', '%d', '%d', '%s', '%s', '%s', '%d')
    );

    if ($result === false) {
        wp_send_json_error('İşlem kaydedilemedi: ' . $wpdb->last_error);
        return;
    }

    // WooCommerce stok güncelleme
    wc_update_product_stock($product, $new_stock, 'set');

    wp_send_json_success(array(
        'new_stock' => $new_stock,
        'message' => 'Stok başarıyla güncellendi.'
    ));
}
add_action('wp_ajax_set_stock', 'set_stock_ajax');

// Stok silme işlemi için AJAX handler ekle
function delete_stock_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    // Giriş ve yetki kontrolü
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }

    $current_user = wp_get_current_user();
    if (!in_array('seller', $current_user->roles) && 
        !in_array('vendor', $current_user->roles) && 
        !in_array('wcfm_vendor', $current_user->roles) && 
        !in_array('dc_vendor', $current_user->roles)) {
        wp_send_json_error('Bu işlem için yetkiniz yok');
        return;
    }

    if (!isset($_POST['product_id']) || !isset($_POST['quantity'])) {
        wp_send_json_error('Geçersiz istek');
    }

    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $notes = sanitize_textarea_field($_POST['notes'] ?? '');
    $vendor_id = get_current_user_id();

    // Ürünü al
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error('Ürün bulunamadı.');
    }

    // Mevcut stok miktarını al
    $current_stock = $product->get_stock_quantity();
    
    // Silinecek miktar mevcut stoktan fazla olamaz
    if ($quantity > $current_stock) {
        wp_send_json_error('Silinecek miktar mevcut stoktan fazla olamaz.');
        return;
    }

    // Yeni stok miktarı
    $new_stock = $current_stock - $quantity;

    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';
    
    // Hareket kaydını ekle
    $result = $wpdb->insert(
        $movements_table,
        array(
            'vendor_id' => $vendor_id,
            'product_id' => $product_id,
            'quantity' => -$quantity,
            'movement_type' => 'delete',
            'movement_date' => current_time('mysql'),
            'notes' => $notes,
            'current_stock' => $new_stock
        ),
        array('%d', '%d', '%d', '%s', '%s', '%s', '%d')
    );

    if ($result === false) {
        wp_send_json_error('İşlem kaydedilemedi: ' . $wpdb->last_error);
        return;
    }

    // WooCommerce stok güncelleme
    wc_update_product_stock($product, $new_stock, 'set');

    wp_send_json_success(array(
        'new_stock' => $new_stock,
        'message' => 'Stok başarıyla silindi.'
    ));
}
add_action('wp_ajax_delete_stock', 'delete_stock_ajax');

// Ürün silme işlemi için AJAX handler
function delete_product_ajax() {
    try {
        error_log('DELETE PRODUCT - Başlangıç');
        error_log('POST verisi: ' . print_r($_POST, true));

        if (!check_ajax_referer('dokan-stock-security', 'security', false)) {
            error_log('DELETE PRODUCT - Nonce hatası');
            wp_send_json_error('Güvenlik doğrulaması başarısız');
            return;
        }

        if (!is_user_logged_in()) {
            error_log('DELETE PRODUCT - Kullanıcı giriş yapmamış');
            wp_send_json_error('Oturum açmanız gerekiyor');
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('seller', $current_user->roles) && 
            !in_array('vendor', $current_user->roles) && 
            !in_array('wcfm_vendor', $current_user->roles) && 
            !in_array('dc_vendor', $current_user->roles)) {
            error_log('DELETE PRODUCT - Yetkisiz kullanıcı');
            wp_send_json_error('Bu işlem için yetkiniz yok');
            return;
        }

        if (!isset($_POST['product_id'])) {
            error_log('DELETE PRODUCT - Ürün ID eksik');
            wp_send_json_error('Geçersiz istek: Ürün ID eksik');
            return;
        }

        $product_id = intval($_POST['product_id']);
        
        // Ürün nesnesini al
        $product = wc_get_product($product_id);
        
        if (!$product) {
            error_log('DELETE PRODUCT - Ürün bulunamadı: ' . $product_id);
            wp_send_json_error('Ürün bulunamadı');
            return;
        }

        // Ürünün yazarını kontrol et
        $post = get_post($product_id);
        if ($post->post_author != get_current_user_id()) {
            error_log('DELETE PRODUCT - Yetkisiz silme girişimi. Ürün ID: ' . $product_id);
            wp_send_json_error('Bu ürünü silme yetkiniz yok');
            return;
        }

        // Önce stok hareketlerini sil
        global $wpdb;
        $movements_table = $wpdb->prefix . 'dokan_stock_movements';
        $wpdb->delete(
            $movements_table,
            array('product_id' => $product_id),
            array('%d')
        );

        // Sonra ürünü sil
        $result = wp_delete_post($product_id, true);

        if (!$result) {
            error_log('DELETE PRODUCT - Silme hatası. Ürün ID: ' . $product_id);
            wp_send_json_error('Ürün silinirken bir hata oluştu');
            return;
        }

        error_log('DELETE PRODUCT - Başarılı. Ürün ID: ' . $product_id);
        wp_send_json_success(array(
            'message' => 'Ürün başarıyla silindi',
            'product_id' => $product_id
        ));

    } catch (Exception $e) {
        error_log('DELETE PRODUCT - Hata: ' . $e->getMessage());
        wp_send_json_error('İşlem sırasında bir hata oluştu: ' . $e->getMessage());
    }
}
add_action('wp_ajax_delete_product', 'delete_product_ajax');

// Ürün verilerini getiren AJAX işleyici
function get_product_data_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }
    
    if (!isset($_POST['product_id'])) {
        wp_send_json_error('Ürün ID eksik');
        return;
    }
    
    $product_id = intval($_POST['product_id']);
    $product = wc_get_product($product_id);
    
    if (!$product) {
        wp_send_json_error('Ürün bulunamadı');
        return;
    }
    
    // Kategori ve etiketleri al
    $categories = wp_get_object_terms($product_id, 'product_cat', array('fields' => 'ids'));
    $tags = wp_get_object_terms($product_id, 'product_tag', array('fields' => 'ids'));
    
    // Galeri görsellerinin URL'lerini al
    $gallery_ids = $product->get_gallery_image_ids();
    $gallery_urls = array();
    
    foreach($gallery_ids as $image_id) {
        $gallery_urls[] = wp_get_attachment_image_url($image_id, 'thumbnail');
    }
    
    // Ürün verilerini hazırla
    $product_data = array(
        'id' => $product_id,
        'name' => $product->get_name(),
        'sku' => $product->get_sku(),
        'price' => $product->get_regular_price(),
        'stock' => $product->get_stock_quantity(),
        'short_description' => $product->get_short_description(),
        'description' => $product->get_description(),
        'categories' => $categories,
        'tags' => $tags,
        'image_id' => $product->get_image_id(),
        'image_url' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
        'gallery_ids' => $gallery_ids,
        'gallery_urls' => $gallery_urls
    );
    
    wp_send_json_success($product_data);
}
add_action('wp_ajax_get_product_data', 'get_product_data_ajax');

// Ürün güncelleme AJAX işleyici
function update_product_ajax() {
    try {
        error_log('UPDATE PRODUCT - Başlangıç');
        error_log('POST verisi: ' . print_r($_POST, true));
        
        if (!check_ajax_referer('dokan-stock-security', 'security', false)) {
            wp_send_json_error('Güvenlik doğrulaması başarısız');
            return;
        }

        if (!is_user_logged_in()) {
            wp_send_json_error('Oturum açmanız gerekiyor');
            return;
        }

        $current_user = wp_get_current_user();
        if (!in_array('seller', $current_user->roles) && 
            !in_array('vendor', $current_user->roles) && 
            !in_array('wcfm_vendor', $current_user->roles) && 
            !in_array('dc_vendor', $current_user->roles)) {
            wp_send_json_error('Bu işlem için yetkiniz yok');
            return;
        }

        if (!isset($_POST['product_data'])) {
            wp_send_json_error('Ürün verisi bulunamadı');
            return;
        }

        $product_data = $_POST['product_data'];
        
        if (!isset($product_data['product_id'])) {
            wp_send_json_error('Ürün ID eksik');
            return;
        }
        
        $product_id = intval($product_data['product_id']);
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error('Ürün bulunamadı');
            return;
        }
        
        // Ürünün yazarını kontrol et
        $post = get_post($product_id);
        if ($post->post_author != get_current_user_id()) {
            wp_send_json_error('Bu ürünü düzenleme yetkiniz yok');
            return;
        }

        try {
            // Temel bilgileri güncelle
            $product->set_name(sanitize_text_field($product_data['name']));
            $product->set_regular_price(strval(floatval($product_data['price'])));
            $product->set_description(wp_kses_post($product_data['description']));
            $product->set_short_description(wp_kses_post($product_data['short_description']));
            $product->set_stock_quantity(intval($product_data['stock']));
            
            if (!empty($product_data['sku'])) {
                $product->set_sku(sanitize_text_field($product_data['sku']));
            }
            
            // Kategoriler
            if (!empty($product_data['categories'])) {
                $product->set_category_ids(array_map('intval', $product_data['categories']));
            }
            
            // Etiketler
            if (!empty($product_data['tags'])) {
                $product->set_tag_ids(array_map('intval', $product_data['tags']));
            }
            
            // Ana görsel
            if (!empty($product_data['image_id'])) {
                $product->set_image_id(intval($product_data['image_id']));
            }
            
            // Galeri görselleri
            if (!empty($product_data['gallery_ids']) && is_array($product_data['gallery_ids'])) {
                $gallery_ids = array_filter($product_data['gallery_ids'], function($value) {
                    return !empty($value);
                });
                if (!empty($gallery_ids)) {
                    $product->set_gallery_image_ids(array_map('intval', $gallery_ids));
                }
            }

            $product->save();
            
            wp_send_json_success([
                'message' => 'Ürün başarıyla güncellendi',
                'product_id' => $product_id
            ]);

        } catch (Exception $e) {
            error_log('UPDATE PRODUCT - Ürün güncelleme hatası: ' . $e->getMessage());
            wp_send_json_error('Ürün güncellenirken hata: ' . $e->getMessage());
        }

    } catch (Exception $e) {
        error_log('UPDATE PRODUCT - Genel hata: ' . $e->getMessage());
        wp_send_json_error('İşlem sırasında hata: ' . $e->getMessage());
    }
}
add_action('wp_ajax_update_product', 'update_product_ajax');

// Kritik stok seviyesini ayarlama için AJAX handler
function update_critical_stock_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }
    
    $current_user = wp_get_current_user();
    if (!in_array('seller', $current_user->roles) && 
        !in_array('vendor', $current_user->roles) && 
        !in_array('wcfm_vendor', $current_user->roles) && 
        !in_array('dc_vendor', $current_user->roles)) {
        wp_send_json_error('Bu işlem için yetkiniz yok');
        return;
    }
    
    if (!isset($_POST['product_id']) || !isset($_POST['critical_level'])) {
        wp_send_json_error('Geçersiz istek');
    }
    
    $product_id = intval($_POST['product_id']);
    $critical_level = intval($_POST['critical_level']);
    
    // Kritik stok seviyesini ürün meta verisi olarak kaydet
    update_post_meta($product_id, '_critical_stock_level', $critical_level);
    
    wp_send_json_success(array(
        'message' => 'Kritik stok seviyesi başarıyla güncellendi.',
        'critical_level' => $critical_level
    ));
}
add_action('wp_ajax_update_critical_stock', 'update_critical_stock_ajax');

// Kritik stok seviyesini kontrol eden fonksiyon
function check_critical_stock_levels() {
    // Satıcının ürünlerini al
    $vendor_id = get_current_user_id();
    $args = array(
        'author' => $vendor_id,
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish'
    );
    
    $products_query = new WP_Query($args);
    $critical_products = array();
    
    if ($products_query->have_posts()) {
        while ($products_query->have_posts()) {
            $products_query->the_post();
            $product = wc_get_product(get_the_ID());
            $product_id = $product->get_id();
            
            // Kritik stok seviyesini al
            $critical_level = get_post_meta($product_id, '_critical_stock_level', true);
            if (!$critical_level) {
                $critical_level = 5; // varsayılan değer
            }
            
            // Mevcut stok ile karşılaştır
            $current_stock = $product->get_stock_quantity();
            if ($current_stock <= $critical_level) {
                $critical_products[] = array(
                    'id' => $product_id,
                    'name' => $product->get_name(),
                    'stock' => $current_stock,
                    'critical_level' => $critical_level
                );
            }
        }
    }
    
    wp_reset_postdata();
    return $critical_products;
}

// Debug log fonksiyonu
function dokan_stock_debug_log($message, $data = null) {
    if (defined('WP_DEBUG') && WP_DEBUG === true) {
        $log_entry = '[Dokan Stock] ' . $message;
        
        if ($data !== null) {
            if (is_array($data) || is_object($data)) {
                $log_entry .= ' Data: ' . print_r($data, true);
            } else {
                $log_entry .= ' Data: ' . $data;
            }
        }
        
        error_log($log_entry);
    }
}

// get_critical_stocks_ajax fonksiyonunda hata ayıklama ekleyelim
function get_critical_stocks_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }
    
    try {
        $vendor_id = get_current_user_id();
        
        // Log kaydı ekle
        dokan_stock_debug_log('Kritik stok kontrolü başlatıldı. Satıcı ID: ' . $vendor_id);
        
        // Kritik stoktaki ürünleri al
        $critical_products = check_critical_stock_levels();
        
        // Çıktı oluştur
        if (!empty($critical_products)) {
            dokan_stock_debug_log('Kritik stok ürünleri bulundu. Adet: ' . count($critical_products));
            
            $output = '<div class="critical-stock-notification">';
            $output .= '<h4>Kritik Stok Seviyesindeki Ürünler</h4>';
            $output .= '<table class="critical-stock-table">';
            $output .= '<thead><tr>
                <th>Ürün</th>
                <th>Mevcut Stok</th>
                <th>Kritik Seviye</th>
                <th>Durum</th>
                <th>İşlem</th>
            </tr></thead>';
            $output .= '<tbody>';
            
            foreach ($critical_products as $product) {
                // Kritik seviye sınıfı belirleme
                $critical_class = '';
                $critical_text = '';
                
                $stock_percentage = ($product['stock'] / $product['critical_level']) * 100;
                
                if ($stock_percentage <= 25) {
                    $critical_class = 'critical-level-severe';
                    $critical_text = 'Acil';
                } elseif ($stock_percentage <= 50) {
                    $critical_class = 'critical-level-warning';
                    $critical_text = 'Uyarı';
                } else {
                    $critical_class = 'critical-level-attention';
                    $critical_text = 'Dikkat';
                }
                
                $output .= sprintf(
                    '<tr class="critical-stock-row">
                        <td>%s</td>
                        <td>%d</td>
                        <td>%d</td>
                        <td><span class="critical-level-indicator %s">%s</span></td>
                        <td>
                            <button class="button set-critical-level-btn" data-product-id="%d" data-critical-level="%d">Kritik Seviye Ayarla</button>
                        </td>
                    </tr>',
                    esc_html($product['name']),
                    esc_html($product['stock']),
                    esc_html($product['critical_level']),
                    $critical_class,
                    $critical_text,
                    $product['id'],
                    $product['critical_level']
                );
            }
            
            $output .= '</tbody></table>';
            $output .= '</div>';
        } else {
            dokan_stock_debug_log('Kritik stok ürünü bulunamadı.');
            $output = '<div class="empty-critical-stocks"><div class="success-icon">✓</div><p>Kritik stok seviyesinde ürün bulunmamaktadır.</p></div>';
        }
        
        wp_send_json_success($output);
    } catch (Exception $e) {
        dokan_stock_debug_log('Kritik stok kontrolü hatası: ' . $e->getMessage());
        wp_send_json_error('İşlem sırasında bir hata oluştu: ' . $e->getMessage());
    }
}
add_action('wp_ajax_get_critical_stocks', 'get_critical_stocks_ajax');

// Stok takip sayfasına kritik stok güncelleme düğmesi ekleme
function add_critical_stock_button($output, $product, $product_id) {
    $critical_level = get_post_meta($product_id, '_critical_stock_level', true) ?: 5;
    $button_html = sprintf(
        '<button class="button critical-stock-btn" data-product-id="%d" data-critical-level="%d">Kritik Stok Güncelle</button>',
        $product_id,
        $critical_level
    );
    return $button_html;
}

// Stok raporu oluşturan AJAX handler
function generate_stock_report_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }
    
    $current_user = wp_get_current_user();
    if (!in_array('seller', $current_user->roles) && 
        !in_array('vendor', $current_user->roles) && 
        !in_array('wcfm_vendor', $current_user->roles) && 
        !in_array('dc_vendor', $current_user->roles)) {
        wp_send_json_error('Bu işlem için yetkiniz yok');
        return;
    }
    
    $vendor_id = get_current_user_id();
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');
    
    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';
    
    // Stok hareketlerini sorgula
    $movements = $wpdb->get_results($wpdb->prepare(
        "SELECT m.*, p.post_title as product_name 
        FROM $movements_table m
        JOIN {$wpdb->posts} p ON m.product_id = p.ID
        WHERE m.vendor_id = %d 
        AND DATE(m.movement_date) BETWEEN %s AND %s
        ORDER BY m.movement_date DESC",
        $vendor_id, $start_date, $end_date
    ));
    
    // Özet verileri hesapla
    $summary = array(
        'total_products' => 0,
        'total_movements' => count($movements),
        'stock_added' => 0,
        'stock_removed' => 0,
        'stock_sold' => 0,
        'most_active_products' => array()
    );
    
    $product_movements = array();
    $unique_products = array();
    
    foreach ($movements as $movement) {
        if (!isset($unique_products[$movement->product_id])) {
            $unique_products[$movement->product_id] = $movement->product_name;
        }
        
        if (!isset($product_movements[$movement->product_id])) {
            $product_movements[$movement->product_id] = array(
                'name' => $movement->product_name,
                'movements' => 0,
                'stock_added' => 0,
                'stock_removed' => 0,
                'stock_sold' => 0
            );
        }
        
        $product_movements[$movement->product_id]['movements']++;
        
        if ($movement->quantity > 0 && $movement->movement_type == 'add') {
            $summary['stock_added'] += $movement->quantity;
            $product_movements[$movement->product_id]['stock_added'] += $movement->quantity;
        } elseif ($movement->quantity < 0 && $movement->movement_type == 'sale') {
            $summary['stock_sold'] += abs($movement->quantity);
            $product_movements[$movement->product_id]['stock_sold'] += abs($movement->quantity);
        } elseif ($movement->quantity < 0) {
            $summary['stock_removed'] += abs($movement->quantity);
            $product_movements[$movement->product_id]['stock_removed'] += abs($movement->quantity);
        }
    }
    
    $summary['total_products'] = count($unique_products);
    
    // En aktif ürünleri bul
    uasort($product_movements, function($a, $b) {
        return $b['movements'] - $a['movements'];
    });
    
    $summary['most_active_products'] = array_slice($product_movements, 0, 5, true);
    
    // Rapor HTML'ini oluştur
    $report_html = '<div class="stock-report">';
    $report_html .= '<h3>Stok Hareketleri Raporu</h3>';
    $report_html .= '<p>' . $start_date . ' - ' . $end_date . ' tarihleri arası</p>';
    
    // Rapor özeti
    $report_html .= '<div class="report-summary">';
    $report_html .= '<h4>Özet</h4>';
    $report_html .= '<ul>';
    $report_html .= '<li>Toplam Ürün: ' . $summary['total_products'] . '</li>';
    $report_html .= '<li>Toplam Hareket: ' . $summary['total_movements'] . '</li>';
    $report_html .= '<li>Eklenen Toplam Stok: ' . $summary['stock_added'] . '</li>';
    $report_html .= '<li>Satılan Toplam Stok: ' . $summary['stock_sold'] . '</li>';
    $report_html .= '<li>Silinen Toplam Stok: ' . $summary['stock_removed'] . '</li>';
    $report_html .= '</ul>';
    $report_html .= '</div>';
    
    // En aktif ürünler
    $report_html .= '<div class="most-active-products">';
    $report_html .= '<h4>En Aktif Ürünler</h4>';
    $report_html .= '<table class="widefat">';
    $report_html .= '<thead><tr><th>Ürün</th><th>Hareket Sayısı</th><th>Eklenen</th><th>Satılan</th><th>Silinen</th></tr></thead>';
    $report_html .= '<tbody>';
    
    foreach ($summary['most_active_products'] as $product_id => $data) {
        $report_html .= sprintf(
            '<tr>
                <td>%s</td>
                <td>%d</td>
                <td>%d</td>
                <td>%d</td>
                <td>%d</td>
            </tr>',
            esc_html($data['name']),
            $data['movements'],
            $data['stock_added'],
            $data['stock_sold'],
            $data['stock_removed']
        );
    }
    
    $report_html .= '</tbody></table>';
    $report_html .= '</div>';
    
    // Tüm hareketler tablosu
    $report_html .= '<div class="all-movements">';
    $report_html .= '<h4>Tüm Stok Hareketleri</h4>';
    $report_html .= '<table class="widefat">';
    $report_html .= '<thead><tr><th>Tarih</th><th>Ürün</th><th>İşlem</th><th>Miktar</th><th>Stok Durumu</th><th>Not</th></tr></thead>';
    $report_html .= '<tbody>';
    
    foreach ($movements as $movement) {
        $type = match($movement->movement_type) {
            'add' => 'Stok Ekleme',
            'sale' => 'Manuel Satış',
            'online_sale' => 'Online Satış', 
            'delete' => 'Stok Silme',
            'set' => 'Stok Güncelleme',
            default => 'Diğer'
        };
        
        $quantity = abs($movement->quantity);
        $date = wp_date('d.m.Y H:i', strtotime($movement->movement_date));
        
        $report_html .= sprintf(
            '<tr>
                <td>%s</td>
                <td>%s</td>
                <td>%s</td>
                <td>%d</td>
                <td>%d</td>
                <td>%s</td>
            </tr>',
            esc_html($date),
            esc_html($movement->product_name),
            esc_html($type),
            esc_html($quantity),
            esc_html($movement->current_stock),
            esc_html($movement->notes)
        );
    }
    
    $report_html .= '</tbody></table>';
    $report_html .= '</div>';
    
    // Excel'e aktar butonu
    $report_html .= '<div class="report-actions">';
    $report_html .= '<button class="button export-excel" data-start="' . esc_attr($start_date) . '" data-end="' . esc_attr($end_date) . '">Excel\'e Aktar</button>';
    $report_html .= '<button class="button close-report">Kapat</button>';
    $report_html .= '</div>';
    
    $report_html .= '</div>';
    
    wp_send_json_success($report_html);
}
add_action('wp_ajax_generate_stock_report', 'generate_stock_report_ajax');

// Excel raporu oluşturan AJAX handler
function export_stock_report_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }
    
    $vendor_id = get_current_user_id();
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : date('Y-m-d', strtotime('-30 days'));
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : date('Y-m-d');
    
    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';
    
    // Stok hareketlerini sorgula
    $movements = $wpdb->get_results($wpdb->prepare(
        "SELECT m.*, p.post_title as product_name 
        FROM $movements_table m
        JOIN {$wpdb->posts} p ON m.product_id = p.ID
        WHERE m.vendor_id = %d 
        AND DATE(m.movement_date) BETWEEN %s AND %s
        ORDER BY m.movement_date DESC",
        $vendor_id, $start_date, $end_date
    ));
    
    // CSV verisi oluştur
    $csv_data = array(
        array('Tarih', 'Ürün', 'İşlem', 'Miktar', 'Stok Durumu', 'Not')
    );
    
    foreach ($movements as $movement) {
        $type = match($movement->movement_type) {
            'add' => 'Stok Ekleme',
            'sale' => 'Manuel Satış',
            'online_sale' => 'Online Satış', 
            'delete' => 'Stok Silme',
            'set' => 'Stok Güncelleme',
            default => 'Diğer'
        };
        
        $quantity = abs($movement->quantity);
        $date = wp_date('d.m.Y H:i', strtotime($movement->movement_date));
        
        $csv_data[] = array(
            $date,
            $movement->product_name,
            $type,
            $quantity,
            $movement->current_stock,
            $movement->notes
        );
    }
    
    // CSV içeriğini oluştur
    $csv_content = '';
    foreach ($csv_data as $row) {
        $csv_content .= implode(',', array_map(function($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $row)) . "\n";
    }
    
    // CSV'yi indirilecek veri olarak gönder
    $filename = 'stok_raporu_' . $start_date . '_' . $end_date . '.csv';
    
    // Base64 şifrelemesi yapalım
    $base64_content = base64_encode($csv_content);
    
    wp_send_json_success(array(
        'filename' => $filename,
        'content' => $base64_content
    ));
}
add_action('wp_ajax_export_stock_report', 'export_stock_report_ajax');

// En çok ve en az satılan ürünleri getiren AJAX handler
function sales_analysis_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }
    
    $vendor_id = get_current_user_id();
    $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'monthly';
    
    // Tarih aralığını belirle
    switch ($period) {
        case 'weekly':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $period_text = 'Son 7 gün';
            break;
        case 'monthly':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $period_text = 'Son 30 gün';
            break;
        case 'quarterly':
            $start_date = date('Y-m-d', strtotime('-90 days'));
            $period_text = 'Son 90 gün';
            break;
        case 'yearly':
            $start_date = date('Y-m-d', strtotime('-365 days'));
            $period_text = 'Son 365 gün';
            break;
        default:
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $period_text = 'Son 30 gün';
    }
    
    $end_date = date('Y-m-d');
    
    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';
    
    // Manuel satışlar (stok hareketlerinden)
    $manual_sales = $wpdb->get_results($wpdb->prepare(
        "SELECT m.product_id, p.post_title as product_name, ABS(SUM(m.quantity)) as quantity
        FROM $movements_table m
        JOIN {$wpdb->posts} p ON m.product_id = p.ID
        WHERE m.vendor_id = %d 
        AND m.movement_type = 'sale'
        AND DATE(m.movement_date) BETWEEN %s AND %s
        GROUP BY m.product_id
        ORDER BY quantity DESC",
        $vendor_id, $start_date, $end_date
    ));
    
    // Online satışlar (WooCommerce'dan)
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'author'         => $vendor_id
    );
    
    $products_query = new WP_Query($args);
    $online_sales = array();
    
    if ($products_query->have_posts()) {
        while ($products_query->have_posts()) {
            $products_query->the_post();
            $product = wc_get_product(get_the_ID());
            $product_id = $product->get_id();
            
            // Belirli bir tarih aralığındaki WooCommerce siparişlerini kontrol et
            $order_items = $wpdb->get_row($wpdb->prepare(
                "SELECT SUM(oim.meta_value) as total_qty 
                FROM {$wpdb->prefix}woocommerce_order_items oi
                JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id
                JOIN {$wpdb->posts} p ON p.ID = oi.order_id
                WHERE oim.meta_key = '_qty'
                AND oim2.meta_key = '_product_id'
                AND oim2.meta_value = %d
                AND p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
                AND p.post_date BETWEEN %s AND %s",
                $product_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59'
            ));
            
            $online_qty = $order_items ? floatval($order_items->total_qty) : 0;
            
            if ($online_qty > 0) {
                $online_sales[] = array(
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'quantity' => $online_qty
                );
            }
        }
    }
    
    wp_reset_postdata();
    
    // Online satışları sırala
    usort($online_sales, function($a, $b) {
        return $b['quantity'] - $a['quantity'];
    });
    
    // Toplam satışları hesapla
    $total_sales = array();
    
    // Manuel satışları ekle
    foreach ($manual_sales as $sale) {
        $total_sales[$sale->product_id] = array(
            'product_id' => $sale->product_id,
            'product_name' => $sale->product_name,
            'manual_sales' => $sale->quantity,
            'online_sales' => 0,
            'total_sales' => $sale->quantity
        );
    }
    
    // Online satışları ekle
    foreach ($online_sales as $sale) {
        if (isset($total_sales[$sale['product_id']])) {
            $total_sales[$sale['product_id']]['online_sales'] = $sale['quantity'];
            $total_sales[$sale['product_id']]['total_sales'] += $sale['quantity'];
        } else {
            $total_sales[$sale['product_id']] = array(
                'product_id' => $sale['product_id'],
                'product_name' => $sale['product_name'],
                'manual_sales' => 0,
                'online_sales' => $sale['quantity'],
                'total_sales' => $sale['quantity']
            );
        }
    }
    
    // Toplam satışlara göre sırala
    usort($total_sales, function($a, $b) {
        return $b['total_sales'] - $a['total_sales'];
    });
    
    // En çok ve en az satan ürünleri belirle
    $top_selling = array_slice($total_sales, 0, 5);
    $least_selling = array_slice(array_reverse($total_sales), 0, 5);
    
    // Grafik verilerini hazırla
    $chart_labels = array();
    $chart_data = array();
    
    foreach ($top_selling as $item) {
        $chart_labels[] = $item['product_name'];
        $chart_data[] = $item['total_sales'];
    }
    
    // Analiz HTML'ini oluştur
    $analysis_html = '<div class="sales-analysis-report">';
    $analysis_html .= '<h3>Satış Analizi Raporu</h3>';
    $analysis_html .= '<p>' . $period_text . ' içerisindeki satış verileri</p>';
    
    // Periyot seçimi
    $analysis_html .= '<div class="period-selector">';
    $analysis_html .= '<label>Periyot Seçin: </label>';
    $analysis_html .= '<select id="analysis-period">';
    $analysis_html .= '<option value="weekly" ' . selected($period, 'weekly', false) . '>Haftalık</option>';
    $analysis_html .= '<option value="monthly" ' . selected($period, 'monthly', false) . '>Aylık</option>';
    $analysis_html .= '<option value="quarterly" ' . selected($period, 'quarterly', false) . '>3 Aylık</option>';
    $analysis_html .= '<option value="yearly" ' . selected($period, 'yearly', false) . '>Yıllık</option>';
    $analysis_html .= '</select>';
    $analysis_html .= '<button class="button update-analysis">Güncelle</button>';
    $analysis_html .= '</div>';
    
    // Grafik için alan
    $analysis_html .= '<div class="sales-chart-container">';
    $analysis_html .= '<canvas id="salesChart" width="400" height="200"></canvas>';
    $analysis_html .= '</div>';
    
    // En çok satan ürünler tablosu
    $analysis_html .= '<div class="top-selling-products">';
    $analysis_html .= '<h4>En Çok Satan Ürünler</h4>';
    $analysis_html .= '<table class="widefat">';
    $analysis_html .= '<thead><tr><th>Ürün</th><th>Toplam Satış</th><th>Online Satış</th><th>Manuel Satış</th></tr></thead>';
    $analysis_html .= '<tbody>';
    
    foreach ($top_selling as $item) {
        $analysis_html .= sprintf(
            '<tr>
                <td>%s</td>
                <td>%d</td>
                <td>%d</td>
                <td>%d</td>
            </tr>',
            esc_html($item['product_name']),
            $item['total_sales'],
            $item['online_sales'],
            $item['manual_sales']
        );
    }
    
    $analysis_html .= '</tbody></table>';
    $analysis_html .= '</div>';
    
    // En az satan ürünler tablosu
    $analysis_html .= '<div class="least-selling-products">';
    $analysis_html .= '<h4>En Az Satan Ürünler</h4>';
    $analysis_html .= '<table class="widefat">';
    $analysis_html .= '<thead><tr><th>Ürün</th><th>Toplam Satış</th><th>Online Satış</th><th>Manuel Satış</th></tr></thead>';
    $analysis_html .= '<tbody>';
    
    foreach ($least_selling as $item) {
        $analysis_html .= sprintf(
            '<tr>
                <td>%s</td>
                <td>%d</td>
                <td>%d</td>
                <td>%d</td>
            </tr>',
            esc_html($item['product_name']),
            $item['total_sales'],
            $item['online_sales'],
            $item['manual_sales']
        );
    }
    
    $analysis_html .= '</tbody></table>';
    $analysis_html .= '</div>';
    
    // Analiz HTML'inde Excel butonu için kod düzenlemesi
    $analysis_html .= '<div class="analysis-actions">
        <button class="button export-sales-excel">Excel\'e Aktar</button>
        <button class="button close-analysis">Kapat</button>
    </div>';
    
    $analysis_html .= '</div>';
    
    // Chart.js kütüphanesini ekle
    $analysis_html .= '<script>
        if (typeof Chart === "undefined") {
            var script = document.createElement("script");
            script.src = "https://cdn.jsdelivr.net/npm/chart.js";
            script.onload = function() {
                initializeChart();
            };
            document.head.appendChild(script);
        } else {
            initializeChart();
        }
        
        function initializeChart() {
            var ctx = document.getElementById("salesChart").getContext("2d");
            var salesChart = new Chart(ctx, {
                type: "bar",
                data: {
                    labels: ' . json_encode($chart_labels) . ',
                    datasets: [{
                        label: "Satış Miktarı",
                        data: ' . json_encode($chart_data) . ',
                        backgroundColor: [
                            "rgba(54, 162, 235, 0.6)",
                            "rgba(75, 192, 192, 0.6)",
                            "rgba(255, 206, 86, 0.6)",
                            "rgba(153, 102, 255, 0.6)",
                            "rgba(255, 159, 64, 0.6)"
                        ],
                        borderColor: [
                            "rgba(54, 162, 235, 1)",
                            "rgba(75, 192, 192, 1)",
                            "rgba(255, 206, 86, 1)",
                            "rgba(153, 102, 255, 1)",
                            "rgba(255, 159, 64, 1)"
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
    </script>';
    
    wp_send_json_success($analysis_html);
}
add_action('wp_ajax_sales_analysis', 'sales_analysis_ajax');

// Kritik stok verilerini Excel formatında indirme AJAX handler
function export_critical_stocks_excel_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }
    
    $current_user = wp_get_current_user();
    if (!in_array('seller', $current_user->roles) && 
        !in_array('vendor', $current_user->roles) && 
        !in_array('wcfm_vendor', $current_user->roles) && 
        !in_array('dc_vendor', $current_user->roles)) {
        wp_send_json_error('Bu işlem için yetkiniz yok');
        return;
    }
    
    $critical_products = check_critical_stock_levels();
    
    if (empty($critical_products)) {
        wp_send_json_error('İndirilebilecek kritik stok verisi bulunamadı.');
        return;
    }
    
    // CSV verisi oluştur
    $csv_data = array(
        array('Ürün', 'Mevcut Stok', 'Kritik Seviye', 'Durum')
    );
    
    foreach ($critical_products as $product) {
        $stock_percentage = ($product['stock'] / $product['critical_level']) * 100;
        
        if ($stock_percentage <= 25) {
            $status = 'Acil';
        } elseif ($stock_percentage <= 50) {
            $status = 'Uyarı';
        } else {
            $status = 'Dikkat';
        }
        
        $csv_data[] = array(
            $product['name'],
            $product['stock'],
            $product['critical_level'],
            $status
        );
    }
    
    // CSV içeriğini oluştur
    $csv_content = '';
    foreach ($csv_data as $row) {
        $csv_content .= implode(',', array_map(function($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $row)) . "\n";
    }
    
    // CSV'yi indirilecek veri olarak gönder
    $filename = 'kritik_stok_raporu_' . date('Y-m-d') . '.csv';
    
    // Base64 şifrelemesi yapalım
    $base64_content = base64_encode($csv_content);
    
    wp_send_json_success(array(
        'filename' => $filename,
        'content' => $base64_content
    ));
}
add_action('wp_ajax_export_critical_stocks_excel', 'export_critical_stocks_excel_ajax');

// Satış analizi verilerini Excel formatında indirme AJAX handler
function export_sales_analysis_excel_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }
    
    $vendor_id = get_current_user_id();
    $period = isset($_POST['period']) ? sanitize_text_field($_POST['period']) : 'monthly';
    
    // Tarih aralığını belirle
    switch ($period) {
        case 'weekly':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $period_text = 'Son 7 gün';
            break;
        case 'monthly':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $period_text = 'Son 30 gün';
            break;
        case 'quarterly':
            $start_date = date('Y-m-d', strtotime('-90 days'));
            $period_text = 'Son 90 gün';
            break;
        case 'yearly':
            $start_date = date('Y-m-d', strtotime('-365 days'));
            $period_text = 'Son 365 gün';
            break;
        default:
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $period_text = 'Son 30 gün';
    }
    
    $end_date = date('Y-m-d');
    
    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';
    
    // Manuel satışlar (stok hareketlerinden)
    $manual_sales = $wpdb->get_results($wpdb->prepare(
        "SELECT m.product_id, p.post_title as product_name, ABS(SUM(m.quantity)) as quantity
        FROM $movements_table m
        JOIN {$wpdb->posts} p ON m.product_id = p.ID
        WHERE m.vendor_id = %d 
        AND m.movement_type = 'sale'
        AND DATE(m.movement_date) BETWEEN %s AND %s
        GROUP BY m.product_id
        ORDER BY quantity DESC",
        $vendor_id, $start_date, $end_date
    ));
    
    // Online satışlar (WooCommerce'dan)
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'author'         => $vendor_id
    );
    
    $products_query = new WP_Query($args);
    $online_sales = array();
    
    if ($products_query->have_posts()) {
        while ($products_query->have_posts()) {
            $products_query->the_post();
            $product = wc_get_product(get_the_ID());
            $product_id = $product->get_id();
            
            // Belirli bir tarih aralığındaki WooCommerce siparişlerini kontrol et
            $order_items = $wpdb->get_row($wpdb->prepare(
                "SELECT SUM(oim.meta_value) as total_qty 
                FROM {$wpdb->prefix}woocommerce_order_items oi
                JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id
                JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id
                JOIN {$wpdb->posts} p ON p.ID = oi.order_id
                WHERE oim.meta_key = '_qty'
                AND oim2.meta_key = '_product_id'
                AND oim2.meta_value = %d
                AND p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing')
                AND p.post_date BETWEEN %s AND %s",
                $product_id, $start_date . ' 00:00:00', $end_date . ' 23:59:59'
            ));
            
            $online_qty = $order_items ? floatval($order_items->total_qty) : 0;
            
            if ($online_qty > 0) {
                $online_sales[] = array(
                    'product_id' => $product_id,
                    'product_name' => $product->get_name(),
                    'quantity' => $online_qty
                );
            }
        }
    }
    
    wp_reset_postdata();
    
    // Toplam satışları hesapla
    $total_sales = array();
    
    // Manuel satışları ekle
    foreach ($manual_sales as $sale) {
        $total_sales[$sale->product_id] = array(
            'product_id' => $sale->product_id,
            'product_name' => $sale->product_name,
            'manual_sales' => $sale->quantity,
            'online_sales' => 0,
            'total_sales' => $sale->quantity
        );
    }
    
    // Online satışları ekle
    foreach ($online_sales as $sale) {
        if (isset($total_sales[$sale['product_id']])) {
            $total_sales[$sale['product_id']]['online_sales'] = $sale['quantity'];
            $total_sales[$sale['product_id']]['total_sales'] += $sale['quantity'];
        } else {
            $total_sales[$sale['product_id']] = array(
                'product_id' => $sale['product_id'],
                'product_name' => $sale['product_name'],
                'manual_sales' => 0,
                'online_sales' => $sale['quantity'],
                'total_sales' => $sale['quantity']
            );
        }
    }
    
    // Toplam satışlara göre sırala
    usort($total_sales, function($a, $b) {
        return $b['total_sales'] - $a['total_sales'];
    });
    
    // En az 1 satışı olan ürünler
    $sales_products = array_filter($total_sales, function($item) {
        return $item['total_sales'] > 0;
    });
    
    // En çok satan 10 ürün
    $top_selling = array_slice($sales_products, 0, 10);
    
    // En az satan 10 ürün (satışı olanlar arasında)
    $sales_count = count($sales_products);
    $least_selling = ($sales_count > 10) ? 
        array_slice($sales_products, -10) : 
        array_slice($sales_products, 0, $sales_count);
    
    // CSV verisi oluştur - başlık ve açıklama
    $csv_data = array(
        array('Satış Analizi Raporu - ' . $period_text . ' - ' . date('d.m.Y'))
    );
    
    // Boş satır ekle
    $csv_data[] = array('');
    
    // En çok satan ürünler tablosu
    $csv_data[] = array('EN ÇOK SATAN ÜRÜNLER');
    $csv_data[] = array('Ürün', 'Toplam Satış', 'Online Satış', 'Manuel Satış');
    
    foreach ($top_selling as $item) {
        $csv_data[] = array(
            $item['product_name'],
            $item['total_sales'],
            $item['online_sales'],
            $item['manual_sales']
        );
    }
    
    // Boş satır ekle
    $csv_data[] = array('');
    $csv_data[] = array('');
    
    // En az satan ürünler tablosu
    $csv_data[] = array('EN AZ SATAN ÜRÜNLER');
    $csv_data[] = array('Ürün', 'Toplam Satış', 'Online Satış', 'Manuel Satış');
    
    // Satış miktarına göre artan sıralama (en azdan en fazlaya)
    usort($least_selling, function($a, $b) {
        return $a['total_sales'] - $b['total_sales'];
    });
    
    foreach ($least_selling as $item) {
        $csv_data[] = array(
            $item['product_name'],
            $item['total_sales'],
            $item['online_sales'],
            $item['manual_sales']
        );
    }
    
    // Boş satır ekle
    $csv_data[] = array('');
    $csv_data[] = array('');
    
    // Tüm ürünlerin satış tablosu
    $csv_data[] = array('TÜM ÜRÜNLER SATIŞI');
    $csv_data[] = array('Ürün', 'Toplam Satış', 'Online Satış', 'Manuel Satış');
    
    // Satış miktarına göre azalan sıralama (en fazladan en aza)
    usort($total_sales, function($a, $b) {
        return $b['total_sales'] - $a['total_sales'];
    });
    
    foreach ($total_sales as $item) {
        $csv_data[] = array(
            $item['product_name'],
            $item['total_sales'],
            $item['online_sales'],
            $item['manual_sales']
        );
    }
    
    // CSV içeriğini oluştur
    $csv_content = '';
    foreach ($csv_data as $row) {
        $csv_content .= implode(',', array_map(function($value) {
            // Özel karakterleri escape et ve tırnak içine al
            return '"' . str_replace('"', '""', $value) . '"';
        }, $row)) . "\n";
    }
    
    // CSV'yi indirilecek veri olarak gönder
    $filename = 'satis_analizi_' . $period . '_' . date('Y-m-d') . '.csv';
    
    // Base64 şifrelemesi yapalım
    $base64_content = base64_encode($csv_content);
    
    wp_send_json_success(array(
        'filename' => $filename,
        'content' => $base64_content
    ));
}
add_action('wp_ajax_export_sales_analysis_excel', 'export_sales_analysis_excel_ajax');

// WooCommerce siparişleri için stok hareketi kaydı oluşturma
function record_online_order_stock_movement($order_id) {
    $order = wc_get_order($order_id);
    
    // Sipariş durumu tamamlandıysa veya işlemdeyse devam et
    if (!$order || !in_array($order->get_status(), array('completed', 'processing'))) {
        return;
    }
    
    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';
    
    // Sipariş kalemlerini döngüye alıp stok hareketleri tablosuna ekle
    foreach ($order->get_items() as $item) {
        $product_id = $item->get_product_id();
        $product = wc_get_product($product_id);
        
        if (!$product || !$product->managing_stock()) {
            continue;
        }
        
        $vendor_id = get_post_field('post_author', $product_id);
        $quantity = $item->get_quantity();
        
        // ÖNEMLİ: Mevcut stok değerini siparişten sonra al
        $current_stock = $product->get_stock_quantity();
        
        // Eğer aynı ürün için daha önce kayıt yapılmışsa tekrar ekleme
        $existing_record = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $movements_table 
            WHERE product_id = %d AND vendor_id = %d AND movement_type = 'online_sale' 
            AND notes = %s",
            $product_id, $vendor_id, sprintf('Online sipariş #%s', $order->get_order_number())
        ));
        
        if ($existing_record > 0) {
            continue;
        }
        
        // Stok hareketi kaydı ekle
        $wpdb->insert(
            $movements_table,
            array(
                'vendor_id' => $vendor_id,
                'product_id' => $product_id,
                'quantity' => -$quantity, // Eksi değer çünkü bu bir satış
                'movement_type' => 'online_sale',
                'movement_date' => current_time('mysql'),
                'notes' => sprintf('Online sipariş #%s', $order->get_order_number()),
                'current_stock' => $current_stock // Güncel stok miktarı
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%d')
        );
        
        // YENI: Gerçek zamanlı güncelleme için bir transient oluştur
        set_transient('dokan_stock_updated_' . $product_id, time(), 300); // 5 dk süre ile sakla
    }
    
    // YENI: Dokan stok güncellemelerini temizle, böylece hemen görünür olacak
    delete_transient('dokan_stock_checking');
}
add_action('woocommerce_order_status_completed', 'record_online_order_stock_movement');
add_action('woocommerce_order_status_processing', 'record_online_order_stock_movement');

// Stok güncellemelerini kontrol eden AJAX handler
function check_stock_updates_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }
    
    if (!isset($_POST['product_ids']) || !is_array($_POST['product_ids'])) {
        wp_send_json_error('Ürün ID\'leri geçersiz');
        return;
    }
    
    // Eğer son kontrol çok yakındaysa ve güncellemeler yoksa, erken dön
    $checking = get_transient('dokan_stock_checking');
    if ($checking && time() - $checking < 5 && !isset($_POST['force_check'])) {
        wp_send_json_success(['message' => 'Henüz kontrol edildi']);
        return;
    }
    
    // Kontrol edildiğini işaretle
    set_transient('dokan_stock_checking', time(), 60);
    
    $product_ids = array_map('intval', $_POST['product_ids']);
    $previous_stocks = isset($_POST['previous_stocks']) ? $_POST['previous_stocks'] : [];
    $vendor_id = get_current_user_id();
    $result = array();
    
    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';
    
    foreach ($product_ids as $product_id) {
        $product = wc_get_product($product_id);
        
        if (!$product) {
            continue;
        }
        
        // Ürün sahibi kontrolü
        if (get_post_field('post_author', $product_id) != $vendor_id) {
            continue;
        }
        
        // YENI: Son güncelleme kontrolü
        $last_updated = get_transient('dokan_stock_updated_' . $product_id);
        $stock_changed = false;
        
        // Önceki stok değerini kontrole etmek için kullanacağız
        $previous_stock = isset($previous_stocks[$product_id]) ? intval($previous_stocks[$product_id]) : null;
        $current_stock = $product->get_stock_quantity();
        
        // Eğer bu ürün son 5 dk içinde güncellendiyse veya stok değişmişse
        if ($last_updated || ($previous_stock !== null && $previous_stock !== $current_stock)) {
            $stock_changed = true;
        }
        
        // Manuel satışları getir
        $manual_sales = $wpdb->get_var($wpdb->prepare(
            "SELECT ABS(COALESCE(SUM(quantity), 0)) FROM $movements_table 
            WHERE product_id = %d AND vendor_id = %d AND movement_type = 'sale'",
            $product_id, $vendor_id
        ));
        
        // Online satışları getir
        $online_sales = $product->get_total_sales();
        
        $manual_sales = intval($manual_sales);
        $total_sales = $online_sales + $manual_sales;
        
        $result[$product_id] = array(
            'stock' => $current_stock,
            'manual_sales' => $manual_sales,
            'online_sales' => $online_sales,
            'total_sales' => $total_sales,
            'stock_changed' => $stock_changed
        );
        
        // YENI: İşlendikten sonra temizle
        if ($last_updated) {
            delete_transient('dokan_stock_updated_' . $product_id);
        }
    }
    
    wp_send_json_success($result);
}
add_action('wp_ajax_check_stock_updates', 'check_stock_updates_ajax');

// Siparişler için stok güncelleme olayını yakalamak için alternatif yaklaşım
function woocommerce_order_stock_reduction_fix($order_id) {
    // 10 saniye bekleyerek WooCommerce'in stok güncellemelerini tamamlamasını sağla
    wp_schedule_single_event(time() + 10, 'dokan_delayed_stock_movement_record', array($order_id));
}
add_action('woocommerce_payment_complete', 'woocommerce_order_stock_reduction_fix');
add_action('woocommerce_order_status_changed', 'woocommerce_order_stock_reduction_fix');

// Gecikmeli olarak stok hareketi kaydı oluştur
function dokan_delayed_stock_movement_record($order_id) {
    record_online_order_stock_movement($order_id);
}
add_action('dokan_delayed_stock_movement_record', 'dokan_delayed_stock_movement_record');

// Yönetici kullanımı için: Online satış kayıtlarındaki stok miktarlarını düzeltme
function fix_online_sale_stock_records() {
    // Sadece yöneticiler için
    if (!current_user_can('manage_options')) {
        return 'Bu işlem için yetkiniz yok.';
    }
    
    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';
    
    // Online satış kayıtlarını al
    $online_sales = $wpdb->get_results(
        "SELECT * FROM $movements_table WHERE movement_type = 'online_sale' ORDER BY id ASC"
    );
    
    $fixed_count = 0;
    $previous_stocks = array();
    
    // Her kayıt için doğru stok miktarını hesapla ve güncelle
    foreach ($online_sales as $sale) {
        $product_id = $sale->product_id;
        
        // Bu ürün için önceki stok miktarını izle
        if (!isset($previous_stocks[$product_id])) {
            // Ürünün mevcut stok miktarını al
            $product = wc_get_product($product_id);
            if (!$product) continue;
            
            $current_stock = $product->get_stock_quantity();
            
            // Bu ürünün tüm stok hareketlerini kronolojik sırayla al
            $all_movements = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $movements_table WHERE product_id = %d ORDER BY movement_date DESC",
                $product_id
            ));
            
            // En son bilinen doğru stok değerini belirle
            $latest_stock = $current_stock;
            
            // Bu ürün için önceki stok değerini başlat
            $previous_stocks[$product_id] = $latest_stock;
        }
        
        // Satıştan önceki stok miktarını hesapla
        $quantity = abs($sale->quantity);
        $correct_stock = $previous_stocks[$product_id] + $quantity;
        
        // Stok hareketini güncelle
        $wpdb->update(
            $movements_table,
            array('current_stock' => $correct_stock),
            array('id' => $sale->id),
            array('%d'),
            array('%d')
        );
        
        // Sonraki kayıt için önceki stok değerini güncelle
        $previous_stocks[$product_id] = $correct_stock;
        
        $fixed_count++;
    }
    
    return "Toplam $fixed_count online satış kaydının stok miktarları düzeltildi.";
}

// Admin sayfasına düzeltme aracı bağlantısı ekle
function add_stock_fix_admin_menu() {
    if (current_user_can('manage_options')) {
        add_submenu_page(
            'dokan', 
            'Stok Kayıtlarını Düzelt', 
            'Stok Kayıtlarını Düzelt',
            'manage_options',
            'dokan-fix-stock-records',
            'render_fix_stock_records_page'
        );
    }
}
add_action('admin_menu', 'add_stock_fix_admin_menu', 99);

// Düzeltme sayfasını oluştur
function render_fix_stock_records_page() {
    $result = '';
    if (isset($_POST['fix_records']) && check_admin_referer('dokan_fix_stock_records')) {
        $result = fix_online_sale_stock_records();
    }
    
    echo '<div class="wrap">';
    echo '<h1>Stok Kayıtlarını Düzelt</h1>';
    
    if ($result) {
        echo '<div class="notice notice-success"><p>' . $result . '</p></div>';
    }
    
    echo '<form method="post">';
    wp_nonce_field('dokan_fix_stock_records');
    echo '<p>Bu araç, online satış kayıtlarındaki stok miktarı hatalarını düzeltir.</p>';
    echo '<p class="submit"><input type="submit" name="fix_records" class="button button-primary" value="Kayıtları Düzelt"></p>';
    echo '</form>';
    echo '</div>';
}

// Sipariş olaylarını dinleme için AJAX handler
function listen_order_events() {
    // SSE başlıklarını ayarla
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    
    // Giriş kontrolü
    if (!is_user_logged_in()) {
        echo "data: " . json_encode(['error' => 'unauthorized']) . "\n\n";
        flush();
        exit;
    }
    
    // Yeni siparişleri kontrol et (eğer WooCommerce'in hooks'u varsa kullanılabilir)
    $last_checked = time();
    
    // Döngüyü başlat
    while (true) {
        // Bağlantı koptu mu kontrol et
        if (connection_aborted()) {
            break;
        }
        
        global $wpdb;
        
        // Son birkaç dakikada oluşturulan veya güncellenen siparişleri kontrol et
        $check_time = date('Y-m-d H:i:s', $last_checked - 300); // 5 dakika önceki
        $last_checked = time();
        
        $recent_orders = $wpdb->get_results($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} 
            WHERE post_type = 'shop_order' 
            AND (post_date >= %s OR post_modified >= %s)
            AND post_status IN ('wc-processing', 'wc-completed')",
            $check_time, $check_time
        ));
        
        if (!empty($recent_orders)) {
            // Yeni veya güncellenmiş sipariş var
            echo "data: " . json_encode([
                'type' => 'order_updated',
                'count' => count($recent_orders)
            ]) . "\n\n";
            flush();
        } else {
            // Sadece bağlantıyı açık tut
            echo ": keepalive\n\n";
            flush();
        }
        
        // 5 saniye bekle
        sleep(5);
    }
    
    exit;
}
add_action('wp_ajax_listen_order_events', 'listen_order_events');

// Stok geçmişi için Excel çıktısı alma AJAX handler
function export_stock_history_excel_ajax() {
    check_ajax_referer('dokan-stock-security', 'security');
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Oturum açmanız gerekiyor');
        return;
    }
    
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : null;
    $end_date = isset($_POST['end_date']) ? sanitize_text_field($_POST['end_date']) : null;
    $vendor_id = get_current_user_id();
    
    if (!$product_id) {
        wp_send_json_error('Ürün ID\'si gereklidir');
        return;
    }
    
    // Ürün kontrolü
    $product = wc_get_product($product_id);
    if (!$product) {
        wp_send_json_error('Ürün bulunamadı');
        return;
    }
    
    // Ürün sahibi kontrolü
    if (get_post_field('post_author', $product_id) != $vendor_id) {
        wp_send_json_error('Bu ürün için yetkiniz yok');
        return;
    }
    
    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';
    
    // Tarih filtresi varsa SQL sorgusuna ekle
    $where_clause = "WHERE product_id = %d AND vendor_id = %d";
    $query_args = array($product_id, $vendor_id);
    
    if ($start_date && $end_date) {
        $where_clause .= " AND DATE(movement_date) BETWEEN %s AND %s";
        $query_args[] = $start_date;
        $query_args[] = $end_date;
    }
    
    // Stok hareketlerini sorgula
    $movements = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $movements_table $where_clause ORDER BY movement_date DESC",
        $query_args
    ));
    
    if (empty($movements)) {
        wp_send_json_error('Bu ürün için hareket bulunamadı');
        return;
    }
    
    // Ürün adını al
    $product_name = $product->get_name();
    
    // Excel için başlık satırı oluştur
    $csv_data = array(
        array(sprintf('%s - Stok Hareket Geçmişi', $product_name))
    );
    
    // Filtre açıklaması ekle
    if ($start_date && $end_date) {
        $csv_data[] = array(sprintf('Filtre: %s - %s', $start_date, $end_date));
    }
    
    // Boş satır
    $csv_data[] = array('');
    
    // Tablo başlıkları
    $csv_data[] = array('Tarih', 'İşlem', 'Miktar', 'Stok Durumu', 'Not');
    
    // Stok hareketlerini ekle
    foreach ($movements as $movement) {
        $type = match($movement->movement_type) {
            'add' => 'Stok Ekleme',
            'sale' => 'Manuel Satış',
            'online_sale' => 'Online Satış',
            'delete' => 'Stok Silme',
            'set' => 'Stok Güncelleme',
            default => 'Diğer'
        };
        
        $quantity = abs($movement->quantity);
        $date = wp_date('d.m.Y H:i', strtotime($movement->movement_date));
        
        $csv_data[] = array(
            $date,
            $type,
            $quantity,
            $movement->current_stock,
            $movement->notes
        );
    }
    
    // CSV içeriğini oluştur
    $csv_content = '';
    foreach ($csv_data as $row) {
        $csv_content .= implode(',', array_map(function($value) {
            return '"' . str_replace('"', '""', $value) . '"';
        }, $row)) . "\n";
    }
    
    // Dosya adını oluştur
    $filename = sprintf('urun_gecmis_%s_%s.csv', $product_id, date('Y-m-d'));
    
    // Filtreli versiyonsa dosya adına tarih aralığını ekle
    if ($start_date && $end_date) {
        $filename = sprintf('urun_gecmis_%s_%s_%s.csv', $product_id, $start_date, $end_date);
    }
    
    // Base64 şifrelemesi yapalım
    $base64_content = base64_encode($csv_content);
    
    wp_send_json_success(array(
        'filename' => $filename,
        'content' => $base64_content
    ));
}
add_action('wp_ajax_export_stock_history_excel', 'export_stock_history_excel_ajax');
