<?php

$script_path = "/home/moneyinmotioncom/public_html/cron_jobs/logs";

//only show the error if in debug mode
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', $script_path . 'track_errors.txt');

include "setting.php";
include "MasterDataMembers.php";
require("Database.singleton.php");

date_default_timezone_set('Australia/Queensland');

// create the $db singleton object
$objDB = Database::obtain(DB_SERVER, DB_USER, DB_PASS, DB_DATABASE);
$objDB->connect();

///////////////////////////////////////////////////////////////////
///////// ONLY FOR TESTING /////////////////////////////////////////

///////////////////////////////////////////////////////////////////

//$date2 = new DateTime(date('Y-m-d H:i:s', time()));
//print3("abcd---".$date2);

//exit();

////////////////////////////////////////////////////////////////////
//////////All processing starts from main///////////////////////////
////////////////////////////////////////////////////////////////////
LogMessage("Main : Start scraping tracks and races data.");
main();
LogMessage("Main : Completed scraping.");
/////////////////////////////////////////////////////////////////////

function main()
{
    //delete the old records that are no more required
    delete_previous_records();

    //scrape track data from tatts.com
    LogMessage("Main : scrape track data from tatts.com.");
    //get current date time
    $date = new DateTime(date('Y-m-d H:i:s', time()));
	$tatts_url = "https://tatts.com/racing/".$date->format("Y")."/".$date->format("m")."/".$date->format("d")."/RaceDay";

    scrape_daily_race_data_from_tatts($tatts_url);

    //scrape track data from sportingbet.com.au
    LogMessage("Main : scrape track data from sportingbet.com.au.");
    $sportting_url = "https://www.sportingbet.com.au/horse-racinggrid/meetings/Today";
    scrape_daily_race_data_from_sportingbet($sportting_url);

    //scrape BetFair.com.au URL for each race
    LogMessage("Main : scrape track data from betfair.com.au.");
    $betfair_url = "https://www.betfair.com.au/racing";
    scrape_daily_race_data_from_betfair($betfair_url);

}

function delete_previous_records()
{
    LogMessage("delete_previous_records : deleting previous records before start loading new records.");

    $db2 = Database::obtain();

    $sSql = "Delete From trackracemaster ";
    $db2->query($sSql);

    $sSql = "Delete From runner_details  ";
    $db2->query($sSql);
}

function ConvertStringToDate($dateString)
{
    $d = new DateTime($dateString);
    $formatted_date = $d->format('Y-m-d H:i:s');

    return $formatted_date;
}

// Method to return XPath object
function returnXPathObject($item) {
    $xmlPageDom = new DomDocument();    // Instantiating a new DomDocument object
    @$xmlPageDom->loadHTML($item);   // Loading the HTML from downloaded page
    $xmlPageXPath = new DOMXPath($xmlPageDom);  // Instantiating new XPath DOM object
    return $xmlPageXPath;   // Returning XPath object
}

// Method to return XPath object
function returnDOMObject($htmlContent) {
    $xmlPageDom = new DomDocument();    // Instantiating a new DomDocument object
    @$xmlPageDom->loadHTML($htmlContent);   // Loading the HTML from downloaded page

    return @$xmlPageDom;
}

// Defining the basic cURL function
function Get_Curl($url) {
    LogMessage("Get_Curl : Calling URL - ".$url);

    //Random sleep or delay in call of page to avoid risk of blocking of IP address
    sleep(rand(MinDelayTime, MaxDelayTime));

    $ch = curl_init();  // Initialising cURL
    curl_setopt($ch, CURLOPT_URL, $url);    // Setting cURL's URL option with the $url variable passed into the function
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, True); // Setting cURL's option to return the webpage data
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);

    $data = curl_exec($ch); // Executing the cURL request and assigning the returned data to the $data variable
    if(curl_errno($ch)){
        LogMessage('Curl error: ' . curl_error($ch));
    }
    else
    {
        //print_r(curl_getinfo($ch));
    }
    curl_close($ch);    // Closing cURL
    return $data;   // Returning html content of site
}

function scrape_daily_race_data_from_tatts($url)
{
    $IsHeader  =true;
    $siteContent = returnXPathObject(Get_Curl($url));

    //LogMessage("Scraping started for tatts");

    $blockToScrape=$siteContent->document->getElementById("page_R1");
    $rows = $blockToScrape->getElementsByTagName('tr');
    //LogMessage("Total tracks to scrape are : ".($rows->length - 1));

    foreach ($rows as $row)
    {
        //avoid first rows as its contain column names
        if ($IsHeader == true){
            $IsHeader = false;
            continue;
        }

        //LogMessage("Scraping started for a track.");
        //scrape each track and its races
        scrape_tatts_for_each_track($row);

        //LogMessage("Scraping completed for this track.");
    }
}

function scrape_tatts_for_each_track($row)
{
    $cols = $row->getElementsByTagName('td');

    $TrackName = trim($cols->item(1)->nodeValue);
    $shortName = GetShortName($TrackName);

    LogMessage("scrape_tatts_for_each_track : Scraping For track".$TrackName);

    //uncomment...BS is used for testiling only
    if (trim(substr($shortName,strlen($shortName)-1,1)) == 'R'){
        LogMessage("scrape_tatts_for_each_track : Starting Scraping For track".$TrackName);
        scrape_tatts_track_for_each_race($cols, $TrackName, $shortName);
        LogMessage("scrape_tatts_for_each_track : Completed Scraping For track".$TrackName);
    }
}

function scrape_tatts_track_for_each_race($cols, $TrackName, $shortName)
{
    LogMessage("scrape_tatts_track_for_each_race : Total races to scrape - ".$cols->length-2);
    //first 2 columns are not required to scrape
    for ($i = 2; $i < $cols->length-1; $i++) {
        $dmRaceTrack = new MasterDataMembers();

        $dmRaceTrack->tatts_track_name = GetTrackName($TrackName);
        $dmRaceTrack->tatts_short_track_name = $shortName;
        $dmRaceTrack->race_no = $i-1;

        $col = $cols->item($i)->getElementsByTagName("a");

        if($col->length > 0){
            $dmRaceTrack->tatts_race_url = GetTattsUrl($col->item(0)->getAttribute('href'));
            $dmRaceTrack->race_cell_value = trim($col->item(0)->nodeValue);
            //Get race date time
            $spanCol = $col->item(0)->getElementsByTagName("span");

            if ($spanCol->length > 0){
                $spanCol = $spanCol->item(0)->getElementsByTagName("span");
                $dmRaceTrack->race_date = ConvertStringToDate($spanCol->item(0)->nodeValue);
                $dmRaceTrack->race_time = ConvertStringToDate($spanCol->item(0)->nodeValue);

                $dmRaceTrack->next_scrape_time = date('Y-m-d H:i:s', time());
            }
            $dmRaceTrack->race_status = "Open"; //GetRaceStatus($dmRaceTrack);
            //only save entry is there.
            if (strlen(trim($dmRaceTrack->race_cell_value))>0){
                SaveTrackRaceMaster($dmRaceTrack);
            }
        } else {
            LogMessage('Error - expected A tag is not found. So time and other information does not exists.');
        }
    }
}

function GetShortName($TrackName)
{
    $temp = explode(" ", $TrackName);
    $shortName = $temp[0];
    $shortName = str_replace("(", "", $shortName);
    $shortName = str_replace(")", "", $shortName);

    return $shortName;
}


function LogMessage($message)
{
    print_r( "<br>\n\t".date('Y-m-d H:i:s', time()).": ".$message);

    save_log($message);
}

function save_log($message) {
    $log_file = "/home/moneyinmotioncom/public_html/cron_jobs/logs/track_scrape_log.txt";
	
    $file = fopen($log_file, 'a');
    $to_save = date(DATE_COOKIE) . ' -- ' . getmypid() . ' -- ' . $message . "\r\n";

    if (flock($file, LOCK_EX)) {
        fputs($file, $to_save);
    } else {
        echo 'Cant save log...';
    }
    echo $to_save . "\r\n";
    fclose($file);

}

function GetTrackName($TrackName)
{
    $temp = explode(")", $TrackName);
    return trim($temp[1]);
}

function SaveTrackRaceMaster($dmMaster)
{
    $date = new DateTime(date('Y-m-d H:i:s', time()));
    $date_str = $date->format("Y")."-".$date->format("m")."-".$date->format("d");

    $db2 = Database::obtain();
    $sSql = "SELECT master_id FROM ".TABLE_MASTER." WHERE tatts_track_name = '".trim($dmMaster->tatts_track_name)."'
                                              and race_no=".$dmMaster->race_no." and date(race_time) = '".$date_str."'";
    $record = $db2->query_first($sSql);
    $rec_mID =  $record['master_id'];

    if ($rec_mID > 0){
        //Update Record
        UpdateTrackRaceMaster($rec_mID, $dmMaster);
    }else{
        //Insert Record
        InsertTrackRaceMaster($dmMaster);
    }
}

function UpdateTrackRaceMaster($rec_mID, $dmMaster)
{
    $db2 = Database::obtain();
    //$dmMaster = new MasterDataMembers();

    $data['race_cell_value'] = $dmMaster->race_cell_value;
    $data['race_status'] = $dmMaster->race_status;
    $where = ' master_id='.$rec_mID;

    $resultData = $db2->update(TABLE_MASTER, $data, $where);
}

function InsertTrackRaceMaster($dmMaster)
{
    $db2 = Database::obtain();
    //$dmMaster = new MasterDataMembers();

    if (strlen(trim($dmMaster->tatts_track_name))>0)
        $data['tatts_track_name'] = $dmMaster->tatts_track_name;

    if (strlen(trim($dmMaster->tatts_short_track_name))>0)
        $data['tatts_short_track_name'] = $dmMaster->tatts_short_track_name;

    if (strlen(trim($dmMaster->sb_track_name))>0)
        $data['sb_track_name'] = $dmMaster->sb_track_name;

    if (strlen(trim($dmMaster->bf_track_name))>0)
        $data['bf_track_name'] = $dmMaster->bf_track_name;

    $data['race_no'] = $dmMaster->race_no;
    $data['race_date'] = ConvertStringToDate($dmMaster->race_date);
    $data['race_time'] = ConvertStringToDate($dmMaster->race_time);

    if (strlen(trim($dmMaster->tatts_race_url))>0)
        $data['tatts_race_url'] = $dmMaster->tatts_race_url;

    if (strlen(trim($dmMaster->sb_race_url))>0)
        $data['sb_race_url'] = $dmMaster->sb_race_url;

    if (strlen(trim($dmMaster->bf_race_url))>0)
        $data['bf_race_url'] = $dmMaster->bf_race_url;

    $data['race_status'] = $dmMaster->race_status;
    $data['race_cell_value'] = $dmMaster->race_cell_value;

    $data['next_scrape_time'] = date('Y-m-d H:i:s', time());// $dmMaster->race_cell_value;
    //$data['last_scrape_time'] = ConvertStringToDate($dmMaster->race_time); //$dmMaster->race_cell_value;

    $primary_id = $db2->insert(TABLE_MASTER, $data);

    return $primary_id;
}

function GetTattsUrl($scrapedURL){
    //@#!@#!@#!@#!@
    return TattsBetDomainURL.$scrapedURL;
}

////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////SPORTBET.COM///////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////

function scrape_daily_race_data_from_sportingbet($url)
{
    $siteContent = returnXPathObject(Get_Curl($url));
    $blockToScrape = GetBlockToScrape($siteContent);
    //check if there is any block to scrape
    //if no block then return false
    if ($blockToScrape == false)
    {
        return false;
    }

    $eachTrack = $siteContent->query(qEachTracksInBlock, $blockToScrape);

    for ($i = 0; $i < $eachTrack->length; $i++) {
        GetTimesOnEachTrack($siteContent, $eachTrack->item($i));
    }
}

function GetBlockToScrape($fnSiteContent)
{
    $eleCollection = $fnSiteContent->query(qRacingTrackBlocks);

    for ($i = 0; $i < $eleCollection->length; $i++) {
        $eachBlock = $fnSiteContent->query(qRacingTrackBlocksHeader, $eleCollection->item($i));
        $tempValue = $eachBlock->item(0)->nodeValue;
        $pos = strpos($tempValue, qRacingTrackBlocksHeaderText);
        if ($pos !== false)
        {
            return $eleCollection->item($i);
        }
    }
    return false;
}


function GetTimesOnEachTrack($fnSiteContent, $track)
{
    $eachRace = $fnSiteContent->query(qEachTimeInTrack, $track);
    //first coloum for track name
    $traceName = $eachRace->item(0)->nodeValue;
    LogMessage("GetTimesOnEachTrack : SporttingBet track scraping for - ".$traceName);
    /*
    $pos = strpos($traceName, 'Sportingbet');
    if ($pos !== false)
    {
        return "";
    }
    */

    for ($i = 1; $i < $eachRace->length; $i++) {

        $dmMaster = new MasterDataMembers();
        $dmMaster->race_name = $traceName;
        $master_id = check_track_race_no_exist($traceName, $i);

        if ((strlen(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $eachRace->item($i)->nodeValue)) > 0)
            && (strlen(trim($master_id))>0)
            && (!IsRaceAbandoned($eachRace->item($i)))){

            $race = $fnSiteContent->query('.//a', $eachRace->item($i));
            $dmMaster->sb_race_url = SportingBetDomainURL.$race->item(0)->attributes->item(1)->value;
        }
        else{
            $dmMaster->race_no = $i;
            $dmMaster->race_cell_value = trim($eachRace->item($i)->nodeValue);
            $dmMaster->race_status = "";  //trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $eachRace->item($i)->nodeValue));

            //print_r($dmMaster->race_status.";".$dmMaster->race_name.";".'cell value'.";" .'date'.";".'time'.";".'url'.";"." <br> \n");
        }
        if (trim($dmMaster->sb_race_url) !='') save_sportting_track_master($dmMaster, $master_id);
    }
}

function check_track_race_no_exist($track, $race_no)
{
    $db2 = Database::obtain();

    $date = new DateTime(date('Y-m-d H:i:s', time()));
    $date_str = $date->format("Y")."-".$date->format("m")."-".$date->format("d");


    $sSql = "Select master_id, tatts_track_name from trackracemaster
            where upper(trim(tatts_track_name)) = upper('".translate_track(trim($track))."')
            and race_no = ".$race_no."
            and date(race_time) = '".$date_str."'";

    $master_list =  $db2->fetch_array($sSql);

    if(count($master_list)==0){
        return "";
    }else{
        return $master_list[0]["master_id"];
    }
}

function IsRaceAbandoned($eachRace)
{
    if (trim($eachRace->nodeValue) == 'Abandoned') {
        return true;
    }else{
        return false;
    }
}


function GetRaceTime($fnSiteContent, $eachRace)
{
    $race = $fnSiteContent->query('.//a/time', $eachRace);
    return $race->item(0)->attributes->item(0)->value;
}

function save_sportting_track_master($dmMaster, $master_id)
{
       //Update Record
        update_sporting_master_track($dmMaster, $master_id);
}

function update_sporting_master_track($dmMaster, $rec_mID)
{
    $db2 = Database::obtain();
    //$dmMaster = new MasterDataMembers();

    $data['sb_race_url'] = $dmMaster->sb_race_url;

    $where = ' master_id='.$rec_mID;

    $resultData = $db2->update(TABLE_MASTER, $data, $where);
}



////////////////////////////////////////////////////////////////////////////////////////
/////////////////////////////BETFAIR.COM.AU/////////////////////////////////////////////
////////////////////////////////////////////////////////////////////////////////////////

function scrape_daily_race_data_from_betfair($url)
{
    $siteContent = returnXPathObject(Get_Curl($url));
    $blockToScrape = getBF_block_to_scrape($siteContent);
    //check if there is any block to scrape
    //if no block then return false
    if ($blockToScrape == false)
    {
        LogMessage("scrape_daily_race_data_from_betfair : No Betfair track found to scrape. Failed at level 1");
        return false;
    }

    $eachTrack = $siteContent->query('.//div[contains(@class, "races-list has-racenumbers ")]', $blockToScrape);

    //define('qEachTracksInBlock', './/div[@class="block-inner block-betting block-racecard block-grid"]/table/tbody/tr');
    //$eachTrack = $siteContent->query();

    for ($i = 0; $i < $eachTrack->length; $i++) {
        getBF_timings_on_each_track($siteContent, $eachTrack->item($i));
    }
}
//venue-event-list event-list-container0AU ui-expanded

function getBF_block_to_scrape($fnSiteContent)
{
    $eleCollection = $fnSiteContent->query('//div[@class="venue-event-list event-list-container0AU ui-expanded"]');

    if ($eleCollection->length > 0){
        return $eleCollection->item(0);
    }

    return false;
}


function getBF_timings_on_each_track($fnSiteContent, $track)
{
    //Get track name
    $eachRace = $fnSiteContent->query('.//div[@class="track-venue-name"]', $track);
    $traceName = trim($eachRace->item(0)->nodeValue);

    LogMessage("getBF_timings_on_each_track : Betfair Scraping for track ".$traceName);

    //get race details
    //$eachRace = $fnSiteContent->query('.//div[@class="single-race hov-me"]', $track);
    $eachRace = $fnSiteContent->query('.//div[contains(@class, "single-race")]', $track);

    //get detail for each timing
    //index start with 0 as track name is srtored in different Div.Class_Name
    for ($i = 0; $i < $eachRace->length; $i++) {
        if ($eachRace->item($i)->attributes->item(0)->value != 'single-race hov-me'){
            continue;
        }
        $dmMaster = new MasterDataMembers();
        $dmMaster->race_name = $traceName;
        $master_id = check_track_race_no_exist($traceName, ($i+1));

        if (strlen(trim($master_id))>0){
            $race = $fnSiteContent->query('.//a', $eachRace->item($i));
            $dmMaster->sb_race_url = scrape_market_id($race->item(0)->attributes->item(3)->value);
        }
        else{
            $dmMaster->race_no = $i;
            $dmMaster->race_cell_value = trim($eachRace->item($i)->nodeValue);
            $dmMaster->race_status = "";  //trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $eachRace->item($i)->nodeValue));

            //print_r($dmMaster->race_status.";".$dmMaster->race_name.";".'cell value'.";" .'date'.";".'time'.";".'url'.";"." <br> \n");
        }
        if (trim($dmMaster->sb_race_url) !='') save_BF_track_master($dmMaster, $master_id);
    }
}

function save_BF_track_master($dmMaster, $master_id)
{
    //Update Record
    update_BF_master_track($dmMaster, $master_id);
}

function update_BF_master_track($dmMaster, $rec_mID)
{
    $db2 = Database::obtain();
    //$dmMaster = new MasterDataMembers();

    $data['bf_race_url'] = $dmMaster->sb_race_url;

    $where = ' master_id='.$rec_mID;

    $resultData = $db2->update(TABLE_MASTER, $data, $where);
}

function scrape_market_id($scraped_url)
{
    $strPos2 = strrpos($scraped_url,"/");
    $temp = substr($scraped_url, $strPos2+1);

    $strCount = substr_count($temp, ".");

    if ($strCount == 0){
        LogMessage(scrape_market_id." : ".$scraped_url." is not valid market Id.");
        return false;
    }elseif($strCount == 1){
        return $temp;
    }elseif($strCount>1){
        $strPos1 = strrpos($temp,".");
        $strPos1 = strrpos($temp,".",(-1* (strlen($temp) - $strPos1)-1));

        return substr($temp, $strPos1+1);
    }
    return false;
}

function translate_track($track) {
    $track_translate  = array(
        'Sportingbet Pk'      => 'Sandown',
        'Sportingbet Pk (H)'  => 'Sandown',
        'Pt Augusta'  => 'Port Augusta',
        'Pt Augusta'  => 'P Aug'
    );

    if (array_key_exists($track, $track_translate)) {
        return $track_translate[$track];
    } else {
        return $track;
    }
}

$objDB->close();
