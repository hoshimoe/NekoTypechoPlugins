<?php
/**
 * 多語言文本解析與語言回退
 *
 * 回退順序：
 *   1. 訪客語言精確匹配（zh-TW → zh-TW）
 *   2. 中文簡繁互為缺省後備（繁體缺失回退簡體，簡體缺失回退繁體）
 *   3. 同語系的其他地區變體（pt-BR → pt-PT）
 *   4. 未標記語言的缺省版本
 *   5. 英文（en）
 *   6. 任意一個有內容的版本
 *
 * 本文件在 TypechoNotice 與其他子插件中各有一份副本，
 * 以保證每個子插件都能獨立部署，修改時請同步。
 *
 * @package TypechoNotice
 * @author Hoshi
 * @link https://github.com/moehoshio/NekoTypechoPlugins
 */

if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class TypechoNotice_I18n
{
    /**
     * 未標記語言的缺省版本所使用的鍵名
     */
    const DEFAULT_KEY = '*';

    /**
     * 簡體中文語言標籤（含泛用的 zh）
     */
    private static $hans = array('zh-cn', 'zh-hans', 'zh-sg', 'zh-my', 'zh');

    /**
     * 繁體中文語言標籤
     */
    private static $hant = array('zh-tw', 'zh-hant', 'zh-hk', 'zh-mo');

    /**
     * 標準化語言標籤：轉小寫、底線轉連字號、剔除非法值
     *
     * @param string $tag
     * @return string 非法標籤回傳空字串
     */
    public static function normalize($tag)
    {
        $tag = str_replace('_', '-', strtolower(trim((string) $tag)));

        if (self::DEFAULT_KEY === $tag) {
            return $tag;
        }

        return preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/', $tag) ? $tag : '';
    }

    /**
     * 取得語言標籤的主語言（zh-tw → zh）
     *
     * @param string $tag
     * @return string
     */
    public static function baseTag($tag)
    {
        $tag = self::normalize($tag);
        if ('' === $tag || self::DEFAULT_KEY === $tag) {
            return $tag;
        }

        $pos = strpos($tag, '-');
        return false === $pos ? $tag : substr($tag, 0, $pos);
    }

    /**
     * 判斷中文標籤使用的書寫系統
     *
     * @param string $tag 已標準化的標籤
     * @return string hant 或 hans
     */
    private static function chineseScript($tag)
    {
        if (false !== strpos($tag, 'hant') || in_array($tag, array('zh-tw', 'zh-hk', 'zh-mo'), true)) {
            return 'hant';
        }

        return 'hans';
    }

    /**
     * 產生單一語言的回退鏈
     *
     * 中文簡繁互為缺省後備：先嘗試同書寫系統的各地區標籤，再嘗試另一書寫系統。
     *
     * @param string $tag
     * @return array
     */
    public static function chain($tag)
    {
        $tag = self::normalize($tag);
        if ('' === $tag || self::DEFAULT_KEY === $tag) {
            return array();
        }

        $chain = array($tag);

        if ('zh' === self::baseTag($tag)) {
            $sameScript  = ('hant' === self::chineseScript($tag)) ? self::$hant : self::$hans;
            $otherScript = ('hant' === self::chineseScript($tag)) ? self::$hans : self::$hant;
            foreach (array_merge($sameScript, $otherScript) as $item) {
                $chain[] = $item;
            }
        } else {
            $chain[] = self::baseTag($tag);
        }

        return array_values(array_unique($chain));
    }

    /**
     * 依語言偏好從多語言映射中取出對應內容
     *
     * @param array $map 語言標籤 => 內容（內容可為字串或陣列）
     * @param array|string|null $langs 語言偏好，null 表示自動偵測
     * @return mixed 無任何內容時回傳 null
     */
    public static function resolve($map, $langs = null)
    {
        $map = self::compact($map);
        if (empty($map)) {
            return null;
        }

        if (null === $langs) {
            $langs = self::detect();
        }

        foreach ((array) $langs as $lang) {
            foreach (self::chain($lang) as $candidate) {
                if (isset($map[$candidate])) {
                    return $map[$candidate];
                }
            }

            // 同語系的其他地區變體
            $base = self::baseTag($lang);
            if ('' !== $base && self::DEFAULT_KEY !== $base) {
                foreach ($map as $key => $value) {
                    if (self::baseTag($key) === $base) {
                        return $value;
                    }
                }
            }
        }

        // 未標記語言的缺省版本
        if (isset($map[self::DEFAULT_KEY])) {
            return $map[self::DEFAULT_KEY];
        }

        // 回退英文
        foreach ($map as $key => $value) {
            if ('en' === self::baseTag($key)) {
                return $value;
            }
        }

        // 回退到任意一個有內容的版本
        return reset($map);
    }

    /**
     * 解析多語言文本，支持三種寫法：
     *
     *   1. JSON 物件：{"zh-TW": "內容", "en": "content"}
     *   2. 逐行標記：`zh-TW: 內容`，未帶語言標記的後續行視為同一語言的續行
     *   3. 純文字：整段作為缺省版本，對所有語言生效
     *
     * @param string $raw
     * @return array 語言標籤 => 文本
     */
    public static function parse($raw)
    {
        $raw = trim((string) $raw);
        if ('' === $raw) {
            return array();
        }

        if (0 === strpos($raw, '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return self::compact($decoded);
            }
        }

        $map = array();
        $current = self::DEFAULT_KEY;

        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            if (preg_match('/^\s*\[?([A-Za-z]{2,3}(?:[-_][A-Za-z0-9]{2,8})*)\]?\s*(?::|：)\s*(.*)$/u', $line, $matches)) {
                $tag = self::normalize($matches[1]);
                $current = '' === $tag ? self::DEFAULT_KEY : $tag;
                $line = $matches[2];
            }

            $map[$current] = isset($map[$current]) ? $map[$current] . "\n" . $line : $line;
        }

        return self::compact($map);
    }

    /**
     * 解析並取出對應語言的文本
     *
     * @param string $raw
     * @param array|string|null $langs
     * @return string
     */
    public static function text($raw, $langs = null)
    {
        $value = self::resolve(self::parse($raw), $langs);
        return null === $value ? '' : (string) $value;
    }

    /**
     * 偵測語言偏好，依優先順序回傳語言標籤列表
     *
     * 站點語言僅在完全偵測不到訪客語言時作為兜底，否則日文訪客會因為
     * 站點語言是中文而先拿到中文，而非規則所要求的英文。
     *
     * @param string $source auto（網址參數 → Cookie → 瀏覽器 → 站點）
     *                       browser（瀏覽器 → 站點）
     *                       site（僅站點語言）
     * @return array
     */
    public static function detect($source = 'auto')
    {
        $langs = array();

        if ('site' !== $source) {
            if ('auto' === $source) {
                if (isset($_GET['lang'])) {
                    $langs[] = $_GET['lang'];
                }
                if (isset($_COOKIE['typecho_lang'])) {
                    $langs[] = $_COOKIE['typecho_lang'];
                }
            }

            foreach (self::acceptLanguages() as $tag) {
                $langs[] = $tag;
            }
        }

        $result = array();
        foreach ($langs as $lang) {
            $lang = self::normalize($lang);
            if ('' !== $lang && self::DEFAULT_KEY !== $lang && !in_array($lang, $result, true)) {
                $result[] = $lang;
            }
        }

        if (empty($result)) {
            $siteLang = self::normalize(self::siteLang());
            if ('' !== $siteLang && self::DEFAULT_KEY !== $siteLang) {
                $result[] = $siteLang;
            }
        }

        return $result;
    }

    /**
     * 取得站點語言，未設定時視為簡體中文（Typecho 預設語言）
     *
     * @return string
     */
    public static function siteLang()
    {
        try {
            $options = Helper::options();
            $lang = $options ? (string) $options->lang : '';
        } catch (Exception $e) {
            $lang = '';
        }

        return '' === $lang ? 'zh-cn' : $lang;
    }

    /**
     * 解析 Accept-Language 標頭，依 q 值排序
     *
     * @return array
     */
    private static function acceptLanguages()
    {
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return array();
        }

        $items = array();
        $index = 0;

        foreach (explode(',', substr((string) $_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 512)) as $part) {
            $quality = 1.0;

            if (false !== strpos($part, ';')) {
                list($part, $params) = explode(';', $part, 2);
                if (preg_match('/q\s*=\s*([0-9.]+)/i', $params, $matches)) {
                    $quality = (float) $matches[1];
                }
            }

            $tag = self::normalize($part);
            if ('' === $tag || self::DEFAULT_KEY === $tag) {
                continue;
            }

            $items[] = array('tag' => $tag, 'quality' => $quality, 'index' => $index++);
        }

        usort($items, array(__CLASS__, 'compareQuality'));

        $tags = array();
        foreach ($items as $item) {
            $tags[] = $item['tag'];
        }

        return $tags;
    }

    /**
     * Accept-Language 排序：q 值大者優先，相同時維持原順序
     */
    public static function compareQuality($a, $b)
    {
        if ($a['quality'] === $b['quality']) {
            return $a['index'] - $b['index'];
        }

        return ($a['quality'] < $b['quality']) ? 1 : -1;
    }

    /**
     * 清理多語言映射：標準化鍵名、剔除空內容
     *
     * @param array $map
     * @return array
     */
    private static function compact($map)
    {
        $result = array();

        if (!is_array($map)) {
            return $result;
        }

        foreach ($map as $key => $value) {
            $key = self::normalize($key);
            if ('' === $key) {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
                if ('' === $value) {
                    continue;
                }
            } elseif (empty($value)) {
                continue;
            }

            if (!isset($result[$key])) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * 後台語言選單常用標籤
     *
     * @return array
     */
    public static function commonTags()
    {
        return array(
            'zh-CN', 'zh-TW', 'zh-HK', 'en', 'ja', 'ko',
            'fr', 'de', 'es', 'ru', 'pt', 'it', 'vi', 'th', 'id', 'ar'
        );
    }
}
