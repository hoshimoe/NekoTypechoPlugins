# TypechoNotice

一個用於 Typecho 的通知與公告插件，支持多條通知管理、定時顯示、現代化UI以及用戶記憶功能。

> 本插件是 [NekoTypechoPlugins](https://github.com/moehoshio/NekoTypechoPlugins) 聚合專案中的子插件，對應倉庫中的 `Notice` 目錄。

## 功能特點

- 📢 支持多條通知/公告管理
- ✏️ 後台可視化編輯（標題、內容、類型）
- 👁️ 顯示/隱藏切換
- ⏰ 指定開始時間與結束時間
- 🎨 四種通知類型（資訊、成功、警告、錯誤）
- 🌓 支持淺色/深色/跟隨系統主題
- 📍 支持頂部/底部/居中顯示位置
- 🍪 Cookie 記住「不再顯示」選擇
- 💅 自定義CSS支持
- 📱 響應式設計

## 安裝

1. 從 [NekoTypechoPlugins](https://github.com/moehoshio/NekoTypechoPlugins) 倉庫下載 `Notice` 目錄，並將文件夾重命名為 `TypechoNotice`
2. 上傳至 Typecho 的 `usr/plugins/` 目錄
3. 在後台「插件」頁面啟用 `TypechoNotice`

> 注意：插件文件夾名稱必須為 `TypechoNotice`，否則無法正常載入。

## 使用方法

### 後台管理

啟用插件後，在後台導航的「管理」菜單下會出現「通知管理」選項。

在通知管理頁面，您可以：
- **新增通知**：填寫標題、內容、類型、顯示狀態、開始/結束時間
- **編輯通知**：點擊通知標題進入編輯
- **刪除通知**：點擊「刪除」按鈕
- **切換顯示**：快速切換通知的顯示/隱藏狀態

### 插件設置

在插件設置頁面可以配置：
- **顯示位置**：頂部固定 / 底部固定 / 居中彈窗
- **主題**：淺色 / 深色 / 跟隨系統
- **自定義CSS**：覆蓋默認樣式
- **Cookie有效天數**：用戶點擊「不再顯示」後的記憶天數

### 主題調用

如果您希望在主題中自行控制通知的顯示，可以使用：

```php
<?php
$notices = TypechoNotice_Plugin::getNotices();
foreach ($notices as $notice) {
    echo '<div class="my-notice">';
    echo '<h4>' . htmlspecialchars($notice['title']) . '</h4>';
    echo '<p>' . $notice['content'] . '</p>';
    echo '</div>';
}
?>
```

## 自定義樣式

通過插件設置中的「自定義CSS」欄位，您可以覆蓋任何默認樣式。例如：

```css
/* 修改通知圓角 */
.typecho-notice-item {
    border-radius: 0;
}

/* 修改通知背景 */
.typecho-notice-item {
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
TypechoNotice/
├── Plugin.php          # 主插件文件
├── Action.php          # 後台操作處理
├── manage-notice.php   # 後台管理頁面
├── assets/
│   ├── notice.css      # 前端樣式
│   └── notice.js       # 前端腳本
├── LICENSE
└── README.md
```

## 授權

MIT License
