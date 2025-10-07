<?php
// helpers.php:汎用ユーティリティ関数(例のごとく未整理)

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
 * バイト数を読みやすいMB形式にフォーマット
 */
function format_bytes($bytes, $precision = 2) {
    if ($bytes === null || !is_numeric($bytes) || $bytes < 0) return '-';
    if ($bytes == 0) return '0 B';
    $megabytes = $bytes / (1024 * 1024);
    return number_format($megabytes, 2) . ' MB';
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
// ====================================================================ィ
/**
 * フォルダとその中身を削除します
 */
function delete_directory($dir) { 
    if (!file_exists($dir)) return true; 
    if (!is_dir($dir)) return unlink($dir);
    //TODO 再帰的にな処理がシステムに負荷をかけています。再検討が必要です。
    foreach (scandir($dir) as $item) { 
        if ($item == '.' || $item == '..') continue; 
        if (!delete_directory($dir . DIRECTORY_SEPARATOR . $item)) return false; 
    } 
    return rmdir($dir); 
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
            if (defined('INBOX_DIR_NAME') && $item == INBOX_DIR_NAME) continue;
            if (str_starts_with($item, '.')) continue;
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
 * ディレクトリ構造のキャッシュを再構築し、ファイルに保存
 * ディレクトリの表示のために再帰的にフォルダ走査することを回避します
 */
function rebuild_dir_cache(): array {
    $tree = get_directory_tree(DATA_ROOT);
    $list = get_all_directories_recursive(DATA_ROOT);
    sort($list);

    $cache_data = [
        'tree' => $tree,
        'list' => $list
    ];

    $cache_file_path = DATA_ROOT . DIRECTORY_SEPARATOR . DIR_CACHE_PATH;
    file_put_contents($cache_file_path, json_encode($cache_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $cache_data;
}
/**
 * ディレクトリ構造のキャッシュを読み込む
 */
function load_dir_cache(): array {
    $cache_file_path = DATA_ROOT . DIRECTORY_SEPARATOR . DIR_CACHE_PATH;

    if (file_exists($cache_file_path)) {
        $cache_content = file_get_contents($cache_file_path);
        $cache_data = json_decode($cache_content, true);
        if (is_array($cache_data) && isset($cache_data['tree']) && isset($cache_data['list'])) {
            return $cache_data;
        }
    }

    return rebuild_dir_cache();
}
// ====================================================================
// スター（お気に入り）機能系
// ====================================================================
/**
 * スター設定ファイルパスを取得
 */
function get_star_config_path(): string {
    if (!defined('DATA_ROOT') || !defined('STAR_CONFIG_FILENAME')) {
        error_log('DATA_ROOT or STAR_CONFIG_FILENAME is not defined.');
        return '';
    }
    return DATA_ROOT . DIRECTORY_SEPARATOR . STAR_CONFIG_FILENAME;
}
/**
 * スター設定ファイルを読み込み、スター登録されたアイテムのリストを返す。
 */
function load_star_config(): array {
    $path = get_star_config_path();
    if (empty($path) || !file_exists($path)) {
        return [];
    }
    $content = file_get_contents($path);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}
/**
 * スター登録されたアイテムのリストをスター設定ファイルに保存
 */
function save_star_config(array $data): bool {
    $path = get_star_config_path();
    if (empty($path)) return false;
    
    usort($data, fn($a, $b) => strcmp($a['path'] . $a['name'], $b['path'] . $b['name']));
    
    $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($path, $content) !== false;
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
 * 状態を変更し、設定ファイルを保存します。
 */
function toggle_star_item(string $web_path, string $item_name, bool $is_dir): array {
    global $DATA_ROOT;
    
    $current_stars = load_star_config();
    $item_hash = get_item_hash($web_path, $item_name);
    $is_starred = false;
    
    $is_inbox_item = (defined('INBOX_DIR_NAME') && $web_path === 'inbox');
    if ($is_inbox_item) {
        $full_path = realpath(DATA_ROOT . DIRECTORY_SEPARATOR . INBOX_DIR_NAME . DIRECTORY_SEPARATOR . $item_name);
    } else {
        $full_path = realpath(DATA_ROOT . '/' . ltrim($web_path . '/' . $item_name, '/'));
    }
    if ($full_path === false || strpos($full_path, DATA_ROOT) !== 0 || str_starts_with($item_name, '.')) {
        return ['success' => false, 'message' => '無効なアイテムです。'];
    }
    
    $size = $is_dir ? get_directory_size($full_path) : filesize($full_path);

    // ハッシュに基づいてアイテムを検索
    $new_stars = [];
    foreach ($current_stars as $star) {
        if (get_item_hash($star['path'], $star['name']) !== $item_hash) {
            $new_stars[] = $star;
        } else {
            $is_starred = true;
        }
    }

    if ($is_starred) {
        if (save_star_config($new_stars)) {
            return ['success' => true, 'action' => 'removed', 'message' => htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8') . ' のスターを解除しました。'];
        }
    } else {
        $new_item = [
            'hash' => $item_hash,
            'path' => $web_path,
            'name' => $item_name,
            'is_dir' => $is_dir,
            'size' => $size,
        ];
        $new_stars[] = $new_item;
        if (save_star_config($new_stars)) {
            return ['success' => true, 'action' => 'added', 'message' => htmlspecialchars($item_name, ENT_QUOTES, 'UTF-8') . ' をスターに登録しました。'];
        }
    }
    
    return ['success' => false, 'message' => 'スター設定の保存に失敗しました。'];
}
/**
 * 移動、リネーム時にスターアイテムの情報を更新します
 * 古いパス/名前を持つアイテムを検索し、新しい情報に書き換え
 */
function update_star_item(string $old_web_path, string $old_item_name, string $new_web_path, string $new_item_name): bool {
    global $DATA_ROOT;
    
    $old_hash = get_item_hash($old_web_path, $old_item_name);
    $current_stars = load_star_config();
    $updated = false;

    foreach ($current_stars as &$star) {
        if ($star['hash'] === $old_hash) {
            $star['path'] = $new_web_path;
            $star['name'] = $new_item_name;
            $star['hash'] = get_item_hash($new_web_path, $new_item_name);
            $is_inbox_item = (defined('INBOX_DIR_NAME') && $new_web_path === 'inbox');

            if ($is_inbox_item) {
                $full_path = realpath(DATA_ROOT . DIRECTORY_SEPARATOR . INBOX_DIR_NAME . DIRECTORY_SEPARATOR . $new_item_name);
            } else {
                $item_path_from_root = ltrim($new_web_path . '/' . $new_item_name, '/');
                $full_path = realpath(DATA_ROOT . '/' . $item_path_from_root);
            }

            if ($full_path !== false) {
                $star['size'] = is_dir($full_path) ? get_directory_size($full_path) : filesize($full_path);
            }
            
            $updated = true;
            break;
        }
    }
    unset($star);

    if ($updated) {
        return save_star_config($current_stars);
    }

    return false;
}
/**
 * 削除時、スターアイテムの登録を解除
 * 指定されたパスと名前のアイテムをスターリストから削除
 */
function remove_star_item(string $web_path, string $item_name): bool {
    $item_hash = get_item_hash($web_path, $item_name);
    $current_stars = load_star_config();
    $removed = false;

    $new_stars = [];
    foreach ($current_stars as $star) {
        if ($star['hash'] !== $item_hash) {
            $new_stars[] = $star;
        } else {
            $removed = true;
        }
    }

    if (!$removed) {
        return true;
    }

    return save_star_config($new_stars);
}
/**
 * 削除されたフォルダ（とその配下）に関連するスターアイテムをスター設定ファイルから削除
 * データ整合性の維持を目的にしています
 */
function clean_star_items_for_deleted_folder(string $deleted_folder_path): bool
{
    $star_file = get_star_config_path();

    if (empty($star_file) || !file_exists($star_file)) {
        return true; 
    }

    $star_data = load_star_config();

    if (!is_array($star_data)) { return true; }

    $original_count = count($star_data);
    $cleaned_data = []; 

    /**
     * 検索を容易にするため、削除されたフォルダパスを正規化
     * 'Docs/Archive'
     * 'Docs/Archive/'
     */
    $normalized_deleted_path = trim($deleted_folder_path, '/');
    $search_prefix = $normalized_deleted_path . '/';

    foreach ($star_data as $item) {
        $item_path = $item['path'];
        $item_name = $item['name'];
        
        $item_full_path = ltrim($item_path . '/' . $item_name, '/');

        /** フィルタリングをスキップ
         *
         * 削除されたフォルダ自身がスター登録されていた場合
         * アイテムが削除されたフォルダの配下にある場合
         */
        if ($item['is_dir'] && $item_full_path === $normalized_deleted_path) {
            continue;
        }
        
        if (str_starts_with($item_path . '/', $search_prefix)) {
            continue;
        }

        $cleaned_data[] = $item; 
    }

    if (count($cleaned_data) < $original_count) {
        return save_star_config($cleaned_data);
    }

    return true;
}