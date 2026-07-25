<?php
/**
 * Typecho 友情鏈接插件
 *
 * 支持友情鏈接管理、現有鏈接匯入、優先級、分類、排序/隨機排序、
 * 隨機取得一個或多個，以及前端側 RSS 擷取與展示（請求由訪客瀏覽器發出，
 * 不經過伺服器，避免暴露伺服器 IP）。
 *
 * @package NekoTypechoLinks
 * @author Hoshi
 * @version 1.0.0
 * @link https://github.com/moehoshio/NekoTypechoPlugins
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class NekoTypechoLinks_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件
     */
    public static function activate()
    {
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();

        $db->query("CREATE TABLE IF NOT EXISTS `{$prefix}friendlinks` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(150) NOT NULL DEFAULT '',
            `url` VARCHAR(255) NOT NULL DEFAULT '',
            `image` VARCHAR(255) NOT NULL DEFAULT '',
            `description` VARCHAR(500) NOT NULL DEFAULT '',
            `category` VARCHAR(100) NOT NULL DEFAULT '',
            `rss` VARCHAR(255) NOT NULL DEFAULT '',
            `priority` INT(10) NOT NULL DEFAULT 0,
            `sort_order` INT(10) NOT NULL DEFAULT 0,
            `visible` TINYINT(1) NOT NULL DEFAULT 1,
            `created` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            `modified` INT(10) UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        Helper::addPanel(3, 'NekoTypechoLinks/manage-links.php', '友情鏈接', '管理友情鏈接', 'administrator');
        Helper::addAction('links-edit', 'NekoTypechoLinks_Action');

        Typecho_Plugin::factory('Widget_Archive')->header = array('NekoTypechoLinks_Plugin', 'header');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('NekoTypechoLinks_Plugin', 'footer');
        Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('NekoTypechoLinks_Plugin', 'parseContent');

        return _t('插件已激活，請前往「友情鏈接」管理頁面添加或匯入鏈接。');
    }

    /**
     * 禁用插件
     *
     * 為避免誤刪友鏈資料，停用插件時不會刪除資料表。
     * 如需徹底清除，請手動刪除 `friendlinks` 資料表。
     */
    public static function deactivate()
    {
        Helper::removePanel(3, 'NekoTypechoLinks/manage-links.php');
        Helper::removeAction('links-edit');

        return _t('插件已禁用，友鏈資料仍保留（如需清除請手動刪除 friendlinks 資料表）。');
    }

    /**
     * 插件配置面板
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $defaultOrder = new Typecho_Widget_Helper_Form_Element_Select(
            'defaultOrder',
            array(
                'priority' => '按優先級（再按排序值）',
                'sort'     => '僅按排序值',
                'created'  => '按建立時間',
                'random'   => '隨機排序'
            ),
            'priority',
            _t('預設排序方式'),
            _t('未在短代碼或主題調用中指定時採用的排序方式')
        );
        $form->addInput($defaultOrder);

        $groupByCategory = new Typecho_Widget_Helper_Form_Element_Radio(
            'groupByCategory',
            array(
                '1' => '是',
                '0' => '否'
            ),
            '1',
            _t('按分類分組顯示'),
            _t('啟用後，前端會將友鏈依分類分組並顯示分類標題')
        );
        $form->addInput($groupByCategory);

        $linkTarget = new Typecho_Widget_Helper_Form_Element_Radio(
            'linkTarget',
            array(
                '_blank' => '新分頁開啟',
                '_self'  => '當前分頁開啟'
            ),
            '_blank',
            _t('鏈接打開方式')
        );
        $form->addInput($linkTarget);

        $relNofollow = new Typecho_Widget_Helper_Form_Element_Radio(
            'relNofollow',
            array(
                '1' => '是',
                '0' => '否'
            ),
            '1',
            _t('為鏈接添加 nofollow'),
            _t('為友情鏈接添加 rel="nofollow noopener" 屬性')
        );
        $form->addInput($relNofollow);

        $enableRss = new Typecho_Widget_Helper_Form_Element_Radio(
            'enableRss',
            array(
                '1' => '啟用',
                '0' => '停用'
            ),
            '1',
            _t('啟用 RSS 擷取'),
            _t('啟用後，含有 RSS 地址的友鏈會在前端由訪客瀏覽器擷取並顯示最新文章（請求不經過伺服器，避免暴露伺服器 IP）')
        );
        $form->addInput($enableRss);

        $rssItemCount = new Typecho_Widget_Helper_Form_Element_Text(
            'rssItemCount',
            NULL,
            '3',
            _t('RSS 顯示條數'),
            _t('每個友鏈最多顯示的最新文章數量')
        );
        $form->addInput($rssItemCount);

        $rssProxy = new Typecho_Widget_Helper_Form_Element_Text(
            'rssProxy',
            NULL,
            '',
            _t('RSS 代理地址（可選）'),
            _t('部分 RSS 來源未開放跨域（CORS），瀏覽器會直接擷取失敗。若填寫代理地址，將以「代理地址 + 被擷取的 RSS 網址」方式請求。留空則僅嘗試直接擷取。')
        );
        $form->addInput($rssProxy);

        $customCss = new Typecho_Widget_Helper_Form_Element_Textarea(
            'customCss',
            NULL,
            '',
            _t('自定義CSS'),
            _t('添加自定義CSS樣式來覆蓋默認樣式')
        );
        $form->addInput($customCss);
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
        $pluginOptions = $options->plugin('NekoTypechoLinks');
        $cssUrl = Typecho_Common::url('NekoTypechoLinks/assets/links.css', $options->pluginUrl);

        echo '<link rel="stylesheet" href="' . $cssUrl . '" />' . "\n";

        if (!empty($pluginOptions->customCss)) {
            echo '<style>' . $pluginOptions->customCss . '</style>' . "\n";
        }
    }

    /**
     * 輸出底部JS（RSS 客戶端擷取）
     */
    public static function footer()
    {
        $options = Helper::options();
        $pluginOptions = $options->plugin('NekoTypechoLinks');

        if (empty($pluginOptions->enableRss) || $pluginOptions->enableRss === '0') {
            return;
        }

        $rssItemCount = intval($pluginOptions->rssItemCount ?? 3);
        if ($rssItemCount <= 0) {
            $rssItemCount = 3;
        }
        $rssProxy = (string) ($pluginOptions->rssProxy ?? '');

        $jsUrl = Typecho_Common::url('NekoTypechoLinks/assets/links.js', $options->pluginUrl);
        echo '<script>window.nekoTypechoLinksConfig = '
            . json_encode(array(
                'rssItemCount' => $rssItemCount,
                'rssProxy' => $rssProxy
            ))
            . ';</script>' . "\n";
        echo '<script src="' . $jsUrl . '"></script>' . "\n";
    }

    /**
     * 內容短代碼解析
     *
     * 支持在文章/獨立頁面中插入 [friendlinks] 短代碼，可帶屬性：
     *   [friendlinks category="技術" order="random" limit="10" rss="1"]
     *
     * @param string $content
     * @param Widget_Abstract_Contents $widget
     * @param string $lastResult
     * @return string
     */
    public static function parseContent($content, $widget, $lastResult)
    {
        $content = empty($lastResult) ? $content : $lastResult;

        if (strpos($content, '[friendlinks') === false) {
            return $content;
        }

        return preg_replace_callback('/\[friendlinks([^\]]*)\]/i', function ($matches) {
            $attrs = self::_parseShortcodeAttrs($matches[1]);
            return self::render($attrs);
        }, $content);
    }

    /**
     * 解析短代碼屬性字串
     */
    private static function _parseShortcodeAttrs($attrString)
    {
        $attrs = array();
        if (preg_match_all('/(\w+)\s*=\s*"([^"]*)"/', $attrString, $m, PREG_SET_ORDER)) {
            foreach ($m as $item) {
                $attrs[strtolower($item[1])] = $item[2];
            }
        }
        return $attrs;
    }

    /**
     * 取得友情鏈接
     *
     * @param array $params 可選參數：
     *   - category (string) 僅取得指定分類
     *   - order    (string) priority|sort|created|random
     *   - limit    (int)    最多取得數量，0 表示全部
     *   - visibleOnly (bool) 預設 true，僅取得顯示中的鏈接
     * @return array
     */
    public static function getLinks($params = array())
    {
        $db = Typecho_Db::get();
        $select = $db->select()->from('table.friendlinks');

        $visibleOnly = !array_key_exists('visibleOnly', $params) || $params['visibleOnly'];
        if ($visibleOnly) {
            $select->where('visible = 1');
        }

        if (!empty($params['category'])) {
            $select->where('category = ?', $params['category']);
        }

        $order = isset($params['order']) ? $params['order'] : 'priority';

        switch ($order) {
            case 'random':
                // 隨機排序由 PHP 端處理，確保跨資料庫相容
                break;
            case 'sort':
                $select->order('sort_order', Typecho_Db::SORT_ASC)
                    ->order('id', Typecho_Db::SORT_ASC);
                break;
            case 'created':
                $select->order('created', Typecho_Db::SORT_DESC);
                break;
            case 'priority':
            default:
                $select->order('priority', Typecho_Db::SORT_DESC)
                    ->order('sort_order', Typecho_Db::SORT_ASC)
                    ->order('id', Typecho_Db::SORT_ASC);
                break;
        }

        $links = $db->fetchAll($select);

        if ($order === 'random') {
            shuffle($links);
        }

        if (!empty($params['limit'])) {
            $links = array_slice($links, 0, intval($params['limit']));
        }

        return $links;
    }

    /**
     * 隨機取得一個或多個友情鏈接
     *
     * @param int $count 數量，預設 1
     * @param array $params 其他過濾參數（同 getLinks）
     * @return array
     */
    public static function getRandomLinks($count = 1, $params = array())
    {
        $params['order'] = 'random';
        $params['limit'] = max(1, intval($count));
        return self::getLinks($params);
    }

    /**
     * 取得所有分類名稱
     *
     * @return array
     */
    public static function getCategories()
    {
        $db = Typecho_Db::get();
        $rows = $db->fetchAll($db->select('category')->from('table.friendlinks')
            ->where('visible = 1'));

        $categories = array();
        foreach ($rows as $row) {
            $cat = $row['category'];
            if ($cat !== '' && !in_array($cat, $categories, true)) {
                $categories[] = $cat;
            }
        }
        return $categories;
    }

    /**
     * 渲染友情鏈接 HTML（供主題或短代碼調用）
     *
     * @param array $params
     *   - category (string)
     *   - order    (string)
     *   - limit    (int)
     *   - rss      (bool|string) 是否顯示 RSS 區塊
     * @return string
     */
    public static function render($params = array())
    {
        $options = Helper::options();
        $pluginOptions = $options->plugin('NekoTypechoLinks');

        $order = isset($params['order']) && $params['order'] !== ''
            ? $params['order']
            : ($pluginOptions->defaultOrder ?? 'priority');

        $groupByCategory = !isset($params['category']) || $params['category'] === '';
        if ($groupByCategory) {
            $groupByCategory = ($pluginOptions->groupByCategory ?? '1') === '1';
        }
        // 隨機排序時不進行分組，以保留隨機性
        if ($order === 'random') {
            $groupByCategory = false;
        }

        $linkTarget = $pluginOptions->linkTarget ?? '_blank';
        $rel = (($pluginOptions->relNofollow ?? '1') === '1') ? 'nofollow noopener' : 'noopener';

        $rssEnabled = ($pluginOptions->enableRss ?? '1') === '1';
        if (isset($params['rss'])) {
            $rssEnabled = $rssEnabled && ($params['rss'] === true || $params['rss'] === '1' || $params['rss'] === 1);
        }

        $links = self::getLinks(array(
            'category' => isset($params['category']) ? $params['category'] : '',
            'order' => $order,
            'limit' => isset($params['limit']) ? intval($params['limit']) : 0
        ));

        if (empty($links)) {
            return '<div class="neko-typecho-links neko-typecho-links-empty">' . _t('暫無友情鏈接') . '</div>';
        }

        $html = '<div class="neko-typecho-links" data-order="' . htmlspecialchars($order) . '">';

        if ($groupByCategory) {
            $grouped = array();
            foreach ($links as $link) {
                $cat = $link['category'] !== '' ? $link['category'] : _t('未分類');
                $grouped[$cat][] = $link;
            }
            foreach ($grouped as $cat => $items) {
                $html .= '<div class="neko-typecho-links-group">';
                $html .= '<h3 class="neko-typecho-links-category">' . htmlspecialchars($cat) . '</h3>';
                $html .= self::_renderList($items, $linkTarget, $rel, $rssEnabled);
                $html .= '</div>';
            }
        } else {
            $html .= self::_renderList($links, $linkTarget, $rel, $rssEnabled);
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * 渲染單個鏈接列表
     */
    private static function _renderList($links, $linkTarget, $rel, $rssEnabled)
    {
        $html = '<ul class="neko-typecho-links-list">';
        foreach ($links as $link) {
            $name = htmlspecialchars($link['name']);
            $url = htmlspecialchars($link['url']);
            $desc = htmlspecialchars($link['description']);
            $image = htmlspecialchars($link['image']);

            $html .= '<li class="neko-typecho-link-item">';
            $html .= '<a class="neko-typecho-link-main" href="' . $url . '" target="' . $linkTarget . '" rel="' . $rel . '">';
            if ($image !== '') {
                $html .= '<img class="neko-typecho-link-avatar" src="' . $image . '" alt="' . $name . '" loading="lazy" />';
            }
            $html .= '<span class="neko-typecho-link-info">';
            $html .= '<span class="neko-typecho-link-name">' . $name . '</span>';
            if ($desc !== '') {
                $html .= '<span class="neko-typecho-link-desc">' . $desc . '</span>';
            }
            $html .= '</span>';
            $html .= '</a>';

            if ($rssEnabled && !empty($link['rss'])) {
                $html .= '<div class="neko-typecho-link-rss" data-rss-url="' . htmlspecialchars($link['rss'])
                    . '" data-link-name="' . $name . '"></div>';
            }

            $html .= '</li>';
        }
        $html .= '</ul>';
        return $html;
    }
}
