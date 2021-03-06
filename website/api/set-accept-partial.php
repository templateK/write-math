<?php
include '../init.php';
require_once 'api.functions.php';
require_once '../view/submit_answer.php';

if (!is_logged_in()) {
    header("Location: ../login");
}

// Parameters
// -----------
// raw_data_id : int
//     Identifier of the recording
// answer : identifier of the partial answer
//
// Returns
// -------
// boolean :
//     true if it was successful, otherwise false
function accept_partial_answer($raw_data_id, $answer_id) {
    global $pdo;
    global $msg;

    $user_id = get_uid();

    // Check if this is either an admin or the creator of the recording
    $sql = "SELECT `user_id` ".
           "FROM `wm_raw_draw_data` ".
           "WHERE `id` = :recording_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':recording_id', $raw_data_id, PDO::PARAM_INT);
    $stmt->execute();
    $obj = $stmt->fetchObject();
    if ($obj->user_id != $user_id && $user_id != 10) {  // TODO: Admin group check
        return '{"error": "You may not accept an answer."}';
    }

    $total_strokes = get_stroke_count($raw_data_id);
    $strokes = implode(',', range(0, $total_strokes-1));

    // Check if this answer conflicts with other partial answers
    $sql = "SELECT `wm_partial_answer`.`id`, `formula_name`, `strokes`, ".
           "`symbol_id` ".
           "FROM `wm_partial_answer` ".
           "JOIN `wm_formula` ON (`symbol_id` = `wm_formula`.`id`) ".
           "WHERE ".
           "(`is_accepted` = 1 OR `wm_partial_answer`.`id` = :answer_id) ".
           "AND `recording_id` = :recording_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':answer_id', $answer_id, PDO::PARAM_INT);
    $stmt->bindParam(':recording_id', $raw_data_id, PDO::PARAM_INT);
    $stmt->execute();
    $partial_answers = $stmt->fetchAll();

    // Check if there are colliding previous answers
    $new_answer = null;
    $aa_strokes = array();
    $aa_symbols = array();
    foreach ($partial_answers as $answer) {
        $answer['strokes'] = explode(',', $answer['strokes']);
        $aa_symbols[] = array('name' => $answer['formula_name'],
                              'id' => $answer['symbol_id']);
        if ($answer['id'] == $answer_id) {
            $new_answer = $answer['strokes'];
        } else {
            $aa_strokes = array_merge($aa_strokes, $answer['strokes']);
            $aa_strokes = array_unique($aa_strokes);
        }
    }
    foreach ($new_answer as $stroke_nr) {
        if (in_array($stroke_nr, $aa_strokes)) {
            // There is an collision
            return '{"error": "You cannot accept this answer, as you '.
                   'have accepted another answer which '.
                   'classifies stroke '.$stroke_nr.', too."}';
        }
    }

    $sql = "UPDATE `wm_partial_answer` ".
           "SET `is_accepted` = 1 ".
           "WHERE `id` = :answer_id ".
           "AND `recording_id` = :recording_id ".
           "AND (`user_id` = :user_id OR :user_id = 10) ";  # TODO: Change to admin-group check
           "LIMIT 1;";
    $stmt = $pdo->prepare($sql);
    $uid = get_uid();
    $stmt->bindParam(':user_id', $uid, PDO::PARAM_INT);
    $stmt->bindParam(':answer_id', $answer_id, PDO::PARAM_INT);
    $stmt->bindParam(':recording_id', $raw_data_id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() != 1) {
        return '{"error": "'.$stmt->rowCount().'.'.$uid.'.'.$answer_id.'.'.$raw_data_id.': You could not accept that answer. '.
               "This happens when you try to accept ".
               "a classification of a formula you ".
               "did not write. ".
               "Or multiple form submission: $sql.\"}";
    }
    // Check if this answer classified the whole recording. If that is the case
    // then write the answer in wm_raw_draw_data.accepted_formula_id

    if (count($aa_strokes) + count($new_answer) == $total_strokes) {
        // All strokes were classified and accepted as some symbol
        // Check if there is more then one accepted formula / symbol
        // (excluding WILDPOINT)
        // If there are more, then we are still missing the geometry
        // information
        $other = 0;
        $last_formula_id = 0;
        if (count($aa_strokes) + count($new_answer) == 0) {
            $other = 1;
            $last_formula_id = $answer_id;
        } else {
            foreach ($aa_symbols as $symbol) {
                if ($symbol['name'] != 'WILDPOINT' && $symbol['name'] != 'TRASH') {
                    $other += 1;
                    $last_formula_id = $symbol['id'];
                }
            }
        }

        if ($other == 1) {
            // accept
            $sql = "UPDATE `wm_raw_draw_data` ".
                   "SET `accepted_formula_id` = :fid ".
                   "WHERE `id` = :raw_data_id ".
                   "LIMIT 1;";  # TODO: Change to admin-group check
            $stmt = $pdo->prepare($sql);
            $uid = get_uid();
            $stmt->bindParam(':raw_data_id', $raw_data_id, PDO::PARAM_INT);
            $stmt->bindParam(':fid', $last_formula_id, PDO::PARAM_INT);
            $stmt->execute();
            return true;
        }
    }
    return true;
}

if (isset($_POST['raw_data_id'])) {
    $raw_data_id = intval($_POST['raw_data_id']);
    if(isset($_POST['answer_id'])) {
        $answer_id = intval($_POST['answer_id']);
    } elseif (isset($_POST['symbol_id'])) {
        $symbol_id = intval($_POST['symbol_id']);
        $strokes = intval($_POST['strokes']);
        $answer_id = get_answer_id($raw_data_id, $symbol_id, $strokes);
    } else {
        echo '{"error": "neither \'symbol_id\' nor \'answer_id\' was set."}';
    }
    echo accept_partial_answer($raw_data_id, $answer_id);
} else {
    echo json_encode('{"error": "Not POSTed raw_data_id"}');
}
?>