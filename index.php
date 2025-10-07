<?php
define('ONESTORAGE_RUNNING', true);
// 開発用
ini_set('display_errors', 1);
error_reporting(E_ALL);
// セッション
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// SETTING
require_once __DIR__ . '/path.php';
require_once __DIR__ . '/functions/init.php';
require_once __DIR__ . '/functions/helpers.php';
// AUTH
require_once __DIR__ . '/functions/cookie.php';
require_once __DIR__ . '/functions/auth.php';
check_authentication();
// ハンドラー
require_once __DIR__ . '/functions/handler_get_method.php';
require_once __DIR__ . '/functions/handler_post_method.php';

// --- 画面描画 ---
$current_path_raw = $_GET['path'] ?? '';
$is_star_view = ($current_path_raw === 'starred');
$is_inbox_view = is_inbox_view($current_path_raw);
$is_root_view = is_root_view($current_path_raw);

if ($is_star_view) {
    $current_path = ''; // 疑似的なパス
    $web_path = ''; // 疑似的なウェブパス
    $items = [];
    $all_starred_items = load_star_config();

    foreach ($all_starred_items as $star_item) {
        $is_inbox_starred = ($star_item['path'] === 'inbox');

        if ($is_inbox_starred) {
            $full_path = realpath(DATA_ROOT . DIRECTORY_SEPARATOR . INBOX_DIR_NAME . DIRECTORY_SEPARATOR . $star_item['name']);
        } else {
            $item_path_from_root = ltrim($star_item['path'] . '/' . $star_item['name'], '/');
            $full_path = realpath(DATA_ROOT . '/' . $item_path_from_root);
        }

        if ($full_path !== false && strpos($full_path, DATA_ROOT) === 0) {
            $is_dir = is_dir($full_path);
            $size = $is_dir ? null : filesize($full_path);

            $items[] = [
                'name' => $star_item['name'],
                'path' => $star_item['path'],
                'is_dir' => $is_dir,
                'size' => $size,
                'formatted_size' => format_bytes($size),
                'is_starred' => true
            ];
        } else {
        }
    }
} elseif ($is_inbox_view) {
    $current_path = get_inbox_path(); // 隠しディレクトリの絶対パスを取得
    $web_path = 'inbox'; // 疑似的なウェブパス
    $items = [];

    $all_items = array_diff(scandir($current_path), ['.', '..']);
    natsort($all_items);

    $starred_items = load_star_config();
    $starred_hashes = [];
    foreach ($starred_items as $star) {
        if ($star['path'] === $web_path) {
            $starred_hashes[get_item_hash($star['path'], $star['name'])] = true;
        }
    }

    foreach ($all_items as $item) {
        if (str_starts_with($item, '.')) continue; // .で始まるファイルは描画しない
        $item_path = $current_path . '/' . $item;
        $is_dir = is_dir($item_path);

        if ($is_dir) continue;

        $size = filesize($item_path);
        $item_hash = get_item_hash($web_path, $item);

        $items[] = [
            'name' => $item,
            'is_dir' => false,
            'size' => $size,
            'formatted_size' => format_bytes($size),
            'is_starred' => isset($starred_hashes[$item_hash]),
            'path' => 'inbox',
        ];
    }
} else {
    $current_path = realpath(DATA_ROOT . '/' . $current_path_raw);
    if ($current_path === false || strpos($current_path, DATA_ROOT) !== 0) $current_path = DATA_ROOT;
    $web_path = ltrim(substr($current_path, strlen(DATA_ROOT)), '/');
    $web_path = str_replace('\\', '/', $web_path);
    $items = [];
    $all_items = array_diff(scandir($current_path), ['.', '..']);
    natsort($all_items);

    $starred_items = load_star_config();
    $starred_hashes = [];
    foreach ($starred_items as $star) {
        if ($star['path'] === $web_path) {
            $starred_hashes[get_item_hash($star['path'], $star['name'])] = true;
        }
    }

    foreach ($all_items as $item) {
        if (str_starts_with($item, '.')) continue;
        $item_path = $current_path . '/' . $item;
        $is_dir = is_dir($item_path);
        $size = $is_dir ? null : filesize($item_path);

        $item_hash = get_item_hash($web_path, $item);

        $items[] = [
            'name' => $item,
            'is_dir' => $is_dir,
            'size' => $size,
            'formatted_size' => format_bytes($size),
            'is_starred' => isset($starred_hashes[$item_hash]),
            'path' => $web_path,
        ];
    }
}

if (!$is_star_view) {
    usort($items, fn($a, $b) => ($a['is_dir'] !== $b['is_dir']) ? ($a['is_dir'] ? -1 : 1) : strcasecmp($a['name'], $b['name']));
}

$dir_cache = load_dir_cache();
$sidebar_folders = $dir_cache['tree'];
$all_dirs = $dir_cache['list'];
$breadcrumbs = [];
//パンくずリスト
if ($is_star_view) {
    $breadcrumbs[] = ['name' => 'Starred Items', 'path' => 'starred'];
} elseif ($is_inbox_view) {
    $breadcrumbs[] = ['name' => 'INBOX', 'path' => 'inbox'];
} else {
    $breadcrumbs[] = ['name' => 'home', 'path' => ''];
    if (!empty($web_path)) {
        $tmp_path = '';
        foreach (explode('/', $web_path) as $part) {
            $tmp_path .= (empty($tmp_path) ? '' : '/') . $part;
            $breadcrumbs[] = ['name' => $part, 'path' => $tmp_path];
        }
    }
}
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);
$json_message = json_encode($message);

// 変数
$STAR_API_URL = 'functions/star.php';
$json_star_view = json_encode($is_star_view);
?>
<!DOCTYPE html>
<html lang="ja">
<?php require_once __DIR__ . '/static/template_head.php'; ?>

<body>
    <?php require_once __DIR__ . '/static/template_nav.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="col-md-4 col-lg-3 d-md-block bg-ligth sidebar collapse d-md-flex flex-column">

                <div id="sidebarTopFixed" class="py-3 flex-shrink-0">
                    <ul class="nav flex-column px-3">
                        <li class="nav-item">
                            <a class="nav-link <?= $is_star_view ? 'active' : '' ?>" href="?path=starred">
                                <i class="bi bi-star-fill me-2" style="color: gold;"></i>Starred Items
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_inbox_view ? 'active' : '' ?>" href="?path=inbox">
                                <i class="bi bi-inbox-fill me-2 text-info"></i>INBOX
                            </a>
                        </li>
                        <hr>
                        <li class="nav-item">
                            <a class="nav-link px-3 <?= (empty($web_path) && !$is_star_view) ? 'active' : '' ?>" href="?path=">
                                <i class="bi bi-house-door me-2"></i>home
                            </a>
                        </li>
                    </ul>
                </div>

                <div id="sidebarScrollable" class="sidebar-sticky overflow-auto">
                    <ul class="nav flex-column mb-2">
                        <?php
                        function render_folder_tree($folders, $current_path, $level = 0, $max_depth = 2)
                        {
                            $html = '';
                            $indent_px = 16 * ($level + 1);
                            foreach ($folders as $folder) {
                                $has_children = !empty($folder['children']);
                                $is_active = ($current_path === $folder['path']);
                                $is_active_parent = str_starts_with($current_path, $folder['path'] . '/');
                                $li_classes = 'nav-item nav-item-folder' . ($is_active_parent ? ' is-active-parent' : '');
                                $link_classes = 'nav-link d-flex align-items-center' . ($is_active ? ' is-active' : '');
                                $collapse_id = 'collapse-' . str_replace('/', '-', $folder['path']);
                                $is_collapsed_open = $is_active || $is_active_parent;

                                $html .= '<li class="' . $li_classes . '">';
                                $html .= '<a class="' . $link_classes . '" href="?path=' . urlencode($folder['path']) . '" style="padding-left: ' . $indent_px . 'px;">';
                                if ($has_children) {
                                    $html .= '<i class="bi me-1 toggle-icon" data-bs-toggle="collapse" data-bs-target="#' . $collapse_id . '" aria-expanded="' . ($is_collapsed_open ? 'true' : 'false') . '" style="cursor: pointer;">' . ($is_collapsed_open ? '▾' : '▸') . '</i>';
                                } else {
                                    $html .= '<i class="me-1" style="width: 1rem;"></i>';
                                }
                                $html .= '<i class="bi bi-folder me-2"></i>' . htmlspecialchars($folder['name'], ENT_QUOTES, 'UTF-8') . '</a>';
                                // max_depthを超えていない場合のみ子要素をレンダリング
                                if ($has_children && $level < $max_depth) {
                                    $html .= '<div class="collapse ' . ($is_collapsed_open ? 'show' : '') . '" id="' . $collapse_id . '"><ul class="nav flex-column">' . render_folder_tree($folder['children'], $current_path, $level + 1, $max_depth) . '</ul></div>'; // ★ max_depthを渡す
                                }
                                $html .= '</li>';
                            }
                            return $html;
                        }
                        echo render_folder_tree($sidebar_folders, $web_path);
                        ?>
                    </ul>
                </div>

                <div id="sidebarBottomFixed" class="p-3 border-top flex-shrink-0 text-center">
                    <img class="img-fluid" src="static/img_logo.PNG" alt="ONE STORAGE Logo" style="width: 100%; max-width: 150px; margin: 0 auto;">
                </div>
            </nav>

            <main class="col-md-8 ms-sm-auto col-lg-9 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div id="breadcrumbContainer" class="flex-grow-1">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <?php foreach ($breadcrumbs as $crumb): ?>
                                    <li class="breadcrumb-item">
                                        <?php if ($crumb['name'] === 'Starred Items'): ?>
                                            <a href="?path=starred" class="text-dark text-decoration-none">
                                                <i class="bi bi-star-fill me-1 text-warning"></i><?= htmlspecialchars($crumb['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php elseif ($crumb['name'] === 'INBOX'): ?>
                                            <a href="?path=inbox" class="text-dark text-decoration-none">
                                                <i class="bi bi-inbox-fill me-1 text-info"></i><?= htmlspecialchars($crumb['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php elseif ($crumb['name'] === 'home'): ?>
                                            <a href="?path=inbox" class="text-dark text-decoration-none">
                                                <i class="bi bi-inbox-fill me-1 text-info"></i>home
                                            </a>
                                        <?php else: ?>
                                            <a href="?path=<?= urlencode($crumb['path']) ?>" class="text-dark text-decoration-none">
                                                <?= htmlspecialchars($crumb['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </nav>
                    </div>
                    <div id="tableActionsContainer" class="d-none">
                        <span class="text-muted me-3"><strong id="selectionCount">0</strong>個選択中</span>
                        <?php if (!$is_star_view): ?>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#moveItemsModal"><i class="bi bi-folder-symlink"></i> 選択項目を移動</button>
                            <button class="btn btn-danger btn-sm" id="batchDeleteBtn"><i class="bi bi-trash"></i> 選択項目を削除</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="file-list">
                    <div class="row gx-2 text-muted border-bottom py-2 d-none d-md-flex small">
                        <div class="col-auto" style="width: 30px;"></div>
                        <div class="col-auto">
                            <input class="form-check-input" type="checkbox" id="selectAllCheckbox">
                        </div>
                        <div class="col fw-bold">ファイル名</div>
                        <div class="col-3 col-lg-2 text-end fw-bold">容量</div>
                        <div class="col-auto" style="width: 50px;"></div>
                    </div>

                    <?php if (empty($items)): ?>
                        <div class="text-center text-muted py-5">ファイルがありません</div>
                    <?php else: ?>
                        <?php foreach ($items as $item):
                            $item_web_path_for_action = $item['path'] ?? $web_path;
                            $item_full_web_path = ltrim($item_web_path_for_action . '/' . $item['name'], '/');
                            $is_starred = $item['is_starred'] ?? false;
                        ?>
                            <div class="row gx-2 d-flex align-items-center border-bottom file-row">
                                <div class="col-auto py-2">
                                    <button type="button" class="btn btn-sm btn-light star-toggle-btn"
                                        data-web-path="<?= htmlspecialchars($item_web_path_for_action, ENT_QUOTES, 'UTF-8') ?>"
                                        data-item-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-is-dir="<?= $item['is_dir'] ? '1' : '0' ?>"
                                        title="<?= $is_starred ? 'スターを解除' : 'スターに登録' ?>">
                                        <i class="bi bi-star<?= $is_starred ? '-fill text-warning' : ' text-muted' ?>"></i>
                                    </button>
                                </div>
                                <div class="col-auto py-2">
                                    <input class="form-check-input item-checkbox" type="checkbox" value="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col text-truncate py-2">
                                    <?php if ($item['is_dir']): ?>
                                        <a href="?path=<?= urlencode($is_star_view ? $item_web_path_for_action : ltrim($web_path . '/' . $item['name'], '/')) ?>" class="d-flex align-items-center">
                                            <i class="bi bi-folder-fill text-primary me-2 fs-5"></i>
                                            <span><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($is_star_view): ?>
                                                <span class="ms-2 badge bg-secondary-subtle text-secondary fw-normal small">in: /<?= htmlspecialchars($item_web_path_for_action, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </a>
                                    <?php else: ?>
                                        <a href="?action=view&path=<?= urlencode($item_full_web_path) ?>" target="_blank" class="d-flex align-items-center">
                                            <i class="bi bi-file-earmark-text me-2 fs-5"></i>
                                            <span><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php if ($is_star_view): ?>
                                                <span class="ms-2 badge bg-secondary-subtle text-secondary fw-normal small">in: /<?= htmlspecialchars($item_web_path_for_action, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="col-3 d-none d-md-block col-lg-2 text-end py-2">
                                    <?= htmlspecialchars($item['formatted_size'], ENT_QUOTES, 'UTF-8') ?>
                                </div>
                                <div class="col-auto py-2">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="アクション">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">

                                            <?php if (!$is_star_view): ?>
                                                <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#renameItemModal" data-bs-item-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>" data-bs-is-dir="<?= $item['is_dir'] ? '1' : '0' ?>"><i class="bi bi-pencil-fill me-2"></i>名前の変更</button></li>
                                            <?php endif; ?>

                                            <li><a class="dropdown-item" href="?action=download&path=<?= urlencode($item_full_web_path) ?>"><i class="bi bi-download me-2"></i>ダウンロード</a></li>

                                            <?php if ($is_star_view): ?>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li><a class="dropdown-item" href="?path=<?= urlencode($item_web_path_for_action) ?>"><i class="bi bi-arrow-return-right me-2"></i>元のフォルダへ</a></li>
                                            <?php endif; ?>

                                            <?php if (!$is_star_view): ?>
                                                <li>
                                                    <hr class="dropdown-divider">
                                                </li>
                                                <li>
                                                    <form action="index.php" method="post" onsubmit="return confirm('本当に「<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>」を削除しますか？\nこの操作は元に戻せません。');">
                                                        <input type="hidden" name="action" value="delete_item">
                                                        <input type="hidden" name="path" value="<?= htmlspecialchars($item_web_path_for_action, ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="item_name" value="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash-fill me-2"></i>削除</button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>

                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

    <?php
    require_once __DIR__ . '/static/component_toast_container.php';
    require_once __DIR__ . '/static/component_create_folder_modal.php';
    require_once __DIR__ . '/static/component_upload_file_modal.php';
    require_once __DIR__ . '/static/component_rename_item_modal.php';
    require_once __DIR__ . '/static/component_move_item_modal.php'
    ?>
    <form action="index.php" method="post" id="batchDeleteForm" class="d-none">
        <input type="hidden" name="action" value="delete_items">
        <input type="hidden" name="path" value="<?= htmlspecialchars($web_path, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="items_json" id="delete_items_json">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const phpMessage = <?= $json_message ?>;
        const STAR_API_URL = '<?= $STAR_API_URL ?>';
        const isStarView = <?= $json_star_view ?>;
    </script>
    <script src="static/asset_index.js"></script>
</body>

</html>