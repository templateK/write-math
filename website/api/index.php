<?php
include '../init.php';

$tasks = array("list-unclassified", "view-raw-data", "view-formula", "delete", "classify");

function invalid_latex_request($latex) {
    global $pdo;

    $sql = "SELECT `id` FROM `wm_invalid_formula_requests` ".
           "WHERE `latex` = :latex";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':latex', $latex, PDO::PARAM_STR);
    $stmt->execute();
    $row = $stmt->fetchObject();

    if ($row === false) {
        // Nobody made that request so far.
        $sql = "INSERT INTO `wm_invalid_formula_requests` ".
               "(`latex`) VALUES (:latex);";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':latex', $latex, PDO::PARAM_STR);
        $stmt->execute();
        echo "This request was invalid. It's recorded and the symbol ".
             "will eventually be added after manual review.";
    } else {
        // The request is already there. Update it.
        $sql = "UPDATE `wm_invalid_formula_requests` ".
               "SET  `last_request_time` = CURRENT_TIMESTAMP( ), ".
               "`requests` =  `requests` + 1 ".
               "WHERE `id` = :id;";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $row->id, PDO::PARAM_INT);
        $stmt->execute();
        echo "This request was invalid. It's already on my manual ".
             "review queue and will eventually be added.";
    }
}

function add_classification($formula_id, $raw_data_id) {
    global $pdo;

    $sql = "INSERT INTO `wm_raw_data2formula` (".
           "`raw_data_id`, ".
           "`formula_id` , ".
           "`user_id` ".
           ") VALUES (".
           ":raw_data_id, :formula_id, :uid);";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':uid', get_uid(), PDO::PARAM_INT);
    $stmt->bindParam(':raw_data_id', $raw_data_id, PDO::PARAM_INT);
    $stmt->bindParam(':formula_id', $formula_id, PDO::PARAM_INT);

    // TODO: Catch duplicate classification as upvote
    try {
        $result = $stmt->execute();
    } catch (Exception $e) {
        var_dump($e);
    }
    if ($result) {
        echo "Insert was successful.";
    } else {
        echo "Insert failed.";
    }
}

if (!isset($_GET['task']) || !in_array($_GET['task'], $tasks)) {
    echo "You have to provide a valid task. Valid tasks are: ";
    echo "<ul>";
    foreach ($tasks as $task) {
        echo "<li><a href=\"?task=$task\">$task</a></li>";
    }
    echo "</ul>";
    exit (-1);
}

if ($_GET['task'] == "list-unclassified") {
    $sql = "SELECT `id` FROM `wm_raw_draw_data` WHERE `accepted_formula_id` IS NULL";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $unclassified = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($unclassified);
} elseif ($_GET['task'] == "view-raw-data") {
    if (!isset($_GET['id'])) {
        echo "You have to specify an id. Some of the valid ids are:";
        $sql = "SELECT `id` FROM `wm_raw_draw_data` LIMIT 0, 7";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $url = "?task=".$_GET['task']."&id=$id";
            echo "<li><a href=\"$url\">$url</a></li>";
        }
        exit(-1);
    }

    $formats = array("svg", "path", "pointlist");
    if (!isset($_GET['format']) || !in_array($_GET['format'], $formats)) {
        echo "<p>You have to note which format you want to see.</p>";
        echo "<p>Valid formats are:</p>";
        echo "<ul>";
        foreach ($formats as $format) {
            $url = "?task=view-raw-data&id=".$_GET['id']."&format=$format";
            echo "<li><a href=\"$url\">$format</a></li>";
        }
        echo "</ul>";
        exit (-1);
    }

    if ($_GET['format'] == "pointlist") {
        $sql = "SELECT `data` FROM `wm_raw_draw_data` WHERE `id` = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetchObject();
        echo $row->data;
    } else {
        echo "Format not implemented yet.";
    }
} elseif ($_GET['task'] == "view-formula") {
    if (!isset($_GET['id']) && !isset($_GET['latex'])) {
        echo "You have to specify an id. Some of the valid ids are:";
        $sql = "SELECT `id` FROM `wm_formula` ORDER BY `id` DESC LIMIT 0, 7";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<ul>";
        foreach ($ids as $id) {
            $url = "?task=".$_GET['task']."&id=$id";
            echo "<li><a href=\"$url\">$url</a></li>";
        }
        echo "</ul>";
        echo "<p>Alternatively, you can enter latex code directly:</p>";
        echo "<ul>";
        foreach (array("$\subseteq$", "A", "$\Omega$") as $url) {
            $url = "?task=view-formula&latex=$url";
            echo "<li><a href=\"$url\">$url</a></li>";
        }
        echo "</ul>";
        exit(-1);
    }

    if (isset($_GET['id'])) {
        $sql = "SELECT `svg` FROM `wm_formula` WHERE `id` = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
        $stmt->execute();
        $svg = $stmt->fetchObject()->svg;
        header('Content-type: image/svg+xml');
        echo $svg;
    } elseif (isset($_GET['latex'])) {
        $sql = "SELECT `svg` FROM `wm_formula` WHERE `formula_in_latex` = :latex";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':latex', $_GET['latex'], PDO::PARAM_STR);
        $stmt->execute();
        $svg = $stmt->fetchObject();
        if ($svg === false) {
            // This request was invalid. Probably a candidate to add?
            invalid_latex_request($_GET['latex']);
        } else {
            header('Content-type: image/svg+xml');
            echo $svg->svg;
        }
    } else {
        echo "Nothing here.";
    }
} elseif ($_GET['task'] == "classify") {
    if (!is_logged_in()) {
        echo "You have to be logged in for this step!";
        exit(-1);
    }


    if (!isset($_GET['raw_data_id'])) {
        echo "You have to specify an raw_data_id. Some of the valid ids are:";
        $sql = "SELECT `id` FROM `wm_raw_draw_data` LIMIT 0, 7";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($ids as $id) {
            $url = "?task=".$_GET['task']."&raw_data_id=$id";
            echo "<li><a href=\"$url\">$url</a></li>";
        }
        exit(-1);
    }

    if (!isset($_GET['latex']) && !isset($_GET['formula_id'])) {
        echo "You have to specify either a 'formula_id' or directly 'latex'.";
        exit(-1);
    }

    if (isset($_GET['formula_id'])) {
        add_classification($_GET['formula_id'], $_GET['raw_data_id']);
    } elseif (isset($_GET['latex'])) {
        $sql = "SELECT `id` FROM `wm_formula` ".
               "WHERE `latex` = :latex";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':latex', $_GET['latex'], PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetchObject();

        if ($row === false) {
            invalid_latex_request($_GET['latex']);
            exit(-1);
        } else {
            add_classification($row->id, $_GET['raw_data_id']);
        }
    }
} else {
    echo "Task is not implemented yet.";
}

?>