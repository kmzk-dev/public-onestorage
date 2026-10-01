<nav class="navbar navbar-dark sticky-top bg-dark flex-nowrap px-3">
    <div class="d-flex align-items-center">
        <button class="navbar-toggler d-md-none border-0 p-1 me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <a class="header-icon-btn me-2" href="index.php" title="ホーム">
            <i class="bi bi-house-door fs-5"></i>
        </a>
    </div>
    
    <div class="px-0 flex-grow-1 d-flex justify-content-end align-items-center">
        <!-- 検索コンポーネント（虫眼鏡アイコンボタン & モーダル） -->
        <?php require_once __DIR__ . '/component_search.php'; ?>

        <?php 
            $is_folder_limit_reached = (!$is_virtual_view && isset($folder_item_count, $max_folder_items) && $folder_item_count >= $max_folder_items);
            $create_folder_disabled = ($is_inbox_view || $is_star_view || !empty($is_recent_view) || $is_sharebox_folder || $is_folder_limit_reached) ? 'disabled' : ''; 
        ?>
        <button class="header-icon-btn me-2" data-bs-toggle="modal" data-bs-target="#createFolderModal" title="<?= $is_folder_limit_reached ? 'フォルダ内アイテム上限（200件）に達しています' : 'フォルダ作成' ?>" <?= $create_folder_disabled ?>>
            <i class="bi bi-folder-plus fs-5"></i>
        </button>
        
        <?php 
            $upload_disabled = ($is_root_view || $is_star_view || !empty($is_recent_view) || $is_sharebox_view || $is_folder_limit_reached) ? 'disabled' : ''; 
        ?>
        <button id="headerUploadBtn" class="header-icon-btn me-2" data-bs-toggle="modal" data-bs-target="#uploadFileModal" title="<?= $is_folder_limit_reached ? 'フォルダ内アイテム上限（200件）に達しています' : 'ファイルアップロード' ?>" <?= $upload_disabled ?>>
            <i class="bi bi-cloud-arrow-up fs-5"></i>
        </button>

        <a href="admin.php" class="header-icon-btn me-2" title="管理設定">
            <i class="bi bi-gear fs-5"></i>
        </a>
    </div>
</nav>