# NekoTypechoLinks

一個功能完善的 Typecho 友情鏈接插件，支持現有友鏈匯入、優先級、分類、排序/隨機排序、隨機取得一個或多個，以及前端側 RSS 擷取與展示。

> 本插件是 [NekoTypechoPlugins](https://github.com/moehoshio/NekoTypechoPlugins) 聚合專案中的子插件，對應倉庫中的 `Links` 目錄。

## 功能特點

- 🔗 後台可視化友鏈管理（名稱、網址、頭像、描述、分類、RSS）
- 📥 匯入現有友鏈：支持從 Typecho 官方 Links 插件資料表匯入，或批量貼上文字/JSON
- ⭐ 優先級（priority）與排序值（sort_order）雙重排序
- 🗂️ 分類分組顯示
- 🔀 排序方式可選：優先級 / 排序值 / 建立時間 / 隨機排序
- 🎲 隨機取得一個或多個友鏈（主題 API）
- 📰 RSS 擷取：在訪客瀏覽器端擷取友鏈 RSS 並顯示最新文章，**請求不經過本站伺服器，避免暴露伺服器 IP**
- 📝 文章/頁面短代碼 `[friendlinks]`
- 🌓 自適應淺色/深色主題、響應式設計
- 💅 自定義 CSS 支持

## 安裝

1. 從 [NekoTypechoPlugins](https://github.com/moehoshio/NekoTypechoPlugins) 倉庫下載 `Links` 目錄，並將文件夾重命名為 `NekoTypechoLinks`
2. 上傳至 Typecho 的 `usr/plugins/` 目錄
3. 在後台「插件」頁面啟用 `NekoTypechoLinks`

> 注意：插件文件夾名稱必須為 `NekoTypechoLinks`，否則無法正常載入。

## 使用方法

### 後台管理

啟用插件後，在後台導航的「管理」菜單下會出現「友情鏈接」選項。在該頁面，您可以：

- **新增/編輯友鏈**：填寫名稱、網址、頭像、描述、分類、RSS、優先級、排序值與顯示狀態
- **切換顯示**：快速隱藏/顯示某個友鏈
- **刪除友鏈**

### 匯入現有友鏈

在管理頁面的「匯入友鏈」區塊：

- **從官方 Links 插件匯入**：自動讀取 Typecho 官方 Links 插件的 `links` 資料表並匯入。
- **批量匯入**：每行一筆，格式為 `名稱|網址|頭像|描述|分類|RSS`，也支持貼上 JSON 陣列：

```json
[
  { "name": "示例站點", "url": "https://example.com", "image": "https://example.com/avatar.png", "description": "一句話介紹", "category": "技術", "rss": "https://example.com/feed", "priority": 10, "sort_order": 0 }
]
```

### 短代碼

在文章或獨立頁面內容中插入：

```
[friendlinks]
```

支持以下屬性：

| 屬性 | 說明 | 範例 |
| ---- | ---- | ---- |
| `category` | 僅顯示指定分類 | `category="技術"` |
| `order` | 排序方式：`priority` / `sort` / `created` / `random` | `order="random"` |
| `limit` | 最多顯示數量，`0` 表示全部 | `limit="10"` |
| `rss` | 是否顯示 RSS 區塊（`1`/`0`） | `rss="1"` |

範例：`[friendlinks category="技術" order="random" limit="10" rss="1"]`

### 主題調用

在主題模板中可直接調用以下靜態方法：

```php
<?php
// 渲染完整友鏈列表（HTML）
echo NekoTypechoLinks_Plugin::render();

// 帶參數渲染
echo NekoTypechoLinks_Plugin::render(array(
    'category' => '技術',
    'order'    => 'random',
    'limit'    => 12,
));

// 取得友鏈資料陣列（自行渲染）
$links = NekoTypechoLinks_Plugin::getLinks(array('order' => 'priority', 'limit' => 0));

// 隨機取得一個或多個友鏈
$one  = NekoTypechoLinks_Plugin::getRandomLinks(1);
$some = NekoTypechoLinks_Plugin::getRandomLinks(5);

// 取得所有分類
$categories = NekoTypechoLinks_Plugin::getCategories();
?>
```

## RSS 擷取與隱私

啟用「RSS 擷取」後，含有 RSS 地址的友鏈會在前端由 `links.js` 透過訪客瀏覽器（`fetch`）直接擷取並顯示最新文章。由於請求由訪客發出而非伺服器，因此**不會暴露本站伺服器 IP**。

部分 RSS 來源未開放跨域（CORS），瀏覽器將擷取失敗並安靜降級顯示「無法擷取 RSS」。如需繞過跨域限制，可在插件設置中填寫「RSS 代理地址」，屆時請求會以「代理地址 + 被擷取的 RSS 網址」的形式發出。

## 插件設置

- **預設排序方式**：優先級 / 排序值 / 建立時間 / 隨機
- **按分類分組顯示**
- **鏈接打開方式**：新分頁 / 當前分頁
- **為鏈接添加 nofollow**
- **啟用 RSS 擷取** 與 **RSS 顯示條數**
- **RSS 代理地址**（可選）
- **自定義 CSS**

## 目錄結構

```
NekoTypechoLinks/
├── Plugin.php          # 主插件文件
├── Action.php          # 後台操作處理（CRUD 與匯入）
├── manage-links.php    # 後台管理頁面
├── assets/
│   ├── links.css       # 前端樣式
│   └── links.js        # 前端腳本（客戶端 RSS 擷取）
├── LICENSE
└── README.md
```

## 授權

MIT License
