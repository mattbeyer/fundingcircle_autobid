<?php
error_reporting(E_ALL); 
ini_set('display_errors', 1); 
ini_set('max_execution_time', 0);

include ('config.php');
include ('fc_login_func.php');
include ('db_functions.php');


define ('AUCTION_ROW', 1);
define ('LOGFILE', 'logfile' . AUCTION_ROW . '.html');
if (file_exists(LOGFILE)) unlink(LOGFILE);

// fc_log logs information to log file.

function fc_log($log_line) {


	$fp = fopen(LOGFILE , "a+");
	fwrite($fp, $log_line);
	fclose($fp);


}

// log_no_bid_data logs that no bid data was found on the html return from curl request.

function log_no_bid_data() {

	fc_log(put_time() . "PID = " . PID . " " . AUCTION_URL . '. There was no bid data. <br>');	

}


// curl_opts sets up curl options.

function curl_opts($url)	{

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1)");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_COOKIEJAR, COOKIEFILE);
	curl_setopt($ch, CURLOPT_COOKIEFILE, COOKIEFILE);
	curl_setopt($ch, CURLOPT_HEADER, 0);  
	curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
	curl_setopt($ch, CURLOPT_TIMEOUT, 0);
	curl_setopt($ch, CURLOPT_URL, $url);
	
	sleep(1); 

	return $ch;

}

// get_auction_html sends auction_url to fundindcircle.com to get auction information.

function get_auction_html($auction_url) {
	
	$ch = curl_opts($auction_url);
 	$auction_html = curl_exec($ch); 
	curl_close($ch);
	return $auction_html;

}

// get_bid_data checks finds DOM elements needed to make auto bid

function get_bid_data($auction_html) {

	libxml_use_internal_errors(true);
	$doc = new DOMDocument();
	if ($auction_html == true) $doc->loadHTML($auction_html);
	$rates_list = $doc->getElementById('bid_annualised_rate')->childNodes;
	if ($rates_list == null) {
	log_no_bid_data();
		return false;		
	}
	$rates_arr = array();

	foreach($rates_list as $rate) {
		$rates_arr[] = $rate->firstChild->nodeValue;
	}

	$top_rate = $rates_arr[1];

	$bid_form = $doc->getElementById('new_bid');
	$action = $bid_form->getAttribute('action');

	$live_bids = $doc->getElementById('live_bids')->nodeValue;
	$live_bids = ltrim($live_bids, "Â£");
	$live_bids = intval($live_bids);
	
	$bid_url = 'https://www.fundingcircle.com' . $action;

	$auth_token = $bid_form->firstChild->firstChild->nextSibling->getAttribute('value');

	$bid_data = array(
	
		'auth_token' => $auth_token,
		'top_rate' => $top_rate,
		'bid_url' => $bid_url,		
		'live_bids' => $live_bids
	
	);
	
	return $bid_data;

	
}

// bid sends bid data to fundingcircle.com to make bid. It returns that fundingcircle.com returns after
// a bid attempt.

function bid($auth_token, $top_rate, $bid_url) {

	$ch = curl_opts($bid_url);
	sleep(1);		

	$fields = array(
						'utf8' => urlencode('✓'),
						'authenticity_token' => urlencode($auth_token),
						'is_bid' => urlencode('1'),
						'bid[amount]' => urlencode('20'),
						'bid[annualised_rate]' => urlencode($top_rate),
						'make_bid' => ''

					);
					
	$fields_string = "";
	
	foreach ($fields as $key => $value) {
					$fields_string .= $key.'='.$value.'&';
		}
		
	$post_string = rtrim($fields_string, '&');

	curl_setopt($ch, CURLOPT_POST, 1);  
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post_string);  

	sleep(1);
	$bid_return = curl_exec($ch);

	if ($bid_return == false) log_curl_error($ch);
	if ($bid_return == true) {
	
		fc_log(put_time() . "PID = " . PID . " " . AUCTION_URL . '. Bid attempt true.<br>');		
	
	}	
	
	curl_close($ch);
	
	return $bid_return;
}

// auto_bid gets bid_data from $bid_data array and sends to bid function.

function auto_bid($bid_data) {

	$auth_token = $bid_data['auth_token'];
	$top_rate = $bid_data['top_rate'];
	$bid_url = $bid_data['bid_url'];

	$bid_return = bid($auth_token, $top_rate, $bid_url);
	return $bid_return;
		
}

// save_auction_html save the auction page html to check for errors if needed.

function save_auction_html($auction_html) {

	$fp = fopen('auction_html.html' , "w+");
	fwrite($fp, $auction_html);
	fclose($fp);

}

// logged_in_bid_init does the work of deciding when to autobid.
// it gets the bid data. 
// Checks that top_rate is higher than the stop rate.
// Checks that the live bids are less than the max_live_bids.
// If these are both true it makes a bid request.
// logged_in_bid_init exits if runs for more than 100 seconds.
// logged_in_bid_init stops if stop_rate is reached.
// logged_in_bid_init stops if the bid data has been cleared from the database.

function logged_in_bid_init($auction_url, $max_live_bids, $stop_rate) {

	$auction_html = get_auction_html($auction_url);
	sleep(5);
	$bid_data = get_bid_data($auction_html);		
	
	if ($bid_data == true) {
	
		save_auction_html($auction_html);
		fc_log(put_time() . 'Auction data true.<br>' . $bid_data['live_bids']);
		
	}	

	if ($bid_data == false) {
	
		save_auction_html($auction_html);
		fc_log(put_time() . 'Auction data false.<br>');
		exit;
		
	}
	
	$live_bids = $bid_data['live_bids'];
	$top_rate = $bid_data['top_rate'];
	$top_rate = (float) rtrim($top_rate, "%");

	$while_entry_time = time();
	
	while ($top_rate > $stop_rate) {

		$top_of_while_time = time();
		$time_elapsed = $top_of_while_time - $while_entry_time;
		if ($time_elapsed > 100) {
		
			fc_log(put_time() . 'Run time elapsed.<br>');
			exit;
		
		}

		
		fc_log(put_time() . "PID = " . PID . " " . AUCTION_URL . '. Live bids are ' . $live_bids . '. Max live bids are ' . $max_live_bids . '. ');
		fc_log(' Top rate is ' . $top_rate . '. Stop rate is ' . $stop_rate . '. ');	
	
	
		if ($live_bids < $max_live_bids) {
		
			fc_log(put_time() . 'Bid attempted.<br>');			
			$bid_return = auto_bid($bid_data);
			sleep(5);
			$bid_data = get_bid_data($bid_return);

			if ($bid_data == false) return false; 

			fc_log(put_time() . "PID = " . PID . " " . AUCTION_URL . '. Bid successful.<br>');	
			
			$live_bids = $bid_data['live_bids'];
			$top_rate = $bid_data['top_rate'];
			$top_rate = (float) rtrim($top_rate, "%");
		}	

		if ($live_bids >= $max_live_bids) {

			fc_log('Refreshing auction page to check for outbids.<br>');			
			$auction_html = get_auction_html($auction_url);
			sleep(5);
			$bid_data = get_bid_data($auction_html);	
			if ($bid_data == false) return false; 
			
			$live_bids = $bid_data['live_bids'];
			$top_rate = $bid_data['top_rate'];
			$top_rate = (float) rtrim($top_rate, "%");
			
		}
		
		$db_bid_data = row_exists(AUCTION_ROW);
		if ($db_bid_data == false) {		
			fc_log(put_time() . "PID = " . PID . " " . AUCTION_URL . '. Bid data cleared.<br>');	
			exit('Bid data cleared.');	
		}
		
	}  

	if ($top_rate <= $stop_rate) {
	
		fc_log(put_time() . "PID = " . PID . " " . AUCTION_URL . ' Top rate is ' . $top_rate . '. Stop rate is ' . $stop_rate . '. Auto bid stopped.<br>');	
		exit;
	}
		

		
		
		
}	


// get_my_lending_html requests a page from fundingcircle.com that should return html 
// The returned html is sued to check whether user is logged in.

function get_my_lending_html() {

	$url = 'https://www.fundingcircle.com/my-account/my-lending/';
	$ch = curl_opts($url);
 	$logged_in_html = curl_exec($ch); 
	curl_close($ch);
	
	return $logged_in_html;
}

// fcbid checks to see if user is logged it.
// If user is logged in auto bidding is initialised.
// If user is logged out, the user is logged in.

function fcbid($auction_url, $max_live_bids, $stop_rate) {

	$logged_in_html = get_my_lending_html();

	if (logged_in($logged_in_html) == true) {
		$log_line = put_time() . "PID = " . PID . " " . AUCTION_URL . '. Already logged in. Cookiefile kept.<br>';
		fc_log($log_line);		
		$logged_in_html = logged_in_bid_init($auction_url, $max_live_bids, $stop_rate);
	}	
	
	
	while (logged_in($logged_in_html) == false) {
	$log_line = put_time() . "PID = " . PID . " " . AUCTION_URL . '. Need to login.<br>';
		fc_log($log_line);		
		$logged_in_html = login();
		if ($logged_in_html == true) {
			$log_line = put_time() . "PID = " . PID . " " . AUCTION_URL . '. You are now logged in.<br>';
			fc_log($log_line);
			
		}
		if ($logged_in_html == false) exit; 
		while (logged_in($logged_in_html)) {
			$logged_in_html = logged_in_bid_init($auction_url, $max_live_bids, $stop_rate);
		}
		
	}

}

// got_db_bid_data checks that there is data in the table that can be used to bid with.

function got_db_bid_data() {

	$db_bid_data = row_exists(AUCTION_ROW);
	
	$auction_url = $db_bid_data['auction_url'];
	$max_live_bids = $db_bid_data['max_live_bids'];
	$stop_rate = $db_bid_data['stop_rate'];

	if ($auction_url == false) {
		fc_log(put_time() . 'Missing or unusable bid information.<br>');
		exit('Missing bid information...');
	}

	if ($max_live_bids == false) {
		fc_log(put_time() . 'Missing or unusable bid information.<br>');
		exit('Missing bid information...');
	}

	if ($stop_rate == false) {
		fc_log(put_time() . 'Missing or unusable bid information.<br>');
		exit('Missing bid information...');
	}
	
	return $db_bid_data;
}

$db_bid_data = got_db_bid_data();

$auction_url = $db_bid_data['auction_url'];
$max_live_bids = $db_bid_data['max_live_bids'];
$stop_rate = $db_bid_data['stop_rate'];

define ('AUCTION_URL', $auction_url);

fcbid($auction_url, $max_live_bids, $stop_rate);	








					


