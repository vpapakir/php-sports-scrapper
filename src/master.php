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

    $listOfRace = get_races();
    $htmlContent = "";
    $isfirstRow = true;

    print_r(count($listOfRace));

    $htmlContent = $htmlContent."<html> <title>Test Screen - To Display Race List</title>";
    $htmlContent = $htmlContent."<body>";
    // print out array later on when we need the info on the page
    foreach($listOfRace as $race){
        if($isfirstRow)
        {
            $htmlContent = $htmlContent."<br><br><br><center><table border=2>";

            $htmlContent = $htmlContent."<tr>";
            $htmlContent = $htmlContent."<td>Track Name</td>";
            $htmlContent = $htmlContent."<td>Short Name</td>";
            $htmlContent = $htmlContent."<td>Race No</td>";
            $htmlContent = $htmlContent."<td>Race time</td>";
            $htmlContent = $htmlContent."<td>Time Left (mins)</td>";
            $htmlContent = $htmlContent."<td>Race Status</td>";
            $htmlContent = $htmlContent."</tr>";

            $isfirstRow = false;
        }

        $htmlContent = $htmlContent."<tr>";
        $htmlContent = $htmlContent."<td><a href='records.php?m=".$race["master_id"]."'>".$race["tatts_track_name"]."</a></td>";
        $htmlContent = $htmlContent."<td>".$race["tatts_short_track_name"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["race_no"]."</td>";
        $htmlContent = $htmlContent."<td>".$race["race_time"]."</td>";
        $htmlContent = $htmlContent."<td>".get_date_diff_in_minutes(date('Y-m-d H:i:s', time()),$race["race_time"])."</td>";
        $htmlContent = $htmlContent."<td>".$race["race_status"]."</td>";
        $htmlContent = $htmlContent."</tr>";
    }

    $htmlContent = $htmlContent."</table></td></tr></table></center>";
    $htmlContent = $htmlContent."</body></html>";

    echo $htmlContent;
}

function get_races()
{
    $date = new DateTime(date('Y-m-d H:i:s', time()));
    $date_str = $date->format("Y")."-".$date->format("m")."-".$date->format("d");

    $db2 = Database::obtain();

    $sSql = "SELECT master_id, race_no, tatts_track_name, tatts_short_track_name,race_time, race_status
                FROM trackracemaster
                where  date(race_time) = '".$date_str."'
                order by tatts_short_track_name, race_no";


    // WHERE race_status in ('Open', 'Interim', 'Closed')";



    // feed it the sql directly. store all returned rows in an array
    return $db2->fetch_array($sSql);
}

function get_date_diff_in_minutes($fromDate, $toDate)
{
    $to_time = strtotime($toDate);
    $from_time = strtotime($fromDate);

    return round(($to_time - $from_time) / 60,2);
}