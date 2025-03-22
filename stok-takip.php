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
    $output .= '<button class="button add-new-product">Yeni Ürün Ekle</button>';
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
            <button class="button set-stock-btn" data-product-id="' . $product_id . '">Stok Ayarla</button>
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

// Stok geçmişi HTML'ini oluşturan yardımcı fonksiyon
function get_stock_history_html($product_id, $vendor_id, $start_date = null, $end_date = null, $page = 1) {
    global $wpdb;
    $movements_table = $wpdb->prefix . 'dokan_stock_movements';
    $per_page = 10;
    $offset = ($page - 1) * $per_page;

    $where_clause = "WHERE product_id = %d AND vendor_id = %d";
    $params = array($product_id, $vendor_id);

    if ($start_date && $end_date) {
        $where_clause .= " AND DATE(movement_date) BETWEEN DATE(%s) AND DATE(%s)";
        $params[] = $start_date;
        $params[] = $end_date;
    }

    // Debug için
    error_log('SQL Where Clause: ' . $where_clause);
    error_log('Parameters: ' . print_r($params, true));

    // Toplam kayıt sayısını al
    $total_records = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $movements_table $where_clause",
        $params
    ));

    // Hareket kayıtlarını al
    $movements = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $movements_table 
        $where_clause 
        ORDER BY movement_date DESC
        LIMIT %d OFFSET %d",
        array_merge($params, array($per_page, $offset))
    ));

    // Debug için
    error_log('Total Records: ' . $total_records);
    error_log('Movements Count: ' . count($movements));

    if (empty($movements)) {
        return '<p>Bu tarih aralığında hareket kaydı yok.</p>';
    }

    $output = '<div class="history-filter">
        <input type="date" class="history-date-start" value="' . ($start_date ?: '') . '">
        <span>-</span>
        <input type="date" class="history-date-end" value="' . ($end_date ?: '') . '">
        <button class="button filter-history">Filtrele</button>
        <button class="button reset-filter">Filtreyi Temizle</button>
    </div>';

    $output .= '<table class="widefat">
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
            'delete' => 'Stok Silme',
            'set' => 'Stok Güncelleme',
            default => 'Diğer'
        };
        
        $quantity = abs($movement->quantity);
        $date = wp_date('d.m.Y H:i', strtotime($movement->movement_date));
        
        $output .= sprintf(
            '<tr>
                <td>%s</td>
                <td>%s</td>
                <td>%d</td>
                <td>%d</td>
                <td>%s</td>
            </tr>',
            esc_html($date),
            esc_html($type),
            esc_html($quantity),
            esc_html($movement->current_stock),
            esc_html($movement->notes)
        );
    }

    $output .= '</tbody></table>';

    // Sayfalandırma
    $total_pages = ceil($total_records / $per_page);
    if ($total_pages > 1) {
        $output .= '<div class="history-pagination">';
        for ($i = 1; $i <= $total_pages; $i++) {
            $active_class = $i == $page ? 'active' : '';
            $output .= sprintf(
                '<button class="button pagination-btn %s" data-page="%d">%d</button>',
                $active_class,
                $i,
                $i
            );
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
