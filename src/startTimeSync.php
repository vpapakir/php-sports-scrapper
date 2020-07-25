<?php

include "setting.php";
include "Holder.php";
require("Database.singleton.php");
$do = true;

date_default_timezone_set('Australia/Queensland');
$dt = new DateTime();
$dt->modify('14 hour');
$dt = $dt->format('Y-m-d H:i:s');

$db = Database::obtain(DB_SERVER, DB_USER, DB_PASS, DB_DATABASE);
$db->connect();
$query = $db->query_first("select master_id from trackracemaster WHERE race_time < '{$dt}' limit 1");
print_r($query);
$do = ($query  && count($query) > 0);

if($do){
try{
	entry();
} catch (Exception $e) {
    echo 'Caught exception: ',  $e->getMessage(), "\n";
}
}
function entry(){
        $date = new DateTime(date('Y-m-d H:i:s', time()));
	$tatts_url = "https://tatts.com/racing/".$date->format("Y")."/".$date->format("m")."/".$date->format("d")."/RaceDay";
	$doc = getWebPage($tatts_url);
	processPage($doc);
}



function processPage($siteContent)
{
    $skip  = true;

    $blockToScrape = $siteContent->document->getElementById("page_R1");
    $rows = $blockToScrape->getElementsByTagName('tr');

    foreach ($rows as $row) {
        if ($skip){
            $skip = false;
            continue;
        }
        processTracks($row);
    }
}

function processTracks($row)
{
    $cols = $row->getElementsByTagName('td');

    $trackName = trim($cols->item(1)->nodeValue);
    $shortName = GetShortName($trackName);

    LogMessage("scrape_tatts_for_each_track : Scraping For track".$trackName);

    if (trim(substr($shortName,strlen($shortName)-1,1)) == 'R'){
        LogMessage("scrape_tatts_for_each_track : Starting Scraping For track".$trackName);
		$trackName = trim(explode(")", $trackName)[1]);
        processRaces($cols, $trackName, $shortName);
        LogMessage("scrape_tatts_for_each_track : Completed Scraping For track".$trackName);
    }
}

function processRaces($cols, $trackName, $shortName)
{
    LogMessage("scrape_tatts_track_for_each_race : Total races to scrape - ".$cols->length-2);
    //first 2 columns are not required to scrape
    for ($i = 2; $i < $cols->length-1; $i++) {
        $holder = new Holder();
		$holder->tatts_short_track_name =  $shortName;
        $holder->tatts_track_name = $trackName;
        $holder->race_no = $i-1;

        $col = $cols->item($i)->getElementsByTagName("a");

        if($col->length > 0){
            $holder->tatts_race_url = "https://tatts.com".$col->item(0)->getAttribute('href');
            $holder->raw = trim($col->item(0)->nodeValue);
            $spanCol = $col->item(0)->getElementsByTagName("span");

            if ($spanCol->length > 0){
                $spanCol = $spanCol->item(0)->getElementsByTagName("span");
                $holder->race_date = (new DateTime($spanCol->item(0)->nodeValue))->format('Y-m-d H:i:s');
            }			

            if (strlen(trim($holder->raw)) > 0){
				if(isset($holder->race_date)){
					updateStartTime($holder);
				}else{
					//if(preg_match("/[a-z ]/i", trim($holder->raw))){
						updateStatus($holder);
					//}
				}
               	print_r($holder);
            }
        } else {
            LogMessage('Error - expected A tag is not found. So time and other information does not exists.');
        }
    }
}



function getWebPage($url) {
    echo "URL requested: ".$url."\n";
	
    $ch = curl_init();  
    curl_setopt($ch, CURLOPT_URL, $url);    
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, True); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, True);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	///////////////////////////
	//curl_setopt($ch, CURLOPT_PROXY, "localhost:8118");
	///////////////////////////

    $data = curl_exec($ch); 
    if(curl_errno($ch)){
        echo 'Curl error: ' . curl_error($ch) . "\n";
    }
    curl_close($ch);
    return returnXPathObject($data);
}



function LogMessage($msg){
	echo $msg."\n";
}


function GetShortName($trackName) {
    $temp = explode(" ", $trackName);
    $shortName = $temp[0];
    $shortName = str_replace("(", "", $shortName);
    $shortName = str_replace(")", "", $shortName);

    return $shortName;
}


function returnXPathObject($item) {
    $xmlPageDom = new DomDocument();    
    @$xmlPageDom->loadHTML($item);  
    $xmlPageXPath = new DOMXPath($xmlPageDom);  
    return $xmlPageXPath;   
}


function updateStartTime($holder){
    global $db;
	$sql = "update trackracemaster set race_date = '" . $holder->race_date."', race_time = '" . $holder->race_date . "'" . getClause($holder) . " and (race_date != '" . $holder->race_date . "' and race_time != '" . $holder->race_date . "');";
	printf($sql."\n");
	$db->query($sql);
}

function updateStatus($holder){
    global $db;
	$sql = "update trackracemaster set race_status = '" . $holder->raw . "'" . getClause($holder) . " and race_status != '" . $holder->raw . "';";
	printf($sql."\n");
	$db->query($sql);
}

function getClause($holder) {
	return " where ((tatts_track_name = '" . $holder->tatts_track_name . "' and tatts_short_track_name = '" . $holder->tatts_short_track_name ."' and race_no = '" . trim($holder->race_no) . "') or tatts_race_url = '" . $holder->tatts_race_url . "')";
}

$db->close();