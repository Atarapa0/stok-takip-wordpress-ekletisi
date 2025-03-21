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

    // Excel export fonksiyonu
    $('#export-excel').on('click', function() {
        var date = new Date();
        var filename = 'stok-raporu-' + date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate() + '.xls';
        
        // Excel için tablo başlığı
        var tableHtml = `
            <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
            <head>
                <meta charset="UTF-8">
                <style>
                    table { border-collapse: collapse; }
                    th, td { border: 1px solid #000; padding: 5px; }
                    th { background-color: #f0f0f0; }
                    .header-row { font-weight: bold; background-color: #e0e0e0; }
                </style>
            </head>
            <body>
            <table>
                <thead>
                    <tr>
                        <th>Ürün Kodu</th>
                        <th>Ürün Adı</th>
                        <th>Mevcut Stok</th>
                        <th>Online Satış</th>
                        <th>Manuel Satış</th>
                        <th>Hareket</th>
                        <th>Tarih</th>
                    </tr>
                </thead>
                <tbody>`;

        // Her ürün için bilgileri ve hareketleri al
        $('.stock-table tbody tr:not(.history-row, .inline-form-row)').each(function() {
            var $productRow = $(this);
            var productId = $productRow.find('.stock-add-btn').data('product-id');
            var sku = $productRow.find('td:eq(0)').text();
            var productName = $productRow.find('td:eq(1)').text();
            var currentStock = $productRow.find('td:eq(2)').text();
            var onlineSales = $productRow.find('td:eq(3)').text();
            var manualSales = $productRow.find('td:eq(4)').text();

            // Ürün başlık satırı
            tableHtml += `
                <tr class="header-row">
                    <td>${sku}</td>
                    <td>${productName}</td>
                    <td>${currentStock}</td>
                    <td>${onlineSales}</td>
                    <td>${manualSales}</td>
                    <td></td>
                    <td></td>
                </tr>`;

            // Boş satır
            tableHtml += `
                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>`;
            
            // AJAX ile ürünün geçmişini al
            $.ajax({
                url: dokanStock.ajaxurl,
                type: 'POST',
                async: false, // Senkron istek yapıyoruz
                data: {
                    action: 'get_stock_history',
                    product_id: productId,
                    security: dokanStock.security
                },
                success: function(response) {
                    if (response.success) {
                        // Geçici bir div oluşturup HTML'i parse edelim
                        var $temp = $('<div>').html(response.data);
                        
                        // Geçmiş tablosundaki her satırı işle
                        $temp.find('tbody tr').each(function() {
                            var $row = $(this);
                            var moveType = $row.find('td:eq(1)').text(); // İşlem tipi
                            var quantity = parseInt($row.find('td:eq(2)').text()); // Miktar
                            var stockAfterMove = $row.find('td:eq(3)').text(); // İşlem sonrası stok
                            var moveDate = $row.find('td:eq(0)').text(); // İşlem tarihi

                            tableHtml += `
                                <tr>
                                    <td></td>
                                    <td></td>
                                    <td>${stockAfterMove}</td>
                                    <td>${moveType === 'Online Satış' ? quantity : ''}</td>
                                    <td>${moveType === 'Manuel Satış' ? quantity : ''}</td>
                                    <td>${moveType}</td>
                                    <td>${moveDate}</td>
                                </tr>`;
                        });

                        // Ürünler arası boş satır
                        tableHtml += `
                            <tr>
                                <td colspan="7">&nbsp;</td>
                            </tr>`;
                    }
                }
            });
        });

        // Tabloyu kapat
        tableHtml += '</tbody></table></body></html>';
        
        // Excel dosyasını indir
        var uri = 'data:application/vnd.ms-excel;charset=utf-8,' + encodeURIComponent(tableHtml);
        var link = document.createElement("a");
        link.href = uri;
        link.style = "visibility:hidden";
        link.download = filename;
        
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
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

    // Tarih filtresi için event handler'lar
    $(document).on('click', '.filter-history', function() {
        var $historySection = $(this).closest('.stock-history');
        var productId = $historySection.closest('tr').prev().find('.stock-add-btn').data('product-id');
        var startDate = $historySection.find('.history-date-start').val();
        var endDate = $historySection.find('.history-date-end').val();
        
        if (!startDate || !endDate) {
            alert('Lütfen başlangıç ve bitiş tarihlerini seçin');
            return;
        }
        
        updateHistory(productId, startDate, endDate);
    });

    $(document).on('click', '.reset-filter', function() {
        var $historySection = $(this).closest('.stock-history');
        var productId = $historySection.closest('tr').prev().find('.stock-add-btn').data('product-id');
        
        // Tarih inputlarını temizle
        $historySection.find('.history-date-start, .history-date-end').val('');
        
        // Filtresiz geçmişi getir
        updateHistory(productId);
    });

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

    // Geçmiş güncelleme fonksiyonu
    function updateHistory(productId, startDate = null, endDate = null) {
        var $historyRow = $('#history_' + productId);
        
        $.ajax({
            url: dokanStock.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_stock_history',
                product_id: productId,
                security: dokanStock.security,
                start_date: startDate,
                end_date: endDate
            },
            success: function(response) {
                if (response.success) {
                    $historyRow.find('.history-content').html(response.data);
                    $historyRow.show();
                } else {
                    alert('Geçmiş bilgileri alınamadı.');
                }
            }
        });
    }
});