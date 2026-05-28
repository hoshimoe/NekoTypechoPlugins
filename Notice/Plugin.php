<?php
/**
 * Typecho 通知與公告插件
 * 
 * @package TypechoNotice
 * @author Hoshi
 * @version 1.0.0
 * @link https://github.com/moehoshio/TypechoNotice
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

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
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        Helper::addPanel(3, 'TypechoNotice/manage-notice.php', '通知管理', '管理通知與公告', 'administrator');
        Helper::addAction('notice-edit', 'TypechoNotice_Action');
        
        Typecho_Plugin::factory('Widget_Archive')->header = array('TypechoNotice_Plugin', 'header');
        Typecho_Plugin::factory('Widget_Archive')->footer = array('TypechoNotice_Plugin', 'footer');
        
        return _t('插件已激活，請前往通知管理頁面添加通知。');
    }

    /**
     * 禁用插件
     */
    public static function deactivate()
    {
        Helper::removePanel(3, 'TypechoNotice/manage-notice.php');
        Helper::removeAction('notice-edit');
        
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        $db->query("DROP TABLE IF EXISTS `{$prefix}notice`");
        
        return _t('插件已禁用，通知數據已清除。');
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
        $now = time();
        
        $db = Typecho_Db::get();
        $prefix = $db->getPrefix();
        
        $notices = $db->fetchAll($db->select()->from('table.notice')
            ->where('visible = 1')
            ->where('start_time = 0 OR start_time <= ?', $now)
            ->where('end_time = 0 OR end_time >= ?', $now)
            ->order('order_num', Typecho_Db::SORT_ASC)
            ->order('created', Typecho_Db::SORT_DESC));
        
        if (empty($notices)) {
            return;
        }
        
        $position = $pluginOptions->position ?? 'top';
        $theme = $pluginOptions->theme ?? 'auto';
        $cookieDays = intval($pluginOptions->cookieDays ?? 7);
        
        echo '<div id="typecho-notice-container" class="typecho-notice-' . $position . '" data-theme="' . $theme . '">' . "\n";
        
        foreach ($notices as $notice) {
            $noticeId = 'notice-' . $notice['id'];
            echo '<div class="typecho-notice-item typecho-notice-type-' . htmlspecialchars($notice['type']) . '" data-notice-id="' . $notice['id'] . '" id="' . $noticeId . '">' . "\n";
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
     */
    public static function getNotices()
    {
        $now = time();
        $db = Typecho_Db::get();
        
        return $db->fetchAll($db->select()->from('table.notice')
            ->where('visible = 1')
            ->where('start_time = 0 OR start_time <= ?', $now)
            ->where('end_time = 0 OR end_time >= ?', $now)
            ->order('order_num', Typecho_Db::SORT_ASC)
            ->order('created', Typecho_Db::SORT_DESC));
    }
}
