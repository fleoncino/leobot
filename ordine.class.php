<?php

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
	private $conndb;
	public $ord;  //array con response TRT dopo inserimento ordine 
	public $executed;
	public $inserted;
	function __construct($segno,$coppia,$qta,$prezzo,$idbot,$conndb, $idord=false){
		$this->segno=$segno;
		$this->coppia=$coppia;
		$this->qta=$qta-0;
		$this->qtaese=0;
		$this->prezzo=$prezzo-0;
		$this->prezzoese=0;
		$this->chiuso=false;
		$this->annullato=false;
		$this->idbot=$idbot;
		$this->conndb=$conndb;
		$this->ord=array();
		$this->executed=false;
		$this->inserted=false;
		if ($idord)	$this->id=$idord;
	}
	function scrivilog($s){
		scrivilogbot($this->idbot,$s);
	}
	function immetti(){
		$this->inviaordine();
		if (!$this->inserted){
			$this->scrivilog("Fallito ordine: $this->segno $this->coppia $this->qta $this->prezzo " . $this->qta*$this->prezzo . "\n");
			throw new Exception("Fallito ordine: $this->segno $this->coppia $this->qta $this->prezzo " . $this->qta*$this->prezzo . "\n");
		}
		$this->scrivilog("ordine: $this->id $this->segno $this->coppia $this->qta $this->prezzo " . $this->qta*$this->prezzo );
		// inserimento ordine in TRT
		$q="INSERT INTO `ordini`(`id`, `segno`, `coppia`, `qta`, `qtaese`, `prezzo`, `prezzoese`, `chiuso`, `annullato`, `idbot`) VALUES (?,?,?,?,0,?,0,0,0,?)";
		$conn=connessione($this->conndb);  
		$stmt=$conn->prepare($q);
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
		if ($rowinserted<>1) $this->scrivilog("Errore inserimento ordine in DB:$this->id $this->segno $this->coppia $this->qta $this->prezzo $this->idbot");
                $stmt->close();
	}
	function inviaordine(){
		$params=array(
			"fund_id"=>$this->coppia,
			"side"=>$this->segno,
			"amount"=>$this->qta,
			"price"=>$this->prezzo
		  );
        $url="https://api.therocktrading.com/v1/funds/".$this->coppia."/orders";
		$headers=creaHeaders($url);
		$ch=curl_init();
		curl_setopt($ch,CURLOPT_URL,$url);
		curl_setopt($ch,CURLOPT_CUSTOMREQUEST,"POST");
		curl_setopt($ch,CURLOPT_POST,TRUE);
		curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($params));
		curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
		$callResult=curl_exec($ch);
		$this->scrivilog(json_encode($params));
		$this->scrivilog($callResult);
		if ($callResult) {
			$this->inserted=true;
			$this->ord=json_decode($callResult);
			$this->id=$this->ord->id;
		}
	}
	function annulla(){
		//Annullamento ordine in TRT
		//update ordine con annullato= true
	}
	function checkstato(){
		//recupero info ordine da TRT
		$url="https://api.therocktrading.com/v1/funds/".$this->coppia."/orders/".$this->id;

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
		if ($result["status"]=="executed"){
			$this->executed=true;
			$conn=connessione($this->conndb);  
			$conn->query("UPDATE `ordini` set chiuso=1 where id=".$this->id);		
			$this->scrivilog("Eseguito $callResult");
		}
	}
}
