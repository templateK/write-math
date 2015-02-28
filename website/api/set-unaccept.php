<?php
include '../init.php';

if (!is_logged_in()) {
    header("Location: ../login");
}

if (isset($_POST['raw_data_id'])) {
    $raw_data_id = intval($_POST['raw_data_id']);
    $symbol_id = intval($_POST['symbol_id']);

    # Insert dataset
    $sql = "UPDATE `wm_raw_draw_data` ".
           "SET `accepted_formula_id` = NULL ".
           "WHERE `id` = :raw_data_id AND `user_id` = :uid AND `accepted_formula_id` = :formula_id;";
    $stmt = $pdo->prepare($sql);
    $uid = get_uid();
    $stmt->bindParam(':raw_data_id', $raw_data_id, PDO::PARAM_INT);
    $stmt->bindParam(':formula_id', $symbol_id, PDO::PARAM_INT);
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $result = $stmt->execute();
    echo $result." ";
}

?>