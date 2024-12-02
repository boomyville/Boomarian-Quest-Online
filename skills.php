<?php

/****************************************/
/*            BoomyRPG script           */
/*           Written by Boomy           */
/*            Battle Skills             */
/****************************************/

//This file represents skills that ma be used in battle. These have to be hardcoded in this file
//Skills are applicable to both player and monsters
//Skill ID represent a unique skills and ID starts at 10000 
//Upgraded skills will be represented by an increase in ID by 1 (eg 10001)
//Difference skills are represented in changes by 10s (eg 10020 and 10030 will be different skills)
//Type of skill represents if the skill is physical offensive, magical offensive, defensive, self-buff, enemy-debuff or other
//Energy_cost represents how much energy the skill consumes. Default is 3
//Effect is a string that represents what the skill does. These effects are hardcoded into skills.php
//Value_A represents a value relating to the skill. This is the primary value used to determine the effect of the skill. Eg Value_A = 5 for a block skill would mean that the skill generates 5 block
//Value_B represents a value relating to the skill. This is a secondary value used to determine tehe effect of the skill. Eg Value_B = 3 for a debuff skill would mean that the skill causes a debuff for 3 turns
//Value_C represents a value relating to the skill. This is a tertiary value used to determine the effect of the skill. Eg Value_C = 0.5 for a 50% chance of skill being duplicated upon use
//Value_D and Value_E are extra values relating to skills. They often will go unused
//Skill_requirement is a string that denotes conditions for a skill to be used. For example Health-50 would mean that the user must have at least 50% health to use the particular skill. Hardcorded into skills.php
//End_turn is a boolean value that determines if the skill ends the user's turn or not
//Exhaust is a probability a skill is removed from the battle when used. If set to 0, then the skill is placed into the discard pile when used
//Crit is the bonus crit chance. Only applies to the skill. Can be negative. If set to -1 then the skill will never crit.
//Cost is the value in gold that the skill is worth
//Priority is a integer number on the importance of the skill relative to other skills. The higher the number, the more likely the AI will use it. High priority skills are considered to be more 'top tier' in general

//Other important variables
//Skill_draw determines how many skills are drawn per turn
//Skill_hand_size is the maxmium number of skills a combatant can carry at any one time 
//Skill_discard determines if skills are discarded at the end of the turn or not
//Skill_discard_reset determines if skills from the discard pile are returned to the draw pile when the draw pile is empty or not

//All these functions require config.php to work

//Define font styles
//These are defined as they are global values; this allows functions to access these variables without needing to pass the variables through the function itself
//These are HTML / CSS codes that determine the font-style of various battle messages
define("PLAYER_DEFAULT_FONT", "<span style=\"text-shadow: 0 0 3px rgb(0,240,0), 0 0 6px rgb(0, 160, 0), 0 0 9px rgb(0, 80, 0); color:rgb(0,220,0)\">");
define("PLAYER_BUFF_FONT", "<span style=\"text-shadow: 0 0 5px rgb(0,240,0), 0 0 10px rgb(0, 200, 0), 0 0 15px rgb(0, 120, 0); color:rgb(255,255,255)\">");
define("PLAYER_BLOCKED_FONT", "<span style=\"color:rgb(0,180,0)\">");
define("PLAYER_MISS_FONT", "<span style=\"color:rgb(0,100,0)\">");
define("ENEMY_DEFAULT_FONT", "<span style=\"color:rgb(255,0,0)\">");
define("ENEMY_BUFF_FONT", "<span style=\"color:rgb(255,0,0)\">");
define("ENEMY_BLOCKED_FONT", "<span style=\"color:rgb(255,0,0)\">");
define("ENEMY_MISS_FONT", "<span style=\"color:rgb(200,0,0)\">");
define("DEFAULT_DEAD_FONT", "(<span style=\"text-shadow: 0 0 1px rgb(255, 0, 0), 0 3px 1px rgb(200, 0, 0), 0 6px 1px rgb(160, 0, 0), 0 9px 1px rgb(120, 0, 0), 0 12px 1px rgb(80, 0, 0); color:rgb(190,190,190)\">Fallen</span>)</span><br>");
    

//Query to grab skill data
function skill_data($con) {
    $query = mysqli_query($con, "SELECT * FROM skills");
    return mysqli_fetch_all($query);
}

//This function adds skills to the pool of possible actions that can be selected by the auto-battle AI
//This function is called at the start of battle (only)
//It sets up various values that are added to the $player or $enemy arrays (which are already established by other functions in battle.php and passed through using the $unit variable)
//Variables include: Draw pile (skills that could possibly be used), discard pile (skills that have been used), exhaust pile (skills that have been used and unavailable) and the hand (skills that are drawn and usable now)
//This function also simulates shuffling the draw pile, adding skills to the hand from the draw pile and returning cards from the discard pile into the draw pile (if needed; highly unlikely however)
function skill_initialise(&$unit) {            
    //There are several variables/array relating to skills in battle
    //Draw pile is the list of skills that can be drawn
    //Hand is the skills available to be used; they are drawn from the draw pile
    //Discard is where used skills are put into
    //Draw is how many skill that are drawn per turn
    $unit->draw_pile = explode(',', $unit->skills);
    $unit->skill_hand = array(); //create empty array
    $unit->skill_discard = array(); 
    $unit->skill_exhaust = array();
    shuffle($unit->draw_pile); //Shuffle the draw pile
    for($i=0; $i < $unit->battle_draw_per_turn; $i++) {
        if (count($unit->draw_pile) > 0) { //Skills still exist in skill_draw pile 
            //Add skills based on the value of skill_draw
            array_push($unit->skill_hand, current($unit->draw_pile));
            //Remove added skill from the draw pile
            array_splice($unit->draw_pile, 0, 1);
           
        }
        else {
            //Shuffle discard pile into draw_pile
            $unit->draw_pile = $unit->skill_discard;
            $unit->skill_discard = array(); //empty discard pile
            shuffle($draw_pile); //shuffle the new draw pile
            if (count($unit->draw_pile) > 0) { //There are skills in the draw pile
                //Add skills based on the value of skill_draw
                array_push($unit->skill_hand, current($unit->draw_pile));
                //Remove added skill from the draw pile
                array_splice($unit->draw_pile, 0, 1);    
            }
            else {
                //Player out of skills; game over
                echo "No more skills left";
            }
        }
    }
}

//This function adds draws skills to the attacker's hand at the start of the turn
//The discard pile is shuffled back into the deck if the deck is empty
function skill_draw(&$unit, $draw_amount, &$output) {
    
    for($i=0; $i < $draw_amount; $i++) {
        if (count($unit->draw_pile) > 0) { //Skills still exist in skill_draw pile 
            //Add skills based on the value of skill_draw
            array_push($unit->skill_hand, current($unit->draw_pile));
            //Remove added skill from the draw pile
            array_splice($unit->draw_pile, 0, 1);
        }
        else {
            //Shuffle discard pile into draw_pile
            $unit->draw_pile = $unit->skill_discard;
            $unit->skill_discard = array(); //empty discard pile
            shuffle($unit->draw_pile);
            if(count($unit->draw_pile) > 0) {
                $i--;
                //Only draw if you have cards left in the draw_pile otherwise your deck is empty
            }
        }
    }
}

//This function checks conditions that have been set by "requirement_A and requirement_B" skill_info[12] and skill_info[13]
//Either returns true or false which is then used by other functions
//These requirements are set in the database. This basically sets up the skill_conditional function (which does all the actual checking and is editable)
function skill_requirement_check($con, $skill_id, $skill_data, $user, &$output) {
    //Use skill_data to grab skill info
    $query = mysqli_query($con, "SELECT * FROM skills WHERE id = '$skill_id' LIMIT 1");
    if(mysqli_num_rows($query) == 0) {
        //No valid skill exists
        return false;
    } else { //Skill exists
        
        //Find information about skill_id['id'] in the master skill_data array (which is grabbed directly from the database)
        $array_search_key = array_search($skill_id, array_column($skill_data, 0)); 
        
        //Now we have the skill id; grab its info
        $skill_info =  $skill_data[$array_search_key];
        
        //This variable is returned when this function is called (true for passing skill requirements; false for not)
        //It is initially set to true and will be set to false if ANY requirements are failed
        $returned_value = true;

            if($skill_info[12]) {
                $req_A = explode(":", $skill_info[12]);
            }
            if($skill_info[13]) {
                $req_B = explode(":", $skill_info[13]);
            }
            if(isset($req_A) && isset($req_B)) {
                $requirements = array_merge($req_A, $req_B);
            } elseif (isset($req_A) && !isset($req_B)) {
                $requirements = $req_A;
            } elseif (!isset($req_A) && isset($req_B)) {
                $requirements = $req_B;
            } else {
                return true; //No requirements to meet
            }
            
      if(isset($requirements)) { 
          skill_conditional($requirements, $user, $output, $returned_value);
      }
    return $returned_value;
    }
}

//This function is basically the databank of requirements that can be set on skills for their usage
//it follows a fairly strict pattern of requirement:x where requirement is a string (and to be selected from the selections below) and x is usually an integer
//This function can be edited to add new requirements

//List of conditionals:
//Hand size
//Skills played (this turn / this battle)
//Health (Percentage and value)
//Block
//Damage 
//Damage ratio 
//Blocked-to-unblocked damage ratio
//Battle turns

function skill_conditional($requirements, $user, &$output, &$returned_value) {
  //Requirement: Max skills in hand (No more than X skills in hand)
    if(array_search("max_hand_size", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_hand_size', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Hand size of " . $user->username . ": " . count($user->skill_hand) . "<br>";
        }
        
        if(count($user->skill_hand) > $requirements[$key2]) {
            $output .= "This skill can only be played if " . $requirements[$key2] . " or less skill(s) are in your hand (including this skill)<br>";
            $returned_value = false; 
            }
    }
  //Requirement: Min skills in hand (At least X skills in hand)
    if(array_search("min_hand_size", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_hand_size', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Hand size of " . $user->username . ": " . count($user->skill_hand) . "<br>";
        }
        
        if(count($user->skill_hand) < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " skill(s) are in your hand (including this skill)<br>";
            $returned_value = false; 
            }
    }    
    
      //Requirement: Max skills played in one turn (No more than X skills played this turn)
    if(array_search("max_skills_played", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_skills_played', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Skills played by " . $user->username . ": " . substr_count($user->skills_played[$user->battle_turns], ",") . "<br>";
        }
        
        if(substr_count($user->skills_played[$user->battle_turns], ",") > $requirements[$key2]) {
            $output .= "This skill can only be played if " . $requirements[$key2] . " or less skill(s) have been played this turn<br>";
            $returned_value = false; 
            }
    }
    
    //Requirement: Max attacks played in one turn (No more than X attacks played this turn)
    if(array_search("max_attacks_played", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_attacks_played', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Attacks played by " . $user->username . ": " . substr_count($user->skills_played[$user->battle_turns], "attack") . "<br>";
        }
        
        if(substr_count($user->skills_played[$user->battle_turns], "attack") > $requirements[$key2]) {
            $output .= "This skill can only be played if " . $requirements[$key2] . " or less attacks(s) have been played this turn<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Max block skills played in one turn
    if(array_search("max_block_skills_played", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_block_skills_played', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Defensive skills played by " . $user->username . ": " . substr_count($user->skills_played[$user->battle_turns], "defensive") . "<br>";
        }
        
        if(substr_count($user->skills_played[$user->battle_turns], "defensive") > $requirements[$key2]) {
            $output .= "This skill can only be played if " . $requirements[$key2] . " or less defensive skill(s) have been played this turn<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Max buff skills played in one turn
    if(array_search("max_buff_skills_played", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_buff_skills_played', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Abilities played by " . $user->username . ": " . substr_count($user->skills_played[$user->battle_turns], "buff") . "<br>";
        }
        
        if(substr_count($user->skills_played[$user->battle_turns], "buff") > $requirements[$key2]) {
            $output .= "This skill can only be played if " . $requirements[$key2] . " or less abilities have been played this turn<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Max debuff skills played in one turn
    if(array_search("max_debuff_skills_played", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_debuff_skills_played', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Debuff skills played by " . $user->username . ": " . substr_count($user->skills_played[$user->battle_turns], "debuff") . "<br>";
        }
        
        if(substr_count($user->skills_played[$user->battle_turns], "debuff") > $requirements[$key2]) {
            $output .= "This skill can only be played if " . $requirements[$key2] . " or less debuff skill(s) have been played this turn<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Max non-offensive skills played in one turn
    if(array_search("max_non_offensive_skills_played", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_non_offensive_skills_played', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Non-offensive skills played by " . $user->username . ": " . (substr_count($user->skills_played[$user->battle_turns], ",") - substr_count($user->skills_played[$user->battle_turns], "offensive")) . "<br>";
        }
        
        if((substr_count($user->skills_played[$user->battle_turns], ",") - substr_count($user->skills_played[$user->battle_turns], "offensive")) > $requirements[$key2]) {
            $output .= "This skill can only be played if " . $requirements[$key2] . " or less non-attacking skill(s) have been played this turn<br>";
            $returned_value = false;
        }
    }

    //Requirement: Max skills played in one battle
    if(array_search("max_skills_played_all_battle", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_skills_played_all_battle', $requirements);
        $key2 = $key + 1;
        $count = 0;
        for($i=1; $i <= $user->battle_turns; $i++) { //Check how many skills were played this entire battle by manually checking each turn  
            $count += substr_count($user->skills_played[$i], ",");
        }
        if(DEBUG) {
            $output .= "Skills played by " . $user->username . ": " . $count . " this battle<br>";
        }
        
        if($count > $requirements[$key2]) {
            $output .= "This skill can only be played if " . $requirements[$key2] . " or less skill(s) have been played this battle<br>";
            $returned_value = false;
        }
    }
            
    //Requirement: Max attacks played in one battle
    if(array_search("max_attacks_played_all_battle", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_attacks_played_all_battle', $requirements);
        $key2 = $key + 1;
        $count = 0;
        for($i=1; $i <= $user->battle_turns; $i++) { //Check how many skills were played this entire battle by manually checking each turn  
            $count += substr_count($user->skills_played[$i], "attack");
        }            
        if(DEBUG) {
            $output .= "Skills played by " . $user->username . ": " . $count . " this battle<br>";
        }
        
        if($count > $requirements[$key2]) {
            $output .= "This skill can only be played if " . $requirements[$key2] . " or less attacks(s) have been played this battle<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Max block skills played in one battle
    if(array_search("max_block_skills_played_all_battle", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_block_skills_played_all_battle', $requirements);
        $key2 = $key + 1;
        $count = 0;
        for($i=1; $i <= $user->battle_turns; $i++) { //Check how many skills were played this entire battle by manually checking each turn  
            $count += substr_count($user->skills_played[$i], "defensive");
        }            
        if(DEBUG) {
            $output .= "Skills played by " . $user->username . ": " . $count . " this battle<br>";
        }
        
        if($count > $requirements[$key2]) {
            $output .= "This skill can only be played if " . $requirements[$key2] . " or less defensive skill(s) have been played this battle<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Max buff skills played in one battle
    if(array_search("max_buff_skills_played_all_battle", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_buff_skills_played_all_battle', $requirements);
        $key2 = $key + 1;
        $count = 0;
        for($i=1; $i <= $user->battle_turns; $i++) { //Check how many skills were played this entire battle by manually checking each turn  
            $count += substr_count($user->skills_played[$i], "buff");
        }                 
        if(DEBUG) {
            $output .= "Skills played by " . $user->username . ": " . $count . " this battle<br>";
        }
        
        if($count > $requirements[$key2]) {
            $output .= "This skill can only be played if " . $requirements[$key2] . " or less abilities have been played this battle<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Max debuff skills played in one battle
    if(array_search("max_debuff_skills_played_all_battle", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_debuff_skills_played_all_battle', $requirements);
        $key2 = $key + 1;
        $count = 0;
        if(DEBUG) {
            $output .= "Skills played by " . $user->username . ": " . $count . " this battle<br>";
        }
        
        if($count > $requirements[$key2]) {
            $output .= "This skill can only be played if " . $requirements[$key2] . " or less debuffs have been played this battle<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Max non-offensive skills played in one battle
    if(array_search("max_non_offensive_skills_played_all_battle", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_non_offensive_skills_played_all_battle', $requirements);
        $key2 = $key + 1;
        $count = 0;
        for($i=1; $i <= $user->battle_turns; $i++) { //Check how many skills were played this entire battle by manually checking each turn  
            $count += substr_count($user->skills_played[$i], ",");
        }
        for($i=1; $i <= $user->battle_turns; $i++) { //Check how many skills were played this entire battle by manually checking each turn  
            $count -= substr_count($user->skills_played[$i], "attack");
        }            
        if(DEBUG) {
            $output .= "Non-attack skills played by " . $user->username . ": " . $count . " this battle<br>";
        }
        if($count > $requirements[$key2]) {
            $output .= "This skill can only be played if " . $requirements[$key2] . " or less non-attacking skills have been played this battle<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Minimum skills played in one turn
    if(array_search("min_skills_played", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_skills_played', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Skills played by " . $user->username . ": " . substr_count($user->skills_played[$user->battle_turns], ",") . "<br>";
        }
        
        if(substr_count($user->skills_played[$user->battle_turns], ",") < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " skill(s) have been played this turn<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Minimum attacks played in one turn
    if(array_search("min_attacks_played", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_attacks_played', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Attacks played by " . $user->username . ": " . substr_count($user->skills_played[$user->battle_turns], "attack") . "<br>";
        }
        
        if(substr_count($user->skills_played[$user->battle_turns], "attack") < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " attacks(s) have been played this turn<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Minimum block skills played in one turn
    if(array_search("min_block_skills_played", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_block_skills_played', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Defensive skills played by " . $user->username . ": " . substr_count($user->skills_played[$user->battle_turns], "defensive") . "<br>";
        }
        
        if(substr_count($user->skills_played[$user->battle_turns], "defensive") < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " defensive skill(s) have been played this turn<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Minimum buff skills played in one turn
    if(array_search("min_buff_skills_played", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_buff_skills_played', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Abilities played by " . $user->username . ": " . substr_count($user->skills_played[$user->battle_turns], "buff") . "<br>";
        }
        
        if(substr_count($user->skills_played[$user->battle_turns], "buff") < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " abilities have been played this turn<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Minimum debuff skills played in one turn
    if(array_search("min_debuff_skills_played", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_debuff_skills_played', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Debuff skills played by " . $user->username . ": " . substr_count($user->skills_played[$user->battle_turns], "debuff") . "<br>";
        }
        
        if(substr_count($user->skills_played[$user->battle_turns], "debuff") < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " debuff skill(s) have been played this turn<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Min non-offensive skills played in one turn
    if(array_search("min_non_offensive_skills_played", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_non_offensive_skills_played', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Non-offensive skills played by " . $user->username . ": " . (substr_count($user->skills_played[$user->battle_turns], ",") - substr_count($user->skills_played[$user->battle_turns], "offensive")) . "<br>";
        }
        
        if((substr_count($user->skills_played[$user->battle_turns], ",") - substr_count($user->skills_played[$user->battle_turns], "offensive")) < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " non-attacking skill(s) have been played this turn<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Minimum skills played in one battle
    if(array_search("min_skills_played_all_battle", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_skills_played_all_battle', $requirements);
        $key2 = $key + 1;
        $count = 0;
        for($i=1; $i <= $user->battle_turns; $i++) { //Check how many skills were played this entire battle by manually checking each turn  
            $count += substr_count($user->skills_played[$i], ",");
        }
        if(DEBUG) {
            $output .= "Skills played by " . $user->username . ": " . $count . " this battle<br>";
        }
        if($count < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " skill(s) have been played this battle<br>";
            $returned_value = false;
        }
    }
            
    //Requirement: Minimum attacks played in one battle
    if(array_search("min_attacks_played_all_battle", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_attacks_played_all_battle', $requirements);
        $key2 = $key + 1;
        $count = 0;
        for($i=1; $i <= $user->battle_turns; $i++) { //Check how many skills were played this entire battle by manually checking each turn  
            $count += substr_count($user->skills_played[$i], "attack");
        }            
        if(DEBUG) {
            $output .= "Skills played by " . $user->username . ": " . $count . " this battle<br>";
        }
        
        if($count < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " attacks(s) have been played this battle<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Minimum block skills played in one battle
    if(array_search("min_block_skills_played_all_battle", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_block_skills_played_all_battle', $requirements);
        $key2 = $key + 1;
        $count = 0;
        for($i=1; $i <= $user->battle_turns; $i++) { //Check how many skills were played this entire battle by manually checking each turn  
            $count += substr_count($user->skills_played[$i], "defensive");
        }            
        if(DEBUG) {
            $output .= "Skills played by " . $user->username . ": " . $count . " this battle<br>";
        }
        
        if($count < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " defensive skills have been played this battle<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Minimum buff skills played in one battle
    if(array_search("min_buff_skills_played_all_battle", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_buff_skills_played_all_battle', $requirements);
        $key2 = $key + 1;
        $count = 0;
        for($i=1; $i <= $user->battle_turns; $i++) { //Check how many skills were played this entire battle by manually checking each turn  
            $count += substr_count($user->skills_played[$i], "buff");
        }                 
        if(DEBUG) {
            $output .= "Skills played by " . $user->username . ": " . $count . " this battle<br>";
        }
        
        if($count < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " abilities have been played this battle<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Minimum debuff skills played in one battle
    if(array_search("min_debuff_skills_played_all_battle", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_debuff_skills_played_all_battle', $requirements);
        $key2 = $key + 1;
        $count = 0;
        if(DEBUG) {
            $output .= "Skills played by " . $user->username . ": " . $count . " this battle<br>";
        }
        
        if($count < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " debuffs have been played this battle<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Minimum non-offensive skills played in one battle
    if(array_search("min_non_offensive_skills_played_all_battle", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_non_offensive_skills_played_all_battle', $requirements);
        $key2 = $key + 1;
        $count = 0;
        for($i=1; $i <= $user->battle_turns; $i++) { //Check how many skills were played this entire battle by manually checking each turn  
            $count += substr_count($user->skills_played[$i], ",");
        }
        for($i=1; $i <= $user->battle_turns; $i++) { //Check how many skills were played this entire battle by manually checking each turn  
            $count -= substr_count($user->skills_played[$i], "attack");
        }            
        if(DEBUG) {
            $output .= "Non-attack skills played by " . $user->username . ": " . $count . " this battle<br>";
        }
        if($count < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " non-attacking skills have been played this battle<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Health above X
    if(array_search("min_health", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_health', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Current Health of " . $user->username . ": " . $user->hp . "<br>";
        }
        if($user->hp < $requirements[$key2]) {
            $output .= "This skill can only be played if the user has at least " . $requirements[$key2] . " health<br>";
            $returned_value = false;
        }
    }

    //Requirement: Health below X
    if(array_search("max_health", $requirements) === false) {
        //Continue
    } else {
         $key = array_search('max_health', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Current Health of " . $user->username . ": " . $user->hp . "<br>";
        }
        if($user->hp > $requirements[$key2]) {
            $output .= "This skill can only be played if the user has less than " . $requirements[$key2] . " health<br>";
            $returned_value = false;
        }
    }

    //Requirement: Percentage Health above X
    if(array_search("min_percentage_health", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_percentage_health', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Current Health of " . $user->username . ": " . round($user->hp / $user->maxhp * 100) . "%<br>";
        }
        if($user->hp / $user->maxhp * 100 < $requirements[$key2]) {
            $output .= "This skill can only be played if the user has more than " . $requirements[$key2] . "% health<br>";
            $returned_value = false;
        }
    }

    //Requirement: Percentage Health below X
    if(array_search("max_percentage_health", $requirements) === false) {
        //Continue
    } else {
         $key = array_search('max_percentage_health', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Current Health of " . $user->username . ": " . round($user->hp / $user->maxhp * 100) . "%<br>";
        }
        if($user->hp / $user->maxhp * 100 >= $requirements[$key2]) {
            $output .= "This skill can only be played if the user has less than " . $requirements[$key2] . " health<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Block above X
    if(array_search("min_block", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_block', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Current Block of " . $user->username . ": " . $user->block . "<br>";
        }
        if($user->block < $requirements[$key2]) {
            $output .= "This skill can only be played if the user has more than " . $requirements[$key2] . " block<br>";
            $returned_value = false;
        }
    }

    //Requirement: Block below X
    if(array_search("max_block", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_block', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Current Block of " . $user->username . ": " . $user->block . "<br>";
        }
        if($user->block > $requirements[$key2]) {
            $output .= "This skill can only be played if the user has less than or equal to " . $requirements[$key2] . " block<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Total damage above X
    if(array_search("min_total_damage", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_total_damage', $requirements);
        $key2 = $key + 1;
        if(DEBUG && isset($user->damage_given['total'])) {
            $output .= "Total damage inflicted by " . $user->username . " this battle: " . $user->damage_given['total'] . "<br>";
        }
        if(isset($user->damage_given['total'])) {
            if($user->damage_given['total'] < $requirements[$key2]) {
                $output .= "This skill can only be played if the user has more than " . $user->damage_given['total'] . " damage<br>";
                $returned_value = false;
            }
        }
    }    
    
    //Requirement: Total damage below X
    if(array_search("max_total_damage", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_total_damage', $requirements);
        $key2 = $key + 1;
        if(DEBUG && isset($user->damage_given['total'])) {
            $output .= "Total damage inflicted by " . $user->username . " this battle: " . $user->damage_given['total'] . "<br>";
        }
        if(isset($user->damage_given['total']))
            if($user->damage_given['total'] > $requirements[$key2]) {
                $output .= "This skill can only be played if the user less than " . $user->damage_given['total'] . " damage<br>";
                $returned_value = false;
        }
    }  
    
    //Requirement: Total damage given / Total damage ratio above X  (Inflict more damage than taken)
    if(array_search("min_damage_given_taken_ratio", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_damage_given_taken_ratio', $requirements);
        $key2 = $key + 1;
        
        if(DEBUG && isset($user->damage_given['total']) && isset($user->damage_taken['total'])) {
            $output .= "Damaged dished out by " . $user->username . ": " . $user->damage_given['total'] . " | Damage received: " . $user->damage_taken['total'] . "<br>";
        }
        
        if(isset($user->damage_given['total'])) {
            if($user->damage_given['total'] > 0) {
                if(isset($user->damage_taken['total'])) {
                    if($user->damage_given['total'] / ($user->damage_given['total'] + $user->damage_taken['total']) * 100 < $requirements[$key2]) {
                        $output .= "This skill can only be played if the user has inflicted at least " . round($requirements[$key2] * 0.01 * ($user->damage_taken['total'] + $user->damage_given['total'])) . " damage <br>";
                        $returned_value = false;
                    }
                }
            } else {
                $output .= "You have not inflicted any damage this battle!<br>";
                $returned_value = false;
            }
        }
        else {
            $output .= "You have not inflicted any damage this battle!<br>";
            $returned_value = false;
        }
    }

    //Requirement: Total damage given / Total damage ratio below X  (Inflict less damage than taken)
    if(array_search("max_damage_given_taken_ratio", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_damage_given_taken_ratio', $requirements);
        $key2 = $key + 1;
        
        if(DEBUG && isset($user->damage_given['total']) && isset($user->damage_taken['total'])) {
            $output .= "Damaged dished out by " . $user->username . ": " . $user->damage_given['total'] . " | Damage received: " . $user->damage_taken['total'] . "<br>";
        }
        
        if(isset($user->damage_taken['total'])) {
            if($user->damage_taken['total'] > 0) {
                if(isset($user->damage_given['total'])) {
                    if($user->damage_taken['total'] / ($user->damage_given['total'] + $user->damage_taken['total']) * 100 < $requirements[$key2]) {
                        $output .= "This skill can only be played if the user has taken at least " . round($requirements[$key2] * 0.01 * ($user->damage_taken['total'] + $user->damage_given['total'])) . " damage <br>";
                        $returned_value = false;
                    }
                }
            } else {
                $output .= "You have not taken any damage this battle!<br>";
                $returned_value = false;
            }
        }
        else {
            $output .= "You have not taken any damage this battle!<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Unblocked / blocked damage ratio above X 
    if(array_search("min_unblocked_blocked_damage_ratio", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_unblocked_blocked_damage_ratio', $requirements);
        $key2 = $key + 1;
        
        if(isset($user->damage_given['blocked'])) {
            if($user->damage_given['blocked'] > 0) {
                if($user->damage_given['unblocked'] / $user->damage_given['total'] * 100 > $requirements[$key2]) {
                    $output .= "This skill can only be played if the user has inflicted at least " . round($requirements[$key2] * 0.01 * $user->damage_given['total']) . " blocked damage <br>";
                    $returned_value = false;
                }
            } else {
                $output .= "The opponent has not blocked any damage done by you this battle!<br>";
                $returned_value = false;
            }
        }
        else {
            $output .= "The opponent has not blocked any damage done by you this battle!<br>";
            $returned_value = false;
        }
    }

    //Requirement: Unblocked / blocked damage ratio below X
    if(array_search("max_unblocked_blocked_damage_ratio", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_unblocked_blocked_damage_ratio', $requirements);
        $key2 = $key + 1;
    if(DEBUG && isset($user->damage_given['unblocked']) && isset($user->damage_given['blocked'])) {
        $output .= "Unblocked / blocked damage by " . $user->username . ": " . $user->damage_given['unblocked'] . " / " . $user->damage_given['blocked'] . " (Unblocked damage: " . round($user->damage_given['unblocked'] / $user->damage_given['total'] * 100) . "% | Blocked damage: " .  round($user->damage_given['blocked'] / $user->damage_given['total'] * 100) . "%)<br>";
    }
    
    if(isset($user->damage_given['unblocked'])) {
        if($user->damage_given['blocked'] > 0) {
            if($user->damage_given['unblocked'] / $user->damage_given['total'] * 100 < $requirements[$key2]) {
                $output .= "This skill can only be played if the user has inflicted at least " . round($requirements[$key2] * 0.01 * $user->damage_given['total']) . " unblocked damage <br>";
                $returned_value = false;
            }
        } else {
            $output .= "The opponent has not received any damage this battle!<br>";
            $returned_value = false;
        }
    } else {
        $output .= "The opponent has not received any damage this battle!<br>";
        $returned_value = false;
        }
    }
    
    //Requirement: Turns above X
    if(array_search("min_battle_turns", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_battle_turns', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Current turn" . $user->battle_turns . "<br>";
        }
        if($user->battle_turns < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " turns have elapsed<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Turns below X
    if(array_search("max_battle_turns", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_battle_turns', $requirements);
        $key2 = $key + 1;
        if(DEBUG) {
            $output .= "Current turn" . $user->battle_turns . "<br>";
        }
        if($user->battle_turns > $requirements[$key2]) {
            $output .= "This skill can only be played if less than " . $requirements[$key2] . " turns have elapsed<br>";
            $returned_value = false;
        }
    }
    
    //Requirement: Buff count above X
    if(array_search("min_buffs", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_buffs', $requirements);
        $key2 = $key + 1;
        
        $buff_count = 0;
        for($i = 0; $i < count($user->status); $i++) {
            $value = (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
            if($value > 0) {
                $buff_count++;
            }
        }
        
        if(DEBUG) {
            $output .= "Current number of buffs: " . $buff_count . "<br>";
        }
        if($buff_count < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " buffs have been applied on " . $user->username . "<br>";
            $returned_value = false;
        }
    } 
    
    //Requirement: Buff count below X
    if(array_search("max_buffs", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_buffs', $requirements);
        $key2 = $key + 1;
        
        $buff_count = 0;
        for($i = 0; $i < count($user->status); $i++) {
            $value = (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
            if($value > 0) {
                $buff_count++;
            }
        }
        
        if(DEBUG) {
            $output .= "Current number of buffs: " . $buff_count . "<br>";
        }
        if($buff_count > $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " buffs have been applied on " . $user->username . "<br>";
            $returned_value = false;
        }
    }    

    //Requirement: Debuff count above X
    if(array_search("min_debuffs", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('min_debuffs', $requirements);
        $key2 = $key + 1;
        
        $debuff_count = 0;
        for($i = 0; $i < count($user->status); $i++) {
            $value = (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
            if($value > 0) {
                $debuff_count++;
            }
        }
        
        if(DEBUG) {
            $output .= "Current number of buffs: " . $debuff_count . "<br>";
        }
        if($debuff_count < $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " buffs have been applied on " . $user->username . "<br>";
            $returned_value = false;
        }
    } 
        
    
    //Requirement: Debuff count below X
    if(array_search("max_debuffs", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_debuffs', $requirements);
        $key2 = $key + 1;
        
        $debuff_count = 0;
        for($i = 0; $i < count($user->status); $i++) {
            $value = (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
            if($value < 0) {
                $debuff_count++;
            }
        }
        
        if(DEBUG) {
            $output .= "Current number of buffs: " . $debuff_count . "<br>";
        }
        if($debuff_count > $requirements[$key2]) {
            $output .= "This skill can only be played if more than " . $requirements[$key2] . " buffs have been applied on " . $user->username . "<br>";
            $returned_value = false;
        }
    }     
    
    //Requirement: Debuff/Buff/Status X above Y
    if(array_search("min_status", $requirements) === false) {
        //Continue
    } else {
        $key = array_search('max_debuffs', $requirements);
        $key2 = $key + 1;
        $status_value = 0;
        
        for($i = 0; $i < count($user->status); $i++) {
            //preg_replace strips numbers and symbols from $requirements[$key2]
            //Given the format of this requirement will be ~identifier_string~requirement_stringX and X is an integer; we need to remove the integer so we can do a string match

            if(strpos($user->status[$i], preg_replace('/[0-9]+/', "", $requirements[$key2])) !== false) { 
                 //There is a match in the status array with our requirement; now check if the requirement is satisfied or not   
                $status_value += (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
            }
        }
        
        $requirement_value = (int) filter_var($requirements[$key2], FILTER_SANITIZE_NUMBER_INT);
        $string_name = str_replace("_", " ", ucfirst(preg_replace('/[0-9]+/', "", $requirements[$key2])));
        
        if($status_value < $requirement_value) { //Requirement not met
            $output .= "This skill can only be played if the user has more than " . $requirement_value . " " . $string_name . ". You have " . $status_value . "<br>";
            $returned_value = false;
        }
    }     
}

//This function is simply a simplification of the conditional check that can be applied to skill effects 
//The way this is handled is via a string in the database with the pattern: skill_effect:x~conditional~y where conditional and y are conditions which must be satisfied before effect x occurs
//Value is based off $effect[$keys[$i] + 1] where $effect is an array containing all the effects (from database in the format of effect:x), x is the element of the key [$keys[$i] + 1]
//This function also manipulates the value of a particular skill effect such that we grab a custom value for effect : x 

//Custom values include:
//User's loss in HP (useful for full heal)
//User's hand size (discard hand)

function skill_effect_conditional_check(&$value, $user, $target) {
    $conditional = true;
    if(!is_numeric($value) && strpos($value, '~')) {
        $key3 = explode("~", $value);
        $value = $key3[0];
        $conditional_array[0] = $key3[1];
        $conditional_array[1] = $key3[2];
        skill_conditional($conditional_array, $user, $empty, $conditional);
    }
    if(!is_numeric($value)) {
        if($value == "user_missing_hp") {
            $value = $user->maxhp - $user->hp;
        } elseif ($value == "user_hand_size") {
            $value = count($user->skill_hand);
        } else {
            $value = 0;
            echo "Error in skill database: Skill effect: X (X is not a number or a valid string)";
        }
    }
    if(!is_numeric($value)) {
        $conditional = false;
    }
    return $conditional;
}

//This function processes secondary effects of skill
function skill_effect($con, $skill_id, $player, $user, $target, &$output, &$message) {
    //Use skill_data to grab skill info
    $skill_data = skill_data($con);
    $query = mysqli_query($con, "SELECT * FROM skills WHERE id = '$skill_id' LIMIT 1");
    if(mysqli_num_rows($query) == 0 && $skill_id !== false) {
        //No valid skill exists
        return false;
    } else { //Skill exists
        
        if($skill_id !== false) {
        
        //Find information about skill_id['id'] in the master skill_data array (which is grabbed directly from the database)
        $array_search_key = array_search($skill_id, array_column($skill_data, 0)); 
        
        //Now we have the skill id; grab its info
        $skill_info =  $skill_data[$array_search_key];

            if($skill_info[9]) {
                $effect_A = explode(":", $skill_info[9]);
            } else {
                $effect_A = array();
            }
            if($skill_info[10]) {
                $effect_B = explode(":", $skill_info[10]);
            } else {
                $effect_B = array();
            }
            if($skill_info[11]) {
                $effect_C = explode(":", $skill_info[11]);
            } else {
                $effect_C = array();
            }
            
            $effect = array_merge($effect_A, $effect_B, $effect_C);

        } else {
            $effect = explode(":", $message);
            $message = NULL;
        }
            
        //Gain Block X
        if(array_search("gain_block", $effect) !== false) {
            $keys = array_keys($effect, 'gain_block');
            for($i = 0; $i < count($keys) && $effect[$keys[0]] === "gain_block"; $i++) {
                if(skill_effect_conditional_check($effect[$keys[$i] + 1], $user, $target)) {
                    if($message === NULL) {
                        block($effect[$keys[$i] + 1], $skill_info, $user, $player, $output, false);
                    }
                    
                    $block_amount = block($effect[$keys[$i] + 1], $skill_info, $user, $player, $output, true);
                    $text = ($effect[$keys[$i] + 1] > 0) ? "gain" : "lose";
                    
                    if($message !== NULL) {
                        $message .= ucfirst($text). " " . $block_amount . " block<br>";
                    }
                }
            }
        }
            
        //Multiply Block by X
        if(array_search("multiply_block", $effect) !== false) { 
            $keys = array_keys($effect, 'multiply_block');
            for($i = 0; $i < count($keys) && $effect[$keys[0]] === "multiply_block"; $i++) {
                if(skill_effect_conditional_check($effect[$keys[$i] + 1], $user, $target)) {
                    if($message === NULL) {
                        block(max(0, round($user->block * $effect[$keys[$i] + 1])), $skill_info, $user, $player, $output, false);  
                    }
                    
                    $block_amount = block($effect[$keys[$i] + 1], $skill_info, $user, $player, $output, true);
                    $text = ($effect[$keys[$i] + 1] > 0) ? "gain" : "lose";
                    
                    if($message !== NULL) {
                        $message .= ucfirst($text). " " . $block_amount . " block<br>";
                    }
                }
            }
        }
        
        shuffle_skills("shuffle_skills", "draw_pile", $skill_data, $effect, $player, $user, $target, $message, $output);
        shuffle_skills("discard_skills", "skill_discard", $skill_data, $effect, $player, $user, $target, $message, $output);
        shuffle_skills("exhaust_skills", "skill_exhaust", $skill_data, $effect, $player, $user, $target, $message, $output);
         
        //Gain X Energy
        if(false !== array_search("gain_energy", $effect)) {
            $keys = array_keys($effect, 'gain_energy');
            for($i = 0; $i < count($keys) && $effect[$keys[0]] === "gain_energy"; $i++) {
                if(skill_effect_conditional_check($effect[$keys[$i] + 1], $user, $target)) {
                    if ($message === NULL) { 
                        $user->battle_energy = min($user->battle_energy + $effect[$keys[$i] + 1], $user->max_energy);
                    }
                    
                    if($message === NULL) { 
                        $output .= ($user->username != $player->username) ? ENEMY_BUFF_FONT : PLAYER_BUFF_FONT;
                        $output .= $user->username . " regenerates " . $effect[$keys[$i] + 1] . " energy</span><br>";
                    } else {
                        $message .= "Gain " . $effect[$keys[$i] + 1] . " energy<br>";
                    }
                }
            }
        }
        
        //Draw X skills
        if(array_search("draw_skills", $effect) !== false) {
            $keys = array_keys($effect, 'draw_skills');
            for($i = 0; $i < count($keys) && $effect[$keys[0]] === "draw_skills"; $i++) {            
                if(skill_effect_conditional_check($effect[$keys[$i] + 1], $user, $target)) {
                    if($message === NULL) {
                        skill_draw($user,  $effect[$keys[$i] + 1], $output);
                    }

                    if($message === NULL) {
                        $output .= ($user->username != $player->username) ? ENEMY_BUFF_FONT : PLAYER_BUFF_FONT;
                        $output .= $user->username . " draws " . $effect[$keys[$i] + 1] . " skills</span><br>";
                    } else {
                        $message .= "Draw " . $effect[$keys[$i] + 1] . " skills<br>";
                    }
                }
            }
        }     
        
        //The following block uses the free_skill_effect() function to apply a buff ($unit->status) that allows a skill to be used for 0 energy
        //An example would be free_magical_attack:1 which gives the user 1 use of any magical attack for 0 energy
        //Adding this_turn to the skill_effect will cause this effect to expire at the end of the turn (eg. free_magical_attack_this_turn)
        
        free_skill_effect("free_physical_attack", $effect, $player, $user, $target, $message, $output);
        free_skill_effect("free_magical_attack", $effect, $player, $user, $target, $message, $output);
        free_skill_effect("free_attack", $effect, $player, $user, $target, $message, $output);
        free_skill_effect("free_defensive_skill", $effect, $player, $user, $target, $message, $output);
        free_skill_effect("free_non_attack", $effect, $player, $user, $target, $message, $output);
        free_skill_effect("free_skill", $effect, $player, $user, $target, $message, $output);
        
        free_skill_effect("free_physical_attack_this_turn", $effect, $player, $user, $target, $message, $output);
        free_skill_effect("free_magical_attack_this_turn", $effect, $player, $user, $target, $message, $output);
        free_skill_effect("free_attack_this_turn", $effect, $player, $user, $target, $message, $output);
        free_skill_effect("free_defensive_skill_this_turn", $effect, $player, $user, $target, $message, $output);
        free_skill_effect("free_non_attack_this_turn", $effect, $player, $user, $target, $message, $output);
        free_skill_effect("free_skill_this_turn", $effect, $player, $user, $target, $message, $output);
        
        //Gain HP X 
        if(array_search("gain_hp", $effect) !== false) {
            $keys = array_keys($effect, 'gain_hp');
            for($i = 0; $i < count($keys) && $effect[$keys[0]] === "gain_hp"; $i++) {
                if(skill_effect_conditional_check($effect[$keys[$i] + 1], $user, $target)) {
                    
                    $text = ($effect[$keys[$i] + 1] >= 0) ? "gain" : "lose";
                    
                    if($message === NULL) {
                        $user->hp = min($user->maxhp, $user->hp + $effect[$keys[$i] + 1]);
                        if($effect[$keys[$i] + 1] > 0) {
                            $output .= ($user->username != $player->username) ? ENEMY_BUFF_FONT : PLAYER_BUFF_FONT;
                        } else {
                            $output .= ($user->username != $player->username) ? ENEMY_MISS_FONT : PLAYER_MISS_FONT;
                        }
                        $output .= $user->username . " " . $text . "s " . abs($effect[$keys[$i] + 1]) . " health (Health: " . max(0, $user->hp) . ")</span><br>";
                    } else {
                        $message .= ucfirst($text) . " " . abs($effect[$keys[$i] + 1]) . "HP<br>"; 
                    }
                }
            }
        }        
        
        //Gain X extra Draw for Y turns (temporary_drawX : Y)
        $searchword = 'temporary_draw';
        $matches = array();
        foreach($effect as $k=>$v) {
            if(preg_match("/$searchword/i", $v)) {
                $matches[$v] = $k; //$k is the key which temporary_draw was found in within the $effect array; $v will contain temporary_draw and an integer which represents how many extra card will be drawn per turn
                if(skill_effect_conditional_check($effect[$k + 1], $user, $target)) {
                $draw_change = (int) filter_var($v, FILTER_SANITIZE_NUMBER_INT);
                
                //determine if there are any draw buffs/debuffs
                $pernament_card_draw_change = 0;
                for($i = 0; $i < count($user->status); $i++) {
                    if(strpos($user->status[$i], "pernament_draw") !== false) { 
                        $pernament_card_draw_change += (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
                    }
                }
                
                //Set draw_change to user's current draw if set to 0
                if($draw_change == 0) { $draw_change = -($user->battle_draw_per_turn + $pernament_card_draw_change); }
                
                if($message === NULL) {
                    array_push($user->status, "temporary_draw:" . $draw_change . ":" . $effect[$k + 1]); // ($draw_change is set by the integer next to temporary_draw and buff turns is set by $effect[$k + 1]
                }

                $optional_text2 = ($draw_change > 0) ? "more" : "less ";
                
                if($message === NULL) {
                    if($draw_change > 0) {
                        $output .= ($user->username != $player->username) ? ENEMY_BUFF_FONT : PLAYER_BUFF_FONT;
                    } else {
                        $output .= ($user->username != $player->username) ? ENEMY_MISS_FONT : PLAYER_MISS_FONT;
                    }
                    $output .= $user->username . " will now draw " . abs($draw_change) . " " . $optional_text2 . " skills for the next " . $effect[$k + 1] . " turn(s)!</span><br>";
                } else {
                    $message .= "Draw ". abs($draw_change) . " " . $optional_text2 . " skills for " . $effect[$k + 1] . " turn(s)<br>";    
                    }
                }
            }
        }
        
        //Draw X extra skills per turn 
        if(array_search("pernament_draw", $effect) !== false) {
            $keys = array_keys($effect, 'pernament_draw');
            for($i = 0; $i < count($keys) && $effect[$keys[0]] === "pernament_draw"; $i++) {
                if(skill_effect_conditional_check($effect[$keys[$i] + 1], $user, $target)) {
                    if($message === NULL) {
                        $effect_value = 0;
                        foreach ($user->status as $key => $status) {
                            if(strpos($status, "pernament_draw") !== false) { 
                                $effect_value += (int) filter_var($status, FILTER_SANITIZE_NUMBER_INT);
                                array_splice($user->status, $key, 1);
                            }
                        }
                        array_push($user->status, "pernament_draw:" . ($effect_value + $effect[$keys[$i] + 1]));                        
                    }
                    
                    $text = ($effect[$keys[$i] + 1] > 0) ? " more " : " less ";
                    
                    if($message === NULL) {
                        if($effect[$keys[$i] + 1] > 0) {
                            $output .= ($user->username != $player->username) ? ENEMY_BUFF_FONT : PLAYER_BUFF_FONT;
                        } else {
                            $output .= ($user->username != $player->username) ? ENEMY_MISS_FONT : PLAYER_MISS_FONT;
                        }
                        $output .= $user->username . " will draw " . $effect[$keys[$i] + 1] . $text . " skill per turn now (Draw per turn: " . ($user->battle_draw_per_turn + $effect_value + $effect[$keys[$i] + 1]) . ")</span><br>"; 
                    } else {
                        $message .= "Draw " . $effect[$keys[$i] + 1] . $text . " skill(s) per turn<br>";    
                    }
                }
            }
        }        

        //Gain effect: X after turn Y (does not work with the all variable)
        //Format is as follows: turn_effectZ-effect:x~condition~y where Z denotes the delay in turns which the effect will occur 
        $searchword = 'turn_effect';
        $matches = array();
        foreach($effect as $k=>$v) {
            if(preg_match("/$searchword/i", $v)) {
                $matches[$v] = $k; 
                if(skill_effect_conditional_check($effect[$k + 1], $user, $target)) {
                $turn_delay = (int) filter_var($v, FILTER_SANITIZE_NUMBER_INT);
                if($message === NULL) {
                    array_push($user->turn_effect, ($user->battle_turns + $turn_delay) . ":" . explode("-", $effect[$k])[1] . ":" . $effect[$k + 1]); 
                }
                
                if($message === NULL) {
                    if($effect[$k + 1] > 0) {
                        $output .= ($user->username != $player->username) ? ENEMY_BUFF_FONT : PLAYER_BUFF_FONT;
                    } else {
                        $output .= ($user->username != $player->username) ? ENEMY_MISS_FONT : PLAYER_MISS_FONT;
                    }
                    $output .= $user->username . " will undergo " . str_replace("_", " ", ucfirst(explode("-", $effect[$k])[1])) . " after " . $turn_delay . " turn(s)!</span><br>";
                } else {
                    $message .= str_replace("_", " ", ucfirst(explode("-", $effect[$k])[1])) . " at turn " . $turn_delay . "<br>";    
                    }
                }
            }
        }
        
        //Gain X turns of %Stat buff/debuff
        skill_effect_status("attack_shift", "fury", "weak", "gains", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("defense_shift", "protection", "vulnerable", "gains", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("power_shift", "vigour", "debilitation", "gains", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("fortitude_shift", "endurance", "frail", "gains", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("magic_shift", "focus", "attenuation", "gains", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("resistance_shift", "resilience", "feeble", "gains", $effect, $player, $user, $target, $message, $output);
        
        //Gain X stat
        skill_effect_status("attack_adjust", "extra strength", "less strength", "will until the end of battle have", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("defense_adjust", "extra defense", "less defense", "will until the end of battle have", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("power_adjust", "extra power", "less power", "will until the end of battle have", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("magic_adjust", "extra magic", "less magic", "will until the end of battle have", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("resistance_adjust", "extra resistance", "less resistance", "will until the end of battle have", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("fortitude_adjust", "extra fortitude", "less fortitude", "will until the end of battle have", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("element_fire", "extra fire power", "less fire power", "now has", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("element_lightning", "extra lightning power", "less lightning power", "now has", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("element_ice", "extra ice power", "less ice power", "now has", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("fire_element_energy_reduction", "reduced fire skill energy costs by", "increased fire skill energy costs by", "has now", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("lightning_element_energy_reduction", "reduced lightning skill energy costs by", "increased lightning skill energy costs by", "has now", $effect, $player, $user, $target, $message, $output);


        //Gain X stat temporarily (reduces by 1 every turn)
        skill_effect_status("attack_modify", "extra strength", "less strength", "will temporarily have", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("defense_modify", "extra defense", "less defense", "will temporarily have", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("power_modify", "extra power", "less power", "will temporarily have", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("magic_modify", "extra magic", "less magic", "will temporarily have", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("resistance_modify", "extra resistance", "less resistance", "will temporarily have", $effect, $player, $user, $target, $message, $output);
        skill_effect_status("fortitude_modify", "extra fortitude", "less fortitude", "will temporarily have", $effect, $player, $user, $target, $message, $output);
        
        //Select Z skills from X to Y (x_selectable_skills_y:z where x = exhaust and y = hand/deck/discard and z is quanity of skills)
        $search = array_filter($effect, function ($var) { return (stripos($var, "exhaust_selectable_skills") !== false); }); //Grabs an array ($search) which contains the elements that match thee string "exhaust_selectable_skills"
        skill_manipulation('exhaust_selectable_skills', $effect, $player, $user, $target, $message, $output, $search); //exhaust z skills
        $search = array_filter($effect, function ($var) { return (stripos($var, "discard_selectable_skills") !== false); });
        skill_manipulation('discard_selectable_skills', $effect, $player, $user, $target, $message, $output, $search); //discard z skills
        $search = array_filter($effect, function ($var) { return (stripos($var, "draw_selectable_skills") !== false); });
        skill_manipulation('draw_selectable_skills', $effect, $player, $user, $target, $message, $output, $search); //put z skills into hand
        $search = array_filter($effect, function ($var) { return (stripos($var, "shuffle_selectable_skills") !== false); });
        skill_manipulation('shuffle_selectable_skills', $effect, $player, $user, $target, $message, $output, $search); //shuffle z skills into the deck
        
        //Select up to Z skills from X to Y (x_selectable_skills_y:z where x = exhaust and y = hand/deck/discard and z is quanity of skills)
        $search = array_filter($effect, function ($var) { return (stripos($var, "exhaust_optional_skills") !== false); });
        skill_manipulation('exhaust_optional_skills', $effect, $player, $user, $target, $message, $output, $search); //exhaust z skills
        $search = array_filter($effect, function ($var) { return (stripos($var, "discard_optional_skills") !== false); });
        skill_manipulation('discard_optional_skills', $effect, $player, $user, $target, $message, $output, $search); //exhaust z skills
        $search = array_filter($effect, function ($var) { return (stripos($var, "draw_optional_skills") !== false); });
        skill_manipulation('draw_optional_skills', $effect, $player, $user, $target, $message, $output, $search); //exhaust z skills
        $search = array_filter($effect, function ($var) { return (stripos($var, "shuffle_optional_skills") !== false); });
        skill_manipulation('shuffle_optional_skills', $effect, $player, $user, $target, $message, $output, $search); //exhaust z skills        
    }
}

//This function adds skills that simply add a string to the $user->status array
//Currently used for adding buffs/debuffs as well as elemental buffs/debuffs
function skill_effect_status($skill_string, $string1, $string2, $string3, $effect, $player, &$user, $target, &$message, &$output) {
    if(array_search($skill_string, $effect) !== false) {    
        $keys = array_keys($effect, $skill_string);
        for($i = 0; $i < count($keys) && $effect[$keys[0]] === $skill_string; $i++) {
            if(skill_effect_conditional_check($effect[$keys[$i] + 1], $user, $target)) {
                if($message === NULL) {
                    $effect_value = 0;
                    foreach ($user->status as $key => $status) {
                        if(strpos($status, $skill_string) !== false) { 
                            $effect_value += (int) filter_var($status, FILTER_SANITIZE_NUMBER_INT);
                            array_splice($user->status, $key, 1);
                        }
                    }
                    array_push($user->status, $skill_string . ":" . ($effect_value + $effect[$keys[$i] + 1]));   
                }
                $text = ($effect[$keys[$i] + 1] > 0) ? $string1 : $string2;
                if($message === NULL) {
                    if($effect[$keys[$i] + 1] > 0) {
                        $output .= ($user->username != $player->username) ? ENEMY_BUFF_FONT : PLAYER_BUFF_FONT;
                    } else {
                        $output .= ($user->username != $player->username) ? ENEMY_MISS_FONT : PLAYER_MISS_FONT;
                    }
                    $output .= $user->username . " " . $string3 . " " . abs($effect[$keys[$i] + 1]) . " " . $text . "</span><br>"; 
                } else {
                    $message .= "Gain " . abs($effect[$keys[$i] + 1]) . " " . $text . "<br>";    
                }
            }
        }
    }
}

function shuffle_skills($action_string, $destination, $skill_data, $effect, $player, &$user, $target, &$message, &$output) { 
    //Moves X skills randomly from the hand to discard/draw pile/exhaust zone
    if(array_search($action_string, $effect) !== false) {
        $keys = array_keys($effect, $action_string);
        for($i = 0; $i < count($keys) && $effect[$keys[0]] === $action_string; $i++) {
            if(skill_effect_conditional_check($effect[$keys[$i] + 1], $user, $target)) {
                $discard = 0;
                
                if($message === NULL) {
                $output .= ($user->username != $player->username) ? ENEMY_MISS_FONT : PLAYER_MISS_FONT;
                }
                
                for($j = 0; $j < $effect[$keys[$i] + 1] && count($user->skill_hand) > 0 && $message === NULL; $j++) {
                    //Select a random skill from player's hand
                    $selected = rand(1, count($user->skill_hand)) - 1;    
                    
                    //Add selected skill to destination (eg. discard pile)
                    array_push($user->$destination, $user->skill_hand[$selected]); 
                    
                    //Grab discarded skill's name
                    $array_search_key = array_search($user->skill_hand[$selected], array_column($skill_data, 0)); 
                    $skill_info =  $skill_data[$array_search_key];
                    
                    //Print result
                    $output .= $skill_info[1];
                    if($j < $effect[$keys[$i] + 1] - 2 && count($user->skill_hand) > 2) {
                        $output .= ", ";
                    } elseif ($j < $effect[$keys[$i] + 1] - 2 && count($user->skill_hand) == 2) {
                        $output .= " and ";
                    } elseif ($j == $effect[$keys[$i] + 1] - 2 && count($user->skill_hand) > 1) {
                        $output .= " and ";
                    }
                    array_splice($user->skill_hand, $selected, 1);
                    $discard++;
                }
                if($message === NULL) {
                    if($discard == 0) {
                        $output .= "Nothing was " . str_replace("_skills", "", $action_string) . ((substr(str_replace("_skills", "", $action_string), -1) == "e") ? "" : "e") . "d from the hand of " . $user->username . "</span><br>";
                    } elseif ($discard == 1) {
                        $output .= " was ". str_replace("_skills", "", $action_string) . ((substr(str_replace("_skills", "", $action_string), -1) == "e") ? "" : "e") . "d from the hand of " . $user->username . "</span><br>";
                    } elseif ($discard > 1) {
                    $output .= " were " . str_replace("_skills", "", $action_string) . ((substr(str_replace("_skills", "", $action_string), -1) == "e") ? "" : "e") . "d from the hand of " . $user->username . "</span><br>";
                    }
                } else {
                    $message .= ucfirst(str_replace("_skills", "", $action_string)) . " " . $effect[$keys[$i] + 1] . " skills<br>";
                }
            }
        }
    }
}

//A function that adds a string to the unit->status array that allows for skills to be used for 0 energy
function free_skill_effect($skill_string, $effect, $player, &$user, $target, &$message, &$output) { 
    if(array_search($skill_string, $effect) !== false) {
        $keys = array_keys($effect, $skill_string);
        for($i = 0; $i < count($keys) && $effect[$keys[0]] === $skill_string; $i++) {
            if(skill_effect_conditional_check($effect[$keys[$i] + 1], $user, $target)) {
                for($j = 0; $j < $effect[$keys[$i] + 1] && $message === NULL; $j++) {
                    array_push($user->skill_effect, $skill_string);
                }
                if($message === NULL) {
                    $output .= ($user->username != $player->username) ? ENEMY_BUFF_FONT : PLAYER_BUFF_FONT;
                    $output .= "The next " . $effect[$keys[$i] + 1] . " " . (str_replace("_", " ", str_replace("free_", "", $effect[$keys[$i]]))) . "(s) will be free " . ((strpos($effect[$keys[$i]], "this_turn") !== false) ? "this turn" : "") ."!</span><br>";
                }
                else {
                    $message .= "Next " . $effect[$keys[$i] + 1] . " " . (str_replace("_", " ", str_replace("free_", "", $effect[$keys[$i]]))) . "(s) costs 0 energy " . ((strpos($effect[$keys[$i]], "this_turn") !== false) ? "this turn" : "") ."<br>";
                }
            }
        }
    }     
}

//A function that adds a string to the unit->status array that allows for skills to be selected and moved (eg. move a skill from the deck to the discard pile)
function skill_manipulation($skill_string, $effect, $player, &$user, $target, &$message, &$output, $search) {
    for($i = 0; $i < count($search); $i++) {
        $pointer = array_key_first($search);
        if(skill_effect_conditional_check($effect[$pointer + 1], $user, $target)) {
            if($message === NULL) {
                $effect_value = 0;
                foreach ($user->status as $key => $status) {
                    if(strpos($status, $effect[$pointer]) !== false) { 
                        $effect_value += (int) filter_var($status, FILTER_SANITIZE_NUMBER_INT);
                        array_splice($user->status, $key, 1);
                    }
                }
                
                $output .= ($user->username != $player->username) ? ENEMY_BUFF_FONT : PLAYER_BUFF_FONT;
                $output .= $user->username . " will select " . ((strpos($skill_string, "optional") !== false) ? " up to " : "") . abs($effect[$pointer + 1]) . " skill(s) from their " .  ((strpos(str_replace($skill_string . "_", "", $effect[$pointer]), "_") !== false) ? substr(str_replace($skill_string . "_", "", $effect[$pointer]), 0, strpos(str_replace($skill_string . "_", "", $effect[$pointer]), "_")) : str_replace($skill_string . "_", "", $effect[$pointer]))  ."</span><br>"; 
                array_push($user->status, $effect[$pointer] . ":" . ($effect_value + $effect[$pointer + 1]));   
                array_splice($effect, $pointer, 2);
            } else {
                $message .= "Pick " . ((strpos($skill_string, "optional") !== false) ? " up to " : "") . abs($effect[$pointer + 1]) . " skill(s) from " . ((strpos(str_replace($skill_string . "_", "", $effect[$pointer]), "_") !== false) ? substr(str_replace($skill_string . "_", "", $effect[$pointer]), 0, strpos(str_replace($skill_string . "_", "", $effect[$pointer]), "_")) : str_replace($skill_string . "_", "", $effect[$pointer])) . " to ". substr($skill_string , 0, strpos($skill_string , '_')) . "<br>";  
                array_splice($effect, $pointer, 2);
            }
        }
    }   
}

function skill_effect_energy(&$value, $user, $skill_info) {
   //Check if energy-modifying effect is in place
    $value = 1;
    
    $key = array_search("free_physical_attack", $user->skill_effect); 
    if($skill_info[5] == "physical_attack" && false !== $key) {
        $value = 0;
    }
    $key = array_search("free_magical_attack", $user->skill_effect); 
    if($skill_info[5] == "magical_attack" && false !== $key) {
        $value = 0;
    }
    $key = array_search("free_attack", $user->skill_effect); 
    if(($skill_info[5] == "physical_attack" || $skill_info[5] == "magical_attack" ) && false !== $key) {
        $value = 0;
    }    
    $key = array_search("free_defensive_skill", $user->skill_effect); 
    if($skill_info[5] == "defensive" && false !== $key) {
        $value = 0;
    }    
    $key = array_search("free_non_attack", $user->skill_effect); 
    if($skill_info[5] != "physical_attack" && $skill_info[5] != "magical_attack" && false !== $key) {
        $value = 0;
    }
    $key = array_search("free_skill", $user->skill_effect); 
    if(false !== $key) {
        $value = 0;
    }
    $key = array_search("free_physical_attack_this_turn", $user->skill_effect); 
    if($skill_info[5] == "physical_attack" && false !== $key) {
        $value = 0;
    }
    $key = array_search("free_magical_attack_this_turn", $user->skill_effect); 
    if($skill_info[5] == "magical_attack" && false !== $key) {
        $value = 0;
    }
    $key = array_search("free_attack_this_turn", $user->skill_effect); 
    if(($skill_info[5] == "physical_attack" || $skill_info[5] == "magical_attack" ) && false !== $key) {
        $value = 0;
    }    
    $key = array_search("free_defensive_skill_this_turn", $user->skill_effect); 
    if($skill_info[5] == "defensive" && false !== $key) {
        $value = 0;
    }    
    $key = array_search("free_non_attack_this_turn", $user->skill_effect); 
    if($skill_info[5] != "physical_attack" && $skill_info[5] != "magical_attack" && false !== $key) {
        $value = 0;
    }
    $key = array_search("free_skill_this_turn", $user->skill_effect); 
    if(false !== $key) {
        $value = 0;
    } 
    
    for($i = 0; $i < count($user->status); $i++) {
        if(strpos($user->status[$i], "element_energy_reduction") !== false) {
            if($skill_info[4] == str_replace("_element_energy_reduction", "", explode(":", $user->status[$i])[0])) {
                $value = ((int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT) == -1) ? 0 : max((($skill_info[6] - (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT)) / ($skill_info[6])), 0);
            }
        }
    }
}

function skill_effect_energy_reset($value, $skill_type, &$user) {

    //Reset skill_effect (if used)
    $key = array_search("free_physical_attack", $user->skill_effect);
    $key2 = array_search("free_magical_attack", $user->skill_effect);
    $key3 = array_search("free_attack", $user->skill_effect);
    $key4 = array_search("free_defensive_skill", $user->skill_effect);  
    $key5 = array_search("free_non_attack", $user->skill_effect);
    $key6 = array_search("free_skill", $user->skill_effect);  
    $key7 = array_search("free_physical_attack_this_turn", $user->skill_effect);
    $key8 = array_search("free_magical_attack_this_turn", $user->skill_effect);
    $key9 = array_search("free_attack_this_turn", $user->skill_effect);
    $key10 = array_search("free_defensive_skill_this_turn", $user->skill_effect);  
    $key11 = array_search("free_non_attack_this_turn", $user->skill_effect);
    $key12 = array_search("free_skill_this_turn", $user->skill_effect);       
    if($value == 0 && false !== $key && $skill_type == "physical_attack") {
        array_splice($user->skill_effect, $key, 1);
    } elseif($value == 0 && false !== $key2 && $skill_type == "magical_attack") {
        array_splice($user->skill_effect, $key2, 1);
    } elseif($value == 0 && false !== $key3 && ($skill_type == "physical_attack" || $skill_type == "magical_attack")) {
        array_splice($user->skill_effect, $key3, 1);
    } elseif($value == 0 && false !== $key4 && $skill_type == "defensive") {
        array_splice($user->skill_effect, $key4, 1);
    } elseif($value == 0 && false !== $key5 && $skill_type != "physical_attack" && $skill_type != "magical_attack") {
        array_splice($user->skill_effect, $key5, 1);
    } elseif($value == 0 && false !== $key6) {
        array_splice($user->skill_effect, $key6, 1);
    } elseif($value == 0 && false !== $key7 && $skill_type == "physical_attack") {
        array_splice($user->skill_effect, $key7, 1);
    } elseif($value == 0 && false !== $key8 && $skill_type == "magical_attack") {
        array_splice($user->skill_effect, $key8, 1);
    } elseif($value == 0 && false !== $key9 && ($skill_type == "physical_attack" || $skill_type == "magical_attack")) {
        array_splice($user->skill_effect, $key9, 1);
    } elseif($value == 0 && false !== $key10 && $skill_type == "defensive") {
        array_splice($user->skill_effect, $key10, 1);
    } elseif($value == 0 && false !== $key11 && $skill_type != "physical_attack" && $skill_type != "magical_attack") {
        array_splice($user->skill_effect, $key11, 1);
    } elseif($value == 0 && false !== $key12) {
        array_splice($user->skill_effect, $key12, 1);
    }  
}

//This function determines an action based on skill_id
//The Ampersand in front of output is used to denote that the output variable is to be modified (vs. passing the value of output into the function and then losing it once the function is used)
function skill($con, $skill_data, $skill_id, $player, &$unit, &$target, &$output) {
    //$skill_data is a query containing all skill data (grabbed with a mysqli query and fed into this function)
    //$skill_id is the unit's selected skill (from their hand)
    
    //Generate random number generator for missing
    $misschance = intval(rand(1, 100)); 
    
    //Grab first skill from unit's hand and check it's type
    //Generate a query to check if the skill exists from the database
    $query = mysqli_query($con, "SELECT * FROM skills WHERE id = '$skill_id' LIMIT 1");
    if(mysqli_num_rows($query) == 0) {
        //Error
    }
    
    else { //Skill exists; grab its id from array_data (our temporary variable containing multi-dimensional array of skill data)
        
        $array_search_key = array_search($skill_id, array_column($skill_data, 0)); //Find information about skill_id['id'] in the master skill_data array (which is grabbed directly from the database)
        //Now we have the skill id; grab its info
        $skill_info =  $skill_data[$array_search_key];
        
        $energy_multiplier = 1;
        skill_effect_energy($energy_multiplier, $player, $skill_info);
        
        //Check if skill can be used (energy)
        if($skill_info[6] * $energy_multiplier > $unit->battle_energy) {
            //No energy 
            $output .= "No energy to use " . $skill_info[1] . "! User energy: " . $unit->battle_energy . " | Energy required: " . $skill_info[6] . " <br>";
        } elseif (!skill_requirement_check($con, $skill_id, $skill_data, $unit, $output)) {
            //Failed skill requirement check
        }
        else {
        
            //Add skill to discard pile if non-exhausting
            if($skill_info[15] == 0) {
                array_push($unit->skill_discard, $skill_id); 
            } 
            else { //Add skill to exhaust pile
                array_push($unit->skill_exhaust, $skill_id);
            }
            
            //Remove skill from hand 
            //Place selected skill to the front of the hand and remove it
            $search_key = array_search($skill_id, $unit->skill_hand);
            $temp = $unit->skill_hand[0];
            $unit->skill_hand[0] = $skill_id;
            $unit->skill_hand[$search_key] = $temp;
            array_splice($unit->skill_hand, 0, 1);
            
            //consume energy
            $unit->battle_energy -= $skill_info[6] * $energy_multiplier;
            
            //Reset skill_effect (if used)
            skill_effect_energy_reset($energy_multiplier, $skill_info[5], $unit);        
            
            //add to skill_played array
            $unit->skills_played[$unit->battle_turns] .= $skill_id . ":" . $skill_info[5] . ",";
            
            //Run a switch/case loop to determine actions
            //This is where a lot of stuff is hardcoded (such as a specific effect)
            
            switch($skill_info[5]) { //$skill_info[5] represents skill type
                case 'physical_attack':
                    //Accuracy check
                    if($misschance <= $unit->miss) {
                        //Action failed
                        $output .= ($unit->username != $player->username) ? ENEMY_MISS_FONT : PLAYER_MISS_FONT;
                        $output .= $unit->username . " tried to attack " . $target->username . " but missed!</span><br>";
                    }
                    else {
                        damage($output, $skill_info, $player, $unit, $target, 'physical');
                    }
                    break;
                case 'magical_attack': //This should be the same as physical attack except using different variables
    
                    //Accuracy check
                    if($misschance <= $unit->miss) {
                        //Action failed
                        $output .= ($unit->username != $player->username) ? ENEMY_MISS_FONT : PLAYER_MISS_FONT;
                        $output .= $unit->username . " unleashed a magical spell at " . $target->username . " but missed!</span><br>";
                    }
                    else {
                        damage($output, $skill_info, $player, $unit, $target, 'magical');
                    }
                    break;
                case 'defensive':
                    $output .= $unit->username . " " . $skill_info[3] . ": ";
                    break;
                    //Value A ($skill_info[7]) = Block amount
                    //block($skill_info[7], $skill_info, $unit, $player, $output, false);
                    //break;
                case 'buff':
                    $output .= $unit->username . " " . $skill_info[3] . ": ";
                    break;
                case 'debuff':
                    $output .= $unit->username . " " . $skill_info[3] . ": ";
                    break;
            }
            
            //Run secondary effects
            $message = NULL;
            skill_effect($con, $skill_id, $player, $unit, $target, $output, $message); 
            
        }
    }
}

//This function handles  custom damage formulas where the base skill damage is variable (eg. attack power = hand size)
//Potential custom damage formulas include:
//Hand/discard/exhaust pile size
//Skills played this turn/battle
//Block amount
//Battle turns
function damage_extra_effects(&$skill_info, &$user_damage_modifier, &$target_damage_modifier, $user, $target, &$output) { 
      
    //Add buffs/debuffs
    $net_atk = $user->atk;
    $net_mag = $user->mag;
    $net_def = $target->def;
    $net_res = $target->res;
    
    for($i = 0; $i < count($user->status); $i++) {
        if(strpos($user->status[$i], "attack_adjust") !== false) {
            $net_atk += (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
        }
        if(strpos($user->status[$i], "attack_modify") !== false) {
            $net_atk += (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
        }        
        if(strpos($user->status[$i], "magic_adjust") !== false) {
            $net_mag += (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
        }
        if(strpos($user->status[$i], "magic_modify") !== false) {
            $net_mag += (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
        }        
        if(strpos($user->status[$i], "power_adjust") !== false) {
            $net_atk += (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
            $net_mag += (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
        }
        if(strpos($user->status[$i], "power_modify") !== false) {
            $net_atk += (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
            $net_mag += (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
        }        
        if(strpos($user->status[$i], "attack_shift") !== false) {
            $net_atk += ((int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT) > 0) ? $net_atk * 0.5 : $net_atk * -0.25;
        }
        if(strpos($user->status[$i], "magic_shift") !== false) {
            $net_mag += ((int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT) > 0) ? $net_mag * 0.5 : $net_mag * -0.25;
        }        
        if(strpos($user->status[$i], "power_shift") !== false) {
            $net_atk += ((int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT) > 0) ? $net_atk * 1 : $net_atk * -0.5;
            $net_mag += ((int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT) > 0) ? $net_mag * 1 : $net_mag * -0.5;            
        }
    }
    
    for($i = 0; $i < count($target->status); $i++) {
        if(strpos($target->status[$i], "defense_adjust") !== false) {
            $net_def += (int) filter_var($target->status[$i], FILTER_SANITIZE_NUMBER_INT);
        }
        if(strpos($target->status[$i], "resistance_adjust") !== false) {
            $net_res += (int) filter_var($target->status[$i], FILTER_SANITIZE_NUMBER_INT);
        }   
        if(strpos($target->status[$i], "defense_modify") !== false) {
            $net_def += (int) filter_var($target->status[$i], FILTER_SANITIZE_NUMBER_INT);
        }
        if(strpos($target->status[$i], "resistance_modify") !== false) {
            $net_res += (int) filter_var($target->status[$i], FILTER_SANITIZE_NUMBER_INT);
        }           
        if(strpos($target->status[$i], "defense_shift") !== false) {
            $net_def += ((int) filter_var($target->status[$i], FILTER_SANITIZE_NUMBER_INT) > 0) ? $net_def * 0.5 : $net_def * -0.25;
        }
        if(strpos($target->status[$i], "resistance_shift") !== false) {
            $net_res += ((int) filter_var($target->status[$i], FILTER_SANITIZE_NUMBER_INT) > 0) ? $net_res * 0.5 : $net_res * -0.25;
        }        
    }
    
    if(!is_numeric($skill_info[7])) { //Apply custom damage formulas
        $exploded_string = explode(":", $skill_info[7]);
        $skill_info[7] = 0;
        
        //Counting
        $attacks_played = $physical_attacks_played = $magical_attacks_played = $defensive_played = $buffs_played = $debuffs_played = $skills_played = 0;
        for($i = 1; $i < $user->battle_turns; $i++) {
            $attacks_played += substr_count($user->skills_played[$i], "attack");
            $physical_attacks_played += substr_count($user->skills_played[$i], "physical_attack");
            $magical_attacks_played += substr_count($user->skills_played[$i], "magical_attack");
            $defensive_played += substr_count($user->skills_played[$i], "defensive");
            $buffs_played += substr_count($user->skills_played[$i], "buff");
            $debuffs_played += substr_count($user->skills_played[$i], "debuff");
            $skills_played = substr_count($user->skills_played[$i], ":");
        }
        
        for($i = 0; $i < count($exploded_string); $i += 2) {
            $skill_info[7] += (strpos($exploded_string[$i], "base_damage") !== false) ? $exploded_string[$i + 1] * 1 : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "hand_size") !== false) ? $exploded_string[$i + 1] * count($user->skill_hand) : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "discard_size") !== false) ? $exploded_string[$i + 1] * count($user->skill_discard) : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "exhaust_size") !== false) ? $exploded_string[$i + 1] * count($user->skill_exhaust) : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "deck_size") !== false) ? $exploded_string[$i + 1] * count($user->draw_pile) : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "attacks_played_this_turn") !== false) ? $exploded_string[$i + 1] * substr_count($user->skills_played[$user->battle_turns], "attack") : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "attacks_played_this_battle") !== false) ? $exploded_string[$i + 1] * $attacks_played : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "physical_attacks_played_this_turn") !== false) ? $exploded_string[$i + 1] * substr_count($user->skills_played[$user->battle_turns], "physical_attack") : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "physical_attacks_played_this_battle") !== false) ? $exploded_string[$i + 1] * $physical_attacks_played : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "magical_attacks_played_this_turn") !== false) ? $exploded_string[$i + 1] * substr_count($user->skills_played[$user->battle_turns], "magical_attack") : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "magical_attacks_played_this_battle") !== false) ? $exploded_string[$i + 1] * $magical_attacks_played : 0;     
            $skill_info[7] += (strpos($exploded_string[$i], "defensive_played_this_turn") !== false) ? $exploded_string[$i + 1] * substr_count($user->skills_played[$user->battle_turns], "defensive") : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "defensive_played_this_battle") !== false) ? $exploded_string[$i + 1] * $defensive_played : 0;    
            $skill_info[7] += (strpos($exploded_string[$i], "positive_status_played_this_turn") !== false) ? $exploded_string[$i + 1] * substr_count($user->skills_played[$user->battle_turns], "buff") : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "positive_status_played_this_battle") !== false) ? $exploded_string[$i + 1] * $buffs_played : 0;     
            $skill_info[7] += (strpos($exploded_string[$i], "negative_status_played_this_turn") !== false) ? $exploded_string[$i + 1] * substr_count($user->skills_played[$user->battle_turns], "debuff") : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "negative_status_played_this_turn") !== false) ? $exploded_string[$i + 1] * $debuffs_played : 0;     
            $skill_info[7] += (strpos($exploded_string[$i], "all_skills_played_this_turn") !== false) ? $exploded_string[$i + 1] * count($user->skills_played[$user->battle_turns]) : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "all_skills_played_this_battle") !== false) ? $exploded_string[$i + 1] * $skills_played : 0;     
            $skill_info[7] += (strpos($exploded_string[$i], "current_block") !== false) ? $exploded_string[$i + 1] * $user->block : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "battle_turns") !== false) ? $exploded_string[$i + 1] * count($user->battle_turns) : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "current_hp") !== false) ? $exploded_string[$i + 1] * $user->hp : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "remaining_hp") !== false) ? $exploded_string[$i + 1] * ($user->maxhp - $user->hp) : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "percentage_hp") !== false) ? round($exploded_string[$i + 1] * $user->hp / $user->maxhp) : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "unblocked_damage_taken") !== false && isset($user->damage_taken['unblocked'])) ? round($exploded_string[$i + 1] * $user->damage_taken['unblocked']) : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "total_damage_taken") !== false && isset($user->damage_taken['unblocked']) && isset($user->damage_taken['blocked'])) ? round($exploded_string[$i + 1] * ($user->damage_taken['blocked'] + $user->damage_taken['unblocked'])) : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "damage_taken_frequency") !== false && isset($user->damage_taken['damage_frequency'])) ? round($exploded_string[$i + 1] * $user->damage_taken['damage_frequency']) : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "unblocked_damage_given") !== false && isset($user->damage_given['unblocked'])) ? round($exploded_string[$i + 1] * $user->damage_given['unblocked']) : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "total_damage_given") !== false && isset($user->damage_given['unblocked']) && isset($user->damage_given['blocked'])) ? round($exploded_string[$i + 1] * ($user->damage_given['blocked'] + $user->damage_given['unblocked'])) : 0;
            $skill_info[7] += (strpos($exploded_string[$i], "damage_given_frequency") !== false && isset($user->damage_given['damage_frequency'])) ? round($exploded_string[$i + 1] * $user->damage_given['damage_frequency']) : 0;
            
        }
    }    
  
    if($skill_info[5] == "physical_attack") {
        $user_damage_modifier = $net_atk - $user->atk; //This returns the damage modification that occurs due to shift/adjust status effects
        $target_damage_modifier = $net_def - $target->def;
    } elseif ($skill_info[5] == "magical_attack") {
        $user_damage_modifier = $net_mag - $user->mag;
        $target_damage_modifier = $net_res - $target->res;
    }
    if(!is_numeric($skill_info[7])) {
        $skill_info[7] = 0;
    }
}

function block($block_value, $skill_info, $user, $player, &$output, $message) {
    //This function increases the user's block
    //If message is set to true, then no block is gained and instead the amount blocked is returned
    
    $fort_change = 0;
    $block_multiplier = 1;
    for($i = 0; $i < count($user->status); $i++) {
        if(strpos($user->status[$i], "fortitude_adjust") !== false) {
            $fort_change += (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
        }
        if(strpos($user->status[$i], "fortitude_modify") !== false) {
            $fort_change += (int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT);
        }                
        if(strpos($user->status[$i], "fortitude_shift") !== false) {
            $block_multiplier = ((int) filter_var($user->status[$i], FILTER_SANITIZE_NUMBER_INT) > 0) ? 1.5 : 0.75;
        }
    }
    
    if(!$message) {
        $block_gain = max(0, $block_multiplier * ($block_value + $user->fort + $fort_change));
        $user->block += $block_gain;
        $output .= ($user->username != $player->username) ? ENEMY_BUFF_FONT : PLAYER_BUFF_FONT;
        $output .= $user->username . " gains " . $block_gain . " block (Block : " . $user->block . ")</span><br>";
    } 
    else {
        return max(0, $block_multiplier * ($block_value + $user->fort + $fort_change));
    }
}

function damage(&$output, $skill_info, $player, $unit, $target, $type) {
    
    //Run some random variables
    $critchance = intval(rand(1, 100)); 
    if($skill_info[16] == -1) { $critchance = 99999999999; } //Skills with -1 crit rate will never crit
    $random     = intval(rand(1, 100));
    $divisor    = 10 * ($skill_info[17] <= 0 ? 10 : $skill_info[17]); //Given if $random is between 1 and 100 and $divisor is 30; then high chance the outcome will be between 0 and 2 with a tiny chance of 3.
    
    //Some limitations on values
    $min_dmg = 0;
    
    //Damage modifier variable (changes based on buffs)
    $damage_modifier = 0;
    
    //Process ignore user / target's stats (eg. ignore target's defense)
    $user_multiplier = 1;
    $target_multiplier = 1;
    if(!is_numeric($skill_info[7])) { //Apply custom damage formulas
        $exploded_string = explode(":", $skill_info[7]);
        for($i = 0; $i < count($exploded_string); $i += 2) {
            $user_multiplier = (strpos($exploded_string[$i], "user_attack_multiplier") !== false && $type == 'physical') ? $exploded_string[$i + 1] : 0;
            $user_multiplier = (strpos($exploded_string[$i], "user_magic_multiplier") !== false && $type == 'magical') ? $exploded_string[$i + 1] : 0;
            $target_multiplier = (strpos($exploded_string[$i], "target_defense_multiplier") !== false && $type == 'physical') ? $exploded_string[$i + 1] : 0;
            $target_multiplier = (strpos($exploded_string[$i], "target_resistance_multiplier") !== false && $type == 'magical') ? $exploded_string[$i + 1] : 0;            
        }
    }
    
    //Processes weird battle formulas (eg. damage proportional to hand size) and buffs/debuffs/status effects
    damage_extra_effects($skill_info, $user_damage_modifier, $target_damage_modifier, $unit, $target, $output);
    
    $unit->dmg_phys = max($min_dmg, floor((($unit->atk + $unit->pow + $user_damage_modifier) * $user_multiplier + $skill_info[7] - ($target->def + $target_damage_modifier) * $target_multiplier) * ($skill_info[8] + 100) / 100));
    $unit->dmg_mag = max($min_dmg, floor((($unit->mag + $unit->pow + $user_damage_modifier) * $user_multiplier + $skill_info[7] - ($target->res + $target_damage_modifier) * $target_multiplier) * ($skill_info[8] + 100) / 100));
  
if ($type == 'physical') { $dmg = $unit->dmg_phys; }
    elseif ($type == 'magical') { $dmg = $unit->dmg_mag; }
    else { $dmg = 0; }
        //Calculate damage done (critical)
        $raw_damage = ($critchance - $skill_info[16] <= $unit->critmax) ? round($dmg * 1.5 + floor($random / $divisor) + pow($unit->level, 1.2)) : $damage = $dmg + floor($random/$divisor);
        $damage = max(0, $raw_damage - $target->block);
        
        //Battle statistics
        $unit->damage_given['total'] = isset($unit->damage_given['total']) ?  $unit->damage_given['total'] + $raw_damage : $raw_damage;
        $unit->damage_given[$type] = isset($unit->damage_given[$type]) ?  $unit->damage_given[$type] + $raw_damage : $raw_damage;
        $unit->damage_given['blocked'] = isset($unit->damage_given['blocked']) ?  $unit->damage_given['blocked'] - ($damage - $raw_damage) : - ($damage - $raw_damage);
        $unit->damage_given['unblocked'] = isset($unit->damage_given['unblocked']) ?  $unit->damage_given['unblocked'] + $damage : $damage;
        $unit->damage_given['damage_frequency'] = isset($unit->damage_given['damage_frequency']) ?  $unit->damage_given['damage_frequency'] + 1 : 1;
        $target->damage_taken['total'] = isset($target->damage_taken['total']) ?  $target->damage_taken['total'] + $raw_damage : $raw_damage;
        $target->damage_taken[$type] = isset($target->damage_taken[$type]) ?  $target->damage_taken[$type] + $raw_damage : $raw_damage;
        $target->damage_taken['blocked'] = isset($target->damage_taken['blocked']) ?  $target->damage_taken['blocked'] - ($damage - $raw_damage) : - ($damage - $raw_damage);
        $target->damage_taken['unblocked'] = isset($target->damage_taken['unblocked']) ?  $target->damage_taken['unblocked'] + $damage : $damage;        
        $target->damage_taken['damage_frequency'] = isset($target->damage_taken['damage_frequency']) ?  $target->damage_taken['damage_frequency'] + 1 : 1;        
        
        //Process damage 
        $target->hp -= max(0, $damage);
        $target->block = max(0, $target->block - $raw_damage);
        if($damage == 0 && $target->block >= 0) { //Damage is fully blocked
            //Battle statistics
            $unit->damage_given['full_blocked_frequency'] = isset($target->damage_given['full_blocked_frequency']) ?  $target->damage_given['full_blocked_frequency'] + 1 : 1;
            $target->damage_taken['full_blocked_frequency'] = isset($target->damage_taken['full_blocked_frequency']) ?  $target->damage_taken['full_blocked_frequency'] + 1 : 1;
            
            //Battle message
            $output .= ($unit->username != $player->username) ? ENEMY_BLOCKED_FONT : PLAYER_BLOCKED_FONT;
            $output .= $unit->username . " attempts to attack " . $target->username . " but the attack is blocked. (Block: " . $target->block . ")</span><br>";
        }
    elseif ($damage > 0) {
        if($critchance - $skill_info[16] <= $unit->critmax) {
            //Critical hit message
            $output .= "<b><span id=\"gradtext\">Critical hit! </b></span>";
            
            //Battle statistics
            $unit->damage_given['critical_damage'] = isset($unit->damage_given['critical_damage']) ?  $unit->damage_given['critical_damage'] + $raw_damage : $raw_damage;
            $unit->damage_given['critical_frequency'] = isset($unit->damage_given['critical_frequency']) ?  $unit->damage_given['critical_frequency'] + 1 : 1;            
            $target->damage_taken['critical_damage'] = isset($target->damage_taken['critical_damage']) ?  $target->damage_taken['critical_damage'] + $raw_damage : $raw_damage;
            $target->damage_taken['critical_frequency'] = isset($target->damage_taken['critical_frequency']) ?  $target->damage_taken['critical_frequency'] + 1 : 1;          
        }
        $output .= ($unit->username != $player->username) ? ENEMY_DEFAULT_FONT : PLAYER_DEFAULT_FONT;
        $output .= $unit->username . " " . $skill_info[3] . " " . $target->username . " for " . $damage . " damage! ";
        $output .= ($target->hp > 0) ? "(" . $target->hp . " HP left)</span><br>" : DEFAULT_DEAD_FONT;   
    }
}

function skill_select($con, &$unit, $skill_data, $algorithm, &$output) {
    $roulette = array(); //This is used to weight selection of skill based on priority
    
    switch ($algorithm) {
        case 'priority': //Picks skills based on priority of the skill

            $roulette = array();
            for($k=0; $k < count($unit->skill_hand); $k++) {
                $array_search_key = array_search($unit->skill_hand[$k], array_column($skill_data, 0));
                $skill_info =  $skill_data[$array_search_key]; //Grabs data of skill based on id
                if($skill_info[6] <= $unit->battle_energy && skill_requirement_check($con, $unit->skill_hand[$k], $skill_data, $unit, $empty)) { //User has enough energy to use skill and meets skill requirement criteria
                    for($j=0; $j <= $skill_info[18]; $j++) {
                        array_push($roulette, $unit->skill_hand[$k]);
                    }
                }
            }
            
            shuffle($roulette); //Pick a random skill from this built up array based on priority
            
            //Rearrange hand based on results of algorithm
            if(count($roulette) > 0) {
                
                if(DEBUG) {
                    $output .= $unit->username . " " . $unit->battle_ai . " skill selection algorithm: <font color =\"pink\">";
                        for($i = 0; $i < count($roulette); $i++) {
                            $array_search_key = array_search($roulette[$i], array_column($skill_data, 0));
                            $skill_info =  $skill_data[$array_search_key]; //Grabs data of skill based on id
                            $output .= $skill_info[1];
                            if($i < count($roulette) - 1) { $output .= " | "; }
                        }
                    $output .= "</font><br>";
                }
                
                $selected_skill = array_search($roulette[0], $unit->skill_hand, 0); //Find the key to the hand that matches the selected skill
                $temp = $unit->skill_hand[0];
                $unit->skill_hand[0] = $roulette[0]; //Move selected skill to the front
                $unit->skill_hand[$selected_skill] = $temp;
            }
            
            break;
        case 'energy': //Sorts skills in one's hand based on energy
        
            $roulette = array();
            
            //Convert the unit's hand of skills array (index => skill_id) to include skill energy requirement (skill_id => energy)
            for($k=0; $k < count($unit->skill_hand); $k++) {
                
                //Find the skill_id amongst skill_data array (which contains everything including skill energy requiement)
                $array_search_key = array_search($unit->skill_hand[$k], array_column($skill_data, 0));
                $skill_info =  $skill_data[$array_search_key]; //Grabs data of skill based on id
                
                $roulette[$unit->skill_hand[$k]] = $skill_info[6];

            }
            
            //Sort $roulette based on it's value (in this case; energy) and reset the internal pointer to the start of the array
            asort($roulette);
            reset($roulette);
            
            //Sort unit's hand of skills based on the valeues of $roulette
            for($l = 0; $l < count($roulette); $l++) {
                $unit->skill_hand[$l] = key($roulette);
                next($roulette);
            }
            
            if(DEBUG) {
                $output .= $unit->username . " " . $unit->battle_ai . " skill selection algorithm: <font color =\"pink\">";
                    for($i = 0; $i < count($unit->skill_hand); $i++) {
                        $array_search_key = array_search($unit->skill_hand[$i], array_column($skill_data, 0));
                        $skill_info =  $skill_data[$array_search_key]; //Grabs data of skill based on id
                        $output .= $skill_info[1];                        
                        if($i < count($unit->skill_hand) - 1) { $output .= " | "; }
                    }
                $output .= "</font><br>";
            }
            
            break;
        default:
            shuffle($unit->skill_hand);
            
            if(DEBUG) {
                $output .= $unit->username . " " . $unit->battle_ai . " skill selection algorithm: <font color =\"pink\">";
                    for($i = 0; $i < count($unit->skill_hand); $i++) {
                        $array_search_key = array_search($unit->skill_hand[$i], array_column($skill_data, 0));
                        $skill_info =  $skill_data[$array_search_key]; //Grabs data of skill based on id
                        $output .= $skill_info[1];     
                        if($i < count($unit->skill_hand) - 1) { $output .= " | "; }
                    }
                $output .= "</font><br>";
            }
            break; //First skill in hand is the skill used (randomly)
    }
}    

