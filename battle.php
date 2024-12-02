<?php
/****************************************/
/*            BoomyRPG script           */
/*           Written by Boomy           */
/*            Battle Engine             */
/****************************************/

//This page runs battles between players and monsters
//It also has a section to allow admin users to add monsters

include("config.php"); //Includes connection to the database
include("functions.php"); //Includes connection to the database 
include("skills.php"); //Includes connection to the database

//Some modifiable variables
define("BATTLE_EXPIRY", 60 * 60 * 48);
$minrounds = 30;
$max_attack = 1; //max number of attacks playable per turn
$output = "";
$max_select = 3;

//This function is basically placed in every 'members-only' page to check if the user has been logged in or not
//It uses temporary variables (SESSION) to store login data locally
$player = check_user($con, $secret_key);

//Show Navigation bar
navigationBar($player);

//========================
//Battle-related functions
//========================

//This function checks if a battle can occur and returns either true or spits out an error
//It requires a connection to the databse
//Type is either player or monster; determines which table to check in the database to confirm if a battle can occur 
//Conditions that are checked including checking is enemy id exists and if there is a level mis-match between combatants 
//If autobattle is set; check if player meets conditions to fight (check if player has enough health and energy)
function battle_preliminary_check($con, $player, $enemy, $type, $autobattle) { 
    if ($type == 'monster' ) {
        $query = mysqli_query($con, "SELECT * from monsters WHERE id = '$enemy'"); //***add checking for environment later on with a "AND location = $player_location"
    }
    elseif ($type == 'player') {
        $query = mysqli_query($con, "SELECT * from players WHERE id = '$enemy'");
    }
    else {
        return "The enemy you targeted was imaginary...";
        exit;
    }
    if (mysqli_num_rows($query) == 0) {
        //Enemy does not exist
         return "The " . $type. " you tried to attack could not be found!";
         exit;
    }
    //Grab enemy info and run a few checks
    $enemy_query = mysqli_fetch_assoc($query); //Grab enemy info
        
    if($player->energy  < 0.1 * $player->maxenergy && $autobattle = 'auto') {
        //Player lacks enough enegy to engage in battle
        return "You lack the energy to engage in battle! Come back when you have rested a bit!";
        exit;
    }
    if($player->hp  < 0.2 * $player->maxhp && $autobattle = 'auto') {
        //Player lacks enough health to engage in battle
        return "You are too injured to engage in engage in battle! Come back when you have rested a bit!";
        exit;
    }                
    if( $enemy_query['level']   < $player->level  * 0.75 - 5 && $autobattle = 'auto') {
        //Player level too high
        return "The " . $type . " you tried to attack was too intimidated by your high level and ran away!";
        exit;
    }
    if( $enemy_query['level'] > $player->level  * 1.25 + 5 && $autobattle = 'auto') {
        //Enemy level too high
        return "The " . $type . " you tried to attack is far too strong for you! Your attacks do nothing and the monster showed mercy by leaving!";
        exit;
    }
}

//This function creates an enemy and its associated variables based on information from the database 
function create_enemy($con, $enemy_id, $type) {
    if ($type == 'monster' ) {
        $query = mysqli_query($con, "SELECT * from monsters WHERE id = '$enemy_id'"); //***add checking for environment later on with a "AND location = $player_location"
    }
    elseif ($type == 'player') {
        $query = mysqli_query($con, "SELECT * from players WHERE id = '$enemy_id'");
    }
    if(!$query) {
        //Error occurred
        die();
    } else {
        $enemy_query = mysqli_fetch_assoc($query); //Grab enemy info
        $enemy = new StdClass;
        foreach($enemy_query as $key => $value) {
            $enemy->$key = $value;
        }
        return $enemy;
    }
}

//This function sets the basic variables for both the attacker (player) and defender (enemy)
//This function will also add on equipment bonuses if applicable (if defender is a player)
function battle_start($player, $enemy) {
    
    //Some modifiable variables
    $max_combo = 3;
    $max_crit = 50; //max critical hit rate is 50%
    $base_miss = 10; //base accuracy is 90% 
    $max_miss = 80; //all attacks have minimum accuracy of 20%
    $min_miss = 5; //all attacks have maximum accuracy of 95%

    //Calculate player stats based on stances and bonuses from equipment
    $player->atk = ($player->attack  * $player->atk_adj  * 0.01) + 0; //add equipment bonuses here 
    $player->def = ($player->defence  * $player->def_adj  * 0.01) + 0; //add equipment bonuses here 
    $player->mag = ($player->magic  * $player->mag_adj  * 0.01) + 0; //add equipment bonuses here 
    $player->res = ($player->resistance  * $player->res_adj  * 0.01) + 0; //add equipment bonuses here 
    $player->agi = ($player->agility  * $player->agi_adj  * 0.01) + 0; //add equipment bonuses here 
    $player->dex = ($player->dexterity  * $player->dex_adj  * 0.01) + 0; //add equipment bonuses here 
    $player->pow = ($player->power  * $player->pow_adj  * 0.01) + 0; //add equipment bonuses here 
    $player->fort = ($player->fortitude  * $player->for_adj  * 0.01) + 0; //add equipment bonuses here 
    $player->acc =  0; //add equipment bonuses here 
    $player->eva = 0; //add equipment bonuses here 
    $player->crit = 0; //add equipment bonuses here
    $player->block = 0; //add equipment bonuses here
    $player->battle_energy = 0; // $player->battle_energy_reset;
    $player->max_energy = 15; //Maximum energy a combatant can have)
    $player->battle_turns = 0;
    $player->status = array(); //An array that stores buff/debuffs for regular stats
    
    //Extra variables (passive effects)
    $player->block_loss = -1; //If block_loss is set to -1 then block is set to 0 at the start of the next turn. If its set to 0 then no block loss; if set to 10 then lose 10 block
    
    //Extra battle statistics
    
    $player->skills_played = array();
    $player->damage_given = array(); //damage_given['total'], damage_given['blocked'], damage_given['unblocked'], damage_given['physical'], damage_given['magical'], damage_given['damage_frequency'], damage_given['fully_blocked_frequency'], damage_given['critical_damage'], damage_given['critical_frequency']
    $player->damage_taken = array(); //damage_taken['total'], damage_taken['blocked'], damage_taken['unblocked'], damage_taken['physical'], damage_taken['magical'], damage_given['damage_frequency'], damage_given['fully_blocked_frequency'], damage_given['critical_damage'], damage_given['critical_frequency']
    $player->skill_effect = array();
    $player->turn_effect = array();
    $player->block_generated = 0;
    
    //Pull enemy stats
    if(!isset($enemy->maxhp)) { $enemy->maxhp = $enemy->hp; }
    $enemy->atk = $enemy->attack; 
    $enemy->def = $enemy->defence; 
    $enemy->mag = $enemy->magic; 
    $enemy->res = $enemy->resistance; 
    $enemy->agi = $enemy->agility; 
    $enemy->dex = $enemy->dexterity; 
    $enemy->pow = $enemy->power;
    $enemy->fort = $enemy->fortitude; 
    $enemy->acc = $enemy->accuracy; 
    $enemy->eva = $enemy->evasion; 
    $enemy->crit = $enemy->critical;
    $enemy->block = 0;
    $enemy->battle_energy = 0; //$enemy->battle_energy_reset;
    $enemy->max_energy = 15; //Maximum energy a combatant can have)
    $enemy->battle_turns = 0;
    $enemy->status = array(); //An array that stores buff/debuffs for regular stats
    
   //Extra variables (passive effects)
    $enemy->block_loss = -1; 
    
    //Extra battle statistics
    $enemy->skills_played = array();
    $enemy->damage_given = array(); //damage_given['total'], damage_given['blocked'], damage_given['unblocked'], damage_given['physical'], damage_given['magical'], damage_given['damage_frequency'], damage_given['fully_blocked_frequency'], damage_given['critical_damage'], damage_given['critical_frequency']
    $enemy->damage_taken = array(); //damage_taken['total'], damage_taken['blocked'], damage_taken['unblocked'], damage_taken['physical'], damage_taken['magical'], damage_taken['damage_frequency'], damage_taken['fully_blocked_frequency'], damage_taken['critical_damage'], damage_taken['critical_frequency']
    $enemy->skill_effect = array();
    $enemy->turn_effect = array();
    $enemy->block_generated = 0;
    

    //Attacks per turn
    $player->combo = min($max_combo * 100, ceil($player->agi * 100 / $enemy->agi)); 
    $enemy->combo = min($max_combo * 100, ceil($enemy->agi * 100 / $player->agi));
    
    //Accuracy of attacks
    $player->miss = max($min_miss, min($max_miss, ($enemy->agi / $player->dex * 10) -  $player->acc + $enemy->eva));
    $enemy->miss = max($min_miss, min($max_miss, ($player->agi / $enemy->dex * 10) + $player->eva - $enemy->acc));
    
    //Critical rate
    $player->critmax = min($max_crit, $player->dex / ($enemy->dex + $enemy->level) *10 + $player->crit);
    $enemy->critmax = min($max_crit, $enemy->dex / ($player->dex + $player->level) *10 + $enemy->crit);
}

//This function creates an $output_string that is used for the modal displays, which display info on the $unit's skills (Such as the deck or discard pile)
//This function is only used with battle_display_info()
function display_skill($unit, $location_string, $skill_data, &$output_string) {
    if(count($unit->$location_string) == 0) { 
        $output_string = "<span class=\"text-danger\" style=\"line-height: 4em; padding-left:1.5em\">Empty</span>";
    } else {
        $output_string = "<div class=\"container-large mx-auto px-0 py-3\"><div class=\"card-deck\">";
        for($i = 0; $i < count($unit->$location_string); $i++) {
            $array_search_key = array_search($unit->$location_string[$i], array_column($skill_data, 0)); 
            $skill_info =  $skill_data[$array_search_key];
            $energy_multiplier = 1;
            $skill_image_name = str_replace(" ", "", $skill_info[1]);
            $output_string .= "<div class=\"card bg-dark\" style=\"min-width: 160px;\"><img class=\"card-img-top\" src=\"Images/" .  $skill_image_name . ".png\" alt=\"" . $skill_info[1] . "\"><div class=\"card-img-overlay\" style=\"height:126px\"><h2>" . $skill_info[6] * $energy_multiplier . "</h2></div><div class=\"card-body\"><h5 class=\"card-title\">"  . $skill_info[1] . "</h5><p class=\"card-text\"><small class=\"text\">"  . $skill_info[2] . "</small></div></div>";            
        }
        $output_string .= "</div></div>";
    }  
}

//This function shows various battle statistics pertaining to the player and enemy
//Also creates cards that represent skills that can be selected
//This function is only used for turn-based battles
function battle_display_info($con, $player, $enemy, $skill_data, &$output) { 
     
    if($player->hp <= 0 || $enemy->hp <= 0) {
        $output.= "<br>The battle has ended!";
    } elseif (check_user_input($con, $output, $player, $enemy->id, "check") !== false) {
        check_user_input($con, $output, $player, $enemy->id, "display");
    } else {
     
        //Display Player data to the player
        $output .= "<br>";
                    
        //Grab data in regards to the user's draw pile, discard pile and exhaust pile
        //Also grab data in regards to the enemy's hand, draw pile, discard pile and exhaust pile
        display_skill($player, "draw_pile", $skill_data, $drawpile); 
        display_skill($player, "skill_discard", $skill_data, $discardpile); 
        display_skill($player, "skill_exhaust", $skill_data, $exhaustpile); 
        display_skill($enemy, "skill_hand", $skill_data, $enemyhand); 
        display_skill($enemy, "draw_pile", $skill_data, $enemydrawpile); 
        display_skill($enemy, "skill_discard", $skill_data, $enemydiscardpile); 
        display_skill($enemy, "skill_exhaust", $skill_data, $enemyexhaustpile); 
        
        //Create container to display battle info to the user. A set height is used to align elements
        $output .= "<div class=\"container\"><div class=\"row justify-content-md-center\">";
        
        //Display buttons to trigger the modal windows displaying various info . We use bootstrap here with the mix-and-match grid system to alter the stacking of columns based on screen size (basically col-4 col-md-2 means at medium or smaller display windows use col-4 otherwise use col-2; noting that col4 is twice the width of col-2)
        $output .= "<div class=\"col-6 col-lg-2 text-center\"><button type=\"button\" class=\"btn btn-primary my-1 btn-block\" data-toggle=\"modal\" data-target=\"#deck\">Deck</button><button type=\"button\" class=\"btn btn-primary my-1 btn-block\" data-toggle=\"modal\" data-target=\"#discard\">Discard</button><button type=\"button\" class=\"btn btn-primary my-1 btn-block\" data-toggle=\"modal\" data-target=\"#exhaust\">Exhaust</button><a href=\"battle.php?action=attack_monster&id=" . $enemy->id . "&turn=end\" class=\"btn btn-primary my-1 btn-block\" role=\"button\">End Turn</a></div>";
       
        //Display a ring to represent the user's health. A style = float right is added to align the ring to the right (mainly for mobile view)
        //Icon is acquired via https://icons.getbootstrap.com/
        $energy_icon = "<svg class=\"bi bi-server\" width=\"1em\" height=\"1em\" viewBox=\"0 0 16 16\" fill=\"currentColor\" xmlns=\"http://www.w3.org/2000/svg\"> <path d=\"M13 2c0-1.105-2.239-2-5-2S3 .895 3 2s2.239 2 5 2 5-.895 5-2z\"/> <path d=\"M13 3.75c-.322.24-.698.435-1.093.593C10.857 4.763 9.475 5 8 5s-2.857-.237-3.907-.657A4.881 4.881 0 013 3.75V6c0 1.105 2.239 2 5 2s5-.895 5-2V3.75z\"/> <path d=\"M13 7.75c-.322.24-.698.435-1.093.593C10.857 8.763 9.475 9 8 9s-2.857-.237-3.907-.657A4.881 4.881 0 013 7.75V10c0 1.105 2.239 2 5 2s5-.895 5-2V7.75z\"/> <path d=\"M13 11.75c-.322.24-.698.435-1.093.593-1.05.42-2.432.657-3.907.657s-2.857-.237-3.907-.657A4.883 4.883 0 013 11.75V14c0 1.105 2.239 2 5 2s5-.895 5-2v-2.25z\"/> </svg>";
        $shield_icon = "<svg class=\"bi bi-shield-fill\" width=\"1em\" height=\"1em\" viewBox=\"0 0 16 16\" fill=\"currentColor\" xmlns=\"http://www.w3.org/2000/svg\"> <path fill-rule=\"evenodd\" d=\"M5.187 1.025C6.23.749 7.337.5 8 .5c.662 0 1.77.249 2.813.525a61.09 61.09 0 012.772.815c.528.168.926.623 1.003 1.184.573 4.197-.756 7.307-2.367 9.365a11.191 11.191 0 01-2.418 2.3 6.942 6.942 0 01-1.007.586c-.27.124-.558.225-.796.225s-.526-.101-.796-.225a6.908 6.908 0 01-1.007-.586 11.192 11.192 0 01-2.417-2.3C2.167 10.331.839 7.221 1.412 3.024A1.454 1.454 0 012.415 1.84a61.11 61.11 0 012.772-.815z\" clip-rule=\"evenodd\"/> </svg>";
        $output .= "<div class=\"col-6 col-lg-2 text-center\"><div class=\"c100 p" . round($player->hp / $player->maxhp * 100) . " small\" style=\"float: right\"><span height:120>" . $player->hp . " | " . $player->maxhp . "</span><div class=\"slice\"><div class=\"bar\"></div><div class=\"fill\"></div></div></div><p style=\"display: block;float: right\">" . $player->block . " " . $shield_icon . " " . $player->battle_energy . " " . $energy_icon . "</p></div>";
        
        //Display the player's character (image). A margin of 8px is added to the image
        $output .= "<div class=\"col-6 col-lg-2\"><img src=\"Images/" .$player->image_path. "\" class=\"img-fluid\" style=\"margin: 8px\" alt=\"Player\"></div>";
        
         //Display the enemy's character (image)
        $output .= "<div class=\"col-6 col-lg-2\"><img src=\"Images/" .$enemy->image_path. "\" class=\"img-fluid\" style=\"float: right; margin: 8px\" alt=\"Enemy\"></div>";
        
        //Display a ring to represent the user's health
        $output .= "<div class=\"col-6 col-lg-2 text-center\"><div class=\"c100 p" . round($enemy->hp / $enemy->maxhp * 100) . " small\"><span height:120>" . $enemy->hp . " | " . $enemy->maxhp . "</span><div class=\"slice\"><div class=\"bar\"></div><div class=\"fill\"></div></div></div><p style=\"display: block;float: left\">" . $enemy->block . " " . $shield_icon . " " . $enemy->battle_energy . " " . $energy_icon . "</p></div>";
       
       //Display buttons to trigger the modal windows displaying various enemy info
        $output .= "<div class=\"col-6 col-lg-2 text-center\"><button type=\"button\" class=\"btn btn-primary my-1 btn-block\" data-toggle=\"modal\" data-target=\"#enemyHand\">Hand</button><button type=\"button\" class=\"btn btn-primary my-1 btn-block\" data-toggle=\"modal\" data-target=\"#enemyDeck\">Deck</button><button type=\"button\" class=\"btn btn-primary my-1 btn-block\" data-toggle=\"modal\" data-target=\"#enemyDiscard\">Discard</button><button type=\"button\" class=\"btn btn-primary my-1 btn-block\" data-toggle=\"modal\" data-target=\"#enemyExhaust\">Exhaust</button></div>";
         
        //Create a modal window that basically pops up data relevant to the user when a button is pressed
        //modal-xl in the modal-dialog class makes the modal window extra large (desktop view only)
        $output .= "<div class=\"modal fade\" id=\"deck\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"deckTitle\" aria-hidden=\"true\" style =\"background-color:rgba(0,0,0,0.5)\"> <div class=\"modal-dialog modal-xl modal-dialog-centered\" role=\"document\"> <div class=\"modal-content\"   style=\"background-color:rgb(40,40,40)\"> <div class=\"modal-header\"> <h5 class=\"modal-title\" id=\"deckTitle\">Deck</h5></div> <div class=\"modal-body p-0\">" . $drawpile . "</div> <div class=\"modal-footer\"> <button type=\"button\" class=\"btn btn-secondary\" data-dismiss=\"modal\">Close</button>  </div> </div> </div> </div>";
        $output .= "<div class=\"modal fade\" id=\"discard\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"discardTitle\" aria-hidden=\"true\" style =\"background-color:rgba(0,0,0,0.5)\"> <div class=\"modal-dialog modal-xl modal-dialog-centered\" role=\"document\"> <div class=\"modal-content\"   style=\"background-color:rgb(40,40,40)\"> <div class=\"modal-header\"> <h5 class=\"modal-title\" id=\"discardTitle\">Discard Pile</h5></div> <div class=\"modal-body p-0\">" . $discardpile . "</div> <div class=\"modal-footer\"> <button type=\"button\" class=\"btn btn-secondary\" data-dismiss=\"modal\">Close</button>  </div> </div> </div> </div>";
        $output .= "<div class=\"modal fade\" id=\"exhaust\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"exhaustTitle\" aria-hidden=\"true\" style =\"background-color:rgba(0,0,0,0.5)\"> <div class=\"modal-dialog modal-xl modal-dialog-centered\" role=\"document\"> <div class=\"modal-content\"   style=\"background-color:rgb(40,40,40)\"> <div class=\"modal-header\"> <h5 class=\"modal-title\" id=\"exhaustTitle\">Exhaust Zone</h5></div> <div class=\"modal-body p-0\">" . $exhaustpile . "</div> <div class=\"modal-footer\"> <button type=\"button\" class=\"btn btn-secondary\" data-dismiss=\"modal\">Close</button>  </div> </div> </div> </div>";
        $output .= "<div class=\"modal fade\" id=\"enemyHand\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"enemyHandTitle\" aria-hidden=\"true\" style =\"background-color:rgba(0,0,0,0.5)\"> <div class=\"modal-dialog modal-xl modal-dialog-centered\" role=\"document\"> <div class=\"modal-content\"   style=\"background-color:rgb(40,40,40)\"> <div class=\"modal-header\"> <h5 class=\"modal-title\" id=\"enemyHandTitle\">Deck</h5></div> <div class=\"modal-body p-0\">" . $enemyhand . "</div> <div class=\"modal-footer\"> <button type=\"button\" class=\"btn btn-secondary\" data-dismiss=\"modal\">Close</button>  </div> </div> </div> </div>";
        $output .= "<div class=\"modal fade\" id=\"enemyDeck\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"enemyDeckTitle\" aria-hidden=\"true\" style =\"background-color:rgba(0,0,0,0.5)\"> <div class=\"modal-dialog modal-xl modal-dialog-centered\" role=\"document\"> <div class=\"modal-content\"   style=\"background-color:rgb(40,40,40)\"> <div class=\"modal-header\"> <h5 class=\"modal-title\" id=\"enemyDeckTitle\">Deck</h5></div> <div class=\"modal-body p-0\">" . $enemydrawpile . "</div> <div class=\"modal-footer\"> <button type=\"button\" class=\"btn btn-secondary\" data-dismiss=\"modal\">Close</button>  </div> </div> </div> </div>";
        $output .= "<div class=\"modal fade\" id=\"enemyDiscard\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"enemyDiscardTitle\" aria-hidden=\"true\" style =\"background-color:rgba(0,0,0,0.5)\"> <div class=\"modal-dialog modal-xl modal-dialog-centered\" role=\"document\"> <div class=\"modal-content\"   style=\"background-color:rgb(40,40,40)\"> <div class=\"modal-header\"> <h5 class=\"modal-title\" id=\"enemyDiscardTitle\">Deck</h5></div> <div class=\"modal-body p-0\">" . $enemydiscardpile . "</div> <div class=\"modal-footer\"> <button type=\"button\" class=\"btn btn-secondary\" data-dismiss=\"modal\">Close</button>  </div> </div> </div> </div>";
        $output .= "<div class=\"modal fade\" id=\"enemyExhaust\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"enemyExhaustTitle\" aria-hidden=\"true\" style =\"background-color:rgba(0,0,0,0.5)\"> <div class=\"modal-dialog modal-xl modal-dialog-centered\" role=\"document\"> <div class=\"modal-content\"   style=\"background-color:rgb(40,40,40)\"> <div class=\"modal-header\"> <h5 class=\"modal-title\" id=\"enemyExhaustTitle\">Deck</h5></div> <div class=\"modal-body p-0\">" . $enemyexhaustpile . "</div> <div class=\"modal-footer\"> <button type=\"button\" class=\"btn btn-secondary\" data-dismiss=\"modal\">Close</button>  </div> </div> </div> </div>";
        
        //Debug: Add extra skills into hand
        if(DEBUG) {
            $output .= "<button type=\"button\" class=\"btn btn-primary my-1\" data-toggle=\"modal\" data-target=\"#addSkill\">Add Skill</button>";
            $query = mysqli_query($con, "SELECT * from skills");
            $result = "";
                if(!$query) {
                    //No valid skills to show
                } else {
                    while ($row = mysqli_fetch_row($query)) {
                        $result .= "<option value =\"" . $row[0] . "\">" . $row[1] . "</option>";
                    }
                }
            $form_data = "<form action=\"battle.php\" method=\"GET\" role=\"form\" class=\"form-inline p-2\"> <div class=\"form-group p-2\"><input type=\"hidden\" id=\"action\" name=\"action\" value=\"add_skill\"><input type=\"hidden\" id=\"enemy_id\" name=\"id\" value=\"" . $enemy->id ."\"> <div class=\"form-group m-2\"><select name=\"skill_id\" class=\"form-control\">" . $result . "</select></div><div class=\"form-group m-2\"><input type=\"number\" class=\"form-control\" name=\"randomizer_quantity\" placeholder=\"Add Random Skills\" id=\"randomizer_quantity\"> </div></div><div class=\"form-check form-check-inline m-2\"> <input class=\"form-check-input m-2\" type=\"radio\" name=\"location\" id=\"add_hand\" value=\"hand\" checked> <label class=\"form-check-label\" for=\"add_hand\"> Add to hand </label><input class=\"form-check-input m-2\" type=\"radio\" name=\"location\" id=\"add_deck\" value=\"deck\"><label class=\"form-check-label\" for=\"add_deck\"> Add to deck </label></div> <input class=\"form-check-input m-2\" type=\"checkbox\" name=\"purge\" id=\"purge\" value=\"true\"> <label class=\"form-check-label\" for=\"purge\"> Remove skills </label></div><div class=\"form-group\">  <button type=\"submit\" class=\"btn btn-primary m-2\">Add</button>  <button type=\"button\" class=\"btn btn-secondary\" data-dismiss=\"modal\">Close</button> </div> </form>";
            $output .= "<div class=\"modal fade\" id=\"addSkill\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"addSkillTitle\" aria-hidden=\"true\" style =\"background-color:rgba(0,0,0,0.5)\"> <div class=\"modal-dialog modal modal-dialog-centered\" role=\"document\"> <div class=\"modal-content\"   style=\"background-color:rgb(40,40,40)\"> <div class=\"modal-header\"> <h5 class=\"modal-title\" id=\"addSkillTitle\">Add Skill</h5></div> <div class=\"modal-body p-0\">" . $form_data . "</div> </div> </div> </div>";
        }
        
        $output .= "</div></div>";
        
        $output .= "<br>Current buffs: ";
        if(count($player->status) == 0) { $output  .= "<span class=\"text-danger\">None</span>"; }
        for($i = 0; $i < count($player->status); $i++) {
             $output .= $player->status[$i];
             if ($i < count($player->status) - 1) { $output .= " | "; }
        }
        $output .= " | Current effects: ";
        if(count($player->skill_effect) == 0) { $output  .= "<span class=\"text-danger\">None</span>"; }
        for($i = 0; $i < count($player->skill_effect); $i++) {
             $output .= ucfirst(str_replace("_", " ", $player->skill_effect[$i]));
             if ($i < count($player->skill_effect) - 1) { $output .= " | "; }
        }
        
        $output .= "<br>";
        $output .= "</div></div>";
        
        //Show available skills to the player
        $output .= "<div class=\"col-md-12 linebreak\"></div><div class=\"container-large mx-auto\"><div class=\"card-deck\">";
        for($i = 0; $i < count($player->skill_hand); $i++) {
            $array_search_key = array_search($player->skill_hand[$i], array_column($skill_data, 0)); 
            $skill_info =  $skill_data[$array_search_key];

            $energy_multiplier = 1;
            skill_effect_energy($energy_multiplier, $player, $skill_info);

            if ($skill_info[6] * $energy_multiplier > $player->battle_energy) {
                $background_colour = "bg-secondary";
            } elseif (!skill_requirement_check($con, $player->skill_hand[$i], $skill_data, $player, $empty)) {
                $background_colour = "bg-danger";
            } else {
                $background_colour = "bg-dark";
            }
            
            $link1 = ($skill_info[6] * $energy_multiplier <= $player->battle_energy && skill_requirement_check($con, $player->skill_hand[$i], $skill_data, $player, $empty)) ? "<a href=\"battle.php?action=attack_monster&id=" . $enemy->id . "&skill=" . $skill_info[0] . "\">" : "";
            $link2 = ($skill_info[6] * $energy_multiplier <= $player->battle_energy && skill_requirement_check($con, $player->skill_hand[$i], $skill_data, $player, $empty)) ? "</a>" : "";
            
            //Grab data about skills and process it 
            if($skill_info[5] == "physical_attack" || $skill_info[5] == "magical_attack") {
                $offensive_stat = ($skill_info[5] == "physical_attack") ? $player->atk + $player->pow : $player->mag + $player->pow;
                $defensive_stat = ($skill_info[5] == "physical_attack") ? $enemy->def : $enemy->res;
                $crit = ($skill_info[16] == -1) ? 0 : round($player->critmax);
                $target_damage_modifier = $user_damage_modifier = 0;
                $target_multiplier = $user_multiplier = 1;
                
                if(!is_numeric($skill_info[7])) { //Apply custom damage formulas
                    $exploded_string = explode(":", $skill_info[7]);
                    for($j = 0; $j < count($exploded_string); $j += 2) {
                        $user_multiplier = (strpos($exploded_string[$j], "user_attack_multiplier") !== false && $skill_info[5] == "physical_attack") ? $exploded_string[$j + 1] : 0;
                        $user_multiplier = (strpos($exploded_string[$j], "user_magic_multiplier") !== false && $skill_info[5] == "magical_attack") ? $exploded_string[$j + 1] : 0;
                        $target_multiplier = (strpos($exploded_string[$j], "target_defense_multiplier") !== false && $skill_info[5] == "physical_attack") ? $exploded_string[$j + 1] : 0;
                        $target_multiplier = (strpos($exploded_string[$j], "target_resistance_multiplier") !== false && $skill_info[5] == "magical_attack") ? $exploded_string[$j + 1] : 0;            
                    }
                }
                
                damage_extra_effects($skill_info, $user_damage_modifier, $target_damage_modifier, $player, $enemy, $output);
                $skill_stats = "Base damage: " . ($skill_info[7]) . "<br>Damage Multiplier: " . round(100 + $skill_info[8]) . "%<br>Critical hit rate: " . $crit . "%";
                $skill_stats .= (DEBUG) ? "<br>Estimated damage: " . max(0, floor(($skill_info[7] + ($offensive_stat + $user_damage_modifier) * $user_multiplier - (($defensive_stat + $target_damage_modifier) * $target_multiplier)) * ($skill_info[8] + 100) / 100) - $enemy->block) : "";
            } else {
                $skill_stats = "";
            }
            
            $message = ($skill_stats == "") ? "" : "<br>"; 
            skill_effect($con, $player->skill_hand[$i], $player, $player, $enemy, $output, $message);
            if($skill_info[15] == 1) { $message .= "Exhaust on use<br>"; }
            if($skill_info[14] == 1) { $message .= "End turn on use<br>"; }
        
            $skill_image_name = str_replace(" ", "", $skill_info[1]);
        $output .= "<span rel=\"tooltip\" data-toggle=\"tooltip\" data-html=\"true\" data-placement=\"top\" title=\"" . $skill_stats . $message . "\"><div class=\"card " . $background_colour . "\" style=\"min-width: 160px;\">".$link1."<img class=\"card-img-top\" src=\"Images/" . $skill_image_name . ".png\" alt=\"" . $skill_info[1] . "\"><div class=\"card-img-overlay\" style=\"height:126px\"><h2>" . $skill_info[6] * $energy_multiplier . "</h2></div>".$link2."<div class=\"card-body\"><h5 class=\"card-title\">"  . $skill_info[1] . "</h5><p class=\"card-text\"><small class=\"text\">"  . $skill_info[2] . "</small></div></div></span>";
        } 
        //Show button to end turn
        $output .= "</div></div>";
        //$output .= "<div class=\"col-md-12 linebreak-big\"></div><div class=\"container\"> <div class=\"row\"> <div class=\"col-sm\"></div><div class=\"col-sm\"><a href=\"battle.php?action=attack_monster&id=" . $enemy->id . "&turn=end\" class=\"btn btn-primary btn-lg btn-block\" role=\"button\">End Turn</a></div><div class=\"col-sm\"></div></div>";
    }
}

//This function is used to convert data (whcih is stored in $player or $enemy arrays) into a string that is stored in the database
//Some data points are removed from the saved data-set (such as passwords) as they are not required (and for security purposes)
function data_encode($unit) {
    //Do not save player-sensitive information
    if(isset($unit->password)) { $unit->password = NULL; }
    if(isset($unit->email)) { $unit->email = NULL; }
    if(isset($unit->verification_key)) { $unit->verification_key = NULL; }
    if(isset($unit->verification_status)) { $unit->verification_status = NULL; }
    if(isset($unit->date_registered)) { $unit->date_registered = NULL; }
    if(isset($unit->current_login_time)) { $unit->current_login_time = NULL; }
    if(isset($unit->previous_login_time)) { $unit->previous_login_time = NULL; }
    if(isset($unit->ip)) { $unit->ip = NULL; }
    
    $data = http_build_query((array) $unit);
    return $data;
}

function end_expired_battle($con, $player) {
    $con->query("UPDATE battle SET complete = 'yes' WHERE attacker = '$player->id' AND complete = 'no'");
    //***Add some sort of punishment for letting battle expire
}

//This function is used in tandem with battle_display_info() to check if a battle is active and valid or not 
//It will display info in regards to a battle if a valid battle is found
function battle_check_info($con, $player, $enemy_id, &$output, $display_info) {
    //Turn based battle begin
    //Check if battle already exists
    $query = mysqli_query($con, "SELECT * from battle WHERE attacker = '$player->id' AND complete = 'no'");
    if(mysqli_num_rows($query) == 0) {  
         $output .= "Something went wrong... The battle you are looking for does not exist!";
    }
    else {
        //Existing battle exists; grab data
        $row = mysqli_fetch_assoc($query);
        parse_str($row['attacker_data'], $attacker_unpacked_data);
        parse_str($row['defender_data'], $defender_unpacked_data);
        
        //Grab skill data
        $skill_data = skill_data($con);
        
        //Check if battle has expired
        if(BATTLE_EXPIRY < TIME - $row['battle_last_active']) {
            $output .= "This battle has expired. The enemy escaped " . round((TIME - $row['battle_last_active'] + BATTLE_EXPIRY) / 60) . " minutes ago...";
            end_expired_battle($con, $player);
        } elseif (BATTLE_EXPIRY * 3.5 < TIME - $row['battle_start'] ) {
            $output .= "This battle has expired. The enemy disappeared without a trace " . round((TIME - $row['battle_start'] + BATTLE_EXPIRY * 3.5) / 60) . " minutes ago...";
            end_expired_battle($con, $player);        
        } elseif ($defender_unpacked_data['id'] != $enemy_id) {
            $output .= "You are already engaged in a battle with another enemy!";
        }
        else { //Battle still valid
            //Convert array into variables available to the player and enemy classes
            foreach($attacker_unpacked_data as $key => $value) { $player->$key = $value; }
            if(!isset($player->draw_pile)) { $player->draw_pile = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($player->skill_hand)) { $player->skill_hand = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($player->skill_discard)) { $player->skill_discard = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($player->skill_exhaust)) { $player->skill_exhaust = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($player->skill_effect)) { $player->skill_effect = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($player->turn_effect)) { $player->turn_effect = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($player->status)) { $player->status = array(); } //parse_str doesn't deal well with empty arrays
                        
            $enemy = new StdClass;
            foreach($defender_unpacked_data as $key => $value) { $enemy->$key = $value; }
            if(!isset($enemy->draw_pile)) { $enemy->draw_pile = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($enemy->skill_hand)) { $enemy->skill_hand = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($enemy->skill_discard)) { $enemy->skill_discard = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($enemy->skill_exhaust)) { $enemy->skill_exhaust = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($enemy->skill_effect)) { $enemy->skill_effect = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($enemy->turn_effect)) { $enemy->turn_effect = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($enemy->status)) { $enemy->status = array(); } //parse_str doesn't deal well with empty arrays
            
            if($display_info) {
                battle_display_info($con, $player, $enemy, $skill_data, $output);
            }
        }
    }
}

//This function is used to process a skill from turn-based battle upon the click of a skill created with battle_display_skill. It also uses the skill() function from skill.php
//This function is only used for turn based battles
//Skill() function performs a change in variable (such as loss of hp or an increase of a particular statistic) and moves a used skill to its appropriate location (such as the discard pile)
//Various checks are performed to ensure the skill is used within a  valid battle setting
//$skill_id if equal to a number will presume the player is attacking and execute said skill; if said to 'enemy' then it will assume the enemy is attacking
function battle_process_skill($con, $player, $enemy_id, &$output, $skill_id) {
    $query = mysqli_query($con, "SELECT * from battle WHERE attacker = '$player->id' AND complete = 'no'");
    if(mysqli_num_rows($query) == 0) {  
         $output .= "Something went wrong... The battle you are looking for does not exist!";
    }
    else {
        //Existing battle exists; grab data
        $row = mysqli_fetch_assoc($query);
        parse_str($row['attacker_data'], $attacker_unpacked_data);
        parse_str($row['defender_data'], $defender_unpacked_data);
        
        //Grab skill data
        $skill_data = skill_data($con);
        
        //Check if battle has expired
        if(BATTLE_EXPIRY < TIME - $row['battle_last_active']) {
            $output .= "This battle has expired. The enemy escaped " . round((TIME - $row['battle_last_active'] + BATTLE_EXPIRY) / 60) . " minutes ago...";
            end_expired_battle($con, $player);
        } elseif (BATTLE_EXPIRY * 3.5 < TIME - $row['battle_start'] ) {
            $output .= "This battle has expired. The enemy disappeared without a trace " . round((TIME - $row['battle_start'] + BATTLE_EXPIRY * 3.5) / 60) . " minutes ago...";
            end_expired_battle($con, $player);        
        }  elseif ($defender_unpacked_data['id'] != $enemy_id) {
            $output .= "You are already engaged in a battle with another enemy!";
        }
        else {
            //Convert array into variables available to the player and enemy classes
            foreach($attacker_unpacked_data as $key => $value) { $player->$key = $value; }
            if(!isset($player->draw_pile)) { $player->draw_pile = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($player->skill_hand)) { $player->skill_hand = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($player->skill_discard)) { $player->skill_discard = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($player->skill_exhaust)) { $player->skill_exhaust = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($player->skill_effect)) { $player->skill_effect = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($player->turn_effect)) { $player->turn_effect = array(); } //parse_str doesn't deal well with empty arrays            
            if(!isset($player->status)) { $player->status = array(); } //parse_str doesn't deal well with empty arrays
            
            $enemy = new StdClass;
            foreach($defender_unpacked_data as $key => $value) { $enemy->$key = $value; }
            if(!isset($enemy->draw_pile)) { $enemy->draw_pile = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($enemy->skill_hand)) { $enemy->skill_hand = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($enemy->skill_discard)) { $enemy->skill_discard = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($enemy->skill_exhaust)) { $enemy->skill_exhaust = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($enemy->skill_effect)) { $enemy->skill_effect = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($enemy->turn_effect)) { $enemy->turn_effect = array(); } //parse_str doesn't deal well with empty arrays
            if(!isset($enemy->status)) { $enemy->status = array(); } //parse_str doesn't deal well with empty arrays
            
            if(is_numeric($skill_id)) { //Attempt to execute player skill
                //Check if skill is in players hand or not
                if(in_array($skill_id, $player->skill_hand) && check_user_input($con, $output, $player, $enemy->id, "check") === false) {
                    skill($con, $skill_data, $skill_id, $player, $player, $enemy, $output);
                }
                else {
                    $output .= "You cannot play this skill!<br>";
                }
            }
            
            elseif($skill_id == 'enemy') {  //Execute enemy AI, reset block and shuffle cards
                
                turn_reset($con, $player, $enemy, $player, $output, 2);

                //Check if additonal skills can be used
                $current_hand_size = count($enemy->skill_hand); //Current hand size (enemy can only use skills up to the number of skills in its hand)
                
                //Skill sorting algorithm (sorted by energy)
                skill_select($con, $enemy, $skill_data, $enemy->battle_ai, $output);
                
                for($i = 0; $i < $current_hand_size && $player->hp > 0; $i++) {
                    $array_search_key = array_search($enemy->skill_hand[$i], array_column($skill_data, 0));
                    $skill_info =  $skill_data[$array_search_key]; //Grabs data of skill based on id
            
                    $energy_multiplier = 1;
                    skill_effect_energy($energy_multiplier, $player, $skill_info);
                    if($skill_info[6] * $energy_multiplier <= $enemy->battle_energy && skill_requirement_check($con, $enemy->skill_hand[$i], $skill_data, $enemy, $empty)) { //Check if skill can be used (energy and skill-specific requirements)
                        skill($con, $skill_data, $enemy->skill_hand[$i], $player, $enemy, $player, $output);
                        if(check_user_input($con, $output, $enemy, $enemy->id, "check") !== false) {
                            check_user_input($con, $output, $enemy, $enemy->id, "auto_select");
                        }                        
                        $current_hand_size = count($enemy->skill_hand) - 1; //A skill got used up so the hand size is reduced
                        $i--; //When a skill gets used; all the keys get reassigned which means we need to bring the current position back 1 spot
                    } 
                }
                
                turn_reset($con, $player, $player, $enemy, $output, 2);
     
            }
            
            
            $attacker_data = data_encode($player); 
            $defender_data = data_encode($enemy);
            $time = TIME;
            
            //Update database with updated info
            $con->query("UPDATE battle SET attacker_data = '$attacker_data', defender_data = '$defender_data', battle_last_active = $time WHERE attacker = '$player->id' AND complete = 'no'");
            battle_display_info($con, $player, $enemy, $skill_data, $output);
            
            end_battle($con, $player, $enemy, $output, "turn");
            
        }
    }
}

function turn_reset($con, $player, $unit, $target, &$output, $gain_factor) {
    
    if($unit->hp > 0) {
    
    //Gain energy; by default turn based battle provides double energy per turn vs. autobattle
    $unit->battle_energy = min($unit->max_energy, $unit->battle_energy + $unit->battle_energy_reset * $gain_factor); 
    
    //Increase turn counter
    $unit->battle_turns++;

    //Reset block (if needed)
    $unit->block = ($unit->block_loss == -1) ? $unit->block = 0 : max(0, $unit->block - $unit->block_loss);
    
    //Draw skills from draw pile to the hand
    $draw_delta = 0; //This variable is used to account for buffs that temporarily/pernamently alter skill draw per turn

    //Handle buffs / debuffs
    for($i = 0; $i < count($unit->status); $i++) {
        if(strpos($unit->status[$i], "pernament_draw") !== false) { 
            $draw_shift = explode(":",$unit->status[$i]);
            $draw_delta += $draw_shift[1];
        }
        if(strpos($unit->status[$i], "temporary_draw") !== false) {  //$unit->status[$i] should be the following format: "temporary_draw:$draw_gain:$buff_turns"
            $draw_shift = explode(":",$unit->status[$i]);
            $draw_delta += $draw_shift[1];
            
            if($draw_shift[2] == 1) { //If there was only one turn left on the temporary_draw buff, remove it from $unit->status array (otherwise reduce its count by 1)
                array_splice($unit->status, $i, 1); 
                $i--;
                if($i < 0) {
                    break;
                }
            } else {
                $unit->status[$i] = "temporary_draw:" . $draw_shift[1] . ":" . ($draw_shift[2] - 1);
            }
        }
        if(strpos($unit->status[$i], "modify") !== false) { //Reduce modify status effects by 1 
            $status = explode(":",$unit->status[$i]);
            if(abs($status[1]) <= 1) {
                $output .= $unit->username . " " . str_replace("_modify", "", $status[0]) . " stat modification has expired<br>";
                array_splice($unit->status, $i, 1); 
                $i--;
                if($i < 0) {
                    break;
                }
            } else {
                $unit->status[$i] = $status[0] . ":" . ($status[1] - (abs($status[1]) / $status[1])); //Reduce modify status by 1; if status is negative (debuff)
            }
        }
        
        //The following code handles parameter buffs/debuffs
        //If a parameter_shift turn count is positive then it is assumed to be a buff
        //If a parameter_shift turn count is negative then it is assumed to be a debuff
        
        if(strpos($unit->status[$i], "shift") !== false) { 
            $turns_remaining = (int) filter_var($unit->status[$i], FILTER_SANITIZE_NUMBER_INT);
            $status_name = explode(':', $unit->status[$i])[0];
            
            if($turns_remaining == -1 || $turns_remaining == 1 || $turns_remaining == 0) {
                array_splice($unit->status, $i, 1); 
                if($i >= count($unit->status)) { break; }
            } elseif($turns_remaining > 1) {
                $unit->status[$i] = $status_name . ":" . ($turns_remaining - 1);
            } elseif ($turns_remaining < 1) {
                $unit->status[$i] = $status_name . ":" . ($turns_remaining + 1);
            }
        }
    }
    
    //Handle skill effects
    for($i = 0; $i < count($unit->skill_effect); $i++) {        
        //Remove any temporary "free skills" buff if they are set to expire at the end of the turn (this is done by adding this_turn to the skill_effect parameter Eg. free_attack_this_turn)
        if(strpos($unit->skill_effect[$i], "free") !== false && strpos($unit->skill_effect[$i], "this_turn") !== false) {
            $output .= "The " . (str_replace("_", " ", $unit->skill_effect[$i])) . " effect has expired<br>";
            array_splice($unit->skill_effect, $i, 1); 
            $i--;
            if($i < 0) {
                break;
            }
        }
    }
            
    
    $draw_delta = max(0, $draw_delta + $unit->battle_draw_per_turn);
    if(DEBUG) { $output .= $unit->username . " draws " . $draw_delta . " skills. Current turn: " . $unit->battle_turns . "<br>"; }
    
    skill_draw($unit, $draw_delta, $output);
    
    //Apply turn effects
    for($i = 0; $i < count($unit->turn_effect); $i++) {
        if(explode(":", $unit->turn_effect[$i])[0] == $unit->battle_turns) {
            skill_effect($con, false, $player, $unit, $target, $output, strval(explode(":", $unit->turn_effect[$i])[1] . ":" . explode(":", $unit->turn_effect[$i])[2])); 
        }
    }
    
    //Set battle statistics (if applicable)
    $unit->skills_played[$unit->battle_turns] = "";

    }
}

function end_battle($con, $player, $enemy, &$output, $type) {
    if ($player->hp <= 0 && $enemy->hp > 0) {
        $output .= "<br><u>You were defeated by " . $enemy->username . "!</u>";
        // *** INSERT PUNISHEMENT FOR LOSING
        
        if($type == "turn") { //Conclude a turn based battle
            //Close the battle (database)
            $query = mysqli_query($con, "SELECT * from battle WHERE attacker = '$player->id' AND complete = 'no'");
            if(mysqli_num_rows($query) == 0) {  
                $output .= "Something went wrong...";
            }
            else {
                $con->query("UPDATE battle SET complete = 'yes' WHERE attacker = '$player->id' AND complete = 'no'");
            }
        }
        
    } elseif ($enemy->hp <= 0 && $player->hp > 0) {
        $output .= "<br><u>You defeated " . $enemy->username . "!</u>";
        if($type == "turn") { //Conclude a turn based battle
            //Close the battle (database)
            $query = mysqli_query($con, "SELECT * from battle WHERE attacker = '$player->id' AND complete = 'no'");
            if(mysqli_num_rows($query) == 0) {  
                $output .= "Something went wrong...";
            }
            else {
                $con->query("UPDATE battle SET complete = 'yes' WHERE attacker = '$player->id' AND complete = 'no'");
            }
        }
        elseif($type == "auto") {
            // *** INSERT REWARD FOR WINNING
        }
    } elseif ($enemy->hp <= 0 && $player->hp <= 0) {
        $output .= "<u>Both you and the opponent lied bloodied and bruised; both defeated in battle...</u>";
    } elseif ($type == "auto") {
        $output .= "<u>Both combatant lie still, exhausted by the never-ending battle. You retreat without the spoils of victory.</u>";
    }
}


//This function is used when user input is required
//The main case for this function is when a skill needs to be selected from the user's deck/discard pile/hand
//This function pauses the battle such that the user can make a selection
//$unit will almost always be $player
function check_user_input($con, &$output, $unit, $target_id, $function) {
    if($function == "check") {
        $returned_value = false;
        foreach ($unit->status as $key => $status) {
            if(strpos($status, "selectable_skills") !== false) { 
                $returned_value = $status;
                break;
            } 
            if(strpos($status, "optional_skills") !== false) { 
                $returned_value = $status;
                break;
            } 
        }
        return $returned_value;
    }
    elseif($function == "display") {
        $location = 0;
        $amount = 0;
        $optional = false;
        foreach ($unit->status as $key => $status) {
            if(strpos($status, "exhaust_selectable_skills") !== false) { 
                $amount = (int) filter_var(substr($status, strpos($status, ":")), FILTER_SANITIZE_NUMBER_INT);
                $location = substr($status, (26 - strlen($status))); //The length of exhaust_selectable_skills_ in exhaust_selectable_skills_X:Y is 26
                $action = "exhaust";
                $returned_value = true;
                break;
            } elseif(strpos($status, "discard_selectable_skills") !== false) { 
                $amount = (int) filter_var(substr($status, strpos($status, ":")), FILTER_SANITIZE_NUMBER_INT);
                $location = substr($status, (26 - strlen($status))); //The length of discard_selectable_skills in discard_selectable_skills_X:Y is 26
                $action = "discard";
                $returned_value = true;
                break;
            } elseif(strpos($status, "draw_selectable_skills") !== false) { 
                $amount = (int) filter_var(substr($status, strpos($status, ":")), FILTER_SANITIZE_NUMBER_INT);
                $location = substr($status, (23 - strlen($status))); //The length of draw_selectable_skills in draw_selectable_skills_X:Y is 23
                $action = "draw";
                $returned_value = true;
                break;
            } elseif(strpos($status, "use_selectable_skills") !== false) { 
                $amount = (int) filter_var(substr($status, strpos($status, ":")), FILTER_SANITIZE_NUMBER_INT);
                $location = substr($status, (22 - strlen($status))); //The length of use_selectable_skills in use_selectable_skills_X:Y is 22
                $action = "use";
                $returned_value = true;
                break;
            } elseif(strpos($status, "shuffle_selectable_skills") !== false) { 
                $amount = (int) filter_var(substr($status, strpos($status, ":")), FILTER_SANITIZE_NUMBER_INT);
                $location = substr($status, (24 - strlen($status))); //The length of shuffle_selectable_skills in shuffle_selectable_skills_X:Y is 26
                $action = "shuffle";
                $returned_value = true;
                break;
            } elseif(strpos($status, "exhaust_optional_skills") !== false) { 
                $amount = (int) filter_var(substr($status, strpos($status, ":")), FILTER_SANITIZE_NUMBER_INT);
                $location = substr($status, (24 - strlen($status))); //The length of discard_optional_skills in discard_optional_skills_X:Y is 24
                $action = "discard";
                $returned_value = true;
                $optional = true;
                break;                
            } elseif(strpos($status, "discard_optional_skills") !== false) { 
                $amount = (int) filter_var(substr($status, strpos($status, ":")), FILTER_SANITIZE_NUMBER_INT);
                $location = substr($status, (24 - strlen($status))); //The length of discard_optional_skills in discard_optional_skills_X:Y is 24
                $action = "discard";
                $returned_value = true;
                $optional = true;
                break;
            } elseif(strpos($status, "draw_optional_skills") !== false) { 
                $amount = (int) filter_var(substr($status, strpos($status, ":")), FILTER_SANITIZE_NUMBER_INT);
                $location = substr($status, (21 - strlen($status))); //The length of draw_optional_skills in draw_optional_skills_X:Y is 21
                $action = "draw";
                $returned_value = true;
                $optional = true;
                break;
            } elseif(strpos($status, "use_optional_skills") !== false) { 
                $amount = (int) filter_var(substr($status, strpos($status, ":")), FILTER_SANITIZE_NUMBER_INT);
                $location = substr($status, (20 - strlen($status))); //The length of use_optional_skills in use_optional_skills_X:Y is 20
                $action = "use";
                $returned_value = true;
                $optional = true;
                break;
            } elseif(strpos($status, "shuffle_optional_skills") !== false) { 
                $amount = (int) filter_var(substr($status, strpos($status, ":")), FILTER_SANITIZE_NUMBER_INT);
                $location = substr($status, (24 - strlen($status))); //The length of shuffle_optional_skills in shuffle_optional_skills_X:Y is 24
                $action = "shuffle";
                $returned_value = true;
                $optional = true;
                break;
            }              
        }
        
        if(strpos($location, "deck") !== false) {
            $locator = "draw_pile";
            $location_name = "deck";
        } elseif(strpos($location, "hand") !== false) {
            $locator = "skill_hand";
            $location_name = "hand";
        } elseif(strpos($location, "discard") !== false) {
            $locator = "skill_discard";
            $location_name = "discard pile";
        } elseif(strpos($location, "exhaust") !== false) {
            $locator = "skill_exhaust";
            $location_name = "exhaust zone";
        }

        if(isset($locator) && isset($location_name)) {
            if(count($unit->$locator) == 0) {  //Find the status and remove the requirement to exhaust skills then send an update SQL query to the database
                if($optional) {
                    $array_search_key = array_search($action . "optional_skills_" . $location_name, $unit->status);
                } else {
                    $array_search_key = array_search($action . "selectable_skills_" . $location_name, $unit->status);   
                }
                array_splice($unit->status, $array_search_key, 1); //Remove this status as there are no cards in the deck
                $con->query("UPDATE battle SET attacker_data ='" . data_encode($unit) . "', battle_last_active = " . TIME . " WHERE attacker = '$unit->id' AND complete = 'no'");
                $output .= "You have no skills in the " . $location_name . " to " . $action;
                $output .= "<div class=\"col-md-12 linebreak\"></div><a class=\"btn btn-primary\" role=\"button\" href=\"battle.php?action=attack_monster&id=" . $_GET['id'] . "\">Continue Battle</a>"; 
            }
            else {
                $output .= "Select " . $amount . " skill(s) from your " . $location_name . " to " . $action;
                
                //Grab info from database and create classes
                battle_check_info($con, $unit, $target_id, $output, false);
                $skill_data = skill_data($con);
                echo "<input type=\"hidden\" id=\"select-quantity\" name=\"select-quantity\" value=\"" . $amount . "\">";  //This is a hidden element that is used to pass a php variable to a javascript snipper that limits how many skills that can be selected
                
                $form = "<form action=\"battle.php\" method=\"GET\" role=\"form\" class=\"form-horizontal\"><div class=\"container-large mx-auto px-0 py-3\"><div class=\"card-deck\">";
                for($i = 0; $i < count($unit->$locator); $i++) {
                    $array_search_key = array_search($unit->$locator[$i], array_column($skill_data, 0)); 
                    $skill_info =  $skill_data[$array_search_key];
                    $skill_image_name = str_replace(" ", "", $skill_info[1]);
                    $form .= "<input type=\"checkbox\" class =\"multi-checkbox\" value=\"" . $i . "\"  name=\"" . strtok($location_name,  ' ') . "[]\" id=\"hide-checkbox" . $i . "\" /><label for=\"hide-checkbox" . $i . "\"><div class=\"card bg-dark\" style=\"min-width: 160px;\"><img class=\"card-img-top\" src=\"Images/" . $skill_image_name . ".png\" alt=\"" . $skill_info[1] . "\"><div class=\"card-img-overlay\" style=\"height:126px\"><h2>" . $skill_info[6] . "</h2></div><div class=\"card-body\"><h5 class=\"card-title\">"  . $skill_info[1] . "</h5><p class=\"card-text\"><small class=\"text\">"  . $skill_info[2] . "</small></div></div></label>";            
                }
                
                $form .= "</div></div><input type=\"hidden\" name=\"id\" value=\"" . $target_id ."\"><input type=\"hidden\" name=\"action\" value=\"" . $action ."\"><button type=\"submit\" class=\"btn btn-primary btn-block\">" . ucfirst($action) . "</button> " . (($optional) ? "<button type=\"submit\" name=\"action\" value=\"end_selection\" class=\"btn btn-primary btn-block\">End Selection</button> ": "") . "</form>";
                $output .= $form;
            }
        } 
    }
    elseif($function == "auto_select") { //This function is used in auto-battle as well as when an enemy is required to select skills
        foreach ($unit->status as $key => $status) {
            if(strpos($status, "selectable_skills") !== false || strpos($status, "optional_skills") !== false) {
                $skills_selected = "";
                $skill_data = skill_data($con);
                
                $amount = (int) filter_var(substr($status, strpos($status, ":")), FILTER_SANITIZE_NUMBER_INT);
                switch(substr(explode("_", $status)[3], 0, strpos(explode("_", $status)[3], ":"))) {
                    case "hand":
                        $origin = "skill_hand";
                        break;
                    case "exhaust":
                        $origin = "skill_exhaust";
                        break;
                    case "discard":
                        $origin = "skill_discard";
                        break;                        
                    case "deck":
                        $origin = "draw_pile";
                        break;                        
                }
                for($i = 0; $i < $amount && count($unit->$origin); $i++) {
                    $rand = rand(0, count($unit->$origin) - 1); //select random skill
                    switch(explode("_", $status)[0]) {
                        case "exhaust":
                            $destination = "skill_exhaust";
                            break;
                        case "discard":
                            $destination = "skill_discard";
                            break;
                        case "draw":
                            $destination = "skill_hand";
                            break;
                        case "shuffle":
                            $destination = "draw_pile";
                            break;
                    }
                    array_push($unit->$destination, $unit->$origin[$rand]);
                    $array_search_key = array_search($unit->$origin[$rand], array_column($skill_data, 0)); 
                    $skill_info =  $skill_data[$array_search_key];                            
                    $skills_selected .= $skill_info[1] . " ";
                    array_splice($unit->$origin, $rand, 1);
                }
            $output = ($skills_selected == "") ? $output . "No skills avaialble to be selected <br>"  : $output . $skills_selected . " was selected from the " . substr(explode("_", $status)[3], 0, strpos(explode("_", $status)[3], ":")) . " and placed into " . explode("_", $status)[0] . "<br>";
            $skills_selected  = ($skills_selected == "") ? "Nothing" : $skills_selected; 
            unset($unit->status[$key]);
            $unit->status = array_values($unit->status);
            }
        }
    }
}

//===============
// Select Enemy
//===============

$min_level = $player->level * 0.75 - 5;
$max_level = $player->level * 1.25 + 5;
$query = mysqli_query($con, "SELECT * FROM monsters WHERE level > '$min_level' AND level < '$max_level'");

if(!$query) { 
    echo "Something not right has happened...";
}
elseif(mysqli_num_rows($query) == 0) {
    echo "No available enemies to attack";
} 
  else {

    $query3 = mysqli_query($con, "SELECT * from battle WHERE attacker = '$player->id' AND complete = 'no' LIMIT 1");
    if(mysqli_num_rows($query3) > 0 && !isset($_GET['action'])) {  
        $row3 = mysqli_fetch_assoc($query3);
        echo "<div class=\"col-md-12 linebreak\"></div><a class=\"btn btn-primary\" role=\"button\" href=\"battle.php?action=attack_monster&id=" . $row3['defender'] . "\">Continue Battle</a>";
    }
    elseif (!isset($_GET['action'])) {
    echo "<div class=\"col-md-12 linebreak\"></div><div class=\"container-large\"><div class=\"row\"><div class=\"col-4\"><div class=\"list-group\" id=\"monsterList\" role=\"tablist\">";
    for($i = 0; $i < mysqli_num_rows($query); $i++)
        {
            $query2 = mysqli_query($con, "SELECT * FROM monsters WHERE level > '$min_level' AND level < '$max_level' ORDER BY id ASC LIMIT 1 OFFSET $i");
            $row = mysqli_fetch_assoc($query2);
            $name = str_replace(' ', '', $row['username']);
            if($i == 0) { $active = "active"; } else { $active = ""; }
            echo "<a class=\"list-group-item list-group-item-action " . $active . "\" data-toggle=\"list\" href=\"#" . $name . "\" role=\"tab\">" . $row['username'] . "</a>";
            //echo $row['username'];
        }
    echo "</div></div><div class=\"col-8\"><div class=\"tab-content\">";
    
    for($i = 0; $i < mysqli_num_rows($query); $i++)
        {
            $query2 = mysqli_query($con, "SELECT * FROM monsters WHERE level > '$min_level' AND level < '$max_level' ORDER BY id ASC LIMIT 1 OFFSET $i");
            $row = mysqli_fetch_assoc($query2);
            $name = str_replace(' ', '', $row['username']);
            if($i == 0) { $active = " active"; } else { $active = ""; }
            $info = "Monster: " . $row['username'] . "<br>Level: " . $row['level'] . "<br>Health: " . $row['hp'] . " | Attack: " . $row['attack'] . " | Defence: " . $row['defence'] . " | Magic: " . $row['magic'] . " | Resistance: " . $row['resistance'] . " | Agility: " . $row['agility'] . " | Dexterity: " . $row['dexterity'] . "<br><a class=\"btn btn-primary\" href=\"battle.php?action=autoattack_monster&id=" . $row['id'] ."\" role=\"button\">Auto-Battle</a> <a class=\"btn btn-primary\" href=\"battle.php?action=attack_monster&id=" . $row['id'] ."\" role=\"button\">Turn-Based Battle</a>";
            echo "<div class=\"tab-pane" . $active . "\" id=\"" . $name . "\" role=\"tabpanel\">" . $info . "</div>";
        }    

    echo "</div></div></div></div>";
    }
}

if(!isset($_GET['action'])) {
    // $output .= "Error";
} else {
    switch ($_GET['action']) {
        case "autoattack_player":
            $output .= "This function has not been added to the game yet";
            break;
        case "attack_player":
            $output .= "This function has not been added to the game yet";
            break;            
        case "attack_monster":
            if (!isset($_GET['id']) && !isset($_GET['skill'])) {
                //If no monster id provided; then terminate battle
                $output .= "The monster you tried to attack disappeared before the battle started!";
                break;
            } elseif (isset($_GET['id']) && !isset($_GET['skill']) && !isset($_GET['turn'])) { //Monster id has been returned but not an action
                $enemy_id = $_GET['id'];
                $output .= battle_preliminary_check($con, $player, $enemy_id, 'monster', 'turn'); 
                if($output == "") { //No errors; continue with battle processing
                    //Turn based battle begin
                    //Check if battle already exists
                    $query = mysqli_query($con, "SELECT * from battle WHERE attacker = '$player->id' AND complete = 'no'");
                    if(mysqli_num_rows($query) == 0) {
                        
                        $enemy = create_enemy($con, $enemy_id, 'monster');
                        battle_start($player, $enemy); 
                        skill_initialise($player);
                        skill_initialise($enemy);   
                        
                        //Give player extra draw + energy gain (assumes player goes first in a turn based battle)
                        turn_reset($con, $player, $player, $enemy, $output, 2);
                        
                        //Grab all relevant variables from the battle and turn them into strings (arrays)
                        $attacker = $player->id; //These values are important in comparing battle variables to original variables
                        $defender = $enemy_id;
                        $defender_type = 'monster';
                        $attacker_data = data_encode($player);
                        $defender_data = data_encode($enemy);
                        //Add a new battle in the battle database
                        if ($con->query("INSERT INTO battle (attacker, defender, defender_type, attacker_data, defender_data, battle_data, battle_start, battle_last_active, complete) VALUES ($attacker, $defender, '$defender_type', '$attacker_data', '$defender_data', '', " . TIME . "," . TIME . ", 'no')") === TRUE) {
                            $output .= "A new battle against " . $enemy->username . " has begun!"; 
                            $output .= "<br><a class=\"btn btn-primary\" role=\"button\" href=\"battle.php?action=attack_monster&id=" . $defender . "\">Continue Battle</a>";                        }
                    }
                    else {
                        // $output .= "You already have an active battle happening! <br>";
                        
                        battle_check_info($con, $player, $enemy_id, $output, true);
                        
                    }
                }
            }
            elseif(isset($_GET['id']) && isset($_GET['skill'])) {
                $enemy_id = $_GET['id'];
                $output .= battle_preliminary_check($con, $player, $enemy_id, 'monster', 'turn'); 
                if($output == "") { //No errors; continue with battle processing
                    $skill_data = skill_data($con);
                    battle_process_skill($con, $player, $enemy_id, $output, $_GET['skill']);
                }
            }
            elseif(isset($_GET['id']) && isset($_GET['turn'])) { //End turn
                $enemy_id = $_GET['id'];
                $output .= battle_preliminary_check($con, $player, $enemy_id, 'monster', 'turn'); 
                battle_check_info($con, $player, $enemy_id, $output, false); 
                if($output == "" && $_GET['turn'] == 'end') { //No errors; continue with battle processing
                   if(check_user_input($con, $output, $player, $enemy_id, "check") !== false) {  //Check if the battle state is awaiting user input or not
                        $output .= "Cannot end your turn without finishing your skill! <br>";
                        check_user_input($con, $output, $player, $enemy_id, "display");
                    } else { 
                        battle_process_skill($con, $player, $enemy_id, $output, 'enemy');
                    }
                }
            }
            break;
        case "autoattack_monster":
            if (!isset($_GET['id'])) {
                //If no monster id provided; then terminate battle
                $output .= "The monster you tried to attack disappeared before the battle started!";
                break;
            }
            else {
                $enemy_id = $_GET['id'];
                $output .= battle_preliminary_check($con, $player, $enemy_id, 'monster', 'auto'); //Grab monster id data
                if($output == "") { //No errors; continue with battle processing

                    $enemy = create_enemy($con, $enemy_id, 'monster');
                    //So far all checks have passed so now we grab player equipment bonuses
                    //*** Add equipment bonus checking via mysqli query
                    
                    battle_start($player, $enemy);
                   
                    //Battlerounds
                    $battlerounds = $minrounds + $player->level + $enemy->level;
                    
                    //Grab skill data
                    $skill_data = skill_data($con);
                    skill_initialise($player);
                    skill_initialise($enemy);   
                    
                    //======================
                    // BATTLE PROCESSING  //
                    //=====================
        
                        while ($enemy->hp > 0 && $player->hp > 0 && $battlerounds > 0) { //Both combatants have hp and battle rounds have not hit the limit
                        
                        //Hits determines how many bonus actions a player can perform. 
                        $player->actions = ($player->combo % 100 >= rand(1, 100)) ? ceil($player->combo / 100) : max(1, floor($player->combo / 100));
                        $enemy->actions = ($enemy->combo % 100 >= rand(1, 100)) ? ceil($enemy->combo / 100) : max(1, floor($enemy->combo / 100));
                        
                        //Determine who is attacker and defender at the start 
                        $attacking      = ($player->agi >= $enemy->agi) ? $player : $enemy;
                        $defending      = ($player->agi >= $enemy->agi) ? $enemy : $player;
                        
                        //Reset turn
                        turn_reset($con, $player, $attacking, $defending, $output, 1); 
                        
                        for ($i = 0; $i < $attacking->actions && $attacking->hp > 0 && $defending->hp > 0; $i++) {
                            
                            //Based on Auto-Battle algorithm - Pick a skill to use (moves selected skill to the front of the hand)
                            skill_select($con, $attacking, $skill_data, $attacking->battle_ai, $output);
                            $skill_select = current($attacking->skill_hand); 
                            
                            //Grab hand size
                            $current_hand_size = count($attacking->skill_hand); //Current hand size (enemy can only use skills up to the number of skills in its hand)
                            
                            //Run through skills in order and attempt to use them
                            for($i = 0; $i < $current_hand_size && $attacking->hp > 0 && $attacking->actions > 0; $i++) {
                            $array_search_key = array_search($attacking->skill_hand[$i], array_column($skill_data, 0));
                            $skill_info =  $skill_data[$array_search_key]; //Grabs data of skill based on id
                    
                            $energy_multiplier = 1;
                            skill_effect_energy($energy_multiplier, $player, $skill_info);
                            
                            if($skill_info[6] * $energy_multiplier <= $attacking->battle_energy && skill_requirement_check($con, $attacking->skill_hand[$i], $skill_data, $attacking, $empty)) { //Check if skill can be used (energy and skill-specific requirements)
                                skill($con, $skill_data, $attacking->skill_hand[$i], $player, $attacking, $defending, $output);
                                $current_hand_size = count($attacking->skill_hand) - 1; //A skill got used up so the hand size is reduced
                                $i--; //When a skill gets used; all the keys get reassigned which means we need to bring the current position back 1 spot
                                $attacking->actions--; //One action used
                                
                                if(check_user_input($con, $output, $attacking, $enemy->id, "check") !== false) {
                                    check_user_input($con, $output, $attacking, $enemy->id, "auto_select");
                                }
                                
                                //Death check
                                if ($defending->hp <= 0 || $attacking->hp <= 0) {
                                    end_battle($con, $player, $enemy, $output, "auto");
                                    break 2;
                                    }
                                } 
                            }
                        }
                            
                       //Reset turn
                        turn_reset($con, $player, $defending, $attacking, $output, 1); 
                            
                        for ($i = 0; $i < $defending->actions && $attacking->hp > 0 && $defending->hp > 0; $i++) {
                            
                            //Based on Auto-Battle algorithm - Pick a skill to use (moves selected skill to the front of the hand)
                            skill_select($con, $defending, $skill_data, $attacking->battle_ai, $output);
                            $skill_select = current($defending->skill_hand); 
                            
                            //Grab hand size
                            $current_hand_size = count($defending->skill_hand); //Current hand size (enemy can only use skills up to the number of skills in its hand)
                            
                            //Run through skills in order and attempt to use them
                            for($i = 0; $i < $current_hand_size && $defending->hp > 0 && $defending->actions > 0; $i++) {
                            $array_search_key = array_search($defending->skill_hand[$i], array_column($skill_data, 0));
                            $skill_info =  $skill_data[$array_search_key]; //Grabs data of skill based on id
                            
                            $energy_multiplier = 1;
                            skill_effect_energy($energy_multiplier, $player, $skill_info);
                            
                            if($skill_info[6] * $energy_multiplier <= $defending->battle_energy && skill_requirement_check($con, $defending->skill_hand[$i], $skill_data, $defending, $empty)) { //Check if skill can be used (energy and skill-specific requirements)
                                skill($con, $skill_data, $defending->skill_hand[$i], $player, $defending, $attacking, $output);
                                $current_hand_size = count($defending->skill_hand) - 1; //A skill got used up so the hand size is reduced
                                $i--; //When a skill gets used; all the keys get reassigned which means we need to bring the current position back 1 spot
                                $defending->actions--;
                                
                                if(check_user_input($con, $output, $defending, $enemy->id, "check") !== false) {
                                    check_user_input($con, $output, $defending, $enemy->id, "auto_select");
                                }
                                
                                //Death check
                                if ($defending->hp <= 0 || $attacking->hp <= 0) {
                                    end_battle($con, $player, $enemy, $output, "auto");
                                    break 2;
                                    }
                                } 
                            }
                        }
                            
                        $battlerounds--;
                        
                        if(DEBUG) { $output .= "<br>"; }
                        
                        if ($battlerounds <= 0) {
                            end_battle($con, $player, $enemy, $output, "auto");
                            break; //Break out of for and while loop, battle is over!
                        } else {
                            //Battle continues
                        }
                    }
                }
            }
            break;
        case "add_skill":
            if(!DEBUG) {
                $output .= "Action not supported without debug privileges!";
                break;
            } elseif (isset($_GET['skill_id']) && isset($_GET['id']) && isset($_GET['randomizer_quantity']) && isset($_GET['location'])) {
                if(!is_numeric($_GET['randomizer_quantity'])) {
                    $enemy_id = $_GET['id'];
                    $output .= battle_preliminary_check($con, $player, $enemy_id, 'monster', 'turn'); //Check if enemy_id exists
                    battle_check_info($con, $player, $enemy_id, $output, false); //Check if battle with enemy_id is valid or not
                    if($output == "") { //No errors, grab user's data so we can add stuff to it
                        
                        if(isset($_GET['purge'])) {
                            if($_GET['location'] == "hand" && $_GET['purge'] == true) {
                                $player->skill_hand = array();
                            } elseif($_GET['location'] == "deck" && $_GET['purge'] == true) {
                                $player->draw_pile = array();
                            }
                            $output .= ucfirst($_GET['location']) . " has been emptied<br>";
                        }
                
                        if($_GET['location'] == "hand") {
                            array_push($player->skill_hand, $_GET['skill_id']);
                        } elseif($_GET['location'] == "deck") {
                            array_push($player->draw_pile, $_GET['skill_id']);
                        }
                        
                        if(check_user_input($con, $output, $player, $enemy_id, "check") !== false) {  //Check if the battle state is awaiting user input or not
                           check_user_input($con, $output, $player, $enemy_id, "display");
                        } else { //No input required from user, add the modified player data back into the database (with our new skill added)
                        
                        //Update database with updated info
                        $con->query("UPDATE battle SET attacker_data ='" . data_encode($player) . "', battle_last_active = " . TIME . " WHERE attacker = '$player->id' AND complete = 'no'");
                        $output .= "Skill added to " . $_GET['location'];
                        $output .= "<br><a class=\"btn btn-primary\" role=\"button\" href=\"battle.php?action=attack_monster&id=" . $_GET['id'] . "\">Continue Battle</a>";
                        }
                    }
                    break;
                } else {
                    $enemy_id = $_GET['id'];
                    $output .= battle_preliminary_check($con, $player, $enemy_id, 'monster', 'turn'); //Check if enemy_id exists
                    battle_check_info($con, $player, $enemy_id, $output, false); //Check if battle with enemy_id is valid or not
                    $skill_data = skill_data($con);
                    if($output == "") { //No errors, grab user's data so we can add stuff to it
                        if(check_user_input($con, $output, $player, $enemy_id, "check") !== false) {  //Check if the battle state is awaiting user input or not
                            check_user_input($con, $output, $player, $enemy_id, "display");
                        } else {
                            if(isset($_GET['purge'])) {
                                if($_GET['location'] == "hand" && $_GET['purge'] == true) {
                                    $player->skill_hand = array();
                                } elseif($_GET['location'] == "deck" && $_GET['purge'] == true) {
                                    $player->draw_pile = array();
                                }
                                $output .= ucfirst($_GET['location']) . " has been emptied<br>";
                            }
                        
                            for($i = 0; $i < $_GET['randomizer_quantity']; $i++) { 
                                $rand = rand(0, count($skill_data) - 1);
                                if($_GET['location'] == "hand") {
                                    array_push($player->skill_hand, $skill_data[$rand][0]);
                                    $output .= $skill_data[$rand][1] . " ";
                                } elseif($_GET['location'] == "deck") {
                                    array_push($player->draw_pile, $skill_data[$rand][0]);
                                    $output .= $skill_data[$rand][1] . " ";
                                }
                                
                            }
                            $con->query("UPDATE battle SET attacker_data ='" . data_encode($player) . "', battle_last_active = " . TIME . " WHERE attacker = '$player->id' AND complete = 'no'");
                            $output .= " were added to " . $_GET['location'];
                            $output .= "<br><a class=\"btn btn-primary\" role=\"button\" href=\"battle.php?action=attack_monster&id=" . $_GET['id'] . "\">Continue Battle</a>";                            
                        }
                    }
                    break;
                } 
            }
        case "shuffle":
        case "draw":
        case "discard":
        case "exhaust":
            
            //Check if skill is valid
            if(isset($_GET['id'])) {
                $output .= battle_preliminary_check($con, $player, $_GET['id'], 'monster', 'turn'); //Check if enemy_id exists
                battle_check_info($con, $player, $_GET['id'], $output, false); //Check if battle with enemy_id is valid or not
                if ($output != "") {
                    echo "Something went wrong...";
                } 

                if(isset($_GET['deck']) || isset($_GET['hand']) || isset($_GET['exhaust']) || isset($_GET['discard'])) {
                    

                    
                    $skill_data = skill_data($con); 
                    $returned_value = check_user_input($con, $output, $player, $_GET['id'], "check");
                    $quantity = (int) filter_var(substr($returned_value, strpos($returned_value, ":")), FILTER_SANITIZE_NUMBER_INT);
                    
                    $gain_energy = 0;
                    $add_array = array();
                    $remove_array = array();
                   
                    if(strpos($returned_value, "energy") !== false) {
                        $gain_energy = substr($returned_value, strpos($returned_value, "energy") + strlen("energy"), 1); //Gain energy equal to selected skill's energy requirement
                    }
                   
                    $origin = 0;
                    $source = 0;
                    
                    //This basically grabs the array from the $_GET parameter (which holds the POSITION id) and arranges it such that it can be parsed by the function
                    //For example; $_GET['deck'] [0] => 2 [1] => 5 would mean that skills 2 and 5 in the deck array have been selected
                    if(isset($_GET['deck'])) {
                        $_GET['deck'] = array_unique($_GET['deck']); //The array_unique removes duplicate id (eg user trying to exhaust skill slot 2 twice)
                        $source = $_GET['deck'];
                        $origin = 'draw_pile';
                        $origin_name = "deck";
                    } elseif(isset($_GET['hand'])) {
                        $_GET['hand'] = array_unique($_GET['hand']);
                        $source = $_GET['hand'];
                        $origin = 'skill_hand';
                        $origin_name = "hand";
                    } elseif(isset($_GET['exhaust'])) {
                        $_GET['exhaust'] = array_unique($_GET['exhaust']);
                        $source = $_GET['exhaust'];
                        $origin = 'skill_exhaust';
                        $origin_name = "exhaust zone";
                    } elseif(isset($_GET['discard'])) {
                        $_GET['discard'] = array_unique($_GET['discard']);
                        $source = $_GET['discard'];
                        $origin = 'skill_discard';
                        $origin_name = "discard pile";
                    }
                    
                    if ($quantity == 0) { //$quantity is the value of skills that are to be selected and is derived from $user->status
                        $origin = 0;
                        $source = 0;
                        echo "Something went wrong...";
                    } elseif(strpos($returned_value, "deck") !== false && (isset($_GET['hand']) || isset($_GET['exhaust_pile']) || isset($_GET['discard_pile']))) {
                        $origin = 0;
                        $source = 0;
                        echo "You can only choose skills from the deck!";
                    } elseif (strpos($returned_value, "hand") !== false && (isset($_GET['deck']) || isset($_GET['exhaust_pile']) || isset($_GET['discard_pile']))) {
                        $origin = 0;
                        $source = 0;
                        echo "You can only choose skills from the hand!";
                    } elseif (strpos($returned_value, "exhaust_pile") !== false && (isset($_GET['deck']) || isset($_GET['hand']) || isset($_GET['discard_pile']))) {
                        $origin = 0;
                        $source = 0;
                        echo "You can only skills from the exhaust pile!";
                    } elseif (strpos($returned_value, "discard_pile") !== false && (isset($_GET['deck']) || isset($_GET['exhaust_pile']) || isset($_GET['hand']))) {
                        $origin = 0;
                        $source = 0;
                        echo "You can only choose skills from the discard pile!";
                    } 
                    

                    if($origin !== 0 && $source !== 0) { //No errors, grab user's data so we can add stuff to it
                        for($i = 0; $i < count($source); $i++) {
                            
                            if(strpos($returned_value, "selectable_skills") !== false) {
                                $attribute = "selectable";
                            } elseif (strpos($returned_value, "optional_skills") !== false) {
                                $attribute = "optional";
                            } else {
                                echo "You cannot select skills to " . $_GET['action'] . " at this stage!";
                                break;
                            }
                    
                            if(!array_key_exists($source[$i], $player->$origin)) {
                                echo "The selected skill cannot be found in your " . $origin_name;
                                break;
                            } elseif (count($source) > $quantity) {
                                echo "You have selected too many skills";
                                break;
                            } else {
                                array_push($add_array, $source[$i]);
                                array_push($remove_array, $source[$i]);
                            }
                        }
                        
                        if(!empty($add_array) && !empty($remove_array)) {
                            //Set destination for skills
                            if($_GET['action'] == "exhaust") {
                                $destination = "skill_exhaust";
                                $destination_name = "exhaust zone";
                            } elseif($_GET['action'] == "draw") {
                                $destination = "skill_hand";
                                $destination_name = "hand";
                            } elseif($_GET['action'] == "shuffle") {
                                $destination = "draw_pile";
                                $destination_name = "deck";
                            } elseif($_GET['action'] == "discard") {
                                $destination = "skill_discard";
                                $destination_name = "discard pile";
                            } 
                            
                            for($i = 0; $i < count($add_array); $i++) {
                                $array_search_key = array_search($player->$origin[$add_array[$i]], array_column($skill_data, 0)); 
                                $skill_info =  $skill_data[$array_search_key]; 
                                array_push($player->$destination, $player->$origin[$add_array[$i]]);
                                echo $skill_info[1] . " was added to " . $destination_name ."<br>";
                                
                                if($gain_energy > 0) {
                                    $player->battle_energy = min($player->max_energy, $player->battle_energy  + $skill_info[6] * $gain_energy);
                                    echo "Gained " . ($skill_info[6] * $gain_energy) . " energy (Current energy: " . $player->battle_energy . ")<br>";
                                }
                            } 
                            
                            for($i = 0; $i < count($remove_array); $i++) {
                                $array_search_key = array_search($player->$origin[$remove_array[$i]], array_column($skill_data, 0)); 
                                $skill_info =  $skill_data[$array_search_key]; 
                                echo $skill_info[1] . " was removed from the " . $origin_name . "<br>";
                                unset($player->$origin[$remove_array[$i]]);
                            } 
                            echo "The skills in the " . $origin_name . " have been shuffled<br>";
                            
                            //This function rekeys the affected skill pile array (such as the deck) such that they go sequentially from 0; array_values($player->$origin) doesn't work when the keys and values are integers for some reason
                            $player->$origin = array_merge($player->$origin);
                            shuffle($player->$origin);
    
                            //Update the $player->status (either remove this particular status if no more skills can be selected or update the number of skills that can be selected)
                            if($quantity == count($source)) {
                                $array_search_key = array_search($_GET['action'] . "_" . $attribute . "_skills_" . $origin_name . ":" . $quantity, $player->status);
                                array_splice($player->status, $array_search_key, 1);
                                
                            } else {
                                $array_search_key = array_search($_GET['action']. "_" . $attribute . "_skills_" . ":" . $quantity, $player->status);
                                $player->status[$array_search_key] =  substr($returned_value, 0, strpos($returned_value, ":") + 1) . ($quantity -  count($source));
                            }
                            
                            //Send the updated data to the database
                            $con->query("UPDATE battle SET attacker_data ='" . data_encode($player) . "', battle_last_active = " . TIME . " WHERE attacker = '$player->id' AND complete = 'no'");
                            
                            echo "<div class=\"col-md-12 linebreak\"></div><a class=\"btn btn-primary\" role=\"button\" href=\"battle.php?action=attack_monster&id=" . $_GET['id'] . "\">Continue Battle</a>";
                          
                        }
                    }
                }
            }
            
            break;
        case "end_selection":
            //Check if skill is valid
            if(isset($_GET['id'])) {
                $output .= battle_preliminary_check($con, $player, $_GET['id'], 'monster', 'turn'); //Check if enemy_id exists
                battle_check_info($con, $player, $_GET['id'], $output, false); //Check if battle with enemy_id is valid or not
                if ($output != "") {
                    echo "Something went wrong...";
                } else {
                    $returned_value = check_user_input($con, $output, $player, $_GET['id'], "check");
                    if(strpos($returned_value, "optional")) {
                        //End selection
                        $array_search_key = array_search($returned_value, $player->status);
                        array_splice($player->status, $array_search_key, 1);
                        $con->query("UPDATE battle SET attacker_data ='" . data_encode($player) . "', battle_last_active = " . TIME . " WHERE attacker = '$player->id' AND complete = 'no'");
                        $output .= "<div class=\"col-md-12 linebreak\"></div><a class=\"btn btn-primary\" role=\"button\" href=\"battle.php?action=attack_monster&id=" . $_GET['id'] . "\">Continue Battle</a>";
                    } else {
                        $output .= "You must select skills in order to continue the battle! <br></div><a class=\"btn btn-primary\" role=\"button\" href=\"battle.php?action=attack_monster&id=" . $_GET['id'] . "\">Continue Battle</a>" ;
                    }
                }
            }
            break;
        default:
            $output .= "Action not supported!";
    }
}

echo $output;
?>

<script>

var limit = document.getElementById('select-quantity').value;
$('input.multi-checkbox').on('change', function(evt) {
   if($(this).siblings(':checked').length >= limit) {
       this.checked = false;
   }
});

</script>