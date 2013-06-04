<?php defined('SYSPATH') OR die('No direct access allowed.');

/**
 * Bandit Table Utilities
 *
 * @package  Bandit
 * @author   Clay McIlrath
 */
class Bandit_Table
{
    public $source;
    public $anchor;
    public $anchor_within;
    public $header_row;
    public $start_row;
    public $max_rows;
    public $start_col;
    public $max_cols;
    public $strip_tags;
    public $extra_cols;
    public $row_count;
    public $drop_rows;

    public $clean_html;
    public $raw_array;
    public $final_array;

    public static function factory( array $options = [] )
    {
        $instance = new Bandit_Table();

        if ( isset($options['source']) )
            $instance->source = $options['source'];
        else
            throw new Bandit_Exception('you must define a table source', 'moderate');

        $instance->anchor = isset($options['anchor']) ? $options['anchor'] : NULL;

        $instance->header_row = isset($options['header_row']) ? $options['header_row'] : FALSE;
        $instance->start_row = isset($options['start_row']) ? $options['start_row'] : 0;
        $instance->max_rows = isset($options['max_rows']) ? $options['max_rows'] : 0;
        $instance->start_col = isset($options['start_col']) ? $options['start_col'] : 0;
        $instance->max_cols = isset($options['max_cols']) ? $options['max_cols'] : 0;
        $instance->strip_tags = isset($options['strip_tags']) ? $options['strip_tags'] : TRUE;
        $instance->extra_cols = isset($options['extra_cols']) ? $options['extra_cols'] : [];
        $instance->row_count = isset($options['row_count']) ? $options['row_count'] : 0;
        $instance->drop_rows = isset($options['drop_rows']) ? $options['drop_rows'] : NULL;

        $instance->clean_html = isset($options['clean_html']) ? $options['clean_html'] : NULL;
        $instance->raw_array = isset($options['raw_array']) ? $options['raw_array'] : NULL;
        $instance->final_array = isset($options['final_array']) ? $options['final_array'] : NULL;

        return $instance;
    }

    public function extract()
    {
        $this->clean_html();
        $this->_prepare_array();

        return $this->_build_array();
    }


    function clean_html()
    {
        // find unique string that appears before the table you want to extract
        if ( is_int($this->anchor) )
        {
            $startSearch = $this->anchor;
        }
        else if ( $this->anchor_within )
        {
            $anchorPos = stripos($this->source, $this->anchor) + strlen($this->anchor);
            $sourceSnippet = strrev(substr($this->source, 0, $anchorPos));
            $tablePos = stripos($sourceSnippet, strrev(("<table"))) + 6;
            $startSearch = strlen($sourceSnippet) - $tablePos;
        }
        else
        {
            $startSearch = stripos($this->source, $this->anchor);
        }

        // extract table
        $startTable = stripos($this->source, '<table', $startSearch);
        $endTable = stripos($this->source, '</table>', $startTable) + 8;
        $table = substr($this->source, $startTable, $endTable - $startTable);

        if ( ! function_exists('lcase_tags') )
        {
            function lcase_tags($input) {
                return strtolower($input[0]);
            }
        }

        // lowercase all table related tags
        $table = preg_replace_callback('/<(\/?)(table|tr|th|td)/is', 'lcase_tags', $table);

        // remove all thead and tbody tags
        $table = preg_replace('/<\/?(thead|tbody).*?>/is', '', $table);

        // replace th tags with td tags
        $table = preg_replace('/<(\/?)th(.*?)>/is', '<$1td$2>', $table);

        // clean string
        $table = trim($table);
        $table = str_replace("\r\n", "", $table);

        // Specify configuration
        $config = [
            'clean' => TRUE,
            'indent' => TRUE,
            'drop-font-tags' => TRUE,
            'output-xhtml' => TRUE,
            'wrap' => 200
        ];

        // Tidy
        $tidy = new tidy;
        $tidy->parseString($table, $config, 'utf8');
        $tidy->cleanRepair();

        return $this->clean_html = $tidy;
    }

    private function _prepare_array() {

        // split table into individual elements
        $pattern = '/(<\/?(?:tr|td).*?>)/is';
        $table = preg_split($pattern, $this->clean_html, -1, PREG_SPLIT_DELIM_CAPTURE);

        // define array for new table
        $tableCleaned = array();

        // define variables for looping through table
        $row_count = 0;
        $colCount = 1;
        $trOpen = false;
        $tdOpen = false;

        // loop through table
        foreach($table as $item) {

            // trim item
            $item = str_replace(' ', '', $item);
            $item = trim($item);

            // save the item
            $itemUnedited = $item;

            // clean if tag
            $item = preg_replace('/<(\/?)(table|tr|td).*?>/is', '<$1$2>', $item);

            // pick item type
            switch ($item) {


                case '<tr>':
                    // start a new row
                    $row_count++;
                    $colCount = 1;
                    $trOpen = true;
                    break;

                case '<td>':
                    // save the td tag for later use
                    $tdTag = $itemUnedited;
                    $tdOpen = true;
                    break;

                case '</td>':
                    $tdOpen = false;
                    break;

                case '</tr>':
                    $trOpen = false;
                    break;

                default :

                    // if a TD tag is open
                    if($tdOpen) {

                        // check if td tag contained colspan
                        if(preg_match('/<td [^>]*colspan\s*=\s*(?:\'|")?\s*([0-9]+)[^>]*>/is', $tdTag, $matches))
                            $colspan = $matches[1];
                        else
                            $colspan = 1;

                        // check if td tag contained rowspan
                        if(preg_match('/<td [^>]*rowspan\s*=\s*(?:\'|")?\s*([0-9]+)[^>]*>/is', $tdTag, $matches))
                            $rowspan = $matches[1];
                        else
                            $rowspan = 0;

                        // loop over the colspans
                        for($c = 0; $c < $colspan; $c++) {

                            // if the item data has not already been defined by a rowspan loop, set it
                            if(!isset($tableCleaned[$row_count][$colCount]))
                                $tableCleaned[$row_count][$colCount] = $item;
                            else
                                $tableCleaned[$row_count][$colCount + 1] = $item;

                            // create new row_count variable for looping through rowspans
                            $futureRows = $row_count;

                            // loop through row spans
                            for($r = 1; $r < $rowspan; $r++) {
                                $futureRows++;
                                if($colspan > 1)
                                    $tableCleaned[$futureRows][$colCount + 1] = $item;
                                else
                                    $tableCleaned[$futureRows][$colCount] = $item;
                            }

                            // increase column count
                            $colCount++;

                        }

                        // sort the row array by the column keys (as inserting rowspans screws up the order)
                        ksort($tableCleaned[$row_count]);
                    }
                    break;
            }
        }
        // set row count
        if($this->header_row)
            $this->row_count    = count($tableCleaned) - 1;
        else
            $this->row_count    = count($tableCleaned);

        $this->raw_array = $tableCleaned;

    }

    private function _build_array() {

        // define array to store table data
        $tableData = array();

        // get column headers
        if($this->header_row) {

            // trim string
            $row = $this->raw_array[$this->header_row];

            // set column names array
            $columnNames = array();
            $uniqueNames = array();

            // loop over column names
            $colCount = 0;
            foreach($row as $cell) {

                $colCount++;

                $cell = strip_tags($cell);
                $cell = trim($cell);

                // save name if there is one, otherwise save index
                if($cell) {

                    if(isset($uniqueNames[$cell])) {
                        $uniqueNames[$cell]++;
                        $cell .= ' ('.($uniqueNames[$cell] + 1).')';
                    }
                    else {
                        $uniqueNames[$cell] = 0;
                    }

                    $columnNames[$colCount] = $cell;

                }
                else
                    $columnNames[$colCount] = $colCount;

            }

            // remove the headers row from the table
            unset($this->raw_array[$this->header_row]);

        }

        // remove rows to drop
        foreach(explode(',', $this->drop_rows) as $key => $value) {
            unset($this->raw_array[$value]);
        }

        // set the end row
        if($this->max_rows)
            $endRow = $this->start_row + $this->max_rows - 1;
        else
            $endRow = count($this->raw_array);

        // loop over row array
        $row_count = 0;
        $newrow_count = 0;
        foreach($this->raw_array as $row) {

            $row_count++;

            // if the row was requested then add it
            if($row_count >= $this->start_row && $row_count <= $endRow) {

                $newrow_count++;

                // create new array to store data
                $tableData[$newrow_count] = array();

                //$tableData[$newrow_count]['origRow'] = $row_count;
                //$tableData[$newrow_count]['data'] = array();
                $tableData[$newrow_count] = array();

                // set the end column
                if($this->max_cols)
                    $endCol = $this->start_col + $this->max_cols - 1;
                else
                    $endCol = count($row);

                // loop over cell array
                $colCount = 0;
                $newColCount = 0;
                foreach($row as $cell) {

                    $colCount++;

                    // if the column was requested then add it
                    if($colCount >= $this->start_col && $colCount <= $endCol) {

                        $newColCount++;

                        if($this->extra_cols) {
                            foreach($this->extra_cols as $extraColumn) {
                                if($extraColumn['column'] == $colCount) {
                                    if(preg_match($extraColumn['regex'], $cell, $matches)) {
                                        if(is_array($extraColumn['names'])) {
                                            $this->extra_colsCount = 0;
                                            foreach($extraColumn['names'] as $extraColumnSub) {
                                                $this->extra_colsCount++;
                                                $tableData[$newrow_count][$extraColumnSub] = $matches[$this->extra_colsCount];
                                            }
                                        } else {
                                            $tableData[$newrow_count][$extraColumn['names']] = $matches[1];
                                        }
                                    } else {
                                        $this->extra_colsCount = 0;
                                        if(is_array($extraColumn['names'])) {
                                            $this->extra_colsCount = 0;
                                            foreach($extraColumn['names'] as $extraColumnSub) {
                                                $this->extra_colsCount++;
                                                $tableData[$newrow_count][$extraColumnSub] = '';
                                            }
                                        } else {
                                            $tableData[$newrow_count][$extraColumn['names']] = '';
                                        }
                                    }
                                }
                            }
                        }

                        if($this->strip_tags)
                            $cell = strip_tags($cell);

                        // set the column key as the column number
                        $colKey = $newColCount;

                        // if there is a table header, use the column name as the key
                        if($this->header_row)
                            if(isset($columnNames[$colCount]))
                                $colKey = $columnNames[$colCount];

                        // add the data to the array
                        //$tableData[$newrow_count]['data'][$colKey] = $cell;
                        $tableData[$newrow_count][$colKey] = $cell;
                    }
                }
            }
        }

        $this->final_array = $tableData;
        return $tableData;
    }
}
?>