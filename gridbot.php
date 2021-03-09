<?php
include "include/config.inc.php";
include "/etc/gridbot/headers.inc.php";

class bidask{
	public $bid;
	public $ask;
	function __construct ($coppia){
		$url="https://api.therocktrading.com/v1/funds/".$coppia."/orderbook?limit=1";
		$headers=creaHeaders($url);
		$ch=curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		$callResult=curl_exec($ch);
		curl_close($ch);
		$result=json_decode($callResult,true);
		$this->bid=$result["bids"][0]["price"];
		$this->ask=$result["asks"][0]["price"];
	}
	function mediaba(){
		return ($this->bid+$this->ask)/2;
	}
}
function scrivilog($s){
	echo "$s\n";
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
class ordine {
	public $id;
	public $segno;
	public $coppia;
	public $qta;
	public $qtaese;
	public $prezzo;
	public $prezzoese;
	public $chiuso;
	public $annullato;
	private $idbot;
	private $db;
	function __construct($segno,$coppia,$qta,$prezzo,$idbot,$db){
		$this->segno=$segno;
		$this->coppia=$coppia;
		$this->qta=$qta;
		$this->qtaese=0;
		$this->prezzo=$prezzo;
		$this->prezzoese=0;
		$this->chiuso=false;
		$this->annullato=false;
		$this->idbot=$idbot;
		$this->db=$db;
	}
	function immetti(){
		echo "ordine: $this->segno $this->coppia $this->qta $this->prezzo " . $this->qta*$this->prezzo . "\n";
		$this->id=random_int(1, 99999);
		// inserimento ordine in TRT
		$q="INSERT INTO `ordini`(`id`, `segno`, `coppia`, `qta`, `qtaese`, `prezzo`, `prezzoese`, `chiuso`, `annullato`, `idbot`) VALUES (?,?,?,?,0,?,0,0,0,?)";
		$stmt=$this->db->prepare($q);
		$stmt->bind_param('sssdds',
                                        $this->id,
                                        $this->segno,
                                        $this->coppia,
                                        $this->qta,
                                        $this->prezzo,
                                        $this->idbot
                        );
		$stmt->execute();
		$rowinserted=$stmt->affected_rows;
		if ($rowinserted<>1) scrivilog("Errore inserimento ordine in DB:$this->id $this->segno $this->coppia $this->qta $this->prezzo $this->idbot");
                $stmt->close();
	}
	function annulla(){
		//Annullamento ordine in TRT
		//update ordine con annullato= true
	}
	function checkstato(){
		//recupero info orfine da TRT
	}
}
class bot {
	public $idbot; //identificativo bot
	private $ngrid; //numero di ordini da generare
	private $coppia; //coppia su cui operare
	private $qta;  //quantita' singolo ordine 
	private $mingap; //valore minimo del gap (sarà quello relativo alla coppia buy/sell più vicina al prezzo di mercato all'avvio del bot
	private $maxgap; //valore massimo del gap, relativo alla differenza fra l'ordine estremo e il precedente
	private $incgap; //incremento fra due gap successivi
	private $db;
	private $ordini;
	function __construct ($id, $db,$ngrid, $coppia, $qta, $mingap, $maxgap){
		$this->ngrid = $ngrid;
		$this->coppia = $coppia;
		$this->qta = $qta;
		$this->mingap = $mingap;
		$this->maxgap = $maxgap;
		$this->incgap = $this->incremento_gap() ;
		$this->db = $db;
		$this->idbot = $id;
	}
	private function verificasaldi($prezzo){
		$r="";
		$necessitabase=$this->qta*$prezzo*$this->ngrid/2;
		$necessitatarget=$this->qta*$this->ngrid/2;
		$saldobase=saldo(substr($this->coppia,3,6));
		$saldotarget=saldo(substr($this->coppia,0,3));
		if ($saldobase<$necessitabase) $r.="Saldo " . substr($this->coppia,3,6) . " insufficiente. Richiesto $necessitabase presente $saldobase\n";
		if ($saldotarget<$necessitatarget) $r.="Saldo " . substr($this->coppia,0,3) ." insufficiente. Richiesto $necessitatarget presente $saldotarget\n";
		if ($r==="") return false; else return $r;
	}
	function pianifica(){
		$ba= new bidask ($this->coppia);
		$prezzoavvio= $ba->mediaba();
		$r=$this->verificasaldi($prezzoavvio);
		if ($r) throw new Exception($r);
		$prezzobuy=$prezzoavvio;
		$prezzosell=$prezzoavvio;
		$gap=$this->mingap;
		for ($i=0; $i<$this->ngrid/2;$i++){
			$prezzobuy+=$gap;
			$prezzosell-=$gap;
			$gap+=$this->incgap;
			$ordbuy= new ordine("buy",$this->coppia,$this->qta,$prezzobuy, $this->idbot, $this->db);
			$ordsell= new ordine("sell",$this->coppia,$this->qta,$prezzosell, $this->idbot, $this->db);
			$this->ordini[]=$ordbuy;
			$this->ordini[]=$ordsell;
		}
		$this->registrabot();
		foreach ($this->ordini as $o){
			$o->immetti();
		}
	}
	private function incremento_gap(){
		return 2*($this->maxgap-$this->mingap)/$this->ngrid;
	}
	private function registrabot(){
                $q="INSERT INTO `bot`(`id`, `ngrid`, `coppia`, `qta`, `mingap`, `maxgap`, `attivo`) VALUES (?,?,?,?,?,?,0)";
                $stmt=$this->db->prepare($q);
                $stmt->bind_param('sdsddd',
                                        $this->idbot,
                                        $this->ngrid,
                                        $this->coppia,
                                        $this->qta,
                                        $this->mingap,
                                        $this->maxgap
                        );
                $stmt->execute();
                $rowinserted=$stmt->affected_rows;
                if ($rowinserted<>1) scrivilog("Errore inserimento bot in DB: $this->idbot $this->ngrid $this->coppia $this->qta $this->mingap $this->maxgap");
                $stmt->close();
	}
	function checkbot(){
		foreach ($this->ordini as $o){
			if ($o->checkstato()=='chiuso') {
				if ($o->segno=='buy')
					$ord= new ordine ("sell",$o->coppia,$o->qta,$o->prezzo+$this->gap, $this->idbot, $this->db);
				else 
					$ord= new ordine ("buy",$o->coppia,$o->qta,$o->prezzor-$this->gap, $this->idbot, $this->db);
				unset($o);
				$this->ordini[]=$ord;
				$ord->immetti();
			}
		}
	}
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
try{
	$b = new bot($argv[6], $conn, $argv[1], $argv[2], $argv[3], $argv[4], $argv[5]);
	$b->pianifica();
}catch (Exception $e) {
    echo "Errore:\n",  $e->getMessage(), "\n";

}

