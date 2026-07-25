# NekoTypechoPlugins

NekoTypechoPlugins 是一個 [Typecho](https://typecho.org/) 插件的聚合型專案。
倉庫中的每個子目錄代表一個獨立的子插件，可以單獨下載、安裝與使用。

## 插件列表

| 目錄 | 插件名稱 | 部署文件夾名 | 說明 |
| ---- | -------- | ------------ | ---- |
| [`Notice`](./Notice) | TypechoNotice | `TypechoNotice` | 通知與公告插件，支持多條通知管理、定時顯示、多語言版本、現代化 UI 以及用戶「不再顯示」記憶功能。 |
| [`Links`](./Links) | TypechoLinks | `TypechoLinks` | 功能完善的友情鏈接插件，支持匯入、優先級、分類、排序/隨機排序、隨機取得，以及前端側 RSS 擷取與展示。 |
| [`CommentFilter`](./CommentFilter) | TypechoCommentFilter | `TypechoCommentFilter` | 評論連結過濾插件，攔截含連結（含裸域名與混淆寫法）的垃圾評論。預設在寫入資料庫前直接拒絕，不產生待審核記錄、不觸發郵件通知，並提供攔截記錄以便複查與還原。 |

## 安裝方式

每個子插件都是標準的 Typecho 插件。通用安裝步驟如下：

1. 從本倉庫下載對應的子目錄（例如 `Notice` 或 `Links`）。
2. 將文件夾重命名為該插件的「部署文件夾名」（見上表，例如 `TypechoNotice`、`TypechoLinks`）。
   > Typecho 要求插件文件夾名稱與插件主類名前綴一致，因此重命名是必須的。
3. 將重命名後的文件夾上傳至 Typecho 的 `usr/plugins/` 目錄。
4. 在後台「插件」頁面啟用對應插件。

各插件的詳細功能、設置與使用方法，請參閱對應子目錄中的 `README.md`。

## 授權

本專案以 MIT License 釋出，詳見 [LICENSE](./LICENSE)。
