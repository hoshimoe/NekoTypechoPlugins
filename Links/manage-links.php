<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

include 'header.php';
include 'menu.php';

$db = Typecho_Db::get();
$prefix = $db->getPrefix();
$options = Helper::options();
$adminUrl = $options->adminUrl;
$actionUrl = $options->index . '/action/links-edit';
$security = Typecho_Widget::widget('Widget_Security');

// 判斷是否為編輯模式
$editId = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$editLink = null;
if ($editId) {
    $editLink = $db->fetchRow($db->select()->from('table.friendlinks')->where('id = ?', $editId));
}

// 獲取所有友鏈
$links = $db->fetchAll($db->select()->from('table.friendlinks')
    ->order('priority', Typecho_Db::SORT_DESC)
    ->order('sort_order', Typecho_Db::SORT_ASC)
    ->order('id', Typecho_Db::SORT_ASC));

function _tl_h($value)
{
    return htmlspecialchars($value ?? '', ENT_QUOTES);
}
?>

<div class="main">
    <div class="body container">
        <?php include 'page-title.php'; ?>
        <div class="row typecho-page-main" role="main">
            <div class="col-mb-12 col-tb-8" role="form">
                <h3><?php echo $editLink ? '編輯友鏈' : '新增友鏈'; ?></h3>
                <form action="<?php echo $actionUrl; ?>" method="post">
                    <input type="hidden" name="do" value="<?php echo $editLink ? 'update' : 'add'; ?>" />
                    <?php if ($editLink): ?>
                    <input type="hidden" name="id" value="<?php echo intval($editLink['id']); ?>" />
                    <?php endif; ?>
                    <?php $security->token(); ?>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="name">名稱</label>
                            <input type="text" id="name" name="name" class="w-100" required
                                value="<?php echo _tl_h($editLink ? $editLink['name'] : ''); ?>" />
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="url">網址</label>
                            <input type="url" id="url" name="url" class="w-100" required
                                value="<?php echo _tl_h($editLink ? $editLink['url'] : ''); ?>" />
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="image">頭像/Logo</label>
                            <input type="url" id="image" name="image" class="w-100"
                                value="<?php echo _tl_h($editLink ? $editLink['image'] : ''); ?>" />
                            <p class="description">圖片網址（可選）</p>
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="description">描述</label>
                            <textarea id="description" name="description" class="w-100" rows="2"><?php echo _tl_h($editLink ? $editLink['description'] : ''); ?></textarea>
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="category">分類</label>
                            <input type="text" id="category" name="category" class="w-100"
                                value="<?php echo _tl_h($editLink ? $editLink['category'] : ''); ?>" />
                            <p class="description">相同分類的友鏈會被分組顯示（可選）</p>
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="rss">RSS 地址</label>
                            <input type="url" id="rss" name="rss" class="w-100"
                                value="<?php echo _tl_h($editLink ? $editLink['rss'] : ''); ?>" />
                            <p class="description">填寫後，前端會由訪客瀏覽器擷取並顯示其最新文章（可選）</p>
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="priority">優先級</label>
                            <input type="number" id="priority" name="priority"
                                value="<?php echo $editLink ? intval($editLink['priority']) : 0; ?>" />
                            <p class="description">數字越大越靠前</p>
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="sort_order">排序值</label>
                            <input type="number" id="sort_order" name="sort_order"
                                value="<?php echo $editLink ? intval($editLink['sort_order']) : 0; ?>" />
                            <p class="description">同優先級下，數字越小越靠前</p>
                        </li>
                    </ul>

                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="visible">顯示狀態</label>
                            <select id="visible" name="visible">
                                <option value="1" <?php echo (!$editLink || $editLink['visible']) ? 'selected' : ''; ?>>顯示</option>
                                <option value="0" <?php echo ($editLink && !$editLink['visible']) ? 'selected' : ''; ?>>隱藏</option>
                            </select>
                        </li>
                    </ul>

                    <ul class="typecho-option typecho-option-submit">
                        <li>
                            <button type="submit" class="btn primary"><?php echo $editLink ? '更新友鏈' : '新增友鏈'; ?></button>
                            <?php if ($editLink): ?>
                            <a href="<?php echo $adminUrl; ?>extending.php?panel=NekoTypechoLinks/manage-links.php" class="btn">取消</a>
                            <?php endif; ?>
                        </li>
                    </ul>
                </form>

                <?php if (!$editLink): ?>
                <h3>匯入友鏈</h3>
                <form action="<?php echo $actionUrl; ?>" method="post">
                    <input type="hidden" name="do" value="import" />
                    <input type="hidden" name="source" value="text" />
                    <?php $security->token(); ?>
                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="importText">批量匯入</label>
                            <textarea id="importText" name="importText" class="w-100" rows="5" placeholder="每行一筆：名稱|網址|頭像|描述|分類|RSS&#10;或貼上 JSON 陣列"></textarea>
                            <p class="description">每行格式：<code>名稱|網址|頭像|描述|分類|RSS</code>，也支持 JSON 陣列格式。</p>
                        </li>
                    </ul>
                    <ul class="typecho-option typecho-option-submit">
                        <li><button type="submit" class="btn primary">匯入文字</button></li>
                    </ul>
                </form>

                <form action="<?php echo $actionUrl; ?>" method="post" onsubmit="return confirm('確定從官方 Links 插件資料表匯入？');">
                    <input type="hidden" name="do" value="import" />
                    <input type="hidden" name="source" value="typecho" />
                    <?php $security->token(); ?>
                    <ul class="typecho-option typecho-option-submit">
                        <li>
                            <button type="submit" class="btn">從官方 Links 插件匯入</button>
                            <p class="description">從 Typecho 官方 Links 插件的 <code><?php echo _tl_h($prefix); ?>links</code> 資料表匯入現有友鏈。</p>
                        </li>
                    </ul>
                </form>
                <?php endif; ?>
            </div>

            <div class="col-mb-12 col-tb-4" role="complementary">
                <h3>友鏈列表（<?php echo count($links); ?>）</h3>
                <?php if (empty($links)): ?>
                <p>暫無友鏈</p>
                <?php else: ?>
                <div class="typecho-list-operate clearfix">
                    <table class="typecho-list-table">
                        <thead>
                            <tr>
                                <th>名稱</th>
                                <th>分類</th>
                                <th>狀態</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($links as $link): ?>
                            <tr <?php echo (!$link['visible']) ? 'style="opacity:0.5"' : ''; ?>>
                                <td>
                                    <a href="<?php echo $adminUrl; ?>extending.php?panel=NekoTypechoLinks/manage-links.php&edit=<?php echo intval($link['id']); ?>">
                                        <?php echo _tl_h($link['name'] ?: '(無名稱)'); ?>
                                    </a>
                                </td>
                                <td><?php echo _tl_h($link['category'] ?: '—'); ?></td>
                                <td><?php echo $link['visible'] ? '顯示' : '隱藏'; ?></td>
                                <td>
                                    <a href="<?php echo $actionUrl; ?>?do=toggle&id=<?php echo intval($link['id']); ?>&_=<?php echo $security->getToken($actionUrl . '?do=toggle&id=' . $link['id']); ?>">
                                        <?php echo $link['visible'] ? '隱藏' : '顯示'; ?>
                                    </a>
                                    |
                                    <a href="<?php echo $actionUrl; ?>?do=delete&id=<?php echo intval($link['id']); ?>&_=<?php echo $security->getToken($actionUrl . '?do=delete&id=' . $link['id']); ?>"
                                       onclick="return confirm('確定刪除此友鏈？')">刪除</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <h3>調用方法</h3>
                <p class="description">在文章或獨立頁面中插入短代碼：</p>
                <p><code>[friendlinks]</code></p>
                <p class="description">可帶屬性：<code>[friendlinks category="技術" order="random" limit="10" rss="1"]</code></p>
                <p class="description">主題中調用：</p>
                <pre style="white-space:pre-wrap;">&lt;?php echo NekoTypechoLinks_Plugin::render(); ?&gt;</pre>
            </div>
        </div>
    </div>
</div>

<?php
include 'copyright.php';
include 'common-js.php';
include 'footer.php';
?>
