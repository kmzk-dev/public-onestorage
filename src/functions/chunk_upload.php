<?php
//chunk_upload.php:分割アップロードの受け付け,一時ファイルの管理,ファイルの結合,最終保存を行う
if (!defined('ONESTORAGE_RUNNING')) {
    die('Access Denied: Invalid execution context.');
}
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/init.php';
function handle_chunk_upload(string $target_dir_path): void {
    global $file_config;
    global $encryption_enabled;
    
    cleanup_stale_chunks();
    
    $chunk = $_FILES['chunk'] ?? null;
    $original_name = $_POST['original_name'] ?? '';
    $chunk_index = (int)($_POST['chunk_index'] ?? -1);
    $total_chunks = (int)($_POST['total_chunks'] ?? -1);
    $total_size = (int)($_POST['total_size'] ?? 0);

    $response = ['type' => 'danger', 'text' => '不明なエラーが発生しました。'];

    if (!$chunk || $chunk['error'] !== UPLOAD_ERR_OK || empty($original_name) || $chunk_index < 0 || $total_chunks <= 0) {
        $response['text'] = '不正なリクエストです。';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    $final_path = $target_dir_path . DIRECTORY_SEPARATOR . $original_name;
    $is_overwrite = file_exists($final_path);

    // 最初のチャンク受信時に、フォルダアイテム上限（200件）と容量制限を事前チェック
    if ($chunk_index === 0) {
        if (!$is_overwrite && count_folder_items($target_dir_path) >= MAX_FOLDER_ITEMS) {
            $response['text'] = 'フォルダ内のアイテム数が上限（' . MAX_FOLDER_ITEMS . '件）に達しているため、アップロードできません。';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        $quota = get_storage_quota_info();
        if (!$quota['is_unlimited']) {
            $existing_size = $is_overwrite ? filesize($final_path) : 0;
            $additional_size = max(0, $total_size - $existing_size);
            if ($quota['used_bytes'] + $additional_size > $quota['limit_bytes']) {
                $response['text'] = 'ストレージの割り当て容量（' . $quota['formatted_limit'] . '）を超過するためアップロードできません。（残容量: ' . $quota['formatted_remaining'] . '）';
                header('Content-Type: application/json');
                echo json_encode($response);
                exit;
            }
        }
    }

    // 一時フォルダにチャンクを書き込み
    $temp_dir = DATA_ROOT . DIRECTORY_SEPARATOR . '.temp_chunks';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0777, true);
        ensure_dir_protection($temp_dir);
    }

    $temp_file_name = session_id() . '_' . md5($original_name) . '.part';
    $temp_file_path = $temp_dir . DIRECTORY_SEPARATOR . $temp_file_name;

    if (file_put_contents($temp_file_path, file_get_contents($chunk['tmp_name']), FILE_APPEND) === false) {
        $response['text'] = '一時ファイルへの書き込みに失敗しました。';
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // ファイル結合-保存
    if ($chunk_index === $total_chunks - 1) {
        
        $max_size_bytes = $file_config['max_file_size_mb'] * 1024 * 1024;
        if (filesize($temp_file_path) !== $total_size || $total_size > $max_size_bytes) {
            unlink($temp_file_path);
            $response['text'] = 'ファイルサイズが不正か、上限を超えています。(' . $file_config['max_file_size_mb'] . 'MB)';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        if (!$is_overwrite && count_folder_items($target_dir_path) >= MAX_FOLDER_ITEMS) {
            unlink($temp_file_path);
            $response['text'] = 'フォルダ内のアイテム数が上限（' . MAX_FOLDER_ITEMS . '件）に達しているため、アップロードできません。';
            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        }

        if ($is_overwrite) {
            unlink($temp_file_path);
            $response = ['type' => 'warning', 'text' => '同名のファイルが既に存在します: ' . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8')];
        } else {
            $encryption_key = get_encryption_key();
            if ($encryption_key === false) {
                $response['text'] = '暗号キーの取得に失敗しました。';
            } else {
                if (encrypt_file_stream($temp_file_path, $final_path, $encryption_key)) {
                    $response = ['type' => 'success', 'text' => 'ファイルをアップロードしました: ' . htmlspecialchars($original_name, ENT_QUOTES, 'UTF-8')];
                    rebuild_dir_cache();
                } else {
                    $response['text'] = 'ファイルの暗号化処理に失敗しました。';
                }
            }
            // 成否を問わず一時ファイルを削除
            if (file_exists($temp_file_path)) {
                unlink($temp_file_path);
            }

        } 

        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['type' => 'processing', 'text' => 'Chunk ' . ($chunk_index + 1) . '/' . $total_chunks . ' processed.']);
    exit;
}