<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class NekoTypechoCommentFilter_Action extends Typecho_Widget implements Widget_Interface_Do
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
     * 將被攔截的評論還原為正常評論
     *
     * 還原時不會觸發評論完成接口，因此不會補發郵件通知。
     */
    public function restore()
    {
        $this->_auth();

        $id = intval($this->request->get('id'));
        if (!$id) {
            throw new Typecho_Widget_Exception(_t('記錄ID不存在'));
        }

        $row = $this->db->fetchRow($this->db->select()->from('table.comment_filter_log')->where('id = ?', $id));
        if (empty($row)) {
            throw new Typecho_Widget_Exception(_t('攔截記錄不存在'));
        }

        $status = $this->request->get('status', 'waiting');
        if (!in_array($status, array('approved', 'waiting'), true)) {
            $status = 'waiting';
        }

        $comment = json_decode($row['raw'], true);
        if (!is_array($comment)) {
            $comment = array();
        }

        $cid = intval(!empty($comment['cid']) ? $comment['cid'] : $row['cid']);
        $content = $this->db->fetchRow($this->db->select('cid', 'authorId')->from('table.contents')->where('cid = ?', $cid));
        if (empty($content)) {
            throw new Typecho_Widget_Exception(_t('原文章已不存在，無法還原此評論'));
        }

        // 父評論可能已被刪除，避免留下無效的父節點
        $parent = intval(isset($comment['parent']) ? $comment['parent'] : 0);
        if ($parent > 0) {
            $parentRow = $this->db->fetchRow($this->db->select('coid')->from('table.comments')
                ->where('coid = ? AND cid = ?', $parent, $cid));
            if (empty($parentRow)) {
                $parent = 0;
            }
        }

        $author = $this->_value($comment, 'author', $row['author']);
        $mail = $this->_value($comment, 'mail', $row['mail']);
        $url = $this->_value($comment, 'url', $row['url']);
        $text = isset($comment['text']) ? (string) $comment['text'] : (string) $row['text'];

        $this->db->query($this->db->insert('table.comments')->rows(array(
            'cid'      => $cid,
            'created'  => intval(!empty($comment['created']) ? $comment['created'] : $row['created']),
            'author'   => '' === $author ? NULL : $author,
            'authorId' => intval(isset($comment['authorId']) ? $comment['authorId'] : 0),
            'ownerId'  => intval(isset($comment['ownerId']) ? $comment['ownerId'] : $content['authorId']),
            'mail'     => '' === $mail ? NULL : $mail,
            'url'      => '' === $url ? NULL : $url,
            'ip'       => $this->_value($comment, 'ip', $row['ip']),
            'agent'    => $this->_value($comment, 'agent', $row['agent']),
            'text'     => '' === $text ? NULL : $text,
            'type'     => isset($comment['type']) ? $comment['type'] : 'comment',
            'status'   => $status,
            'parent'   => $parent
        )));

        $this->_refreshCommentsNum($cid);

        $this->db->query($this->db->update('table.comment_filter_log')
            ->rows(array('state' => 'restored'))
            ->where('id = ?', $id));

        $this->widget('Widget_Notice')->set(
            'approved' === $status ? _t('評論已還原並通過審核') : _t('評論已還原至待審核'),
            'success'
        );
        $this->_redirect();
    }

    /**
     * 刪除單筆攔截記錄
     */
    public function delete()
    {
        $this->_auth();

        $id = intval($this->request->get('id'));
        if (!$id) {
            throw new Typecho_Widget_Exception(_t('記錄ID不存在'));
        }

        $this->db->query($this->db->delete('table.comment_filter_log')->where('id = ?', $id));

        $this->widget('Widget_Notice')->set(_t('記錄已刪除'), 'success');
        $this->_redirect();
    }

    /**
     * 清空攔截記錄
     */
    public function clear()
    {
        $this->_auth();

        $this->db->query($this->db->delete('table.comment_filter_log'));

        $this->widget('Widget_Notice')->set(_t('攔截記錄已清空'), 'success');
        $this->_redirect();
    }

    /**
     * 立即清理過期記錄
     */
    public function clean()
    {
        $this->_auth();

        NekoTypechoCommentFilter_Plugin::cleanLog(NULL, true);

        $this->widget('Widget_Notice')->set(_t('過期記錄已清理'), 'success');
        $this->_redirect();
    }

    /**
     * 路由分發
     */
    public function action()
    {
        $do = $this->request->get('do');

        switch ($do) {
            case 'restore':
                $this->restore();
                break;
            case 'delete':
                $this->delete();
                break;
            case 'clear':
                $this->clear();
                break;
            case 'clean':
                $this->clean();
                break;
            default:
                throw new Typecho_Widget_Exception(_t('無效的操作'));
        }
    }

    /**
     * 重新統計文章的評論數
     *
     * @param int $cid
     */
    private function _refreshCommentsNum($cid)
    {
        $num = $this->db->fetchObject($this->db->select(array('COUNT(coid)' => 'num'))->from('table.comments')
            ->where('status = ? AND cid = ?', 'approved', $cid))->num;

        $this->db->query($this->db->update('table.contents')
            ->rows(array('commentsNum' => intval($num)))
            ->where('cid = ?', $cid));
    }

    /**
     * 取值，優先使用原始評論資料
     *
     * @param array $comment
     * @param string $key
     * @param mixed $fallback
     * @return string
     */
    private function _value($comment, $key, $fallback)
    {
        $value = isset($comment[$key]) ? $comment[$key] : $fallback;
        return trim((string) $value);
    }

    /**
     * 權限與來源驗證
     */
    private function _auth()
    {
        $user = Typecho_Widget::widget('Widget_User');
        $user->pass('administrator');

        // 校驗管理頁面帶上的 token，避免跨站請求偽造
        Typecho_Widget::widget('Widget_Security')->protect();
    }

    /**
     * 跳轉回管理頁
     */
    private function _redirect()
    {
        $page = intval($this->request->get('page', 1));
        $url = 'extending.php?panel=NekoTypechoCommentFilter/manage-comment-filter.php';
        if ($page > 1) {
            $url .= '&page=' . $page;
        }

        $this->response->redirect(Typecho_Common::url($url, Helper::options()->adminUrl));
    }
}
