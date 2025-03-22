jQuery(document).ready(function($) {
    // Manuel satış popup HTML'ini ekle
    $('body').append(`
        <div id="manual-sale-popup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); 
            background:#fff; padding:20px; border:1px solid #ccc; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.1); z-index:9999;">
            <h3>Manuel Satış</h3>
            <p class="product-name"></p>
            <p>
                <label>Satış Miktarı:</label>
                <input type="number" id="sale-quantity" min="1" value="1">
            </p>
            <div style="margin-top:15px;">
                <button class="button save-sale">Kaydet</button>
                <button class="button cancel-sale">İptal</button>
            </div>
        </div>
        <div id="popup-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; 
            background:rgba(0,0,0,0.5); z-index:9998;"></div>
    `);

    // Form HTML'ini oluşturan yardımcı fonksiyon
    function getInlineFormHtml(productId, type) {
        var title = type === 'add' ? 'Stok Ekle' : 'Manuel Satış';
        var buttonText = type === 'add' ? 'Stok Ekle' : 'Satış Yap';
        
        return `
            <tr class="inline-form-row" id="form_${productId}">
                <td colspan="7">
                    <div class="inline-form">
                        <div class="form-group">
                            <label>${title}</label>
                            <input type="number" class="quantity-input" min="1" value="1">
                        </div>
                        <div class="form-group">
                            <label>Not:</label>
                            <input type="text" class="notes-input" placeholder="Not ekleyin...">
                        </div>
                        <div class="button-group">
                            <button class="button save-inline">${buttonText}</button>
                            <button class="button cancel-inline">İptal</button>
                        </div>
                    </div>
                </td>
            </tr>`;
    }

    // Mevcut formu kaldır
    function removeInlineForm() {
        $('.inline-form-row').remove();
    }

    // Manuel satış butonu
    $('.manual-sale-btn').on('click', function() {
        var $row = $(this).closest('tr');
        var productId = $(this).data('product-id');
        var currentStock = parseInt($('#stock_' + productId).text());
        
        removeInlineForm();
        
        var formHtml = `
            <tr class="inline-form-row">
                <td colspan="7">
                    <div class="inline-form">
                        <div class="form-group">
                            <label>Mevcut Stok: ${currentStock}</label>
                            <label>Satış Miktarı:</label>
                            <input type="number" class="quantity-input" min="1" max="${currentStock}" value="1">
                        </div>
                        <div class="form-group">
                            <label>Not:</label>
                            <input type="text" class="notes-input" placeholder="Satış notu...">
                        </div>
                        <div class="button-group">
                            <button class="button save-sale">Satışı Kaydet</button>
                            <button class="button cancel-form">İptal</button>
                        </div>
                    </div>
                </td>
            </tr>`;
        
        $row.after(formHtml);
        
        // Kaydet butonu için event
        $row.next().find('.save-sale').on('click', function() {
            var quantity = parseInt($row.next().find('.quantity-input').val());
            var notes = $row.next().find('.notes-input').val();
            
            if (!quantity || quantity <= 0 || quantity > currentStock) {
                alert('Lütfen geçerli bir miktar girin!');
                return;
            }
            
            $.ajax({
                url: dokanStock.ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_stock',
                    product_id: productId,
                    quantity: quantity,
                    notes: notes,
                    movement_type: 'sale',
                    security: dokanStock.security
                },
                success: function(response) {
                    console.log('Response:', response);
                    if (response.success) {
                        $('#stock_' + productId).text(response.data.new_stock);
                        $('.manual-sales-' + productId).text(response.data.manual_sales);
                        $('.total-sales-' + productId).text(response.data.total_sales);
                        removeInlineForm();
                        alert('Manuel satış başarıyla kaydedildi.');
                        updateHistory(productId);
                    } else {
                        alert('Hata: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    alert('İşlem sırasında bir hata oluştu!');
                }
            });
        });
        
        $row.next().find('.cancel-form').on('click', removeInlineForm);
    });

    // Stok ekleme butonu
    $('.stock-add-btn').on('click', function() {
        var $row = $(this).closest('tr');
        var productId = $(this).data('product-id');
        var currentStock = parseInt($('#stock_' + productId).text());
        
        removeInlineForm();
        
        var formHtml = `
            <tr class="inline-form-row">
                <td colspan="7">
                    <div class="inline-form">
                        <div class="form-group">
                            <label>Mevcut Stok: ${currentStock}</label>
                            <label>Eklenecek Miktar:</label>
                            <input type="number" class="quantity-input" min="1" value="1">
                        </div>
                        <div class="form-group">
                            <label>Not:</label>
                            <input type="text" class="notes-input" placeholder="Stok ekleme nedeni...">
                        </div>
                        <div class="button-group">
                            <button class="button save-stock">Kaydet</button>
                            <button class="button cancel-form">İptal</button>
                        </div>
                    </div>
                </td>
            </tr>`;
        
        $row.after(formHtml);
        
        // Kaydet butonu için event
        $row.next().find('.save-stock').on('click', function() {
            var quantity = parseInt($row.next().find('.quantity-input').val());
            var notes = $row.next().find('.notes-input').val();
            
            if (!quantity || quantity <= 0) {
                alert('Lütfen geçerli bir miktar girin!');
                return;
            }
            
            $.ajax({
                url: dokanStock.ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_stock',
                    product_id: productId,
                    quantity: quantity,
                    notes: notes,
                    movement_type: 'add',
                    security: dokanStock.security
                },
                success: function(response) {
                    console.log('Response:', response);
                    if (response.success) {
                        $('#stock_' + productId).text(response.data.new_stock);
                        removeInlineForm();
                        alert('Stok başarıyla eklendi.');
                        updateHistory(productId);
                    } else {
                        alert('Hata: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Ajax error:', error);
                    console.error('Status:', status);
                    console.error('Response:', xhr.responseText);
                    alert('İşlem sırasında bir hata oluştu!');
                }
            });
        });
        
        $row.next().find('.cancel-form').on('click', removeInlineForm);
    });

    // Geçmiş butonu
    $('.show-history-btn').on('click', function() {
        var $row = $(this).closest('tr');
        var productId = $(this).data('product-id');
        var $historyRow = $('#history_' + productId);
        
        if ($historyRow.is(':visible')) {
            $historyRow.hide();
            return;
        }
        
        updateHistory(productId);
    });

    // Geçmiş güncelleme fonksiyonu
    function updateHistory(productId, startDate = null, endDate = null, page = 1) {
        var $historyRow = $('#history_' + productId);
        
        $.ajax({
            url: dokanStock.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_stock_history',
                product_id: productId,
                security: dokanStock.security,
                start_date: startDate,
                end_date: endDate,
                page: page
            },
            success: function(response) {
                if (response.success) {
                    $historyRow.find('.history-content').html(response.data);
                    $historyRow.show();

                    // Filtreleme butonları için yeni event listener'lar
                    $historyRow.find('.filter-history').off('click').on('click', function() {
                        var newStartDate = $(this).closest('.history-filter').find('.history-date-start').val();
                        var newEndDate = $(this).closest('.history-filter').find('.history-date-end').val();
                        
                        if (!newStartDate || !newEndDate) {
                            alert('Lütfen başlangıç ve bitiş tarihlerini seçin');
                            return;
                        }
                        
                        updateHistory(productId, newStartDate, newEndDate, 1);
                    });

                    $historyRow.find('.reset-filter').off('click').on('click', function() {
                        $(this).closest('.history-filter').find('.history-date-start, .history-date-end').val('');
                        updateHistory(productId, null, null, 1);
                    });

                    // Sayfalandırma butonları için event listener
                    $historyRow.find('.pagination-btn').off('click').on('click', function() {
                        var newPage = $(this).data('page');
                        updateHistory(productId, startDate, endDate, newPage);
                    });
                } else {
                    alert('Geçmiş bilgileri alınamadı.');
                }
            },
            error: function(xhr, status, error) {
                console.error('Ajax error:', error);
                alert('Geçmiş bilgileri alınırken bir hata oluştu.');
            }
        });
    }

    // Popup kaydet butonu
    $('.save-movement').on('click', function() {
        var quantity = parseInt($('#movement-quantity').val());
        var notes = $('#movement-notes').val();
        var type = $(this).data('movement-type');
        var productId = $(this).data('product-id');

        if (!quantity || quantity <= 0) {
            alert('Lütfen geçerli bir miktar girin!');
            return;
        }

        // Eğer manuel satış ise miktarı negatife çevir
        if (type === 'sale') {
            quantity = -quantity;
        }

        $.ajax({
            url: dokanStock.ajaxurl,
            type: 'POST',
            data: {
                action: 'update_stock',
                product_id: productId,
                quantity: quantity,
                notes: notes,
                movement_type: type,
                security: dokanStock.security
            },
            success: function(response) {
                if (response.success) {
                    $('#stock_' + productId).text(response.data.new_stock);
                    $('.manual-sales-' + productId).text(response.data.manual_sales);
                    $('.total-sales-' + productId).text(response.data.total_sales);
                    hidePopup();
                    alert('İşlem başarıyla kaydedildi!');
                } else {
                    alert('Hata: ' + response.data);
                }
            }
        });
    });

    // Popup iptal butonu
    $('.cancel-movement').on('click', hidePopup);

    function showPopup(type, productId) {
        $('.save-movement')
            .data('movement-type', type)
            .data('product-id', productId);
        $('#stock-movement-popup').show();
    }

    function hidePopup() {
        $('#stock-movement-popup').hide();
    }

    // Yeni Ürün Ekle butonu için event listener
    $('.add-new-product').on('click', function() {
        $('body').append(getNewProductFormHtml());
        loadTaxonomies();
        
        // Select2 initialize
        $('.categories-select, .tags-select').select2({
            width: '100%',
            placeholder: 'Seçiniz...',
            allowClear: true,
            closeOnSelect: false,
            tags: true // Yeni etiket eklemeye izin ver
        });
    });

    // Yeni ürün ekleme popup'ı için HTML
    function getNewProductFormHtml() {
        return `
        <div id="new-product-popup" class="stock-popup">
            <div class="popup-content">
                <h3>Yeni Ürün Ekle</h3>
                <form id="new-product-form">
                    <div class="form-group">
                        <label>Ürün Adı:</label>
                        <input type="text" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Ürün Kodu (SKU):</label>
                        <input type="text" name="sku">
                    </div>
                    
                    <div class="form-group">
                        <label>Fiyat:</label>
                        <input type="number" name="price" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Başlangıç Stok:</label>
                        <input type="number" name="stock" required>
                    </div>
                    
                    <div class="taxonomy-container">
                        <div class="form-group">
                            <label>Kategoriler:</label>
                            <select name="categories[]" multiple class="categories-select">
                                <option value="">Yükleniyor...</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Etiketler:</label>
                            <select name="tags[]" multiple class="tags-select">
                                <option value="">Yükleniyor...</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Kısa Açıklama:</label>
                        <textarea name="short_description" rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Detaylı Açıklama:</label>
                        <textarea name="description" rows="5"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Ana Görsel:</label>
                        <button type="button" class="button upload-image">Görsel Seç</button>
                        <div class="image-preview"></div>
                        <input type="hidden" name="image_id">
                    </div>
                    
                    <div class="form-group">
                        <label>Galeri Görselleri:</label>
                        <button type="button" class="button upload-gallery">Görseller Seç</button>
                        <div class="gallery-preview"></div>
                        <input type="hidden" name="gallery_ids">
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="button button-primary">Kaydet</button>
                        <button type="button" class="button cancel-product">İptal</button>
                    </div>
                </form>
            </div>
        </div>`;
    }

    // Kategori ve etiketleri yükle
    function loadTaxonomies() {
        $.ajax({
            url: dokanStock.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_product_taxonomies',
                security: dokanStock.security
            },
            success: function(response) {
                if (response.success) {
                    // Kategorileri doldur
                    var categorySelect = $('.categories-select');
                    categorySelect.empty();
                    $.each(response.data.categories, function(i, category) {
                        categorySelect.append($('<option>', {
                            value: category.term_id,
                            text: category.name
                        }));
                    });

                    // Etiketleri doldur
                    var tagSelect = $('.tags-select');
                    tagSelect.empty();
                    $.each(response.data.tags, function(i, tag) {
                        tagSelect.append($('<option>', {
                            value: tag.term_id,
                            text: tag.name
                        }));
                    });
                }
            }
        });
    }

    // Görsel yükleme işleyicisi
    function handleImageUpload(button, previewDiv, inputField, multiple = false) {
        var frame = wp.media({
            title: 'Görsel Seç',
            multiple: multiple
        });

        frame.on('select', function() {
            var selection = frame.state().get('selection');
            var ids = [];
            previewDiv.empty();

            selection.each(function(attachment) {
                ids.push(attachment.id);
                previewDiv.append(
                    $('<img>', {
                        src: attachment.attributes.sizes.thumbnail.url,
                        width: 80,
                        height: 80,
                        style: 'margin: 5px;'
                    })
                );
            });

            inputField.val(ids.join(','));
        });

        frame.open();
    }

    $(document).on('click', '.upload-image', function() {
        handleImageUpload(
            $(this),
            $(this).siblings('.image-preview'),
            $(this).siblings('input[name="image_id"]'),
            false
        );
    });

    $(document).on('click', '.upload-gallery', function() {
        handleImageUpload(
            $(this),
            $(this).siblings('.gallery-preview'),
            $(this).siblings('input[name="gallery_ids"]'),
            true
        );
    });

    $(document).on('submit', '#new-product-form', function(e) {
        e.preventDefault();
        var formData = {
            name: $('input[name="name"]').val(),
            sku: $('input[name="sku"]').val(),
            price: $('input[name="price"]').val(),
            stock: $('input[name="stock"]').val(),
            categories: $('.categories-select').val(),
            tags: $('.tags-select').val(),
            short_description: $('textarea[name="short_description"]').val(),
            description: $('textarea[name="description"]').val(),
            image_id: $('input[name="image_id"]').val(),
            gallery_ids: $('input[name="gallery_ids"]').val().split(',')
        };
        
        $.ajax({
            url: dokanStock.ajaxurl,
            type: 'POST',
            data: {
                action: 'add_new_product',
                security: dokanStock.security,
                product_data: formData
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Hata: ' + response.data);
                }
            }
        });
    });

    // Stok Gir butonu için event listener ekle
    $('.set-stock-btn').on('click', function() {
        var $row = $(this).closest('tr');
        var productId = $(this).data('product-id');
        var currentStock = parseInt($('#stock_' + productId).text());
        
        removeInlineForm();
        
        var formHtml = `
            <tr class="inline-form-row">
                <td colspan="7">
                    <div class="inline-form">
                        <div class="form-group">
                            <label>Mevcut Stok: ${currentStock}</label>
                            <label>Yeni Stok Miktarı:</label>
                            <input type="number" class="quantity-input" min="0" value="${currentStock}">
                        </div>
                        <div class="form-group">
                            <label>Not:</label>
                            <input type="text" class="notes-input" placeholder="Stok güncelleme nedeni...">
                        </div>
                        <div class="button-group">
                            <button class="button save-set-stock">Kaydet</button>
                            <button class="button cancel-form">İptal</button>
                        </div>
                    </div>
                </td>
            </tr>`;
        
        $row.after(formHtml);
        
        // Kaydet butonu için event
        $row.next().find('.save-set-stock').on('click', function() {
            var newStock = parseInt($row.next().find('.quantity-input').val());
            var notes = $row.next().find('.notes-input').val();
            
            if (newStock < 0) {
                alert('Lütfen geçerli bir stok miktarı girin!');
                return;
            }
            
            $.ajax({
                url: dokanStock.ajaxurl,
                type: 'POST',
                data: {
                    action: 'set_stock',
                    product_id: productId,
                    new_stock: newStock,
                    notes: notes,
                    security: dokanStock.security
                },
                success: function(response) {
                    if (response.success) {
                        $('#stock_' + productId).text(response.data.new_stock);
                        removeInlineForm();
                        alert('Stok başarıyla güncellendi.');
                        updateHistory(productId);
                    } else {
                        alert('Hata: ' + response.data);
                    }
                }
            });
        });
        
        $row.next().find('.cancel-form').on('click', removeInlineForm);
    });

    // Stok silme butonu için event listener ekle
    $('.delete-stock-btn').on('click', function() {
        var $row = $(this).closest('tr');
        var productId = $(this).data('product-id');
        var currentStock = parseInt($('#stock_' + productId).text());
        
        removeInlineForm();
        
        var formHtml = `
            <tr class="inline-form-row">
                <td colspan="7">
                    <div class="inline-form">
                        <div class="form-group">
                            <label>Mevcut Stok: ${currentStock}</label>
                            <label>Silinecek Miktar:</label>
                            <input type="number" class="quantity-input" min="1" max="${currentStock}" value="1">
                        </div>
                        <div class="form-group">
                            <label>Not:</label>
                            <input type="text" class="notes-input" placeholder="Stok silme nedeni...">
                        </div>
                        <div class="button-group">
                            <button class="button save-delete-stock">Stok Sil</button>
                            <button class="button cancel-form">İptal</button>
                        </div>
                    </div>
                </td>
            </tr>`;
        
        $row.after(formHtml);
        
        // Kaydet butonu için event
        $row.next().find('.save-delete-stock').on('click', function() {
            var quantity = parseInt($row.next().find('.quantity-input').val());
            var notes = $row.next().find('.notes-input').val();
            
            if (!quantity || quantity <= 0 || quantity > currentStock) {
                alert('Lütfen geçerli bir miktar girin!');
                return;
            }
            
            if (confirm('Bu işlem stok miktarını azaltacaktır. Devam etmek istiyor musunuz?')) {
                $.ajax({
                    url: dokanStock.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'delete_stock',
                        product_id: productId,
                        quantity: quantity,
                        notes: notes,
                        security: dokanStock.security
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#stock_' + productId).text(response.data.new_stock);
                            removeInlineForm();
                            alert('Stok başarıyla silindi.');
                            updateHistory(productId);
                        } else {
                            alert('Hata: ' + response.data);
                        }
                    }
                });
            }
        });
        
        $row.next().find('.cancel-form').on('click', removeInlineForm);
    });

    // Ürün silme butonu için event listener
    $('.delete-product-btn').on('click', function() {
        var $btn = $(this);
        var $row = $btn.closest('tr');
        var productId = $btn.data('product-id');
        var productName = $row.find('td:nth-child(2)').text();
        
        if (confirm('Bu ürünü silmek istediğinizden emin misiniz?\n\nÜrün: ' + productName + '\n\nBu işlem geri alınamaz!')) {
            $btn.prop('disabled', true).text('Siliniyor...');
            
            $.ajax({
                url: dokanStock.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'delete_product',
                    product_id: productId,
                    security: dokanStock.security
                },
                success: function(response) {
                    if (response.success) {
                        alert('Ürün başarıyla silindi');
                        // Önce geçmiş satırını sil
                        $('#history_' + productId).fadeOut(400, function() {
                            $(this).remove();
                        });
                        // Sonra ürün satırını sil
                        $row.fadeOut(400, function() {
                            $(this).remove();
                        });
                    } else {
                        alert('Hata: ' + (response.data || 'Bilinmeyen bir hata oluştu'));
                        $btn.prop('disabled', false).text('Ürünü Sil');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX hatası:', error);
                    console.error('Durum:', status);
                    console.error('Yanıt:', xhr.responseText);
                    alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                    $btn.prop('disabled', false).text('Ürünü Sil');
                }
            });
        }
    });

    // İptal butonuna tıklama olayı
    $(document).on('click', '.cancel-product', function() {
        // Popup'ı temizle ve kapat
        $('#new-product-popup').remove();
    });

    // Popup dışına tıklama ile kapatma
    $(document).on('click', '#new-product-popup', function(e) {
        // Eğer direkt popup'ın kendisine tıklandıysa ve içeriğine değil
        if (e.target === this) {
            $('#new-product-popup').remove();
        }
    });

    // Ürünü düzenleme butonu için event listener
    $('.edit-product-btn').on('click', function() {
        var productId = $(this).data('product-id');
        
        // AJAX ile ürün bilgilerini alalım
        $.ajax({
            url: dokanStock.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_product_data',
                product_id: productId,
                security: dokanStock.security
            },
            success: function(response) {
                if (response.success) {
                    // Ürün verilerini alalım
                    var productData = response.data;
                    
                    // Düzenleme formunu ekleyelim
                    $('body').append(getEditProductFormHtml(productData));
                    
                    // Kategori ve etiketleri yükleyelim
                    loadTaxonomiesForEdit(productData.categories, productData.tags);
                    
                    // Select2 initialize
                    $('.categories-select, .tags-select').select2({
                        width: '100%',
                        placeholder: 'Seçiniz...',
                        allowClear: true,
                        closeOnSelect: false,
                        tags: true
                    });
                    
                    // Görselleri gösterelim
                    if (productData.image_url) {
                        $('.image-preview').html('<img src="' + productData.image_url + '" width="100" height="100">');
                        $('input[name="image_id"]').val(productData.image_id);
                    }
                    
                    if (productData.gallery_urls && productData.gallery_urls.length > 0) {
                        var galleryHtml = '';
                        for (var i = 0; i < productData.gallery_urls.length; i++) {
                            galleryHtml += '<img src="' + productData.gallery_urls[i] + '" width="80" height="80" style="margin:5px;">';
                        }
                        $('.gallery-preview').html(galleryHtml);
                        $('input[name="gallery_ids"]').val(productData.gallery_ids.join(','));
                    }
                } else {
                    alert('Ürün bilgileri alınırken hata oluştu: ' + response.data);
                }
            },
            error: function() {
                alert('Sunucu ile iletişim hatası');
            }
        });
    });

    // Ürün düzenleme formunu oluşturan fonksiyon
    function getEditProductFormHtml(productData) {
        return `
        <div id="edit-product-popup" class="stock-popup">
            <div class="popup-content">
                <h3>Ürünü Düzenle</h3>
                <form id="edit-product-form">
                    <input type="hidden" name="product_id" value="${productData.id}">
                    
                    <div class="form-group">
                        <label>Ürün Adı:</label>
                        <input type="text" name="name" value="${productData.name}" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Ürün Kodu (SKU):</label>
                        <input type="text" name="sku" value="${productData.sku || ''}">
                    </div>
                    
                    <div class="form-group">
                        <label>Fiyat:</label>
                        <input type="number" name="price" step="0.01" value="${productData.price}" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Stok:</label>
                        <input type="number" name="stock" value="${productData.stock}" required>
                    </div>
                    
                    <div class="taxonomy-container">
                        <div class="form-group">
                            <label>Kategoriler:</label>
                            <select name="categories[]" multiple class="categories-select">
                                <option value="">Yükleniyor...</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Etiketler:</label>
                            <select name="tags[]" multiple class="tags-select">
                                <option value="">Yükleniyor...</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Kısa Açıklama:</label>
                        <textarea name="short_description" rows="3">${productData.short_description || ''}</textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Detaylı Açıklama:</label>
                        <textarea name="description" rows="5">${productData.description || ''}</textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Ana Görsel:</label>
                        <button type="button" class="button upload-image">Görsel Seç</button>
                        <div class="image-preview"></div>
                        <input type="hidden" name="image_id" value="${productData.image_id || ''}">
                    </div>
                    
                    <div class="form-group">
                        <label>Galeri Görselleri:</label>
                        <button type="button" class="button upload-gallery">Görseller Seç</button>
                        <div class="gallery-preview"></div>
                        <input type="hidden" name="gallery_ids" value="${productData.gallery_ids ? productData.gallery_ids.join(',') : ''}">
                    </div>
                    
                    <div class="button-group">
                        <button type="submit" class="button button-primary">Güncelle</button>
                        <button type="button" class="button cancel-edit">İptal</button>
                    </div>
                </form>
            </div>
        </div>`;
    }

    // Kategori ve etiketleri yükleyen fonksiyon (düzenleme için)
    function loadTaxonomiesForEdit(selectedCategories, selectedTags) {
        $.ajax({
            url: dokanStock.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_product_taxonomies',
                security: dokanStock.security
            },
            success: function(response) {
                if (response.success) {
                    // Kategorileri doldur
                    var categorySelect = $('.categories-select');
                    categorySelect.empty();
                    $.each(response.data.categories, function(i, category) {
                        var selected = selectedCategories && selectedCategories.includes(parseInt(category.term_id)) ? 'selected' : '';
                        categorySelect.append($('<option>', {
                            value: category.term_id,
                            text: category.name,
                            selected: selected
                        }));
                    });

                    // Etiketleri doldur
                    var tagSelect = $('.tags-select');
                    tagSelect.empty();
                    $.each(response.data.tags, function(i, tag) {
                        var selected = selectedTags && selectedTags.includes(parseInt(tag.term_id)) ? 'selected' : '';
                        tagSelect.append($('<option>', {
                            value: tag.term_id,
                            text: tag.name,
                            selected: selected
                        }));
                    });
                }
            }
        });
    }

    // Düzenleme formunu gönderme olayı
    $(document).on('submit', '#edit-product-form', function(e) {
        e.preventDefault();
        
        var formData = {
            product_id: $('input[name="product_id"]').val(),
            name: $('input[name="name"]').val(),
            sku: $('input[name="sku"]').val(),
            price: $('input[name="price"]').val(),
            stock: $('input[name="stock"]').val(),
            categories: $('.categories-select').val(),
            tags: $('.tags-select').val(),
            short_description: $('textarea[name="short_description"]').val(),
            description: $('textarea[name="description"]').val(),
            image_id: $('input[name="image_id"]').val(),
            gallery_ids: $('input[name="gallery_ids"]').val() ? $('input[name="gallery_ids"]').val().split(',') : []
        };
        
        $.ajax({
            url: dokanStock.ajaxurl,
            type: 'POST',
            data: {
                action: 'update_product',
                security: dokanStock.security,
                product_data: formData
            },
            beforeSend: function() {
                // Butonun metnini değiştir ve devre dışı bırak
                $('#edit-product-form button[type="submit"]').prop('disabled', true).text('Güncelleniyor...');
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    $('#edit-product-popup').remove();
                    location.reload();
                } else {
                    alert('Hata: ' + response.data);
                    // Butonu tekrar kullanılabilir hale getir
                    $('#edit-product-form button[type="submit"]').prop('disabled', false).text('Güncelle');
                }
            },
            error: function() {
                alert('Sunucu ile iletişim hatası');
                // Butonu tekrar kullanılabilir hale getir
                $('#edit-product-form button[type="submit"]').prop('disabled', false).text('Güncelle');
            }
        });
    });

    // İptal butonuna tıklama olayı
    $(document).on('click', '.cancel-edit', function() {
        $('#edit-product-popup').remove();
    });

    // Düzenleme popup'ı dışına tıklama ile kapatma
    $(document).on('click', '#edit-product-popup', function(e) {
        if (e.target === this) {
            $('#edit-product-popup').remove();
        }
    });
});