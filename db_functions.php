<?php

// update_row changes the content of a row in the database.

function update_row(){

	db_connect();
	
	$auction_url = $_POST['auction_url'];
	$max_live_bids = $_POST['max_live_bids'];
	$stop_rate = $_POST['stop_rate'];
	$auction_row = $_POST['auction_row'];

	$sql = 	'UPDATE bid_form ' .
			'SET auction_url = "' . $auction_url . '", ' .
			'max_live_bids = ' . $max_live_bids . ', ' .	
			'stop_rate = ' . $stop_rate . ' ' .			
			'WHERE auction_row =' . $auction_row;	
			
	$result = mysql_query($sql);			

}

// If a row has not be created, insert_row creates a new row containing the form data.

function insert_row() {

	db_connect();
	
	$auction_url = $_POST['auction_url'];
	$max_live_bids = $_POST['max_live_bids'];
	$stop_rate = $_POST['stop_rate'];
	$auction_row = $_POST['auction_row'];

	
	$sql = 	"
				INSERT INTO bid_form (auction_row, auction_url, max_live_bids, stop_rate) VALUES 
					('"
						. $auction_row . "', '"
						. $auction_url . "', '"
						. $max_live_bids . "', '"				
						. $stop_rate . "'
					)
			";

	$result = mysql_query($sql);	

}

// row_exists checks to see if the row, containing the row number, exist.

function row_exists($auction_row) {
	
	db_connect();
	
	$sql = "SELECT auction_row, auction_url, max_live_bids, stop_rate FROM bid_form WHERE auction_row ='" . $auction_row ."'";
	$result = mysql_query($sql);
	
	while ($row = mysql_fetch_array($result)) {
		$auction_url = $row['auction_url'];
		$max_live_bids = $row['max_live_bids'];
		$stop_rate = $row['stop_rate'];		
	}

	if (mysql_num_rows($result) == 0) return false;
	
	$db_bid_data = [];
	$db_bid_data['auction_url'] = $auction_url;
	$db_bid_data['max_live_bids'] = $max_live_bids;
	$db_bid_data['stop_rate'] = $stop_rate;	
	
	return $db_bid_data;
 

}

?>
