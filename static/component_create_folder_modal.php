<div class="modal fade" id="createFolderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header border-0 bg-dark text-light"> 
                <h5 class="modal-title">新しいフォルダの作成</h5>
            </div>
            <form action="index.php" method="post">
                <div class="modal-body"><input type="hidden" name="action" value="create_folder"><input type="hidden" name="path" value="<?= htmlspecialchars($web_path, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <p class="mb-1 text-break">
                            <i class="bi bi-folder-fill me-1"></i>
                            home/<?= htmlspecialchars(empty($web_path) ? '' : $web_path, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                    <div class="mb-3"><label for="folder_name" class="form-label fw-bold">新しいフォルダ名</label>
                    <input type="text" class="form-control form-control-lg" id="folder_name" name="folder_name" required></div>
                    <div class="form-text">`.`で始まるフォルダ名や、特殊な記号は使えません。</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">作成</button></div>
            </form>
        </div>
    </div>
</div>