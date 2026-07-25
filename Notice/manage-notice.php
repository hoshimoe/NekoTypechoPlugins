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

// 由舊版本升級而來時補上多語言欄位
$i18nReady = NekoTypechoNotice_Plugin::ensureI18nColumns();

// 編輯中的通知已有的語言版本
$editTranslations = $editNotice ? NekoTypechoNotice_Plugin::translations($editNotice) : array();
$siteLang = NekoTypechoNotice_I18n::normalize(NekoTypechoNotice_I18n::siteLang());

function _tn_h($value)
{
    return htmlspecialchars($value === NULL ? '' : $value, ENT_QUOTES);
}
?>

<datalist id="neko-typecho-notice-langs">
    <?php foreach (NekoTypechoNotice_I18n::commonTags() as $tag): ?>
    <option value="<?php echo _tn_h($tag); ?>"></option>
    <?php endforeach; ?>
</datalist>

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

                    <?php if (!$i18nReady): ?>
                    <p class="description">
                        多語言功能需要 <code>notice</code> 資料表中的 <code>default_lang</code> 與 <code>i18n</code> 欄位。
                        自動升級未成功，請在後台「插件」頁面將本插件停用後再啟用（停用不會刪除既有通知）。
                    </p>
                    <?php else: ?>
                    <ul class="typecho-option">
                        <li>
                            <label class="typecho-label" for="default_lang">主要語言</label>
                            <input type="text" id="default_lang" name="default_lang" list="neko-typecho-notice-langs"
                                value="<?php echo _tn_h(isset($editNotice['default_lang']) ? $editNotice['default_lang'] : ''); ?>" />
                            <p class="description">
                                上方標題與內容所使用的語言。留空則視為站點語言（目前為 <code><?php echo _tn_h($siteLang); ?></code>）。
                            </p>
                        </li>
                    </ul>

                    <h4>其他語言版本</h4>
                    <p class="description">
                        僅需填寫需要翻譯的語言。訪客語言沒有對應版本時，
                        中文簡繁互為缺省後備，其餘語言優先回退英文，最後回退到任意一個有內容的版本。
                        標題與內容分別回退，因此只翻譯內容、不填標題時會沿用主要語言的標題。
                    </p>

                    <div id="notice-i18n-list">
                        <?php foreach ($editTranslations as $lang => $item): ?>
                        <div class="notice-i18n-item">
                            <ul class="typecho-option">
                                <li>
                                    <label class="typecho-label">語言代碼</label>
                                    <input type="text" name="i18n_lang[]" list="neko-typecho-notice-langs"
                                        value="<?php echo _tn_h($lang); ?>" placeholder="例如 en、ja、zh-TW" />
                                    <button type="button" class="btn btn-s notice-i18n-remove">移除</button>
                                </li>
                            </ul>
                            <ul class="typecho-option">
                                <li>
                                    <label class="typecho-label">標題</label>
                                    <input type="text" name="i18n_title[]" class="w-100" value="<?php echo _tn_h($item['title']); ?>" />
                                </li>
                            </ul>
                            <ul class="typecho-option">
                                <li>
                                    <label class="typecho-label">內容</label>
                                    <textarea name="i18n_content[]" class="w-100" rows="3"><?php echo _tn_h($item['content']); ?></textarea>
                                </li>
                            </ul>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <ul class="typecho-option">
                        <li><button type="button" class="btn btn-s" id="notice-i18n-add">新增語言版本</button></li>
                    </ul>
                    <?php endif; ?>

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
                            <a href="<?php echo $adminUrl; ?>extending.php?panel=NekoTypechoNotice/manage-notice.php" class="btn">取消</a>
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
                                    <a href="<?php echo $adminUrl; ?>extending.php?panel=NekoTypechoNotice/manage-notice.php&edit=<?php echo $notice['id']; ?>">
                                        <?php echo htmlspecialchars($notice['title'] ?: '(無標題)'); ?>
                                    </a>
                                    <?php
                                    $langs = array(NekoTypechoNotice_I18n::normalize(isset($notice['default_lang']) ? $notice['default_lang'] : '') ?: $siteLang);
                                    foreach (array_keys(NekoTypechoNotice_Plugin::translations($notice)) as $lang) {
                                        if (!in_array($lang, $langs, true)) {
                                            $langs[] = $lang;
                                        }
                                    }
                                    ?>
                                    <br /><span class="description"><?php echo _tn_h(implode(' / ', $langs)); ?></span>
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

<script type="text/html" id="notice-i18n-proto">
    <div class="notice-i18n-item">
        <ul class="typecho-option">
            <li>
                <label class="typecho-label">語言代碼</label>
                <input type="text" name="i18n_lang[]" list="neko-typecho-notice-langs" value="" placeholder="例如 en、ja、zh-TW" />
                <button type="button" class="btn btn-s notice-i18n-remove">移除</button>
            </li>
        </ul>
        <ul class="typecho-option">
            <li>
                <label class="typecho-label">標題</label>
                <input type="text" name="i18n_title[]" class="w-100" value="" />
            </li>
        </ul>
        <ul class="typecho-option">
            <li>
                <label class="typecho-label">內容</label>
                <textarea name="i18n_content[]" class="w-100" rows="3"></textarea>
            </li>
        </ul>
    </div>
</script>

<script>
(function () {
    var list = document.getElementById('notice-i18n-list');
    var addButton = document.getElementById('notice-i18n-add');
    var proto = document.getElementById('notice-i18n-proto');

    if (!list || !addButton || !proto) {
        return;
    }

    addButton.addEventListener('click', function () {
        var wrapper = document.createElement('div');
        wrapper.innerHTML = proto.innerHTML;

        var item = wrapper.querySelector('.notice-i18n-item');
        if (item) {
            list.appendChild(item);
            var input = item.querySelector('input[name="i18n_lang[]"]');
            if (input) {
                input.focus();
            }
        }
    });

    list.addEventListener('click', function (event) {
        if (!event.target || event.target.className.indexOf('notice-i18n-remove') < 0) {
            return;
        }

        var item = event.target;
        while (item && item.className.indexOf('notice-i18n-item') < 0) {
            item = item.parentNode;
        }

        if (item && item.parentNode) {
            item.parentNode.removeChild(item);
        }
    });
})();
</script>

<style>
.notice-i18n-item {
    border-left: 3px solid #ddd;
    padding-left: 12px;
    margin-bottom: 12px;
}
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
