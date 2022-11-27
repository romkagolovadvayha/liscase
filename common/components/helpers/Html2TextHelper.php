<?php

namespace common\components\helpers;

use Soundasleep\Html2Text;

class Html2TextHelper extends Html2Text
{
    static function iterateOverNode($node, $prevName = null, $in_pre = false, $is_office_document = false, $options)
    {
        if ($node instanceof \DOMText) {
            // Replace whitespace characters with a space (equivilant to \s)
            if ($in_pre) {
                $text = "\n" . trim(static::renderText($node->wholeText), "\n\r\t ") . "\n";

                // Remove trailing whitespace only
                $text = preg_replace("/[ \t]*\n/im", "\n", $text);

                // armor newlines with \r.
                return str_replace("\n", "\r", $text);

            } else {
                $text = static::renderText($node->wholeText);
                $text = preg_replace("/[\\t\\n\\f\\r ]+/im", " ", $text);

                if (!static::isWhitespace($text) && ($prevName == 'p' || $prevName == 'div')) {
                    return "\n" . $text;
                }

                return $text;
            }
        }

        if ($node instanceof \DOMDocumentType || $node instanceof \DOMProcessingInstruction) {
            // ignore
            return "";
        }

        $name     = strtolower($node->nodeName);
        $nextName = static::nextChildName($node);

        // start whitespace
        switch ($name) {
            case "hr":
                $prefix = '';
                if ($prevName != null) {
                    $prefix = "\n";
                }

                return $prefix . "---------------------------------------------------------------\n";

            case "style":
            case "head":
            case "title":
            case "meta":
            case "script":
                // ignore these tags
                return "";

            case "h1":
            case "h2":
            case "h3":
            case "h4":
            case "h5":
            case "h6":
            case "ol":
            case "ul":
            case "pre":
                // add two newlines
                $output = "\n\n";
                break;

            case "td":
            case "th":
                // add tab char to separate table fields
                $output = "\t";
                break;

            case "p":
                // Microsoft exchange emails often include HTML which, when passed through
                // html2text, results in lots of double line returns everywhere.
                //
                // To fix this, for any p element with a className of `MsoNormal` (the standard
                // classname in any Microsoft export or outlook for a paragraph that behaves
                // like a line return) we skip the first line returns and set the name to br.
                if ($is_office_document && $node->getAttribute('class') == 'MsoNormal') {
                    $output = "";
                    $name   = 'br';
                    break;
                }

                // add two lines
                $output = "\n\n";
                break;

            case "tr":
                // add one line
                $output = "\n";
                break;

            case "div":
                $output = "";
                if ($prevName !== null) {
                    // add one line
                    $output .= "\n";
                }
                break;

            case "li":
                $output = "- ";
                break;

            default:
                // print out contents of unknown tags
                $output = "";
                break;
        }

        // debug
        //$output .= "[$name,$nextName]";

        if (isset($node->childNodes)) {

            $n                    = $node->childNodes->item(0);
            $previousSiblingNames = [];
            $previousSiblingName  = null;

            $parts               = [];
            $trailing_whitespace = 0;

            while ($n != null) {

                $text = static::iterateOverNode($n, $previousSiblingName, $in_pre || $name == 'pre',
                    $is_office_document, $options);

                // Pass current node name to next child, as previousSibling does not appear to get populated
                if ($n instanceof \DOMDocumentType
                    || $n instanceof \DOMProcessingInstruction
                    || ($n instanceof \DOMText && static::isWhitespace($text))) {
                    // Keep current previousSiblingName, these are invisible
                    $trailing_whitespace++;
                } else {
                    $previousSiblingName    = strtolower($n->nodeName);
                    $previousSiblingNames[] = $previousSiblingName;
                    $trailing_whitespace    = 0;
                }

                $node->removeChild($n);
                $n = $node->childNodes->item(0);

                $parts[] = $text;
            }

            // Remove trailing whitespace, important for the br check below
            while ($trailing_whitespace-- > 0) {
                array_pop($parts);
            }

            // suppress last br tag inside a node list if follows text
            $last_name = array_pop($previousSiblingNames);
            if ($last_name === 'br') {
                $last_name = array_pop($previousSiblingNames);
                if ($last_name === '#text') {
                    array_pop($parts);
                }
            }

            $output .= implode('', $parts);
        }

        // end whitespace
        switch ($name) {
            case "h1":
            case "h2":
            case "h3":
            case "h4":
            case "h5":
            case "h6":
            case "pre":
            case "p":
                // add two lines
                $output .= "\n\n";
                break;

            case "br":
                // add one line
                $output .= "\n";
                break;

            case "div":
                break;

            case "a":
                // links are returned in [text](link) format
                $href = $node->getAttribute("href");

                $output = trim($output);

                // remove double [[ ]] s from linking images
                if (substr($output, 0, 1) == "[" && substr($output, -1) == "]") {
                    $output = substr($output, 1, strlen($output) - 2);

                    // for linking images, the title of the <a> overrides the title of the <img>
                    if ($node->getAttribute("title")) {
                        $output = $node->getAttribute("title");
                    }
                }

                // if there is no link text, but a title attr
                if (!$output && $node->getAttribute("title")) {
                    $output = $node->getAttribute("title");
                }

                if ($href == null) {
                    // it doesn't link anywhere
                    if ($node->getAttribute("name") != null) {
                        if ($options['drop_links']) {
                            $output = "$output";
                        } else {
                            $output = "[$output]";
                        }
                    }
                } else {
                    if ($href == $output || $href == "mailto:$output" || $href == "http://$output"
                        || $href == "https://$output") {
                        // link to the same address: just use link
                        $output = "$output";
                    } else {
                        // replace it
                        if ($output) {
                            if ($options['drop_links']) {
                                $output = "$output";
                            } else {
                                $output = "[$output]($href)";
                            }
                        } else {
                            // empty string
                            $output = "$href";
                        }
                    }
                }

                // does the next node require additional whitespace?
                switch ($nextName) {
                    case "h1":
                    case "h2":
                    case "h3":
                    case "h4":
                    case "h5":
                    case "h6":
                        $output .= "\n";
                        break;
                }
                break;

            case "img":
                if ($node->getAttribute("alt")) {
                    $output = $node->getAttribute("alt");
                } elseif ($node->getAttribute("title")) {
                    $output = $node->getAttribute("title");
                } else {
                    $output = "";
                }
                break;

            case "li":
                $output .= "\n";
                break;

            case "blockquote":
                // process quoted text for whitespace/newlines
                $output = static::processWhitespaceNewlines($output);

                // add leading newline
                $output = "\n" . $output;

                // prepend '> ' at the beginning of all lines
                $output = preg_replace("/\n/im", "\n> ", $output);

                // replace leading '> >' with '>>'
                $output = preg_replace("/\n> >/im", "\n>>", $output);

                // add another leading newline and trailing newlines
                $output = "\n" . $output . "\n\n";
                break;
            default:
                // do nothing
        }

        return $output;
    }
}