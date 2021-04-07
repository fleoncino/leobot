<?php
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