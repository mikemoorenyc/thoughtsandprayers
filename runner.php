<?php
date_default_timezone_set('UTC');
ini_set('auto_detect_line_endings',TRUE);
//AUTOMATION
chdir(dirname(__FILE__));

//MAKE IT SO IT CAN ONLY BE RUN ON CRON / Swap dev spreadsheet
$spreadsheet_url = 'dev_spread.html';
if($_GET['dev'] !== 'yes') {
  if( php_sapi_name() !== 'cli' ){http_response_code(404); die();}
  //TY Mother Jones
  $spreadsheet_url = 'https://docs.google.com/spreadsheets/d/1XV4mZi3gYDgwx5PrLwqqHTUlHkwkV-6uy_yeJh3X46o/pub';
}


$spreadsheet = @file_get_contents($spreadsheet_url);
if(!$spreadsheet) {
//ADD FAIL TIME
  http_response_code(404);
  die();
}
$dom = new DOMDocument;
$dom->loadHTML($spreadsheet);

$parsed_table = [];

foreach($dom->getElementsbyTagName('tr') as $tr) {
  if(empty($tr->nodeValue)) {
    continue;
  }
  $item = [];
  foreach($tr->getElementsbyTagName('td') as $td) {
    if(!empty($td->nodeValue)) {
      $item[] = $td->nodeValue;
    }
  }
  $parsed_table[] = $item;
}

//GET FIRST ONE;
$first = [];
foreach($parsed_table[0] as $k => $h) {
  $first[$h] = $parsed_table[1][$k];
}

$newest = [];
$newest['shooting_title'] = trim($first['Case']);
$location = explode(',',$first['Location']);
if(count($location) > 1) {
  $newest['shooting_city'] = trim($location[0]);
  $newest['shooting_state'] = trim($location[1]);
} else {
  $newest['shooting_city'] = trim($location[0]);
  $newest['shooting_state'] = '';
}
$newest['shooting_date'] = strtotime($first['Date']);



$run_info = json_decode(@file_get_contents('run_info.json'),TRUE);

$comparers = ['shooting_title','shooting_city','shooting_state','shooting_date'];
$same_count = 0;
//COMPARE ITEMS
foreach($comparers as $c) {
  if($newest[$c] === $run_info[$c]) {
    $same_count++;
  }
}
//IF 2 or more are the same, it's probably the same. There's so many mass shootings, it could be possible that one happened on the same day in the same state. So, i'm guessing that if less than 3 things are the same, it's probably a new shooting. Call your senator.
if($same_count >= 2) {
  // ADD NO RUN TIME
  http_response_code(404);
  die();
}

//I'm using this library to talk to the Twitter API  http://github.com/j7mbo/twitter-api-php
require_once('twitterexchange.php');

//TWITTER API DEV CREDENTIALS
require_once('twitter_credentials.php');

$settings = array(
    'oauth_access_token' => $credentials['access_token'],
    'oauth_access_token_secret' => $credentials['access_token_secret'],
    'consumer_key' => $credentials['consumer_key'],
    'consumer_secret' => $credentials['consumer_secret']
);

//LOAD IN TWEET TEMPLATES
$templates = json_decode(@file_get_contents('tweet_templates.json'),true);
$template_number = $run_info['last_template']++;
if($template_number > count($templates) - 1) {
 $template_number = 0;
}


//MAKE THE TWEET
$tweet_text = str_replace("***SHOOTING LOCATION***",$newest['shooting_city'],$templates[$template_number]);

//SEND THE TWEET
$twitterNew = new TwitterAPIExchange($settings);
$postfields = array(
  'status' => $tweet_text
);
$postedTweet = $twitterNew->buildOauth('https://api.twitter.com/1.1/statuses/update.json', 'POST')->setPostfields($postfields)->performRequest();


if($postedTweet['created_at']) {
  $newest['last_template'] = $template_number;
  file_put_contents('run_info.json',json_encode($newest));
}

die();

?>
