<?php
include '../init.php';
require_once '../classification.php';
require_once '../svg.php';

$raw_data_id = "";

if (!is_logged_in()) {
    header("Location: ../login");
}

function insert_userdrawing($user_id, $data) {
    global $pdo, $msg;

    $linelist = json_decode($data);
    $pointlist = array();
    foreach ($linelist as $line) {
        foreach ($line as $p) {
            $pointlist[] = array("x"=>$p->x, "y"=>$p->y);
        }
    }

    if (count($pointlist) == 0) {
        $msg[] = array("class" => "alert-danger",
                       "text" => "This could not be inserted. It didn't even ".
                                 "have a single point (ERR 2). You sent:<br/>".
                                 "<pre>".$data."</pre>");
        return false;
    } else {
        if (strpos($data, "[]") === false) {
            $sql = "INSERT INTO `wm_raw_draw_data` (".
                   "`user_id`, ".
                   "`data`, ".
                   "`creation_date`, ".
                   "`user_agent`, ".
                   "`accepted_formula_id`".
                   ") VALUES (:user_id, :data, CURRENT_TIMESTAMP, :user_agent, NULL);";
            $stmt = $pdo->prepare($sql);
            $uid = get_uid();
            $stmt->bindParam(':user_id', $uid, PDO::PARAM_INT);
            $stmt->bindParam(':data', $data, PDO::PARAM_STR);
            $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT'], PDO::PARAM_STR);
            $stmt->execute();
            $raw_data_id = $pdo->lastInsertId();

            create_raw_data_svg($raw_data_id, $data);

            return $raw_data_id;
        } else {
        $msg[] = array("class" => "alert-danger",
                       "text" => "This could not be inserted. It didn't even ".
                                 "have a single point. You sent:<br/>".
                                 "<pre>".$data."</pre> ".
                                 "At the moment I have problems with single ".
                                 "points. This might be a symptom of those ".
                                 "problems. See <a href=\"https://github.com/MartinThoma/write-math/issues/6\">issue 6</a>.");
        return false;
        }
    }
}

function classify() {
    global $msg, $pdo;

    $raw_data_id = insert_userdrawing(get_uid(), $_POST['drawnJSON']);
    if ($raw_data_id == false) {
        return;
    }
    # Get a list of all workers
    $sql = "SELECT `id`, `worker_name`, `url` ".
           "FROM `wm_workers` WHERE `latest_heartbeat` IS NOT NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $workers = $stmt->fetchAll();

    # TODO: Make this asynchronous
    # Send classification request to all workers
    foreach ($workers as $worker) {
        $request_url = $worker['url'];
        // contact worker
        //set POST variables
        $url = $request_url;
        $fields = array(
                    'classify' => urlencode($_POST['drawnJSON'])
                );

        //url-ify the data for the POST
        foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
        rtrim($fields_string, '&');

        //open connection
        $ch = curl_init();

        //set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_POST, count($fields));
        curl_setopt($ch,CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        //execute post
        $answer = curl_exec($ch);

        //close connection
        curl_close($ch);
        // end contact worker

        $answer_json = json_decode($answer, true);

        if (!(json_last_error() == JSON_ERROR_NONE)) {
            $msg[] = array("class" => "alert-warning",
               "text" => "Worker '".$worker['worker_name']."' returned '".
                         json_last_error_msg()."'<br/>".
                         "Request URL: <a href=\"$request_url\">Link</a><br/>".
                         "Answer: ".htmlentities(substr($answer, 0, 20))).
                         "...";
             # TODO: The user should not see this. This should be logged, though.
        } else {
            foreach ($answer_json as $key => $object) {
                $formula_id = array_keys($object)[0];
                $probability = $object[$formula_id];
                $sql = "INSERT INTO `wm_worker_answers` (".
                        "`worker_id` , ".
                        "`raw_data_id` , ".
                        "`formula_id` , ".
                        "`probability` ".
                        ") VALUES (:wid, :raw_data_id, :formula_id, :probability)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':wid', $worker['id'], PDO::PARAM_INT);
                $stmt->bindParam(':raw_data_id', $raw_data_id, PDO::PARAM_INT);
                $stmt->bindParam(':formula_id', $formula_id, PDO::PARAM_INT);
                $stmt->bindParam(':probability', $probability, PDO::PARAM_STR);
                try {
                  $stmt->execute();
                } catch (Exception $e) {
                  var_dump($e);
                }
                header("Location: ../view/?raw_data_id=".$raw_data_id);
            }
        }
    }
}

$formula_ids = array();

if (isset($_POST['drawnJSON'])) {
    classify();
}

echo $twig->render('classify.twig', array('heading' => 'Classify',
                                       'file'=> "classify",
                                       'logged_in' => is_logged_in(),
                                       'display_name' => $_SESSION['display_name'],
                                       'formula_ids' => $formula_ids,
                                       'raw_data_id' => $raw_data_id,
                                       'msg' => $msg
                                       )
                  );

?>