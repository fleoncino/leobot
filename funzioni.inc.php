<?php

function scrivilog($s){
		$fp=fopen("gridbot.log","a+");
		$ora=date('d-m-Y H:i:s') ." ";
		fwrite($fp,"$ora $s\n");
		fclose($fp);
		echo "$ora $s\n";
}

function saldo($valuta){

	$url="https://api.therocktrading.com/v1/balances/$valuta";
	$headers=creaHeaders($url);
	$ch=curl_init();
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	$callResult=curl_exec($ch);
	curl_close($ch);
	$result=json_decode($callResult,true);
	return $result["trading_balance"];
}

function checkparam($a){
if (!is_integer (0+$a[1])) return "ngrid deve essere numero intero";
if ($a[1]<0) return "ngrid deve essere maggiore di zero";
//controllare anche che ngrid sia pari
if ($a[2]!=="ETHEUR" and $a[2]!=="BTCEUR") return "coppie accettate: BTCEUR, ETHEUR";
if (!is_numeric ($a[3])) return "quantita' deve essere numerico";
if (!is_numeric ($a[4])) return "mingap deve essere numerico";
if (!is_numeric ($a[5])) return "mingap deve essere numerico";

return false;

}

function leggibot($conn, $idbot){
	$q="SELECT `id`, `ngrid`, `coppia`, `qta`, `mingap`, `maxgap`, `attivo`  FROM `bot` WHERE id=?";
	scrivilog($q);
	$stmt = $conn->prepare($q);
	$stmt->bind_param("s", $idbot);
	$stmt->execute();
	$stmt->bind_result($id, $ngrid, $coppia, $qta, $mingap, $maxgap, $attivo);
	$stmt->fetch();
	$r=array($id, $ngrid, $coppia, $qta, $mingap, $maxgap, $attivo);
	return $r;
  }