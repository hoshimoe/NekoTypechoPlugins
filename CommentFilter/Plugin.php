<?php
/**
 * Typecho 評論連結過濾插件
 *
 * 攔截含有連結的垃圾評論，支持帶協議網址、無協議網址、裸域名、混淆寫法
 * （hxxp、[.]、(dot)、全形句點等）、HTML 與 BBCode 連結。
 *
 * 預設在評論寫入資料庫之前直接拒絕，因此垃圾評論不會產生待審核記錄，
 * 也不會觸發郵件通知插件的提醒；被攔截的內容仍會留存在後台攔截記錄中，
 * 可隨時複查與還原，避免誤殺。
 *
 * @package NekoTypechoCommentFilter
 * @author Hoshi
 * @version 1.0.0
 * @link https://github.com/moehoshio/NekoTypechoPlugins
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (!class_exists('NekoTypechoCommentFilter_I18n', false)) {
    require_once dirname(__FILE__) . '/I18n.php';
}

class NekoTypechoCommentFilter_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 預設的拒絕提示（多語言）
     */
    const DEFAULT_REJECT_MESSAGE = "zh-CN: 评论中不允许包含链接，请去掉链接后重新提交。\n"
        . "zh-TW: 留言中不允許包含連結，請移除後再重新送出。\n"
        . "en: Links are not allowed in comments. Please remove them and try again.\n"
        . "ja: コメントにリンクを含めることはできません。リンクを削除して再度送信してください。";

    /**
     * 用於裸域名偵測的頂級域名列表
     *
     * 刻意採用白名單而非 `[a-z]{2,}`，以避免把 `README.md`、`app.py`
     * 這類寫法誤判為域名。
     */
    private static $tlds = array(
        // 通用頂級域名
        'com', 'net', 'org', 'info', 'biz', 'pro', 'name', 'mobi', 'asia', 'edu', 'gov', 'int',
        // 垃圾評論常見的新頂級域名
        'xyz', 'top', 'club', 'online', 'site', 'shop', 'store', 'live', 'vip', 'icu', 'buzz',
        'fun', 'space', 'website', 'tech', 'app', 'dev', 'ai', 'art', 'blog', 'wiki', 'news',
        'email', 'host', 'cloud', 'digital', 'network', 'agency', 'solutions', 'link', 'click',
        'best', 'cyou', 'sbs', 'rest', 'bond', 'cfd', 'today', 'world', 'life', 'plus', 'red',
        'ren', 'wang', 'xin', 'ltd', 'group', 'team', 'work', 'design', 'studio', 'media', 'zone',
        'run', 'fit', 'men', 'win', 'loan', 'date', 'racing', 'stream', 'download', 'science',
        // 常見國家與地區頂級域名
        'cn', 'tw', 'hk', 'mo', 'jp', 'kr', 'sg', 'my', 'th', 'vn', 'id', 'ph', 'in', 'ru', 'ua',
        'de', 'fr', 'es', 'pt', 'it', 'nl', 'pl', 'se', 'no', 'fi', 'dk', 'ch', 'at', 'cz', 'be',
        'gr', 'hu', 'ro', 'bg', 'tr', 'il', 'ir', 'sa', 'ae', 'eg', 'za', 'ng', 'ke', 'br', 'mx',
        'ar', 'cl', 'co', 'pe', 've', 'us', 'uk', 'ca', 'au', 'nz', 'io', 'me', 'cc', 'tv', 'la',
        'gg', 'ly', 'to', 'ws', 'nu', 'fm', 'pw', 'su', 'tk', 'ml', 'ga', 'cf'
    );

    /**
     * 激活插件
     */
    public static function activate()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}comment_filter_log` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `cid` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            `author` VARCHAR(200) NOT NULL DEFAULT '',
            `mail` VARCHAR(200) NOT NULL DEFAULT '',
            `url` VARCHAR(255) NOT NULL DEFAULT '',
            `ip` VARCHAR(64) NOT NULL DEFAULT '',
            `agent` VARCHAR(511) NOT NULL DEFAULT '',
            `text` TEXT NOT NULL,
            `rule` VARCHAR(30) NOT NULL DEFAULT '',
            `field` VARCHAR(30) NOT NULL DEFAULT '',
            `sample` VARCHAR(255) NOT NULL DEFAULT '',
            `handled` VARCHAR(20) NOT NULL DEFAULT 'reject',
            `state` VARCHAR(20) NOT NULL DEFAULT 'logged',
            `raw` TEXT NOT NULL,
            `created` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `created` (`created`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        Helper::addPanel(3, 'NekoTypechoCommentFilter/manage-comment-filter.php', '評論過濾', '查看與還原被攔截的評論', 'administrator');
        Helper::addAction('comment-filter', 'NekoTypechoCommentFilter_Action');

        Typecho_Plugin::factory('Widget_Feedback')->comment = array('NekoTypechoCommentFilter_Plugin', 'filter');

        return _t('插件已激活，預設會直接拒絕含連結的評論；可前往「評論過濾」頁面查看攔截記錄。');
    }

    /**
     * 禁用插件
     *
     * 為避免誤刪攔截記錄，停用時不會刪除資料表。
     * 如需徹底清除，請手動刪除 `comment_filter_log` 資料表。
     */
    public static function deactivate()
    {
        Helper::removePanel(3, 'NekoTypechoCommentFilter/manage-comment-filter.php');
        Helper::removeAction('comment-filter');

        return _t('插件已禁用，攔截記錄仍保留（如需清除請手動刪除 comment_filter_log 資料表）。');
    }

    /**
     * 插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $action = new Typecho_Widget_Helper_Form_Element_Radio(
            'action',
            array(
                'reject'  => '直接拒絕（不寫入資料庫，不觸發郵件通知）',
                'spam'    => '標記為垃圾（寫入資料庫，於「垃圾」列表中）',
                'waiting' => '標記為待審核（寫入資料庫，於「待審核」列表中）'
            ),
            'reject',
            _t('攔截後的處理方式'),
            _t('「直接拒絕」時評論不會進入資料庫，因此不會產生待審核記錄，郵件通知插件也不會發信；被攔截的內容仍可在攔截記錄中查看與還原。')
        );
        $form->addInput($action);

        $rejectMessage = new Typecho_Widget_Helper_Form_Element_Textarea(
            'rejectMessage',
            NULL,
            self::DEFAULT_REJECT_MESSAGE,
            _t('拒絕提示（支持多語言）'),
            _t('每行一種語言，格式為 <code>語言代碼: 提示文字</code>，例如 <code>zh-TW: 留言不允許包含連結</code>；也可貼上 JSON 物件。'
                . '<br />未帶語言代碼時，整段文字對所有語言生效。'
                . '<br />回退順序：訪客語言 → 中文簡繁互為後備 → 缺省版本 → 英文 → 任意有內容的版本。'
                . '<br />可使用佔位符 <code>{sample}</code>（命中的內容）與 <code>{max}</code>（允許的連結數量）。')
        );
        $form->addInput($rejectMessage);

        $langSource = new Typecho_Widget_Helper_Form_Element_Select(
            'langSource',
            array(
                'auto'    => '自動（網址 lang 參數 → Cookie → 瀏覽器語言 → 站點語言）',
                'browser' => '瀏覽器語言（Accept-Language）',
                'site'    => '站點語言'
            ),
            'auto',
            _t('拒絕提示的語言判定方式')
        );
        $form->addInput($langSource);

        $maxLinks = new Typecho_Widget_Helper_Form_Element_Text(
            'maxLinks',
            NULL,
            '0',
            _t('允許的連結數量'),
            _t('評論中允許出現的連結數量（依網域去重計算），<code>0</code> 表示完全禁止。')
        );
        $form->addInput($maxLinks);

        $checkFields = new Typecho_Widget_Helper_Form_Element_Checkbox(
            'checkFields',
            array(
                'text'   => '評論內容',
                'author' => '暱稱',
                'url'    => '個人主頁欄位'
            ),
            array('text', 'author'),
            _t('檢查範圍'),
            _t('「個人主頁欄位」本就用於填寫網址，勾選後只要填寫了非白名單網址即會被攔截，請斟酌啟用。')
        );
        $form->addInput($checkFields);

        $detectBare = new Typecho_Widget_Helper_Form_Element_Radio(
            'detectBare',
            array('1' => '是', '0' => '否'),
            '1',
            _t('偵測無協議的裸域名'),
            _t('例如 <code>example.com</code>、<code>www.example.com</code>。'
                . '可能會把 <code>socket.io</code> 這類技術名詞誤判為連結，可將其加入白名單或關閉此項。')
        );
        $form->addInput($detectBare);

        $detectObfuscated = new Typecho_Widget_Helper_Form_Element_Radio(
            'detectObfuscated',
            array('1' => '是', '0' => '否'),
            '1',
            _t('偵測混淆連結'),
            _t('還原 <code>hxxp://</code>、<code>example[.]com</code>、<code>example(dot)com</code>、全形句點與零寬字元等寫法後再行檢查。')
        );
        $form->addInput($detectObfuscated);

        $detectMail = new Typecho_Widget_Helper_Form_Element_Radio(
            'detectMail',
            array('1' => '是', '0' => '否'),
            '0',
            _t('攔截內容中的電子郵件地址'),
            _t('啟用後，評論內容中出現電子郵件地址也會被攔截（不影響填寫郵箱欄位）。')
        );
        $form->addInput($detectMail);

        $whitelistDomains = new Typecho_Widget_Helper_Form_Element_Textarea(
            'whitelistDomains',
            NULL,
            '',
            _t('網域白名單'),
            _t('每行一個網域，子網域自動放行（填寫 <code>example.com</code> 時 <code>blog.example.com</code> 亦放行）。本站網域已自動放行。')
        );
        $form->addInput($whitelistDomains);

        $keywords = new Typecho_Widget_Helper_Form_Element_Textarea(
            'keywords',
            NULL,
            '',
            _t('關鍵詞黑名單'),
            _t('每行一個關鍵詞，命中即攔截（不分大小寫）；以 <code>/</code> 包裹時視為正則表達式，例如 <code>/微信\\s*[:：]/u</code>。')
        );
        $form->addInput($keywords);

        $exempt = new Typecho_Widget_Helper_Form_Element_Select(
            'exempt',
            array(
                'admin' => '僅管理員與編輯',
                'login' => '所有已登入用戶',
                'none'  => '不豁免任何人'
            ),
            'login',
            _t('豁免對象'),
            _t('豁免對象提交的評論不受過濾。')
        );
        $form->addInput($exempt);

        $exemptApproved = new Typecho_Widget_Helper_Form_Element_Radio(
            'exemptApproved',
            array('1' => '是', '0' => '否'),
            '1',
            _t('豁免曾通過審核的訪客'),
            _t('暱稱與郵箱皆與既往「已通過」評論一致時放行，讓常客可以正常貼連結。')
        );
        $form->addInput($exemptApproved);

        $logEnable = new Typecho_Widget_Helper_Form_Element_Radio(
            'logEnable',
            array('1' => '是', '0' => '否'),
            '1',
            _t('記錄被攔截的評論'),
            _t('關閉後被攔截的評論將直接丟棄，無法在後台複查與還原。')
        );
        $form->addInput($logEnable);

        $logKeepDays = new Typecho_Widget_Helper_Form_Element_Text(
            'logKeepDays',
            NULL,
            '30',
            _t('攔截記錄保留天數'),
            _t('超過天數的記錄會自動清理，<code>0</code> 表示永久保留。')
        );
        $form->addInput($logKeepDays);
    }

    /**
     * 個人用戶配置
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 評論過濾入口（掛載於 Widget_Feedback 的 comment 接口）
     *
     * @param array $comment 評論資料
     * @param mixed $content 文章物件（各版本傳入型別不同，此處不依賴）
     * @return array
     * @throws Typecho_Widget_Exception 「直接拒絕」模式下中止本次評論
     */
    public static function filter($comment, $content = null)
    {
        $config = self::pluginConfig();

        if (self::isExempt($comment, $config)) {
            return $comment;
        }

        $hit = self::inspect($comment, $config);
        if (null === $hit) {
            return $comment;
        }

        $action = self::option($config, 'action', 'reject');
        if (!in_array($action, array('reject', 'spam', 'waiting'), true)) {
            $action = 'reject';
        }

        self::log($comment, $hit, $action, $config);

        if ('reject' !== $action) {
            $comment['status'] = $action;
            return $comment;
        }

        throw new Typecho_Widget_Exception(self::rejectMessage($hit, $config));
    }

    /**
     * 檢查一則評論是否命中規則
     *
     * @param array $comment
     * @param Typecho_Config|null $config
     * @return array|null 命中時回傳 array(rule, field, sample)，否則回傳 null
     */
    public static function inspect($comment, $config = null)
    {
        $config = $config ?: self::pluginConfig();

        $fields = self::option($config, 'checkFields', array('text', 'author'));
        if (!is_array($fields)) {
            $fields = array($fields);
        }

        $maxLinks = max(0, intval(self::option($config, 'maxLinks', 0)));

        foreach (array('text', 'author', 'url') as $field) {
            if (!in_array($field, $fields, true)) {
                continue;
            }

            $value = isset($comment[$field]) ? (string) $comment[$field] : '';
            if ('' === $value) {
                continue;
            }

            // 個人主頁欄位本身就是網址，只要不在白名單即視為一個連結
            if ('url' === $field) {
                $host = self::hostOf($value);
                if ('' !== $host && !self::isWhitelisted($host, $config)) {
                    return array('rule' => 'link', 'field' => 'url', 'sample' => $host);
                }
                continue;
            }

            $hit = self::inspectText($value, $config, $maxLinks);
            if (null !== $hit) {
                $hit['field'] = $field;
                return $hit;
            }
        }

        return null;
    }

    /**
     * 檢查一段文本是否命中規則
     *
     * @param string $text
     * @param Typecho_Config|null $config
     * @param int|null $maxLinks
     * @return array|null
     */
    public static function inspectText($text, $config = null, $maxLinks = null)
    {
        $config = $config ?: self::pluginConfig();

        if (null === $maxLinks) {
            $maxLinks = max(0, intval(self::option($config, 'maxLinks', 0)));
        }

        $normalized = self::deobfuscate($text, $config);

        // 關鍵詞同時比對原文與還原混淆後的文本
        $keyword = self::matchKeyword($text, $config);
        if (null === $keyword && $normalized !== $text) {
            $keyword = self::matchKeyword($normalized, $config);
        }
        if (null !== $keyword) {
            return array('rule' => 'keyword', 'field' => 'text', 'sample' => $keyword);
        }

        $links = self::findLinks($text, $config);
        if (count($links) > $maxLinks) {
            $samples = array_slice($links, 0, 3);
            return array('rule' => 'link', 'field' => 'text', 'sample' => implode(', ', $samples));
        }

        if (self::option($config, 'detectMail', '0') === '1'
            && preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', $normalized, $matches)) {
            return array('rule' => 'mail', 'field' => 'text', 'sample' => $matches[0]);
        }

        return null;
    }

    /**
     * 從文本中找出所有非白名單連結（依網域去重）
     *
     * @param string $text
     * @param Typecho_Config|null $config
     * @return array
     */
    public static function findLinks($text, $config = null)
    {
        $config = $config ?: self::pluginConfig();

        $normalized = self::deobfuscate($text, $config);
        $candidates = array();

        // HTML 與 BBCode 連結：直接取出 href / url 屬性
        if (preg_match_all('#<a\s[^>]*href\s*=\s*["\']?([^"\'>\s]+)#i', $normalized, $matches)) {
            $candidates = array_merge($candidates, $matches[1]);
        }
        if (preg_match_all('#\[url\s*=\s*["\']?([^"\'\]\s]+)#i', $normalized, $matches)) {
            $candidates = array_merge($candidates, $matches[1]);
        }

        // 帶協議的網址
        if (preg_match_all('#\b(?:https?|ftps?|sftp)://[^\s<>"\'()\[\]，。、；]+#i', $normalized, $matches)) {
            $candidates = array_merge($candidates, $matches[0]);
        }

        if (self::option($config, 'detectBare', '1') === '1') {
            // 無協議的 www 網址
            if (preg_match_all('#(?<![\w.@-])www\.[a-z0-9-]+(?:\.[a-z0-9-]+)+#i', $normalized, $matches)) {
                $candidates = array_merge($candidates, $matches[0]);
            }

            // 裸域名（排除電子郵件地址中的網域）
            $pattern = '#(?<![\w.@-])(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+(?:'
                . implode('|', self::$tlds) . ')(?![a-z0-9-])#i';
            if (preg_match_all($pattern, $normalized, $matches)) {
                $candidates = array_merge($candidates, $matches[0]);
            }
        }

        $links = array();
        foreach ($candidates as $candidate) {
            $host = self::hostOf($candidate);
            $key = '' === $host ? strtolower(trim($candidate)) : $host;

            if ('' === $key || isset($links[$key])) {
                continue;
            }

            if ('' !== $host && self::isWhitelisted($host, $config)) {
                continue;
            }

            $links[$key] = $key;
        }

        return array_values($links);
    }

    /**
     * 還原常見的連結混淆寫法
     *
     * @param string $text
     * @param Typecho_Config|null $config
     * @return string
     */
    public static function deobfuscate($text, $config = null)
    {
        $config = $config ?: self::pluginConfig();
        $text = (string) $text;

        if (self::option($config, 'detectObfuscated', '1') !== '1') {
            return $text;
        }

        // 零寬與方向控制字元
        $text = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]/u', '', $text);

        // 全形符號
        $text = str_replace(
            array('。', '．', '｡', '·', '｜', '：', '／', '＠', '－'),
            array('.', '.', '.', '.', '|', ':', '/', '@', '-'),
            $text
        );

        // hxxp:// 之類的協議變體（不動真正的 http/https）
        $text = preg_replace('/\bh[x*_]{2}(ps?)(?=\s*:)/i', 'htt$1', $text);

        // example[.]com、example(dot)com、example 点 com
        $text = preg_replace('/[\(\[\{<]\s*(?:\.|dot|点|點)\s*[\)\]\}>]/iu', '.', $text);
        $text = preg_replace('/\s+(?:dot|点|點)\s+/iu', '.', $text);
        $text = preg_replace('/[\(\[\{<]\s*(?:@|at|艾特)\s*[\)\]\}>]/iu', '@', $text);

        // 協議與網域之間被插入空白
        $text = preg_replace('#(https?)\s*:\s*/\s*/\s*#i', '$1://', $text);

        return $text;
    }

    /**
     * 從候選字串中取出主機名
     *
     * @param string $candidate
     * @return string 取不到時回傳空字串
     */
    public static function hostOf($candidate)
    {
        $candidate = trim((string) $candidate);
        if ('' === $candidate) {
            return '';
        }

        $candidate = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $candidate);
        $candidate = preg_split('#[/?\#\\\\]#', $candidate);
        $candidate = $candidate[0];

        // 去除使用者資訊與埠號
        if (false !== strpos($candidate, '@')) {
            $parts = explode('@', $candidate);
            $candidate = array_pop($parts);
        }
        $candidate = preg_replace('/:\d+$/', '', $candidate);
        $candidate = strtolower(trim($candidate, ".\t\n\r\0\x0B "));

        return preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $candidate) ? $candidate : '';
    }

    /**
     * 判斷主機名是否在白名單內（含本站網域）
     *
     * @param string $host
     * @param Typecho_Config|null $config
     * @return bool
     */
    public static function isWhitelisted($host, $config = null)
    {
        $config = $config ?: self::pluginConfig();

        $host = strtolower(trim((string) $host));
        if ('' === $host) {
            return false;
        }

        foreach (self::whitelist($config) as $entry) {
            if ($host === $entry || substr($host, -strlen('.' . $entry)) === '.' . $entry) {
                return true;
            }
        }

        return false;
    }

    /**
     * 取得白名單網域列表
     *
     * @param Typecho_Config|null $config
     * @return array
     */
    public static function whitelist($config = null)
    {
        $config = $config ?: self::pluginConfig();

        $entries = array();

        // 本站網域自動放行
        $siteHost = self::hostOf(Helper::options()->siteUrl);
        if ('' !== $siteHost) {
            $entries[] = $siteHost;
        }

        foreach (preg_split('/\r\n|\r|\n/', (string) self::option($config, 'whitelistDomains', '')) as $line) {
            $line = trim($line);
            if ('' === $line || 0 === strpos($line, '#')) {
                continue;
            }

            $host = self::hostOf($line);
            if ('' !== $host) {
                $entries[] = $host;
            }
        }

        return array_values(array_unique($entries));
    }

    /**
     * 比對關鍵詞黑名單
     *
     * @param string $text
     * @param Typecho_Config|null $config
     * @return string|null 命中的關鍵詞
     */
    public static function matchKeyword($text, $config = null)
    {
        $config = $config ?: self::pluginConfig();

        $keywords = (string) self::option($config, 'keywords', '');
        if ('' === trim($keywords)) {
            return null;
        }

        foreach (preg_split('/\r\n|\r|\n/', $keywords) as $keyword) {
            $keyword = trim($keyword);
            if ('' === $keyword || 0 === strpos($keyword, '#')) {
                continue;
            }

            // 以 / 包裹時視為正則表達式
            if (strlen($keyword) > 2 && '/' === $keyword[0] && false !== strrpos($keyword, '/', 1)) {
                $matched = @preg_match($keyword, $text);
                if (1 === $matched) {
                    return $keyword;
                }
                if (false !== $matched) {
                    continue;
                }
            }

            if (false !== stripos($text, $keyword)) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * 判斷本次評論是否豁免過濾
     *
     * @param array $comment
     * @param Typecho_Config|null $config
     * @return bool
     */
    public static function isExempt($comment, $config = null)
    {
        $config = $config ?: self::pluginConfig();

        $exempt = self::option($config, 'exempt', 'login');

        if ('none' !== $exempt) {
            try {
                $user = Typecho_Widget::widget('Widget_User');
                if ($user->hasLogin()) {
                    if ('login' === $exempt || $user->pass('editor', true)) {
                        return true;
                    }
                }
            } catch (Exception $e) {
                // 取不到用戶資訊時視為未登入，繼續後續檢查
            }
        }

        if (self::option($config, 'exemptApproved', '1') === '1') {
            $author = isset($comment['author']) ? trim((string) $comment['author']) : '';
            $mail = isset($comment['mail']) ? trim((string) $comment['mail']) : '';

            if ('' !== $author && '' !== $mail) {
                $db = Typecho_Db::get();
                $row = $db->fetchRow($db->select('coid')->from('table.comments')
                    ->where('author = ? AND mail = ? AND status = ?', $author, $mail, 'approved')
                    ->limit(1));

                if (!empty($row)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 產生拒絕提示
     *
     * @param array $hit
     * @param Typecho_Config|null $config
     * @return string
     */
    public static function rejectMessage($hit, $config = null)
    {
        $config = $config ?: self::pluginConfig();

        $raw = (string) self::option($config, 'rejectMessage', self::DEFAULT_REJECT_MESSAGE);
        $langs = NekoTypechoCommentFilter_I18n::detect(self::option($config, 'langSource', 'auto'));

        $message = NekoTypechoCommentFilter_I18n::text($raw, $langs);
        if ('' === $message) {
            $message = NekoTypechoCommentFilter_I18n::text(self::DEFAULT_REJECT_MESSAGE, $langs);
        }

        $sample = isset($hit['sample']) ? (string) $hit['sample'] : '';
        $sample = trim(preg_replace('/[<>"\'\\\\]+/', '', strip_tags($sample)));
        if (strlen($sample) > 80) {
            $sample = substr($sample, 0, 80) . '…';
        }

        return str_replace(
            array('{sample}', '{max}', '{rule}'),
            array($sample, intval(self::option($config, 'maxLinks', 0)), isset($hit['rule']) ? $hit['rule'] : ''),
            $message
        );
    }

    /**
     * 記錄被攔截的評論
     *
     * @param array $comment
     * @param array $hit
     * @param string $action
     * @param Typecho_Config|null $config
     */
    public static function log($comment, $hit, $action, $config = null)
    {
        $config = $config ?: self::pluginConfig();

        if (self::option($config, 'logEnable', '1') !== '1') {
            return;
        }

        $db = Typecho_Db::get();

        try {
            $db->query($db->insert('table.comment_filter_log')->rows(array(
                'cid'     => isset($comment['cid']) ? intval($comment['cid']) : 0,
                'author'  => self::truncate(isset($comment['author']) ? $comment['author'] : '', 200),
                'mail'    => self::truncate(isset($comment['mail']) ? $comment['mail'] : '', 200),
                'url'     => self::truncate(isset($comment['url']) ? $comment['url'] : '', 255),
                'ip'      => self::truncate(isset($comment['ip']) ? $comment['ip'] : '', 64),
                'agent'   => self::truncate(isset($comment['agent']) ? $comment['agent'] : '', 511),
                'text'    => isset($comment['text']) ? (string) $comment['text'] : '',
                'rule'    => self::truncate(isset($hit['rule']) ? $hit['rule'] : '', 30),
                'field'   => self::truncate(isset($hit['field']) ? $hit['field'] : '', 30),
                'sample'  => self::truncate(isset($hit['sample']) ? $hit['sample'] : '', 255),
                'handled' => self::truncate($action, 20),
                'state'   => 'logged',
                'raw'     => json_encode($comment),
                'created' => time()
            )));
        } catch (Exception $e) {
            // 記錄失敗不應影響過濾本身
            return;
        }

        self::cleanLog($config);
    }

    /**
     * 清理過期的攔截記錄
     *
     * @param Typecho_Config|null $config
     * @param bool $force 略過抽樣，強制清理
     */
    public static function cleanLog($config = null, $force = false)
    {
        $config = $config ?: self::pluginConfig();

        $days = intval(self::option($config, 'logKeepDays', 30));
        if ($days <= 0) {
            return;
        }

        // 攔截頻繁時無需每次清理
        if (!$force && 1 !== mt_rand(1, 20)) {
            return;
        }

        $db = Typecho_Db::get();

        try {
            $db->query($db->delete('table.comment_filter_log')
                ->where('created < ?', time() - $days * 86400));
        } catch (Exception $e) {
            // 忽略清理失敗
        }
    }

    /**
     * 取得插件配置
     *
     * @return Typecho_Config|null
     */
    public static function pluginConfig()
    {
        try {
            return Helper::options()->plugin('NekoTypechoCommentFilter');
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * 讀取配置項，未設定或為空時回傳預設值
     *
     * @param Typecho_Config|null $config
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public static function option($config, $name, $default = '')
    {
        if (empty($config)) {
            return $default;
        }

        $value = $config->$name;

        return (null === $value || '' === $value) ? $default : $value;
    }

    /**
     * 依位元組截斷字串，避免超出欄位長度
     *
     * @param string $value
     * @param int $length
     * @return string
     */
    private static function truncate($value, $length)
    {
        $value = (string) $value;

        if (strlen($value) <= $length) {
            return $value;
        }

        // mb_strcut 依位元組截斷且不會切壞多位元組字元
        if (function_exists('mb_strcut')) {
            return mb_strcut($value, 0, $length, 'UTF-8');
        }

        $value = substr($value, 0, $length);

        // 移除截斷後殘缺的多位元組字元
        return preg_replace('/[\xC0-\xFD][\x80-\xBF]*$/', '', $value);
    }
}
