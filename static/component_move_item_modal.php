<div class="modal fade" id="moveItemsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">アイテムの移動</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php" method="post">
                <div class="modal-body"><input type="hidden" name="action" value="move_items"><input type="hidden" name="path" value="<?= htmlspecialchars($web_path, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="items_json" id="move_items_json">
                    <div class="mb-3"><label for="destination" class="form-label">移動先のフォルダを選択</label><select class="form-select" name="destination" id="destination" required>
                            <?php foreach ($all_dirs as $dir): ?>
                                <option value="<?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($dir, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                        </select></div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button><button type="submit" class="btn btn-primary">ここに移動</button></div>
            </form>
        </div>
    </div>
</div>