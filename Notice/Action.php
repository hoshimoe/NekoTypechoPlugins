<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class NekoTypechoNotice_Action extends Typecho_Widget implements Widget_Interface_Do
{
    private $db;
    private $prefix;

    public function __construct($request, $response, $params = NULL)
    {
        parent::__construct($request, $response, $params);
        $this->db = Typecho_Db::get();
        $this->prefix = $this->db->getPrefix();
    }

    public function execute()
    {
    }

    /**
     * 新增通知
     */
    public function add()
    {
        $user = Typecho_Widget::widget('Widget_User');
        $user->pass('administrator');

        $data = $this->_collect();
        $data['created'] = time();
        $data['modified'] = time();

        $this->db->query($this->db->insert('table.notice')->rows($data));

        $this->widget('Widget_Notice')->set(_t('通知已新增'), 'success');
        $this->response->redirect(Typecho_Common::url('extending.php?panel=NekoTypechoNotice/manage-notice.php', Helper::options()->adminUrl));
    }

    /**
     * 更新通知
     */
    public function update()
    {
        $user = Typecho_Widget::widget('Widget_User');
        $user->pass('administrator');

        $id = $this->request->get('id');
        if (!$id) {
            throw new Typecho_Widget_Exception(_t('通知ID不存在'));
        }

        $data = $this->_collect();
        $data['modified'] = time();

        $this->db->query($this->db->update('table.notice')->rows($data)->where('id = ?', $id));

        $this->widget('Widget_Notice')->set(_t('通知已更新'), 'success');
        $this->response->redirect(Typecho_Common::url('extending.php?panel=NekoTypechoNotice/manage-notice.php', Helper::options()->adminUrl));
    }

    /**
     * 刪除通知
     */
    public function delete()
    {
        $user = Typecho_Widget::widget('Widget_User');
        $user->pass('administrator');

        $id = $this->request->get('id');
        if (!$id) {
            throw new Typecho_Widget_Exception(_t('通知ID不存在'));
        }

        $this->db->query($this->db->delete('table.notice')->where('id = ?', $id));

        $this->widget('Widget_Notice')->set(_t('通知已刪除'), 'success');
        $this->response->redirect(Typecho_Common::url('extending.php?panel=NekoTypechoNotice/manage-notice.php', Helper::options()->adminUrl));
    }

    /**
     * 切換顯示狀態
     */
    public function toggle()
    {
        $user = Typecho_Widget::widget('Widget_User');
        $user->pass('administrator');

        $id = $this->request->get('id');
        if (!$id) {
            throw new Typecho_Widget_Exception(_t('通知ID不存在'));
        }

        $notice = $this->db->fetchRow($this->db->select()->from('table.notice')->where('id = ?', $id));
        if ($notice) {
            $newVisible = $notice['visible'] ? 0 : 1;
            $this->db->query($this->db->update('table.notice')->rows(array('visible' => $newVisible, 'modified' => time()))->where('id = ?', $id));
        }

        $this->widget('Widget_Notice')->set(_t('狀態已更新'), 'success');
        $this->response->redirect(Typecho_Common::url('extending.php?panel=NekoTypechoNotice/manage-notice.php', Helper::options()->adminUrl));
    }

    /**
     * 路由分發
     */
    public function action()
    {
        $do = $this->request->get('do');
        
        switch ($do) {
            case 'add':
                $this->add();
                break;
            case 'update':
                $this->update();
                break;
            case 'delete':
                $this->delete();
                break;
            case 'toggle':
                $this->toggle();
                break;
            default:
                throw new Typecho_Widget_Exception(_t('無效的操作'));
        }
    }

    /**
     * 收集表單資料
     */
    private function _collect()
    {
        $data = array(
            'title' => $this->request->get('title', ''),
            'content' => $this->request->get('content', ''),
            'type' => $this->request->get('type', 'info'),
            'visible' => $this->request->get('visible', 1) ? 1 : 0,
            'start_time' => $this->_parseTime($this->request->get('start_time', '')),
            'end_time' => $this->_parseTime($this->request->get('end_time', '')),
            'order_num' => intval($this->request->get('order_num', 0))
        );

        // 資料表尚未升級時略過多語言欄位，避免寫入不存在的欄位
        if (NekoTypechoNotice_Plugin::ensureI18nColumns()) {
            $data['default_lang'] = NekoTypechoNotice_I18n::normalize($this->request->get('default_lang', ''));
            $data['i18n'] = $this->_collectI18n();
        }

        return $data;
    }

    /**
     * 收集多語言版本，序列化為 JSON
     *
     * 表單以 i18n_lang[] / i18n_title[] / i18n_content[] 三組平行陣列提交，
     * 語言代碼為空或標題與內容皆為空的區塊會被忽略。
     *
     * @return string
     */
    private function _collectI18n()
    {
        $langs = $this->request->getArray('i18n_lang');
        $titles = $this->request->getArray('i18n_title');
        $contents = $this->request->getArray('i18n_content');

        $result = array();

        foreach ($langs as $index => $lang) {
            $lang = NekoTypechoNotice_I18n::normalize($lang);
            if ('' === $lang || NekoTypechoNotice_I18n::DEFAULT_KEY === $lang) {
                continue;
            }

            $title = isset($titles[$index]) ? trim((string) $titles[$index]) : '';
            $content = isset($contents[$index]) ? trim((string) $contents[$index]) : '';

            if ('' === $title && '' === $content) {
                continue;
            }

            $result[$lang] = array('title' => $title, 'content' => $content);
        }

        if (empty($result)) {
            return '';
        }

        return json_encode($result, defined('JSON_UNESCAPED_UNICODE') ? JSON_UNESCAPED_UNICODE : 0);
    }

    /**
     * 解析時間字串
     */
    private function _parseTime($timeStr)
    {
        if (empty($timeStr)) {
            return 0;
        }
        $timestamp = strtotime($timeStr);
        return $timestamp ? $timestamp : 0;
    }
}
