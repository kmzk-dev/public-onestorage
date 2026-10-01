import tkinter as tk
from tkinter import filedialog, messagebox, scrolledtext, ttk
import hashlib
from Crypto.Cipher import AES
from Crypto.Util.Padding import unpad
import threading
import os

# --- 復号ロジック ---
# PHPの最終安定版 `decrypt_file_stream` と完全互換

AES_BLOCK_SIZE = 16

def get_decryption_key(email: str) -> bytes:
    """
    PHPの `hash('sha256', ..., true)` と同等の鍵を生成します。
    """
    return hashlib.sha256(email.encode('utf-8')).digest()

def decrypt_file(source_path: str, dest_path: str, key: bytes) -> tuple[bool, str]:
    """
    PHPのストリーミング復号ロジックをPythonで忠実に再現します。
    """
    try:
        with open(source_path, 'rb') as f_in, open(dest_path, 'wb') as f_out:
            # 1. ファイル先頭からIV (初期化ベクトル) を読み込む
            iv = f_in.read(AES_BLOCK_SIZE)
            if len(iv) < AES_BLOCK_SIZE:
                return False, "ファイルが小さすぎるか、破損しています。"

            # 2. 復号オブジェクトを一度だけ生成
            cipher = AES.new(key, AES.MODE_CBC, iv)
            
            ct_buf = b''
            is_first_chunk = True

            while True:
                # チャンクを読み込みバッファに追加
                read_chunk = f_in.read(8192)
                if not read_chunk:
                    break
                ct_buf += read_chunk

                # 最後のブロックをパディング処理のために残す
                available = len(ct_buf)
                if available > AES_BLOCK_SIZE:
                    process_len = available - (available % AES_BLOCK_SIZE)
                    # ファイルの終端でない場合は、パディング処理のために最後のブロックをバッファに残す
                    if f_in.tell() != os.fstat(f_in.fileno()).st_size:
                        process_len -= AES_BLOCK_SIZE
                    
                    if process_len > 0:
                        to_dec = ct_buf[:process_len]
                        ct_buf = ct_buf[process_len:]
                        
                        decrypted_chunk = cipher.decrypt(to_dec)
                        f_out.write(decrypted_chunk)
            
            # 3. 最後に残ったバッファを復号し、パディングを除去
            if ct_buf:
                final_decrypted = cipher.decrypt(ct_buf)
                unpadded_final = unpad(final_decrypted, AES_BLOCK_SIZE)
                f_out.write(unpadded_final)
            
        return True, "成功"

    except ValueError:
        return False, "パディングエラー。キーが違うかファイルが破損しています。"
    except Exception as e:
        return False, f"予期せぬエラーが発生しました: {e}"

# --- GUIアプリケーション ---

class DecryptApp(tk.Tk):
    def __init__(self):
        super().__init__()
        self.title("ONESTORAGE 復号ツール (フォルダ専用)")
        self.geometry("650x450")
        self.resizable(False, False)
        # Ttkテーマを設定 (Clamは比較的シンプルでモダンなテーマ)
        s = ttk.Style()
        s.theme_use('clam')
        self.config(bg='white') 

        # メインフレーム
        # TtkのFrameに置き換え。背景色はルートウィンドウから継承される
        main_frame = ttk.Frame(self, padding="15 15 15 15")
        main_frame.pack(fill=tk.BOTH, expand=True)

        # 1. メールアドレス
        # ttk.Labelに置き換え
        ttk.Label(main_frame, text="ONESTORAGEに登録したメールアドレス:", anchor='w',font=("Arial", 12)).pack(fill=tk.X, pady=(0, 5))
        # 枠線と大型化を維持するため、tk.Entryをそのまま使用
        self.email_entry = tk.Entry(main_frame, width=50, font=("Arial", 12), relief="groove", borderwidth=2)
        self.email_entry.pack(fill=tk.X, pady=(0, 10))

        # 2. 復号元と 3. 復号先の横並びフレーム
        ttk.Label(main_frame, text="復号元/保存先フォルダを指定", anchor='w', font=("Arial", 12)).pack(fill=tk.X, pady=(10, 5))
        
        # 横並びコンテナ (ttk.Frameに置き換え)
        folder_frame = ttk.Frame(main_frame)
        folder_frame.pack(fill=tk.X, pady=(0, 15))

        # --- left ---
        input_frame = ttk.Frame(folder_frame) # ttk.Frameに置き換え
        input_frame.pack(side=tk.LEFT, fill=tk.X, expand=True, padx=(0, 10))
        self.select_folder_btn = ttk.Button(input_frame, text="📂 復号元フォルダ", command=self.select_folder) # ttk.Buttonに置き換え
        self.select_folder_btn.pack(fill=tk.X)

        # --- right ---
        output_frame = ttk.Frame(folder_frame) # ttk.Frameに置き換え
        output_frame.pack(side=tk.LEFT, fill=tk.X, expand=True, padx=(10, 0))
        self.select_output_btn = ttk.Button(output_frame, text="💾 保存先フォルダ", command=self.select_output_dir) # ttk.Buttonに置き換え
        self.select_output_btn.pack(fill=tk.X)

        # --- パス表示エリア---
        path_display_frame = ttk.Frame(main_frame, padding="1 0 1 0")
        path_display_frame.pack(fill=tk.X, pady=(5, 10))

        self.target_path_label = ttk.Label(path_display_frame, text="復号元: 選択されていません", foreground="gray", justify=tk.LEFT, wraplength=600)
        self.target_path_label.pack(fill=tk.X, pady=(2, 2))

        self.output_path_label = ttk.Label(path_display_frame, text="保存先: 選択されていません", foreground="gray", justify=tk.LEFT, wraplength=600)
        self.output_path_label.pack(fill=tk.X, pady=(2, 2))

        # 4. 実行ボタン (大型化とttk対応)
        # Ttk ButtonはHeight/Bg/Fgの設定が複雑なため、一部設定を変更
        s.configure('Big.TButton', font=('Arial', 16, 'bold'), padding=10)
        s.map('Big.TButton', background=[('active', '#0a58ca')],foreground=[('active', 'white')])

        self.decrypt_btn = ttk.Button(main_frame, text="復号を開始", command=self.start_decryption, style='Big.TButton')
        self.decrypt_btn.pack(fill=tk.X, pady=(15, 10))

        # 5. ログ表示
        # scrolledtextはtkウィジェットのためそのまま。背景色は白を維持。
        self.log_area = scrolledtext.ScrolledText(main_frame, height=8, state='disabled', relief="solid", borderwidth=1) 
        self.log_area.pack(fill=tk.BOTH, expand=True, pady=(5, 0))

        # 内部変数
        self.target_paths = []
        self.output_dir = ""

    def select_folder(self):
        dirpath = filedialog.askdirectory(title="復号するファイルが含まれるフォルダを選択")
        if dirpath:
            self.target_paths = [os.path.join(dirpath, f) for f in os.listdir(dirpath) if os.path.isfile(os.path.join(dirpath, f))]
            self.target_path_label.config(text=f"フォルダ内の {len(self.target_paths)} ファイルを対象とします")

    def select_output_dir(self):
        dirpath = filedialog.askdirectory(title="保存先フォルダを選択")
        if dirpath:
            self.output_dir = dirpath
            self.output_path_label.config(text=dirpath)

    def log(self, message):
        self.log_area.config(state='normal')
        self.log_area.insert(tk.END, message + "\n")
        self.log_area.see(tk.END)
        self.log_area.config(state='disabled')
        self.update_idletasks()

    def set_ui_state(self, is_running):
        state = 'disabled' if is_running else 'normal'
        widgets = [self.email_entry, self.select_folder_btn, self.select_output_btn, self.decrypt_btn]
        for widget in widgets:
            widget.config(state=state)
        self.decrypt_btn.config(text="処理中..." if is_running else "復号を開始")
            
    def start_decryption(self):
        email = self.email_entry.get()
        if not email or not self.target_paths or not self.output_dir:
            messagebox.showerror("入力エラー", "すべての項目を入力・選択してください。")
            return
        threading.Thread(target=self.run_decryption_thread, args=(email,)).start()
    
    def reset_ui_paths(self):
        """
        内部変数とUI上のパス表示を初期状態に戻します。
        """
        # 内部変数をクリア
        self.target_paths = []
        self.output_dir = ""
        
        # ラベル表示をリセット
        self.target_path_label.config(text="復号元: 選択されていません", foreground="gray")
        self.output_path_label.config(text="保存先: 選択されていません", foreground="gray")

    def run_decryption_thread(self, email):
        self.set_ui_state(True)
        self.log_area.config(state='normal')
        self.log_area.delete('1.0', tk.END)
        self.log_area.config(state='disabled')

        self.log("処理を開始します...")
        
        try:
            key = get_decryption_key(email)
        except Exception as e:
            self.log(f"エラー: 鍵の生成に失敗しました。 {e}")
            messagebox.showerror("エラー", "鍵の生成に失敗しました。")
            self.set_ui_state(False)
            return

        success_count, fail_count = 0, 0
        for file_path in self.target_paths:
            filename = os.path.basename(file_path)
            dest_path = os.path.join(self.output_dir, filename)
            self.log(f"ファイル: {filename}")

            success, message = decrypt_file(file_path, dest_path, key)
            
            if success:
                self.log("  -> 成功")
                success_count += 1
            else:
                self.log(f"  -> 失敗: {message}")
                fail_count += 1
        
        self.log("--------------------")
        self.log(f"処理完了 (成功: {success_count}件, 失敗: {fail_count}件)")
        self.set_ui_state(False)
        self.reset_ui_paths()

        if fail_count > 0:
            messagebox.showwarning("完了", f"{fail_count}件のファイルでエラーが発生しました。詳細はログを確認してください。")
        else:
            messagebox.showinfo("完了", "すべてのファイルの復号が正常に完了しました。")

if __name__ == "__main__":
    app = DecryptApp()
    app.mainloop()