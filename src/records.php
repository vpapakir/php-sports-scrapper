<?php
//only show the error if in debug mode
error_reporting(E_ALL);
ini_set('display_errors', '1');

include "setting.php";
include "MasterDataMembers.php";
include "RunnerListDataMamber.php";
require("Database.singleton.php");

date_default_timezone_set('Australia/Queensland');

// create the $db singleton object
$objDB = Database::obtain(DB_SERVER, DB_USER, DB_PASS, DB_DATABASE);
$objDB->connect();

main();

/////////////////////////////////////////////////////////////////////
/*SELECT trackracemaster.`master_id` , `tatts_track_name` , `tatts_short_track_name` , `race_time` , `race_status` , `next_scrape_time` , `runr_name` , `runr_status` , `runr_fix_win` , `tatts_runr_win_20` , `tatts_runr_win_15` , `tatts_runr_win_10` , `tatts_runr_win_5` , `tatts_runr_win_2`
FROM `trackracemaster` , runner_details
WHERE trackracemaster.master_id = runner_details.master_id
LIMIT 0 , 30*/

function main()
{
    show_records();
}

function show_records()
{
    $master_id = $_GET['m'];
    if (strlen(trim($master_id))==0) return;

    $listOfRace = get_races($master_id);
    $htmlContent = "";
    $isfirstRow = true;

    print_r(count($listOfRace));

    $htmlContent = $htmlContent."<html> <title>Test Screen - To Display scraped values</title>";
    $htmlContent = $htmlContent."<body>";
    // print out array later on when we need the info on the page
    foreach($listOfRace as $race){
        if($isfirstRow)
        {
            $htmlContent = $htmlContent."<center><table>";
            $htmlContent = $htmlContent."<tr>";
            $htmlContent = $htmlContent."<td>Current Time</td>";
            $htmlContent = $htmlContent."<td>".date('Y-m-d H:i:s', time())."</td>";
            $htmlContent = $htmlContent."</tr>";

            $htmlContent = $htmlContent."<tr>";
            $htmlContent = $htmlContent."<td>Race</td>";
            $htmlContent = $htmlContent."<td>".$race["tatts_track_name"]."</td>";
            $htmlContent = $htmlContent."</tr>";
            $htmlContent = $htmlContent."<tr>";
            $htmlContent = $htmlContent."<td>Time</td>";
            $htmlContent = $htmlContent."<td>".$race["race_time"]."</td>";
            $htmlContent = $htmlContent."</tr>";
            $htmlContent = $htmlContent."<tr>";
            $htmlContent = $htmlContent."<td>race_status</td>";
            $htmlContent = $htmlContent."<td>".$race["race_status"]."</td>";
            $htmlContent = $htmlContent."</tr>";
            $htmlContent = $htmlContent."<tr>";
            $htmlContent = $htmlContent."<td>Mins Left</td>";
            $htmlContent = $htmlContent."<td>".get_date_diff_in_minutes(date('Y-m-d H:i:s', time()),$race["race_time"])."</td>";
            $htmlContent = $htmlContent."</tr>";
            $htmlContent = $htmlContent."<tr>";
            $htmlContent = $htmlContent."<td>Next Scrape Time</td>";
            $htmlContent = $htmlContent."<td>".$race["next_scrape_time"]."</td>";
            $htmlContent = $htmlContent."</tr>";
            $htmlContent = $htmlContent."<tr>";
            $htmlContent = $htmlContent."<td>Time Left To Scrape</td>";
            $htmlContent = $htmlContent."<td>".get_date_diff_in_minutes(date('Y-m-d H:i:s', time()),$race["next_scrape_time"])."</td>";
            $htmlContent = $htmlContent."</tr>";
            $htmlContent = $htmlContent."<tr><td colspan=2><table border=2>";

            $htmlContent = $htmlContent."<tr>";
            $htmlContent = $htmlContent."<td>R_Name</td>";
            $htmlContent = $htmlContent."<td>Status</td>";
            $htmlContent = $htmlContent."<td>tatts_Curr</td>";
            $htmlContent = $htmlContent."<td>tatts_21</td>";
            $htmlContent = $htmlContent."<td>tatts_20</td>";
            $htmlContent = $htmlContent."<td>tatts_19</td>";
            $htmlContent = $htmlContent."<td>tatts_4</td>";
            $htmlContent = $htmlContent."<td>tatts_3</td>";
            $htmlContent = $htmlContent."<td>tatts_2</td>";
			
            $htmlContent = $htmlContent."<td>bf_Curr_bk</td>";
            $htmlContent = $htmlContent."<td>bf_Curr_Ly</td>";
            $htmlContent = $htmlContent."<td>bf_11_bk</td>";
            $htmlContent = $htmlContent."<td>bf_10_Ly</td>";
            $htmlContent = $htmlContent."<td>bf_6_bk</td>";
            $htmlContent = $htmlContent."<td>bf_5_Ly</td>";
			$htmlContent = $htmlContent."<td>bf_3_bk</td>";
            $htmlContent = $htmlContent."<td>bf_2_Ly</td>";
            $htmlContent = $htmlContent."</tr>";
            $isfirstRow = false;
        }

        $htmlContent = $htmlContent."<tr>";
        $htmlContent = $htmlContent."<td>".$race["runr_name"]."</td>";
        $htmlContent = $htmlContent."<td>".substr($race["runr_status"],0,1)."</td>";

        $htmlContent = $htmlContent."<td>".$race["runr_fix_win"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["tatts_runr_win_21"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["tatts_runr_win_20"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["tatts_runr_win_19"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["tatts_runr_win_4"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["tatts_runr_win_3"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["tatts_runr_win_2"]."</td>";

  
        $htmlContent = $htmlContent."<td>".$race["bf_fix_win_b"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["bf_fix_win_L"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["bf_runr_win_11_b"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["bf_runr_win_9_b"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["bf_runr_win_7_b"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["bf_runr_win_4_L"]."</td>";
		$htmlContent = $htmlContent."<td>".$race["bf_runr_win_3_b"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["bf_runr_win_2_L"]."</td>";

        $htmlContent = $htmlContent."</tr>";
    }

    $htmlContent = $htmlContent."</table></td></tr></table></center>";
    $htmlContent = $htmlContent."</body></html>";

    echo $htmlContent;
}

function get_races($master)
{
    $db2 = Database::obtain();

    $sSql = "SELECT * FROM `trackracemaster` , runner_details
            WHERE trackracemaster.master_id = runner_details.master_id
                  and trackracemaster.master_id = ".$master;

    // feed it the sql directly. store all returned rows in an array
    return $db2->fetch_array($sSql);
}

function get_date_diff_in_minutes($fromDate, $toDate)
{
    $to_time = strtotime($toDate);
    $from_time = strtotime($fromDate);

    return round(($to_time - $from_time) / 60,2);
}