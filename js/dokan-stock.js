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
        showNewProductPopup();
    });

    function showNewProductPopup() {
        var popupHtml = `
            <div id="new-product-popup" class="stock-popup">
                <div class="popup-content">
                    <h3 class="popup-title">Yeni Ürün Ekle</h3>
                    <form id="new-product-form">
                        <div class="form-group">
                            <label>Ürün Adı:</label>
                            <input type="text" id="product-name" required>
                        </div>
                        <div class="form-group">
                            <label>Ürün Kodu (SKU):</label>
                            <input type="text" id="product-sku">
                        </div>
                        <div class="form-group">
                            <label>Başlangıç Stok Miktarı:</label>
                            <input type="number" id="product-stock" min="0" value="0">
                        </div>
                        <div class="form-group">
                            <label>Fiyat:</label>
                            <input type="number" id="product-price" min="0" step="0.01" required>
                        </div>
                        <div class="form-group">
                            <label>Ürün Açıklaması:</label>
                            <textarea id="product-description"></textarea>
                        </div>
                        <div class="form-group">
                            <label>Ürün Görseli:</label>
                            <div class="image-upload-container">
                                <img id="product-image-preview" src="" style="display: none;">
                                <input type="hidden" id="product-image-id">
                                <button type="button" class="button select-image">Görsel Seç</button>
                                <button type="button" class="button remove-image" style="display: none;">Görseli Kaldır</button>
                            </div>
                        </div>
                        <div class="button-group">
                            <button type="submit" class="button save-new-product">Kaydet</button>
                            <button type="button" class="button cancel-new-product">İptal</button>
                        </div>
                    </form>
                </div>
            </div>`;

        $('body').append(popupHtml);
        $('#new-product-popup').show();

        // Form submit handler
        $('#new-product-form').on('submit', function(e) {
            e.preventDefault();
            
            var name = $('#product-name').val().trim();
            var sku = $('#product-sku').val().trim();
            var stock = parseInt($('#product-stock').val()) || 0;
            var price = parseFloat($('#product-price').val()) || 0;
            var description = $('#product-description').val().trim();
            var imageId = $('#product-image-id').val();

            // Form validasyonu
            if (!name) {
                alert('Lütfen ürün adını giriniz');
                return;
            }

            if (!price || price <= 0) {
                alert('Lütfen geçerli bir fiyat giriniz');
                return;
            }

            // AJAX isteği
            $.ajax({
                url: dokanStock.ajaxurl,
                type: 'POST',
                data: {
                    action: 'add_new_product',
                    security: dokanStock.security,
                    product_data: {
                        name: name,
                        sku: sku,
                        stock: stock,
                        price: price,
                        description: description,
                        image_id: imageId || 0
                    }
                },
                success: function(response) {
                    console.log('Sunucu yanıtı:', response);
                    if (response.success) {
                        alert('Ürün başarıyla eklendi');
                        location.reload();
                    } else {
                        alert('Hata: ' + (response.data || 'Bilinmeyen bir hata oluştu'));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX hatası:', error);
                    console.error('Durum:', status);
                    console.error('Yanıt:', xhr.responseText);
                    alert('Bir hata oluştu. Lütfen tekrar deneyin.');
                }
            });
        });

        // WordPress medya yükleyici
        var mediaUploader;
        $('.select-image').on('click', function(e) {
            e.preventDefault();

            if (mediaUploader) {
                mediaUploader.open();
                return;
            }

            mediaUploader = wp.media({
                title: 'Ürün Görseli Seç',
                button: {
                    text: 'Görseli Kullan'
                },
                multiple: false,
                library: {
                    type: 'image'
                }
            });

            mediaUploader.on('select', function() {
                var attachment = mediaUploader.state().get('selection').first().toJSON();
                $('#product-image-preview').attr('src', attachment.url).show();
                $('#product-image-id').val(attachment.id);
                $('.select-image').hide();
                $('.remove-image').show();
            });

            mediaUploader.open();
        });

        // Görseli kaldır
        $('.remove-image').on('click', function() {
            $('#product-image-preview').attr('src', '').hide();
            $('#product-image-id').val('');
            $('.select-image').show();
            $('.remove-image').hide();
        });

        // İptal butonu
        $('.cancel-new-product').on('click', function() {
            $('#new-product-popup').remove();
        });
    }

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
});