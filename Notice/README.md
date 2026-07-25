# NekoTypechoNotice

一個用於 Typecho 的通知與公告插件，支持多條通知管理、定時顯示、多語言版本、現代化UI以及用戶記憶功能。

> 本插件是 [NekoTypechoPlugins](https://github.com/moehoshio/NekoTypechoPlugins) 聚合專案中的子插件，對應倉庫中的 `Notice` 目錄。

## 功能特點

- 📢 支持多條通知/公告管理
- ✏️ 後台可視化編輯（標題、內容、類型）
- 👁️ 顯示/隱藏切換
- ⏰ 指定開始時間與結束時間
- 🎨 四種通知類型（資訊、成功、警告、錯誤）
- 🌐 多語言版本：每條通知可分語言撰寫標題與內容，依訪客語言自動選擇
- 🌓 支持淺色/深色/跟隨系統主題
- 📍 支持頂部/底部/居中顯示位置
- 🍪 Cookie 記住「不再顯示」選擇
- 💅 自定義CSS支持
- 📱 響應式設計

## 安裝

1. 從 [NekoTypechoPlugins](https://github.com/moehoshio/NekoTypechoPlugins) 倉庫下載 `Notice` 目錄，並將文件夾重命名為 `NekoTypechoNotice`
2. 上傳至 Typecho 的 `usr/plugins/` 目錄
3. 在後台「插件」頁面啟用 `NekoTypechoNotice`

> 注意：插件文件夾名稱必須為 `NekoTypechoNotice`，否則無法正常載入。

## 使用方法

### 後台管理

啟用插件後，在後台導航的「管理」菜單下會出現「通知管理」選項。

在通知管理頁面，您可以：
- **新增通知**：填寫標題、內容、類型、顯示狀態、開始/結束時間
- **編輯通知**：點擊通知標題進入編輯
- **刪除通知**：點擊「刪除」按鈕
- **切換顯示**：快速切換通知的顯示/隱藏狀態
- **撰寫多語言版本**：見下節

### 多語言

每條通知都可以有多個語言版本：

- **主要語言**：上方標題與內容所使用的語言。留空時視為站點語言。
- **其他語言版本**：按需新增，每個版本填寫語言代碼（如 `en`、`ja`、`zh-TW`）、標題與內容；只需翻譯需要的語言。

訪客語言沒有對應版本時，依下列順序回退：

1. 訪客語言精確匹配（`zh-TW` → `zh-TW`）
2. **中文簡繁互為缺省後備**（繁體缺失時回退簡體，簡體缺失時回退繁體）
3. 同語系的其他地區變體（`pt-BR` → `pt-PT`）
4. **英文（`en`）**
5. **任意一個有內容的版本**（優先使用主要語言的版本）

標題與內容分別回退，因此只翻譯了內容、未填標題的版本會沿用主要語言的標題。

訪客語言的判定順序為：網址參數 `?lang=` → Cookie `typecho_lang` → 瀏覽器 `Accept-Language` → 站點語言（僅在完全偵測不到訪客語言時使用），可在插件設置中調整。

前端輸出的每條通知會帶上 `lang` 屬性，標示實際採用的語言。

### 插件設置

在插件設置頁面可以配置：
- **顯示位置**：頂部固定 / 底部固定 / 居中彈窗
- **主題**：淺色 / 深色 / 跟隨系統
- **自定義CSS**：覆蓋默認樣式
- **Cookie有效天數**：用戶點擊「不再顯示」後的記憶天數
- **多語言判定方式**：自動 / 瀏覽器語言 / 站點語言。若站點啟用了整頁快取，建議選擇「站點語言」，避免不同語言的訪客拿到同一份快取

### 主題調用

如果您希望在主題中自行控制通知的顯示，可以使用：

```php
<?php
// 已依訪客語言取好標題與內容，另附 lang 欄位標示實際採用的語言
$notices = NekoTypechoNotice_Plugin::getNotices();
foreach ($notices as $notice) {
    echo '<div class="my-notice" lang="' . htmlspecialchars($notice['lang']) . '">';
    echo '<h4>' . htmlspecialchars($notice['title']) . '</h4>';
    echo '<p>' . $notice['content'] . '</p>';
    echo '</div>';
}

// 取得未經語言處理的原始資料
$raw = NekoTypechoNotice_Plugin::getNotices(false);

// 指定語言取值
$notice = NekoTypechoNotice_Plugin::localize($raw[0], array('ja'));
?>
```

## 自定義樣式

通過插件設置中的「自定義CSS」欄位，您可以覆蓋任何默認樣式。例如：

```css
/* 修改通知圓角 */
.neko-typecho-notice-item {
    border-radius: 0;
}

/* 修改通知背景 */
.neko-typecho-notice-item {
    background: rgba(255, 255, 255, 0.95);
}
```

CSS變量列表：
- `--notice-bg-light` - 淺色背景
- `--notice-bg-dark` - 深色背景
- `--notice-text-light` - 淺色文字
- `--notice-text-dark` - 深色文字
- `--notice-border-radius` - 圓角大小
- `--notice-shadow` - 陰影
- `--notice-info` - 資訊色
- `--notice-success` - 成功色
- `--notice-warning` - 警告色
- `--notice-error` - 錯誤色

## 目錄結構

```
NekoTypechoNotice/
├── Plugin.php          # 主插件文件
├── Action.php          # 後台操作處理
├── I18n.php            # 多語言解析與語言回退
├── manage-notice.php   # 後台管理頁面
├── assets/
│   ├── notice.css      # 前端樣式
│   └── notice.js       # 前端腳本
├── LICENSE
└── README.md
```

## 從舊版本升級

### 從 `TypechoNotice` 舊命名升級

插件自有的類名、部署資料夾名、CSS 類名與 Cookie 鍵已統一改為 `NekoTypechoNotice` / `neko-typecho-*` 前綴。
（Typecho 核心的類名與後台樣式，如 `Typecho_Db`、`typecho-option`、Cookie `typecho_lang`，維持原樣不受影響。）

升級步驟：

1. 在後台「插件」頁面**停用** `TypechoNotice`。
2. 將 `usr/plugins/TypechoNotice` 重新命名為 `usr/plugins/NekoTypechoNotice`，並以新版檔案覆蓋。
3. 重新**啟用** `NekoTypechoNotice`。

需要留意的變更：

- **通知資料不受影響**：`notice` 資料表名稱未變，既有通知與多語言版本全部保留。
- **插件設置會重置**：Typecho 以插件名稱儲存設置，改名後請在設置頁重新配置一次（顯示位置、主題、Cookie 天數、多語言判定方式、自定義CSS）。
- **自定義 CSS 需同步**：`.typecho-notice-*` → `.neko-typecho-notice-*`，容器 id `typecho-notice-container` → `neko-typecho-notice-container`。
- **主題調用需同步**：`TypechoNotice_Plugin::` → `NekoTypechoNotice_Plugin::`。
- **訪客的「不再顯示」記錄會重置**：Cookie 鍵由 `typecho_notice_dismissed` 改為 `neko_typecho_notice_dismissed`，先前點過「不再顯示」的訪客會再看到一次通知。

### 多語言欄位

多語言功能為 `notice` 資料表新增了 `default_lang` 與 `i18n` 兩個欄位，需要**停用後再啟用**插件才會套用。

自 v1.1.0 起，停用插件**不再刪除** `notice` 資料表，因此升級時不會遺失既有通知；如需徹底清除資料，請手動刪除該資料表。

既有通知無需任何調整：未填寫多語言版本時，行為與升級前完全一致。

## 相容性

- Typecho 1.1 / 1.2
- 資料表使用 MySQL 語法（與本倉庫其他子插件一致）
- `I18n.php` 與 `CommentFilter` 子插件中的同名文件內容一致（僅類名不同），以保證各子插件可獨立部署；修改時請同步

## 授權

MIT License
