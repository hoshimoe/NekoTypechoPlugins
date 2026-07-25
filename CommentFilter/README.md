# NekoTypechoCommentFilter

一個用於攔截「帶連結的垃圾評論」的 Typecho 插件。預設在評論寫入資料庫**之前**直接拒絕，因此垃圾評論不會進入待審核列表，郵件通知插件自然也不會發信；被攔截的內容仍會留在後台的攔截記錄中，可隨時複查與還原。

> 本插件是 [NekoTypechoPlugins](https://github.com/moehoshio/NekoTypechoPlugins) 聚合專案中的子插件，對應倉庫中的 `CommentFilter` 目錄。

## 為什麼是「拒絕」而不是「待審核」

Typecho 的「待審核」只是把評論寫進資料庫並標記狀態，郵件通知類插件仍會在評論寫入後收到通知並發信。垃圾評論一多，信箱就會被淹沒，反而錯過真正需要處理的留言。

本插件掛載在 `Widget_Feedback` 的 `comment` 接口上，於 `insert()` 之前介入：

- **直接拒絕**（預設）：拋出異常中止本次提交，評論**不寫入資料庫**，`finishComment` 接口不會執行，任何郵件通知插件都不會發信。
- **標記為垃圾 / 標記為待審核**：仍然寫入資料庫，適合想保留全部樣本、或郵件插件本身已能區分狀態的情況。

## 功能特點

- 🔗 偵測多種連結寫法：
  - 帶協議：`https://example.com`、`ftp://…`
  - 無協議：`www.example.com`
  - 裸域名：`example.com`（採用頂級域名白名單，避免把 `README.md`、`app.py` 誤判為網域）
  - HTML 與 BBCode：`<a href="…">`、`[url=…]`
  - Markdown：`[文字](網址)`
- 🕵️ 還原混淆寫法後再檢查：`hxxp://`、`example[.]com`、`example(dot)com`、全形句點 `。`、零寬字元等
- 🔢 可設定允許的連結數量（依網域去重），`0` 表示完全禁止
- ✅ 網域白名單，本站網域自動放行；子網域一併放行
- 🔤 關鍵詞黑名單，支持正則表達式
- 🙋 豁免對象：管理員 / 所有已登入用戶；可另外豁免「曾有評論通過審核」的常客
- 🌐 拒絕提示支持多語言，依訪客語言自動選擇
- 📋 攔截記錄面板：查看、還原（待審或直接通過）、刪除、清空、自動清理過期記錄
- 🧪 後台內建規則測試框，可貼上一段文字檢查是否會被攔截

## 安裝

1. 從 [NekoTypechoPlugins](https://github.com/moehoshio/NekoTypechoPlugins) 倉庫下載 `CommentFilter` 目錄，並將文件夾重命名為 `NekoTypechoCommentFilter`
2. 上傳至 Typecho 的 `usr/plugins/` 目錄
3. 在後台「插件」頁面啟用 `NekoTypechoCommentFilter`

> 注意：插件文件夾名稱必須為 `NekoTypechoCommentFilter`，否則無法正常載入。

啟用後即以預設規則（禁止任何連結、直接拒絕、記錄攔截內容）開始工作，無需額外設置。

## 插件設置

| 設置項 | 預設 | 說明 |
| ------ | ---- | ---- |
| 攔截後的處理方式 | 直接拒絕 | 拒絕 / 標記為垃圾 / 標記為待審核 |
| 拒絕提示 | 中英日四語 | 多語言文本，見下節 |
| 拒絕提示的語言判定方式 | 自動 | 自動 / 瀏覽器語言 / 站點語言 |
| 允許的連結數量 | `0` | 依網域去重後的數量上限 |
| 檢查範圍 | 評論內容、暱稱 | 可另外勾選「個人主頁欄位」 |
| 偵測無協議的裸域名 | 是 | 關閉後只攔截帶協議與 `www.` 開頭的網址 |
| 偵測混淆連結 | 是 | 還原 `hxxp`、`[.]`、`(dot)`、全形符號後再檢查 |
| 攔截內容中的電子郵件地址 | 否 | 不影響郵箱欄位本身 |
| 網域白名單 | 空 | 每行一個，子網域自動放行 |
| 關鍵詞黑名單 | 空 | 每行一個，`/…/` 包裹時視為正則 |
| 豁免對象 | 所有已登入用戶 | 僅管理員與編輯 / 所有已登入用戶 / 不豁免 |
| 豁免曾通過審核的訪客 | 是 | 暱稱與郵箱皆與既往「已通過」評論一致時放行 |
| 記錄被攔截的評論 | 是 | 關閉後被攔截內容不可複查 |
| 攔截記錄保留天數 | `30` | `0` 表示永久保留 |

### 多語言拒絕提示

拒絕提示可以按語言分別撰寫，每行一種語言：

```
zh-CN: 评论中不允许包含链接，请去掉链接后重新提交。
zh-TW: 留言中不允許包含連結，請移除後再重新送出。
en: Links are not allowed in comments. Please remove them and try again.
ja: コメントにリンクを含めることはできません。
```

也支持直接貼上 JSON 物件：

```json
{ "zh-TW": "留言不允許包含連結", "en": "Links are not allowed" }
```

若整段文字不帶語言代碼，則作為缺省版本對所有語言生效。

**語言回退順序**：

1. 訪客語言精確匹配（`zh-TW` → `zh-TW`）
2. 中文簡繁互為缺省後備（繁體缺失時回退簡體，簡體缺失時回退繁體）
3. 同語系的其他地區變體（`pt-BR` → `pt-PT`）
4. 未標記語言的缺省版本
5. 英文（`en`）
6. 任意一個有內容的版本

訪客語言的判定順序為：網址參數 `?lang=` → Cookie `typecho_lang` → 瀏覽器 `Accept-Language` → 站點語言（僅在完全偵測不到訪客語言時使用）。

提示文字中可使用佔位符：

| 佔位符 | 說明 |
| ------ | ---- |
| `{sample}` | 命中的內容（如被攔截的網域） |
| `{max}` | 設置中允許的連結數量 |
| `{rule}` | 命中的規則類型（`link` / `keyword` / `mail`） |

## 攔截記錄

後台「管理 → 評論過濾」可以看到全部被攔截的評論，包含時間、暱稱、郵箱、IP、命中規則與完整內容。

- **還原待審**：把該評論寫回資料庫並置為待審核狀態
- **還原並通過**：直接寫回並置為已通過，同時修正文章的評論計數
- 還原**不會**觸發評論完成接口，因此不會補發郵件通知

右側的「規則測試」可貼上任意文字，立即看到是否會被攔截、命中了什麼、以及訪客會看到的提示文字，便於調整設置。

## 誤判與白名單

裸域名偵測會把 `socket.io`、`vue.dev` 這類技術名詞視為網域。若站點經常討論這類話題，可以：

- 把該網域加入**網域白名單**（推薦，仍能攔截其他連結）
- 或關閉**偵測無協議的裸域名**（只攔截 `http://`、`www.` 這類明確的連結）

被誤攔的評論一定會出現在攔截記錄中，可直接「還原並通過」，不會真的丟失。

## 主題／程式調用

```php
<?php
// 檢查一段文字是否會被攔截，命中時回傳 array(rule, field, sample)，否則 null
$hit = NekoTypechoCommentFilter_Plugin::inspectText('看看 http://spam.example.com');

// 取出文本中所有非白名單連結的網域
$links = NekoTypechoCommentFilter_Plugin::findLinks($text);

// 取得當前訪客語言下的拒絕提示
$message = NekoTypechoCommentFilter_Plugin::rejectMessage($hit);
?>
```

## 目錄結構

```
NekoTypechoCommentFilter/
├── Plugin.php                    # 主插件文件（過濾規則與掛載點）
├── Action.php                    # 後台操作處理（還原、刪除、清理）
├── I18n.php                      # 多語言解析與語言回退
├── manage-comment-filter.php     # 後台攔截記錄頁面
├── LICENSE
└── README.md
```

## 從舊版本升級

### 從 `TypechoCommentFilter` 舊命名升級

插件自有的類名與部署資料夾名已統一改為 `NekoTypechoCommentFilter` 前綴。
（Typecho 核心的類名與後台樣式，如 `Typecho_Db`、`typecho-option`、Cookie `typecho_lang`，維持原樣不受影響。）

升級步驟：

1. 在後台「插件」頁面**停用** `TypechoCommentFilter`。
2. 將 `usr/plugins/TypechoCommentFilter` 重新命名為 `usr/plugins/NekoTypechoCommentFilter`，並以新版檔案覆蓋。
3. 重新**啟用** `NekoTypechoCommentFilter`。

需要留意的變更：

- **攔截記錄不受影響**：`comment_filter_log` 資料表名稱未變，既有記錄全部保留，仍可複查與還原。
- **插件設置會重置**：Typecho 以插件名稱儲存設置，改名後請在設置頁重新配置一次（過濾規則、白名單、拒絕提示等）。
- **主題／程式調用需同步**：`TypechoCommentFilter_Plugin::` → `NekoTypechoCommentFilter_Plugin::`。

> 停用期間送出的評論不會被過濾，建議在低流量時段進行升級。

## 相容性

- Typecho 1.1 / 1.2
- 資料表使用 MySQL 語法（與本倉庫其他子插件一致）
- `I18n.php` 與 `Notice` 子插件中的同名文件內容一致（僅類名不同），以保證各子插件可獨立部署；修改時請同步

## 授權

MIT License
