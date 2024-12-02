<?php
/****************************************/
/*            BoomyRPG script           */
/*           Written by Boomy           */
/*  Passsword reset page  via email     */
/****************************************/

//***************************************/
/* Variables that need to be edited by  */
/* the user include:                    */
/* reCAPTCHA secret ($secret)           */
/* reCAPTCHA site key (div class)       */
/* cacert.pem (upload into directory)   */
/****************************************/

include("config.php"); //Includes connection to the database

if(isset($_POST['submit'])) {
    //Check if email is valid
    $msg = NULL;
    $email = mysqli_real_escape_string($con, stripslashes(strtolower($_POST['email'])));
    $query = mysqli_query($con, "SELECT * FROM maintenance");
	$row = mysqli_fetch_assoc($query);
	$iv = hex2bin($row['iv']);	
	$encrypted_email = openssl_encrypt($email, 'aes-256-cbc', $secret_key, 0, $iv);
    $query2 = mysqli_query($con, "SELECT * FROM players WHERE email='$encrypted_email'");
    $row2 = mysqli_fetch_assoc($query2);
    $user = $row2['username'];
    
    //The following variables are relating to Google's reCAPTCHA v2 bot-identification mechanism
    $secret = 'Get your secret key from Google reCAPTCHA';
    $captcha = trim($_POST['g-recaptcha-response']);
    $ip = $_SERVER['REMOTE_ADDR'];
    $url = "https://www.google.com/recaptcha/api/siteverify?secret={$secret}&response={$captcha}&remoteip={$ip}";
    $options = array('ssl' => array('cafile' => 'Certificates/cacert.pem', 'verify_peer' => true, 'verify_peer_name' => true, ), );
    $context = stream_context_create($options);
    $res = json_decode(file_get_contents($url, FILE_TEXT, $context));
    
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $msg .= "Your email is not valid";
    }
    elseif(!checkdnsrr(substr(strrchr($email, "@"), 1), 'MX')) {
        $msg .= "Your email domain is not valid";
    } 
    elseif(!isset($captcha)) {
        $msg .= "Something went wrong with the reCAPTCHA verification";
    } 
    //elseif(!$res -> success) {
     //   $msg .= "You failed the reCAPTCHA verification";
    //}
    elseif(mysqli_num_rows($query2)) { //If a email exists
        if($row['status'] == 0) { //Game not under maintenance mode
            //Check if password key already exists
            $time = time();
            $query3 = mysqli_query($con, "SELECT * FROM `password_reset` WHERE (`validity` >= '$time' OR `used_status` != 0) AND `username` = '$user' ORDER BY `validity` DESC");
            if(mysqli_num_rows($query3) > 0) { //Player previously request password reset recently
                $row3 = mysqli_fetch_assoc($query3);
                if($row3['used_status'] != 0) {
                     $msg .= "Your password reset email was sent to you already and will expire on " . date("l jS F Y h:i:s A", $row3['validity']) . ". Please check your email again.";
                } else {
                $msg .= "You already requested to change your password recently. Please check your email again.";
                }
                }
            else {
	        	//Setup email for verification
	        	$email_user = $email;
        		$password_verification = password_hash(time().$user, PASSWORD_DEFAULT);
        		$reset_expiry = time() + 60 * 60; //Email expires in 60 minutes
	        	mysqli_query($con, "INSERT INTO password_reset (password_key, used_status, validity, username) VALUES ('$password_verification', 0, '$reset_expiry', '$user')");
	        	$subject = "Password reset for " . $user;
        		$body = "Hello $user. <br><br>A request to reset your password was made on " . date("l jS F Y h:i:s A") . ".<br>Please click <a href='" . $url_stem . "verify.php?pkey=$password_verification'> here</a> to reset your password<br>Alternatively use the following link to verify: " . $url_stem . "verify.php?pkey=$password_verification <br>This link expires in 60 minutes<br>If you did not request this password change then please ignore this message.";
	        	//Send the email
        	   	include("register_mail.php");		
        		//Send the user to the verification page
        		//header('verify.php?pkey=new');
        		echo "Please check your email for a password reset link";
            }
        }
        else {
            $msg .= "The game is currently under maintenance. Try again later";
        }
        }
    else {
        $msg .= "The email provided does not exist. Please try again. Click <a href=\"password_reset.php\">here</a> to go back";
    }
echo $msg;
    }
else {
    //Echo HTML for password reset
    	echo "<html><head><!-- Google ReCAPTCHA v2 Javascript library --><script src='https://www.google.com/recaptcha/api.js'></script>";
        echo "<!-- Insert CSS Link here eg: <link href=\"style.css\" rel=\"stylesheet\" type=\"text/css\" /> --></head>";
        echo "<body><form method=\"POST\" action=\"\"><table border=\"0\" align=\"center\" cellpadding=\"3\">";
        echo "<tr><td colspan=\"2\" align=\"center\">Enter the email used to register to reset your password</td></tr>";
        echo "<tr><td align=\"right\">Email:</td><td><input type=\"text\" name=\"email\" required></tr>";
        echo "<tr><td align=\"center\" colspan=\"2\"><div class=\"g-recaptcha\" style=\"display: inline-block;\" data-sitekey=\"6Le-VdcUAAAAAMb43VWM8WeDlWv0v_KSE08Aye5v\"></div></td></tr>";
	$query3 = mysqli_query($con, "SELECT * FROM maintenance");
	$row3 = mysqli_fetch_assoc($query3);
			if ($row3['status'] == 0) {
				echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"SUBMIT\" name=\"submit\" value=\"Send Password Reset Email\" required /></td></tr>";
			} else {
				echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"SUBMIT\" name=\"submit\" disabled value=\"Password Reset Disabled\" required /><br>The game is currently under maintenance</td></tr>";
			}
			echo "</table></form><center>";
}
?>