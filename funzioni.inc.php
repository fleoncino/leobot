<?php
function connessione($conndb){
	$conn = new mysqli($conndb["server"], $conndb["user"], $conndb["pw"], $conndb["db"]);
	return $conn;
}

function scrivilogbot($idbot, $s){
		$fp=fopen("$idbot.log","a+");
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
	scrivilogbot($idbot,$q);
	$stmt = $conn->prepare($q);
	$stmt->bind_param("s", $idbot);
	$stmt->execute();
	$stmt->bind_result($id, $ngrid, $coppia, $qta, $mingap, $maxgap, $attivo);
	$stmt->fetch();
	$r=array($id, $ngrid, $coppia, $qta, $mingap, $maxgap, $attivo);
	return $r;
  }

function elencoidordini($coppia){
	//restituisce l'elenco degli id ordine in una stringa usabile per una clausola IN SQL
	$url="https://api.therocktrading.com/v1/funds/".$coppia."/orders";
	$headers=creaHeaders($url);
	$ch=curl_init();
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_CUSTOMREQUEST,"GET");
	curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	$callResult=curl_exec($ch);
	$httpCode=curl_getinfo($ch,CURLINFO_HTTP_CODE);
	curl_close($ch);
	$result=json_decode($callResult,true);
	$in="(";
	foreach ($result["orders"] as $k=>$o){
		$in.="'" . $o["id"] ."',"; 
	}
    $in=substr($in, 0, -1) .")";
	return $in;
}
function elencoordini($coppia){
	//restituisce una array con item con indice l'id ordine e valore true
	$url="https://api.therocktrading.com/v1/funds/".$coppia."/orders";
	$headers=creaHeaders($url);
	$ch=curl_init();
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_CUSTOMREQUEST,"GET");
	curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	$callResult=curl_exec($ch);
	$httpCode=curl_getinfo($ch,CURLINFO_HTTP_CODE);
	curl_close($ch);
	if ($httpCode!="200") {
		scrivilogbot("$coppia" ."_elenco_ordini_error.log",$callResult);
	}else   {
		$result=json_decode($callResult,true);
		$r=array();
		foreach ($result["orders"] as $k=>$o){
			$r[$o["id"]]=true;
		}
		return $r;
	}
	return false;
}