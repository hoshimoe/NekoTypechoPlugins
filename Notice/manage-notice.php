<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

include 'header.php';
include 'menu.php';

$db = Typecho_Db::get();
$prefix = $db->getPrefix();
$options = Helper::options();
$adminUrl = $options->adminUrl;
$actionUrl = $options->index . '/action/notice-edit';
$security = Typecho_Widget::widget('Widget_Security');

// 判斷是否為編輯模式
$editId = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$editNotice = null;
if ($editId) {
    $editNotice = $db->fetchRow($db->select()->from('table.notice')->where('id = ?', $editId));
}

// 獲取所有通知
$notices = $db->fetchAll($db->select()->from('table.notice')->order('order_num', Typecho_Db::SORT_ASC)->order('created', Typecho_Db::SORT_DESC));
?>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 col-tb-8" role="form">
                <h3><?php echo $editNotice ? '編輯通知' : '新增通知'; ?></h3>
                <form action="<?php echo $actionUrl; ?>" method="post">
                    <input type="hidden" name="do" value="<?php echo $editNotice ? 'update' : 'add'; ?>" />
                    <?php if ($editNotice): ?>
                    <input type="hidden" name="id" value="<?php echo $editNotice['id']; ?>" />
                    <?php endif; ?>
                    <?php $security->token(); ?>
                    
                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="title">標題</label>
                            <input type="text" id="title" name="title" class="w-100" 
                                value="<?php echo htmlspecialchars($editNotice ? $editNotice['title'] : ''); ?>" />
                            <p class="description">通知標題（可選）</p>
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="content">內容</label>
                            <textarea id="content" name="content" class="w-100" rows="5"><?php echo htmlspecialchars($editNotice ? $editNotice['content'] : ''); ?></textarea>
                            <p class="description">通知內容，支持HTML</p>
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="type">類型</label>
                            <select id="type" name="type">
                                <option value="info" <?php echo ($editNotice && $editNotice['type'] == 'info') ? 'selected' : ''; ?>>資訊</option>
                                <option value="success" <?php echo ($editNotice && $editNotice['type'] == 'success') ? 'selected' : ''; ?>>成功</option>
                                <option value="warning" <?php echo ($editNotice && $editNotice['type'] == 'warning') ? 'selected' : ''; ?>>警告</option>
                                <option value="error" <?php echo ($editNotice && $editNotice['type'] == 'error') ? 'selected' : ''; ?>>錯誤</option>
                            </select>
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="visible">顯示狀態</label>
                            <select id="visible" name="visible">
                                <option value="1" <?php echo (!$editNotice || $editNotice['visible']) ? 'selected' : ''; ?>>顯示</option>
                                <option value="0" <?php echo ($editNotice && !$editNotice['visible']) ? 'selected' : ''; ?>>隱藏</option>
                            </select>
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="start_time">開始時間</label>
                            <input type="datetime-local" id="start_time" name="start_time" 
                                value="<?php echo ($editNotice && $editNotice['start_time']) ? date('Y-m-d\TH:i', $editNotice['start_time']) : ''; ?>" />
                            <p class="description">留空表示立即開始</p>
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="end_time">結束時間</label>
                            <input type="datetime-local" id="end_time" name="end_time" 
                                value="<?php echo ($editNotice && $editNotice['end_time']) ? date('Y-m-d\TH:i', $editNotice['end_time']) : ''; ?>" />
                            <p class="description">留空表示永不過期</p>
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="order_num">排序</label>
                            <input type="number" id="order_num" name="order_num" 
                                value="<?php echo $editNotice ? $editNotice['order_num'] : 0; ?>" />
                            <p class="description">數字越小越靠前</p>
                        </li>
                    </ul>

                    <ul class="typecho-option typecho-option-submit">
                        <li>
                            <button type="submit" class="btn primary"><?php echo $editNotice ? '更新通知' : '新增通知'; ?></button>
                            <?php if ($editNotice): ?>
                            <a href="<?php echo $adminUrl; ?>extending.php?panel=TypechoNotice/manage-notice.php" class="btn">取消</a>
                            <?php endif; ?>
                        </li>
                    </ul>
                </form>
            </div>

            <div class="col-mb-12 col-tb-4" role="complementary">
                <h3>通知列表</h3>
                <?php if (empty($notices)): ?>
                <p>暫無通知</p>
                <?php else: ?>
                <div class="typecho-list-operate clearfix">
                    <table class="typecho-list-table">
                        <thead>
                            <tr>
                                <th>標題</th>
                                <th>類型</th>
                                <th>狀態</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notices as $notice): ?>
                            <tr <?php echo (!$notice['visible']) ? 'style="opacity:0.5"' : ''; ?>>
                                <td>
                                    <a href="<?php echo $adminUrl; ?>extending.php?panel=TypechoNotice/manage-notice.php&edit=<?php echo $notice['id']; ?>">
                                        <?php echo htmlspecialchars($notice['title'] ?: '(無標題)'); ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="notice-type-badge notice-type-<?php echo $notice['type']; ?>">
                                        <?php
                                        $typeLabels = array('info' => '資訊', 'success' => '成功', 'warning' => '警告', 'error' => '錯誤');
                                        echo $typeLabels[$notice['type']] ?? $notice['type'];
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo $notice['visible'] ? '顯示' : '隱藏'; ?></td>
                                <td>
                                    <a href="<?php echo $actionUrl; ?>?do=toggle&id=<?php echo $notice['id']; ?>&_=<?php echo $security->getToken($actionUrl . '?do=toggle&id=' . $notice['id']); ?>">
                                        <?php echo $notice['visible'] ? '隱藏' : '顯示'; ?>
                                    </a>
                                    |
                                    <a href="<?php echo $actionUrl; ?>?do=delete&id=<?php echo $notice['id']; ?>&_=<?php echo $security->getToken($actionUrl . '?do=delete&id=' . $notice['id']); ?>" 
                                       onclick="return confirm('確定刪除此通知？')">刪除</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.notice-type-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 12px;
    color: #fff;
}
.notice-type-info { background: #3498db; }
.notice-type-success { background: #27ae60; }
.notice-type-warning { background: #f39c12; }
.notice-type-error { background: #e74c3c; }
</style>

<?php
include 'copyright.php';
include 'common-js.php';
include 'footer.php';
?>
