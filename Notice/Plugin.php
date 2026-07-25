<?php
/**
 * Typecho 通知與公告插件
 *
 * @package TypechoNotice
 * @author Hoshi
 * @version 1.1.0
 * @link https://github.com/moehoshio/NekoTypechoPlugins
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

if (!class_exists('TypechoNotice_I18n', false)) {
    require_once dirname(__FILE__) . '/I18n.php';
}

class TypechoNotice_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件
     */
    public static function activate()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}notice` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(200) NOT NULL DEFAULT '',
            `content` TEXT NOT NULL,
            `type` VARCHAR(20) NOT NULL DEFAULT 'info',
            `visible` TINYINT(1) NOT NULL DEFAULT 1,
            `start_time` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            `end_time` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            `created` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            `modified` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            `order_num` INT(10) NOT NULL DEFAULT 0,
            `default_lang` VARCHAR(20) NOT NULL DEFAULT '',
            `i18n` TEXT,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // 由舊版本升級而來時補上多語言欄位
        self::upgradeTable($db, $prefix);

        Helper::addPanel(3, 'TypechoNotice/manage-notice.php', '通知管理', '管理通知與公告', 'administrator');
        Helper::addAction('notice-edit', 'TypechoNotice_Action');
        
        Typecho_Plugin::factory('Widget_Archive')->header = array('TypechoNotice_Plugin', 'header');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('TypechoNotice_Plugin', 'footer');
        
        return _t('插件已激活，請前往通知管理頁面添加通知。');
    }

    /**
     * 禁用插件
     *
     * 升級插件時通常需要「停用再啟用」以套用新的資料表欄位，
     * 因此停用時保留資料表，避免通知內容被清空。
     * 如需徹底清除，請手動刪除 `notice` 資料表。
     */
    public static function deactivate()
    {
        Helper::removePanel(3, 'TypechoNotice/manage-notice.php');
        Helper::removeAction('notice-edit');

        return _t('插件已禁用，通知數據仍保留（如需清除請手動刪除 notice 資料表）。');
    }

    /**
     * 插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $position = new Typecho_Widget_Helper_Form_Element_Select(
            'position',
            array(
                'top' => '頂部固定',
                'bottom' => '底部固定',
                'center' => '居中彈窗'
            ),
            'top',
            _t('通知顯示位置'),
            _t('選擇通知在前端的顯示位置')
        );
        $form->addInput($position);

        $theme = new Typecho_Widget_Helper_Form_Element_Select(
            'theme',
            array(
                'light' => '淺色主題',
                'dark' => '深色主題',
                'auto' => '跟隨系統'
            ),
            'auto',
            _t('通知主題'),
            _t('選擇通知的顏色主題')
        );
        $form->addInput($theme);

        $customCss = new Typecho_Widget_Helper_Form_Element_Textarea(
            'customCss',
            NULL,
            '',
            _t('自定義CSS'),
            _t('添加自定義CSS樣式來覆蓋默認樣式')
        );
        $form->addInput($customCss);

        $cookieDays = new Typecho_Widget_Helper_Form_Element_Text(
            'cookieDays',
            NULL,
            '7',
            _t('Cookie有效天數'),
            _t('用戶點擊"不再顯示"後，Cookie保存的天數')
        );
        $form->addInput($cookieDays);

        $langSource = new Typecho_Widget_Helper_Form_Element_Select(
            'langSource',
            array(
                'auto'    => '自動（網址 lang 參數 → Cookie → 瀏覽器語言 → 站點語言）',
                'browser' => '瀏覽器語言（Accept-Language）',
                'site'    => '站點語言（所有訪客看到同一種語言）'
            ),
            'auto',
            _t('多語言判定方式'),
            _t('決定通知以何種語言顯示。<br />回退順序：訪客語言 → 中文簡繁互為後備 → 英文 → 任意一個有內容的版本。'
                . '<br />若站點啟用了整頁快取，建議選擇「站點語言」，避免不同語言的訪客拿到同一份快取。')
        );
        $form->addInput($langSource);
    }

    /**
     * 個人用戶配置
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form)
    {
    }

    /**
     * 輸出頭部CSS
     */
    public static function header()
    {
        $options = Helper::options();
        $pluginOptions = $options->plugin('TypechoNotice');
        $cssUrl = Typecho_Common::url('TypechoNotice/assets/notice.css', $options->pluginUrl);
        
        echo '<link rel="stylesheet" href="' . $cssUrl . '" />' . "\n";
        
        if (!empty($pluginOptions->customCss)) {
            echo '<style>' . $pluginOptions->customCss . '</style>' . "\n";
        }
    }

    /**
     * 輸出底部JS及通知HTML
     */
    public static function footer()
    {
        $options = Helper::options();
        $pluginOptions = $options->plugin('TypechoNotice');

        $notices = self::getNotices();

        if (empty($notices)) {
            return;
        }

        $position = $pluginOptions->position ?? 'top';
        $theme = $pluginOptions->theme ?? 'auto';
        $cookieDays = intval($pluginOptions->cookieDays ?? 7);

        echo '<div id="typecho-notice-container" class="typecho-notice-' . $position . '" data-theme="' . $theme . '">' . "\n";

        foreach ($notices as $notice) {
            $noticeId = 'notice-' . $notice['id'];
            $lang = empty($notice['lang']) ? '' : ' lang="' . htmlspecialchars($notice['lang']) . '"';
            echo '<div class="typecho-notice-item typecho-notice-type-' . htmlspecialchars($notice['type']) . '" data-notice-id="' . $notice['id'] . '" id="' . $noticeId . '"' . $lang . '>' . "\n";
            echo '  <div class="typecho-notice-content">' . "\n";
            if (!empty($notice['title'])) {
                echo '    <div class="typecho-notice-title">' . htmlspecialchars($notice['title']) . '</div>' . "\n";
            }
            echo '    <div class="typecho-notice-text">' . $notice['content'] . '</div>' . "\n";
            echo '  </div>' . "\n";
            echo '  <div class="typecho-notice-actions">' . "\n";
            echo '    <button class="typecho-notice-close" data-notice-id="' . $notice['id'] . '" title="關閉">×</button>' . "\n";
            echo '    <button class="typecho-notice-dismiss" data-notice-id="' . $notice['id'] . '" title="不再顯示">不再顯示</button>' . "\n";
            echo '  </div>' . "\n";
            echo '</div>' . "\n";
        }
        
        echo '</div>' . "\n";
        
        $jsUrl = Typecho_Common::url('TypechoNotice/assets/notice.js', $options->pluginUrl);
        echo '<script>var typechoNoticeCookieDays = ' . $cookieDays . ';</script>' . "\n";
        echo '<script src="' . $jsUrl . '"></script>' . "\n";
    }

    /**
     * 獲取當前可見通知（供主題調用）
     *
     * @param bool $localize 是否依訪客語言取出對應的標題與內容
     * @return array
     */
    public static function getNotices($localize = true)
    {
        $now = time();
        $db = Typecho_Db::get();

        $notices = $db->fetchAll($db->select()->from('table.notice')
            ->where('visible = 1')
            ->where('start_time = 0 OR start_time <= ?', $now)
            ->where('end_time = 0 OR end_time >= ?', $now)
            ->order('order_num', Typecho_Db::SORT_ASC)
            ->order('created', Typecho_Db::SORT_DESC));

        if (!$localize) {
            return $notices;
        }

        $langs = TypechoNotice_I18n::detect(self::langSource());

        foreach ($notices as &$notice) {
            $notice = self::localize($notice, $langs);
        }
        unset($notice);

        return $notices;
    }

    /**
     * 依語言偏好取出通知的標題與內容
     *
     * 標題與內容分別回退，因此只翻譯了內容、未填標題的版本仍會沿用基礎版本的標題。
     *
     * @param array $notice 資料表中的一列
     * @param array|null $langs 語言偏好，null 表示自動偵測
     * @return array 標題與內容已替換為對應語言，並附上 lang 欄位
     */
    public static function localize($notice, $langs = null)
    {
        if (null === $langs) {
            $langs = TypechoNotice_I18n::detect(self::langSource());
        }

        $baseLang = TypechoNotice_I18n::normalize(isset($notice['default_lang']) ? $notice['default_lang'] : '');
        if ('' === $baseLang || TypechoNotice_I18n::DEFAULT_KEY === $baseLang) {
            $baseLang = TypechoNotice_I18n::normalize(TypechoNotice_I18n::siteLang());
        }
        if ('' === $baseLang) {
            // 站點語言也無法辨識時，基礎版本作為不分語言的缺省版本
            $baseLang = TypechoNotice_I18n::DEFAULT_KEY;
        }

        // 基礎版本置於首位，使其成為「任意有內容的版本」時的首選
        $titles = array();
        $contents = array();

        if ('' !== trim((string) (isset($notice['title']) ? $notice['title'] : ''))) {
            $titles[$baseLang] = $notice['title'];
        }
        if ('' !== trim((string) (isset($notice['content']) ? $notice['content'] : ''))) {
            $contents[$baseLang] = $notice['content'];
        }

        foreach (self::translations($notice) as $lang => $item) {
            if (!isset($titles[$lang]) && '' !== trim($item['title'])) {
                $titles[$lang] = $item['title'];
            }
            if (!isset($contents[$lang]) && '' !== trim($item['content'])) {
                $contents[$lang] = $item['content'];
            }
        }

        // 與內容使用相同的鍵集合，確保回退到同一個語言版本
        $langMap = array();
        foreach ($contents as $lang => $value) {
            $langMap[$lang] = $lang;
        }

        $title = TypechoNotice_I18n::resolve($titles, $langs);
        $content = TypechoNotice_I18n::resolve($contents, $langs);

        $notice['title'] = null === $title ? '' : $title;
        $notice['content'] = null === $content ? '' : $content;
        $notice['lang'] = TypechoNotice_I18n::resolve($langMap, $langs);

        return $notice;
    }

    /**
     * 解出通知的翻譯版本
     *
     * @param array $notice
     * @return array 語言標籤 => array(title, content)
     */
    public static function translations($notice)
    {
        $raw = isset($notice['i18n']) ? (string) $notice['i18n'] : '';
        if ('' === trim($raw)) {
            return array();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array();
        }

        $result = array();
        foreach ($decoded as $lang => $item) {
            $lang = TypechoNotice_I18n::normalize($lang);
            if ('' === $lang || TypechoNotice_I18n::DEFAULT_KEY === $lang || !is_array($item)) {
                continue;
            }

            $result[$lang] = array(
                'title'   => isset($item['title']) ? (string) $item['title'] : '',
                'content' => isset($item['content']) ? (string) $item['content'] : ''
            );
        }

        return $result;
    }

    /**
     * 取得語言判定方式
     *
     * @return string
     */
    public static function langSource()
    {
        try {
            $pluginOptions = Helper::options()->plugin('TypechoNotice');
            $source = $pluginOptions ? (string) $pluginOptions->langSource : '';
        } catch (Exception $e) {
            $source = '';
        }

        return in_array($source, array('auto', 'browser', 'site'), true) ? $source : 'auto';
    }

    /**
     * 資料表是否已具備多語言欄位
     *
     * @param bool $refresh 重新查詢而非使用快取結果
     * @return bool
     */
    public static function hasI18nColumns($refresh = false)
    {
        static $result = NULL;

        if (NULL === $result || $refresh) {
            $columns = self::tableColumns();
            $result = in_array('default_lang', $columns, true) && in_array('i18n', $columns, true);
        }

        return $result;
    }

    /**
     * 確保資料表具備多語言欄位
     *
     * 供後台頁面調用，讓「只覆蓋文件、未重新啟用插件」的升級方式也能正常運作。
     *
     * @return bool
     */
    public static function ensureI18nColumns()
    {
        if (self::hasI18nColumns()) {
            return true;
        }

        $db = Typecho_Db::get();
        self::upgradeTable($db, $db->getPrefix());

        return self::hasI18nColumns(true);
    }

    /**
     * 取得通知資料表的欄位名稱（小寫）
     *
     * @return array 取不到時回傳空陣列
     */
    private static function tableColumns()
    {
        $db = Typecho_Db::get();
        $table = $db->getPrefix() . 'notice';
        $columns = array();

        try {
            foreach ($db->fetchAll('SHOW COLUMNS FROM `' . $table . '`') as $row) {
                foreach ($row as $key => $value) {
                    if (0 === strcasecmp($key, 'Field')) {
                        $columns[] = strtolower($value);
                    }
                }
            }
        } catch (Exception $e) {
            // 取不到欄位資訊時視為未知，避免在非 MySQL 環境下報錯
            return array();
        }

        return $columns;
    }

    /**
     * 為舊版本的資料表補上多語言欄位
     *
     * @param Typecho_Db $db
     * @param string $prefix
     */
    private static function upgradeTable($db, $prefix)
    {
        $table = $prefix . 'notice';
        $columns = self::tableColumns();

        if (empty($columns)) {
            return;
        }

        $missing = array(
            'default_lang' => "ALTER TABLE `{$table}` ADD `default_lang` VARCHAR(20) NOT NULL DEFAULT ''",
            'i18n'         => "ALTER TABLE `{$table}` ADD `i18n` TEXT"
        );

        foreach ($missing as $column => $sql) {
            if (in_array($column, $columns, true)) {
                continue;
            }

            try {
                $db->query($sql);
            } catch (Exception $e) {
                // 欄位已存在或無權限時忽略
            }
        }
    }
}
