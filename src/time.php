<?php

///////////////////////////////////////////////////////////////////
///////// ONLY FOR TESTING /////////////////////////////////////////

///////////////////////////////////////////////////////////////////
$date22 = new DateTime(date('Y-m-d H:i:s', time()));
print_r("Server time - ".$date22->format('Y-m-d H:i:s')."<br>");

$date22->modify ("+16 hours");
print_r("Time after Adjust. Australia time -".$date22->format('Y-m-d H:i:s')."<br>");

date_default_timezone_set('Australia/Queensland');
$date22 = new DateTime(date('Y-m-d H:i:s', time()));
print_r("Australia Time Zone Time - ".$date22->format('Y-m-d H:i:s')."<br>");

exit();