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

    $is_sharebox = is_sharebox_view($path_from_form);
    $is_sharebox_sub = str_starts_with($path_from_form, 'sharebox/');

    if ($is_sharebox || $is_sharebox_sub) {
        if (!is_sharebox_enabled()) {
            $target_dir_path = false;
            $is_valid_for_action = false;
        } elseif ($is_sharebox) {
            $target_dir_path = SHARE_ROOT;
            $is_valid_for_action = true;
        } else {
            $sub = substr($path_from_form, 9);
            $target_dir_path = realpath(SHARE_ROOT . '/' . $sub);
            $is_valid_for_action = ($target_dir_path !== false && str_starts_with($target_dir_path, SHARE_ROOT));
        }
    } else {
        $target_dir_path = realpath(DATA_ROOT . '/' . $path_from_form);
        $is_valid_for_action = ($target_dir_path !== false && strpos($target_dir_path, DATA_ROOT) === 0) || is_inbox_view($path_from_form);
    }

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
                if (count_folder_items($target_dir_path) >= MAX_FOLDER_ITEMS) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'フォルダ内のアイテム数が上限（' . MAX_FOLDER_ITEMS . '件）に達しているため、新しいフォルダを作成できません。'];
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
                if (is_sharebox_view($path_from_form)) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'SHARE BOX 直下にはファイルを直接アップロードできません。共有用フォルダを作成してください。'];
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
                 * 複数選択移動 / 単体移動
                 */
                $items_to_move = [];
                $single_item_name = trim($_POST['item_name'] ?? '');
                $raw_items_json = trim($_POST['items_json'] ?? '');

                if ($single_item_name !== '') {
                    $items_to_move = [$single_item_name];
                } elseif ($raw_items_json !== '') {
                    $decoded = json_decode($raw_items_json, true);
                    if (is_array($decoded)) {
                        $items_to_move = $decoded;
                    }
                }

                if (empty($items_to_move)) {
                    $_SESSION['message'] = ['type' => 'warning', 'text' => '移動するアイテムが選択されていません。'];
                    break;
                }

                $destination_rel_path = $_POST['destination'] ?? '';

                // 同一フォルダへの移動チェック
                if ($path_from_form === $destination_rel_path) {
                    $_SESSION['message'] = ['type' => 'warning', 'text' => '移動元と移動先が同一です。'];
                    break;
                }

                // 移動先の解決
                if ($destination_rel_path === 'sharebox') {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => 'SHARE BOX 直下にはファイルを直接移動できません。共有用フォルダを作成して移動してください。'];
                    break;
                }

                $is_dest_sharebox = str_starts_with($destination_rel_path, 'sharebox/');
                if ($is_dest_sharebox) {
                    if (!is_sharebox_enabled()) {
                        $_SESSION['message'] = ['type' => 'danger', 'text' => 'SHARE BOX が有効化されていません。'];
                        break;
                    }
                    $sub = substr($destination_rel_path, 9);
                    $destination_abs_path = realpath(SHARE_ROOT . '/' . $sub);
                    if ($destination_abs_path === false || !str_starts_with($destination_abs_path, SHARE_ROOT) || !is_dir($destination_abs_path)) {
                        $_SESSION['message'] = ['type' => 'danger', 'text' => '不正な移動先です。'];
                        break;
                    }
                } elseif ($destination_rel_path === 'inbox' || is_inbox_view($destination_rel_path)) {
                    $destination_abs_path = get_inbox_path();
                    if ($destination_abs_path === false || !is_dir($destination_abs_path)) {
                        $_SESSION['message'] = ['type' => 'danger', 'text' => 'INBOXが見つかりません。'];
                        break;
                    }
                } else {
                    $destination_abs_path = realpath(DATA_ROOT . '/' . $destination_rel_path);
                    if ($destination_abs_path === false || strpos($destination_abs_path, DATA_ROOT) !== 0 || !is_dir($destination_abs_path)) {
                        $_SESSION['message'] = ['type' => 'danger', 'text' => '不正な移動先です。'];
                        break;
                    }
                }

                // 移動先フォルダのアイテム上限（200件）チェック
                $dest_item_count = count_folder_items($destination_abs_path);
                if ($dest_item_count + count($items_to_move) > MAX_FOLDER_ITEMS) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => '移動先のフォルダ内のアイテム数が上限（' . MAX_FOLDER_ITEMS . '件）を超えるため、移動できません。'];
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
                    // フォルダの移動制限: フォルダ単位の移動は全面的に禁止（ファイルのみ移動可能）
                    if (is_dir($source_path)) {
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
                if ($error_count > 0) $message .= $error_count . '個のアイテムは移動できませんでした（フォルダは移動できません。また同名ファイルが存在するか、権限がない可能性があります）。';
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
                            // フォルダは誤削除防止のため、private / SHARE BOX 問わず一括削除から除外（個別削除のみ）
                            $folder_skip_count++;
                            continue;
                        } else {
                            if (unlink($item_path)) {
                                $success_count++;
                                remove_star_item($path_from_form, $item_name); //再回帰的な処理を許容しています
                            } else {
                                $error_count++;
                            }
                        }
                    } else {
                        $error_count++;
                    }
                }

                $message = '';
                if ($success_count > 0) $message .= $success_count . '個のファイルを削除しました。';
                if ($folder_skip_count > 0) $message .= $folder_skip_count . '個のフォルダは一括削除から除外されました（フォルダ削除は個別に行ってください）。';
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
                    
                    if ($is_dir && has_folder_items($item_path)) {
                        $_SESSION['message'] = [
                            'type' => 'warning',
                            'text' => 'フォルダ内にファイルが存在するため削除できません。中のファイルを削除してからフォルダを削除してください。'
                        ];
                        break;
                    }

                    /**
                     * フォルダ・ファイルの削除実行
                     * フォルダ内にファイルが存在する場合は削除を制限し、空フォルダのみ安全に削除します
                     */
                    if (($is_dir && delete_directory($item_path)) || (!$is_dir && unlink($item_path))) { 
                        $_SESSION['message'] = ['type' => 'success', 'text' => ($is_dir ? 'フォルダ' : 'ファイル') . 'を削除しました。'];

                        if ($is_dir) {
                            $deleted_item_web_path = ltrim($path_from_form . '/' . $item_name, '/');
                            clean_star_items_for_deleted_folder($deleted_item_web_path);
                            delete_share_record_for_folder($item_name);
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



            case 'delete_sharebox_folder':
                $item_name = trim($_POST['item_name'] ?? '');
                if (!is_sharebox_enabled()) break;
                $folder_path = realpath(SHARE_ROOT . '/' . $item_name);
                if (!$folder_path || !str_starts_with($folder_path, SHARE_ROOT) || str_starts_with($item_name, '.')) {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => '対象フォルダが見つかりません。'];
                    break;
                }
                // 共有リンクが有効なフォルダは削除不可（事前にリンクを無効化する必要がある）
                $active_share = get_active_share_for_folder($item_name);
                if ($active_share !== null) {
                    $_SESSION['message'] = ['type' => 'warning', 'text' => '共有リンクが有効なフォルダは削除できません。先にリンクを無効化してください。'];
                    break;
                }
                // フォルダ内にファイルが存在するかチェック
                if (has_folder_items($folder_path)) {
                    $_SESSION['message'] = [
                        'type' => 'warning',
                        'text' => 'フォルダ内にファイルが存在するため削除できません。中のファイルを削除してからフォルダを削除してください。'
                    ];
                    break;
                }
                if (delete_directory($folder_path)) {
                    $_SESSION['message'] = ['type' => 'success', 'text' => 'フォルダを削除しました。'];
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => '削除に失敗しました。'];
                }
                break;

            case 'create_share_link':
                $folder_name = trim($_POST['folder_name'] ?? '');
                $raw_pwd     = (string)($_POST['share_password'] ?? '');
                $password    = ($raw_pwd !== '' && trim($raw_pwd) !== '') ? trim($raw_pwd) : null;
                $result = create_share_link($folder_name, $password);
                if ($result['success']) {
                    $_SESSION['created_share_token'] = $result['token'];
                    $_SESSION['message'] = ['type' => 'success', 'text' => '共有リンクを発行しました。'];
                } else {
                    $_SESSION['message'] = ['type' => 'danger', 'text' => $result['message']];
                }
                break;

            case 'revoke_share_link':
                $token = trim($_POST['token'] ?? '');
                $db    = get_share_db();
                if ($db && $token) {
                    $db->prepare("DELETE FROM shares WHERE token = :t")->execute([':t' => $token]);
                    $_SESSION['message'] = ['type' => 'success', 'text' => '共有リンクを無効化しました。'];
                }
                break;
        }
    }

    $is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
        || (isset($_POST['ajax']) && $_POST['ajax'] === '1')
        || ($action === 'upload_chunk');

    if ($is_ajax) {
        header('Content-Type: application/json; charset=utf-8');
        $msg = $_SESSION['message'] ?? ['type' => 'info', 'text' => ''];
        unset($_SESSION['message']);
        echo json_encode($msg, JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ?path=' . urlencode($path_from_form));
    exit;
}

