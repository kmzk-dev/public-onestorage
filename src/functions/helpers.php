<?php
// helpers.php:汎用ユーティリティ関数(例のごとく未整理)
require_once __DIR__ . '/db.php';

// ====================================================================
// 一般ユーティリティ
// ====================================================================
/**
 * ランダムな文字列（デフォルト15桁）を生成
 * 主にユニークなフォルダ名やファイル名を作成するために使用されます
 */
function generate_random_string(int $length = 15): string {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $char_length = strlen($characters);
    $random_string = '';
    for ($i = 0; $i < $length; $i++) {
        $random_string .= $characters[random_int(0, $char_length - 1)];
    }
    return $random_string;
}
/**
 * 指定されたパスへHTTPリダイレクトを実行し、スクリプトの実行を明示的に終了
 */
function redirect(string $path): void {
    header("Location: {$path}");
    exit;
}
/**
 * バイト数を読みやすい形式（B, KB, MB, GB, TB）にフォーマット（10GB以上にも対応）
 */
function format_bytes($bytes, $precision = 2): string {
    if ($bytes === null || !is_numeric($bytes) || $bytes < 0) return '-';
    $bytes = (float)$bytes;
    if ($bytes == 0) return '0 B';

    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $pow = 0;
    while ($bytes >= 1024 && $pow < count($units) - 1) {
        $bytes /= 1024;
        $pow++;
    }

    if ($pow === 0) {
        return (string)round($bytes) . ' B';
    }

    return number_format($bytes, $precision) . ' ' . $units[$pow];
}
/**
 * 現在の接続がHTTPS（セキュア接続）であるかどうかを判定
 */
function is_https(): bool {
    // サーバー変数に基づく標準的なチェック
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1)) {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    // ロードバランサやプロキシ経由の場合のチェック
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    return false;
}
/**
 * ウェブパスがINBOXビューのパスであるかを判定
 */
function is_inbox_view(string $web_path): bool {
    return $web_path === 'inbox';
}
/**
 * ウェブパスがルートディレクトリを示すかを判定する
 */
function is_root_view(string $web_path): bool {
    return empty($web_path);
}
// ====================================================================
// ファイル・ディレクトリ操作系ユーティリティ
// ====================================================================
/**
 * ディレクトリ内に .htaccess と index.html を確実に生成して直接アクセス・一覧表示を二重防御
 */
function ensure_dir_protection(string $dir): void {
    if (!is_dir($dir)) return;
    $htaccess_path = $dir . DIRECTORY_SEPARATOR . '.htaccess';
    if (!file_exists($htaccess_path)) {
        @file_put_contents($htaccess_path, "Order allow,deny\nDeny from all\nRequire all denied\n");
    }
    $index_path = $dir . DIRECTORY_SEPARATOR . 'index.html';
    if (!file_exists($index_path)) {
        @file_put_contents($index_path, "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>403 Forbidden</h1></body></html>\n");
    }
}

/**
 * 空のフォルダを削除します
 * （再帰削除は行わず、ファイルやフォルダが存在する場合は削除不可）
 */
function delete_directory($dir) { 
    if (!file_exists($dir)) return true; 
    if (!is_dir($dir)) return unlink($dir);

    // ディレクトリ内にファイルやサブフォルダが存在するか確認
    $items = @scandir($dir);
    if ($items === false) return false;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        // index.html はアクセス防止用ファイルの場合があるため、それだけなら削除可能とする
        if ($item === 'index.html') {
            @unlink($dir . DIRECTORY_SEPARATOR . $item);
            continue;
        }
        // 他にアイテムが存在する場合は削除不可
        return false;
    }
    return @rmdir($dir); 
}

/**
 * フォルダ内にファイルまたはサブフォルダが存在するか判定
 * (.や..、index.htmlなどの管理用ファイルは除外)
 */
function has_folder_items(string $dir_path): bool {
    return count_folder_items($dir_path) > 0;
}
/* 
 * ディレクトリの合計サイズを再帰的に取得する
 */
function get_directory_size(string $dir): int {
    if (!is_readable($dir)) { return 0; }

    $size = 0;
    $items = scandir($dir);

    if ($items === false) { return 0; }

    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;

        if (!is_readable($path)) continue;

        if (is_dir($path)) {
            //TODO: サブフォルダも再帰的に計算:オーバースペックの可能性があるので要考慮
            $size += get_directory_size($path);
        } else {
            $size += filesize($path);
        }
    }
    return $size;
}
/**
 * SQLiteキャッシュから指定フォルダ（サブフォルダ含む全階層）の合計ファイル容量を高速取得
 * @param string $dir_web_path 対象フォルダのWeb相対パス (例: 'documents/2026')
 * @return int 合計バイト数
 */
function get_directory_size_from_cache(string $dir_web_path): int {
    $db = get_db();
    if (!$db) return 0;

    $dir_web_path = trim(str_replace('\\', '/', $dir_web_path), '/');
    if ($dir_web_path === '') {
        $stmt = $db->query("SELECT COALESCE(SUM(size), 0) FROM files WHERE path != 'inbox'");
        return (int)$stmt->fetchColumn();
    }

    try {
        $escaped = addcslashes($dir_web_path, '%_\\');
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(size), 0) 
            FROM files 
            WHERE path = :exact_path OR path LIKE :prefix_path ESCAPE '\\'
        ");
        $stmt->execute([
            ':exact_path'  => $dir_web_path,
            ':prefix_path' => $escaped . '/%'
        ]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        error_log('get_directory_size_from_cache error: ' . $e->getMessage());
        return 0;
    }
}
/**
 * 複数フォルダの容量をSQLiteから一括集計して取得（パフォーマンス最適化）
 * @param string $parent_web_path 現在の親Webパス
 * @param array $dir_names フォルダ名の配列
 * @return array [folder_name => int size_in_bytes]
 */
function get_directory_sizes_batch_from_cache(string $parent_web_path, array $dir_names): array {
    $db = get_db();
    if (!$db || empty($dir_names)) return [];

    $parent_clean = trim(str_replace('\\', '/', $parent_web_path), '/');
    $sizes = [];

    try {
        $stmt = $db->prepare("
            SELECT COALESCE(SUM(size), 0) 
            FROM files 
            WHERE path = :exact_path OR path LIKE :prefix_path ESCAPE '\\'
        ");

        foreach ($dir_names as $name) {
            $full_dir_path = ($parent_clean === '') ? $name : $parent_clean . '/' . $name;
            $escaped = addcslashes($full_dir_path, '%_\\');
            $stmt->execute([
                ':exact_path'  => $full_dir_path,
                ':prefix_path' => $escaped . '/%'
            ]);
            $sizes[$name] = (int)$stmt->fetchColumn();
        }
    } catch (Exception $e) {
        error_log('get_directory_sizes_batch_from_cache error: ' . $e->getMessage());
    }

    return $sizes;
}
/**
 * INBOXの絶対パスを取得する
 */
function get_inbox_path(): string {
    if (!defined('DATA_ROOT') || !defined('INBOX_DIR_NAME')) {
        error_log('DATA_ROOT or INBOX_DIR_NAME is not defined.');
        return '';
    }
    $inbox_path = DATA_ROOT . DIRECTORY_SEPARATOR . INBOX_DIR_NAME;
    if (!is_dir($inbox_path)) {
        if (!mkdir($inbox_path, 0777, true)) {
            error_log('Failed to create INBOX directory: ' . $inbox_path);
        }
    }
    return $inbox_path;
}
/**
 * 24時間以上経過した古いチャンクファイルをクリーンアップする
 * @return int 削除したファイル数
 */
function cleanup_stale_chunks(): int {
    $temp_dir = DATA_ROOT . DIRECTORY_SEPARATOR . '.temp_chunks';
    if (!is_dir($temp_dir)) { return 0; }

    $deleted_count = 0;
    $one_day_ago = time() - (24 * 60 * 60);

    foreach (scandir($temp_dir) as $file) {
        if ($file === '.' || $file === '..' || $file === '.htaccess') {
            continue;
        }

        $file_path = $temp_dir . DIRECTORY_SEPARATOR . $file;
        if (filemtime($file_path) < $one_day_ago) {
            if (unlink($file_path)) {
                $deleted_count++;
            }
        }
    }
    return $deleted_count;
}
// ====================================================================
// ディレクトリ構造・キャッシュ操作系
// ====================================================================
/**
 * DATA_ROOT以下の全てのディレクトリのウェブパスを再帰的に取得
 */
function get_all_directories_recursive($dir, &$results = []) { 
    $items = scandir($dir); 
    foreach ($items as $item) { 
        if ($item == '.' || $item == '..') continue;
        if (defined('INBOX_DIR_NAME') && $item == INBOX_DIR_NAME) continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item; 
        if (is_dir($path)) { 
            if (str_starts_with($item, '.')) continue; 
            $web_path = ltrim(str_replace('\\', '/', substr($path, strlen(DATA_ROOT))), '/'); 
            $results[] = $web_path; 
            get_all_directories_recursive($path, $results); 
        } 
    } 
    return $results; 
}
/**
 * ディレクトリ構造をツリー形式で取得
 */
function get_directory_tree($base_path) {
    $get_web_path = fn($path) => ltrim(str_replace('\\', '/', substr($path, strlen(DATA_ROOT))), '/');
    $build_tree = function($current_path) use (&$build_tree, $get_web_path) {
        $dirs = [];
        $items = array_diff(scandir($current_path), ['.', '..']);
        natsort($items);
        foreach ($items as $item) {
            if ($item === 'index.html' || str_starts_with($item, '.')) continue;
            if (defined('INBOX_DIR_NAME') && $item == INBOX_DIR_NAME) continue;
            $path = $current_path . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $dirs[] = [
                    'name' => $item,
                    'path' => $get_web_path($path),
                    'children' => $build_tree($path)
                ];
            }
        }
        return $dirs;
    };
    return $build_tree(DATA_ROOT);
}
/**
 * DATA_ROOT以下の全てのファイルの情報を再帰的に取得
 */
function get_all_files_recursive(string $dir, array &$results = [], string $web_path = ''): array {
    $items = @scandir($dir);
    if ($items === false) return $results;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if ($item === 'index.html') continue;
        if (defined('INBOX_DIR_NAME') && $item === INBOX_DIR_NAME) continue;
        if (str_starts_with($item, '.')) continue;

        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $child_web_path = empty($web_path) ? $item : $web_path . '/' . $item;
            get_all_files_recursive($path, $results, $child_web_path);
        } else {
            $results[] = [
                'name' => $item,
                'path' => $web_path,
                'size' => filesize($path),
                'formatted_size' => format_bytes(filesize($path)),
                'mtime' => filemtime($path),
                'ext' => strtolower(pathinfo($item, PATHINFO_EXTENSION))
            ];
        }
    }
    return $results;
}

/**
 * ディレクトリおよびファイル構造をスキャンし、SQLiteデータベースを再構築・更新する
 */
function rebuild_dir_cache(): array {
    static $is_rebuilding = false;
    if ($is_rebuilding) {
        return ['tree' => [], 'list' => [], 'files' => []];
    }
    $is_rebuilding = true;

    $list = get_all_directories_recursive(DATA_ROOT);
    sort($list);

    $files = [];
    get_all_files_recursive(DATA_ROOT, $files);

    // INBOX内のファイルも検索対象に追加
    if (defined('INBOX_DIR_NAME')) {
        $inbox_path = DATA_ROOT . DIRECTORY_SEPARATOR . INBOX_DIR_NAME;
        if (is_dir($inbox_path)) {
            $inbox_items = @scandir($inbox_path);
            if ($inbox_items !== false) {
                foreach ($inbox_items as $item) {
                    if ($item === '.' || $item === '..' || $item === 'index.html' || str_starts_with($item, '.')) continue;
                    $path = $inbox_path . DIRECTORY_SEPARATOR . $item;
                    if (!is_dir($path)) {
                        $files[] = [
                            'name' => $item,
                            'path' => 'inbox',
                            'size' => filesize($path),
                            'formatted_size' => format_bytes(filesize($path)),
                            'mtime' => filemtime($path),
                            'ext' => strtolower(pathinfo($item, PATHINFO_EXTENSION))
                        ];
                    }
                }
            }
        }
    }

    $db = get_db();
    if ($db) {
        try {
            $db->beginTransaction();

            $db->exec("DELETE FROM directories;");
            $stmt_dir = $db->prepare("INSERT INTO directories (path, name, parent) VALUES (:path, :name, :parent)");
            foreach ($list as $dir_path) {
                $clean_path = trim(str_replace('\\', '/', $dir_path), '/');
                if ($clean_path === '') continue;
                $name = basename($clean_path);
                $parent = dirname($clean_path);
                if ($parent === '.' || $parent === '/' || $parent === '\\') {
                    $parent = '';
                }
                $stmt_dir->execute([
                    ':path' => $clean_path,
                    ':name' => $name,
                    ':parent' => $parent
                ]);
            }

            $db->exec("DELETE FROM files;");
            $stmt_file = $db->prepare("INSERT INTO files (name, path, size, formatted_size, mtime, ext) VALUES (:name, :path, :size, :formatted_size, :mtime, :ext)");
            foreach ($files as $file) {
                $clean_path = trim(str_replace('\\', '/', $file['path'] ?? ''), '/');
                if ($clean_path === '.') $clean_path = '';
                $stmt_file->execute([
                    ':name' => $file['name'],
                    ':path' => $clean_path,
                    ':size' => (int)$file['size'],
                    ':formatted_size' => $file['formatted_size'],
                    ':mtime' => (int)$file['mtime'],
                    ':ext' => strtolower($file['ext'])
                ]);
            }

            $db->commit();
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('rebuild_dir_cache error: ' . $e->getMessage());
        }
    }

    $is_rebuilding = false;
    return load_dir_cache(false);
}

/**
 * ディレクトリおよびファイル構造をSQLiteデータベースから読み込む
 */
function load_dir_cache(bool $allow_rebuild = true): array {
    $db = get_db();
    if (!$db) {
        return ['tree' => [], 'list' => [], 'files' => []];
    }

    try {
        $stmt_dirs = $db->query("SELECT path, name, parent FROM directories ORDER BY path ASC");
        $db_dirs = $stmt_dirs->fetchAll();

        if (empty($db_dirs) && $allow_rebuild) {
            $file_count = (int)$db->query("SELECT COUNT(*) FROM files")->fetchColumn();
            if ($file_count === 0) {
                // DBが空の場合は1回のみ再構築を試みる（再帰呼び出しを防止）
                return rebuild_dir_cache();
            }
        }

        $list = array_column($db_dirs, 'path');

        // 親階層 parent に基づき再帰ツリーを構築（階層数制限なし）
        $dirs_by_parent = [];
        foreach ($db_dirs as $d) {
            $parent_key = (string)($d['parent'] ?? '');
            $dirs_by_parent[$parent_key][] = $d;
        }
        foreach ($dirs_by_parent as &$group) {
            usort($group, fn($a, $b) => strnatcasecmp($a['name'], $b['name']));
        }
        unset($group);

        $build_tree = function(string $parent) use (&$build_tree, &$dirs_by_parent): array {
            $tree = [];
            if (!isset($dirs_by_parent[$parent])) {
                return $tree;
            }
            foreach ($dirs_by_parent[$parent] as $dir) {
                $tree[] = [
                    'name' => $dir['name'],
                    'path' => $dir['path'],
                    'children' => $build_tree($dir['path'])
                ];
            }
            return $tree;
        };

        $tree = $build_tree('');

        return [
            'tree' => $tree,
            'list' => $list,
            'files' => []
        ];
    } catch (Exception $e) {
        error_log('load_dir_cache error: ' . $e->getMessage());
        return ['tree' => [], 'list' => [], 'files' => []];
    }
}


/**
 * SQLiteデータベースからファイル名を部分一致高速検索（インデックス活用）
 */
function search_files_from_cache(string $query): array {
    $query = trim($query);
    if ($query === '') {
        return [];
    }

    $db = get_db();
    if (!$db) {
        return [];
    }

    try {
        $escaped_query = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $query);
        $like_pattern = '%' . $escaped_query . '%';

        $stmt = $db->prepare("
            SELECT name, path, size, formatted_size, mtime, ext
            FROM files
            WHERE name LIKE :pattern ESCAPE '\\'
            ORDER BY mtime DESC
        ");
        $stmt->execute([':pattern' => $like_pattern]);
        $results = $stmt->fetchAll();

        // ファイルが1件も存在しない場合は再構築後に再検索
        if (empty($results)) {
            $count = (int)$db->query("SELECT COUNT(*) FROM files")->fetchColumn();
            if ($count === 0) {
                rebuild_dir_cache();
                $stmt->execute([':pattern' => $like_pattern]);
                $results = $stmt->fetchAll();
            }
        }

        return $results;
    } catch (Exception $e) {
        error_log('search_files_from_cache error: ' . $e->getMessage());
        return [];
    }
}
// ====================================================================
// スター（お気に入り）機能系
// ====================================================================

/**
 * SQLiteデータベースからスター登録されたアイテムのリストを取得する
 */
function load_star_config(): array {
    $db = get_db();
    if (!$db) {
        return [];
    }

    try {
        $stmt = $db->query("SELECT hash, path, name, is_dir, size FROM stars ORDER BY path ASC, name ASC");
        $stars = $stmt->fetchAll();
        foreach ($stars as &$star) {
            $star['is_dir'] = (bool)$star['is_dir'];
            $star['size'] = (int)$star['size'];
        }
        unset($star);
        return $stars;
    } catch (Exception $e) {
        error_log('load_star_config error: ' . $e->getMessage());
        return [];
    }
}

/**
 * スター登録されたアイテムリストをSQLiteデータベースに一括保存（後方互換性用）
 */
function save_star_config(array $data): bool {
    $db = get_db();
    if (!$db) return false;

    try {
        $db->beginTransaction();
        $db->exec("DELETE FROM stars");
        $stmt = $db->prepare("
            INSERT INTO stars (hash, path, name, is_dir, size)
            VALUES (:hash, :path, :name, :is_dir, :size)
        ");
        foreach ($data as $item) {
            $stmt->execute([
                ':hash' => $item['hash'] ?? get_item_hash($item['path'] ?? '', $item['name'] ?? ''),
                ':path' => $item['path'] ?? '',
                ':name' => $item['name'] ?? '',
                ':is_dir' => !empty($item['is_dir']) ? 1 : 0,
                ':size' => (int)($item['size'] ?? 0)
            ]);
        }
        $db->commit();
        return true;
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('save_star_config error: ' . $e->getMessage());
        return false;
    }
}

/**
 * スターアイテムのフルパス（web_path + item_name）の一意のハッシュキーを取得
 */
function get_item_hash(string $web_path, string $item_name): string {
    $full_path = ltrim($web_path . '/' . $item_name, '/');
    return md5($full_path);
}

/**
 * ファイルまたはディレクトリのスター（お気に入り）の状態を登録/解除（トグル）します。
 * SQLiteの単一レコード追加/削除で高速処理
 */
function toggle_star_item(string $web_path, string $item_name, bool $is_dir): array {
    $db = get_db();
    if (!$db) {
        return ['success' => false, 'message' => 'データベースに接続できません。'];
    }

    $item_hash = get_item_hash($web_path, $item_name);

    $is_inbox_item = (defined('INBOX_DIR_NAME') && $web_path === 'inbox');
    if ($is_inbox_item) {
        $full_path = realpath(DATA_ROOT . DIRECTORY_SEPARATOR . INBOX_DIR_NAME . DIRECTORY_SEPARATOR . $item_name);
    } else {
        $full_path = realpath(DATA_ROOT . '/' . ltrim($web_path . '/' . $item_name, '/'));
    }
    if ($full_path === false || strpos($full_path, DATA_ROOT) !== 0 || str_starts_with($item_name, '.')) {
        return ['success' => false, 'message' => '無効なアイテムです。'];
    }

    try {
        $stmt_check = $db->prepare("SELECT COUNT(*) FROM stars WHERE hash = :hash");
        $stmt_check->execute([':hash' => $item_hash]);
        $exists = ((int)$stmt_check->fetchColumn() > 0);

        if ($exists) {
            $stmt_del = $db->prepare("DELETE FROM stars WHERE hash = :hash");
            $stmt_del->execute([':hash' => $item_hash]);
            return [
                'success' => true,
                'action' => 'removed',
                'message' => htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8') . ' のスターを解除しました。'
            ];
        } else {
            $size = $is_dir ? get_directory_size($full_path) : filesize($full_path);
            $stmt_ins = $db->prepare("
                INSERT INTO stars (hash, path, name, is_dir, size)
                VALUES (:hash, :path, :name, :is_dir, :size)
            ");
            $stmt_ins->execute([
                ':hash' => $item_hash,
                ':path' => $web_path,
                ':name' => $item_name,
                ':is_dir' => $is_dir ? 1 : 0,
                ':size' => $size
            ]);
            return [
                'success' => true,
                'action' => 'added',
                'message' => htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8') . ' をスターに登録しました。'
            ];
        }
    } catch (Exception $e) {
        error_log('toggle_star_item error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'スター設定の保存に失敗しました。'];
    }
}

/**
 * 移動、リネーム時にスターアイテムの情報を更新します（単一レコードUPDATE）
 */
function update_star_item(string $old_web_path, string $old_item_name, string $new_web_path, string $new_item_name): bool {
    $db = get_db();
    if (!$db) return false;

    $old_hash = get_item_hash($old_web_path, $old_item_name);
    $new_hash = get_item_hash($new_web_path, $new_item_name);

    $is_inbox_item = (defined('INBOX_DIR_NAME') && $new_web_path === 'inbox');
    if ($is_inbox_item) {
        $full_path = realpath(DATA_ROOT . DIRECTORY_SEPARATOR . INBOX_DIR_NAME . DIRECTORY_SEPARATOR . $new_item_name);
    } else {
        $item_path_from_root = ltrim($new_web_path . '/' . $new_item_name, '/');
        $full_path = realpath(DATA_ROOT . '/' . $item_path_from_root);
    }

    $size = 0;
    if ($full_path !== false) {
        $size = is_dir($full_path) ? get_directory_size($full_path) : filesize($full_path);
    }

    try {
        $stmt = $db->prepare("
            UPDATE stars
            SET path = :new_path, name = :new_name, hash = :new_hash, size = :size
            WHERE hash = :old_hash
        ");
        return $stmt->execute([
            ':new_path' => $new_web_path,
            ':new_name' => $new_item_name,
            ':new_hash' => $new_hash,
            ':size' => $size,
            ':old_hash' => $old_hash
        ]);
    } catch (Exception $e) {
        error_log('update_star_item error: ' . $e->getMessage());
        return false;
    }
}

/**
 * 削除時、スターアイテムの登録を解除（単一レコードDELETE）
 */
function remove_star_item(string $web_path, string $item_name): bool {
    $db = get_db();
    if (!$db) return false;

    $item_hash = get_item_hash($web_path, $item_name);
    try {
        $stmt = $db->prepare("DELETE FROM stars WHERE hash = :hash");
        return $stmt->execute([':hash' => $item_hash]);
    } catch (Exception $e) {
        error_log('remove_star_item error: ' . $e->getMessage());
        return false;
    }
}

/**
 * 削除されたフォルダ（とその配下）に関連するスターアイテムを削除
 */
function clean_star_items_for_deleted_folder(string $deleted_folder_path): bool {
    $db = get_db();
    if (!$db) return true;

    $normalized = trim($deleted_folder_path, '/');
    $search_prefix = $normalized . '/%';

    try {
        $stmt = $db->prepare("
            DELETE FROM stars
            WHERE (is_dir = 1 AND (path || '/' || name) = :folder_path)
               OR (is_dir = 1 AND name = :folder_path AND path = '')
               OR path = :folder_path
               OR path LIKE :prefix
        ");
        return $stmt->execute([
            ':folder_path' => $normalized,
            ':prefix' => $search_prefix
        ]);
    } catch (Exception $e) {
        error_log('clean_star_items_for_deleted_folder error: ' . $e->getMessage());
        return false;
    }
}
// ====================================================================
// 暗号化・復号ユーティリティ（最終安定版 / ストリーミング対応）
// 目的：低スペックサーバーでの安定稼働、AI検閲の回避
// 設計：OpenSSL の制約に完全準拠し、Grok 指摘＋CBC正規処理対応
// ====================================================================

if (!defined('AES_BLOCK_SIZE')) define('AES_BLOCK_SIZE', 16); // AES固定ブロック長

/**
 * 2バイトの暗号キーを生成します。
 * @return string|false バイナリの暗号キー（32バイト）または失敗時にfalse
 */
function get_encryption_key(): string|false {
    if (!file_exists(AUTH_CONFIG_PATH)) {
        return false;
    }
    $auth_config = require AUTH_CONFIG_PATH;
    if (!isset($auth_config['user'])) {
        return false;
    }
    // sha256バイナリ出力で常に32バイトの鍵を生成
    return hash('sha256', $auth_config['user'], true);
}

/**
 * ファイルをストリーミングで暗号化。
 */
function encrypt_file_stream(string $source_path, string $dest_path, string $key): bool {
    $cipher = 'aes-256-cbc';
    $iv_length = openssl_cipher_iv_length($cipher);
    if ($iv_length === false) return false;

    try {
        $iv = random_bytes($iv_length);
    } catch (Exception $e) {
        return false;
    }

    $in = @fopen($source_path, 'rb');
    if ($in === false) return false;
    $out = @fopen($dest_path, 'wb');
    if ($out === false) {
        fclose($in);
        return false;
    }

    // 1. ファイルの先頭にIVを書き込む
    fwrite($out, $iv);

    // 2. ストリーミング暗号化
    $chunk_size = 8192;
    $pt_buf = '';

    while (!feof($in)) {
        $read = fread($in, $chunk_size);
        if ($read === false) {
            fclose($in); fclose($out);
            return false;
        }
        $pt_buf .= $read;

        // ブロック単位で暗号化
        $full_len = strlen($pt_buf) - (strlen($pt_buf) % AES_BLOCK_SIZE);
        if ($full_len > 0) {
            $to_enc = substr($pt_buf, 0, $full_len);
            $pt_buf = substr($pt_buf, $full_len);

            $ct = openssl_encrypt($to_enc, $cipher, $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);
            if ($ct === false) {
                fclose($in); fclose($out);
                return false;
            }
            fwrite($out, $ct);

            // CBCモード連鎖：IV更新
            $iv = substr($ct, -$iv_length);
        }
    }

    // 3. 最後のブロックにPKCS#7パディングを適用
    $last_len = strlen($pt_buf);
    $pad_len = AES_BLOCK_SIZE - ($last_len % AES_BLOCK_SIZE);
    if ($pad_len === 0) $pad_len = AES_BLOCK_SIZE;
    $padding = str_repeat(chr($pad_len), $pad_len);
    $final_plain = $pt_buf . $padding;

    $final_ct = openssl_encrypt($final_plain, $cipher, $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);
    if ($final_ct === false) {
        fclose($in); fclose($out);
        return false;
    }
    fwrite($out, $final_ct);

    fclose($in);
    fclose($out);
    return true;
}

/**
 * 暗号化ファイルをストリーミングで復号します。
 * PKCS#7パディングを安全に除去します。
 */
function decrypt_file_stream(string $source_path, string $key): bool {
    $cipher = 'aes-256-cbc';
    $iv_length = openssl_cipher_iv_length($cipher);
    if ($iv_length === false) return false;

    $in = @fopen($source_path, 'rb');
    if ($in === false) return false;

    // 1. 先頭のIVを取得
    $iv = fread($in, $iv_length);
    if (strlen($iv) !== $iv_length) {
        fclose($in);
        return false;
    }

    // 2. ストリーミング復号ループ
    $chunk_size = 8192;
    $ct_buf = '';

    while (!feof($in)) {
        $read = fread($in, $chunk_size);
        if ($read === false) {
            fclose($in);
            return false;
        }
        if ($read === '') break;
        $ct_buf .= $read;

        // 常に最後の1ブロックは残す
        $available = strlen($ct_buf);
        if ($available > AES_BLOCK_SIZE) {
            $process_len = $available - ($available % AES_BLOCK_SIZE);
            if ($process_len >= AES_BLOCK_SIZE) {
                $process_len -= AES_BLOCK_SIZE;
            } else {
                $process_len = 0;
            }

            if ($process_len > 0) {
                $to_dec = substr($ct_buf, 0, $process_len);
                $ct_buf = substr($ct_buf, $process_len);

                $pt = openssl_decrypt($to_dec, $cipher, $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);
                if ($pt === false) {
                    fclose($in);
                    return false;
                }
                $iv = substr($to_dec, -$iv_length);
                echo $pt;
            }
        }
    }

    // 3. 残りは最終ブロック群 → 復号＋パディング除去
    $final_ct = $ct_buf;
    if ($final_ct === '' || (strlen($final_ct) % AES_BLOCK_SIZE) !== 0) {
        // データ不整合（破損）の可能性あり
        echo $final_ct;
        fclose($in);
        return true;
    }

    $final_pt = openssl_decrypt($final_ct, $cipher, $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);
    if ($final_pt === false) {
        fclose($in);
        return false;
    }

    // PKCS#7パディングを除去
    $pad = ord(substr($final_pt, -1));
    if ($pad > 0 && $pad <= AES_BLOCK_SIZE) {
        $padstr = substr($final_pt, -$pad);
        if (strlen($padstr) === $pad && strspn($padstr, chr($pad)) === $pad) {
            $final_pt = substr($final_pt, 0, -$pad);
        }
    }

    echo $final_pt;

    fclose($in);
    return true;
}

/**
 * 暗号化ファイルをストリーミングで復号し、指定した一時ファイルに保存します。
 */
function decrypt_file_to_file(string $source_path, string $dest_path, string $key): bool {
    $cipher = 'aes-256-cbc';
    $iv_length = openssl_cipher_iv_length($cipher);
    if ($iv_length === false) return false;

    $in = @fopen($source_path, 'rb');
    if ($in === false) return false;
    $out = @fopen($dest_path, 'wb');
    if ($out === false) {
        fclose($in);
        return false;
    }

    // 1. 先頭のIVを取得
    $iv = fread($in, $iv_length);
    if (strlen($iv) !== $iv_length) {
        fclose($in);
        fclose($out);
        return false;
    }

    // 2. ストリーミング復号ループ
    $chunk_size = 8192;
    $ct_buf = '';

    while (!feof($in)) {
        $read = fread($in, $chunk_size);
        if ($read === false) {
            fclose($in);
            fclose($out);
            return false;
        }
        if ($read === '') break;
        $ct_buf .= $read;

        // 常に最後の1ブロックは残す
        $available = strlen($ct_buf);
        if ($available > AES_BLOCK_SIZE) {
            $process_len = $available - ($available % AES_BLOCK_SIZE);
            if ($process_len >= AES_BLOCK_SIZE) {
                $process_len -= AES_BLOCK_SIZE;
            } else {
                $process_len = 0;
            }

            if ($process_len > 0) {
                $to_dec = substr($ct_buf, 0, $process_len);
                $ct_buf = substr($ct_buf, $process_len);

                $pt = openssl_decrypt($to_dec, $cipher, $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);
                if ($pt === false) {
                    fclose($in);
                    fclose($out);
                    return false;
                }
                $iv = substr($to_dec, -$iv_length);
                fwrite($out, $pt);
            }
        }
    }

    // 3. 残りは最終ブロック群 → 復号＋パディング除去
    $final_ct = $ct_buf;
    if ($final_ct === '' || (strlen($final_ct) % AES_BLOCK_SIZE) !== 0) {
        fwrite($out, $final_ct);
        fclose($in);
        fclose($out);
        return true;
    }

    $final_pt = openssl_decrypt($final_ct, $cipher, $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);
    if ($final_pt === false) {
        fclose($in);
        fclose($out);
        return false;
    }

    // PKCS#7パディングを除去
    $pad = ord(substr($final_pt, -1));
    if ($pad > 0 && $pad <= AES_BLOCK_SIZE) {
        $padstr = substr($final_pt, -$pad);
        if (strlen($padstr) === $pad && strspn($padstr, chr($pad)) === $pad) {
            $final_pt = substr($final_pt, 0, -$pad);
        }
    }

    fwrite($out, $final_pt);

    fclose($in);
    fclose($out);
    return true;
}

/**
 * ファイルの拡張子に応じたアイコンクラスおよびカラークラスを取得
 */
function get_file_icon_info(string $filename): array {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf' => ['icon' => 'bi-file-earmark-pdf-fill', 'color' => 'text-danger'],
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico' => ['icon' => 'bi-file-earmark-image-fill', 'color' => 'text-success'],
        'zip', 'tar', 'gz', 'bz2', '7z', 'rar' => ['icon' => 'bi-file-earmark-zip-fill', 'color' => 'text-warning'],
        'mp3', 'wav', 'ogg', 'm4a', 'flac', 'aac' => ['icon' => 'bi-file-earmark-music-fill', 'color' => 'text-info'],
        'mp4', 'webm', 'mov', 'avi', 'mkv' => ['icon' => 'bi-file-earmark-play-fill', 'color' => 'text-primary'],
        'php', 'js', 'ts', 'html', 'css', 'json', 'py', 'sh', 'sql', 'xml', 'yml', 'yaml' => ['icon' => 'bi-file-earmark-code-fill', 'color' => 'text-dark'],
        'doc', 'docx' => ['icon' => 'bi-file-earmark-word-fill', 'color' => 'text-primary'],
        'xls', 'xlsx', 'csv' => ['icon' => 'bi-file-earmark-excel-fill', 'color' => 'text-success'],
        'ppt', 'pptx' => ['icon' => 'bi-file-earmark-ppt-fill', 'color' => 'text-danger'],
        'txt', 'md', 'log' => ['icon' => 'bi-file-earmark-text-fill', 'color' => 'text-secondary'],
        default => ['icon' => 'bi-file-earmark-fill', 'color' => 'text-muted']
    };
}

// ====================================================================
// SHARE BOX ユーティリティ
// ====================================================================

/**
 * SHARE BOX 環境の自動初期化（未作成・既存環境からの移行時に完全自動セットアップ）
 */
function init_sharebox_environment(): bool {
    if (!defined('MAIN_CONFIG_PATH') || !file_exists(MAIN_CONFIG_PATH)) {
        return false;
    }
    $main_config = require MAIN_CONFIG_PATH;
    $auth_config = (defined('AUTH_CONFIG_PATH') && file_exists(AUTH_CONFIG_PATH)) ? (require AUTH_CONFIG_PATH) : [];

    // 既に share_config.php がある場合はディレクトリの存在だけ確認・修復
    if (defined('SHARE_CONFIG_PATH') && file_exists(SHARE_CONFIG_PATH)) {
        $share_config = require SHARE_CONFIG_PATH;
        if (!empty($share_config['share_root'])) {
            if (!is_dir($share_config['share_root'])) {
                @mkdir($share_config['share_root'], 0777, true);
            }
            ensure_dir_protection($share_config['share_root']);
            return true;
        }
    }

    $share_random_part = generate_random_string(15);
    $share_dir_name    = 'share' . $share_random_part;
    $share_dir_path    = dirname(__DIR__) . '/' . $share_dir_name;

    if (!is_dir($share_dir_path) && !@mkdir($share_dir_path, 0777, true)) {
        error_log('Failed to create SHARE_ROOT directory: ' . $share_dir_path);
        return false;
    }
    ensure_dir_protection($share_dir_path);

    $share_config_content = "<?php\n\nreturn [\n"
        . "    'share_root' => '" . addslashes($share_dir_path) . "',\n"
        . "    'encryption_enabled' => true,\n"
        . "    'encryption_key_seed' => '" . addslashes($auth_config['user'] ?? '') . "',\n"
        . "];\n";

    if (@file_put_contents(SHARE_CONFIG_PATH, $share_config_content) === false) {
        error_log('Failed to create share_config.php');
        return false;
    }

    return true;
}

function is_sharebox_enabled(): bool {
    return defined('SHARE_ROOT') && is_dir(SHARE_ROOT);
}

function is_sharebox_view(string $web_path): bool {
    return $web_path === 'sharebox';
}

/** SHARE BOX 直下のフォルダ一覧（管理者ビュー用、共有状態を付加） */
function get_sharebox_folders(): array {
    if (!is_sharebox_enabled()) return [];
    $folders = [];
    $items = array_diff(scandir(SHARE_ROOT), ['.', '..']);
    natsort($items);
    foreach ($items as $item) {
        if ($item === 'index.html' || str_starts_with($item, '.')) continue;
        $folder_path = SHARE_ROOT . DIRECTORY_SEPARATOR . $item;
        if (!is_dir($folder_path)) continue;
        $folder_size = get_directory_size($folder_path);
        $folders[] = [
            'name'           => $item,
            'path'           => 'sharebox/' . $item,
            'is_dir'         => true,
            'size'           => $folder_size,
            'formatted_size' => format_bytes($folder_size),
            'share_info'     => get_active_share_for_folder($item),
        ];
    }
    return $folders;
}

/** SHARE BOX 内の特定フォルダのファイル一覧（管理者ビュー用） */
function get_sharebox_folder_contents(string $folder_name): array {
    if (!is_sharebox_enabled()) return [];
    $base = realpath(SHARE_ROOT . '/' . $folder_name);
    if (!$base || !str_starts_with($base, SHARE_ROOT) || !is_dir($base)) return [];

    $files = [];
    $items = array_diff(scandir($base), ['.', '..']);
    natsort($items);
    foreach ($items as $item) {
        if ($item === 'index.html' || str_starts_with($item, '.')) continue;
        $p       = $base . DIRECTORY_SEPARATOR . $item;
        $is_dir  = is_dir($p);
        $size    = $is_dir ? null : filesize($p);
        $files[] = [
            'name'           => $item,
            'path'           => 'sharebox/' . $folder_name,
            'is_dir'         => $is_dir,
            'size'           => $size,
            'formatted_size' => format_bytes($size),
        ];
    }
    return $files;
}

/** 指定フォルダのアクティブな共有情報を返す（なければ null） */
function get_active_share_for_folder(string $folder_name): ?array {
    $db = get_share_db();
    if (!$db) return null;
    $stmt = $db->prepare("SELECT * FROM shares WHERE folder_name = :fn LIMIT 1");
    $stmt->execute([':fn' => $folder_name]);
    $result = $stmt->fetch();
    return $result ?: null;
}

/** 共有リンクを発行（同一フォルダの既存リンクは上書き） */
function create_share_link(string $folder_name, ?string $password = null): array {
    $db = get_share_db();
    if (!$db) return ['success' => false, 'message' => 'DB接続に失敗しました。'];
    $base = realpath(SHARE_ROOT . '/' . $folder_name);
    if (!$base || !str_starts_with($base, SHARE_ROOT) || !is_dir($base)) {
        return ['success' => false, 'message' => '指定されたフォルダが存在しません。'];
    }
    // 既存リンクを上書き（1フォルダ = 1リンクポリシー）
    $db->prepare("DELETE FROM shares WHERE folder_name = :fn")->execute([':fn' => $folder_name]);
    $token = bin2hex(random_bytes(32));
    $stmt  = $db->prepare("
        INSERT INTO shares (token, folder_name, password_hash, expires_at, max_downloads, download_count, created_at)
        VALUES (:token, :folder_name, :password_hash, 0, 0, 0, :created_at)
    ");
    $stmt->execute([
        ':token'         => $token,
        ':folder_name'   => $folder_name,
        ':password_hash' => ($password !== null && $password !== '') ? password_hash($password, PASSWORD_BCRYPT) : null,
        ':created_at'    => time(),
    ]);
    return ['success' => true, 'token' => $token];
}

/** トークンの検証 */
function get_valid_share(string $token): ?array {
    $db = get_share_db();
    if (!$db) return null;
    $stmt = $db->prepare("SELECT * FROM shares WHERE token = :token");
    $stmt->execute([':token' => $token]);
    $share = $stmt->fetch();
    return $share ?: null;
}

/** フォルダ削除時に関連する shares レコードも即時削除（Q1確定） */
function delete_share_record_for_folder(string $folder_name): void {
    $db = get_share_db();
    if ($db) {
        $db->prepare("DELETE FROM shares WHERE folder_name = :fn")->execute([':fn' => $folder_name]);
    }
}

// ====================================================================
// RECENT（直近アップロード項目）除外管理系
// ====================================================================

/**
 * RECENTから特定のファイルを除外（非表示化）する
 */
function exclude_recent_item(string $web_path, string $item_name): array {
    $db = get_db();
    if (!$db) {
        return ['success' => false, 'message' => 'データベースに接続できません。'];
    }

    $clean_path = trim(str_replace('\\', '/', $web_path), '/');
    if ($clean_path === '.') $clean_path = '';

    $hash = get_item_hash($clean_path, $item_name);

    try {
        $stmt = $db->prepare("
            INSERT OR IGNORE INTO recent_exclusions (hash, path, name, created_at)
            VALUES (:hash, :path, :name, :created_at)
        ");
        $stmt->execute([
            ':hash' => $hash,
            ':path' => $clean_path,
            ':name' => $item_name,
            ':created_at' => time()
        ]);
        return ['success' => true, 'message' => 'RECENTから除外しました。'];
    } catch (Exception $e) {
        error_log('exclude_recent_item error: ' . $e->getMessage());
        return ['success' => false, 'message' => '除外処理に失敗しました。'];
    }
}

/**
 * 複数のアイテムを一度にRECENTから除外する
 */
function batch_exclude_recent_items(array $items): array {
    $db = get_db();
    if (!$db) {
        return ['success' => false, 'message' => 'データベースに接続できません。'];
    }

    if (empty($items)) {
        return ['success' => false, 'message' => '除外対象のアイテムが選択されていません。'];
    }

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("
            INSERT OR IGNORE INTO recent_exclusions (hash, path, name, created_at)
            VALUES (:hash, :path, :name, :created_at)
        ");
        $count = 0;
        $now = time();
        foreach ($items as $item) {
            $web_path = $item['web_path'] ?? '';
            $item_name = $item['item_name'] ?? '';
            if (empty($item_name)) continue;

            $clean_path = trim(str_replace('\\', '/', $web_path), '/');
            if ($clean_path === '.') $clean_path = '';

            $hash = get_item_hash($clean_path, $item_name);
            $stmt->execute([
                ':hash' => $hash,
                ':path' => $clean_path,
                ':name' => $item_name,
                ':created_at' => $now
            ]);
            $count++;
        }
        $db->commit();
        return ['success' => true, 'message' => "{$count}件のアイテムを除外しました。", 'count' => $count];
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('batch_exclude_recent_items error: ' . $e->getMessage());
        return ['success' => false, 'message' => '一括除外処理に失敗しました。'];
    }
}

/**
 * RECENTから除外されたファイルを復元（再表示）する
 */
function restore_recent_item(string $web_path, string $item_name): array {
    $db = get_db();
    if (!$db) {
        return ['success' => false, 'message' => 'データベースに接続できません。'];
    }

    $clean_path = trim(str_replace('\\', '/', $web_path), '/');
    if ($clean_path === '.') $clean_path = '';

    $hash = get_item_hash($clean_path, $item_name);

    try {
        $stmt = $db->prepare("DELETE FROM recent_exclusions WHERE hash = :hash");
        $stmt->execute([':hash' => $hash]);
        return ['success' => true, 'message' => '除外を解除しました。'];
    } catch (Exception $e) {
        error_log('restore_recent_item error: ' . $e->getMessage());
        return ['success' => false, 'message' => '除外解除処理に失敗しました。'];
    }
}

/**
 * RECENTの除外設定をすべて解除（全復元）する
 */
function clear_all_recent_exclusions(): array {
    $db = get_db();
    if (!$db) {
        return ['success' => false, 'message' => 'データベースに接続できません。'];
    }

    try {
        $db->exec("DELETE FROM recent_exclusions");
        return ['success' => true, 'message' => 'すべての除外を解除しました。'];
    } catch (Exception $e) {
        error_log('clear_all_recent_exclusions error: ' . $e->getMessage());
        return ['success' => false, 'message' => '全解除処理に失敗しました。'];
    }
}

/**
 * RECENTで除外されたアイテムの一覧を取得する
 */
function get_recent_exclusions(): array {
    $db = get_db();
    if (!$db) return [];

    try {
        $stmt = $db->query("SELECT id, hash, path, name, created_at FROM recent_exclusions ORDER BY created_at DESC");
        return $stmt->fetchAll() ?: [];
    } catch (Exception $e) {
        error_log('get_recent_exclusions error: ' . $e->getMessage());
        return [];
    }
}

/**
 * RECENTで除外されているアイテム数を取得する
 */
function get_recent_exclusion_count(): int {
    $db = get_db();
    if (!$db) return 0;

    try {
        return (int)$db->query("SELECT COUNT(*) FROM recent_exclusions")->fetchColumn();
    } catch (Exception $e) {
        error_log('get_recent_exclusion_count error: ' . $e->getMessage());
        return 0;
    }
}

// ==========================================
// 単一フォルダ件数制限 & ストレージ割り当て容量管理
// ==========================================

if (!defined('MAX_FOLDER_ITEMS')) {
    define('MAX_FOLDER_ITEMS', 200);
}

/**
 * 単一フォルダ内のアイテム数（隠しファイルやindex.htmlを除くフォルダ・ファイル数）をカウントする
 */
function count_folder_items(string $dir_path): int {
    if (!is_dir($dir_path) || !is_readable($dir_path)) {
        return 0;
    }
    $items = @scandir($dir_path);
    if ($items === false) {
        return 0;
    }
    $count = 0;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..' || $item === 'index.html' || str_starts_with($item, '.')) {
            continue;
        }
        $count++;
    }
    return $count;
}

/**
 * メイン設定ファイル (config.php) を読み込む
 */
function get_main_config(): array {
    if (file_exists(MAIN_CONFIG_PATH)) {
        $config = require MAIN_CONFIG_PATH;
        if (is_array($config)) {
            return $config;
        }
    }
    return [];
}

/**
 * メイン設定ファイル (config.php) を安全に更新保存する
 */
function update_main_config(array $new_values): bool {
    $current = get_main_config();
    $merged = array_merge($current, $new_values);

    $content = "<?php\n\nreturn [\n";
    foreach ($merged as $key => $val) {
        if (is_bool($val)) {
            $val_str = $val ? 'true' : 'false';
        } elseif (is_int($val) || is_float($val)) {
            $val_str = $val;
        } elseif (is_string($val)) {
            $val_str = "'" . addslashes($val) . "'";
        } else {
            $val_str = var_export($val, true);
        }
        $content .= "    '{$key}' => {$val_str},\n";
    }
    $content .= "];\n";

    return file_put_contents(MAIN_CONFIG_PATH, $content) !== false;
}

/**
 * ストレージ割り当て容量 (GB) を取得する (0は無制限)
 */
function get_storage_limit_gb(): float {
    $config = get_main_config();
    return isset($config['storage_limit_gb']) ? (float)$config['storage_limit_gb'] : 0.0;
}

/**
 * ストレージ割り当て容量 (GB) を更新保存する (0は無制限)
 */
function update_storage_limit_gb(float $limit_gb): bool {
    $limit_gb = max(0.0, round($limit_gb, 2));
    return update_main_config(['storage_limit_gb' => $limit_gb]);
}

/**
 * ストレージ全体の合計使用容量（バイト数）を取得する
 */
function get_total_storage_usage_bytes(): int {
    $db = get_db();
    $total_bytes = 0;
    if ($db) {
        try {
            $stmt = $db->query("SELECT COALESCE(SUM(size), 0) FROM files");
            $total_bytes = (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            error_log('get_total_storage_usage_bytes error: ' . $e->getMessage());
        }
    }

    // SHARE BOX の容量も加算
    if (defined('SHARE_ROOT') && is_dir(SHARE_ROOT) && function_exists('get_share_db')) {
        try {
            $share_db = get_share_db();
            if ($share_db) {
                $stmt_s = $share_db->query("SELECT COALESCE(SUM(size), 0) FROM files");
                $total_bytes += (int)$stmt_s->fetchColumn();
            }
        } catch (Exception $e) {
            error_log('get_total_storage_usage_bytes share error: ' . $e->getMessage());
        }
    }

    return $total_bytes;
}

/**
 * ストレージのクォータ・使用状況情報を一括取得する
 */
function get_storage_quota_info(): array {
    $limit_gb = get_storage_limit_gb();
    $is_unlimited = ($limit_gb <= 0.0);
    $limit_bytes = $is_unlimited ? 0 : (int)round($limit_gb * 1024 * 1024 * 1024);
    $used_bytes = get_total_storage_usage_bytes();

    $remaining_bytes = $is_unlimited ? 0 : max(0, $limit_bytes - $used_bytes);
    $usage_percent = ($is_unlimited || $limit_bytes === 0) ? 0.0 : min(100.0, round(($used_bytes / $limit_bytes) * 100, 1));

    return [
        'is_unlimited'        => $is_unlimited,
        'limit_gb'            => $limit_gb,
        'limit_bytes'         => $limit_bytes,
        'used_bytes'          => $used_bytes,
        'remaining_bytes'     => $remaining_bytes,
        'usage_percent'       => $usage_percent,
        'formatted_limit'     => $is_unlimited ? '無制限' : format_bytes($limit_bytes),
        'formatted_used'      => format_bytes($used_bytes),
        'formatted_remaining' => $is_unlimited ? '無制限' : format_bytes($remaining_bytes),
    ];
}