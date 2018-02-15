<?php
//AUTOMATION
chdir(dirname(__FILE__));

//MAKE IT SO IT CAN ONLY BE RUN ON CRON / Swap dev spreadsheet
$spreadsheet_url = 'dev_spread.csv';
if($_GET['dev'] !== 'yes') {
  if( php_sapi_name() !== 'cli' ){http_response_code(404); die();}
  //TY Mother Jones
  $spreadsheet_url = 'https://docs.google.com/spreadsheet/pub?key=0AswaDV9q95oZdG5fVGJTS25GQXhSTDFpZXE0RHhUdkE&amp;output=csv'
}


$spreadsheet = @file_get_contents($spreadsheet_url);
if(!$spreadsheet) {
  http_response_code(404);
  die();
}
$run_info = json_decode(@file_get_contents('run_info.json'));
//GET FIRST ONE;

//NOTHING NEW
if($newest['shooting_title'] === $run_info['shooting_title']) {
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
$templates = json_decode(@file_get_contents('tweet_templates.json'));
$template_number = $run_info['last_template']++;
if($template_number > count($templates) - 1) {
 $template_number = 0; 
}
//MAKE THE TWEET
$tweet_text = str_replace("***SHOOTING LOCATION***",$newest['shooting_location'],$templates[$template_number]);

//SEND THE TWEET
$twitterNew = new TwitterAPIExchange($settings);
$postfields = array(
  'status' => $tweet_text
);
$postedTweet = $twitterNew->buildOauth('https://api.twitter.com/1.1/statuses/update.json', 'POST')->setPostfields($postfields)->performRequest();

if($postedTweet) {
  $run_info['last_template'] = $template_number;
  file_put_contents('run_info.json',$run_info);
}

die();

?>
