<?php
/****************************************/
/*            BoomyRPG script           */
/*           Written by Boomy           */
/*         Login (and home) page         */
/****************************************/

include("config.php"); //Includes connection to the database

$error = NULL;

$time_active = time() - 300; //Players are considered active if they have clicked a button in the past 300 seconds (5 minutes)
$query = mysqli_query($con, "SELECT * FROM players WHERE last_active > $time_active");
if($query) { $active_users = mysqli_num_rows($query); } else { $active_users = "Unknown"; }

if(isset($_POST['submit'])) {
	$user = mysqli_real_escape_string($con, stripslashes(strtolower($_POST['user'])));
    $password = mysqli_real_escape_string($con, stripslashes($_POST['password']));
    
	//See if login details are valid
	$query = mysqli_query($con, "SELECT * FROM players WHERE username = '$user' LIMIT 1");
	$query2 = mysqli_query($con, "SELECT * FROM maintenance");
	if(!$query) {
	    $error = "Database connection broken (Perhaps  database not installed)"; 
	} elseif (!$query2) {
	    $error = "Maintenance table cannot be accessed (database connection error) and thus cannot determine if game in maintenance or not";
	} else {
	
	$row = mysqli_fetch_assoc($query);
	$row2 = mysqli_fetch_assoc($query2);
	
	if(mysqli_num_rows($query) <= 0) { //if this user does not exist
		$error = "This username does not exist";
	}
	
	elseif ($row['verification_status'] == 0) { //if this user is not verified
		$iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
		$decrypted_email = openssl_decrypt($row['email'], 'aes-256-cbc', $secret_key, 0, $iv);
		$email_domain = substr(strrchr($decrypted_email, "@"), 1);
		$date_of_create = date('d-m-Y h:i:s A', $row['date_registered']);
		$error = "This user has not verified their email. A verification email was sent to your $email_domain account on $date_of_create";
	}		//username exists, check passwords
	elseif (!password_verify($password, $row['password'])) { //if password fails
		$error = "The password you have input was wrong"; 
	}
	elseif ($row2['status'] != 0) 
	{ 
		$error = "The game is currently under maintenance. Try again later!";	
	} else { //pass all checks, proccess login
	    $time = time();
	    $user_ip = $_SERVER['REMOTE_ADDR'];
	    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $last_login_time = $row['current_login_time'];
	    $query3 = mysqli_query($con, "UPDATE players SET last_active = '$time',  ip = '$user_ip', previous_login_time = '$last_login_time', current_login_time = '$time' WHERE username = '$user'");
	    $_SESSION['player_id'] = $row['id']; //stores player id into a SESSION variable
	    $_SESSION['session_secret'] = openssl_decrypt($row['id'], 'aes-256-cbc', $secret_key, 0, $iv);
        header("Location:home.php");
	}
}

} elseif (isset($_GET['error'])) {  
    switch($_GET['error']) {
        case "timeout":
            $error = "You have been logged out due to lack of activity within 30 minutes";
            break;
        case "login_expired":
            $error = "Your login session has expired";
            break;            
        default:
            $error = "You have been logged out";
            break; 
    }

}

?>

<html>

<body>
<div class="container container-small-fixed">
    <div class="col-sm py-2 text-center">
        <h1>Boomy Online</h1>
    </div>
    <form method="POST" action="">
        <div class="form-group row">
            <label for="inputUser" class="col-sm-2 col-form-label">Username</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="inputUser" name="user" required>
            </div>
        </div>
        <div class="form-group row">
            <label for="inputPassword" class="col-sm-2 col-form-label">Password</label>
            <div class="col-sm-10">
                <input type="password" class="form-control " id="inputPassword" name="password" required>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-sm-12">
                <button type="submit" name="submit" value="Login" class="btn btn-primary my-1 required">Sign in</button>
                <a class="btn btn-primary float-right my-1" href="password_reset.php" role="button">Reset Password</a>
                <a class="btn btn-primary float-right mx-2 my-1" href="register.php" role="button">Register</a> 
                <a class="btn btn-primary float-right mx-2 my-1" href="adminer.php" role="button">MariaDB</a> 
                <a class="btn btn-primary float-right mx-2 my-1" href="manager.php" role="button">Edit</a> 
            </div>
        </div>
        <div class="form-group row"><div class="col-sm-12"> <?php echo $active_users . " user(s) active now"; ?> </div></div>
        <div class="form-group row"><div class="col-sm-12"> <?php echo $error; ?> </div></div>
            </div>
    </form>
</div>
</body>
</html>