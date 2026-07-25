<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

include 'header.php';
include 'menu.php';

$db = Typecho_Db::get();
$prefix = $db->getPrefix();
$options = Helper::options();
$adminUrl = $options->adminUrl;
$actionUrl = $options->index . '/action/comment-filter';
$security = Typecho_Widget::widget('Widget_Security');
$config = TypechoCommentFilter_Plugin::pluginConfig();

$pageSize = 20;
$page = max(1, intval(isset($_GET['page']) ? $_GET['page'] : 1));

$total = $db->fetchObject($db->select(array('COUNT(id)' => 'num'))->from('table.comment_filter_log'))->num;
$totalPages = max(1, ceil($total / $pageSize));
$page = min($page, $totalPages);

$logs = $db->fetchAll($db->select()->from('table.comment_filter_log')
    ->order('created', Typecho_Db::SORT_DESC)
    ->page($page, $pageSize));

$todayStart = mktime(0, 0, 0);
$todayCount = $db->fetchObject($db->select(array('COUNT(id)' => 'num'))->from('table.comment_filter_log')
    ->where('created >= ?', $todayStart))->num;
$weekCount = $db->fetchObject($db->select(array('COUNT(id)' => 'num'))->from('table.comment_filter_log')
    ->where('created >= ?', time() - 7 * 86400))->num;

// 取出對應文章標題
$titles = array();
$cids = array();
foreach ($logs as $log) {
    if ($log['cid'] > 0) {
        $cids[] = intval($log['cid']);
    }
}
if (!empty($cids)) {
    $rows = $db->fetchAll($db->select('cid', 'title')->from('table.contents')
        ->where('cid IN (' . implode(',', array_unique($cids)) . ')'));
    foreach ($rows as $row) {
        $titles[$row['cid']] = $row['title'];
    }
}

// 規則測試
$testText = isset($_POST['testText']) ? (string) $_POST['testText'] : '';
$testResult = null;
if ('' !== trim($testText)) {
    $testResult = TypechoCommentFilter_Plugin::inspectText($testText, $config);
}

function _tcf_h($value)
{
    return htmlspecialchars($value === NULL ? '' : $value, ENT_QUOTES);
}

function _tcf_excerpt($value, $length = 140)
{
    $value = trim(preg_replace('/\s+/u', ' ', (string) $value));
    if (function_exists('mb_substr') && mb_strlen($value, 'UTF-8') > $length) {
        return mb_substr($value, 0, $length, 'UTF-8') . '…';
    }
    return $value;
}

$ruleLabels = array(
    'link'    => '連結',
    'keyword' => '關鍵詞',
    'mail'    => '郵件地址'
);
$fieldLabels = array(
    'text'   => '評論內容',
    'author' => '暱稱',
    'url'    => '個人主頁'
);
$handledLabels = array(
    'reject'  => '已拒絕',
    'spam'    => '標記垃圾',
    'waiting' => '標記待審'
);
?>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 col-tb-8">
                <h3>攔截記錄</h3>
                <p class="description">
                    今日 <strong><?php echo intval($todayCount); ?></strong> 筆 ·
                    近七日 <strong><?php echo intval($weekCount); ?></strong> 筆 ·
                    共 <strong><?php echo intval($total); ?></strong> 筆
                </p>

                <?php if (empty($logs)): ?>
                <p>暫無攔截記錄。</p>
                <?php else: ?>
                <div class="typecho-list-operate clearfix">
                    <table class="typecho-list-table">
                        <thead>
                            <tr>
                                <th>時間 / 來源</th>
                                <th>命中</th>
                                <th>內容</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                            <tr<?php echo ('restored' === $log['state']) ? ' style="opacity:0.55"' : ''; ?>>
                                <td>
                                    <?php echo date('Y-m-d H:i', $log['created'] + $options->timezone - $options->serverTimezone); ?><br />
                                    <span class="description">
                                        <?php echo _tcf_h($log['author'] ?: '(匿名)'); ?>
                                        <?php if ($log['mail']): ?>&lt;<?php echo _tcf_h($log['mail']); ?>&gt;<?php endif; ?>
                                        <br /><?php echo _tcf_h($log['ip']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo isset($ruleLabels[$log['rule']]) ? $ruleLabels[$log['rule']] : _tcf_h($log['rule']); ?>
                                    <br /><span class="description"><?php echo isset($fieldLabels[$log['field']]) ? $fieldLabels[$log['field']] : _tcf_h($log['field']); ?></span>
                                    <br /><code><?php echo _tcf_h(_tcf_excerpt($log['sample'], 40)); ?></code>
                                </td>
                                <td>
                                    <?php if (isset($titles[$log['cid']])): ?>
                                    <span class="description">於《<?php echo _tcf_h($titles[$log['cid']]); ?>》</span><br />
                                    <?php endif; ?>
                                    <?php echo _tcf_h(_tcf_excerpt($log['text'])); ?>
                                </td>
                                <td>
                                    <span class="description"><?php echo isset($handledLabels[$log['handled']]) ? $handledLabels[$log['handled']] : _tcf_h($log['handled']); ?></span><br />
                                    <?php if ('restored' === $log['state']): ?>
                                    已還原
                                    <?php else: ?>
                                    <a href="<?php echo _tcf_h($security->getTokenUrl($actionUrl . '?do=restore&status=waiting&page=' . $page . '&id=' . intval($log['id']))); ?>">還原待審</a>
                                    |
                                    <a href="<?php echo _tcf_h($security->getTokenUrl($actionUrl . '?do=restore&status=approved&page=' . $page . '&id=' . intval($log['id']))); ?>">還原並通過</a>
                                    <br />
                                    <?php endif; ?>
                                    <a href="<?php echo _tcf_h($security->getTokenUrl($actionUrl . '?do=delete&page=' . $page . '&id=' . intval($log['id']))); ?>"
                                       onclick="return confirm('確定刪除此記錄？')">刪除</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <ul class="typecho-pager">
                    <?php if ($page > 1): ?>
                    <li class="prev"><a href="<?php echo $adminUrl; ?>extending.php?panel=TypechoCommentFilter/manage-comment-filter.php&page=<?php echo $page - 1; ?>">上一頁</a></li>
                    <?php endif; ?>
                    <li class="current">第 <?php echo $page; ?> / <?php echo $totalPages; ?> 頁</li>
                    <?php if ($page < $totalPages): ?>
                    <li class="next"><a href="<?php echo $adminUrl; ?>extending.php?panel=TypechoCommentFilter/manage-comment-filter.php&page=<?php echo $page + 1; ?>">下一頁</a></li>
                    <?php endif; ?>
                </ul>
                <?php endif; ?>

                <form action="<?php echo $actionUrl; ?>" method="post" onsubmit="return confirm('確定清空全部攔截記錄？此操作無法復原。');">
                    <input type="hidden" name="do" value="clear" />
                    <?php $security->token(); ?>
                    <ul class="typecho-option typecho-option-submit">
                        <li>
                            <button type="submit" class="btn">清空全部記錄</button>
                            <a class="btn" href="<?php echo _tcf_h($security->getTokenUrl($actionUrl . '?do=clean')); ?>">清理過期記錄</a>
                        </li>
                    </ul>
                </form>
                <?php endif; ?>
            </div>

            <div class="col-mb-12 col-tb-4" role="complementary">
                <h3>當前設置</h3>
                <ul class="typecho-option">
                    <li>
                        <p class="description">
                            處理方式：<strong><?php
                                $actionLabels = array('reject' => '直接拒絕（不入庫、不發信）', 'spam' => '標記為垃圾', 'waiting' => '標記為待審核');
                                $current = TypechoCommentFilter_Plugin::option($config, 'action', 'reject');
                                echo isset($actionLabels[$current]) ? $actionLabels[$current] : _tcf_h($current);
                            ?></strong><br />
                            允許連結數：<strong><?php echo intval(TypechoCommentFilter_Plugin::option($config, 'maxLinks', 0)); ?></strong><br />
                            裸域名偵測：<strong><?php echo TypechoCommentFilter_Plugin::option($config, 'detectBare', '1') === '1' ? '開啟' : '關閉'; ?></strong><br />
                            混淆偵測：<strong><?php echo TypechoCommentFilter_Plugin::option($config, 'detectObfuscated', '1') === '1' ? '開啟' : '關閉'; ?></strong><br />
                            白名單：<?php
                                $whitelist = TypechoCommentFilter_Plugin::whitelist($config);
                                echo empty($whitelist) ? '（無）' : _tcf_h(implode('、', $whitelist));
                            ?>
                        </p>
                        <p><a class="btn btn-s" href="<?php echo $adminUrl; ?>options-plugin.php?config=TypechoCommentFilter">前往插件設置</a></p>
                    </li>
                </ul>

                <h3>規則測試</h3>
                <form action="<?php echo $adminUrl; ?>extending.php?panel=TypechoCommentFilter/manage-comment-filter.php" method="post">
                    <?php $security->token(); ?>
                    <ul class="typecho-option">
                        <li>
                            <textarea name="testText" class="w-100" rows="4" placeholder="貼上一段評論內容，檢查是否會被攔截"><?php echo _tcf_h($testText); ?></textarea>
                        </li>
                    </ul>
                    <ul class="typecho-option typecho-option-submit">
                        <li><button type="submit" class="btn primary btn-s">測試</button></li>
                    </ul>
                </form>

                <?php if ('' !== trim($testText)): ?>
                <p class="description">
                    <?php if (NULL === $testResult): ?>
                    結果：<strong>放行</strong>
                    <?php else: ?>
                    結果：<strong>攔截</strong><br />
                    命中規則：<?php echo isset($ruleLabels[$testResult['rule']]) ? $ruleLabels[$testResult['rule']] : _tcf_h($testResult['rule']); ?><br />
                    命中內容：<code><?php echo _tcf_h($testResult['sample']); ?></code><br />
                    拒絕提示：<?php echo _tcf_h(TypechoCommentFilter_Plugin::rejectMessage($testResult, $config)); ?>
                    <?php endif; ?>
                </p>
                <?php endif; ?>

                <h3>說明</h3>
                <p class="description">
                    「直接拒絕」模式下，垃圾評論不會寫入資料庫，因此不會出現在待審核列表，
                    郵件通知插件也不會發信；內容仍保留於此頁，可隨時複查與還原。
                </p>
                <p class="description">
                    誤攔的評論可「還原並通過」使其直接顯示；若某個網域經常被誤判，
                    請將其加入插件設置中的網域白名單。
                </p>
            </div>
        </div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
include 'footer.php';
?>
