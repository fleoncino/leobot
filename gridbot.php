<?php
include "include/config.inc.php";
include "/etc/gridbot/headers.inc.php";
include "/etc/gridbot/telegram.inc.php";
include "bidask.class.php";
include "ordine.class.php";
include "bot.class.php";
include "funzioni.inc.php";


//main
if ($argc< 7) {
  die ("uso: ". $argv[0] ." ngrid coppia qta mingap maxgap identificativo\n");
}

if (($rit=checkparam($argv)) !== false) {
  die ("$rit\n"); 
}

$conn = new mysqli($server, $user, $pw, $db);
if ($conn->connect_error) {
  die("Impossibile connettersi a database\n");
}
scrivilogbot($argv[6],$argv[0]." Inizio");
$conndb=array("server"=>$server,"user"=>$user,"pw"=>$pw,"db"=>$db);
try{
	$b = new bot($argv[6], $conndb, $argv[1], $argv[2], $argv[3], $argv[4], $argv[5]);
	$b->pianifica();
	$b->vai();
}catch (Exception $e) {
    echo "Errore:\n",  $e->getMessage(), "\n";

}

