<div class="modal fade" id="renameItemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="renameItemModalLabel">名前の変更</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php" method="post">
                <div class="modal-body"><input type="hidden" name="action" value="rename_item"><input type="hidden" name="path" value="<?= htmlspecialchars($web_path, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="old_name" id="rename_old_name">
                    <div class="mb-3"><label for="rename_new_name" class="form-label">新しい名前</label>
                        <div class="input-group"><input type="text" class="form-control" id="rename_new_name" name="new_name" required><span class="input-group-text" id="rename_extension">.ext</span></div>
                        <div class="form-text" id="rename_help_text">記号や`.`で始まる名前は使えません。</div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">変更を保存</button></div>
            </form>
        </div>
    </div>
</div>