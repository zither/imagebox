<?php
/**
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     FFTiger <fftiger@wikisquare.com>, myst6re <myst6re@wikisquare.com>
 */

!defined('DOKU_INC') || define('DOKU_INC', realpath(dirname(__FILE__) . '/../../') . '/');
!defined('DOKU_PLUGIN') || define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

require_once(DOKU_PLUGIN . 'syntax.php');

class syntax_plugin_imagebox extends DokuWiki_Syntax_Plugin
{
    public function getInfo()
    {
        return array(
            'author' => 'FFTiger / myst6re',
            'email' => 'myst6re@wikisquare.com',
            'date' => '2010-05-30',
            'name' => 'Imagebox Plugin',
            'desc' => 'Entoure les images avec un cadre de décoration.',
            'url' => 'http://www.wikisquare.com/',
        );
    }

    public function getType()
    {
        return 'protected';
    }

    public function getAllowedTypes()
    {
        return array('substition', 'protected', 'disabled', 'formatting');
    }

    public function getSort()
    {
        return 315;
    }

    public function getPType()
    {
        return 'block';
    }

    public function connectTo($mode)
    {
        $this->Lexer->addEntryPattern(
            '\[\{\{[^\|\}]+\|*(?=[^\}]*\}\}\])',
            $mode,
            'plugin_imagebox'
        );
    }

    public function postConnect()
    {
        $this->Lexer->addExitPattern('\}\}\]', 'plugin_imagebox');
    }

    public function handle($match, $state, $pos, $handler)
    {
        switch ($state) {
            case DOKU_LEXER_ENTER:
                // 备份匹配的部分
                $originalMatch = $match;
                $match = Doku_Handler_Parse_Media(substr($match, 3));

                // 查看是否需要设置图片链接
                preg_match('/(?<=&target=)[^\|&\}]*/', $originalMatch, $result);
                if (!empty($result)) {
                    // 外部链接直接使用，此外当作 wiki id，创建内部链接
                    if (filter_var($result[0], FILTER_VALIDATE_URL)) {
                        $match['target'] = $result[0];
                    } else  {
                        $match['target'] = wl($result[0]);
                    }
                }

                $match['w'] = $match['width'];
                $dispMagnify = ($match['w'] || $match['height'])
                    && $this->getConf('display_magnify') == 'If necessary'
                    || $this->getConf('display_magnify') == 'Always';

                $exists = true;
                list($src, $hash) = explode('#', $match['src'], 2);

                if ($match['type'] == 'internalmedia') {
                    global $ID;
                    resolve_mediaid(getNS($ID), $src, $exists);

                    if ($dispMagnify) {
                        $match['detail'] = ml(
                            $src,
                            array(
                                'id' => $ID,
                                'cache' => $match['cache']
                            ),
                            ($match['linking'] == 'direct')
                        );
                        if ($hash) {
                            $match['detail'] .= '#'.$hash;
                        }
                    }
                } else {
                    if ($dispMagnify) {
                        $match['detail'] = ml(
                            $src,
                            array('cache' => 'cache'),
                            false
                        );
                        if ($hash) {
                            $match['detail'] .= '#'.$hash;
                        }
                    }
                }

                $match['exist'] = $exists;

                if (!$match['align'] || $match['align'] == 'center' && !$this->getConf('center_align')) {
                    $match['align'] = 'rien';
                }

                return array($state, $match);

            case DOKU_LEXER_UNMATCHED:
                return array($state, $match);

            case DOKU_LEXER_EXIT:
                return array($state, $match);
        }
    }

    public function render($mode, $renderer, $data)
    {
        if ($mode == 'xhtml'){
            list($state, $match) = $data;
            switch ($state) {
                case DOKU_LEXER_ENTER:
                    $renderer->doc .= '<div class="thumb2 t' . $match['align'] . '" style="width:' . (isset($match['w']) ? ($match['w'] + 10) . 'px':'auto') . '"><div class="thumbinner">';
                    // 图片链接标签头
                    if (isset($match['target'])) {
                        $renderer->doc .= sprintf(
                            '<a href="%s">',
                            $match['target']
                        );
                    }
                    if ($match['exist']) {
                        $renderer->$match['type'](
                            $match['src'],
                            $match['title'],
                            'box2',
                            $match['width'],
                            $match['height'],
                            $match['cache'],
                            $match['linking']
                        );
                    } else {
                        $renderer->doc .= 'Invalid Image';
                    }
                    // 图片链接标签尾
                    if (isset($match['target'])) {
                        $renderer->doc .= '</a>';
                    }
                    $renderer->doc .= '<div class="thumbcaption">';
                    if ($match['detail']) {
                        $renderer->doc .= '<div class="magnify">';
                        $renderer->doc .= '<a class="internal" title="' . $this->getLang('enlarge').'" href="' . $match['detail'].'">';
                        $renderer->doc .= '<img width="15" height="11" alt="" src="' . DOKU_BASE . 'lib/plugins/imagebox/magnify-clip.png"/>';
                        $renderer->doc .= '</a></div>';
                    }
                    break;

                case DOKU_LEXER_UNMATCHED:
                    $style = $this->getConf('default_caption_style');
                    if ($style == 'Italic')	{
                        $renderer->doc .= '<em>' . $renderer->_xmlEntities($match) . '</em>';
                    } elseif ($style == 'Bold') {
                        $renderer->doc .= '<strong>' . $renderer->_xmlEntities($match) . '</strong>';
                    } else {
                        $renderer->doc .= $renderer->_xmlEntities($match);
                    }
                    break;

                case DOKU_LEXER_EXIT:
                    $renderer->doc .= '</div></div></div>';
                    break;
            }
            return true;
        }
        return false;
    }
}
