<?php
// public/edit_hangar.php

require_once '../src/config/database.php'; // $pdo ve BASE_PATH
require_once BASE_PATH . '/src/functions/auth_functions.php';

require_approved_user();

$page_title = "Hangar Yönetimi";
$user_id = $_SESSION['user_id'];
$api_error_message = null; 

// Kullanıcının mevcut hangarındaki gemileri çek
$user_hangar_ships = [];
try {
    $stmt_hangar = $pdo->prepare(
        "SELECT id, ship_api_id, ship_name, ship_manufacturer, ship_image_url, quantity 
         FROM user_hangar WHERE user_id = ? ORDER BY ship_name ASC"
    );
    $stmt_hangar->execute([$user_id]);
    $user_hangar_ships = $stmt_hangar->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Kullanıcı hangarını çekme hatası: " . $e->getMessage());
    $_SESSION['error_message'] = "Hangar bilgileri yüklenirken bir sorun oluştu.";
}

require_once BASE_PATH . '/src/includes/header.php';
require_once BASE_PATH . '/src/includes/navbar.php';
?>

<div class="hangar-editor-page">
    <div class="hangar-editor-header">
        <h1><?php echo htmlspecialchars($page_title); ?></h1>
        <a href="view_profile.php?user_id=<?php echo $user_id; ?>" class="btn btn-outline-turquase btn-sm">&laquo; Profilime Dön</a>
    </div>

    <p class="message error-message" id="apiErrorMessage" style="display:none; background-color: #f8d7da; color: #721c24; border:1px solid #f5c6cb; padding:10px; border-radius:4px;"></p>


    <div class="hangar-editor-layout">
        <section class="hangar-column current-hangar-column">
            <h2 class="column-title">Hangarındaki Gemiler (<?php echo count($user_hangar_ships); ?>)</h2>
            <?php if (empty($user_hangar_ships)): ?>
                <p class="empty-hangar-message">Hangarında henüz hiç gemi yok. Sağdaki bölümden arama yaparak ekleyebilirsin.</p>
            <?php else: ?>
                <form action="../src/actions/handle_hangar_edit.php" method="POST" id="updateHangarForm">
                    <input type="hidden" name="action" value="update_hangar_quantities">
                    <div class="current-hangar-grid">
                        <?php foreach ($user_hangar_ships as $hangar_ship): ?>
                            <div class="ship-card hangar-display-card">
                                <img src="<?php echo htmlspecialchars($hangar_ship['ship_image_url'] ?: 'https://via.placeholder.com/200x120.png?text=Gemi'); ?>" alt="<?php echo htmlspecialchars($hangar_ship['ship_name']); ?>" class="ship-card-image">
                                <div class="ship-card-body">
                                    <h5 class="ship-card-name"><?php echo htmlspecialchars($hangar_ship['ship_name']); ?></h5>
                                    <p class="ship-card-manufacturer"><?php echo htmlspecialchars($hangar_ship['ship_manufacturer'] ?? 'Bilinmiyor'); ?></p>
                                    <input type="hidden" name="hangar_item_ids[]" value="<?php echo $hangar_ship['id']; ?>">
                                    <div class="form-group quantity-group-display">
                                        <label for="quantity_span_<?php echo $hangar_ship['id']; ?>">Adet:</label>
                                        <div class="quantity-controls">
                                            <button type="button" class="quantity-btn minus-btn" data-target-id="quantity_span_<?php echo $hangar_ship['id']; ?>" data-input-id="quantity_hidden_<?php echo $hangar_ship['id']; ?>">-</button>
                                            <span id="quantity_span_<?php echo $hangar_ship['id']; ?>" class="quantity-display"><?php echo htmlspecialchars($hangar_ship['quantity']); ?></span>
                                            <input type="hidden" name="quantities[]" id="quantity_hidden_<?php echo $hangar_ship['id']; ?>" value="<?php echo htmlspecialchars($hangar_ship['quantity']); ?>">
                                            <button type="button" class="quantity-btn plus-btn" data-target-id="quantity_span_<?php echo $hangar_ship['id']; ?>" data-input-id="quantity_hidden_<?php echo $hangar_ship['id']; ?>">+</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="submit" class="btn btn-primary btn-full-width" style="margin-top:20px;">Hangar Adetlerini Güncelle</button>
                    <small class="form-text-note">(Adedi 0 yapılan gemiler hangardan çıkarılacaktır)</small>
                </form>
            <?php endif; ?>
        </section>

        <section class="hangar-column api-ship-adder-column">
            <h2 class="column-title">Hangara Yeni Gemi Ekle</h2>
            <div class="ship-search-form form-group">
                <label for="ship_search_query">Gemi Adı ile Ara:</label>
                <div style="display:flex; gap:10px;">
                    <input type="text" id="ship_search_query" name="ship_search_query" placeholder="Örn: Aurora, Constellation...">
                    <button type="button" id="searchShipApiButton" class="btn btn-info">Ara</button>
                </div>
            </div>

            <div id="shipSearchResults" class="api-ship-search-results-grid">
                <p class="search-placeholder">Aramak istediğiniz gemi adını girin.</p>
            </div>

            <div id="shipSelectionBasket" class="ship-selection-basket">
                <h4 class="basket-title">Ekleme Sepeti (<span id="basketItemCount">0</span> Gemi)</h4>
                <div id="basketItemsContainer">
                    <p class="empty-basket-message">Sepete eklemek için arama sonuçlarından gemi seçin.</p>
                </div>
                <form action="../src/actions/handle_hangar_edit.php" method="POST" id="addBasketToHangarForm" style="margin-top:15px;">
                    <input type="hidden" name="action" value="add_basket_to_hangar">
                    <button type="submit" class="btn btn-success btn-full-width" id="addBasketButton" style="display:none;">Sepetteki Gemileri Hangara Ekle</button>
                </form>
            </div>
        </section>
    </div>
</div>

<?php
$hangar_api_ids_for_js = [];
if (!empty($user_hangar_ships)) {
    $hangar_api_ids_for_js = array_column($user_hangar_ships, 'ship_api_id');
}
?>
<script>
    window.userHangarShipApiIds = <?php echo json_encode($hangar_api_ids_for_js); ?>;
</script>
<?php require_once BASE_PATH . '/src/includes/footer.php'; ?>