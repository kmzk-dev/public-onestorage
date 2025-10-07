<?php
//handler_post_method.php:POSTリクエストハンドラ: CRUD操作のリクエストを処理
if (!defined('ONESTORAGE_RUNNING')) {
    die('Access Denied: Invalid execution context.');
}
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/chunk_upload.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $path_from_form = $_POST['path'] ?? '';
    $target_dir_path = realpath(DATA_ROOT . '/' . $path_from_form);

    $is_valid_for_action = ($target_dir_path !== false && strpos($target_dir_path, DATA_ROOT) === 0) || is_inbox_view($path_from_form);

    if (!$is_valid_for_action) {
        $target_dir_path = DATA_ROOT;
        $_SESSION['message'] = ['type' => 'danger', 'text' => '不正なディレクトリです。'];
    } else {
        if (is_inbox_view($path_from_form)) {
            $target_dir_path = get_inbox_path();
        }
        switch ($action) {

            case 'create_folder':
                if (is_inbox_view($path_from_form)) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'INBOX内またはルートディレクトリにはフォルダを作成できません。'];
                    break;
                }
                $folder_name = $_POST['folder_name'] ?? '';
                if (!empty($folder_name) && strpbrk($folder_name, "\\/?%*:|\"<>") === false && !str_starts_with($folder_name, '.')) {
                    $new_folder_path = $target_dir_path . '/' . $folder_name;
                    if (!file_exists($new_folder_path)) {
                        mkdir($new_folder_path, 0777, true);
                        $_SESSION['message'] = ['type' => 'success', 'text' => 'フォルダを作成しました。'];
                        rebuild_dir_cache();
                    } else {
                        $_SESSION['message'] = ['type' => 'warning', 'text' => '同じ名前のフォルダが既に存在します。'];
                    }
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => '無効なフォルダ名です。 (.で始まる名前や記号は使えません。)'];
                }
                break;

            case 'upload_chunk':
                if (is_inbox_view($path_from_form)) {
                    $target_dir_path = get_inbox_path();
                }
                if (is_root_view($path_from_form)) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'ルートディレクトリにはファイルをアップロードできません。'];
                    break;
                }
                handle_chunk_upload($target_dir_path);
                break;

            case 'rename_item':
                $old_name = $_POST['old_name'] ?? '';
                $new_name_input = $_POST['new_name'] ?? '';
                $old_path = realpath($target_dir_path . '/' . $old_name);
                if (!$old_path || strpos($old_path, $target_dir_path) !== 0) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => '元のアイテムが見つかりません。'];
                    break;
                }
                if (empty($new_name_input) || strpbrk($new_name_input, "\\/?%*:|\"<>") !== false || str_starts_with($new_name_input, '.')) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => '無効な名前です。記号や.で始まる名前は使えません。'];
                    break;
                }
                $final_new_name = '';
                if (is_dir($old_path)) {
                    $final_new_name = $new_name_input;
                } else {
                    $extension = pathinfo($old_name, PATHINFO_EXTENSION);
                    $final_new_name = empty($extension) ? $new_name_input : $new_name_input . '.' . $extension;
                }
                $new_path = $target_dir_path . '/' . $final_new_name;
                if (strtolower($old_path) === strtolower($new_path)) {
                    break;
                }
                if (file_exists($new_path)) {
                    $_SESSION['message'] = ['type' => 'warning', 'text' => '同じ名前のアイテムが既に存在します。'];
                    break;
                }
                if (rename($old_path, $new_path)) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => '名前を変更しました。'];
                    rebuild_dir_cache();
                    update_star_item($path_from_form, $old_name, $path_from_form, $final_new_name);
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => '名前の変更に失敗しました。サーバーの権限を確認してください。'];
                }
                break;

            case 'move_items':
                /**
                 * 複数選択削除:
                 * 下層レイヤーが深い場合に再回帰的な処理を行うとシステム負荷が高い。
                 * そのため、フォルダ削除を対象外にしています。
                 */
                $items_to_move = json_decode($_POST['items_json'] ?? '[]');
                $destination_rel_path = $_POST['destination'] ?? '';
                $destination_abs_path = realpath(DATA_ROOT . '/' . $destination_rel_path);
                if ($destination_abs_path === false || strpos($destination_abs_path, DATA_ROOT) !== 0) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => '不正な移動先です。'];
                    break;
                }
                $success_count = 0;
                $error_count = 0;
                foreach ($items_to_move as $item_name) {
                    $source_path = $target_dir_path . '/' . $item_name;
                    $dest_path = $destination_abs_path . '/' . $item_name;
                    if (!file_exists($source_path) || str_starts_with($item_name, '.')) {
                        $error_count++;
                        continue;
                    }
                    if (is_dir($source_path) && strpos($destination_abs_path, $source_path) === 0) {
                        $error_count++;
                        continue;
                    }
                    if (file_exists($dest_path)) {
                        $error_count++;
                        continue;
                    }
                    if (rename($source_path, $dest_path)) {
                        $success_count++;
                        update_star_item($path_from_form, $item_name, $destination_rel_path, $item_name);
                    } else {
                        $error_count++;
                    }
                }
                $message = '';
                if ($success_count > 0) $message .= $success_count . '個のアイテムを移動しました。';
                if ($error_count > 0) $message .= $error_count . '個のアイテムは移動できませんでした（移動先に同名ファイルが存在するか、権限がありません、または隠しアイテムです）。';
                $_SESSION['message'] = ['type' => $error_count > 0 ? 'warning' : 'success', 'text' => $message];
                if ($success_count > 0) {
                    rebuild_dir_cache();
                }
                break;

            case 'delete_items':
                $items_to_delete = json_decode($_POST['items_json'] ?? '[]');
                if (!is_array($items_to_delete) || empty($items_to_delete)) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => '削除するアイテムが指定されていません。'];
                    break;
                }

                $success_count = 0;
                $error_count = 0;
                $folder_skip_count = 0;

                foreach ($items_to_delete as $item_name) {
                    $item_path = realpath($target_dir_path . '/' . $item_name);

                    if ($item_path && strpos($item_path, $target_dir_path) === 0 && !str_starts_with($item_name, '.')) {

                        $is_dir = is_dir($item_path);
                        
                        if ($is_dir) {
                            $folder_skip_count++;
                            continue;
                        }
                        
                        if (unlink($item_path)) {
                            $success_count++;
                            remove_star_item($path_from_form, $item_name); //再回帰的な処理を許容しています
                        } else {
                            $error_count++;
                        }
                    } else {
                        $error_count++;
                    }
                }

                $message = '';
                if ($success_count > 0) $message .= $success_count . '個のファイルを削除しました。';
                if ($folder_skip_count > 0) $message .= $folder_skip_count . '個のフォルダは一括削除から除外されました。';
                if ($error_count > 0) $message .= $error_count . '個のアイテムは削除できませんでした（対象が見つからないか、権限がありません、または隠しアイテムです）。';

                if ($success_count > 0) {
                    rebuild_dir_cache();
                }

                if ($success_count > 0 && $error_count === 0 && $folder_skip_count === 0) {
                    $type = 'success';
                } elseif ($error_count > 0 || $folder_skip_count > 0) {
                    $type = 'warning';
                } else {
                    $type = 'danger';
                }

                $_SESSION['message'] = ['type' => $type, 'text' => $message];
                break;

            case 'delete_item':
                $item_name = $_POST['item_name'] ?? ''; 
                $item_path = realpath($target_dir_path . '/' . $item_name);
                
                if ($item_path && strpos($item_path, $target_dir_path) === 0 && !str_starts_with($item_name, '.')) {
                    $is_dir = is_dir($item_path);
                    
                    /**
                     * フォルダ・ファイルの削除実行
                     * フォルダの場合サーバー側で再帰的な処理が発生します
                     */
                    if (($is_dir && delete_directory($item_path)) || (!$is_dir && unlink($item_path))) { 
                        $_SESSION['message'] = ['type' => 'success', 'text' => ($is_dir ? 'フォルダ' : 'ファイル') . 'を削除しました。'];

                        if ($is_dir) {
                            $deleted_item_web_path = ltrim($path_from_form . '/' . $item_name, '/');
                            clean_star_items_for_deleted_folder($deleted_item_web_path);
                        } else {
                            remove_star_item($path_from_form, $item_name);
                        }

                        rebuild_dir_cache();
                    } 
                    else { 
                        $_SESSION['message'] = ['type' => 'danger', 'text' => ($is_dir ? 'フォルダ' : 'ファイル') . 'の削除に失敗しました。']; 
                    }
                } else { 
                    $_SESSION['message'] = ['type' => 'danger', 'text' => '対象が見つからないか、削除できないアイテムです。']; 
                }
                break;
        }
    }
    header('Location: ?path=' . urlencode($path_from_form));
    exit;
}
