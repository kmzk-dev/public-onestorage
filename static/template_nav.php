<nav class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap">
    <a class="navbar-brand col-md-3 col-lg-2 me-0 px-3 d-none d-md-inline" href="index.php">
        <i class="bi bi-hdd-stack"></i>
        <span class="ms-2 fw-bold">ONE STORAGE</span>
    </a>
    <button class="navbar-toggler d-md-none border border-0 " type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
</button>
    <div class="navbar-nav">
        <div class="nav-item text-nowrap d-flex align-items-center">
        <?php 
            $create_folder_disabled = ($is_inbox_view || $is_star_view) ? 'disabled' : ''; 
        ?>
        <button class="btn btn-outline-light me-2" data-bs-toggle="modal" data-bs-target="#createFolderModal" title="フォルダ作成" <?= $create_folder_disabled ?>>
            <i class="bi bi-folder-plus"></i>
            <span class="d-none d-sm-inline ms-1">フォルダ</span>
        </button>
        
        <?php 
            $upload_disabled = ($is_root_view || $is_star_view ) ? 'disabled' : ''; 
        ?>
        <button id="headerUploadBtn" class="btn btn-light me-3" data-bs-toggle="modal" data-bs-target="#uploadFileModal" title="ファイルアップロード" <?= $upload_disabled ?>>
            <i class="bi bi-cloud-arrow-up"></i>
            <span class="d-none d-sm-inline ms-1">アップロード</span>
        </button>
        <a href="logout.php" class="btn btn-danger me-3" title="ログアウト">
            <i class="bi bi-box-arrow-right"></i>
            <span class="d-none d-sm-inline ms-1">ログアウト</span>
        </a>
    </div></div>
</nav>