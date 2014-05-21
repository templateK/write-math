<?php
require_once '../svg.php';
include '../init.php';

if (!is_logged_in()) {
    header("Location: ../login");
}

$edit_id = 0;

function validate_display_name($name) {
    return preg_match('/^[A-Za-z]{1}[A-Za-z0-9_ ]{1,}[A-Za-z0-9]{1}$/',$name);
}

$sql = "SELECT  `language_code` ,  `english_language_name` 
FROM  `wm_languages` 
ORDER BY  `english_language_name` ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$languages =$stmt->fetchAll();

if (isset($_POST['language'])) {
    $lang = $_POST['language'];
    $handedness = $_POST['handedness'];

    if (validate_display_name($_POST['display_name'])) {
        $sql = "UPDATE `wm_users` SET ".
               "`display_name` = :display_name ".
               "WHERE `id` = :uid;";
        $stmt = $pdo->prepare($sql);
        $uid = get_uid();
        $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindParam(':display_name', $_POST['display_name'], PDO::PARAM_STR);
        $stmt->execute();
    }

    if (isset($_POST['password']) && $_POST['password'] != "") {
        if (strlen($_POST['password']) < 6) {
            $msg[] = array("class" => "alert-danger",
                           "text" => "Your password has to have 6 characters.");
        } elseif ($_POST['password'] != $_POST['passwordconf']) {
            $msg[] = array("class" => "alert-danger",
                           "text" => "Your passwords did not match.");
        } else {
            $sql = "UPDATE `wm_users` SET `password` = :password ".
                   "WHERE `id` = :uid;";
            $stmt = $pdo->prepare($sql);
            $uid = get_uid();
            $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
            $hash = password_hash($_POST['password'], PASSWORD_BCRYPT, array("cost" => 10));
            $stmt->bindParam(':password', $hash, PDO::PARAM_STR);
            $stmt->execute();
            header("Location: ../login");
        }
    }

    if ($lang == 'NULL') {
        $sql = "UPDATE `wm_users` SET `language` =  NULL WHERE `id` = :uid;";
        $stmt = $pdo->prepare($sql);
        $uid = get_uid();
        $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $sql = "UPDATE `wm_users` SET `language` =  :lang WHERE `id` = :uid;";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':lang', $lang, PDO::PARAM_STR);
        $uid = get_uid();
        $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
    }

    if ($handedness == 'NULL') {
        $sql = "UPDATE `wm_users` SET `handedness` =  NULL WHERE `id` = :uid;";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':uid', get_uid(), PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $sql = "UPDATE `wm_users` SET `handedness` =  :hand WHERE `id` = :uid;";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':hand', $handedness, PDO::PARAM_STR);
        $uid = get_uid();
        $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
        $stmt->execute();
    }
}

if (isset($_POST['worker_id'])) {
    $sql = "UPDATE `wm_workers` SET ".
           "`worker_name` = :name, ".
           "`description` = :description, ".
           "`url` = :url ".
           "WHERE `id` = :id AND user_id = :uid;";
    $stmt = $pdo->prepare($sql);
    $uid = get_uid();
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $stmt->bindParam(':id', $_POST['worker_id'], PDO::PARAM_INT);
    $stmt->bindParam(':name', trim($_POST['worker_name']), PDO::PARAM_STR);
    $stmt->bindParam(':description', trim($_POST['description']), PDO::PARAM_STR);
    $stmt->bindParam(':url', trim($_POST['url']), PDO::PARAM_STR);
    if ($stmt->execute()) {
        $msg[] = array("class" => "alert-success",
                       "text" => "Your client was successfully edited.");
    } else {
        $msg[] = array("class" => "alert-danger",
                       "text" => "Your client could not be edited.");
    }
} elseif (isset($_POST['worker_name'])) {
    # Insert a new worker
    $sql = "INSERT INTO `wm_workers` ( ".
           "`user_id`, ".
           "`API_key`, ".
           "`worker_name`, ".
           "`description`, ".
           "`url` ".
           ") VALUES (:uid, :api_key, :worker_name, :description, :url);";
    $stmt = $pdo->prepare($sql);
    $uid = get_uid();
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $stmt->bindParam(':api_key', uniqid(), PDO::PARAM_STR);
    $stmt->bindParam(':worker_name', $_POST['worker_name'], PDO::PARAM_STR);
    $stmt->bindParam(':description', $_POST['description'], PDO::PARAM_STR);
    $stmt->bindParam(':url', $_POST['url'], PDO::PARAM_STR);
    if ($stmt->execute()) {
        $msg[] = array("class" => "alert-success",
                       "text" => "Your client was successfully inserted.");
    } else {
        $msg[] = array("class" => "alert-danger",
                       "text" => "Your client could not be inserted. ".
                                 "Probably the name was already taken?");
    }
} elseif (isset($_GET['request_heartbeat'])) {
    # Make a heartbeat
    $sql = "SELECT `url` FROM `wm_workers` ".
           "WHERE `user_id` = :uid AND `id` = :id";
    $stmt = $pdo->prepare($sql);
    $uid = get_uid();
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $stmt->bindParam(':id', $_GET['request_heartbeat'], PDO::PARAM_INT);
    $stmt->execute();
    $worker = $stmt->fetchObject();

    # Make the heartbeat
    $random_msg = uniqid();
    $answer = file_get_contents(($worker->url) . "?heartbeat=" . $random_msg,
                                false,
                                NULL,
                                -1,
                                strlen($random_msg));
    if ($answer != $random_msg) {
        $msg[] = array("class" => "alert-warning",
                       "text" => "The server didn't answer correct. ".
                                 "It's answer was '".htmlentities($answer)."' and should ".
                                 "have been '$random_msg'.<br/>".
                                 "The response headers were:<br/><pre>".
                                 implode("<br/>", $http_response_header).
                                 "</pre>");
    } else {
        $sql = "UPDATE `wm_workers` SET ".
               "`latest_heartbeat` = CURRENT_TIMESTAMP ".
               "WHERE  `wm_workers`.`id` = :id AND `user_id` =:uid;";
        $stmt = $pdo->prepare($sql);
        $uid = get_uid();
        $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
        $stmt->bindParam(':id', $_GET['request_heartbeat'], PDO::PARAM_INT);
        $stmt->execute();
        $msg[] = array("class" => "alert-success",
                       "text" => "The heartbeat was successful." );
    }
} elseif (isset($_GET['edit'])) {
    $sql = "SELECT `id`, `worker_name`, `url`, `description` FROM `wm_workers` ".
           "WHERE `user_id` = :uid AND `id` = :id";
    $stmt = $pdo->prepare($sql);
    $uid = get_uid();
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $stmt->bindParam(':id', $_GET['edit'], PDO::PARAM_INT);
    $stmt->execute();
    $worker = $stmt->fetchObject();
    $edit_id = $worker->id;
} elseif (isset($_GET['delete'])) {
    $sql = "DELETE FROM `wm_workers` WHERE `id` = :wid AND `user_id` = :uid";
    $stmt = $pdo->prepare($sql);
    $uid = get_uid();
    $stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
    $stmt->bindParam(':wid', $_GET['delete'], PDO::PARAM_INT);
    $stmt->execute();
}

// Get all workers of this user
$sql = "SELECT `id`, `API_key`, `worker_name`, `description`, `url`, ".
       "`latest_heartbeat` ".
       "FROM `wm_workers` WHERE `user_id` = :uid";
$stmt = $pdo->prepare($sql);
$uid = get_uid();
$stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
$stmt->execute();
$userworkers = $stmt->fetchAll();

// Get total number of elements for pagination
$sql = "SELECT COUNT(`id`) as counter FROM `wm_raw_draw_data` ".
       "WHERE `user_id` = :uid";
$stmt = $pdo->prepare($sql);
$uid = get_uid();
$stmt->bindParam(':uid', $uid, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetchObject();
$total = $row->counter;

// Get all raw data of this user
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
$sql = "SELECT `id`, `data` as `image`, `creation_date` ".
       "FROM `wm_raw_draw_data` ".
       "WHERE `user_id` = :uid ".
       "ORDER BY `creation_date` DESC ".
       "LIMIT ".(($currentPage-1)*14).", 14";
$stmt = $pdo->prepare($sql);
$uid = get_uid();
$stmt->bindParam(':uid', $uid, PDO::PARAM_STR);
$stmt->execute();
$userimages = $stmt->fetchAll();

echo $twig->render('profile.twig', array('heading' => 'Profile',
                                       'file' => "profile",
                                       'logged_in' => is_logged_in(),
                                       'display_name' => $_SESSION['display_name'],
                                       'user_id' => get_uid(),
                                       'email' => get_email(),
                                       'msg' => $msg,
                                       'gravatar' => "http://www.gravatar.com/avatar/".md5(get_email()),
                                       'language' => get_language(),
                                       'handedness' => get_handedness(),
                                       'languages' => $languages,
                                       'userimages' => $userimages,
                                       'total' => $total,
                                       'pages' => floor(($total)/14),
                                       'currentPage' => $currentPage,
                                       'userworkers' => $userworkers,
                                       'worker' => $worker,
                                       'edit_id' => $edit_id
                                       )
                  );

?>