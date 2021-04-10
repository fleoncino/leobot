<?php
include "include/config.inc.php";
include "/etc/gridbot/headers.inc.php";
include "/etc/gridbot/telegram.inc.php";
include "bidask.class.php";
include "ordine.class.php";
include "bot.class.php";
include "funzioni.inc.php";


//main
if ($argc<> 2) {
  die ("uso: ". $argv[0] ." identificativo\n");
}

$conn = new mysqli($server, $user, $pw, $db);
if ($conn->connect_error) {
  die("Impossibile connettersi a database\n");
}
$conndb=array("server"=>$server,"user"=>$user,"pw"=>$pw,"db"=>$db);

scrivilogbot($argv[1],$argv[0]. " Inizio");
try{
  $a=leggibot($conn, $argv[1]);
  $b = new bot($argv[1], $conndb, $a[1], $a[2], $a[3], $a[4], $a[5]);
	$b->restoreord();
 	$b->vai();
}catch (Exception $e) {
    echo "Errore:\n",  $e->getMessage(), "\n";

}

