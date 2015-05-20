<?php
/**
 * RSS Extend Plugin: extend default rss syntax
 *
 * @license  GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author   Mizunashi Mana <mizunashi_mana@mma.club.uec.ac.jp>
 */


if(!defined('DOKU_INC'))    define('DOKU_INC', realpath(dirname(__FILE__) . '/../../../') . '/');
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN', DOKU_INC . 'lib/plugins/');

/**
 * Class syntax_plugin_rssextend
 */
class syntax_plugin_rssextend extends DokuWiki_Syntax_Plugin {

    /**
     * Syntax Type
     *
     * Needs to return one of the mode types defined in $PARSER_MODES in parser.php
     *
     * @return string
     */
    public function getType() {
        return 'substition';
    }

    /**
     * Sort for applying this mode
     *
     * @return int
     */
    public function getSort() {
        return 309;
    }

    /**
     * @param string $mode
     */
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('\{\{rssext>[^\}]*\}\}', $mode, 'plugin_rssextend');
    }

    /**
     * param parse
     * @param   string      $params     The parameter string
     * @return  array                   Return an array with parameter flags
     */
    protected function _parse_params($params) {
        $result = array();

        // default rss had
        if(preg_match('/\b(\d+)\b/',$params,$match)){
            $result['max'] = $match[1];
        }else{
            $result['max'] = 8;
        }
        $result['reverse'] = (preg_match('/rev/',$params));
        $result['author']  = (preg_match('/\b(by|author)/',$params));
        $result['date']    = (preg_match('/\b(date)/',$params));
        $result['details'] = (preg_match('/\b(desc|detail)/',$params));
        $result['nosort']  = (preg_match('/\b(nosort)\b/',$params));
        if (preg_match('/\b(\d+)([dhm])\b/',$params,$match)) {
            $period = array('d' => 86400, 'h' => 3600, 'm' => 60);
            $result['refresh'] = max(600,$match[1]*$period[$match[2]]);  // n * period in seconds, minimum 10 minutes
        } else {
            $result['refresh'] = 14400;   // default to 4 hours
        }

        // extend syntax

        return $result;
    }

    /**
     * Handler to prepare matched data for the rendering process
     *
     * @param   string       $match   The text matched by the patterns
     * @param   int          $state   The lexer state for the match
     * @param   int          $pos     The character position of the matched text
     * @param   Doku_Handler $handler The Doku_Handler object
     * @return  array Return an array with all data you want to use in render
     */
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        global $ID;

        $link = preg_replace(array('/^\{\{rss>/', '/\}\}$/'), '', $match);
        list($link, $params) = explode(' ', $link, 2);

        return array($link, $this->_parse_params($params));
    }

    /**
     * Handles the actual output creation.
     *
     * @param   $mode     string        output format being rendered
     * @param   $renderer Doku_Renderer the current renderer object
     * @param   $data     array         data created by handler()
     * @return  boolean                 rendered correctly?
     */
    public function render($mode, Doku_Renderer $renderer, $data) {
        global $lang;
        global $conf;

        list($link, $parameter) = $data;

        require_once(realpath(dirname(__FILE__) . '/FeedParser.php'));

        $feed = new FeedParser();
        $feed->set_feed_url($url);
        //disable warning while fetching
        if(!defined('DOKU_E_LEVEL')) {
            $elvl = error_reporting(E_ERROR);
        }
        $rc = $feed->init();
        if(isset($elvl)) {
            error_reporting($elvl);
        }
        if($params['nosort']) $feed->enable_order_by_date(false);
        //decide on start and end
        if($params['reverse']) {
            $mod   = -1;
            $start = $feed->get_item_quantity() - 1;
            $end   = $start - ($params['max']);
            $end   = ($end < -1) ? -1 : $end;
        } else {
            $mod   = 1;
            $start = 0;
            $end   = $feed->get_item_quantity();
            $end   = ($end > $params['max']) ? $params['max'] : $end;
        }
        $renderer->doc .= '<ul class="rss">';
        if($rc) {
            for($x = $start; $x != $end; $x += $mod) {
                $item = $feed->get_item($x);
                $renderer->doc .= '<li><div class="li">';
                // support feeds without links
                $lnkurl = $item->get_permalink();
                if($lnkurl) {
                    // title is escaped by SimplePie, we unescape here because it
                    // is escaped again in externallink() FS#1705
                    $renderer->externallink(
                            $item->get_permalink(),
                            html_entity_decode($item->get_title(), ENT_QUOTES, 'UTF-8')
                            );
                } else {
                    $renderer->doc .= ' '.$item->get_title();
                }
                if($params['author']) {
                    $author = $item->get_author(0);
                    if($author) {
                        $name = $author->get_name();
                        if(!$name) $name = $author->get_email();
                        if($name) $renderer->doc .= ' '.$lang['by'].' '.$name;
                    }
                }
                if($params['date']) {
                    $renderer->doc .= ' ('.$item->get_local_date($conf['dformat']).')';
                }
                if($params['details']) {
                    $renderer->doc .= '<div class="detail">';
                    if($conf['htmlok']) {
                        $renderer->doc .= $item->get_description();
                    } else {
                        $renderer->doc .= strip_tags($item->get_description());
                    }
                    $renderer->doc .= '</div>';
                }
                $renderer->doc .= '</div></li>';
            }
        } else {
            $renderer->doc .= '<li><div class="li">';
            $renderer->doc .= '<em>'.$lang['rssfailed'].'</em>';
            $renderer->externallink($url);
            if($conf['allowdebug'] || true) {
                $renderer->doc .= '<!--'.hsc($feed->error).'-->';
            }
            $renderer->doc .= '</div></li>';
        }
        $renderer->doc .= '</ul>';

        return true;
    }
}
