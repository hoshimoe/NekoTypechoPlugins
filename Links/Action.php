<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class NekoTypechoLinks_Action extends Typecho_Widget implements Widget_Interface_Do
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
     * 新增友鏈
     */
    public function add()
    {
        $this->_auth();

        $data = $this->_collect();
        $data['created'] = time();
        $data['modified'] = time();

        $this->db->query($this->db->insert('table.friendlinks')->rows($data));

        $this->widget('Widget_Notice')->set(_t('友鏈已新增'), 'success');
        $this->_redirect();
    }

    /**
     * 更新友鏈
     */
    public function update()
    {
        $this->_auth();

        $id = $this->request->get('id');
        if (!$id) {
            throw new Typecho_Widget_Exception(_t('友鏈ID不存在'));
        }

        $data = $this->_collect();
        $data['modified'] = time();

        $this->db->query($this->db->update('table.friendlinks')->rows($data)->where('id = ?', $id));

        $this->widget('Widget_Notice')->set(_t('友鏈已更新'), 'success');
        $this->_redirect();
    }

    /**
     * 刪除友鏈
     */
    public function delete()
    {
        $this->_auth();

        $id = $this->request->get('id');
        if (!$id) {
            throw new Typecho_Widget_Exception(_t('友鏈ID不存在'));
        }

        $this->db->query($this->db->delete('table.friendlinks')->where('id = ?', $id));

        $this->widget('Widget_Notice')->set(_t('友鏈已刪除'), 'success');
        $this->_redirect();
    }

    /**
     * 切換顯示狀態
     */
    public function toggle()
    {
        $this->_auth();

        $id = $this->request->get('id');
        if (!$id) {
            throw new Typecho_Widget_Exception(_t('友鏈ID不存在'));
        }

        $link = $this->db->fetchRow($this->db->select()->from('table.friendlinks')->where('id = ?', $id));
        if ($link) {
            $newVisible = $link['visible'] ? 0 : 1;
            $this->db->query($this->db->update('table.friendlinks')
                ->rows(array('visible' => $newVisible, 'modified' => time()))
                ->where('id = ?', $id));
        }

        $this->widget('Widget_Notice')->set(_t('狀態已更新'), 'success');
        $this->_redirect();
    }

    /**
     * 匯入友鏈
     *
     * 支持兩種來源：
     *   - source=typecho   從 Typecho 內建/官方 Links 插件的 `links` 資料表匯入
     *   - source=text      從貼上的文字匯入，每行一筆：
     *                      名稱|網址|頭像|描述|分類|RSS
     *                      也支持 JSON 陣列格式
     */
    public function import()
    {
        $this->_auth();

        $source = $this->request->get('source', 'text');
        $count = 0;

        if ($source === 'typecho') {
            $count = $this->_importFromTypechoLinks();
        } else {
            $count = $this->_importFromText($this->request->get('importText', ''));
        }

        $this->widget('Widget_Notice')->set(_t('成功匯入 %d 筆友鏈', $count), 'success');
        $this->_redirect();
    }

    /**
     * 從 Typecho 官方 Links 插件的 links 資料表匯入
     *
     * @return int 匯入筆數
     */
    private function _importFromTypechoLinks()
    {
        $table = $this->prefix . 'links';

        // 確認資料表存在
        try {
            $exists = $this->db->fetchAll('SHOW TABLES LIKE ' . $this->db->quoteValue($table));
        } catch (Exception $e) {
            $exists = array();
        }
        if (empty($exists)) {
            throw new Typecho_Widget_Exception(_t('未找到官方 Links 插件的資料表（%s），無法匯入', $table));
        }

        $rows = $this->db->fetchAll('SELECT * FROM `' . $table . '`');
        $count = 0;
        foreach ($rows as $row) {
            $data = array(
                'name' => isset($row['name']) ? $row['name'] : '',
                'url' => isset($row['url']) ? $row['url'] : '',
                'image' => isset($row['image']) ? $row['image'] : '',
                'description' => isset($row['description']) ? $row['description'] : '',
                'category' => isset($row['sort']) ? $row['sort'] : '',
                'rss' => isset($row['rss']) ? $row['rss'] : '',
                'priority' => 0,
                'sort_order' => isset($row['order']) ? intval($row['order']) : 0,
                'visible' => 1,
                'created' => time(),
                'modified' => time()
            );
            if ($data['url'] === '') {
                continue;
            }
            $this->db->query($this->db->insert('table.friendlinks')->rows($data));
            $count++;
        }
        return $count;
    }

    /**
     * 從文字/JSON 匯入
     *
     * @param string $text
     * @return int 匯入筆數
     */
    private function _importFromText($text)
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        $items = array();
        $decoded = json_decode($text, true);
        if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
            // JSON 陣列格式
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $items[] = array(
                    'name' => isset($item['name']) ? $item['name'] : '',
                    'url' => isset($item['url']) ? $item['url'] : '',
                    'image' => isset($item['image']) ? $item['image'] : '',
                    'description' => isset($item['description']) ? $item['description'] : '',
                    'category' => isset($item['category']) ? $item['category'] : '',
                    'rss' => isset($item['rss']) ? $item['rss'] : '',
                    'priority' => isset($item['priority']) ? intval($item['priority']) : 0,
                    'sort_order' => isset($item['sort_order']) ? intval($item['sort_order']) : 0
                );
            }
        } else {
            // 每行一筆：名稱|網址|頭像|描述|分類|RSS
            $lines = preg_split('/\r\n|\r|\n/', $text);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $parts = explode('|', $line);
                $items[] = array(
                    'name' => isset($parts[0]) ? trim($parts[0]) : '',
                    'url' => isset($parts[1]) ? trim($parts[1]) : '',
                    'image' => isset($parts[2]) ? trim($parts[2]) : '',
                    'description' => isset($parts[3]) ? trim($parts[3]) : '',
                    'category' => isset($parts[4]) ? trim($parts[4]) : '',
                    'rss' => isset($parts[5]) ? trim($parts[5]) : '',
                    'priority' => 0,
                    'sort_order' => 0
                );
            }
        }

        $count = 0;
        foreach ($items as $item) {
            if (empty($item['url']) || empty($item['name'])) {
                continue;
            }
            $item['visible'] = 1;
            $item['created'] = time();
            $item['modified'] = time();
            $this->db->query($this->db->insert('table.friendlinks')->rows($item));
            $count++;
        }
        return $count;
    }

    /**
     * 收集表單資料
     */
    private function _collect()
    {
        return array(
            'name' => $this->request->get('name', ''),
            'url' => $this->request->get('url', ''),
            'image' => $this->request->get('image', ''),
            'description' => $this->request->get('description', ''),
            'category' => $this->request->get('category', ''),
            'rss' => $this->request->get('rss', ''),
            'priority' => intval($this->request->get('priority', 0)),
            'sort_order' => intval($this->request->get('sort_order', 0)),
            'visible' => $this->request->get('visible', 1) ? 1 : 0
        );
    }

    /**
     * 權限驗證
     */
    private function _auth()
    {
        $user = Typecho_Widget::widget('Widget_User');
        $user->pass('administrator');
    }

    /**
     * 跳轉回管理頁
     */
    private function _redirect()
    {
        $this->response->redirect(
            Typecho_Common::url('extending.php?panel=NekoTypechoLinks/manage-links.php', Helper::options()->adminUrl)
        );
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
            case 'import':
                $this->import();
                break;
            default:
                throw new Typecho_Widget_Exception(_t('無效的操作'));
        }
    }
}
