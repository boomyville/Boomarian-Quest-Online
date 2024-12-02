<?php

/****************************************/
/*            BoomyRPG script           */
/*           Written by Boomy           */
/*            Email via PHP             */
/****************************************/

// This script is used to email the player (it is based off PHPMailer https://github.com/PHPMailer/PHPMailer)
// The following variables will be used by this script:
// $email_user: Unencrypted email of recipient
// $email_username: Username of email recipient 
// $Subject: Subject of the email
// $body = HTML code for the body (contents) of the email

// The user will need to update the following values such that an email is sent:
// $mail->Host: SMTP server (smtp.gmail.com would be a good one to use)
// $sender: Email that is used to send emails from
// $sender_name: Name of the sender 
// $mail->Password: Password to the email (only if using insecure app usage)

// Advanced OAUTH2 Email verification:
// If you do use gmail, you will have to enable less secure app usage: https://myaccount.google.com/lesssecureapps)
// If Google keeps blocking less secure app usage, consider "secure app access" via https://github.com/PHPMailer/PHPMailer/wiki/Using-Gmail-with-XOAUTH2
// Normally you would install composer then send the following commands: composer require league/oauth2-client | composer require stevenmaguire/oauth2-microsoft | composer require hayageek/oauth2-yahoo
// These would install the required files in the vendor folder which can then be used by PHPMailer to setup authetication requests
// For simplicity; these files are already included (no need for composer though you can manually update these files with composer)
// Simply edit the get_oauth_token.php page with the required authetication keys (based on your email provider) and follow steps detailed in the PHPMailer readme
// If you do want to get these files yourself without access to composer on the server then follow these instructions:
    // Install XAMPP on windows; preferrably c:/XAMPP install location
    // Install Composer on windows; selecting the php.exe that is provided by XAMPP
    // Run CMD (Command line) and type in composer 
    // This should provide some output to confirm that it works
    // Now run composer require league/oauth2-client | composer require stevenmaguire/oauth2-microsoft | composer require hayageek/oauth2-yahoo
    // Find the vendor folder; for me it was c:/Users/Username but it could also be in your c:/XAMPP folder
    // Copy the vendor folder onto your server with the PHPMailer folder. 
    // Adjust the require 'vendor/autoload.php' line in the get_oauth_token.php page to the actual vendor folder
    

// Import PHPMailer classes into the global namespace
// These must be at the top of your script, not inside a function
// Set SMTPDebug to 2 to show an debug output (only for testing purposes). Set to 0 once this page is publushed

//$dns=array("8.8.8.8","8.8.4.4");
//var_export (dns_get_record ( "smtp.gmail.com" ,  DNS_ALL , $dns ));

set_include_path('PHPMailer/src/');

//Include these require commands if using composer or have copied a vendor folder to your PHPMailer 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP; 
use PHPMailer\PHPMailer\OAuth; //Only include if using OAUTH2 authentication with mail provider
use League\OAuth2\Client\Provider\Google; // Alias the League Google OAuth2 provider class

//require 'PHPMailer/src/Exception.php'; //Include these require commands if not using composer or not copied a vendor folder to your PHPMailer 
//require 'PHPMailer/src/PHPMailer.php'; //Include these require commands if not using composer or not copied a vendor folder to your PHPMailer 
//require 'PHPMailer/src/SMTP.php'; //Include these require commands if not using composer or not copied a vendor folder to your PHPMailer 

/* Include the Composer generated autoload.php file. Comment out if not using composer */
require 'PHPMailer/vendor/autoload.php';

//SMTP needs accurate times, and the PHP time zone MUST be set
//This should be done in your php.ini, but this is how to do it if you don't have access to that
date_default_timezone_set('Australia/Melbourne');

$sender = 'put your email here';
$sender_name = 'Administrator';

//Use the following template if using Gmail OAUTH

//Create a new PHPMailer instance
$mail = new PHPMailer;

//Tell PHPMailer to use SMTP
$mail->isSMTP();

//Enable SMTP debugging
// SMTP::DEBUG_OFF = off (for production use)
// SMTP::DEBUG_CLIENT = client messages
// SMTP::DEBUG_SERVER = client and server messages
$mail->SMTPDebug = SMTP::DEBUG_OFF;

//Set the hostname of the mail server
$mail->Host = 'smtp.gmail.com';

//Set the SMTP port number - 587 for authenticated TLS, a.k.a. RFC4409 SMTP submission
$mail->Port = 587;

//Set the encryption mechanism to use - STARTTLS or SMTPS
$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

//Whether to use SMTP authentication
$mail->SMTPAuth = true;

//Set AuthType to use XOAUTH2
$mail->AuthType = 'XOAUTH2';

//Fill in authentication details here
//Either the gmail account owner, or the user that gave consent
$email = 'put your email here';
$clientId = 'something.apps.googleusercontent.com';
$clientSecret = 'put in your token';

//Obtained by configuring and running get_oauth_token.php
//after setting up an app in Google Developer Console.
//This requires you adjusting the PHPMailer/get_oauth_token.php file to have the above credentials obtained from Google Cloud Console
//After that has been done, run the PHPMailer/get_oauth_token.php file to get this refresh token
$refreshToken = 'another token';

//Create a new OAuth2 provider instance
$provider = new Google(
    [
        'clientId' => $clientId,
        'clientSecret' => $clientSecret,
    ]
);

//Pass the OAuth provider instance to PHPMailer
$mail->setOAuth(
    new OAuth(
        [
            'provider' => $provider,
            'clientId' => $clientId,
            'clientSecret' => $clientSecret,
            'refreshToken' => $refreshToken,
            'userName' => $email,
        ]
    )
);

//Set who the message is to be sent from
//For gmail, this generally needs to be the same as the user you logged in as
$mail->setFrom($sender, $sender_name);

if (!isset($email_user)) { $email_user = $sender; }
if (!isset($email_username)) { $email_username = 'Admin'; }	

//Set who the message is to be sent to
$mail->addAddress($email_user, $email_username);

if (!isset($subject)) { $subject = 'Notification'; }
if (!isset($body)) { $body = 'Hello from the administrator!'; }	

//Set the subject line
$mail->Subject = $subject;
$mail->Body = $body;
    
//Read an HTML message body from an external file, convert referenced images to embedded,
//convert HTML into a basic plain-text alternative body
$mail->CharSet = PHPMailer::CHARSET_UTF8;
//$mail->msgHTML(file_get_contents('contentsutf8.html'), __DIR__);

//Replace the plain text body with one created manually
$mail->AltBody = strip_tags($body);

//Attach an image file
//$mail->addAttachment('images/phpmailer_mini.png');

//send the message, check for errors
if (!$mail->send()) {
    echo 'Mailer Error: '. $mail->ErrorInfo;
} else {
    echo 'Verification sent to the email of ' . $email_user . '! ';
}

//Use the following template if not using Gmail OAUTH

/*===================================================================================================================================

$mail = new PHPMailer(true);                              // Passing `true` enables exceptions

try {
    //Server settings
    $mail->SMTPDebug = 0;                                 // Enable verbose debug output if set to 1  (or true)
    $mail->isSMTP();                                      // Set mailer to use SMTP
    $mail->Host = 'smtp.gmail.com';                       // Specify main and backup SMTP servers
    $mail->SMTPAuth = true;                               // Enable SMTP authentication
    $mail->Username = $sender;           			      // SMTP username
    $mail->Password = 'frui7324dn';                       // SMTP password
    $mail->SMTPSecure = 'tls';                            // Enable TLS encryption, `ssl` also accepted
    $mail->Port = 587;                                    // TCP port to connect to

$mail->SMTPOptions = array(
'ssl' => array(
'verify_peer' => false,
'verify_peer_name' => false,
'allow_self_signed' => true
)
);
    //Recipients
    $mail->setFrom($sender, $sender_name);        			    //This is the email your form sends from
	if(!isset($email)) { $email = $sender; }
	if(!isset($email_username)) { $email_username = $sender_name; }	
    $mail->addAddress($email, $email_username); 				// Add a recipient address
    //$mail->addAddress('contact@example.com');                 // Name is optional
    //$mail->addReplyTo('info@example.com', 'Information');
    //$mail->addCC('cc@example.com');
    //$mail->addBCC('bcc@example.com');

    //Attachments
    //$mail->addAttachment('/var/tmp/file.tar.gz');         // Add attachments
    //$mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name

    //Content
    $mail->isHTML(true);                                  // Set email format to HTML
	if (!isset($subject)) { $subject = 'Notification'; }
	if (!isset($body)) { $body = 'Hello from the administrator!'; }	
    $mail->Subject = $subject;
    $mail->Body    = $body;
    //$mail->AltBody = 'This is the body in plain text for non-HTML mail clients';

    $mail->send();
    echo 'An email has been sent. ';
} catch (Exception $e) {
    //echo 'Message could not be sent.';
   // echo 'Mailer Error: ' . $mail->ErrorInfo;
}

*///===================================================================================================================================

?>