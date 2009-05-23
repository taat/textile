<?php

/**
* Example: get XHTML from a given Textile-markup string ($string)
*
*		  $textile = new Textile;
*		  echo $textile->TextileThis($string);
*
*/

/*
_____________
T E X T I L E

A Humane Web Text Generator

Version 3.0

Copyright (c) 2003-2004, Dean Allen <dean@textism.com>
All rights reserved.

Thanks to Carlo Zottmann <carlo@g-blog.net> for refactoring
Textile's procedural code into a class framework

Additions and fixes Copyright (c) 2006 Alex Shiels http://thresholdstate.com/

_____________
L I C E N S E

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice,
this list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

* Neither the name Textile nor the names of its contributors may be used to
endorse or promote products derived from this software without specific
prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

___________
R E A D M E
More informations in the README file

*/

// define these before including this file to override the standard glyphs

// TODO: Move this to the class, Replace constants with getters and setters
defined('TXT_QUOTE_SINGLE_OPEN')  || define('TXT_QUOTE_SINGLE_OPEN',  '&#8216;');
defined('TXT_QUOTE_SINGLE_CLOSE') || define('TXT_QUOTE_SINGLE_CLOSE', '&#8217;');
defined('TXT_QUOTE_DOUBLE_OPEN')  || define('TXT_QUOTE_DOUBLE_OPEN',  '&#8220;');
defined('TXT_QUOTE_DOUBLE_CLOSE') || define('TXT_QUOTE_DOUBLE_CLOSE', '&#8221;');
defined('TXT_APOSTROPHE')		  || define('TXT_APOSTROPHE',		  '&#8217;');
defined('TXT_PRIME')			  || define('TXT_PRIME',			  '&#8242;');
defined('TXT_PRIME_double') 	  || define('TXT_PRIME_double', 	  '&#8243;');
defined('TXT_ELLIPSIS') 		  || define('TXT_ELLIPSIS', 		  '&#8230;');
defined('TXT_EMDASH')			  || define('TXT_EMDASH',			  '&#8212;');
defined('TXT_ENDASH')			  || define('TXT_ENDASH',			  '&#8211;');
defined('TXT_DIMENSION')		  || define('TXT_DIMENSION',		  '&#215;');
defined('TXT_TRADEMARK')		  || define('TXT_TRADEMARK',		  '&#8482;');
defined('TXT_REGISTERED')		  || define('TXT_REGISTERED',		  '&#174;');
defined('TXT_COPYRIGHT')		  || define('TXT_COPYRIGHT',		  '&#169;');

/**
 * Convert Textile markup to HTML
 *
 * @todo Doc comments
 * @todo Class member descriptions
 * @todo Scope keywords
 * @todo Replace constants with getters and setters
 * @todo Support for chaining methods
 * @todo Replace member variables with const where
 * @todo Improve function getPlain
 * @todo Allow to decide wheater to use HTML entities or UTF-8 characters
 *
 * @copyright 2003-2004, Dean Allen dean at textism.com
 * @author Dean Allen dean at textism.com
 * @author Carlo Zottmann carlo at g-blog.net (refactoring into a PHP4 class framework)
 * @author Alex Shiels http://thresholdstate.com/ (Additions and fixes)
 * @author Tomek Peszor http://taat.pl/ tomek @ taat dot pl
 */
class Textile
{
    /**
     * Align horizontal regexp
     * @var string
     */
    private $_alignHorizontal = "(?:\<(?!>)|(?<!<)\>|\<\>|\=|[()]+(?! ))";

    /**
     * Align vertical regexp
     * @var string
     */
    private $_alignVertical = "[\-^~]";

    /**
     * Class regexp
     * @var string
     */
    private $_class = "(?:\([^)]+\))";

    /**
     * Directory separator in URLs
     * @var string
     */
    private $_ds = '/';
    private $_language  = "(?:\[[^]]+\])";
    private $_style  = "(?:\{[^}]+\})";
    private $_cspn  = "(?:\\\\\d+)";
    private $_rspn  = "(?:\/\d+)";
    private $_a;
    private $_s;
    private $_c;
    private $_punctuation = '[\!"#\$%&\'()\*\+,\-\./:;<=>\?@\[\\\]\^_`{\|}\~]';
    private $_urlch = '[\w"$\-_.+!*\'(),";\/?:@=&%#{}|\\^~\[\]`]';
    private $_btag = array('bq', 'bc', 'notextile', 'pre', 'h[1-6]', 'fn\d+', 'p');
    private $_rel;
    private $_fn;
    private $_shelf = array();
    private $_restricted = false;
    private $_noimage = false;
    private $_lite = false;
    private $_urlSchemes = array('http','https','ftp','mailto');
    private $_glyph = array(
                             'quote_single_open'	=> TXT_QUOTE_SINGLE_OPEN,
                             'quote_single_close' => TXT_QUOTE_SINGLE_CLOSE,
                             'quote_double_open'	=> TXT_QUOTE_DOUBLE_OPEN,
                             'quote_double_close' => TXT_QUOTE_DOUBLE_CLOSE,
                             'apostrophe' 		=> TXT_APOSTROPHE,
                             'prime'				=> TXT_PRIME,
                             'prime_double'		=> TXT_PRIME_double,
                             'ellipsis'			=> TXT_ELLIPSIS,
                             'emdash' 			=> TXT_EMDASH,
                             'endash' 			=> TXT_ENDASH,
                             'dimension'			=> TXT_DIMENSION,
                             'trademark'			=> TXT_TRADEMARK,
                             'registered' 		=> TXT_REGISTERED,
                             'copyright'			=> TXT_COPYRIGHT,
    );
    private $_hu = '';
    private $_docRoot;

   /**
    * Constructor
    *
    * Sets defaults
    */
    public function __construct()
    {
        $this->_a = "(?:{$this->_alignHorizontal}|{$this->_alignVertical})*";
        $this->_s = "(?:{$this->_cspn}|{$this->_rspn})*";
        $this->_c = "(?:{$this->_class}|{$this->_style}|{$this->_language}|{$this->_alignHorizontal})*";

        if (defined('hu')) {
            $this->_hu = hu;
        }

        if (defined('DIRECTORY_SEPARATOR')) {
            $this->_ds = constant('DIRECTORY_SEPARATOR');
        }

        $this->_docRoot = @$_SERVER['DOCUMENT_ROOT'];
        if (!$this->_docRoot) {
            $this->_docRoot = @$_SERVER['PATH_TRANSLATED']; // IIS
        }

        $this->_docRoot = rtrim($this->_docRoot, $this->_ds).$this->_ds;
    }

   /**
    * Convert Textile markup to HTML
    *
    * @param string $text String with Textile markup.
    * @param bool $lite
    * @param bool $encode
    * @param bool $noimage
    * @param bool $strict
    * @param bool $rel
    * @access public
    * @return string HTML code.
    */
    public function TextileThis($text, $lite = '', $encode = '', $noimage = '', $strict = '', $rel = '')
    {
        $this->_rel = ($rel) ? ' rel="'.$rel.'"' : '';

        $this->_lite = $lite;
        $this->_noimage = $noimage;

        if ($encode) {
            $text = $this->incomingEntities($text);
            $text = str_replace("x%x%", "&amp;", $text);
            return $text;
        } else {

            if(!$strict) {
                $text = $this->cleanWhiteSpace($text);
            }

            if (!$lite) {
                $text = $this->block($text);
            }

            $text = $this->retrieve($text);
            $text = $this->retrieveURLs($text);

            // just to be tidy
            $text = str_replace("<br />", "<br />\n", $text);

            return $text;
        }
    }

    /**
     * Converts light Textile markup to HTML
     * For use with untrusted data.
     *
     * @param <type> $text
     * @param <type> $lite
     * @param <type> $noimage
     * @param <type> $rel
     * @return <type>
     */
    public function TextileRestricted($text, $lite = 1, $noimage = 1, $rel = 'nofollow')
    {
        $this->_restricted = true;
        $this->_lite = $lite;
        $this->_noimage = $noimage;

        $this->_rel = ($rel) ? ' rel="'.$rel.'"' : '';

        // escape any raw html
        $text = $this->encode_html($text, 0);

        $text = $this->cleanWhiteSpace($text);

        if ($lite) {
            $text = $this->blockLite($text);
        }
        else {
            $text = $this->block($text);
        }

        $text = $this->retrieve($text);
        $text = $this->retrieveURLs($text);

        // just to be tidy
        $text = str_replace("<br />", "<br />\n", $text);

        return $text;
    }

    /**
     * Removes Textile markup from the string.
     *
     * @todo Improve getPlain function to preserve whitespace
     * @param string $text String with Textile markup.
     * @return string Plain text without Textile markup.
     */
    public function getPlain($text, $lite = '', $encode = '', $noimage = '', $strict = '', $rel = '')
    {
        return strip_tags($this->TextileThis($text, $lite, $encode, $noimage, $strict, $rel));
    }

    /**
     * Alias for TextileThis
     * @see TextileThis
     */
    public function getHtml($text, $lite = '', $encode = '', $noimage = '', $strict = '', $rel = '')
    {
        return $this->TextileThis($text, $lite, $encode, $noimage, $strict, $rel);
    }

    /**
     * Get both HTML and plain texts
     * Better performance than separate calls
     *
     * @access private
     * @param  $string
     * @return array html, plain
     * @see getHTML
     * @see getPlain
     */
    private function getBoth($text, $lite = '', $encode = '', $noimage = '', $strict = '', $rel = '') {
        $output =  $this->TextileThis($text, $lite, $encode, $noimage, $strict, $rel);
        return array('html' => $output, 'plain' => strip_tags($output));
    }

    /**
     * Alias for TextileRestricted
     */
    public function getHtmlRestricted($text, $lite = 1, $noimage = 1, $rel = 'nofollow')
    {
        return $this->TextileRestricted($text, $lite = 1, $noimage = 1, $rel = 'nofollow');
    }

    /**
     * Parse block attributes
     *
     * @param string $in
     * @param string $element
     * @param int $include_id
     */
    private function pba($in, $element = "", $include_id = 1) // "parse block attributes"
    {
        $style = '';
        $class = '';
        $lang = '';
        $colspan = '';
        $rowspan = '';
        $id = '';
        $atts = '';

        if (!empty($in)) {
            $matched = $in;
            if ($element == 'td') {
                if (preg_match("/\\\\(\d+)/", $matched, $csp)) $colspan = $csp[1];
                if (preg_match("/\/(\d+)/", $matched, $rsp)) $rowspan = $rsp[1];
            }

            if ($element == 'td' or $element == 'tr') {
                if (preg_match("/($this->_alignVertical)/", $matched, $vert))
                $style[] = "vertical-align:" . $this->vAlign($vert[1]) . ";";
            }

            if (preg_match("/\{([^}]*)\}/", $matched, $sty)) {
                $style[] = rtrim($sty[1], ';') . ';';
                $matched = str_replace($sty[0], '', $matched);
            }

            if (preg_match("/\[([^]]+)\]/U", $matched, $lng)) {
                $lang = $lng[1];
                $matched = str_replace($lng[0], '', $matched);
            }

            if (preg_match("/\(([^()]+)\)/U", $matched, $cls)) {
                $class = $cls[1];
                $matched = str_replace($cls[0], '', $matched);
            }

            if (preg_match("/([(]+)/", $matched, $pl)) {
                $style[] = "padding-left:" . strlen($pl[1]) . "em;";
                $matched = str_replace($pl[0], '', $matched);
            }

            if (preg_match("/([)]+)/", $matched, $pr)) {
                // $this->dump($pr);
                $style[] = "padding-right:" . strlen($pr[1]) . "em;";
                $matched = str_replace($pr[0], '', $matched);
            }

            if (preg_match("/($this->_alignHorizontal)/", $matched, $horiz))
            $style[] = "text-align:" . $this->hAlign($horiz[1]) . ";";

            if (preg_match("/^(.*)#(.*)$/", $class, $ids)) {
                $id = $ids[2];
                $class = $ids[1];
            }

            if ($this->_restricted)
            return ($lang)	  ? ' lang="'	 . $lang			.'"':'';

            return join('',array(
                    ($style)   ? ' style="'   . join("", $style) .'"':'',
                    ($class)   ? ' class="'   . $class			 .'"':'',
                    ($lang)    ? ' lang="'	  . $lang			 .'"':'',
                    ($id and $include_id) ? ' id="' 	 . $id				.'"':'',
                    ($colspan) ? ' colspan="' . $colspan		 .'"':'',
                    ($rowspan) ? ' rowspan="' . $rowspan		 .'"':''
                ));
        }
        return '';
    }

    /**
     * Checks whether the text has text not already enclosed by a block tag
     *
     * @param string $text Input string.
     * @return bool
     */
    public function hasRawText($text)
    {
        $r = trim(preg_replace('@<(p|blockquote|div|form|table|ul|ol|pre|h\d)[^>]*?>.*</\1>@s', '', trim($text)));
        $r = trim(preg_replace('@<(hr|br)[^>]*?/>@', '', $r));
        return '' != $r;
    }

    /**
     * Find all occurences of tables
     *
     * @param string $text Input string.
     * @return array
     */
    private function table($text)
    {
        $text = $text . "\n\n";
        return preg_replace_callback("/^(?:table(_?{$this->_s}{$this->_a}{$this->_c})\. ?\n)?^({$this->_a}{$this->_c}\.? ?\|.*\|)\n\n/smU",
            array(&$this, "fTable"), $text);
    }

    /**
     * Get HTML code for table
     *
     * @param array $matches
     * @return string HTML code for table.
     */
    private function fTable($matches)
    {
        $tatts = $this->pba($matches[1], 'table');

        foreach(preg_split("/\|$/m", $matches[2], -1, PREG_SPLIT_NO_EMPTY) as $row) {
            if (preg_match("/^($this->_a$this->_c\. )(.*)/m", ltrim($row), $rmtch)) {
                $ratts = $this->pba($rmtch[1], 'tr');
                $row = $rmtch[2];
            } else $ratts = '';

            $cells = array();
            foreach(explode("|", $row) as $cell) {
                $ctyp = "d";
                if (preg_match("/^_/", $cell)) $ctyp = "h";
                if (preg_match("/^(_?$this->_s$this->_a$this->_c\. )(.*)/", $cell, $cmtch)) {
                    $catts = $this->pba($cmtch[1], 'td');
                    $cell = $cmtch[2];
                } else $catts = '';

                $cell = $this->paragraph($cell);

                if (trim($cell) != '')
                $cells[] = $this->doTagBr("t$ctyp", "\t\t\t<t$ctyp$catts>$cell</t$ctyp>");
            }
            $rows[] = "\t\t<tr$ratts>\n" . join("\n", $cells) . ($cells ? "\n" : "") . "\t\t</tr>";
            unset($cells, $catts);
        }
        return "\t<table$tatts>\n" . join("\n", $rows) . "\n\t</table>\n\n";
    }

    /**
     * Finds lists in input text
     *
     * @param string $text Input string.
     * @return array of strings
     */
    private function lists($text)
    {
        return preg_replace_callback("/^([#*]+$this->_c .*)$(?![^#*])/smU", array(&$this, "fList"), $text);
    }


    /**
     * Lists callback function
     *
     * @see lists
     * @param array $matches
     * @return string HTML code.
     */
    private function fList($matches)
    {
        $text = preg_split('/\n(?=[*#])/m', $matches[0]);
        foreach($text as $nr => $line) {
            $nextline = isset($text[$nr+1]) ? $text[$nr+1] : false;
            if (preg_match("/^([#*]+)($this->_a$this->_c) (.*)$/s", $line, $matches)) {
                list(, $tl, $atts, $content) = $matches;
                $nl = '';
                if (preg_match("/^([#*]+)\s.*/", $nextline, $nm))
                $nl = $nm[1];
                if (!isset($lists[$tl])) {
                    $lists[$tl] = true;
                    $atts = $this->pba($atts);
                    $line = "\t<" . $this->lT($tl) . "l$atts>\n\t\t<li>" . rtrim($content);
                } else {
                    $line = "\t\t<li>" . rtrim($content);
                }

                if(strlen($nl) <= strlen($tl)) $line .= "</li>";
                foreach(array_reverse($lists) as $k => $v) {
                    if(strlen($k) > strlen($nl)) {
                        $line .= "\n\t</" . $this->lT($k) . "l>";
                        if(strlen($k) > 1)
                        $line .= "</li>";
                        unset($lists[$k]);
                    }
                }
            }
            else {
                $line .= "\n";
            }
            $out[] = $line;
        }
        return $this->doTagBr('li', join("\n", $out));
    }

    /**
     * Determine ol or ul tag
     *
     * @param string $in
     * @return string
     */
    private function lT($in)
    {
        return preg_match("/^#+/", $in) ? 'o' : 'u';
    }

    private function doTagBr($tag, $in)
    {
        return preg_replace_callback('@<('.preg_quote($tag).')([^>]*?)>(.*)(</\1>)@s', array(&$this, 'doBr'), $in);
    }

    private function doPBr($in)
    {
        return $this->doTagBr('p', $in);
    }

    private function doBr($matches)
    {
        $content = preg_replace("@(.+)(?<!<br>|<br />)\n(?![#*\s|])@", '$1<br />', $matches[3]);
        return '<'.$matches[1].$matches[2].'>'.$content.$matches[4];
    }

    /**
     * Handle blocks
     *
     * @param string $text
     * @return string
     */
    private function block($text)
    {
        $find = $this->_btag;
        $tre = join('|', $find);

        $text = explode("\n\n", $text);

        $tag = 'p';
        $atts = $cite = $graf = $ext  = '';

        foreach($text as $line) {
            $anon = 0;
            if (preg_match("/^($tre)($this->_a$this->_c)\.(\.?)(?::(\S+))? (.*)$/s", $line, $m)) {
                // last block was extended, so close it
                if ($ext)
                $out[count($out)-1] .= $c1;
                // new block
                list(,$tag,$atts,$ext,$cite,$graf) = $m;
                list($o1, $o2, $content, $c2, $c1) = $this->fBlock(array(0,$tag,$atts,$ext,$cite,$graf));

                // leave off c1 if this block is extended, we'll close it at the start of the next block
                if ($ext)
                $line = $o1.$o2.$content.$c2;
                else
                $line = $o1.$o2.$content.$c2.$c1;
            }
            else {
                // anonymous block
                $anon = 1;
                if ($ext or !preg_match('/^ /', $line)) {
                    list($o1, $o2, $content, $c2, $c1) = $this->fBlock(array(0,$tag,$atts,$ext,$cite,$line));
                    // skip $o1/$c1 because this is part of a continuing extended block
                    if ($tag == 'p' and !$this->hasRawText($content)) {
                        $line = $content;
                    }
                    else {
                        $line = $o2.$content.$c2;
                    }
                }
                else {
                    $line = $this->paragraph($line);
                }
            }

            $line = $this->doPBr($line);
            $line = preg_replace('/<br>/', '<br />', $line);

            if ($ext and $anon)
            $out[count($out)-1] .= "\n".$line;
            else
            $out[] = $line;

            if (!$ext) {
                $tag = 'p';
                $atts = '';
                $cite = '';
                $graf = '';
            }
        }
        if ($ext) $out[count($out)-1] .= $c1;
        return join("\n\n", $out);
    }

    /**
     * Blocks callback function
     *
     * @see blocks
     * @param array $matches
     * @return string HTML code.
     */
    private function fBlock($matches)
    {
        list(, $tag, $att, $ext, $cite, $content) = $matches;
        $atts = $this->pba($att);

        $o1 = $o2 = $c2 = $c1 = '';

        if (preg_match("/fn(\d+)/", $tag, $fns)) {
            $tag = 'p';
            $fnid = empty($this->_fn[$fns[1]]) ? $fns[1] : $this->_fn[$fns[1]];
            $atts .= ' id="fn' . $fnid . '"';
            if (strpos($atts, 'class=') === false)
            $atts .= ' class="footnote"';
            $content = '<sup>' . $fns[1] . '</sup> ' . $content;
        }

        if ($tag == "bq") {
            $cite = $this->shelveURL($cite);
            $cite = ($cite != '') ? ' cite="' . $cite . '"' : '';
            $o1 = "\t<blockquote$cite$atts>\n";
            $o2 = "\t\t<p".$this->pba($att, '', 0).">";
            $c2 = "</p>";
            $c1 = "\n\t</blockquote>";
        }
        elseif ($tag == 'bc') {
            $o1 = "<pre$atts>";
            $o2 = "<code".$this->pba($att, '', 0).">";
            $c2 = "</code>";
            $c1 = "</pre>";
            $content = $this->shelve($this->r_encode_html(rtrim($content, "\n")."\n"));
        }
        elseif ($tag == 'notextile') {
            $content = $this->shelve($content);
            $o1 = $o2 = '';
            $c1 = $c2 = '';
        }
        elseif ($tag == 'pre') {
            $content = $this->shelve($this->r_encode_html(rtrim($content, "\n")."\n"));
            $o1 = "<pre$atts>";
            $o2 = $c2 = '';
            $c1 = "</pre>";
        }
        else {
            $o2 = "\t<$tag$atts>";
            $c2 = "</$tag>";
        }

        $content = $this->paragraph($content);

        return array($o1, $o2, $content, $c2, $c1);
    }

    /**
     * Handle normal paragraph text
     *
     * @param string $text
     * @return string HTML code.
     */
    private function paragraph($text)
    {
        if (!$this->_lite) {
            $text = $this->noTextile($text);
            $text = $this->code($text);
        }

        $text = $this->getRefs($text);
        $text = $this->links($text);
        if (!$this->_noimage)
        $text = $this->image($text);

        if (!$this->_lite) {
            $text = $this->table($text);
            $text = $this->lists($text);
        }

        $text = $this->span($text);
        $text = $this->footnoteRef($text);
        $text = $this->glyphs($text);
        return rtrim($text, "\n");
    }

    /**
     * Handle span tags
     *
     * @param string $text
     * @return string HTML code.
     */
    private function span($text)
    {
        $qtags = array('\*\*','\*','\?\?','-','__','_','%','\+','~','\^');
        $pnct = ".,\"'?!;:";

        foreach($qtags as $f) {
            $text = preg_replace_callback("/
                                          (^|(?<=[\s>$pnct\(])|[{[])
                                          ($f)(?!$f)
                                          ({$this->_c})
                                          (?::(\S+))?
                                          ([^\s$f]+|\S.*?[^\s$f\n])
                                          ([$pnct]*)
                $f
                                          ($|[\]}]|(?=[[:punct:]]{1,2}|\s|\)))
                                          /x", array(&$this, "fSpan"), $text);
        }
        return $text;
    }

    /**
     * Span callback funcion
     *
     * @see span
     * @param array $matches
     * @return string HTML code.
     */
    private function fSpan($matches)
    {
        $qtags = array(
                       '*'  => 'strong',
                       '**' => 'b',
                       '??' => 'cite',
                       '_'  => 'em',
                       '__' => 'i',
                       '-'  => 'del',
                       '%'  => 'span',
                       '+'  => 'ins',
                       '~'  => 'sub',
                       '^'  => 'sup',
        );

        list(, $pre, $tag, $atts, $cite, $content, $end, $tail) = $matches;
        $tag = $qtags[$tag];
        $atts = $this->pba($atts);
        $atts .= ($cite != '') ? 'cite="' . $cite . '"' : '';

        $out = "<$tag$atts>$content$end</$tag>";

        if (($pre and !$tail) or ($tail and !$pre))
        $out = $pre.$out.$tail;

        //		$this->dump($out);

        return $out;

    }

    /**
     * Handle links
     *
     * @param string $text Input text.
     * @return string HTML code.
     */
    private function links($text)
    {
        return preg_replace_callback('/
                                     (^|(?<=[\s>.$pnct\(])|[{[]) # $pre
                                     "							 # start
                                     (' . $this->_c . ')			 # $atts
                                     ([^"]+?)					 # $text
                                     (?:\(([^)]+?)\)(?="))?		 # $title
                                     ":
                                     ('.$this->_urlch.'+?)		 # $url
                                     (\/)?						 # $slash
                                     ([^\w\/;]*?)				 # $post
                                     ([\]}]|(?=\s|$|\)))
                                     /x', array(&$this, "fLink"), $text);
    }

    /**
     * Links callback function
     *
     * @see links
     * @param array $matches
     * @return string
     */
    private function fLink($matches)
    {
        list(, $pre, $atts, $text, $title, $url, $slash, $post, $tail) = $matches;

        $atts = $this->pba($atts);
        $atts .= ($title != '') ? ' title="' . $this->encode_html($title) . '"' : '';

        if (!$this->_noimage)
        $text = $this->image($text);

        $text = $this->span($text);
        $text = $this->glyphs($text);

        $url = $this->shelveURL($url.$slash);

        $out = '<a href="' . $url . '"' . $atts . $this->_rel . '>' . trim($text) . '</a>' . $post;

        if (($pre and !$tail) or ($tail and !$pre))
        $out = $pre.$out.$tail;

        // $this->dump($out);
        return $this->shelve($out);

    }

    /**
     * Handle refs
     *
     * @param string $text
     * @return string
     */
    private function getRefs($text)
    {
        return preg_replace_callback("/^\[(.+)\]((?:http:\/\/|\/)\S+)(?=\s|$)/Um",
            array(&$this, "refs"), $text);
    }


    /**
     * Refs callback function
     *
     * @param array $matches
     * @return string
     */
    private function refs($matches)
    {
        list(, $flag, $url) = $matches;
        $this->urlrefs[$flag] = $url;
        return '';
    }

    private function shelveURL($text)
    {
        if (!$text) return '';
        $ref = md5($text);
        $this->urlshelf[$ref] = $text;
        return 'urlref:'.$ref;
    }

    private function retrieveURLs($text)
    {
        return preg_replace_callback('/urlref:(\w{32})/',
            array(&$this, "retrieveURL"), $text);
    }

    private function retrieveURL($matches)
    {
        $ref = $matches[1];
        if (!isset($this->urlshelf[$ref]))
        return $ref;
        $url = $this->urlshelf[$ref];
        if (isset($this->urlrefs[$url]))
        $url = $this->urlrefs[$url];
        return $this->r_encode_html($this->relURL($url));
    }

    private function relURL($url)
    {
        $parts = @parse_url(urldecode($url));
        if ((empty($parts['scheme']) or @$parts['scheme'] == 'http') and
            empty($parts['host']) and
            preg_match('/^\w/', @$parts['path']))
        $url = $this->_hu.$url;
        if ($this->_restricted and !empty($parts['scheme']) and
            !in_array($parts['scheme'], $this->_urlSchemes))
        return '#';
        return $url;
    }

    private function isRelURL($url)
    {
        $parts = @parse_url($url);
        return (empty($parts['scheme']) and empty($parts['host']));
    }

    /**
     * Handle images
     *
     * @param string $text
     * @return string
     */
    private function image($text)
    {
        return preg_replace_callback("/
                                     (?:[[{])?		   # pre
                                     \!				   # opening !
                                     (\<|\=|\>)? 	   # optional alignment atts
                                     ($this->_c)		   # optional style,class atts
                                     (?:\. )?		   # optional dot-space
                                     ([^\s(!]+)		   # presume this is the src
                                     \s? 			   # optional space
                                     (?:\(([^\)]+)\))?  # optional title
                                     \!				   # closing
                                     (?::(\S+))? 	   # optional href
                                     (?:[\]}]|(?=\s|$|\))) # lookahead: space or end of string
                                     /x", array(&$this, "fImage"), $text);
    }

    /**
     * Image callback function
     *
     * @see image
     * @param array $matches
     * @return <string
     */
    private function fImage($matches)
    {
        list(, $algn, $atts, $url) = $matches;
        $atts  = $this->pba($atts);
        $atts .= ($algn != '')	? ' align="' . $this->iAlign($algn) . '"' : '';
        $atts .= (isset($matches[4])) ? ' title="' . $matches[4] . '"' : '';
        $atts .= (isset($matches[4])) ? ' alt="'	 . $matches[4] . '"' : ' alt=""';
        $size = false;
        if ($this->isRelUrl($url))
        $size = @getimagesize(realpath($this->_docRoot.ltrim($url, $this->_ds)));
        if ($size) $atts .= " $size[3]";

        $href = (isset($matches[5])) ? $this->shelveURL($matches[5]) : '';
        $url = $this->shelveURL($url);

        $out = array(
            ($href) ? '<a href="' . $href . '">' : '',
                     '<img src="' . $url . '"' . $atts . ' />',
            ($href) ? '</a>' : ''
        );

        return $this->shelve(join('',$out));
    }

    /**
     * Handle code tags
     *
     * @param string $text
     * @return string
     */
    private function code($text)
    {
        $text = $this->doSpecial($text, '<code>', '</code>', 'fCode');
        $text = $this->doSpecial($text, '@', '@', 'fCode');
        $text = $this->doSpecial($text, '<pre>', '</pre>', 'fPre');
        return $text;
    }

    /**
     * Code callback function
     *
     * @see code
     * @param array $matches
     * @return string
     */
    private function fCode($matches)
    {
        @list(, $before, $text, $after) = $matches;
        return $before.$this->shelve('<code>'.$this->r_encode_html($text).'</code>').$after;
    }

    private function fPre($matches)
    {
        @list(, $before, $text, $after) = $matches;
        return $before.'<pre>'.$this->shelve($this->r_encode_html($text)).'</pre>'.$after;
    }

    private function shelve($val)
    {
        $i = uniqid(rand());
        $this->_shelf[$i] = $val;
        return $i;
    }

    private function retrieve($text)
    {
        if (is_array($this->_shelf))
        do {
            $old = $text;
            $text = strtr($text, $this->_shelf);
        } while ($text != $old);

        return $text;
    }

    private function cleanWhiteSpace($text)
    {
        $out = str_replace("\r\n", "\n", $text);		# DOS line endings
        $out = preg_replace("/^[ \t]*\n/m", "\n", $out);	# lines containing only whitespace
        $out = preg_replace("/\n{3,}/", "\n\n", $out);	# 3 or more line ends
        $out = preg_replace("/^\n*/", "", $out);		# leading blank lines
        return $out;
    }

    /**
     * Handle special characters
     *
     * @param <type> $text
     * @param <type> $start
     * @param <type> $end
     * @param <type> $method
     * @return <type>
     */
    private function doSpecial($text, $start, $end, $method='fSpecial')
    {
        return preg_replace_callback('/(^|\s|[[({>])'.preg_quote($start, '/').'(.*?)'.preg_quote($end, '/').'(\s|$|[\])}])?/ms',
            array(&$this, $method), $text);
    }

    /**
     * Special callback funcion
     *
     * @see doSpecial
     * @param <type> $matches
     * @return <type>
     */
    private function fSpecial($matches)
    {
        // A special block like notextile or code
        @list(, $before, $text, $after) = $matches;
        return $before.$this->shelve($this->encode_html($text)).$after;
    }

    /**
     * Handle notextile tags
     *
     * @param string $text
     * @return string
     */
    private function noTextile($text)
    {
        $text = $this->doSpecial($text, '<notextile>', '</notextile>', 'fTextile');
        return $this->doSpecial($text, '==', '==', 'fTextile');

    }

    /**
     * Notextile callback function
     *
     * @see noTextile
     * @param array $matches
     * @return string
     */
    private function fTextile($matches)
    {
        @list(, $before, $notextile, $after) = $matches;
        #$notextile = str_replace(array_keys($modifiers), array_values($modifiers), $notextile);
        return $before.$this->shelve($notextile).$after;
    }

    /**
     * Handle footnote references
     *
     * @param string $text
     * @return string
     */
    private function footnoteRef($text)
    {
        return preg_replace('/(?<=\S)\[([0-9]+)\](\s)?/Ue',
                            '$this->footnoteID(\'\1\',\'\2\')', $text);
    }

    /**
     * Handle footnote ids
     *
     * @param <type> $id
     * @param <type> $t
     * @return string
     */
    private function footnoteID($id, $t)
    {
        if (empty($this->_fn[$id]))
        $this->_fn[$id] = uniqid(rand());
        $fnid = $this->_fn[$id];
        return '<sup class="footnote"><a href="#fn'.$fnid.'">'.$id.'</a></sup>'.$t;
    }

    /**
     * Replace glyphs
     *
     * @param string $text
     * @return string
     */
    private function glyphs($text)
    {

        // fix: hackish
        $text = preg_replace('/"\z/', "\" ", $text);
        $pnc = '[[:punct:]]';

        $glyph_search = array(
                              '/(\w)\'(\w)/', 									 // apostrophe's
                              '/(\s)\'(\d+\w?)\b(?!\')/', 						 // back in '88
                              '/(\S)\'(?=\s|'.$pnc.'|<|$)/',						 //  single closing
                              '/\'/', 											 //  single opening
                              '/(\S)\"(?=\s|'.$pnc.'|<|$)/',						 //  double closing
                              '/"/',												 //  double opening
                              '/\b([A-Z][A-Z0-9]{2,})\b(?:[(]([^)]*)[)])/',		 //  3+ uppercase acronym
                              '/(?<=\s|^|[>(;-])([A-Z]{3,})([a-z]*)(?=\s|'.$pnc.'|<|$)/',  //  3+ uppercase
                              '/([^.]?)\.{3}/',									 //  ellipsis
                              '/(\s?)--(\s?)/',									 //  em dash
                              '/\s-(?:\s|$)/',									 //  en dash
                              '/(\d+)( ?)x( ?)(?=\d+)/',							 //  dimension sign
                              '/(\b ?|\s|^)[([]TM[])]/i', 						 //  trademark
                              '/(\b ?|\s|^)[([]R[])]/i',							 //  registered
                              '/(\b ?|\s|^)[([]C[])]/i',							 //  copyright
        );

        extract($this->_glyph, EXTR_PREFIX_ALL, 'txt');

        $glyph_replace = array(
                               '$1'.TXT_APOSTROPHE.'$2',			 // apostrophe's
                               '$1'.TXT_APOSTROPHE.'$2',			 // back in '88
                               '$1'.TXT_QUOTE_SINGLE_CLOSE,		 //  single closing
            TXT_QUOTE_SINGLE_OPEN, 			 //  single opening
                               '$1'.TXT_QUOTE_DOUBLE_CLOSE,		 //  double closing
            TXT_QUOTE_DOUBLE_OPEN, 			 //  double opening
                               '<acronym title="$2">$1</acronym>',  //  3+ uppercase acronym
                               '<span class="caps">$1</span>$2',	 //  3+ uppercase
                               '$1'.TXT_ELLIPSIS, 				 //  ellipsis
                               '$1'.TXT_EMDASH.'$2',				 //  em dash
                               ' '.TXT_ENDASH.' ',				 //  en dash
                               '$1$2'.TXT_DIMENSION.'$3', 		 //  dimension sign
                               '$1'.TXT_TRADEMARK,				 //  trademark
                               '$1'.TXT_REGISTERED,				 //  registered
                               '$1'.TXT_COPYRIGHT,				 //  copyright
        );

        $text = preg_split("@(<[\w/!?].*>)@Us", $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $i = 0;
        foreach($text as $line) {
            // text tag text tag text ...
            if (++$i % 2) {
                // raw < > & chars are already entity encoded in restricted mode
                if (!$this->_restricted) {
                    $line = $this->encode_raw_amp($line);
                    $line = $this->encode_lt_gt($line);
                }
                $line = preg_replace($glyph_search, $glyph_replace, $line);
            }
            $glyph_out[] = $line;
        }
        return join('', $glyph_out);
    }

    private function iAlign($in)
    {
        $vals = array(
                      '<' => 'left',
                      '=' => 'center',
                      '>' => 'right');
        return (isset($vals[$in])) ? $vals[$in] : '';
    }

    private function hAlign($in)
    {
        $vals = array(
                      '<'  => 'left',
                      '='  => 'center',
                      '>'  => 'right',
                      '<>' => 'justify');
        return (isset($vals[$in])) ? $vals[$in] : '';
    }

    private function vAlign($in)
    {
        $vals = array(
                      '^' => 'top',
                      '-' => 'middle',
                      '~' => 'bottom');
        return (isset($vals[$in])) ? $vals[$in] : '';
    }

    private function encode_raw_amp($text)
    {
        return preg_replace('/&(?!#?[a-z0-9]+;)/i', '&amp;', $text);
    }

    private function encode_lt_gt($text)
    {
        return strtr($text, array('<' => '&lt;', '>' => '&gt;'));
    }

    private function encode_html($str, $quotes=1)
    {
        $a = array(
                   '&' => '&amp;',
                   '<' => '&lt;',
                   '>' => '&gt;',
        );
        if ($quotes) $a = $a + array(
                                     "'" => '&#39;', // numeric, as in htmlspecialchars
                                     '"' => '&quot;',
        );

        return strtr($str, $a);
    }

    private function r_encode_html($str, $quotes=1)
    {
        // in restricted mode, input has already been escaped
        if ($this->_restricted)
        return $str;
        return $this->encode_html($str, $quotes);
    }

    private function textile_popup_help($name, $helpvar, $windowW, $windowH)
    {
        return ' <a target="_blank" href="http://www.textpattern.com/help/?item=' . $helpvar . '" onclick="window.open(this.href, \'popupwindow\', \'width=' . $windowW . ',height=' . $windowH . ',scrollbars,resizable\'); return false;">' . $name . '</a><br />';
    }

    private function blockLite($text)
    {
        $this->_btag = array('bq', 'p');
        return $this->block($text."\n\n");
    }



    // DEPRECATED METHODS

    /**
     * @deprecated
     */
    private function encode_high($text, $charset = "UTF-8")
    {
        return mb_encode_numericentity($text, $this->cmap(), $charset);
    }

    /**
     * @deprecated
     */
    private function decode_high($text, $charset = "UTF-8")
    {
        return mb_decode_numericentity($text, $this->cmap(), $charset);
    }

    /**
     * @deprecated
     */
    private function cmap()
    {
        $f = 0xffff;
        $cmap = array(
            0x0080, 0xffff, 0, $f);
        return $cmap;
    }

    /**
     * @deprecated
     */
    private function incomingEntities($text)
    {
        return preg_replace("/&(?![#a-z0-9]+;)/i", "x%x%", $text);
    }

    /**
     * @deprecated
     */
    private function encodeEntities($text)
    {
        return (function_exists('mb_encode_numericentity'))
        ?	 $this->encode_high($text)
        :	 htmlentities($text, ENT_NOQUOTES, "utf-8");
    }

    /**
     * @deprecated
     */
    private function fixEntities($text)
    {
            /*	de-entify any remaining angle brackets or ampersands */
        return str_replace(array("&gt;", "&lt;", "&amp;"),
            array(">", "<", "&"), $text);
    }

} // end class Textile

?>