<?php
include '../init.php';

$sql = "SELECT `wm_users`.`id`, `display_name`, ".
       "COUNT(`wm_users`.`id`) as `written_formulas`, ".
       "COUNT(DISTINCT `accepted_formula_id`) as `distinct_symbols` ".
       "FROM `wm_raw_draw_data` ".
       "RIGHT JOIN `wm_users` ON `wm_users`.`id` = `user_id` ".
       "GROUP BY `user_id` ".
       "ORDER BY `written_formulas` DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$users_by_raw_data = $stmt->fetchAll();

echo $twig->render('ranking.twig', array('heading' => 'Ranking',
                                       'file'=> "ranking",
                                       'logged_in' => is_logged_in(),
                                       'display_name' => $_SESSION['display_name'],
                                       'msg' => $msg,
                                       'users_by_raw_data' => $users_by_raw_data
                                       )
                  );