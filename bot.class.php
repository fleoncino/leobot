<?php
class bot {
	public  $idbot; //identificativo bot
	private $ngrid; //numero di ordini da generare
	private $coppia; //coppia su cui operare
	private $qta;  //quantita' singolo ordine 
	private $mingap; //valore minimo del gap (sarà quello relativo alla coppia buy/sell più vicina al prezzo di mercato all'avvio del bot
	private $maxgap; //valore massimo del gap, relativo alla differenza fra l'ordine estremo e il precedente
	private $incgap; //incremento fra due gap successivi
	private $conndb;
	private $ordini;
	private $prezzomin;
	private $prezzomax;
	function __construct ($idbot, $conndb,$ngrid, $coppia, $qta, $mingap, $maxgap){
		$this->ngrid = $ngrid;
		$this->coppia = $coppia;
		$this->qta = $qta;
		$this->mingap = $mingap-0;//così capisce che è un numero
		$this->maxgap = $maxgap-0;
		$this->incgap = $this->incremento_gap() ;
		$this->conndb = $conndb;
		$this->idbot = $idbot;
	}
	function scrivilog($s){
		scrivilogbot($this->idbot,$s);
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
			$prezzobuy-=$gap;
			$prezzosell+=$gap;
			$gap+=$this->incgap;
			$ordbuy= new ordine("buy",$this->coppia,$this->qta,$prezzobuy, $this->idbot, $this->conndb);
			$ordsell= new ordine("sell",$this->coppia,$this->qta,$prezzosell, $this->idbot, $this->conndb);
			$this->ordini[]=$ordbuy;
			$this->ordini[]=$ordsell;
		}
		$this->prezzomin=$prezzobuy;
		$this->prezzomax=$prezzosell;
		$this->registrabot();
		foreach ($this->ordini as $o){
			$o->immetti();
		}
	}
	function restoreord(){
		$q="SELECT `id`, `segno`, `coppia`, `qta`, `qtaese`, 
			`prezzo`, `prezzoese`, `chiuso`, `annullato`, `idbot` 
			FROM `ordini` WHERE idbot='". $this->idbot ."' and chiuso=0";
  		$this->scrivilog($q);
		$conn=connessione($this->conndb);  
  		$result = $conn->query($q);
		foreach ($result as $row) {
			$ord= new ordine($row['segno'],$row['coppia'],$row['qta'],
				$row['prezzo'], $row['idbot'], $this->conndb, $row['id']);
			$this->ordini[]=$ord;
			$this->scrivilog("ordine: $ord->id $ord->segno $ord->coppia $ord->qta $ord->prezzo " . $ord->qta*$ord->prezzo );
		}

	}
	private function incremento_gap(){
		return 2*($this->maxgap-$this->mingap)/$this->ngrid;
	}
	private function registrabot(){
                $q="INSERT INTO `bot`(`id`, `ngrid`, `coppia`, `qta`, `mingap`, `maxgap`, `attivo`) VALUES (?,?,?,?,?,?,0)";
				$conn=connessione($this->conndb);  
                $stmt=$conn->prepare($q);
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
                if ($rowinserted<>1) $this->scrivilog("Errore inserimento bot in DB: $this->idbot $this->ngrid $this->coppia $this->qta $this->mingap $this->maxgap");
                $stmt->close();
	}
	function vai(){
		while (1){
			//$this->checkbot();
			//$this->checkbot();
			$this->checkbot3();
			usleep(400);
			echo ".";
		}
	}
	function checkbot3(){ 
		//fa una chiamata API TRT per avere tutti gli ordini li confronta con l'array ordini per identificare gli ordini non aperti
		$arridord=elencoordini($this->coppia);//ottengo una array i cui indici sono gli id degli ordini
		if ($arridord){
			foreach ($this->ordini as $k=>$o){
				if (!isset($arridord[$o->id])){//se l'ordine non risulta a TRT devo controllare se è chiuso
					$o->checkstato();
					if ($o->executed) {
						$this->scrivilog("Eseguito $o->id $o->segno $o->coppia $o->qta $o->prezzo " . $o->qta*$o->prezzo );
						$this->scrivilog("IMMETTO NUOVO ORDINE");
						if ($o->segno=='buy'){
							$prezzo=$o->prezzo+$this->mingap;
							$ord= new ordine ("sell",$o->coppia,$o->qta,$prezzo, $this->idbot, $this->conndb);
							$messaggio= "sell " . $o->coppia . " " . $o->qta. " " . $o->prezzo . " " . $this->mingap ." $prezzo";
						}else {
							$prezzo=$o->prezzo-$this->mingap;
							$ord= new ordine ("buy",$o->coppia,$o->qta,$prezzo, $this->idbot, $this->conndb);
							$messaggio = "buy " . $o->coppia . " " . $o->qta. " " . $o->prezzo . " ". $this->mingap ." $prezzo";
						}
						InviaMessaggioTelegram($messaggio);
						echo "$messaggio\n";
						$this->ordini[]=$ord;
						$ord->immetti();
		
						unset($this->ordini[$k]);
						var_dump($this->ordini);
					}
				}
			}
		}
	}

	function checkbot2(){
		//fa una chiamata API TRT per avere tutti gli ordini li confronta con il DB e lavora solo su quelli che non sono aperti
		$in=elencoidordini($this->coppia);
		$q="select id from ordini where id not in $in and chiuso=0 and idbot='" . $this->idbot."'";
		$this->scrivilog($q);
		$conn=connessione($this->conndb);  
  		$result = $conn->query($q);
		foreach ($result as $row) {
			$k=findord($id);
			if ($k){
				$this->ordini[$k]->checkstato();
				if ($this->ordini[$k]->executed) {
					$this->scrivilog("Eseguito $this->ordini[$k]->id $this->ordini[$k]->segno $this->ordini[$k]->coppia $this->ordini[$k]->qta $this->ordini[$k]->prezzo " . $this->ordini[$k]->qta*$this->ordini[$k]->prezzo );
					$this->scrivilog("IMMETTO NUOVO ORDINE");
					if ($this->ordini[$k]->segno=='buy'){
						$prezzo=$this->ordini[$k]->prezzo+$this->mingap;
						$ord= new ordine ("sell",$this->ordini[$k]->coppia,$this->ordini[$k]->qta,$prezzo, $this->idbot, $this->conndb);
						$messaggio= "sell " . $this->ordini[$k]->coppia . " " . $this->ordini[$k]->qta. " " . $this->ordini[$k]->prezzo . " " . $this->mingap ." $prezzo";
					}else {
						$prezzo=$this->ordini[$k]->prezzo-$this->mingap;
						$ord= new ordine ("buy",$this->ordini[$k]->coppia,$this->ordini[$k]->qta,$prezzo, $this->idbot, $this->conndb);
						$messaggio = "buy " . $this->ordini[$k]->coppia . " " . $this->ordini[$k]->qta. " " . $this->ordini[$k]->prezzo . " ". $this->mingap ." $prezzo";
					}
					InviaMessaggioTelegram($messaggio);
					echo "$messaggio\n";
					$this->ordini[]=$ord;
					$ord->immetti();
					unset($this->ordini[$k]);
					var_dump($this->ordini);
					//die();
				}		
			}
		}
	}
	function findord($id){
		foreach ($this->ordini as $k=>$o){
			if ($o->id==$id){
				return $k;
			}
		}
		return false;
	}
	function checkbot(){
		//fa una chiamata API TRT per ogni ordine dell'array $this->ordini
		$nsell=0;
		$nbuy=0;
		foreach ($this->ordini as $k=>$o){
			$o->checkstato();
			if ($o->executed) {
				$this->scrivilog("Eseguito $o->id $o->segno $o->coppia $o->qta $o->prezzo " . $o->qta*$o->prezzo );
				$this->scrivilog("IMMETTO NUOVO ORDINE");
				if ($o->segno=='buy'){
					$prezzo=$o->prezzo+$this->mingap;
					$ord= new ordine ("sell",$o->coppia,$o->qta,$prezzo, $this->idbot, $this->conndb);
					$messaggio= "sell " . $o->coppia . " " . $o->qta. " " . $o->prezzo . " " . $this->mingap ." $prezzo";
				}else {
					$prezzo=$o->prezzo-$this->mingap;
					$ord= new ordine ("buy",$o->coppia,$o->qta,$prezzo, $this->idbot, $this->conndb);
					$messaggio = "buy " . $o->coppia . " " . $o->qta. " " . $o->prezzo . " ". $this->mingap ." $prezzo";
				}
				InviaMessaggioTelegram($messaggio);
				echo "$messaggio\n";
				$this->ordini[]=$ord;
				$ord->immetti();

				unset($this->ordini[$k]);
				var_dump($this->ordini);
				//die();
			}
		}
		foreach ($this->ordini as $o){
			if ($o->segno=='buy') $nbuy++; else $nsell++;
		}
		if ($nbuy==0 or $nsell==0) die("out of range");//$this->ripianifica($nbuy, $nsell);
	}
}

