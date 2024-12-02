<?php
/****************************************/
/*            BoomyRPG script           */
/*           Written by Boomy           */
/*         Common PHP Functions         */
/****************************************/

//All these functions require config.php to work
//Specifically; they require the $special_key (encryption key) and $con (database connection) from config.php to work

//This constant is used to determine time (in UNIX format)
define("TIME", time());

//Show Debug text
define("DEBUG", true);

function check_user($con, $secret_key) { //This function checks if the user is logged in
    $player_id = $_SESSION['player_id'];
    $session_secret = $_SESSION['session_secret'];
    
    if(!isset($player_id) || !isset($session_secret)) {
        header("Location: index.php"); 
        exit;
    }
    else { //SESSION variables have been set, lets check them if they are valid or not
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $calculated_session_secret =  openssl_decrypt($player_id, 'aes-256-cbc', $secret_key, 0, $iv);
        if ($calculated_session_secret != $session_secret) { //Stored SESSION variable for the secret does not match
            session_unset(); 
            session_destroy(); 
            header("Location: index.php"); 
            exit;
        }
        else { //Secret key checks out so login the user
           $query = mysqli_query($con, "SELECT * FROM players WHERE id = '$player_id'");
           //echo mysqli_error($con);
            if(!$query || mysqli_num_rows($query) == 0) { //Player id does not exist
                session_unset(); 
                session_destroy(); 
                header("Location: index.php"); 
                exit;
            }
            $row = mysqli_fetch_assoc($query);
            $time = time();
            
            if($row['current_login_time'] < $time - 60 * 60 * 4) { //More than 4 hours since last login
                session_unset(); 
                session_destroy(); 
                header("Location: index.php?error=timeout");
            }

            if($row['last_active'] < $time - 60 * 60 * 0.5) { //More than 0.5 hours since last activity
                session_unset(); 
                session_destroy(); 
                header("Location: index.php?error=login_expiry"); 
                exit;
            }            
            
            $query4 = mysqli_query($con, "SELECT * FROM maintenance");
            $row4 = mysqli_fetch_assoc($query4);
            
            //Check if game is in maintenancem doe
            if($row4['status'] != 0) { 
                session_unset(); 
                session_destroy(); 
                header("Location: index.php"); 
                exit;
            }
            
            $query2 = mysqli_query($con, "UPDATE players SET last_active = '$time' WHERE id = '$player_id'");
            
            //The following databse query grabs all relevant information about the player so it can be used by the php script
            $query3 = mysqli_query($con, "SELECT * FROM players WHERE id = $player_id LIMIT 1");
            $player_query = mysqli_fetch_assoc($query3);
            $player = new StdClass; 
            foreach($player_query as $key => $value) { 
                $player->$key = $value; 
            }
            return $player;
        }
    }
}

function navigationBar($player) {

if($player->id == 1 || $player->rank == "administrator") {
    $extra = "<li class=\"nav-item\"><a class=\"nav-link\" href=\"manager.php\">File Explorer</a></li><li class=\"nav-item\"><a class=\"nav-link\" href=\"adminer.php\">Database</a></li>";
} else {
    $extra = "";
}

?>

<nav class="navbar navbar-expand-sm bg-dark navbar-dark">
  <a class="navbar-brand" href="home.php">Boomy Online</a>
      <a class="btn btn-primary" href="home.php?action=logout" role="button">Logout</a>
  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarText">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="navbarText">
    <ul class="navbar-nav mr-auto">
      <li class="nav-item active">
        <a class="nav-link" href="battle.php">Battle</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="skills.php">Skills</a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="#">Items</a>
      </li>
    <?php echo $extra; ?>
    </ul>
    <span class="navbar-text">
      <span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Gold is the universal currency used for services and trading of goods"><?php echo "Gold: " . $player->gold; ?></span>
      <span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Energy is used for various actions in-game"><?php echo "Energy: " . $player->energy . " / " . $player->maxenergy; ?></span>
    </span>
  </div>
</nav>

<?php
}
?>