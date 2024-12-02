<?php
/****************************************/
/*            BoomyRPG script           */
/*           Written by Boomy           */
/*      Verification page (email)       */
/****************************************/

//This page is used to verify emails sent for registration purposes as well as password sm/

include("config.php"); //Includes connection to the database
include("functions.php"); //Includes connection to the database

//This function is basically placed in every 'members-only' page to check if the user has been logged in or not
//It uses temporary variables (SESSION) to store login data locally
$player = check_user($con, $secret_key);

//Show Navigation bar (requires $row information pulled from a mysqli query)
navigationBar($player);

//Grab last login details
if ($player->previous_login_time != 0) {
$last_login = date("F j, Y, g:i a", $player->previous_login_time);
}
else {
$last_login = "Never before";    
}

//Process log out actions by destroying the SESSION variable storing login data
if(isset($_GET['action'])) {
    if($_GET['action'] == "logout") {
        //Clear user's session data
        session_unset();
        session_destroy();
        //Redirect to index
        header("Location: index.php");
        exit;
    }    
}

?>

<html>
<head>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
<link rel="stylesheet" href="style.css">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
</head>

<body>

<div class="row"><div class="col-md-12 linebreak"></div></div>

<div class="container col-md-9"> 
<div class="row my-0">
<div class="col-sm-2 text-left"> Username: </div>
<div class="col-sm-2 text-left"> <?php echo $player->username; ?>  </div>
<div class="col-sm-2"></div>
<div class="col-sm-2 text-left"> Rank: </div>
<div class="col-sm-2 text-left"> <?php echo  $player->rank; ?>  </div>
<div class="col-sm-2"></div></div>
<div class="row my-0">
<div class="col-sm-2 text-left"> Date Registered: </div>
<div class="col-sm-3 text-left"> <?php echo date("F j, Y, g:i a", $player->date_registered); ?>  </div>
<div class="col-sm-1"></div>
<div class="col-sm-2 text-left"> Last Login: </div>
<div class="col-sm-3 text-left"> <?php echo $last_login; ?>  </div>
</div></div>
<div class="row"><div class="col-md-12 linebreak-big"></div></div>
<div class="container col-md-9"> 
<div class="row my-0">
<div class="col-sm-3 text-left"><h4>Player Stats</h></div></div>
<div class="row my-0">
<div class="col-sm-2 text-left"><span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Health points represents the points of damage that can be sustained before incapacitation">Health:  </span></div>
<div class="col-sm-1 text-left"><?php echo $player->hp  . "/" . $player->maxhp ; ?>  </div>
<div class="col-sm-2 text-left"><span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="The Win / Loss ratio represents how many battles you have won / lost against others">Win / Loss:  </span></div>
<div class="col-sm-1 text-left"><?php echo $player->kills  . "/" . $player->deaths ; ?>  </div>
<div class="col-sm-2 text-left"><span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Premium status gives players bonus features and extra resources">Premium Status:  </span></div>
<div class="col-sm-1 text-left"><?php echo $player->premium_days  . " days"; ?>  </div>
</div>
<div class="row my-0">
<div class="col-sm-2 text-left"><span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Attack represents how much damage is done by physical attacks">Attack:  </span></div>
<div class="col-sm-1 text-left"><?php echo $player->attack ; ?>  </div>
<div class="col-sm-3 text-left"></div>
<div class="col-sm-2 text-left"><span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Players in the penalty box cannot make posts in the forums">Penalty Box:  </span></div>
<div class="col-sm-1 text-left"><?php echo $player->ban_days  . " days"; ?>  </div>
</div>
<div class="row my-0">
<div class="col-sm-2 text-left"><span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Defence represents how much damage is reduced by physical attacks">Defence: </span> </div>
<div class="col-sm-1 text-left"><?php echo $player->defence ; ?>  </div></div>
<div class="row my-0">
<div class="col-sm-2 text-left"><span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Magic represents how much damage is done by magical attacks">Magic: </span> </div>
<div class="col-sm-1 text-left"><?php echo $player->magic ; ?>  </div></div>
<div class="row my-0">
<div class="col-sm-2 text-left"><span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Resistance represents how much damage is reduced by magical attacks">Resistance:  </span></div>
<div class="col-sm-1 text-left"><?php echo $player->resistance ; ?>  </div></div>
<div class="row my-0">
<div class="col-sm-2 text-left"><span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Agility affects how many actions can be done per turn in battle as well as evasion of particular actions">Agility:  </span></div>
<div class="col-sm-1 text-left"><?php echo $player->agility ; ?>  </div></div>
<div class="row my-0">
<div class="col-sm-2 text-left"><span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Dexterity influences the probability of critical hits as well as the accuracy of particular actions">Dexterity:  </span></div>
<div class="col-sm-1 text-left"><?php echo $player->dexterity ; ?>  </div></div>
<div class="row my-0">
<div class="col-sm-2 text-left"><span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Power is an all-encompassing stat that represents increased damage by any offensive action">Power:  </span></div>
<div class="col-sm-1 text-left"><?php echo $player->power ; ?>  </div></div>
<div class="row my-0">
<div class="col-sm-2 text-left"><span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Fortitude represents how much extra block is generated by actions">Fortitude:  </span></div>
<div class="col-sm-1 text-left"><?php echo $player->fortitude ; ?>  </div></div>
<div class="row my-0">
<div class="col-sm-2 text-left"><span rel="tooltip" data-toggle="tooltip" data-placement="auto" title="Stat points are gained during level up and can be used to increase a particular stat">Stat Points:  </span></div>
<div class="col-sm-1 text-left"><?php echo $player->stat_points ; ?>  </div></div>


<div class="container col-md-10"> <div class="row my-0">
</div></div>
</body>
