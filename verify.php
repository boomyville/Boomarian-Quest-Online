<?php
/****************************************/
/*            BoomyRPG script           */
/*           Written by Boomy           */
/*      Verification page (email)       */
/****************************************/

include("config.php"); //Includes connection to the database

function password_reset_form($con, $pkey, $time)
{
    $query = mysqli_query($con, "SELECT * FROM password_reset WHERE password_key = '$pkey' AND validity > $time AND `used_status` = 0");
    $row = mysqli_fetch_assoc($query);
    echo "<html><head><!-- Google ReCAPTCHA v2 Javascript library --><script src='https://www.google.com/recaptcha/api.js'></script>";
    echo "<!-- Insert CSS Link here eg: <link href=\"style.css\" rel=\"stylesheet\" type=\"text/css\" /> --></head>";
    echo "<body><form method=\"POST\" action=\"\"><table border=\"0\" align=\"center\" cellpadding=\"3\">";
    echo "<tr><td colspan=\"2\" align=\"center\">Enter your new password below</td></tr>";
    echo "<tr><td align=\"right\">Password:</td><td><input type=\"PASSWORD\" name=\"password1\" required></tr>";
    echo "<tr><td align=\"right\">Repeat Password:</td><td><input type=\"PASSWORD\" name=\"password2\" required></tr>";
    echo "<input type=\"hidden\" name=\"username\" value=\"" . $row['username'] . "\">";
    echo "<tr><td align=\"center\" colspan=\"2\"><div class=\"g-recaptcha\" style=\"display: inline-block;\" data-sitekey=\"6Le-VdcUAAAAAMb43VWM8WeDlWv0v_KSE08Aye5v\"></div></td></tr>";
    $query3 = mysqli_query($con, "SELECT * FROM maintenance");
    $row3   = mysqli_fetch_assoc($query3);
    if ($row3['status'] == 0) {
        echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"SUBMIT\" name=\"submit\" value=\"Register\" required /></td></tr>";
    } else {
        echo "<tr><td colspan=\"2\" align=\"center\"><input type=\"SUBMIT\" name=\"submit\" disabled value=\"Registration Disabled\" required /><br>The game is currently under maintenance</td></tr>";
    }
    echo "</table></form><center>";
}

if (isset($_POST['submit'])) {
    //Check if password is valid
    $error     = NULL;
    $pkey      = $_GET['pkey'];
    $password1 = mysqli_real_escape_string($con, stripslashes($_POST['password1']));
    $password2 = mysqli_real_escape_string($con, stripslashes($_POST['password2']));
    $username  = mysqli_real_escape_string($con, stripslashes($_POST['username']));
    //The following variables are relating to Google's reCAPTCHA v2 bot-identification mechanism
    $secret    = 'some secret';
    $captcha   = trim($_POST['g-recaptcha-response']);
    $ip        = $_SERVER['REMOTE_ADDR'];
    $url       = "https://www.google.com/recaptcha/api/siteverify?secret={$secret}&response={$captcha}&remoteip={$ip}";
    $options   = array(
        'ssl' => array(
            'cafile' => 'Certificates/cacert.pem',
            'verify_peer' => true,
            'verify_peer_name' => true
        )
    );
    $context   = stream_context_create($options);
    $res       = json_decode(file_get_contents($url, FILE_TEXT, $context));
    $query     = mysqli_query($con, "SELECT * FROM maintenance");
    $row       = mysqli_fetch_assoc($query);
    
    //Grab username associated with the pkey (password reset)
    
    $query2 = mysqli_query($con, "SELECT * FROM players WHERE username = ");
    
    if ($password1 != $password2) {
        $error .= "<br>Your passwords to not match";
    } elseif ($password1 != mysqli_real_escape_string($con, $password1)) {
        $error .= "<br>Your password contains illegal characters";
    } elseif (strlen($password1) < 8) {
        $error .= "<br>Your password must be at least 8 characters";
    } elseif (strlen($password1) > 40) {
        $error .= "<br>Your password cannot be more than 40 characters";
    } elseif (!preg_match("#[0-9]+#", $password1)) {
        $error .= "<br>Your password must contain at least 1 number";
    } elseif (!preg_match("#[a-z]+#", $password1)) {
        $error .= "<br>Your password must contain at least 1 lower case letter";
    } elseif (!preg_match("#[A-Z]+#", $password1)) {
        $error .= "<br>Your password must contain at least 1 capital latter";
    } elseif (!preg_match("#\W+#", $password1)) {
        $error .= "<br>Your password must contain at least 1 symbol";
    } elseif (!isset($captcha)) {
        $error .= "<br>Something went wrong with the reCAPTCHA verification";
    } elseif (!$res->success) {
        $error .= "<br>You failed the reCAPTCHA verification";
    } else {
        $hashed_password = password_hash($password1, PASSWORD_DEFAULT);
        $query3          = mysqli_query($con, "UPDATE `players` SET `password` = '$hashed_password' WHERE `username` = '$username'");
        $query4          = mysqli_query($con, "UPDATE `password_reset` SET `used_status` = 1 WHERE `username` = '$username'");
        echo "You have successfully changed your password.  Login <a href=index.php>here</a>";
    }
    if ($error != NULL) {
        echo $error;
        password_reset_form($con, $pkey, time());
    }
}

elseif (isset($_GET['vkey'])) { //Verification of email; if vkey is set then check if the verification key is valid
    $vkey = $_GET['vkey'];
    if ($vkey == "new") { //Player just registered, leave a message to tell them to verify
        echo "Thank you for registering. Please check your email and verify your account. Login <a href=index.php>here</a>";
    } else {
        //Check if an account exists with the verrification key and the account is unverified
        $query = mysqli_query($con, "SELECT verification_key, verification_status FROM players WHERE verification_status = 0 and verification_key = '$vkey' LIMIT 1");
        if (mysqli_num_rows($query) == 1) {
            //Validate email as there is one valid entry with the same verification key and non-verified status
            $update = mysqli_query($con, "UPDATE players SET verification_status = 1 WHERE verification_key = '$vkey' LIMIT 1");
            echo "Thank you for verifying your email. Please login <a href=index.php>here</a>";
        } else {
            //Check if verification has already occured
            $query = mysqli_query($con, "SELECT verification_key, verification_status FROM players WHERE verification_status = 1 and verification_key = '$vkey' LIMIT 1");
            if (mysqli_num_rows($query) == 1) {
                echo "This account is already verified. Login <a href=index.php>here</a>";
            } else {
                echo "The validation process did not work. Pleae register <a href=register.php>here</a>";
            }
        }
    }
} elseif (isset($_GET['pkey'])) {
    $time  = time();
    $pkey  = $_GET['pkey'];
    $query = mysqli_query($con, "SELECT * FROM password_reset WHERE password_key = '$pkey' AND validity > $time AND `used_status` = 0");
    if (mysqli_num_rows($query) == 0) { //Password key doesn't exist or validity is off or the key has been used
        $query2 = mysqli_query($con, "SELECT * FROM password_reset WHERE password_key = '$pkey'");
        if (mysqli_num_rows($query2) == 0) { //password key is invalid
            echo "The password reset key is invalid";
        } else { //password key exists, check if its expired or used or not
            $row2 = mysqli_fetch_assoc($query2);
            if ($row2['used_status'] != 0) {
                echo "This password reset link has already being used";
            } elseif ($row2['validity'] <= $time) {
                echo "This password reset link has expired. Please reset your password again";
            } else {
                echo "An unknown error occurred";
            }
        }
    } else { //password reset key is valid and not expired and unused
        password_reset_form($con, $pkey, time());
    }
} else { //User has entered this page without vkey or pkey valid
    die("Something went wrong");
}
?>