<?php
include "include/config.inc.php";
$mysqli = new mysqli($server, $user, $pw, $db);

$mysqli->query("TRUNCATE TABLE ordini");
$mysqli->query("TRUNCATE TABLE bot");