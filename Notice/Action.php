<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class TypechoNotice_Action extends Typecho_Widget implements Widget_Interface_Do
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

        $data = array(
            'title' => $this->request->get('title', ''),
            'content' => $this->request->get('content', ''),
            'type' => $this->request->get('type', 'info'),
            'visible' => $this->request->get('visible', 1) ? 1 : 0,
            'start_time' => $this->_parseTime($this->request->get('start_time', '')),
            'end_time' => $this->_parseTime($this->request->get('end_time', '')),
            'order_num' => intval($this->request->get('order_num', 0)),
            'created' => time(),
            'modified' => time()
        );

        $this->db->query($this->db->insert('table.notice')->rows($data));

        $this->widget('Widget_Notice')->set(_t('通知已新增'), 'success');
        $this->response->redirect(Typecho_Common::url('extending.php?panel=TypechoNotice/manage-notice.php', Helper::options()->adminUrl));
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

        $data = array(
            'title' => $this->request->get('title', ''),
            'content' => $this->request->get('content', ''),
            'type' => $this->request->get('type', 'info'),
            'visible' => $this->request->get('visible', 1) ? 1 : 0,
            'start_time' => $this->_parseTime($this->request->get('start_time', '')),
            'end_time' => $this->_parseTime($this->request->get('end_time', '')),
            'order_num' => intval($this->request->get('order_num', 0)),
            'modified' => time()
        );

        $this->db->query($this->db->update('table.notice')->rows($data)->where('id = ?', $id));

        $this->widget('Widget_Notice')->set(_t('通知已更新'), 'success');
        $this->response->redirect(Typecho_Common::url('extending.php?panel=TypechoNotice/manage-notice.php', Helper::options()->adminUrl));
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
        $this->response->redirect(Typecho_Common::url('extending.php?panel=TypechoNotice/manage-notice.php', Helper::options()->adminUrl));
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
        $this->response->redirect(Typecho_Common::url('extending.php?panel=TypechoNotice/manage-notice.php', Helper::options()->adminUrl));
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
