<?php 

namespace BCCHR\CustomTemplateEngine;

/**
 * Require Smarty class.
 */
require_once "vendor/autoload.php";

use REDCap;
use Smarty;
use DOMDocument;

class Template
{
    /**
     * Class properties.
     * 
     * @var Smarty $smarty      Instance of Smarty object.
     * @var Array $redcap       Stores variables that will be used by Smarty template engine to fill data.
     * 
     * @var Boolean $show_label_and_row         Whether to show a label or row or not in template.
     * @var String $de_identified_replacement   What to replace de-identified data with.
     * @var Array $logical_operators            Allowed logical operators.
     */
    private $smarty;
    private $redcap;
    private $dictionary;
    private $instruments;

    private $show_label_and_row = true;
    private $de_identified_replacement = "[DE-IDENTIFIED]";
    private $logical_operators = array("eq", "ne", "neq", "gt", "lt", "ge", "gte", "lte", "le", "not", "or", "and");

    /**
     * Class constructor.
     * 
     * @param String $templates_dir     Directory where templates are stored.
     * @param String $compiled_dir      Directory where templates compiled by Smarty are stored.
     */
    function __construct($templates_dir, $compiled_dir) 
    {
        $this->dictionary = REDCap::getDataDictionary('array', false);
        $this->instruments = REDCap::getInstrumentNames();
        $this->smarty = new Smarty();
        $this->smarty->setTemplateDir($templates_dir);
        $this->smarty->setCompileDir($compiled_dir);
        $this->smarty->assign("showLabelAndRow", $this->show_label_and_row);
    }

    /**
     * Retrieves empty child nodes within given element.
     * 
     * @access private
     * @param DOMNode $elem     Root element.
     * @return Array            An array of all empty nodes.
     */
    private function getEmptyNodes($elem)
    {
        $empty_elems = array();

        if ($elem->hasChildNodes())
        {
            foreach($elem->childNodes as $child)
            {
                $empty_child_elems = $this->getEmptyNodes($child);
                $empty_elems = array_merge($empty_elems, $empty_child_elems);
            }
        }
        /**
         * Checks for special whitespace characters, and tags that don't contain children.
         */
        else if ((ctype_space($elem->nodeValue) || str_replace(array(" ", "\xC2\xA0"), "", $elem->nodeValue) == "") && 
                $elem->tagName != "img" && $elem->tagName != "body" && $elem->tagName != "hr" && $elem->tagName != "br")
        {
            /**
             * Empty table data elements and headers may pad out other data in table. 
             * Make sure the entire row is empty, before removing.
             */
            if ($elem->tagName == "td")
            {
                if (empty($elem->previousSibling) && $elem->previousSibling->tagName != "td")
                {
                    $empty_elems[] = $elem;
                }
            }
            else if ($elem->tagName == "th")
            {
                if (empty($elem->previousSibling) && $elem->previousSibling->tagName != "th")
                {
                    $empty_elems[] = $elem;
                }
            }
            else
            {
                $empty_elems[] = $elem;
            }
        }
        
        return $empty_elems;
    }

    /**
     * Parses event data into an assosiative array that will be used by Smarty to fill templates 
     * with data.
     * 
     * Builds the array used in filling template data. Values are parsed according to the user's 
     * data rights. i.e. Identifiers removed, no unvalidated text fields, etc... Values are associated with
     * field names, and field names are associated with event names (if project is longitudinal).
     * 
     * @access private
     * @param Array $event_data     Event data for a REDcap record.
     * @return Array                An associative array of fields mapped to values, or events mapped to an array of fields mapped to values (if longitudinal). 
     */
    private function parseEventData($event_data)
    {
        $user = strtolower(USERID);
        $rights = REDCap::getUserRights($user);
        
        $external_fields = array();
        $this->instruments = REDCap::getInstrumentNames();
        foreach ($this->instruments as $unique_name => $label)
        {   
            $external_fields[] = "{$unique_name}_complete";
            $external_fields[] = "{$unique_name}_timestamp";
        }

        $event_fields_and_vals = array();
        foreach($event_data as $field_name => $value)
        {
            $value = trim(strip_tags($value));
            if (in_array($field_name, $external_fields))
            {
                $event_fields_and_vals[$field_name] = $value;
            }
            else if ($field_name !== "redcap_event_name")
            {
                if ($this->dictionary[$field_name]["field_type"] === "checkbox")
                {
                    /*
                    * Check if user's data rights
                    * 
                    * De-identified: All unvalidated text fields & notes will be removed, as well as any date/time fields and Identifier fields.
                    * 
                    * Remove all tagged Identifier Fields: Only identifiers removed.
                    * 
                    */
                    if (($rights[$user]["data_export_tool"] === "2" || $rights[$user]["data_export_tool"] === "3") && $this->dictionary[$field_name]["identifier"] === "y")
                    {
                        $event_fields_and_vals[$field_name] = array();
                        $event_fields_and_vals[$field_name]["allValues"] = $this->de_identified_replacement;
                    }
                    else
                    {
                        $all_choices = explode("|", $this->dictionary[$field_name]["select_choices_or_calculations"]);
                        $all_choices = array_map(function ($v) {
                            $v = strip_tags($v);
                            $first_comma = strpos($v, ",");
                            return trim(substr($v, $first_comma + 1));
                        }, $all_choices);

                        foreach($all_choices as $choice)
                        {
                            if (strpos($value, $choice) !== FALSE)
                            {
                                $event_fields_and_vals[$field_name][] = $choice;
                            }
                        }

                        $event_fields_and_vals[$field_name]["allValues"] = implode(", ", explode(",", $value));
                    }
                }
                else
                {
                    /*
                    * Check if user's data rights
                    * 
                    * De-identified: All unvalidated text fields & notes will be removed, as well as any date/time fields and Identifier fields.
                    * 
                    * Remove all tagged Identifier Fields: Only identifiers removed.
                    * 
                    */
                    if (($rights[$user]["data_export_tool"] === "2" && 
                            ($this->dictionary[$field_name]["field_type"] === "notes" ||
                            ($this->dictionary[$field_name]["field_type"] === "text" && (in_array($this->dictionary[$field_name]["text_validation_type_or_show_slider_number"], $this->date_formats) ||
                                                                                    empty($this->dictionary[$field_name]["text_validation_type_or_show_slider_number"]))))
                        ) ||
                        (($rights[$user]["data_export_tool"] === "2" || $rights[$user]["data_export_tool"] === "3") && $this->dictionary[$field_name]["identifier"] === "y"))
                    {
                        $event_fields_and_vals[$field_name] = $this->de_identified_replacement;
                    }
                    else if($this->dictionary[$field_name]["field_type"] === "notes")
                    {
                        $event_fields_and_vals[$field_name] = str_replace("\r\n", "<br/>", htmlentities($value));
                    }
                    else
                    {
                        $event_fields_and_vals[$field_name] = $value;
                    }
                }
            }
        }
        return $event_fields_and_vals;
    }

    /**
     * Replaces given text with replacement.
     * 
     * @access private
     * @param String $text          The text to replace.
     * @param String $replacement   The replacement text.
     * @return String               A string with the replaced text.
     */
    private function replaceStrings($text, $replacement)
    {
        preg_match_all("/'/", $text, $quotes, PREG_OFFSET_CAPTURE);
        $quotes = $quotes[0];
        if (sizeof($quotes) % 2 === 0)
        {
            $i = 0;
            $to_replace = array();
            while ($i < sizeof($quotes))
            {
                $to_replace[] = substr($text, $quotes[$i][1], $quotes[$i + 1][1] - $quotes[$i][1] + 1);
                $i = $i + 2;
            }

            $text = str_replace($to_replace, $replacement, $text);
        }
        return $text;
    }

    /**
     * Parses a syntax string into blocks.
     * 
     * @access private
     * @param String $syntax     The syntax to parse.
     * @return Array             An array of blocks that make up the syntax passed.
     */
    private function getSyntaxParts($syntax)
    {
        $syntax = str_replace(array("['", "']"), array("[", "]"), $syntax);
        $syntax = $this->replaceStrings(trim($syntax), "''");         //Replace strings with ''

        $parts = array();
        $previous = array();

        $i = 0;
        while($i < strlen($syntax))
        {
            $char = $syntax[$i];
            switch($char)
            {
                case ",":
                case "(":
                case ")":
                case "]":
                    $part = trim(implode("", $previous));
                    $previous = array();
                    if ($part !== "")
                    {
                        $parts[] = $part;
                    }
                    $parts[] = $char;
                    $i++;
                    break;
                case "[":
                    if ($syntax[$i-1] == " ")
                    {
                        $parts[] = " ";
                    }
                    $part = trim(implode("", $previous));
                    if ($part !== "")
                    {
                        $parts[] = $part;
                    }
                    $parts[] = $char;
                    $previous = array();
                    $i++;
                    break;
                case " ":
                    $part = trim(implode("", $previous));
                    $previous = array();
                    if ($part !== "")
                    {
                        $parts[] = $part;
                    }
                    $i++;
                    break;
                default:
                    $previous[] = $char;
                    if ($i == strlen($syntax) - 1)
                    {
                        $part = trim(implode("", $previous));
                        if ($part !== "")
                        {
                            $parts[] = $part;
                        }
                    }
                    $i++;
                    break;
            }
        }

        return $parts;
    }

    /**
     * Checks whether fields and events exist within project
     * 
     * @access private
     * @param String $text      The line of text to validate.
     * @return Array            An array of errors, with the line number appended to indicate where it occured.
     */
    private function isValidFieldOrEvent($var)
    {
        $var = trim($var, "'");

        $events = REDCap::getEventNames(true, true); // If there are no events (the project is classical), the method will return false

        /**
         * Get REDCap completion fields
         */
        $external_fields = array();
        foreach ($this->instruments as $unique_name => $label)
        {   
            $external_fields[] = "{$unique_name}_complete";
            $external_fields[] = "{$unique_name}_timestamp";
        }

        if ($var !== "allValues" && !in_array($var, $external_fields))
        {
            $dictionary = $this->dictionary[$var];
            if (($events === FALSE && empty($dictionary)) ||
                ($events !== FALSE && !in_array($var, $events) && empty($dictionary)))
            {
                return false;
            }
        }
        return true;
    }

    /**
     * Checks whether fields and events are being queried correctly.
     * 
     * @access private
     * @param String $text       The line of text to validate.
     * @param Integer $line_num  The current line number in the template.
     * @return Array             An array of errors, with the line number appended to indicate where it occured.
     */
    private function validateFieldQueries($text, $line_num)
    {
        $errors = array();

        // Get all occurences of an opening square bracket "["
        preg_match_all('/\[/', $text, $opening_brackets, PREG_OFFSET_CAPTURE);

        if (!empty($opening_brackets[0]))
        {
            // Get the event/field name between each opening and closing bracket, and check if it exists
            foreach($opening_brackets[0] as $bracket)
            {
                $start_pos = $bracket[1]+1;

                $closing_bracket = strpos($text, "]", $start_pos);
                if ($closing_bracket !== FALSE)
                {
                    $var = substr($text, $start_pos, $closing_bracket - $start_pos);
                    if (substr($var, 0, 1) !== "'" && substr($var, strlen($var)-1, 1) !== "'")
                    {
                        $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] <strong>'$var'</strong> must be enclosed with single quotes.";
                    }

                    $var = trim($var, "'\"");

                    if ($var !== "allValues")
                    {
                        $dictionary = $this->dictionary[$var];
                        if (!empty($dictionary))
                        {
                            if ($dictionary["field_type"] === "checkbox")
                            {
                                // Check if checkbox is queried via in_array()
                                $in_array = false;
                                $in_array_start_pos = 0;
                                while (($in_array_start_pos = strpos($text, "in_array('", $in_array_start_pos)) !== FALSE)
                                {
                                    $end_of_check_value = strpos($text, "', \$redcap", $in_array_start_pos+1);
                                    $in_array_end_pos = strpos($text, ")", $end_of_check_value+1);
                                    if (($in_array_start_pos < $start_pos) && ($in_array_end_pos > $start_pos))
                                    {
                                        $in_array = true;
                                        break;
                                    }
                                    $in_array_start_pos = $in_array_start_pos + 1;
                                }

                                // check if checkbox is queried via ['allValues']
                                $all_values_str = substr($text, $closing_bracket+1, 13);
                                if ($all_values_str !== "['allValues']" && !$in_array)
                                {   
                                    $errors[] =  "<b>ERROR</b> [EDITOR] LINE [$line_num] <strong>'$var'</strong> is a checkbox and can only be queried using in_array or \$redcap['$var']['allValues']";
                                }
                            }
                        }
                    }
                }
            }
        }
        return $errors;
    }

    /**
     * Validate syntax.
     * 
     * @access private
     * @see Template::validateFieldQueries()    For checking whether fields and events are queried correctly.
     * @see Template::getSyntaxParts()          For retreiving blocks of syntax from the given syntax string.
     * @param String $syntax                    The syntax to validate.
     * @param Integer $line_num                 The current line number in the template.
     * @return Array                            An array of errors, with the line number appended to indicate where it occured.
     */
    private function validateSyntax($syntax, $line_num)
    {
        if (!empty($syntax))
        {
            $errors = $this->validateFieldQueries($syntax, $line_num);

            if ((sizeof(explode("'", $syntax)) - 1) % 2 > 0)
            {
                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Odd number of single quotes exist. You've either added an extra quote, forgotten to close one, or forgotten to escape one.";
            }
            else if ($syntax != strip_tags($syntax))
            {
                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Report logic cannot have any HTML between {}";
            }
            else if (preg_match("/{/", $syntax) !== 0 || preg_match("/}/", $syntax) !== 0)
            {
                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] There is either a '{'  or '}' within {}. They are special characters and can only denote the beginning and end of syntax.";
            }
            
            // Check symmetry of ()
            if (sizeof(array_keys($parts, "(")) != sizeof(array_keys($parts, ")")))
            {
                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Odd number of parenthesis (. You've either added an extra parenthesis, or forgot to close one.";
            }

            // Check symmetry of []
            if (sizeof(array_keys($parts, "[")) != sizeof(array_keys($parts, "]")))
            {
                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Odd number of square brackets [. You've either added an extra bracket, or forgot to close one.";
            }
            
            $parts = $this->getSyntaxParts($syntax);

            foreach($parts as $index => $part)
            {
                switch ($part) {
                    case "if":
                    case "elseif":
                        // Must have either a ( or ) or $redcap or $showLabelAndRow or in_array after
                        if ($index != sizeof($parts) - 1)
                        {
                            if ($index !== 0)
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Mal-formed <strong>$part</strong> condition. <strong>$part</strong> clause must be first part of syntax.";
                            }
                            else 
                            {
                                $next_part = $parts[$index + 1];

                                if (($next_part !== "(" 
                                    && ($next_part != "''" && $previous == "in_array")
                                    && $next_part !== ")" 
                                    && $next_part !== "\$redcap" 
                                    && $next_part !== "\$showLabelAndRow" 
                                    && $next_part !== "in_array"
                                    && $next_part !== "''"))
                                {
                                    $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Invalid <strong>$next_part</strong> after <strong>$part</strong>.";
                                }
                            }
                        }
                        else
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Mal-formed <strong>$part</strong> condition. You cannot have an empty <strong>$part</strong> clause.";
                        }
                        break;
                    case "(":
                        $previous = $parts[$index - 1];
                        $next_part = $parts[$index + 1];
                    
                        if (($next_part !== "(" 
                            && ($next_part != "''" && $previous == "in_array")
                            && $next_part !== ")" 
                            && $next_part !== "\$redcap" 
                            && $next_part !== "\$showLabelAndRow" 
                            && $next_part !== "in_array"
                            && $next_part !== "''"))
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Invalid <strong>$next_part</strong> after <strong>(</strong>.";
                        }
                        else if ($next_part == "(" && $previous == "in_array")
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Malformed <strong>in_array()</strong> function.";
                        }
                        break;
                    case ")":
                        // Must have either a ) or logical operator after, if not the last part of syntax
                        if ($index != sizeof($parts) - 1)
                        {
                            $next_part = $parts[$index + 1];
                            if ($next_part !== ")" && !in_array($next_part, $this->logical_operators))
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Invalid <strong>$next_part</strong> after <strong>)</strong>.";
                            }
                        }
                        break;
                    case "eq":
                    case "ne":
                    case "neq":
                    case "gt":
                    case "ge":
                    case "gte":
                    case "lt":
                    case "le":
                    case "lte":
                        // Must have either a ( or $redcap or $showLabelAndRow or in_array or string or not after
                        // If there's another logical operator two spaces before, is illegal.
                        if ($index == 0)
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Cannot have a comparison operator <strong>$part</strong> as the first part in syntax.";
                        }
                        else if ($index != sizeof($parts) - 1)
                        {
                            $previous = $parts[$index - 2];
                            $next_part = $parts[$index + 1];

                            if (in_array($previous, $this->logical_operators) && $previous !== "or" && $previous !== "and")
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Invalid <strong>$part</strong>. You cannot chain comparison operators together, you must use an <strong>and</strong> or an <strong>or</strong>";
                            }
                            if (!empty($next_part) 
                                && $next_part !== "(" 
                                && $next_part !== "\$redcap" 
                                && $next_part !== "\$showLabelAndRow" 
                                && $next_part !== "''" 
                                && $next_part !== "in_array"
                                && $next_part !== "not" 
                                && !is_numeric($next_part))
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Invalid <strong>$next_part</strong> after <strong>$part</strong>.";
                            }
                        }
                        else
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Cannot have a comparison operator <strong>$part</strong> as the last part in syntax.";
                        }
                        break;
                    case "not":
                    case "or":
                    case "and":
                        // Must have either a ( or $redcap or $showLabelAndRow or in_array or string or not after
                        if ($index == 0)
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Cannot have a logical operator <strong>$part</strong> as the first part in syntax.";
                        }
                        else if ($index != sizeof($parts) - 1)
                        {
                            $next_part = $parts[$index + 1];
                            if (!empty($next_part) 
                                && $next_part !== "(" 
                                && $next_part !== "\$redcap" 
                                && $next_part !== "\$showLabelAndRow" 
                                && $next_part !== "''" 
                                && $next_part !== "in_array"
                                && $next_part !== "not" 
                                && !is_numeric($next_part))
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Invalid <strong>$next_part</strong> after <strong>$part</strong>.";
                            }
                        }
                        else
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Cannot have a logical operator <strong>$part</strong> as the last part in syntax.";
                        }
                        break;
                    case "''":
                        // Must have either a logical operator or ) or , or ]
                        if ($index != sizeof($parts) - 1)
                        {
                            $next_part = $parts[$index + 1];
                            if ($next_part !== ")" 
                                && $next_part != "," 
                                && $next_part != "]"
                                && !in_array($next_part, $this->logical_operators))
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Invalid <strong>$next_part</strong> after string value within ''.";
                            }
                        }
                        break;
                    case "\$redcap":
                        // Must have a [
                        if ($index != sizeof($parts) - 1)
                        {
                            $next_part = $parts[$index + 1];
                            if ($next_part !== "[")
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Invalid <strong>\$redcap</strong> field query.";
                            }
                        }
                        else
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Invalid <strong>\$redcap</strong> field query at end of syntax.";
                        }
                        break;
                    case "[":
                        // Must have a ''
                        if ($index != sizeof($parts) - 1)
                        {
                            $previous = $parts[$index - 1];
                            $next_part = $parts[$index + 1];

                            if ($previous !== "\$redcap" && $previous !== "]")
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Each field and events query must be preceeded by <strong>\$redcap</strong>";
                            }

                            if ($next_part == "]")
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Cannot have empty [] brackets";
                            }
                        }
                        else
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Cannot have opening <strong>[</strong> as end of syntax.";
                        }
                        break;
                    case "\$showLabelAndRow":
                        // Must have either a logical operator or ) after, if not last item in syntax
                        if ($index != sizeof($parts) - 1)
                        {
                            $next_part = $parts[$index + 1];
                            if ($next_part !== ")" 
                                && !in_array($next_part, $this->logical_operators))
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Invalid <strong>$next_part</strong> after <strong>$part</strong>.";
                            }
                        }
                        break;
                    case "]":
                        // Must have either a logical operator or ) or [ after, if not last item in syntax
                        if ($index != sizeof($parts) - 1)
                        {
                            $previous_2 = $parts[$index - 2];
                            $next_part = $parts[$index + 1];

                            if ($previous_2 !== "[")
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Unclosed or empty <strong>]</strong> bracket.";
                            }

                            if ($next_part !== ")" 
                                && $next_part !== "["
                                && !in_array($next_part, $this->logical_operators))
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Invalid <strong>'$next_part'</strong> after <strong>$part</strong>.";
                            }
                        }
                        break;
                    case ",":
                        // Must have a $redcap field query after, has to be part of in_array function call
                        if ($index != sizeof($parts) - 1)
                        {
                            $previous = $parts[$index - 1];
                            $previous_2 = $parts[$index - 2];
                            $previous_3 = $parts[$index - 3];

                            if ($previous_3 !== "in_array" || $previous_2 !== "(" || $previous !== "''")
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Improper use of <strong>,</strong> in syntax. Are you trying to call in_array()?";
                            }

                            $next_part = $parts[$index + 1];
                            if ($next_part !== "\$redcap")
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num]. Invalid <strong>$next_part</strong> after <strong>,</strong>.";
                            }
                        }
                        else
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] <strong>$part</strong> cannot be end of syntax";
                        }
                        break;
                    case "in_array":
                        // Must have a ( after
                        if ($index != sizeof($parts) - 1)
                        {
                            $next_part = $parts[$index + 1];
                            if ($next_part !== "(")
                            {
                                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Malformed <strong>in_array()</strong> function.";
                            }
                        }
                        else
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Malformed <strong>in_array()</strong> function.";
                        }
                        break;
                    case "else":
                    case "/if":
                        // Must be the only clause in syntax
                        if ($index != sizeof($parts) - 1)
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Mal-formed <strong>$part</strong> clause. Must be of form <strong>{{$part}}</strong>.";
                        }
                        break;
                    default:
                        // If it's a number, must have ) or logical operator after, if not last item in syntax
                        if (is_numeric($part))
                        {
                            if ($index != sizeof($parts) - 1)
                            {
                                $next_part = $parts[$index + 1];
                                if (!empty($next_part) && $next_part !== ")" && !in_array($next_part, $this->logical_operators))
                                {
                                    $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Invalid <strong>$next_part</strong> after <strong>$part</strong>.";
                                }
                            }
                        }
                        // Check if it's a string
                        else if (!empty($part) && 
                                $part[0] != "'" && 
                                $part[0] != "\"" && 
                                $part[strlen($part) - 1] != "'" && 
                                $part[strlen($part) - 1] != "\"" &&
                                !$this->isValidFieldOrEvent($part))
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] <strong>$part</strong> is not a valid event/field/syntax in this project";
                        }
                        break;
                }
            }
        }
        return $errors;
    }

    /**
     * Validate if statements, by checking the following:
     *      
     * - If statements have matching opening and closing statements.
     * - Elseifs are associated with an if statement.
     * - Every if statement has at most one else clause.
     * 
     * @access private
     * @param Array $lines      An array of lines within template.
     * @return Array            An array of errors, with their line numbers appended to indicate where it occured.
     */
    private function validateIfStatements($lines)
    {
        $errors = array();

        // Find all occurences of opening {if, {/if}
        $opening_ifs = array();
        $closing_ifs = array();

        foreach($lines as $index => $line)
        {
            // Could be multiple statements on same line
            $last_pos = 0;
            while(($last_pos = strpos($line, '{if', $last_pos)) !== FALSE)
            {
                $line_num = $index + 1;
                if ($line[$last_pos + 3] !== " ")
                {
                    $errors[] =  "<b>ERROR</b> [EDITOR] LINE [$line_num] Mal-formed if/elseif statement. Need a space after 'if'";
                }

                $opening_ifs[] = array(
                    "line_num" => $line_num,
                    "line_pos" => $last_pos
                );
                $last_pos++;
            }

            $last_pos = 0;
            while(($last_pos = strpos($line, '{/if}', $last_pos)) !== FALSE)
            {
                $closing_ifs[] = array(
                    "line_num" => $index + 1,
                    "line_pos" => $last_pos
                );
                $last_pos++;
            }
        }

        $num_opening_ifs = sizeof($opening_ifs);
        $num_closing_ifs = sizeof($closing_ifs);

        $opening_ifs_copy = array_reverse($opening_ifs);
        $closing_ifs_copy = array_reverse($closing_ifs);

        foreach($opening_ifs_copy as $index => $opening_if)
        {
            $key = null;
            for ($i = 0; $i < sizeof($closing_ifs_copy); $i++)
            {
                $closing_if = $closing_ifs_copy[$i];

                if (($opening_if["line_num"] < $closing_if["line_num"]) ||
                    ($opening_if["line_num"] == $closing_if["line_num"] && $opening_if["line_pos"] < $closing_if["line_pos"]))
                {
                    $key = $i;
                    break;
                }
            }

            if (!is_null($key))
            {
                unset($closing_ifs_copy[$key]);
                $closing_ifs_copy = array_values($closing_ifs_copy);
            }
            else
            {
                $errors[] = "<b>ERROR</b> [EDITOR] LINE [" . $opening_if["line_num"] . "] Missing {/if}";
            }
        }

        foreach($closing_ifs_copy as $closing_if)
        {
            $errors[] = "<b>ERROR</b> [EDITOR] LINE [" . $closing_if["line_num"] . "] Extra {/if}";
        }
            
        if (empty($errors))
        {
            // Find all occurences of {elseif, {else}
            $elseifs = array();
            $elses = array();

            foreach($lines as $index => $line)
            {
                $last_pos = 0;
                while(($last_pos = strpos($line, '{elseif', $last_pos)) !== FALSE)
                {
                    $line_num = $index + 1;
                    if ($line[$last_pos + 7] !== " ")
                    {
                        $errors[] =  "<b>ERROR</b> [EDITOR] LINE [$line_num] Mal-formed if/elseif statement. Need a space after 'elseif'";
                    }

                    $elseifs[] = array(
                        "line_num" =>  $line_num,
                        "line_pos" => $last_pos
                    );
                    $last_pos++;
                }

                $last_pos = 0;
                while(($last_pos = strpos($line, '{else}', $last_pos)) !== FALSE)
                {
                    $elses[] = array(
                        "line_num" => $index + 1,
                        "line_pos" => $last_pos
                    );
                    $last_pos++;
                }
            }

            $num_elses = sizeof($elses);
            $num_elseifs = sizeof($elseifs);

            // Check that every elseif clause is associated with an if statement.
            foreach($elseifs as $elseif)
            {
                $line_num = $elseif["line_num"];
                $line_pos = $elseif["line_pos"];

                if ($num_opening_ifs == 0)
                {
                    $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Every {elseif} clause must be associated with an {if} statement";
                }
                else
                {
                    for ($i = 0; $i < $num_opening_ifs; $i++)
                    {
                        $opening_if = $opening_ifs[$i];
                        $closing_if = $closing_ifs[$i];

                        // Check if there's an else clause between the elseif and the opening if
                        $arr = array_filter($elses, function($v) use ($opening_if, $closing_if, $line_num, $line_pos) {
                            $v_line_num = $v["line_num"];
                            $v_line_pos = $v["line_pos"];

                            if ($opening_if["line_num"] < $v_line_num && $v_line_num < $closing_if["line_num"] && $v_line_num < $line_num)
                            {
                                return true;
                            }
                            else if (($opening_if["line_num"] == $v_line_num && $v_line_num == $closing_if["line_num"] && $v_line_num == $line_num) || 
                                    ($opening_if["line_num"] == $v_line_num && $v_line_num < $closing_if["line_num"] && $v_line_num == $line_num) || 
                                    ($opening_if["line_num"] < $v_line_num && $v_line_num == $closing_if["line_num"] && $v_line_num == $line_num) ||
                                    ($opening_if["line_num"] < $v_line_num && $v_line_num < $closing_if["line_num"] && $v_line_num == $line_num))
                            {
                                return $v_line_pos < $line_pos;
                            }
                        });
                            
                        if (($opening_if["line_num"] < $line_num && $line_num < $closing_if["line_num"] && empty($arr)) ||
                            ($opening_if["line_num"] == $line_num && $line_num == $closing_if["line_num"] && $opening_if["line_pos"] < $line_pos && $line_pos < $closing_if["line_pos"] && empty($arr)) ||
                            ($opening_if["line_num"] == $line_num && $line_num < $closing_if["line_num"] && $opening_if["line_pos"] < $line_pos && empty($arr)) ||
                            ($opening_if["line_num"] < $line_num && $line_num == $closing_if["line_num"] && $line_pos < $closing_if["line_pos"] && empty($arr)))
                        {
                            break;
                        }
                        else if ($i == $num_opening_ifs - 1)
                        {
                            $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Every {elseif} clause must be associated with an {if} statement";
                        }
                    }
                }
            }

            // Check that every if statement has at most one else clause, and that every else clause is associated with an if statement.
            foreach($elses as $index => $else)
            {
                $key = null;

                $line_num = $else["line_num"];
                $line_pos = $else["line_pos"];

                for ($i = 0; $i < sizeof($opening_ifs); $i++)
                {
                    $opening_if = $opening_ifs[$i];
                    $closing_if = $closing_ifs[$i];

                    if (($opening_if["line_num"] < $line_num && $line_num < $closing_if["line_num"]) ||
                        ($opening_if["line_num"] == $line_num && $line_num == $closing_if["line_num"] && $opening_if["line_pos"] < $line_pos && $line_pos < $closing_if["line_pos"]) ||
                        ($opening_if["line_num"] == $line_num && $line_num < $closing_if["line_num"] && $opening_if["line_pos"] < $line_pos) ||
                        ($opening_if["line_num"] < $line_num && $line_num == $closing_if["line_num"] && $line_pos < $closing_if["line_pos"]))
                    {
                        $key = $i;
                        break;
                    }
                }

                if (!is_null($key))
                {
                    unset($opening_ifs[$i]);
                    unset($closing_ifs[$i]);

                    $opening_ifs = array_values($opening_ifs);
                    $closing_ifs = array_values($closing_ifs);
                }
                else
                {
                    $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Template either has more than one {else} clause within an {if} statement, or an {else} clause outside of an {if} statement.";
                }
            }
        }

        return $errors;
    }

    /**
     * Performs template validation.
     * 
     * Checks that there are no unclosed curly brackets within the template, as they're special characters that denote
     * Smarty syntax. If validation passes, then retrieve all text enclosed within curly brackets, and validate them.
     * 
     * @see Template::validateSyntax()            For validating all syntax, escept if statements.
     * @see Template::validateIfStatements()    For validating if statements.
     * @param String $template_data             Template contents.
     * @return Array                            An array of errors, with their line numbers appended to indicate where it occured.
     */
    public function validateTemplate($template_data)
    {
        // Decode all HTML entities, and replace escaped quotes with a backtick.
        $template_data = preg_replace("/\\\\'/", "`", html_entity_decode($template_data, ENT_HTML5 | ENT_QUOTES));

        $errors = array();

        $lines = explode("\n", $template_data);

        foreach($lines as $index => $line)
        {
            $line_num = $index + 1;

            // Find all occurences of opening { and closing }
            preg_match_all('/{/', $line, $opening_brackets, PREG_OFFSET_CAPTURE);
            preg_match_all('/}/', $line, $closing_brackets, PREG_OFFSET_CAPTURE);

            if (sizeof($opening_brackets[0]) != sizeof($closing_brackets[0]))
            {
                $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Has uneven number of '{' and '}' brackets. Please check there are no extra opening or unclosed brackets.";
            }
            else
            {
                // Get the smarty syntax between each pair of {}
                foreach($opening_brackets[0] as $bracket)
                {
                    $start_pos = $bracket[1]+1;
                    $closing_bracket = strpos($line, "}", $start_pos);
                    if ($closing_bracket !== FALSE)
                    {
                        $text = substr($line, $start_pos, $closing_bracket - $start_pos);
                        $errors = array_merge($errors, $this->validateSyntax($text, $line_num));
                    }
                    else
                    {
                        $errors[] = "<b>ERROR</b> [EDITOR] LINE [$line_num] Is missing a closing '}'.";
                    }
                }
            }
        }

        return array_merge($errors, $this->validateIfStatements($lines));
    }

    /**
     * Fill a given template with REDCap record data.
     * 
     * Retrieves REDCap data, and parses it into an array used by Smarty to fill the
     * template with data. After using Smarty to fill the template, empty nodes are 
     * found and deleted.
     * 
     * @see Template::parseEventData()  For parsing event data into Array used in Smarty.
     * @see Template::getEmptyNodes()   For retrieving empty nodes in HTML.
     * @param String $template_name     The Template name.
     * @param Integer $record           A REDCap record ID.
     * @return String                   The template filled with REDCap record data.
     */
    public function fillTemplate($template_name, $record)
    {
        $filled_template = "";

        $user = strtolower(USERID);
        $rights = REDCap::getUserRights($user);

        $template = REDCap::getData("json", $record, null, null, null, TRUE, FALSE, TRUE, null, TRUE);

        $json = json_decode($template, true);

        $repeatable_instruments_parsed = array();

        if (REDCap::isLongitudinal())
        {
            $this->redcap = array();

            $event_ids = array_values(REDCap::getEventNames(TRUE));
            $event_labels = array_values(REDCap::getEventNames(FALSE, TRUE));

            $events = array();
            for ($i = 0 ; $i <= count($event_labels)-1; $i++)
            {
                $events[$event_labels[$i]] = $event_ids[$i];
            }

            $first_event = $event_ids[0];

            foreach($json as $index => $event_data)
            {
                $data = array();
                $event = $events[$event_data["redcap_event_name"]];
                if ($event_data["redcap_repeat_instance"] != "")
                {
                    // Repeatable instrument
                    if ($event_data["redcap_repeat_instrument"] != null && !in_array($event_data["redcap_repeat_instrument"], $repeatable_instruments_parsed)) 
                    {
                        // Get latest instance of repeatable instrument.
                        // Retrieve all repeatable instances of event. 
                        $repeatable_instrument_instances = array_filter($json, function($value) use($event_data){
                            return $value["redcap_repeat_instrument"] == $event_data["redcap_repeat_instrument"];
                        });
                        $repeatable_instrument_instances = array_values($repeatable_instrument_instances);

                        // Get the latest instance and parse it. 
                        $repeat_instances = array_column($repeatable_instrument_instances, "redcap_repeat_instance");
                        $latest_instance = max($repeat_instances);
                        $key = array_search($latest_instance, $repeat_instances);

                        if (empty($this->redcap[$event]))
                        {
                            $data = $this->parseEventData($repeatable_instrument_instances[$key]);   
                        }
                        else
                        { 
                            // Merges repeatable instrument data with non-repeatable instrument data in same event.
                            $data = $this->redcap[$event];
                            $repeatable_instrument_data = $this->parseEventData($repeatable_instrument_instances[$key]);
                            
                            foreach($repeatable_instrument_data as $field => $value)
                            {
                                if (empty($data[$field]))
                                    $data[$field] = $value;
                            }
                        }
                        
                        $repeatable_instruments_parsed[] = $event_data["redcap_repeat_instrument"];
                    }
                    // Repeatable event
                    else if (empty($this->redcap[$event]))
                    {
                        // Retrieve all repeatable instances of event. 
                        $repeatable_event_instances = array_filter($json, function($value) use($event_data){
                            return $value["redcap_event_name"] == $event_data["redcap_event_name"];
                        });
                        $repeatable_event_instances = array_values($repeatable_event_instances);
                        
                        // Get the latest instance and parse it. 
                        $repeat_instances = array_column($repeatable_event_instances, "redcap_repeat_instance");
                        $latest_instance = max($repeat_instances);
                        $key = array_search($latest_instance, $repeat_instances);
                        $data = $this->parseEventData($repeatable_event_instances[$key]);
                    }
                }
                else
                {
                    if (empty($this->redcap[$event]))
                    {   
                        $data = $this->parseEventData($event_data);
                    }
                    else
                    {
                        $data = array_merge($this->redcap[$event], $this->parseEventData($event_data));
                    }
                }

                if (!empty($data))
                {
                    if ($first_event == $event)
                    {
                        $this->redcap = array_merge($this->redcap, $data);
                    }
                    $this->redcap[$event] = $data;
                }
            }
        }
        else
        {
            foreach($json as $index => $event_data)
            {
                // Repeatable instrument
                if ($event_data["redcap_repeat_instance"] != "")
                {
                    // Repeatable instrument
                    if ($event_data["redcap_repeat_instrument"] != null && !in_array($event_data["redcap_repeat_instrument"], $repeatable_instruments_parsed)) 
                    {
                        // Get latest instance of repeatable instrument.
                        // Retrieve all repeatable instances of event. 
                        $repeatable_instrument_instances = array_filter($json, function($value) use($event_data){
                            return $value["redcap_repeat_instrument"] == $event_data["redcap_repeat_instrument"];
                        });
                        $repeatable_instrument_instances = array_values($repeatable_instrument_instances);

                        // Get the latest instance and parse it. 
                        $repeat_instances = array_column($repeatable_instrument_instances, "redcap_repeat_instance");
                        $latest_instance = max($repeat_instances);
                        $key = array_search($latest_instance, $repeat_instances);

                        // Merges repeatable instrument data with non-repeatable instrument data in same event.
                        $repeatable_instrument_data = $this->parseEventData($repeatable_instrument_instances[$key]);
                        foreach($repeatable_instrument_data as $field => $value)
                        {
                            if (empty($this->redcap[$field]))
                                $this->redcap[$field] = $value;
                        }

                        $repeatable_instruments_parsed[] = $event_data["redcap_repeat_instrument"];
                    }
                }
                else
                {
                    $this->redcap = $this->parseEventData($event_data);
                }
            }
        }

        try 
        {
            $this->smarty->assign("redcap", $this->redcap);
            $filled_template = $this->smarty->fetch($template_name);

            // Remove empty nodes
            $doc = new DOMDocument();
            $doc->loadHTML($filled_template);
            $body = $doc->getElementsByTagName("body")->item(0);
            
            $empty_elems = $this->getEmptyNodes($body);
            while (!empty($empty_elems))
            {
                foreach($empty_elems as $elem)
                {
                    $elem->parentNode->removeChild($elem);
                }
                $empty_elems = $this->getEmptyNodes($body);
            }
            $filled_template = $doc->saveHTML();
        }
        catch (Exception $e)
        {
            throw new Exception("Error on line " . $e->getLine() . ": " . $e->getMessage());
        }
        
        return $filled_template;
    }
}