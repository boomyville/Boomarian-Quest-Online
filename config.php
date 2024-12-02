<?php

/*************************************/
/*           BoomyRPG script         */
/*          Written by Boomy         */
/*************************************/

// Add database login credentials below
// This file is added to pretty much every BoomyRPG php file to connect with the databse

$mysql_server = "192.168.1.1"; 							// Usually db or localhost
$mysql_username = "user";    					// Your MySQL username
$mysql_password = "pass";      			    // Your MySQL Password
$mysql_database = "database_name";     				// The name of your database
$secret_key = "something";  // Secret key used for dirty encryption (not used)

$con = new mysqli($mysql_server, $mysql_username, $mysql_password, $mysql_database);

if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

//This is the URL of your website. It is used for a few instances of directing the player to the home page
$url_stem = "https://your-website.com/";

//Set the timezone
date_default_timezone_set('Australia/Melbourne');

//Start a SESSION to allow to store variables temporarily for the user
 session_start();

?>

<html>
<head>
<title>Boomy Online</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
<link rel="stylesheet" href="style.css">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
</head>
<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>

<script>
$(document).ready(function(){
  $('[data-toggle="tooltip"]').tooltip();   
});
</script>

</html>
