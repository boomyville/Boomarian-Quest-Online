<?php
/****************************************/
/*            BoomyRPG script           */
/*           Written by Boomy           */
/*    Registration page with CAPTHA     */
/*        and email verification        */
/****************************************/

//***************************************/
/* Variables that need to be edited by  */
/* the user include:                    */
/* reCAPTCHA secret ($secret)           */
/* reCAPTCHA site key (div class)       */
/* cacert.pem (upload into directory)   */
/****************************************/
/* $url_stem is set in config.php       */
/****************************************/

//To do: Lost password via email

include("config.php"); //Includes connection to the database
define("PAGENAME", "Register");

//Error messages
$error = NULL;

if (isset($_POST['submit'])) {
    //Get data from the form 
    //Stripslashes is used to clean up any input from the user (removes backslashes primarily)
	$user = mysqli_real_escape_string($con, stripslashes(strtolower($_POST['user'])));
    $password1 = mysqli_real_escape_string($con, stripslashes($_POST['password1']));
	$password2 = mysqli_real_escape_string($con, stripslashes($_POST['password2']));
    $email = mysqli_real_escape_string($con, stripslashes(strtolower($_POST['email'])));
	
	$query = mysqli_query($con, "SELECT * FROM maintenance");
	$iv = hex2bin(mysqli_fetch_assoc($query)['iv']);	
	$encrypted_email = openssl_encrypt($email, 'aes-256-cbc', $secret_key, 0, $iv);
	
	//The following variables are relating to Google's reCAPTCHA v2 bot-identification mechanism
    $secret = 'Get your secret key from Google reCAPTCHA';
    $captcha = trim($_POST['g-recaptcha-response']);
    $ip = $_SERVER['REMOTE_ADDR'];
    $url = "https://www.google.com/recaptcha/api/siteverify?secret={$secret}&response={$captcha}&remoteip={$ip}";
    $options = array('ssl' => array('cafile' => 'Certificates/cacert.pem', 'verify_peer' => true, 'verify_peer_name' => true, ), );
    $context = stream_context_create($options);
    $res = json_decode(file_get_contents($url, FILE_TEXT, $context));
	
	//This snippet does a database query to check if the same email or username exists already
	$query = mysqli_query($con, "SELECT * FROM players WHERE username='$user'"); 
    $query2 = mysqli_query($con, "SELECT * FROM players WHERE email='$encrypted_email'");
	
	//This query is to check if the game is in maintenance mode or not
	$row = mysqli_fetch_assoc($query);
	
	if (strlen($user) < 5) {
        $error .= "<br>Your username must be at least 5 characters";
    }
    elseif(strlen($user) > 20) {
        $error .= "<br>Your username cannot be more than 20 characters";
    }
    elseif($password1 != $password2) {
        $error .= "<br>Your passwords to not match";
    }
    elseif($user != mysqli_real_escape_string($con, $user)) {
        $error .= "<br>You cannot use the username ".$user.
        ", but you can use ".mysqli_real_escape_string($con, $user);
    }
    elseif($password1 != mysqli_real_escape_string($con, $password1)) {
        $error .= "<br>Your password contains illegal characters";
    }
    elseif(strlen($password1) < 8) {
        $error .= "<br>Your password must be at least 8 characters";
    }
    elseif(strlen($password1) > 40) {
        $error .= "<br>Your password cannot be more than 40 characters";
    }
    elseif(!preg_match("#[0-9]+#", $password1)) {
        $error .= "<br>Your password must contain at least 1 number";
    }
    elseif(!preg_match("#[a-z]+#", $password1)) {
        $error .= "<br>Your password must contain at least 1 lower case letter";
    }
    elseif(!preg_match("#[A-Z]+#", $password1)) {
        $error .= "<br>Your password must contain at least 1 capital latter";
    }
    elseif(!preg_match("#\W+#", $password1)) {
        $error .= "<br>Your password must contain at least 1 symbol";
    }
    elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error .= "<br>Your email is not valid";
    }
    elseif(!checkdnsrr(substr(strrchr($email, "@"), 1), 'MX')) {
        $error .= "<br>Your email domain is not valid";
    } 
    elseif(!isset($captcha)) {
        $error .= "<br>Something went wrong with the reCAPTCHA verification";
    } 
    elseif(!$res -> success) {
        $error .= "<br>You failed the reCAPTCHA verification";
    }
    elseif(!mysqli_num_rows($query) == false) { //This condition will spit out a number if another username exists and false if the username is unique
        $error .= "<br>The username $user already exists. Choose a different username";
    }
    elseif(!mysqli_num_rows($query2) == false ) { //This condition will spit out a number if another email exists and false if the email is unique
        $error .= "<br>The email of $email is already registered. Lost your password?";
    }
    elseif($row['status'] != 0)  { //Game is in maintenance mode
        $error .= "<br>The game is currently under maintenance. Try again later!";
    }	
    else {
		
		//Registration process okay
		//Create verfication key for email
		$email_verification = password_hash(time().$user, PASSWORD_DEFAULT);
		$hashed_password = password_hash($password1, PASSWORD_DEFAULT);
		$current_time = time();
		//Add player to database 
		mysqli_query($con, "INSERT INTO players (username, password, email, verification_key, date_registered) VALUES ('$user', '$hashed_password', '$encrypted_email', '$email_verification', '$current_time')");
		echo mysqli_error($con);
		//Setup email for verification
		$email_user = $email;
		$email_username = $user;
		$subject = "Email verification for " . $user;
		$body = "Welcome $user to Boomy Online! <br><br> Please click <a href='" . $url_stem . "verify.php?vkey=$email_verification'> here</a> to verify your email and complete the registration of your new account!<br>Alternatively use the following link to verify: " . $url_stem . "verify.php?vkey=$email_verification";
		//Send the email
		include("register_mail.php");		
		//Send the user to the verification page
		//header('verify.php?vkey=new');
		echo "Thank you for registering. Please check your email and verify your account. Login <a href=index.php>here</a>";
		exit();
    }
} ?> 

<html>
<body>
<div class="container container-small-fixed">
    <div class="col-sm py-2 text-center">
        <h1>Boomy Online</h1>
        <p>Please fill out the following form to register a new account</p></div>
    <form method="POST" action="">
        <div class="form-group row">
            <label for="inputUser" class="col-sm-4 col-form-label">Username</label>
            <div class="col-sm-8">
                <input type="text" class="form-control" id="inputUser" name="user" required>
            </div>
        </div>
        <div class="form-group row">
            <label for="inputPassword1" class="col-sm-4 col-form-label">Password</label>
            <div class="col-sm-8">
                <input type="password" class="form-control " id="inputPassword1" name="password1" required>
            </div>
        </div>
        <div class="form-group row">
            <label for="inputPassword2" class="col-sm-4 col-form-label">Repeat Password</label>
            <div class="col-sm-8">
                <input type="password" class="form-control " id="inputPassword2" name="password2" required>
            </div>
        </div>       
        <div class="form-group row">
            <label for="inputEmail" class="col-sm-4 col-form-label">Email Address</label>
            <div class="col-sm-8">
                <input type="email" class="form-control " id="inputEmail" name="email" required>
            </div>
        </div>      
         <div class="form-group row"><div class="col-sm-12 d-flex justify-content-center"><div class="g-recaptcha" data-sitekey="6Le-VdcUAAAAAMb43VWM8WeDlWv0v_KSE08Aye5v"></div></div>
            </div>
        <div class="form-group row">
            <div class="col-sm-12 d-flex justify-content-center">
                <button type="submit" name="submit" value="Register" class="btn btn-primary my-1 required">Register New Account</button>
            </div>
        </div>
        <div class="form-group row"><div class="col-sm-12"> <?php echo $error; ?> </div></div>
            </div>
    </form>
</div>
</body>
</html>