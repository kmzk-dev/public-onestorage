# ==============================================================================
# 設定項目
# ==============================================================================
# 公開用Astroプロジェクト
$DestProjectRoot = "C:\Users\kazma\Projects\archive\fillmee"
$DestContentDir = Join-Path $DestProjectRoot "src\content"
$DestFolderName = "onestorage" # コピー先フォルダ名

# コピー元: legal, prod が内包されているフォルダ（相対パスまたは絶対パス）
$SourceFolderName = "fillmee\onestorage"

# ==============================================================================
# 処理本体
# ==============================================================================
$ErrorActionPreference = "Stop"

# コピー元の絶対パスを解決（PowerShell 5.1対応）
$ResolvedSource = Resolve-Path -Path $SourceFolderName -ErrorAction SilentlyContinue
if ($ResolvedSource) {
    $SourceDir = $ResolvedSource.Path
}
else {
    $SourceDir = $null
}

# コピー元フォルダの存在確認
if (-not $SourceDir -or -not (Test-Path -Path $SourceDir -PathType Container)) {
    Write-Host "[エラー] コピー元フォルダが見つかりません: $SourceFolderName" -ForegroundColor Red
    exit 1
}

# コピー先フォルダの絶対パス
$TargetDir = Join-Path $DestContentDir $DestFolderName

# コピー先親ディレクトリ(src/content)の存在確認
if (-not (Test-Path -Path $DestContentDir)) {
    Write-Host "[エラー] 公開用ディレクトリが見つかりません: $DestContentDir" -ForegroundColor Red
    exit 1
}

# 既存フォルダの確認と上書きプロンプト
if (Test-Path -Path $TargetDir) {
    Write-Host "コピー先に同名フォルダが既に存在します:" -ForegroundColor Yellow
    Write-Host "  -> $TargetDir" -ForegroundColor Cyan
    
    $Confirmation = Read-Host "上書きしますか？ (y: 上書き / n: キャンセル) [y/n]"
    if ($Confirmation -notmatch '^[yY](es)?$') {
        Write-Host "処理をキャンセルしました。" -ForegroundColor DarkGray
        exit 0
    }
}
else {
    # 存在しない場合は作成
    New-Item -ItemType Directory -Path $TargetDir -Force | Out-Null
}

# コピー実行
Write-Host "ファイルをコピー中..." -ForegroundColor Green
Write-Host "  FROM: $SourceDir"
Write-Host "  TO  : $TargetDir"

Copy-Item -Path "$SourceDir\*" -Destination $TargetDir -Recurse -Force

Write-Host "コピーが完了しました。" -ForegroundColor Green

# 公開用プロジェクトディレクトリへ移動
Set-Location -Path $DestProjectRoot
Write-Host "公開用プロジェクトディレクトリへ移動しました: $DestProjectRoot" -ForegroundColor Cyan
Write-Host "続けて 'npm run dev' を実行してください。" -ForegroundColor Yellow
Write-Host "公開する場合は 'code -r .' でVSCODEを開き、既存の更新ファイルがないことを確認してください " -ForegroundColor Yellow