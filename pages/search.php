<?php
/**
 * Search Plugin for MantisBT
 * Copyright (C) 2020  Starikov Anton - starikov_aa@mail.ru
 * https://github.com/starikov-aa/mantisbt-search
 */

layout_page_header( plugin_lang_get( 'title' ) );
layout_page_begin();

plugin_config_set( 'my_search_plugin', 'test' );

?>
    <div id="div_search_form">
        <form action="<?php echo plugin_page( "search" ); ?>" method="post">
            <?php echo form_security_field( 'plugin_Search_search_press' ) ?>
            <table style="width:40%;max-width:1000px;min-width:200px;margin:auto;">
                <tr>
                    <td id="text" style="padding:.25em;">
                        <input name="text" size="100" maxlength="300" value="<?=$_POST['text'] ?? ''?>"/>
                    </td>
                    <td style="padding:.25em;">
                        <input type="submit" name="search_submit" value="<?php echo plugin_lang_get( 'search_link' ); ?>" />
                    </td>
                </tr>
            </table>
        </form>
    </div>
<?php

if (!isset($_POST['search_submit']) || empty($_POST['text']))
    exit();

form_security_validate('plugin_Search_search_press');
$search_text = filter_var(gpc_get_string('text'), FILTER_SANITIZE_SPECIAL_CHARS);
$current_project = helper_get_current_project();
$project_children = project_hierarchy_get_all_subprojects($current_project);
$project_children[] = $current_project;

// List of projects to which the current user has access
$allow_project = join(",", user_get_all_accessible_projects());

$query = "SELECT mantis_bug_table.id AS bid,
       SUMMARY,
       description,
       note,
       mantis_bugnote_text_table.id AS noteid
FROM mantis_bug_table
JOIN mantis_bug_text_table ON mantis_bug_text_table.id = mantis_bug_table.id
LEFT JOIN mantis_bugnote_table ON mantis_bugnote_table.bug_id = mantis_bug_table.id
LEFT JOIN mantis_bugnote_text_table ON mantis_bugnote_text_table.id = mantis_bugnote_table.bugnote_text_id
WHERE ((summary LIKE '%" . $search_text . "%')
  OR (description LIKE '%" . $search_text . "%')
  OR (note LIKE '%" . $search_text . "%'))
  AND mantis_bug_table.project_id in (".join(",", $project_children).")
  AND mantis_bug_table.project_id in (" . $allow_project . ")
  ORDER BY bid DESC";


$num_sym_before = plugin_config_get('num_sym_before', 200);
$num_sym_after = plugin_config_get('num_sym_after', 200);

$query_result = db_query($query);

$to_table = [];

while ($row = db_fetch_array($query_result)) {
    $bid = $row['bid'];

    if (!isset($to_table[$bid]))
        $to_table[$bid] = [];

    $to_table[$bid]['summary'] = highlights_text($search_text, $row['summary']);
    $to_table[$bid]['description'] = highlights_text($search_text, $row['description']);

    $pos_text_in_note = strpos($row['note'], $search_text);
    if ($pos_text_in_note !== false) {
        $note = get_text_fragment($row['note'], $search_text, $num_sym_before, $num_sym_after);
        $to_table[$bid]['notes'][$row['noteid']] = highlights_text($search_text, $note);
    }
}

//echo '<pre>';
//print_r($to_table);

$html  = "<div id='div_search_result'>" . gen_result_table($to_table) . "</div>";

echo $html;
layout_page_end();


/**
 * Highlights the specified text
 *
 * @param $search_text string what to mark
 * @param $text string where to search
 * @return string
 */
function highlights_text($search_text, $text)
{
    return str_ireplace($search_text, "<span id='highlights_text'>" . $search_text . "</span>", $text);
}

/**
 * Returns the search text with the specified number of characters before and after it.
 *
 * @param $text string where to search
 * @param $search_text what to search
 * @param $num_sym_before integer the number of characters before the search phrase
 * @param $num_sym_after integer the number of characters after the search phrase
 * @return string
 */
function get_text_fragment($text, $search_text, $num_sym_before, $num_sym_after)
{
    $pos = strpos($text, $search_text);
    $s = ($pos - $num_sym_before) <= 0 ? 0 : $pos - $num_sym_before;
    $e = $pos + strlen($search_text) + $num_sym_after;
    return substr($text, $s, $e);
}

/**
 * Generate html link for bug or comment
 *
 * @param $bug_id integer bug id
 * @param bool|integer $comment_id id comment
 * @return string
 */
function gen_html_link($bug_id, $comment_id = false)
{
    $link = $comment_id ? $bug_id . "#c" . $comment_id : $bug_id;
    $text = $comment_id ? "#" . $comment_id : $bug_id;
    return "<a href='view.php?id=" . $link . "'>" . $text . "</a>";
}

/**
 * Creates an html table from an array with search results
 *
 * @param $search_result array example
 * [[bug_id] = ['summary' => 'text',
 *              'description' => 'text'
 *              'notes' => ['note_id' => 'note_text']]]
 * @return string
 */
function gen_result_table($search_result)
{
    $html = '';

    foreach ($search_result as $bug_id => $data) {
        if (is_array(@$data['notes'])) {
            $notes = $data['notes'];
            $note_num = count($notes);
            $row_num = '';

            if ($note_num > 1) {
                array_walk($notes, function (&$v, $k, $bug_id) {
                    $v = "<tr><td>" . gen_html_link($bug_id, $k) . "</td><td>" . $v . "</td></tr>";
                }, $bug_id);
                $notes = substr(join("", $notes), 4);
                $row_num = "rowspan='" . $note_num . "'";
            } else {
                $notes = "<td>" . gen_html_link($bug_id, key($notes)) . "</td><td>" . current($notes) . "</td></tr>";
            }
        } else {
            $notes = '<td></td><td></td></tr>';
            $row_num = '';
        }

        $html .= "<tr><td " . $row_num . ">" . gen_html_link($bug_id) . "</td>";
        $html .= "<td " . $row_num . ">" . $data['summary'] . "</td>";
        $html .= "<td " . $row_num . ">" . $data['description'] . "</td>";
        $html .= $notes;
    }

    $head = "<tr>
            <th id='col_id'>" . plugin_lang_get( 'result_tbl_id' ) . "</th>
            <th id='col_summary'>" . plugin_lang_get( 'result_tbl_summary' ) . "</th>
            <th id='col_description'>" . plugin_lang_get( 'result_tbl_description' ) . "</th>
            <th id='col_comment_id'>" . plugin_lang_get( 'result_tbl_comment_num' ) . "</th>
            <th id='col_comments'>" . plugin_lang_get( 'result_tbl_comments' ) . "</th>
        </tr>
    ";
    return '<table id="tbl_search_result" class="table table-bordered table-condensed" cellspacing="1">' . $head . $html . '</table>';
}


