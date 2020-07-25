<?php

ini_set('MAX_EXECUTION_TIME', 0);

$script_path = "/home/moneyinmotioncom/public_html/cron_jobs/logs";

//only show the error if in debug mode
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', $script_path . 'runner_errors.txt');

include "setting.php";
include "MasterDataMembers.php";
include "RunnerListDataMamber.php";
require("Database.singleton.php");

date_default_timezone_set('Australia/Queensland');

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
    //scrape all values on daily basis
    scrape_race_runners(false);
}

function ConvertStringToDate($dateString)
{
    $d = new DateTime($dateString);
    $formatted_date = $d->format('Y-m-d H:i:s');

    return $formatted_date;
}

function LogMessage($message)
{
    print_r( "<br>\n\t".date('Y-m-d H:i:s', time()).": ".$message."\n");
	
	$log_file = "/home/moneyinmotioncom/public_html/cron_jobs/logs/runner_scrape_log.txt";
	
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
    LogMessage("URL requested : ".$url);

    $ch = curl_init();  // Initialising cURL
    curl_setopt($ch, CURLOPT_URL, $url);    // Setting cURL's URL option with the $url variable passed into the function
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, True); // Setting cURL's option to return the webpage data
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, True);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

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

    print_r(count($listOfRace));

    // print out array later on when we need the info on the page
    foreach($listOfRace as $race){
        scrape_race_runner_detail($race);
    }

}

function get_race_list_to_scrape($is_first_scrape_of_day)
{
    $db2 = Database::obtain();

    $sSql = 'Select master_id, tatts_track_name, tatts_short_track_name,
            sb_track_name, bf_track_name, race_no, race_time,
            tatts_race_url,sb_race_url, bf_race_url, race_status,
            next_scrape_time,  race_slab_no
    From trackracemaster
    Where next_scrape_time < "'.date('Y-m-d H:i:s', time()).'"
        and (race_status in ("Open", "Closed", "Interim") or left( race_status, 5 ) = "PHOTO") ';

    //LogMessage($sSql);

    //and master_id in (1, 2, 8, 16)

    if($is_first_scrape_of_day){
        $sSql .= ' and last_scrape_time is null';
    }else{
        $sSql .= ' and last_scrape_time is not null';
    }

    //LogMessage($sSql);
    // feed it the sql directly. store all returned rows in an array
    return $db2->fetch_array($sSql);
}

function scrape_race_runner_detail($race)
{
    LogMessage("Start Scraping Tatts.");
    $sUrl = $race['tatts_race_url'];
    $master_id = $race['master_id'];

    $fnSiteContent = scrape_race_runner_detail_tatts($sUrl, $master_id, $race);
    scrape_race_runner_master_update($fnSiteContent, $master_id, $race);

    LogMessage("Start Scraping SporttingBet");
    $sUrl = $race['sb_race_url'];
    scrape_sporting_runner_details($sUrl, $master_id, $race["race_slab_no"], $race["race_no"]);

    LogMessage("Start Scraping BetFair");
    $sUrl = $race['bf_race_url'];
    if (strlen(trim($sUrl))>0){
        scrape_bf_runner_details($sUrl, $master_id, $race["race_slab_no"], $race["race_no"]);
    }

}

function scrape_race_runner_detail_tatts($url, $master_id, $race)
{
    $siteContent = returnXPathObject(Get_Curl($url));
    $raceSlabNo = $race["race_slab_no"];
    $tatts_status = get_status_of_race($siteContent, $race["tatts_short_track_name"], $race["race_no"]);

    if ($tatts_status!="Open") return $siteContent;

    //no need to scrape if intrim or closed
    if ($raceSlabNo==0) return $siteContent;

    //LogMessage("Scraping started for runners details from tatts.");

    $scrapeAllRunner = $siteContent->query('//*[@id="contentPane"]/div[3]/table[1]/tr/td/table/tr[2]/td[2]/table/tr');

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
        $winValue = (float)$winNode->item(0)->nodeValue;
    }else{
        $winValue = (float)$winNode->nodeValue;
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

function get_status_of_race($fnSiteContent, $shortName, $trackNo)
{
    $scrapeStatus = $fnSiteContent->query('//*[@id="'.$shortName.$trackNo.'"]/span/span');

    if($scrapeStatus->length >1)
    {
        $raceStatus= $scrapeStatus->item(1)->nodeValue;
    }else{
        $scrapeStatus = $fnSiteContent->query('//*[@id="contentPane"]/table[1]/tr/td/table/tr/td');
        if ($scrapeStatus->length>0){
            $raceStatus= $scrapeStatus->item(3)->nodeValue;
        }else{
            $raceStatus= "";
        }
    }

    $temp = date_parse($raceStatus);

    if ((strlen(trim($raceStatus))==0) || ($temp["year"])) $raceStatus = "Open";

    return $raceStatus;
}


function scrape_race_runner_master_update($fnSiteContent, $master_id, $race)
{
    $shortName = $race["tatts_short_track_name"];
    $trackNo = $race["race_no"];
    $raceTime = $race["race_time"];

    $raceStatus=get_status_of_race($fnSiteContent, $shortName, $trackNo);

    $minToNextScrape = get_mins_to_next_scrape(date('Y-m-d H:i:s', time()), $raceTime);

    if ($raceStatus != ""){
        if ($minToNextScrape[1]==0){
            $date = new DateTime(date('Y-m-d H:i:s', time()));
            $date->modify("+{$minToNextScrape[0]} minutes");
        }else{
            $date = new DateTime($raceTime);
            //LogMessage("Got Slab 011 --- ".$date->format('Y-m-d H:i:s')."--");
            $date->modify("-{$minToNextScrape[0]} minutes");
        }

        $next_scrape_time = $date; //->format('Y-m-d H:i:s');   //strtotime('-'.($minToNextScrape[0]).' minutes', $raceTime);
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
    $minToRaceStart = floor(get_date_diff_in_minutes($current_time, $raceTime));
    $minToNextScrape = $minToRaceStart - DelayInMinForNextScrape;

    if (($minToNextScrape<=20)&&($minToRaceStart>20)){
        $race_slab = 6;
        $minToNextScrape = 20;
    }elseif($minToNextScrape>20){
        $race_slab = 7;
        //$minToNextScrape = 20;
    }elseif (($minToNextScrape<=15)&&($minToRaceStart>15)){
        $race_slab = 5;
        $minToNextScrape = 15;
    }elseif (($minToNextScrape>=15)&&($minToNextScrape<20)){
        $race_slab = 5;
        //$minToNextScrape = 15;
    }elseif (($minToNextScrape<=10)&&($minToRaceStart>10)){
        $race_slab = 4;
        $minToNextScrape = 10;
    }elseif (($minToNextScrape>=10)&&($minToNextScrape<15)){
        $race_slab = 4;
        //$minToNextScrape = 15;
    }elseif (($minToNextScrape<=5)&&($minToRaceStart>5)){
        $race_slab = 3;
        $minToNextScrape = 5;
    }elseif (($minToNextScrape>=5)&&($minToNextScrape<10)){
        $race_slab = 3;
        //$minToNextScrape = 15;
    }elseif (($minToNextScrape<=3)&&($minToRaceStart>3)){
        $race_slab = 2;
        $minToNextScrape = 3;
    }elseif (($minToNextScrape>=3)&&($minToNextScrape<5)){
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
    //$next_scrape_time = ConvertStringToDate($next_scrape_time);

    $sSql = "update ".TABLE_MASTER.
            " Set race_slab_no = ".$race_slab. ",
                  last_scrape_time = next_scrape_time,
                  next_scrape_time = '".$next_scrape_time->format('Y-m-d H:i:s')."',
                  race_status = '".$raceStatus."'
              where   master_id = ".$master_id;

    //LogMessage($sSql);

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

    if ($raceSlab==6){
        $slabUpdate = ", tatts_runr_win_20 = ".$runner_details->runr_fix_win;
    }elseif($raceSlab==5){
        $slabUpdate = ", tatts_runr_win_15 = ".$runner_details->runr_fix_win;
    }elseif($raceSlab==4){
        $slabUpdate = ", tatts_runr_win_10 = ".$runner_details->runr_fix_win;
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
                    runr_2last_win_value = runr_fix_win,
                    runr_status = '".$runner_details->runr_status."' ".$slabUpdate."
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


///////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////

function scrape_sporting_runner_details($url, $master_id, $slab_no, $race_no)
{
    $siteContent = returnXPathObject(Get_Curl($url));

    $eachRunner = $siteContent->query('//div[@class="row "]');

    LogMessage("Sportting Bet Found ".$eachRunner->length." runners.");

    for ($i = 0; $i < $eachRunner->length; $i++) {
        get_runner_details_for_each_track($siteContent, $eachRunner->item($i), $race_no, $master_id, $slab_no);
    }
}

function get_runner_details_for_each_track($fnSiteContent, $eachRunner, $runner_no, $master_id, $slab_no)
{
    $tempObj = $fnSiteContent->query('.//div[2]/strong', $eachRunner);
    if ($tempObj->length>0){
        $runner_name = $tempObj->item(0)->nodeValue;
    }else{
        $runner_name = trim($fnSiteContent->query('.//div[2]', $eachRunner)->item(0)->nodeValue);
    }
    $runner_id = check_track_runner_no_exists($runner_name, $runner_no, $master_id);

    LogMessage("Runner Name: ".$runner_name.", Runner ID:".$runner_id."--");

    $tempObj = $fnSiteContent->query('.//div[3]/ul/li', $eachRunner);
    if ($tempObj->length>0){
        $runner_win_value = (float)$tempObj->item(0)->nodeValue;
    }else{
        $runner_win_value=0;
    }

    if (strlen(trim($runner_id))>0)
    {
        update_runner_sb_data($runner_win_value, $runner_id, $slab_no);
    }
}

function check_track_runner_no_exists($runner_name, $runner_no, $master_id){
    $sSql = "Select * from runner_details a
            where  master_id = ".$master_id."
                and REPLACE(Upper(trim(runr_name)), '\'', '') = Upper(trim('".str_replace("'", "", $runner_name)."'))";
                //and runr_no = ".($runner_no);

    //LogMessage($sSql);
    $db2 = Database::obtain();

    $runner_list =  $db2->fetch_array($sSql);

    if(count($runner_list)==0){
        return "";
    }else{
        return $runner_list[0]["runner_id"];
    }
}

function update_runner_sb_data($sb_win_value, $runner_id, $raceSlab)
{
    $db2 = Database::obtain();
    $slabUpdate="";
    LogMessage("For Runner ID :".$runner_id."--Slab No : ".$raceSlab."--");

    if ($raceSlab==6){
        $slabUpdate = ", sb_runr_win_20 = ".$sb_win_value;
    }elseif($raceSlab==5){
        $slabUpdate = ", sb_runr_win_15 = ".$sb_win_value;
    }elseif($raceSlab==4){
        $slabUpdate = ", sb_runr_win_10 = ".$sb_win_value;
    }elseif($raceSlab==3){
        $slabUpdate = ", sb_runr_win_5 = ".$sb_win_value;
    }elseif($raceSlab==2){
        $slabUpdate = ", sb_runr_win_2 = ".$sb_win_value;
    }

    //update runner_details Set sb_fix_win = 3.6,   where   runner_id = 103

    $sSql = "update ".TABLE_RUNNER." Set sb_fix_win = ".$sb_win_value. " ".$slabUpdate."  where   runner_id = ".$runner_id;

    $row = $db2->query($sSql);

    if($db2->affected_rows > 0){
        LogMessage("Number new forum inserted ". $db2->affected_rows);
    }
}


///////////////////////////////////////////////////////////////////////////////////////////
///////////////////////////< BETFAIR.COM.AU >//////////////////////////////////////////////
///////////////////////////////////////////////////////////////////////////////////////////
function get_bf_page_content($marketID)
{
    $strUrl = 'https://www.betfair.com.au/aus/www/sports/exchange/readonly/v1/bymarket?currencyCode=AUD&alt=json&locale=en_GB&types=MARKET_STATE%2CMARKET_RATES%2CMARKET_DESCRIPTION%2CEVENT%2CRUNNER_DESCRIPTION%2CRUNNER_STATE%2CRUNNER_EXCHANGE_PRICES_BEST%2CRUNNER_METADATA%2CRUNNER_SP&virtualise=true&minimumStake=2&rollupModel=STAKE&marketIds='.$marketID;
    $TopkeyHtml = file_get_contents($strUrl);

    return json_decode($TopkeyHtml);
}

function parse_runner_name($runner_name)
{
    return trim(substr($runner_name, strpos($runner_name, ".")+1));
}

function read_json_response($response)
{
    $is=0;

    foreach($response->eventTypes as $event_result)
    {
        foreach($event_result->eventNodes as $note_result)
        {
            $is++;
            foreach($note_result->marketNodes as $mark_result)
            {
                $ru=0;
                foreach($mark_result->runners as $runners_result)
                {
                    $ru++;
                    $runner_name = parse_runner_name($runners_result->description->runnerName);
                    $event_arrays[$ru]['name']=$runner_name;

                    if(!empty($runners_result->exchange->availableToBack))
                    {
                        foreach($runners_result->exchange->availableToBack as $back_resul)
                        {
                            $price=$back_resul->price;
                            $size=$back_resul->size;

                            $event_arrays[$ru]['back_price']=$price;
                            $event_arrays[$ru]['back_size']=$size;

                            break;
                        }
                    }

                    if(!empty($runners_result->exchange->availableToLay))
                    {
                        foreach($runners_result->exchange->availableToLay as $lay_resul)
                        {
                            $lay_price=$lay_resul->price;
                            $lay_size=$lay_resul->size;

                            $event_arrays[$ru]['lay_price']=$lay_price;
                            $event_arrays[$ru]['lay_size']=$lay_size;

                            break;
                        }
                    }
                }
            }
        }
    }

    return $event_arrays;
}

function scrape_bf_runner_details($marketID, $master_id, $slab_no, $race_no)
{
    $parsed_response = read_json_response(get_bf_page_content($marketID));

    if (count($parsed_response)<=0){
        LogMessage("no runner found for master id : ".$master_id);
    }

    foreach($parsed_response as $runner)
    {
        get_bf_runner_details_for_each_track($runner, $race_no, $master_id, $slab_no);
    }

    LogMessage("Scraping for Bet-fair is completed.");
}

function get_bf_runner_details_for_each_track($runner_details, $runner_no, $master_id, $slab_no)
{
    $runner_id = check_track_runner_no_exists($runner_details['name'], $runner_no, $master_id);

    LogMessage("Runner Name: ".$runner_details['name'].", Runner ID:".$runner_id."--");

    if (strlen(trim($runner_id))>0){
        if(!empty($runner_details['back_price']))
        {
            $back_price = $runner_details['back_price'];
        }else{
            $back_price =0;
        }

        if (!empty($runner_details['lay_price']))
        {
            $ly_price = trim($runner_details['lay_price']);
        } else {
            $ly_price = 0;
        }

        update_runner_bf_data($back_price, $ly_price, $runner_id, $slab_no);
    }
}

function update_runner_bf_data($bf_win_value_b, $bf_win_value_L, $runner_id, $raceSlab)
{
    $db2 = Database::obtain();
    $slabUpdate="";
    LogMessage("For Runner ID :".$runner_id."--Slab No : ".$raceSlab."--");

    if ($raceSlab==6){
        $slabUpdate = ", bf_runr_win_20_b = ".$bf_win_value_b.", bf_runr_win_20_L = ".$bf_win_value_L;
    }elseif($raceSlab==5){
        $slabUpdate = ", bf_runr_win_15_b = ".$bf_win_value_b.", bf_runr_win_15_L = ".$bf_win_value_L;
    }elseif($raceSlab==4){
        $slabUpdate = ", bf_runr_win_10_b = ".$bf_win_value_b.", bf_runr_win_10_L = ".$bf_win_value_L;
    }elseif($raceSlab==3){
        $slabUpdate = ", bf_runr_win_5_b = ".$bf_win_value_b.", bf_runr_win_5_L = ".$bf_win_value_L;
    }elseif($raceSlab==2){
        $slabUpdate = ", bf_runr_win_2_b = ".$bf_win_value_b.", bf_runr_win_2_L = ".$bf_win_value_L;
    }


    //update runner_details Set sb_fix_win = 3.6,   where   runner_id = 103

    $sSql = "update ".TABLE_RUNNER." Set bf_fix_win_b = ".$bf_win_value_b. ", bf_fix_win_L = ".$bf_win_value_L. " ".$slabUpdate."  where   runner_id = ".$runner_id;

    $row = $db2->query($sSql);

    if($db2->affected_rows > 0){
        LogMessage("Number new forum inserted ". $db2->affected_rows);
    }
}

$objDB->close();
