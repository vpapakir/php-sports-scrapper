<?php

//Check IsDebug = true for display data on screen

//Delay in calling the URL
define('MinDelayTime', 2);
define('MaxDelayTime', 8);

//never increase the value more then 5
//it will not give the correct results
define('DelayInMinForNextScrape', 5);

define('SportingBetSiteToScrape', 'https://www.sportingbet.com.au/horse-racinggrid');
define('SportingBetDomainURL', 'https://www.sportingbet.com.au');

//define('TattsBetSiteToScrape', 'https://tatts.com/racing');
define('TattsBetSiteToScrape', 'https://tatts.com/racing/2014/05/28/RaceDay');
define('TattsBetDomainURL', 'https://tatts.com');
define('TattsBlockToScrape', 'Gallops');
//-------------------------------------------------------------------

//==================================================================================================================//
//=================Any change in blow values will impact the application===========================================//
//===Sporting Bet===============
define('qRacingTrackBlocks', '//section[@class="block flat"]');
define('qRacingTrackBlocksHeader', './/header[@class="drop-header"]/h3/a[@class="drop-toggle f-left"]');
define('qRacingTrackBlocksHeaderText', 'Australia');
//define('qRacingTrackBlocksHeaderText', 'International');
define('qEachTracksInBlock', './/div[@class="block-inner block-betting block-racecard block-grid"]/table/tbody/tr');
define('qEachTimeInTrack', './/td');

//=================================================================================================================//
//===============================Database==========================================================================//
//database server
define('DB_SERVER', "64.150.187.58");

//database login namee
define('DB_USER', "moneyin8_admin");

//database login password
define('DB_PASS', "moneyinmotion!2#");

//database name
define('DB_DATABASE', "moneyin8_moneyinmotion");

//smart to define your table names also
define('TABLE_MASTER', "trackracemaster");
define('TABLE_RUNNER', "runner_details");
