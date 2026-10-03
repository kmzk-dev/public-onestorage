<?php
define('ONESTORAGE_RUNNING', true);
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
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
$is_recent_view = ($current_path_raw === 'recent');
$is_inbox_view = is_inbox_view($current_path_raw);
$is_sharebox_view = is_sharebox_view($current_path_raw);
$is_sharebox_folder = str_starts_with($current_path_raw, 'sharebox/') && !$is_sharebox_view;
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
            if ($is_dir) {
                $star_dir_web_path = ltrim($star_item['path'] . '/' . $star_item['name'], '/');
                $size = get_directory_size_from_cache($star_dir_web_path);
            } else {
                $size = filesize($full_path);
            }

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
} elseif ($is_recent_view) {
    $current_path = ''; // 疑似的なパス
    $web_path = 'recent'; // 疑似的なウェブパス
    $items = [];
    $db = get_db();
    if ($db) {
        $stmt = $db->query("
            SELECT f.name, f.path, f.size, f.formatted_size, f.mtime, f.ext
            FROM files f
            WHERE f.path != 'sharebox'
              AND f.path NOT LIKE 'sharebox/%'
              AND NOT EXISTS (
                  SELECT 1 FROM recent_exclusions e
                  WHERE e.path = f.path AND e.name = f.name
              )
            ORDER BY f.mtime DESC
            LIMIT 100
        ");
        $recent_files = $stmt->fetchAll();

        $starred_items = load_star_config();
        $starred_hashes = [];
        foreach ($starred_items as $star) {
            $starred_hashes[get_item_hash($star['path'], $star['name'])] = true;
        }

        foreach ($recent_files as $file) {
            $item_hash = get_item_hash($file['path'], $file['name']);
            $items[] = [
                'name' => $file['name'],
                'path' => $file['path'],
                'is_dir' => false,
                'size' => (int)$file['size'],
                'formatted_size' => $file['formatted_size'],
                'is_starred' => isset($starred_hashes[$item_hash]),
                'mtime' => (int)$file['mtime']
            ];
        }
    }
} elseif ($is_sharebox_view) {
    $current_path = defined('SHARE_ROOT') ? SHARE_ROOT : '';
    $web_path = 'sharebox';
    $items = get_sharebox_folders();
} elseif ($is_sharebox_folder) {
    $folder_name = substr($current_path_raw, 9);
    $current_path = defined('SHARE_ROOT') ? SHARE_ROOT . '/' . $folder_name : '';
    $web_path = $current_path_raw;
    $items = get_sharebox_folder_contents($folder_name);
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

    $dir_items = [];
    foreach ($all_items as $item) {
        if (str_starts_with($item, '.') || $item === 'index.html') continue;
        if (is_dir($current_path . '/' . $item)) {
            $dir_items[] = $item;
        }
    }
    $folder_sizes = get_directory_sizes_batch_from_cache($web_path, $dir_items);

    foreach ($all_items as $item) {
        if (str_starts_with($item, '.') || $item === 'index.html') continue;
        $item_path = $current_path . '/' . $item;
        $is_dir = is_dir($item_path);
        $size = $is_dir ? ($folder_sizes[$item] ?? 0) : filesize($item_path);

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

if (!$is_star_view && !$is_recent_view) {
    usort($items, fn($a, $b) => ($a['is_dir'] !== $b['is_dir']) ? ($a['is_dir'] ? -1 : 1) : strcasecmp($a['name'], $b['name']));
}

$dir_cache = load_dir_cache();
$sidebar_folders = $dir_cache['tree'];
$all_dirs = $dir_cache['list'];
$breadcrumbs = [];
//パンくずリスト
if ($is_star_view) {
    $breadcrumbs[] = ['name' => 'STAR', 'path' => 'starred'];
} elseif ($is_recent_view) {
    $breadcrumbs[] = ['name' => 'RECENT', 'path' => 'recent'];
} elseif ($is_inbox_view) {
    $breadcrumbs[] = ['name' => 'INBOX', 'path' => 'inbox'];
} elseif ($is_sharebox_view) {
    $breadcrumbs[] = ['name' => 'SHARE', 'path' => 'sharebox'];
} elseif ($is_sharebox_folder) {
    $breadcrumbs[] = ['name' => 'SHARE', 'path' => 'sharebox'];
    $folder_name = substr($current_path_raw, 9);
    $breadcrumbs[] = ['name' => $folder_name, 'path' => $current_path_raw];
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

// RECENT除外情報
$recent_exclusions = $is_recent_view ? get_recent_exclusions() : [];
$recent_exclusion_count = $is_recent_view ? count($recent_exclusions) : 0;

// 単一フォルダアイテム件数と上限
$is_virtual_view = ($is_star_view || $is_recent_view);
$folder_item_count = count($items);
$max_folder_items = defined('MAX_FOLDER_ITEMS') ? MAX_FOLDER_ITEMS : 200;
$folder_count_badge_class = ($folder_item_count >= $max_folder_items) 
    ? 'bg-danger text-white' 
    : (($folder_item_count >= 180) ? 'bg-warning text-dark' : 'bg-light text-secondary border');

// 変数
$STAR_API_URL = 'functions/star.php';
$RECENT_API_URL = 'functions/recent_api.php';
$json_star_view = json_encode($is_star_view);
$json_recent_view = json_encode($is_recent_view);
?>
<!DOCTYPE html>
<html lang="ja">
<?php require_once __DIR__ . '/static/template_head.php'; ?>

<body>
    <?php require_once __DIR__ . '/static/template_nav.php'; ?>
    <div class="container-fluid">
        <div class="row">
            <nav id="sidebarMenu" class="offcanvas offcanvas-start sidebar d-md-flex flex-column" tabindex="-1" aria-labelledby="sidebarMenuLabel">
                <div class="offcanvas-header d-md-none border-bottom px-3 py-2">
                    <h6 class="offcanvas-title fw-bold d-flex align-items-center mb-0" id="sidebarMenuLabel">
                        <i class="bi bi-hdd-stack text-primary me-2"></i>メニュー
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
                </div>

                <div id="sidebarTopFixed" class="py-3 flex-shrink-0">
                    <ul class="nav flex-column px-3">
                        <li class="nav-item">
                            <a class="nav-link <?= $is_star_view ? 'active' : '' ?>" href="?path=starred">
                                <i class="bi bi-star-fill me-2" style="color: gold;"></i>STAR
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_recent_view ? 'active' : '' ?>" href="?path=recent">
                                <i class="bi bi-clock-history me-2 text-primary"></i>RECENT
                            </a>
                        </li>
                        <?php 
                            $is_boxes_active = ($is_inbox_view || $is_sharebox_view || $is_sharebox_folder);
                        ?>
                        <li class="nav-item my-1">
                            <div id="boxesCollapseToggle" 
                                 data-bs-toggle="collapse" 
                                 data-bs-target="#boxesCollapse" 
                                 aria-expanded="<?= $is_boxes_active ? 'true' : 'false' ?>" 
                                 title="<?= $is_boxes_active ? '折りたたむ' : 'その他を展開' ?>">
                                <i class="bi bi-chevron-down toggle-icon-closed small"></i>
                                <i class="bi bi-chevron-up toggle-icon-opened small"></i>
                            </div>
                            <div class="collapse <?= $is_boxes_active ? 'show' : '' ?>" id="boxesCollapse">
                                <ul class="nav flex-column mt-1">
                                    <li class="nav-item">
                                        <a class="nav-link <?= $is_inbox_view ? 'active' : '' ?>" href="?path=inbox">
                                            <i class="bi bi-inbox-fill me-2 text-info"></i>INBOX
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <?php if (is_sharebox_enabled()): ?>
                                            <a class="nav-link <?= ($is_sharebox_view || $is_sharebox_folder) ? 'active' : '' ?>" href="?path=sharebox">
                                                <i class="bi bi-share-fill me-2 text-success"></i>SHARE
                                            </a>
                                        <?php else: ?>
                                            <a class="nav-link text-muted" href="admin.php" title="管理設定から有効化できます">
                                                <i class="bi bi-share me-2"></i>SHARE
                                                <span class="badge bg-secondary ms-1 small">未設定</span>
                                            </a>
                                        <?php endif; ?>
                                    </li>
                                </ul>
                            </div>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_root_view ? 'active' : '' ?>" href="?path=">
                                <i class="bi bi-house-door me-2"></i>HOME
                            </a>
                        </li>
                    </ul>
                </div>

                <div id="sidebarScrollable" class="sidebar-sticky overflow-auto flex-grow-1">
                    <ul class="nav flex-column mb-2">
                        <?php
                        function render_folder_tree($folders, $current_path, $level = 0, $max_depth = 0)
                        {
                            $html = '';
                            $indent_px = 16 * ($level + 1);
                            foreach ($folders as $folder) {
                                $has_children = !empty($folder['children']) && ($level < $max_depth);
                                $is_active = ($current_path === $folder['path']);
                                $is_active_parent = str_starts_with($current_path, $folder['path'] . '/');
                                $li_classes = 'nav-item nav-item-folder' . ($is_active_parent ? ' is-active-parent' : '');
                                $link_classes = 'nav-link d-flex align-items-center' . ($is_active ? ' is-active' : '');
                                $collapse_id = 'collapse-' . str_replace('/', '-', $folder['path']);
                                $is_collapsed_open = $is_active || $is_active_parent;

                                $html .= '<li class="' . $li_classes . '">';
                                $padding_style = ($max_depth > 0) ? 'padding-left: ' . $indent_px . 'px;' : '';
                                $folder_name_escaped = htmlspecialchars($folder['name'], ENT_QUOTES, 'UTF-8');
                                $html .= '<a class="' . $link_classes . '" href="?path=' . urlencode($folder['path']) . '" style="' . $padding_style . '" title="' . $folder_name_escaped . '">';
                                if ($max_depth > 0) {
                                    if ($has_children) {
                                        $html .= '<i class="bi me-1 toggle-icon flex-shrink-0" data-bs-toggle="collapse" data-bs-target="#' . $collapse_id . '" aria-expanded="' . ($is_collapsed_open ? 'true' : 'false') . '" style="cursor: pointer;">' . ($is_collapsed_open ? '▾' : '▸') . '</i>';
                                    } else {
                                        $html .= '<i class="me-1 flex-shrink-0" style="width: 1rem;"></i>';
                                    }
                                }
                                $html .= '<i class="bi bi-folder me-2 flex-shrink-0"></i><span class="text-truncate">' . $folder_name_escaped . '</span></a>';
                                if ($has_children) {
                                    $html .= '<div class="collapse ' . ($is_collapsed_open ? 'show' : '') . '" id="' . $collapse_id . '"><ul class="nav flex-column">' . render_folder_tree($folder['children'], $current_path, $level + 1, $max_depth) . '</ul></div>';
                                }
                                $html .= '</li>';
                            }
                            return $html;
                        }
                        echo render_folder_tree($sidebar_folders, $web_path);
                        ?>
                    </ul>
                </div>
            </nav>

            <main class="main-content px-3 px-md-4">
                <div class="main-action-bar d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div id="breadcrumbContainer" class="flex-grow-1">
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <?php foreach ($breadcrumbs as $crumb): ?>
                                    <li class="breadcrumb-item">
                                        <?php if ($crumb['name'] === 'STAR'): ?>
                                            <a href="?path=starred" class="text-dark text-decoration-none">
                                                <i class="bi bi-star-fill me-1 text-warning"></i><?= htmlspecialchars($crumb['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php elseif ($crumb['name'] === 'RECENT'): ?>
                                            <a href="?path=recent" class="text-dark text-decoration-none">
                                                <i class="bi bi-clock-history me-1 text-primary"></i><?= htmlspecialchars($crumb['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php elseif ($crumb['name'] === 'INBOX'): ?>
                                            <a href="?path=inbox" class="text-dark text-decoration-none">
                                                <i class="bi bi-inbox-fill me-1 text-info"></i><?= htmlspecialchars($crumb['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php elseif ($crumb['name'] === 'SHARE'): ?>
                                            <a href="?path=sharebox" class="text-dark text-decoration-none">
                                                <i class="bi bi-share-fill me-1 text-success"></i><?= htmlspecialchars($crumb['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </a>
                                        <?php elseif ($crumb['name'] === 'home'): ?>
                                            <a href="?path=" class="text-dark text-decoration-none">
                                                <i class="bi bi-house-door me-1"></i>home
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
                        <?php if (!$is_virtual_view): ?>
                            <div class="mt-1 d-md-none">
                                <span class="badge <?= $folder_count_badge_class ?> font-monospace fw-normal" style="font-size: 0.75rem;" title="フォルダ内アイテム数 (上限 <?= $max_folder_items ?>件)">
                                    <i class="bi bi-folder me-1"></i><?= $folder_item_count ?> / <?= $max_folder_items ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($is_recent_view): ?>
                        <div class="ms-2">
                            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#recentExclusionsModal" id="recentExclusionsBtn">
                                <i class="bi bi-eye-slash me-1"></i>除外した項目 (<span id="recentExclusionBadge"><?= $recent_exclusion_count ?></span>件)
                            </button>
                        </div>
                    <?php endif; ?>
                    <div id="tableActionsContainer" class="d-none">
                        <span class="text-muted me-2 me-md-3"><strong id="selectionCount">0</strong>個選択中</span>
                        <?php if ($is_recent_view): ?>
                            <button class="btn btn-outline-secondary btn-sm" id="batchExcludeBtn" title="選択項目を除外する" aria-label="選択項目を除外する"><i class="bi bi-eye-slash"></i><span class="d-none d-md-inline ms-1">選択項目を除外する</span></button>
                        <?php endif; ?>
                        <?php if (!$is_star_view && !$is_sharebox_view && !$is_recent_view): ?>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#moveItemsModal" title="選択項目を移動" aria-label="選択項目を移動"><i class="bi bi-folder-symlink"></i><span class="d-none d-md-inline ms-1">選択項目を移動</span></button>
                            <?php if (!$is_inbox_view): ?>
                                <button class="btn btn-outline-info btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#moveToInboxModal" title="INBOXへ移動" aria-label="INBOXへ移動"><i class="bi bi-inbox-fill"></i><span class="d-none d-md-inline ms-1">INBOXへ移動</span></button>
                            <?php endif; ?>
                            <?php if (is_sharebox_enabled()): ?>
                                <?php $sharebox_btn_text = $is_sharebox_folder ? '他の共有フォルダへ移動' : 'SHARE BOXへ移動'; ?>
                                <button class="btn btn-outline-success btn-sm ms-1" data-bs-toggle="modal" data-bs-target="#moveToShareboxModal" title="<?= $sharebox_btn_text ?>" aria-label="<?= $sharebox_btn_text ?>"><i class="bi bi-share-fill"></i><span class="d-none d-md-inline ms-1"><?= $sharebox_btn_text ?></span></button>
                            <?php endif; ?>
                            <button class="btn btn-danger btn-sm ms-1" id="batchDeleteBtn" title="選択項目を削除" aria-label="選択項目を削除"><i class="bi bi-trash"></i><span class="d-none d-md-inline ms-1">選択項目を削除</span></button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($_SESSION['created_share_token'])): 
                    $new_token = $_SESSION['created_share_token'];
                    unset($_SESSION['created_share_token']);
                    $share_url = (is_https() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/share.php?token=' . $new_token;
                ?>
                    <div class="alert alert-success alert-dismissible fade show my-3" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check-circle-fill me-2 fs-4 text-success"></i>
                            <div class="flex-grow-1">
                                <strong>共有リンクを発行しました！</strong><br>
                                <span class="small text-muted">以下のURLを共有相手にお知らせください：</span>
                                <div class="input-group input-group-sm mt-1">
                                    <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($share_url, ENT_QUOTES, 'UTF-8') ?>" id="createdShareUrlInput" readonly>
                                    <button class="btn btn-outline-success" type="button" onclick="navigator.clipboard.writeText(document.getElementById('createdShareUrlInput').value); alert('共有URLをコピーしました！');">
                                        <i class="bi bi-clipboard me-1"></i>URLをコピー
                                    </button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="file-list">
                    <div class="row gx-2 text-muted border-bottom py-2 d-none d-md-flex small file-list-header align-items-center">
                        <div class="col-auto" style="width: 30px;"></div>
                        <?php if (!$is_sharebox_view): ?>
                            <div class="col-auto">
                                <input class="form-check-input" type="checkbox" id="selectAllCheckbox">
                            </div>
                        <?php endif; ?>
                        <div class="col fw-bold d-flex align-items-center">
                            <span>ファイル名</span>
                            <?php if (!$is_virtual_view): ?>
                                <span class="badge <?= $folder_count_badge_class ?> ms-2 font-monospace fw-normal" style="font-size: 0.75rem;" title="単一フォルダ登録上限 <?= $max_folder_items ?>件">
                                    <?= $folder_item_count ?> / <?= $max_folder_items ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-light text-secondary border ms-2 font-monospace fw-normal" style="font-size: 0.75rem;">
                                    <?= $folder_item_count ?> 件
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="col-3 col-lg-2 text-end fw-bold">容量</div>
                        <div class="col-auto" style="width: 50px;"></div>
                    </div>

                    <?php if (empty($items)): ?>
                        <div class="text-center py-5 text-muted empty-state-container">
                            <i class="bi bi-folder2-open display-5 d-block mb-3 text-secondary opacity-50"></i>
                            <span class="fs-6">ファイルまたはフォルダがありません</span>
                        </div>
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
                                <?php if (!$is_sharebox_view): ?>
                                    <div class="col-auto py-2">
                                        <input class="form-check-input item-checkbox" type="checkbox" value="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>" data-web-path="<?= htmlspecialchars($item_web_path_for_action, ENT_QUOTES, 'UTF-8') ?>" data-is-dir="<?= $item['is_dir'] ? '1' : '0' ?>" <?= $item['is_dir'] ? 'disabled title="フォルダは一括操作できません"' : '' ?>>
                                    </div>
                                <?php endif; ?>
                                <div class="col text-truncate py-2">
                                    <?php 
                                        $item_name_escaped = htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <?php if ($item['is_dir']): 
                                        $dir_target_path = $is_star_view ? $item_web_path_for_action : ltrim($web_path . '/' . $item['name'], '/');
                                        if ($is_sharebox_view) {
                                            $dir_target_path = 'sharebox/' . $item['name'];
                                        }
                                    ?>
                                        <a href="?path=<?= urlencode($dir_target_path) ?>" class="d-flex align-items-center text-truncate" title="<?= $item_name_escaped ?>">
                                            <i class="bi bi-folder-fill <?= $is_sharebox_view ? 'text-success' : 'text-primary' ?> me-2 fs-5 flex-shrink-0"></i>
                                            <span class="text-truncate"><?= $item_name_escaped ?></span>
                                            <?php if ($is_star_view || $is_recent_view): ?>
                                                <span class="ms-2 badge bg-secondary-subtle text-secondary fw-normal small flex-shrink-0">in: /<?= htmlspecialchars($item_web_path_for_action, ENT_QUOTES, 'UTF-8') ?></span>
                                            <?php endif; ?>
                                            <?php if ($is_sharebox_view && !empty($item['share_info'])): ?>
                                                <span class="ms-2 badge bg-success-subtle text-success border border-success-subtle fw-normal small flex-shrink-0">
                                                    <i class="bi bi-link-45deg me-1"></i>共有中
                                                </span>
                                            <?php endif; ?>
                                        </a>
                                    <?php else: 
                                        $file_icon_info = get_file_icon_info($item['name']);
                                        $extension = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
                                        $is_pdf = ($extension === 'pdf');
                                        $is_image = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'avif']);
                                        $view_action_url = $is_pdf
                                            ? "?action=pdf_preview&path=" . urlencode($item_full_web_path)
                                             : "?action=view&path=" . urlencode($item_full_web_path);
                                        $link_classes = 'd-flex align-items-center text-truncate' . ($is_pdf ? ' preview-trigger' : '') . ($is_image ? ' image-preview-trigger' : '');
                                        $image_data_attrs = $is_image ? ' data-image-name="' . $item_name_escaped . '" data-image-path="' . htmlspecialchars($item_full_web_path, ENT_QUOTES, 'UTF-8') . '"' : '';
                                    ?>
                                        <a href="<?= $view_action_url ?>" target="_blank" class="<?= $link_classes ?>" title="<?= $item_name_escaped ?>"<?= $image_data_attrs ?>>
                                            <i class="bi <?= $file_icon_info['icon'] ?> <?= $file_icon_info['color'] ?> me-2 fs-5 flex-shrink-0"></i>
                                            <span class="text-truncate"><?= $item_name_escaped ?></span>
                                            <?php if ($is_star_view || $is_recent_view): ?>
                                                <span class="ms-2 badge bg-secondary-subtle text-secondary fw-normal small flex-shrink-0">in: /<?= htmlspecialchars($item_web_path_for_action, ENT_QUOTES, 'UTF-8') ?></span>
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

                                            <?php if ($is_sharebox_view && $item['is_dir']): ?>
                                                <!-- SHARE BOX ビューのフォルダ専用メニュー -->
                                                <?php if (empty($item['share_info'])): ?>
                                                    <li>
                                                        <button class="dropdown-item text-primary" type="button" data-bs-toggle="modal" data-bs-target="#createShareLinkModal" data-folder-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="bi bi-link-45deg me-2"></i>共有リンクを発行
                                                        </button>
                                                    </li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form action="index.php" method="post" onsubmit="return confirm('本当に「<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>」を削除しますか？\nこの操作は元に戻せません。');">
                                                            <input type="hidden" name="action" value="delete_sharebox_folder">
                                                            <input type="hidden" name="path" value="sharebox">
                                                            <input type="hidden" name="item_name" value="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-trash-fill me-2"></i>削除</button>
                                                        </form>
                                                    </li>
                                                <?php else: 
                                                    $active_share_url = (is_https() ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/share.php?token=' . $item['share_info']['token'];
                                                ?>
                                                    <li>
                                                        <button class="dropdown-item" type="button" onclick="navigator.clipboard.writeText('<?= htmlspecialchars($active_share_url, ENT_QUOTES, 'UTF-8') ?>'); alert('共有URLをコピーしました！');">
                                                            <i class="bi bi-clipboard me-2"></i>共有URLをコピー
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <form action="index.php" method="post" onsubmit="return confirm('共有リンクを無効化しますか？\n無効化すると外部からアクセスできなくなります。');">
                                                            <input type="hidden" name="action" value="revoke_share_link">
                                                            <input type="hidden" name="path" value="sharebox">
                                                            <input type="hidden" name="token" value="<?= htmlspecialchars($item['share_info']['token'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <button type="submit" class="dropdown-item text-warning"><i class="bi bi-x-circle me-2"></i>リンクを無効化</button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <!-- 通常のアイテムメニュー -->
                                                <?php if (!$is_star_view && !$is_recent_view): ?>
                                                    <li><button class="dropdown-item" type="button" data-bs-toggle="modal" data-bs-target="#renameItemModal" data-bs-item-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>" data-bs-is-dir="<?= $item['is_dir'] ? '1' : '0' ?>"><i class="bi bi-pencil-fill me-2"></i>名前の変更</button></li>
                                                    <?php if (!$item['is_dir']): ?>
                                                        <li><button class="dropdown-item single-move-btn" type="button" data-bs-toggle="modal" data-bs-target="#moveItemsModal" data-bs-item-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-folder-symlink me-2"></i>移動</button></li>
                                                        <?php if (!$is_inbox_view): ?>
                                                            <li><button class="dropdown-item text-info single-move-inbox-btn" type="button" data-bs-toggle="modal" data-bs-target="#moveToInboxModal" data-bs-item-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-inbox-fill me-2"></i>INBOXへ移動</button></li>
                                                        <?php endif; ?>
                                                        <?php if (is_sharebox_enabled() && !$is_sharebox_view): ?>
                                                            <li><button class="dropdown-item text-success single-move-sharebox-btn" type="button" data-bs-toggle="modal" data-bs-target="#moveToShareboxModal" data-bs-item-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-share-fill me-2"></i><?= $is_sharebox_folder ? '他の共有フォルダへ移動' : 'SHARE BOXへ移動' ?></button></li>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>

                                                <?php if (!$item['is_dir']): ?>
                                                    <li><a class="dropdown-item" href="?action=download&path=<?= urlencode($item_full_web_path) ?>"><i class="bi bi-download me-2"></i>ダウンロード</a></li>
                                                <?php endif; ?>

                                                <?php if ($is_recent_view): ?>
                                                    <li>
                                                        <button class="dropdown-item text-secondary recent-exclude-btn" type="button"
                                                            data-web-path="<?= htmlspecialchars($item_web_path_for_action, ENT_QUOTES, 'UTF-8') ?>"
                                                            data-item-name="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <i class="bi bi-eye-slash me-2"></i>RECENTから除外 (確認済みにする)
                                                        </button>
                                                    </li>
                                                <?php endif; ?>

                                                <?php if ($is_star_view || $is_recent_view): ?>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li><a class="dropdown-item" href="?path=<?= urlencode($item_web_path_for_action) ?>"><i class="bi bi-arrow-return-right me-2"></i>元のフォルダへ</a></li>
                                                <?php endif; ?>

                                                <?php if (!$is_star_view && !$is_recent_view): ?>
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

    <!-- ドラッグ＆ドロップ アップロード用オーバーレイ -->
    <div id="dragDropOverlay">
        <div class="drag-drop-card">
            <i class="bi bi-cloud-arrow-up-fill fs-1 text-primary mb-3 d-block"></i>
            <h4 class="fw-bold mb-1">ファイルをここにドロップ</h4>
            <p class="text-white-50 mb-0 small">自動的にアップロードが開始されます</p>
        </div>
    </div>

    <?php
    require_once __DIR__ . '/static/component_toast_container.php';
    require_once __DIR__ . '/static/component_create_folder_modal.php';
    require_once __DIR__ . '/static/component_upload_file_modal.php';
    require_once __DIR__ . '/static/component_rename_item_modal.php';
    require_once __DIR__ . '/static/component_move_item_modal.php';
    require_once __DIR__ . '/static/component_move_to_inbox_modal.php';
    if (is_sharebox_enabled()) {
        require_once __DIR__ . '/static/component_move_to_sharebox_modal.php';
    }
    require_once __DIR__ . '/static/component_create_share_link_modal.php';
    require_once __DIR__ . '/static/component_recent_exclusions_modal.php';
    require_once __DIR__ . '/static/preview_modal.php';
    require_once __DIR__ . '/static/component_image_viewer_modal.php';
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
        const RECENT_API_URL = '<?= $RECENT_API_URL ?>';
        const isStarView = <?= $json_star_view ?>;
        const isRecentView = <?= $json_recent_view ?>;
    </script>
    <script src="static/asset_index.js?v=<?= file_exists(__DIR__ . '/static/asset_index.js') ? filemtime(__DIR__ . '/static/asset_index.js') : '1' ?>"></script>
</body>

</html>