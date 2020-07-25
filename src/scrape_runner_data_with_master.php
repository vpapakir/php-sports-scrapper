<?php

ini_set('MAX_EXECUTION_TIME', 0);

$script_path = "/home/moneyinmotioncom/www/cron_jobs/logs";

//only show the error if in debug mode
error_reporting(-1);
ini_set('log_errors', 1);
ini_set('error_log', $script_path . 'runner_master_errors.txt');

include "setting.php";
include "MasterDataMembers.php";
include "RunnerListDataMamber.php";
require("Database.singleton.php");

date_default_timezone_set('Australia/Queensland');

/////////////////////////////////////////////////////////////////////
/////////////////////Testing////////////////////////////////////////


//print_r($temp);



//exit();

////////////////////////////////////////////////////////////////////

// create the $db singleton object
$objDB = Database::obtain(DB_SERVER, DB_USER, DB_PASS, DB_DATABASE);
$objDB->connect();

////////////////////////////////////////////////////////////////////
//////////All processing starts from main///////////////////////////
////////////////////////////////////////////////////////////////////

main2();

/////////////////////////////////////////////////////////////////////

function main2()
{
    scrape_race_runners(true);
}

function ConvertStringToDate($dateString)
{
    $d = new DateTime($dateString);
    $formatted_date = $d->format('Y-m-d H:i:s');

    return $formatted_date;
}

function LogMessage($message)
{
    print_r( "\t".date('Y-m-d H:i:s', time()).": ".$message."\n");

    save_log($message);
}


function save_log($message) {
    $log_file = "/home/moneyinmotioncom/www/cron_jobs/logs/runner_scrape_mst_log.txt";
	
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
        echo 'Curl error: ' . curl_error($ch);
    }
    else
    {
        //print_r(curl_getinfo($ch));
    }
    curl_close($ch);    // Closing cURL
    return $data;   // Returning html content of site
}

function scrape_race_runners($first_start)
{
    $listOfRace = get_race_list_to_scrape($first_start);

    LogMessage("scrape_race_runners : Total races to scrape - ".count($listOfRace));

    // print out array later on when we need the info on the page
    foreach($listOfRace as $race){
        LogMessage("scrape_race_runners : Start scpaing for track:".$race['tatts_track_name']."Master ID: ".$race['master_id']);
        scrape_race_runner_detail($race);
        LogMessage("scrape_race_runners : Completed scpaing for track:".$race['tatts_track_name']."Master ID: ".$race['master_id']);
    }
    LogMessage("scrape_race_runners : Scraping cycle completed");
}

function get_race_list_to_scrape($is_first_scrape_of_day)
{
    $date = new DateTime(date('Y-m-d H:i:s', time()));
    $date_str = $date->format("Y")."-".$date->format("m")."-".$date->format("d");

    $db2 = Database::obtain();

    $sSql = 'Select master_id, tatts_track_name, tatts_short_track_name,
            sb_track_name, bf_track_name, race_no, race_time,
            tatts_race_url,sb_race_url, bf_race_url, race_status,
            next_scrape_time,  race_slab_no
    From trackracemaster
    Where date(race_time) = "'.$date_str.'" ';

    //and master_id in (1, 2, 8, 16)

    if($is_first_scrape_of_day){
        $sSql .= ' and last_scrape_time is null';
    }else{
        $sSql .= ' and last_scrape_time is not null';
    }

    // feed it the sql directly. store all returned rows in an array
    return $db2->fetch_array($sSql);
}

function scrape_race_runner_detail($race)
{
    $sUrl = $race['tatts_race_url'];
    $master_id = $race['master_id'];

    $fnSiteContent = scrape_race_runner_detail_tatts($sUrl, $master_id, $race["race_slab_no"]);

    LogMessage("scrape_race_runner_detail: Updating master record for the race.");
    scrape_race_runner_master_update($fnSiteContent, $master_id, $race);

}

function scrape_race_runner_detail_tatts($url, $master_id, $raceSlabNo)
{
    $siteContent = returnXPathObject(Get_Curl($url));

    $scrapeAllRunner = $siteContent->query('//*[@id="contentPane"]/div[3]/table[1]/tr/td/table/tr[2]/td[2]/table/tr');
    LogMessage("scrape_race_runner_detail_tatts: Total number of runner for the race:".$scrapeAllRunner->length);

    foreach($scrapeAllRunner as $runner){
        $classValue = $runner->getAttribute("class");
        $idValue = $runner->getAttribute("id");
        if ((strlen(trim($classValue))==0)&&(strlen(trim($idValue))==0)){
            $runner_details = scrape_race_runner_detail_tatts_active($siteContent, $runner, $master_id);
        }elseif ($classValue == 'Scratched-Runner'){
            $runner_details = scrape_race_runner_detail_tatts_scratched($siteContent, $runner, $master_id);
        }else{
            $runner_details = false;
        }
        if ($runner_details != false)
        {
            save_tatts_runner_data($runner_details, $raceSlabNo);
        }
    }
    return $siteContent;
}

function scrape_race_runner_detail_tatts_active($fnSiteContent, $runner, $master_id)
{
    $runner_member = new RunnerListDataMamber();

    $tdCollection = $fnSiteContent->query('.//td',$runner);

    //td2 for seq no
    $runner_member->runr_no = $tdCollection->item(1)->nodeValue;

    //td3 for runner name
    $runner_member->runr_name = $tdCollection->item(2)->nodeValue;
    //$runner_member->runr_fix_win = $fnSiteContent->document->getElementById(trim($runner_member->runr_no)."_betOnWinFixedPrice")->nodeValue;
    $runner_member->runr_fix_win = get_win_value($fnSiteContent, $runner_member->runr_no);
    $runner_member->runr_status = 'Active';
    $runner_member->master_id = $master_id;

    //print_r($runner_member);

    return $runner_member;
}

function get_win_value($fnSiteContent, $runer_no)
{
    $winNode = $fnSiteContent->document->getElementById(trim($runer_no)."_betOnWinFixedPrice");
    if (is_null($winNode))
    {
        $winNode = $fnSiteContent->query('//*[@id="'.trim($runer_no).'_fixedOdds"]/table/tr/td[2]');
        $winValue = (int)($winNode->item(0)->nodeValue);
    }else{
        $winValue = (int)($winNode->nodeValue);
    }

    return $winValue;
}

function scrape_race_runner_detail_tatts_scratched($fnSiteContent, $runner, $master_id)
{
    $runner_member = new RunnerListDataMamber();

    $tdCollection = $fnSiteContent->query('.//td',$runner);
    //foreach($tdCollection as $tdElement){
    //$tempValue = $tdElement->nodeValue;

    //td2 for seq no
    $runner_member->runr_no = $tdCollection->item(1)->nodeValue;

    //td3 for runner name
    $runner_member->runr_name = $tdCollection->item(2)->nodeValue;
    $runner_member->runr_fix_win = 'NULL';
    $runner_member->runr_status = 'Scratched';
    $runner_member->master_id = $master_id;

    ///print_r($runner_member);

    return $runner_member;
    //}
}

function save_tatts_runner_data($runner_details, $raceSlabNo)
{
    $db2 = Database::obtain();
    $sSql = "Select runner_id, runr_status from ".TABLE_RUNNER." where master_id = ".$runner_details->master_id." and
                                                            runr_no = ".$runner_details->runr_no;
    $record = $db2->query_first($sSql);
    $rec_mID =  $record['runner_id'];

    if ($rec_mID > 0){
        if ($record['runr_status']=="Scratched") return false;
        //Update Record
        update_runner_data($runner_details, $rec_mID, $raceSlabNo);
    }else{
        //Insert Record
        insert_runner_data($runner_details);
    }
    return true;
}


function scrape_race_runner_master_update($fnSiteContent, $master_id, $race)
{
    $shortName = $race["tatts_short_track_name"];
    $trackNo = $race["race_no"];
    $raceTime = $race["race_time"];

    $scrapeStatus = $fnSiteContent->query('//*[@id="'.$shortName.$trackNo.'"]/span/span');
    $raceStatus="";

    if($scrapeStatus->length >1)
    {
        $raceStatus = $scrapeStatus->item(1)->nodeValue;
    }else{
        $scrapeStatus = $fnSiteContent->query('//*[@id="contentPane"]/table[1]/tr/td/table/tr/td');
        if ($scrapeStatus->length>0){
            $raceStatus = $scrapeStatus->item(3)->nodeValue;
        }else{
            LogMessage("scrape_race_runner_master_update: Error: No status Found;");
        }
    }
    $temp = date_parse($raceStatus);
    if ((strlen(trim($raceStatus))==0) || ($temp["year"])) $raceStatus = "Open";

    $minToNextScrape = get_mins_to_next_scrape(date('Y-m-d H:i:s', time()), $raceTime);

    if ($raceStatus != ""){
        if ($minToNextScrape[1]==0){
            $date = new DateTime(date('Y-m-d H:i:s', time()));
            //$date->add(new DateInterval('PT'.round($minToNextScrape[0]).'M'));
            $date->modify ("+{$minToNextScrape[0]} minutes");
        }else{
            $date = new DateTime($raceTime);
            //$date->sub(new DateInterval('PT'.round($minToNextScrape[0]).'M'));
            $date->modify ("-{$minToNextScrape[0]} minutes");
        }

        $next_scrape_time = $date;   //->format('Y-m-d H:i:s');   //strtotime('-'.($minToNextScrape[0]).' minutes', $raceTime);
        update_master_status($master_id, $raceStatus, $next_scrape_time, $minToNextScrape[1]);
    }
}

function isDate($i_sDate) {
    $blnValid = TRUE;

    if ( $i_sDate == "00/00/0000" ) { return $blnValid; }

    // check the format first (may not be necessary as we use checkdate() below)
    if(!preg_match("^[0-9]{2}/[0-9]{2}/[0-9]{4}$", $i_sDate)) {
        $blnValid = FALSE;
    } else {
        //format is okay, check that days, months, years are okay
        $arrDate = explode("/", $i_sDate); // break up date by slash
        $intMonth = $arrDate[0];
        $intDay = $arrDate[1];
        $intYear = $arrDate[2];

        $intIsDate = checkdate($intMonth, $intDay, $intYear);

        if(!$intIsDate) {
            $blnValid = FALSE;
        }
    }//end else

    return ($blnValid);
} //end function isDate


function get_mins_to_next_scrape($current_time, $raceTime)
{
    $minToRaceStart = round(get_date_diff_in_minutes($current_time, $raceTime));
    $minToNextScrape = $minToRaceStart - DelayInMinForNextScrape;

    $minToNextScrape = floor($minToNextScrape/5)*5;

         if (($minToNextScrape<=21)&&($minToRaceStart>21)){
        $race_slab = 6;
        $minToNextScrape = 21;
    }elseif($minToNextScrape>21){
        $race_slab = 7;
        //$minToNextScrape = 20;
    }elseif (($minToNextScrape<=19)&&($minToRaceStart>19)){
        $race_slab = 5;
        $minToNextScrape = 19;
    }elseif (($minToNextScrape>=19)&&($minToNextScrape<21)){
        $race_slab = 5;
        //$minToNextScrape = 15;
    }elseif (($minToNextScrape<=4)&&($minToRaceStart>4)){
        $race_slab = 4;
        $minToNextScrape =5;
    }elseif (($minToNextScrape>=4)&&($minToNextScrape< 19 )){
        $race_slab = 4;
        //$minToNextScrape = 15;
    }elseif (($minToNextScrape<=3)&&($minToRaceStart>3)){
        $race_slab = 3;
        $minToNextScrape = 4; 
    }elseif (($minToNextScrape>=3)&&($minToNextScrape<4)){
        $race_slab = 3;
        //$minToNextScrape = 15;
    }elseif (($minToNextScrape<=2)&&($minToRaceStart>2)){
        $race_slab = 2;
        $minToNextScrape = 3;
    }elseif (($minToNextScrape>=2)&&($minToNextScrape<3)){
        $race_slab = 2;
        //$minToNextScrape = 15;
    }elseif (($minToRaceStart<=0)){
        $race_slab = 0;
        $minToNextScrape = 0; // scrape values when ever the script is called
    }else{
        $race_slab = 1;
        $minToNextScrape=0; // scrape values when ever the script is called
    }

    return explode(";", $minToNextScrape.";".$race_slab);
}


function update_master_status($master_id, $raceStatus, $next_scrape_time, $race_slab)
{
    $db2 = Database::obtain();

    $sSql = "update ".TABLE_MASTER.
            " Set race_slab_no = ".$race_slab. ",
                  last_scrape_time = next_scrape_time,
                  next_scrape_time = '".$next_scrape_time->format('Y-m-d H:i:s')."',
                  race_status = '".$raceStatus."'
              where   master_id = ".$master_id;

    $row = $db2->query($sSql);

    if($db2->affected_rows > 0){
        //echo "Number new forum inserted ". $db2->affected_rows;
    }
}

function get_date_diff_in_minutes($fromDate, $toDate)
{
    $to_time = strtotime($toDate);
    $from_time = strtotime($fromDate);

    return round(($to_time - $from_time) / 60,2);
}

function update_runner_data($runner_details, $runner_id, $raceSlab)
{
    $db2 = Database::obtain();
    $slabUpdate="";

    if ($raceSlab==9){
        $slabUpdate = ", tatts_runr_win_20 = ".$runner_details->runr_fix_win;
    }elseif($raceSlab==8){
        $slabUpdate = ", tatts_runr_win_18 = ".$runner_details->runr_fix_win;
    }elseif($raceSlab==7){
        $slabUpdate = ", tatts_runr_win_15 = ".$runner_details->runr_fix_win;
    }elseif($raceSlab==6){
        $slabUpdate = ", tatts_runr_win_13= ".$runner_details->runr_fix_win;
    }elseif($raceSlab==5){
        $slabUpdate = ", tatts_runr_win_10 = ".$runner_details->runr_fix_win;
    }elseif($raceSlab==4){
        $slabUpdate = ", tatts_runr_win_7 = ".$runner_details->runr_fix_win;
    }elseif($raceSlab==3){
        $slabUpdate = ", tatts_runr_win_5 = ".$runner_details->runr_fix_win;
    }elseif($raceSlab==2){
        $slabUpdate = ", tatts_runr_win_2 = ".$runner_details->runr_fix_win;
    }elseif($raceSlab==1){
        $slabUpdate = ", runr_less_then_3 = ".$runner_details->runr_fix_win;
    }

    //$runner_details = new RunnerListDataMamber();
    $sSql = "update ".TABLE_RUNNER.
            " Set runr_fix_win = ".$runner_details->runr_fix_win. ",
                    runr_2last_win_value = runr_fix_win,".$slabUpdate."
                    runr_status = '".$runner_details->runr_status."'
                where   runner_id = ".$runner_id;

    $row = $db2->query($sSql);

    if($db2->affected_rows > 0){
        //echo "Number new forum inserted ". $db2->affected_rows;
    }
}

function insert_runner_data($runner_details)
{
    $db2 = Database::obtain();

    $data['master_id'] = $runner_details->master_id;
    $data['runr_no'] = $runner_details->runr_no;
    $data['runr_name'] = $runner_details->runr_name;
    if ($runner_details->runr_status != 'Scratched'){
        $data['runr_fix_win'] = $runner_details->runr_fix_win;
        $data['runr_2last_win_value'] = $runner_details->runr_fix_win;
    }
    $data['runr_status'] = $runner_details->runr_status;

    $primary_id = $db2->insert(TABLE_RUNNER, $data);

    return $primary_id;
}

$objDB->close();
