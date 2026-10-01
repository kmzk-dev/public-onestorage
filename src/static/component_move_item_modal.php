<div class="modal fade" id="moveItemsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">アイテムの移動</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="index.php" method="post" id="moveItemsForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="move_items">
                    <input type="hidden" name="path" value="<?= htmlspecialchars($web_path, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="items_json" id="move_items_json">
                    <input type="hidden" name="item_name" id="move_item_name">
                    <input type="hidden" name="destination" id="moveModalDestination" value="">

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">移動先フォルダの選択</label>
                        
                        <!-- 現在の階層ナビゲーションヘッダー -->
                        <div class="d-flex align-items-center p-2 bg-light border rounded mb-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary me-2 flex-shrink-0" id="moveModalUpBtn" disabled>
                                <i class="bi bi-arrow-up"></i> 上へ
                            </button>
                            <div class="text-truncate flex-grow-1">
                                <span class="text-muted small me-1">現在地:</span>
                                <span id="moveModalCurrentPathDisplay" class="fw-bold small text-dark">/ (ホーム)</span>
                            </div>
                        </div>

                        <!-- フォルダ一覧リスト -->
                        <div class="list-group border rounded" id="moveModalFolderList" style="max-height: 280px; overflow-y: auto;">
                            <!-- JSで動的に描画 -->
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                    <button type="submit" class="btn btn-primary" id="moveModalSubmitBtn">ここに移動</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const directoryTree = <?= json_encode($sidebar_folders, JSON_UNESCAPED_UNICODE) ?>;
    const initialPath = <?= json_encode($web_path, JSON_UNESCAPED_UNICODE) ?>;
    
    // パスからフォルダ情報を高速に引けるようにMapを構築
    const folderMap = {
        '': { path: '', name: 'ホーム', children: Array.isArray(directoryTree) ? [...directoryTree] : [], parent: null }
    };

    function buildMap(nodes, parentPath) {
        if (!Array.isArray(nodes)) return;
        nodes.forEach(node => {
            folderMap[node.path] = {
                path: node.path,
                name: node.name,
                children: Array.isArray(node.children) ? [...node.children] : [],
                parent: parentPath
            };
            if (node.children && node.children.length > 0) {
                buildMap(node.children, node.path);
            }
        });
    }
    buildMap(directoryTree, '');

    let currentPath = (initialPath && folderMap[initialPath]) ? initialPath : '';
    
    const upBtn = document.getElementById('moveModalUpBtn');
    const pathDisplay = document.getElementById('moveModalCurrentPathDisplay');
    const folderList = document.getElementById('moveModalFolderList');
    const destInput = document.getElementById('moveModalDestination');
    const submitBtn = document.getElementById('moveModalSubmitBtn');
    const moveModalEl = document.getElementById('moveItemsModal');
    
    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
    function renderFolder(path) {
        currentPath = path;
        const folder = folderMap[path];
        if (!folder) return;
        
        // フォーム送信用の値を更新
        if (destInput) {
            destInput.value = path;
        }
        
        // UI表示の更新
        if (pathDisplay) {
            pathDisplay.textContent = (path === '') ? '/ (ホーム)' : '/' + path;
        }
        if (upBtn) {
            upBtn.disabled = (path === '');
        }
        
        // 実行ボタンのテキストと有効/無効の更新
        if (submitBtn) {
            const isSameAsInitial = (path === initialPath && initialPath !== 'inbox' && initialPath !== 'starred');
            const rawJson = document.getElementById('move_items_json') ? document.getElementById('move_items_json').value : '[]';
            const singleName = document.getElementById('move_item_name') ? document.getElementById('move_item_name').value : '';
            let fileCount = 0;
            try {
                const items = JSON.parse(rawJson || '[]');
                fileCount = items.length;
            } catch(e) {}
            if (singleName) fileCount = 1;

            if (fileCount === 0) {
                submitBtn.disabled = true;
                submitBtn.textContent = '移動可能なファイルがありません';
            } else if (isSameAsInitial) {
                submitBtn.disabled = true;
                submitBtn.textContent = '現在のフォルダです';
            } else {
                submitBtn.disabled = false;
                submitBtn.textContent = (path === '') ? 'ホームへ移動' : `「${folder.name}」へ移動`;
            }
        }
        
        // リストの描画
        if (folderList) {
            folderList.innerHTML = '';
            if (!folder.children || folder.children.length === 0) {
                folderList.innerHTML = '<div class="p-3 text-muted text-center small"><i class="bi bi-folder2-open me-1"></i>サブフォルダはありません</div>';
            } else {
                folder.children.forEach(child => {
                    const btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2';
                    
                    const leftDiv = document.createElement('div');
                    leftDiv.className = 'd-flex align-items-center text-truncate me-2';
                    leftDiv.innerHTML = '<i class="bi bi-folder-fill text-warning fs-5 me-2 flex-shrink-0"></i><span class="text-truncate">' + escapeHtml(child.name) + '</span>';
                    
                    const arrowIcon = document.createElement('i');
                    arrowIcon.className = 'bi bi-chevron-right text-muted small flex-shrink-0';
                    
                    btn.appendChild(leftDiv);
                    btn.appendChild(arrowIcon);
                    
                    btn.onclick = () => renderFolder(child.path);
                    folderList.appendChild(btn);
                });
            }
        }
    }
    
    // 「上へ」ボタンの挙動
    if (upBtn) {
        upBtn.addEventListener('click', () => {
            if (currentPath !== '') {
                const parentPath = folderMap[currentPath] ? folderMap[currentPath].parent : null;
                if (parentPath !== null && parentPath !== undefined) {
                    renderFolder(parentPath);
                }
            }
        });
    }
    
    // モーダルが開かれたときにリセットする（現在のフォルダを開き直す）
    if (moveModalEl) {
        moveModalEl.addEventListener('show.bs.modal', function () {
            const startPath = (initialPath && folderMap[initialPath]) ? initialPath : '';
            renderFolder(startPath);
        });
    }
    
    // 移動フォームの送信処理 (AJAXで送信し、失敗時はリロードせずトーストを表示)
    const moveForm = document.getElementById('moveItemsForm');
    if (moveForm) {
        moveForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!submitBtn) return;
            const originalBtnText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>移動中...';

            try {
                const formData = new FormData(moveForm);
                formData.append('ajax', '1');
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                const data = await response.json();
                
                // モーダルを閉じる
                if (moveModalEl) {
                    const modalInst = bootstrap.Modal.getInstance(moveModalEl);
                    if (modalInst) modalInst.hide();
                }

                if (data.type === 'danger' || data.type === 'warning') {
                    // 失敗・警告時は再読み込みせず、トーストを手動消去まで表示
                    showToast(data.type, data.text);
                } else {
                    // 成功時のみトーストを表示して再読み込み
                    showToast('success', data.text);
                    setTimeout(() => {
                        location.reload();
                    }, 600);
                }
            } catch (err) {
                showToast('danger', '移動処理中に通信エラーが発生しました: ' + err.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            }
        });
    }

    // 初期描画
    renderFolder(currentPath);
});
</script>