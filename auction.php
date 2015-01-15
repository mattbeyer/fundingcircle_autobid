<?php

// auction.php creates rows for forms.
// The user can place bid data into each row.
// It can be saved to the database and cleared from the database.
// Once data is in database the data will used to auto bid every two minutes.

include ('config.php');

// checks database table for rows that contain data.
// data is put into a array to be placed into form.

function get_bid_form_data() {

	db_connect();

	$sql = "SELECT * FROM bid_form";
	$result = mysql_query($sql);
	
	$bid_form = [];
	while ($row = mysql_fetch_array($result)) {
		$auction_row = $row['auction_row'];
		$bid_form[$auction_row] = array(
			'auction_url' => $row['auction_url'],
			'max_live_bids' => $row['max_live_bids'],
			'stop_rate' => $row['stop_rate']
			);
	}
	
	ksort($bid_form);
	return $bid_form;
	
}

$bid_form = get_bid_form_data();

?>





<head>

	<style>

		.form {

			padding-left: 5%;
			padding-right: 5%;
			padding-top: 0.5cm;
			font-family: verdana,helvetica,arial,sans-serif;
		}

	</style>

</head>

<body>

	<div class="form">

		<h3 style="text-align:center">Funding Circle Auto Bid</h3>
		<br><br>

		<table>
				<tr>
					<td></td>
					<td>Auction url</td>
					<td>Maximum Live Bids</td>
					<td>Stop Rate</td>
					<td></td>
					<td></td>
				</tr>
				<tr>

					
<?php for ($i = 1; $i <= 5; $i++) { ?>
				<tr>
					<form action='save.php' method='post'><input type='hidden' name='auction_row' value='<?php echo $i; ?>'>
						<td><?php echo $i;?>.</td>
						<td><input type="text" name="auction_url" size='60' value='<?php
							if (array_key_exists($i, $bid_form)) echo $bid_form[$i]["auction_url"]; ?>'></td>
						<td><input type="text" name="max_live_bids" size='20' value='<?php
							if (array_key_exists($i, $bid_form)) {
								if ($bid_form[$i]["max_live_bids"] == false) {
								} else echo $bid_form[$i]["max_live_bids"]; 							
							};  
								?>'></td>						
						<td><input type="text" name="stop_rate" size='20' value='<?php
							if (array_key_exists($i, $bid_form)) {
								if ($bid_form[$i]["stop_rate"] == false) {
								} else echo $bid_form[$i]["stop_rate"]; 							
							};  
								?>'></td>	
						<td><input type='submit' value='Save'></td>						
					</form>
					<form action='delete_row.php' method='post'><input type='hidden' name='auction_row' value='<?php echo $i; ?>'>
						<td><input type="submit" value="Clear"</td>
					</form>					
				</tr>

<?php } ?> 


