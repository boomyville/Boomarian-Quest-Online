<?php
/****************************************/
/*            BoomyRPG script           */
/*           Written by Boomy           */
/* Installation of tables into database */
/****************************************/

include("config.php"); //Includes connection to the database

echo "<b>Installation of databases via MySQL </b><br>";

//Create a table to maintain variables in regards to maintenance mode 
//Status variable indicates if the game is in maintenance mode or not
//By default this value is 0 which means the game is in maintenance mode
//In maintainance mode, no actions can occur in the game and login and
//regirstation is disabled. Set to 0 to enable the game

$iv = bin2hex(openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc')));

$sql="
CREATE TABLE `maintenance` (
  `status` tinyint(1) default 1,
  `iv` varchar(255) 
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Maintenance table created (for debugging purposes)";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//check if maintenance row doesn't already exist
$query = mysqli_query($con, "SELECT * FROM maintenance");
if(!mysqli_num_rows($query) == false) {
 echo "<br>Error creating default values for table as values already exist";
}
else {
$sql="
INSERT INTO `maintenance` (`status`, `iv`)
VALUES ('0', '$iv');
";

if ($con->query($sql) === TRUE) {
    echo "<br>Maintenance default values added";
} else {
    echo "<br>Error creating default values for table: " . $con->error;
}
}

//Table that stores password keys (based off user's verification keys)
//pkey is the key that is used to issue a new password
//validity is a time which pkey is valid too. Compared against time()
//username is the username of the player whom is attempting password reset
//used_status indicates if the password reset has been used or not; 0 indicating unused

$sql="CREATE TABLE `password_reset` (
  `password_key` varchar(255) NOT NULL,
  `used_status` int(11) NOT NULL default 0,  
  `validity` int(11) NOT NULL,
  `username` varchar(255) NOT NULL

);";

if ($con->query($sql) === TRUE) {
    echo "<br>Password reset table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}


//Create a table to store information regarding consumables
//Consumables are items that players can pick up and use to heal or modify player's stats
//Id is the internal id of the consumable (used to identify the item)
//Name is the name shown to the player
//Description is the flavour text describing the item such as its effects
//Price is the cost in gold to purchase this item by default
//Type indicates the type of consumable the item is; by default it is a potion that is used but can also be a key item
//Effect is a numerical value that indicates the numerical effect of the consumable (eg. How much health is healed)
//Level_req is the level the player is required to be to use this item
//Health_req is the minimum percentage health (of maximum health) before the item can be consumed. Max is 100
//Cooldown is how long (in seconds) before another item of the same kind can be used. This value is used in conjunction with the inventory_items table

$sql="
CREATE TABLE `consumables` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) collate latin1_general_ci NOT NULL,
  `description` text collate latin1_general_ci NOT NULL,
  `price` int(11) NOT NULL,
  `type` varchar(255) collate latin1_general_ci default 'potion',
  `effect` int(11) default 0,
  `level_req` int(11) default 0,
  `health_req` int(11) default 0,
  `cooldown` int(11) default 0,
  PRIMARY KEY  (`id`)
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Consumables table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//Create a table to store information regarding skills
//Skills are applicable to both player and monsters
//Skill ID represent a unique skills and ID starts at 10000 
//Upgraded skills will be represented by an increase in ID by 1 (eg 10001)
//Difference skills are represented in changes by 10s (eg 10020 and 10030 will be different skills)
//Type of skill represents if the skill is physical offensive, magical offensive, defensive, self-buff, enemy-debuff or other
//Energy_cost represents how much energy the skill consumes. Default is 3
//Energy_use is how much energy a skill uses. Default is 3
//Value_A and Value_B are used primarily for damage calculations. Value_A represents base damage generally
//, Effect_B and Effect_C are effects that occur when the skill is executed. It is a string followed by a dash and a number. For example: Draw-3 or Drain-0.5 or Block-7
//Requirement A and Requirement B are conditions that have to be fulfilled before a skill can be used. Follows same system as effect; examples include: Hand_size-1 or Attacks_played-0 (no attacks played this turn)
//End_turn is a boolean value that determines if the skill ends the user's turn or not
//Exhaust is a probability a skill is removed from the battle when used. If set to 0, then the skill is placed into the discard pile when used
//Cost is the value in gold that the skill is worth
//Priority is a integer number on the importance of the skill relative to other skills. The higher the number, the more likely the AI will use it. High priority skills are considered to be more 'top tier' in general

$sql="
CREATE TABLE `skills` (
  `id` int(11) NOT NULL,
  `name` varchar(255) collate latin1_general_ci NOT NULL,
  `description` text collate latin1_general_ci NOT NULL,
  `game_text` text collate latin1_general_ci NOT NULL,
  `element` varchar(255) default 'none',
  `type` enum('physical_attack','magical_attack','defensive','buff','debuff', 'other') collate latin1_general_ci default 'other',
  `energy_use` int(11) default 0,
  `value_A` varchar(255),
  `value_B` varchar(255) default 0,
  `effect_A` varchar(255),
  `effect_B` varchar(255),
  `effect_C` varchar(255),  
  `requirement_A` varchar(255),
  `requirement_B` varchar(255),  
  `end_turn` tinyint(1) default 0,  
  `exhaust` tinyint(1) default 0,  
  `crit` int(11) default 0,
  `variance` int(11) default 3,
  `priority` int(11) default 0,
  `cost` int(11) default 0
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Skills table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//Insert default skills into the table
$query = mysqli_query($con, "SELECT * FROM skills");
if (!$query) {
    echo "<br>Error, no skill database present";
} elseif (!mysqli_num_rows($query) == false) {
 echo "<br>Error adding skills as they already exist";
}
else {
$sql="
INSERT INTO `skills` (`id`, `name`, `description`, `game_text`, `element`, `type`, `energy_use`, `value_A`, `value_B`, `effect_A`, `effect_B`, `effect_C`, `requirement_A`, `requirement_B`, `end_turn`, `exhaust`, `crit`, `variance`, `priority`, `cost`) VALUES
(10000,	'Strike',	'Basic physical attack that does damage based on the user\'s attack power',	'strikes',	0,	'physical_attack',	3,	'5',	'0',	'',	'',	'',	'0',	'0',	0,	0,	0,	0,	0),
(10010,	'Guard',	'Basic defensive skill that generates block',	'enters a defensive stance',	0,	'defensive',	2,	'0',	'0',	'gain_block:8',	'',	'',	'0',	'0',	0,	0,	0,	0,	2),
(10020,	'Impulse',	'Basic non-elemental magic attack that can only be used if no other skills were used this turn.',	'unleashes a ball of magic at',	0,	'magical_attack',	2,	'0',	'0',	'',	'',	'',	'max_skills_played:0',	'',	0,	0,	-1,	0,	0, 0),
(10030,	'Critical Manoeuvre',	'Powerful attack that deals heavy physical damage. Can only be used if another attack was used this turn',	'smashes',	0,	'physical_attack',	4,	'12',	'0',	'',	'',	'',	'min_attacks_played:1',	'0',	0,	0,	20,	0,	0, 0),
(10040,	'Fleeting Blow',	'A quick attack that expends little energy but can only be used if its the first attack of the turn. Exhaust.',	'performs a glancing blow on',	0,	'physical_attack',	1,	'0',	'0',	'',	'',	'',	'max_attacks_played:0',	'0',	0,	1,	-1,	0,	0, 0),
(10050,	'Strong Guard',	'Gather oneself and prepare for the incoming enemy attack. Cannot be used if any attacks were played this turn.',	'takes cover',	0,	'defensive',	4,	'',	'',	'gain_block:20',	'',	'',	'max_attacks_played:0',	'0',	0,	0,	0,	0,	0, 0),
(10060,	'Astral Blast',	'A powerful non-elemental magical attack that does heavy damage.',	'unleashes a potent force upon',	0,	'magical_attack',	5,	'16',	'0',	'',	'',	'',	'0',	'0',	0,	0,	0,	0,	0, 0),
(10070,	'Barge',	'Collide with the enemy at full force. Requires block to use this skill. If block is greater or equal to 20, double your block.',	'barges at',	0,	'physical_attack',	3,	'10',	'0',	'multiply_block:2~min_block~20',	'',	'',	'min_block:1',	'0',	0,	0,	10,	0,	0, 0),
(10080,	'Recovery',	'Replenish health. More potent if health is below half.',	'initiates a recovery paradigm',	0,	'buff',	3,	'0',	'0',	'gain_hp:4~min_percentage_health~50',	'gain_hp:10~max_percentage_health~50',	'',	'max_percentage_health:100',	'0',	0,	0,	0,	0,	0, 0),
(10090,	'Cautious Strike',	'Inflict damage and gain block. Can only be played if injured and if no attacks have been played this turn.',	'cautiously attacks',	0,	'physical_attack',	3,	'3',	'0',	'gain_block:6~min_block~5',	'gain_block:12~max_block~6',	'',	'max_percentage_health:80',	'max_attacks_played:0',	0,	0,	10,	0,	0, 0),
(10100,	'Frustration',	'Inflict damage on the opponent. Can only be used if blocked damage done this battle is greater than unblocked damage.',	'unleashes their frustration at ',	0,	'physical_attack',	1,	'6',	'0',	'',	'',	'',	'min_unblocked_blocked_damage_ratio:50',	'',	0,	0,	40,	0,	0, 0),
(10110,	'Revenge',	'Inflict heavy damage. Can only be used if you have inflicted less damage than received.',	'acts with a vengeance upon',	0,	'physical_attack',	3,	'12',	'20',	'',	'',	'',	'max_damage_given_taken_ratio:50',	'',	0,	0,	30,	0,	0, 0),
(10120,	'Revelation',	'Draw 2 cards. If this is the last card in your hand, draw 4 cards.',	'achieves a revelation',	0,	'buff',	2,	'0',	'0',	'',	'draw_skills:2~min_hand_size~1',	'draw_skills:4~max_hand_size~1',	'',	'',	0,	0,	0,	0,	0, 0),
(10130,	'Contemplation',	'Gain 4 energy. Exhaust.',	'closes their eyes',	0,	'buff',	0,	'0',	'0',	'',	'gain_energy:4',	'',	'',	'',	0,	1,	0,	0,	0, 0),
(10140,	'Full Heal',	'Recovers all health. Exhaust',	'undergoes deep recuperation',	0,	'buff',	9,	'0',	'0',	'gain_hp:user_missing_hp',	'',	'',	'',	'',	0,	0,	0,	0,	0, 0),
(10150,	'Ravager',	'Inflict heavy damage whilst discarding 3 random cards from your hand.',	'ravages',	0,	'physical_attack',	1,	'6',	'20',	'discard_skills:3',	'',	'',	'',	'',	0,	0,	20,	0,	0, 0),
(10160,	'Berserker Strike',	'Inflict heavy damage and exhaust all skills on hand then draw 2 skills',	'swings wildly',	0,	'physical_attack',	4,	'6',	'50',	'exhaust_skills:user_hand_size',	'draw_skills:2',	'',	'',	'',	0,	0,	35,	0,	0, 0),
(10170,	'Sharpen Blades',	'The next attack the user plays will cost 0 energy. Exhaust',	'shapens their weapons',	0,	'buff',	1,	'0',	'0',	'free_physical_attack:1',	'',	'',	'0',	'0',	0,	1,	0,	0,	0, 0),
(10180,	'Next Frontier',	'The next skill played will cost 0. Exhaust. ',	'enters a new frontier',	0,	'buff',	2,	'0',	'0',	'free_skill:1',	'',	'',	'0',	'0',	0,	1,	0,	0,	0, 0),
(10200,	'Black Hole',	'Exhaust your hand. Damage is proportional to the number of cards exhausted in this way. Exhaust',	'summons a black hole',	0,	'magical_attack',	6,	'hand_size:4:base_damage:5',	'0',	'exhaust_skills:user_hand_size',	'',	'',	'',	'',	0,	1,	-1,	0,	0, 0),
(10210,	'Multi-hit',	'Damage is proportional to the number of attacks played this turn.',	'delivers a flurry of blows',	0,	'physical_attack',	4,	'attacks_played_this_turn:4',	'0',	'',	'',	'',	'',	'',	0,	0,	0,	0,	0, 0),
(10220,	'Invincibility',	'Gain huge amounts of block. Can only be used if the user has no block. Exhaust all cards in hand.',	'becomes impervious',	0,	'defensive',	1,	'0',	'0',	'exhaust_skills:user_hand_size',	'gain_block:60',	'',	'max_block:0',	'',	0,	1,	0,	0,	0, 0),
(10230,	'Agitation',	'Next turn draw an extra 3 skills. Exhaust',	'fidgets impatiently in anticipation',	0,	'buff',	2,	'',	'0',	'',	'',	'temporary_draw3:1',	'',	'',	0,	1,	0,	0,	0, 0),
(10240,	'Desperate Strike',	'Inflict heavy damage. Do not draw any skills next turn unless critically injured.',	'strikes the enemy with all their might',	0,	'physical_attack',	0,	'8',	'0',	'temporary_draw0:1~min_percentage_health~50',	'',	'',	'',	'',	0,	0,	40,	0,	0, 0),
(10250,	'Preparation',	'Draw an extra skill per turn. Exhaust',	'prepares',	0,	'buff',	3,	'',	'0',	'',	'',	'pernament_draw:1',	'',	'',	0,	1,	0,	0,	0, 0),
(10190,	'Iron Will',	'Gain some block. The next defensive skill used this turn will cost 0 energy.',	'enters a defensive stance',	0,	'defensive',	4,	'',	'0',	'free_defensive_skill_this_turn:1',	'gain_block:12',	'',	'0',	'0',	0,	1,	0,	0,	0, 0),
(10260,	'Gather Strength',	'Gain 2 turns of fury. Exhaust',	'gathers strength',	0,	'buff',	5,	'',	'0',	'',	'',	'attack_shift:2',	'',	'',	0,	1,	0,	0,	0, 0),
(10270,	'Aggression',	'Inflict heavy damage. Become vulnerable for 2 turns.',	'unleashes their aggression at',	0,	'physical_attack',	3,	'8',	'20',	'',	'',	'defense_shift:-2',	'',	'',	0,	0,	0,	0,	0, 0),
(10280,	'Power Overwhelming',	'Gain 1 turn of Vigour next turn',	'feels almighty',	0,	'buff',	4,	'',	'0',	'',	'',	'turn_effect1-power_shift:1',	'',	'',	0,	1,	0,	0,	0, 0),
(10290,	'True Tenacity',	'Gain 3 attack for the rest of the battle. Can only be played if there are exactly 3 skills in hand. Exhaust.',	'unleashes their true tenacity',	0,	'buff',	4,	'',	'0',	'',	'',	'attack_adjust:3',	'max_hand_size:3',	'min_hand_size:3',	0,	1,	0,	0,	0, 0),
(10300,	'Sacrifice',	'Lose 12 HP. Gain 4 power, defense and resistance for the rest of the battle. Exhaust.',	'makes a sacrifice',	0,	'buff',	2,	'',	'0',	'gain_hp:-12',	'defense_adjust:4:power_adjust:4:resistance_adjust:4',	'',	'min_health:21',	'',	0,	1,	0,	0,	0, 0),
(10310,	'Guardian Spirit',	'Gain some block. Gain 2 Fortitude. Exhaust.',	'is blessed with divine protection',	0,	'defensive',	5,	'',	'',	'fortitude_adjust:2',	'gain_block:12',	'',	'',	'',	0,	1,	0,	0,	0, 0),
(10320,	'Exhaustive Measures',	'Choose a skill from your deck and exhaust it. Gain energy equal to the energy required to use that skill. Exhaust',	'undertakes exhaustative measures',	0,	'other',	2,	'',	'0',	'exhaust_selectable_skills_deck_energy1:1',	'',	'',	'',	'',	0,	1,	0,	0,	0, 0),
(10330,	'Power Through',	'Choose a skill from your hand and exhaust it. Gain some block.',	'powers through',	0,	'defensive',	2,	'',	'0',	'exhaust_selectable_skills_hand:1',	'gain_block:10',	'',	'min_hand_size:1',	'',	0,	0,	0,	0,	0, 0),
(10340,	'Emmetropic Eye',	'Choose a skill from your deck and place it into your hand. Exhaust',	'unleashes their foresight',	0,	'other',	1,	'',	'0',	'draw_selectable_skills_deck:1',	'',	'',	'',	'',	0,	1,	0,	0,	0, 0),
(10350,	'Salvage',	'Place a skill from your discard pile into your hand. Can only be used if hand size is under 3. Exhaust',	'salvages',	0,	'other',	1,	'',	'0',	'draw_selectable_skills_discard:1',	'',	'',	'max_hand_size:3',	'',	0,	1,	0,	0,	0, 0),
(10360,	'Recycler',	'Place up to 5 skills into your deck from your discard pile. Exhaust.',	'reclaims their lost ones',	0,	'other',	1,	'',	'0',	'shuffle_optional_skills_discard:5',	'',	'',	'',	'',	0,	1,	0,	0,	0, 0),
(10370,	'Purge',	'Select up to 3 skills in your deck to exhaust.',	'undergoes a deep purge',	0,	'other',	1,	'',	'0',	'exhaust_optional_skills_deck:3',	'',	'',	'',	'',	0,	0,	0,	0,	0, 0),
(10380,	'Cyclone',	'Deal heavy damage to a foe. Draw 2 cards and discard 3 cards from your hand.',	'summons forth a cyclone upon',	0,	'magical_attack',	6,	'14',	'30',	'draw_skills:3',	'discard_selectable_skills_hand:3',	'',	'',	'',	0,	0,	0,	0,	0, 0),
(10390,	'Blood of the Forsaken',	'Deal physical damage equal to damage incurred this battle. Ignores target\'s and user\'s stats.',	'exchanges a blood oath with',	0,	'physical_attack',	0,	'unblocked_damage_taken:1:user_attack_multiplier:0:target_defense_multiplier:0',	'0',	'',	'',	'',	'',	'',	0,	0,	-1,	0,	0, 0),
(10400,	'Power Cycle',	'Shuffle your hand into your deck. Draw 2 cards. Gain 2 power. Exhaust',	'undergoes a power cycle',	0,	'buff',	3,	'',	'0',	'shuffle_skills:user_hand_size',	'draw_skills:2',	'power_adjust:2',	'',	'',	0,	1,	0,	0,	0, 0),
(10410,	'Pump and Bump',	'Deal minor physical damage. Gain a small amount of block. Gain 1 Strength. Choose 1 skill from your hand to discard. ',	'pumps themselves up and then charges at',	0,	'physical_attack',	4,	'3',	'0',	'discard_selectable_skills_hand:1:gain_block:6:attack_adjust:1',	'',	'',	'',	'',	0,	0,	0,	0,	0, 0),
(10420,	'Power Burst',	'Temporarily gain 3 power. Exhaust.',	'infuses their spirit with a burst of power',	0,	'buff',	4,	'0',	'0',	'power_modify:3',	'',	'',	'',	'',	0,	1,	0,	0,	0, 0),
(10430,	'Unhinged Attack',	'Deal major physical damage. Lose 3 fortitude temporarily.',	'goes completely unhinged upon ',	0,	'physical_attack',	2,	'12',	'50',	'fortitude_modify:-3~min_status~element_lightning3',	'',	'',	'',	'',	0,	0,	0,	0,	0, 0),
(10440,	'Enter Asylum',	'Can only be used if the user has a non-temporary strength buff. Gain 3 strength. Lose 4 HP.',	'enters a psychotic rage',	0,	'buff',	4,	'0',	'0',	'attack_adjust:3',	'gain_hp:-4',	'',	'min_status:attack_adjust1',	'',	0,	0,	0,	0,	0, 0),
(10450,	'Borrowed Strength',	'Temporarily gain 3 strength. Lose 12HP. Gain 12HP in 3 turns.',	'borrows demonic strength',	0,	'buff',	2,	'0',	'0',	'attack_modify:3',	'gain_hp:-12',	'turn_effect3-gain_hp:12',	'min_health:12',	'',	0,	0,	0,	0,	0, 0),
(10460,	'Well-timed Strike',	'Deal minor damage. Temporarily gain 1 strength.',	'strikes with precision against',	0,	'physical_attack',	3,	'5',	'0',	'attack_modify:1',	'',	'',	'',	'',	0,	0,	10,	0,	0, 0),
(10470,	'Ball Lightning',	'Deal minor lightning damage. Gain 2 Lightning',	'conjures a ball of lightning and shoots it at',	0,	'magical_attack',	2,	'5',	'0',	'element_lightning:2',	'',	'',	'',	'',	0,	0,	0,	0,	0, 0),
(10480,	'Lightning Bolt',	'Requires at least 2 lightning power. Lose 2 lightning power. Do heavy lightning damage.',	'unleashes a bolt of electricity at',	0,	'magical_attack',	3,	'16',	'0',	'element_lightning:-2',	'',	'',	'min_status:element_lightning2',	'',	0,	0,	30,	0,	0, 0);
"; }

if ($con->query($sql) === TRUE) { 
    echo "<br>Default Skills added"; 
} else {
    echo "<br>Error creating table: " . $con->error; 
}

//The battle table is used to turn-based battles.
//This includes duels between players as well as battles against monsters
//The data is mainly stored in a string which can be converted into an array
//attacker is the id of the player attacking
//defender is the id of the player whom is attacked (or monster id)
//defender_type is either player or monster
//battle_start is the time when the battle started
//attacker_data is a string containing the player's CURRENT battle data
//defender_data is a string containing the player's CURRENT battle data
//battle_data includes data such as history of moves; outcome of moves as well as turns elapsed

$sql="
CREATE TABLE `battle` (
  `id` int(11) NOT NULL auto_increment,
  `attacker` int(11) default 0,  
  `defender` int(11) default 0,
  `defender_type` varchar(255) collate latin1_general_ci NOT NULL,
  `attacker_data` text collate latin1_general_ci NOT NULL,
  `defender_data` text collate latin1_general_ci NOT NULL,
  `battle_data` text collate latin1_general_ci NOT NULL,
  `battle_start` int(11) default 0,  
  `battle_last_active` int(11) default 0,
  `complete` varchar(255) default 'no',
    PRIMARY KEY  (`id`)
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Battle table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//Create a table to store information regarding equipment
//Information stored in this stable is used as a database and used in conjunction with the inventory table

$sql="
CREATE TABLE `equipment` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) collate latin1_general_ci NOT NULL,
  `description` text collate latin1_general_ci NOT NULL,
  `type` enum('weapon','armour','gloves','boots','necklace','hat','accessory') collate latin1_general_ci NOT NULL,
  `effectiveness` int(11) NOT NULL, 
  `price` int(11) NOT NULL, 
  `weapon_type` enum('sword','lance','axe','none') collate latin1_general_ci NOT NULL,
  `attack_bonus` int(11) NOT NULL default 0,
  `defence_bonus` int(11) NOT NULL default 0, 
  `magic_bonus` int(11) NOT NULL default 0, 
  `resistance_bonus` int(11) NOT NULL default 0,   
  `agility_bonus` int(11) NOT NULL default 0,
  `dexterity_bonus` int(11) NOT NULL default 0,
  `power_bonus` int(11) NOT NULL default 0,
  `fortitude_bonus` int(11) NOT NULL default 0,  
  `accuracy_bonus` int(11) NOT NULL default 0, 
  `evasion_bonus` int(11) NOT NULL default 0,  
  `critical_bonus` int(11) NOT NULL default 0,
  `level_req` int(11) NOT NULL default 0, 
  `strength_req` int(11) NOT NULL default 0,
  `vitality_req` int(11) NOT NULL default 0, 
  `agility_req` int(11) NOT NULL default 0, 
  `dexterity_req` int(11) NOT NULL default 0, 
  `rarity` int(11) NOT NULL, 
  `event` int(11) NOT NULL, 
  PRIMARY KEY  (`id`)
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Equipment table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}
//Create a table to store information regarding player's equipment inventory
//This table basically stores all the information in regards to all player's equipment 

$sql="CREATE TABLE `inventory_equip` (
  `id` int(11) NOT NULL auto_increment,
  `player_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `status` enum('equipped','unequipped') collate latin1_general_ci NOT NULL default 'unequipped',
  PRIMARY KEY  (`id`)
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Inventory for equipment table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//Create a table to store information regarding player's consumable inventory
//This table basically stores all the information in regards to all player's items 

$sql="CREATE TABLE `inventory_items` (
  `id` int(11) NOT NULL auto_increment,
  `player_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `last_use` int(11) NOT NULL default 0,
  `type` enum('quest','consumable','none') collate latin1_general_ci NOT NULL default 'none',
  PRIMARY KEY  (`id`)
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Inventory for consumable items table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//Create a table to store information regarding player's mail

$sql="CREATE TABLE `mail` (
  `id` int(11) NOT NULL auto_increment,
  `to` int(11) NOT NULL,
  `from` int(11) NOT NULL,
  `subject` varchar(255) collate latin1_general_ci NOT NULL,
  `body` text collate latin1_general_ci NOT NULL,
  `time` int(11) NOT NULL,
  `status` enum('read','unread') collate latin1_general_ci NOT NULL default 'unread',
  PRIMARY KEY  (`id`)
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Player mail table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//Create a table to store information regarding player's stats and information

$sql="CREATE TABLE `players` (
  `id` int(11) NOT NULL auto_increment,
  `username` varchar(255) collate latin1_general_ci NOT NULL,
  `password` varchar(255) collate latin1_general_ci NOT NULL,
  `email` varchar(255) collate latin1_general_ci NOT NULL,
  `verification_key` varchar(255) collate latin1_general_ci NOT NULL,
  `verification_status` tinyint(1) NOT NULL default 0,
  `rank` varchar(255) collate latin1_general_ci NOT NULL default 'Member',
  `date_registered` int(11) NOT NULL,
  `current_login_time` int(11) NOT NULL default 0,
  `previous_login_time` int(11) NOT NULL default 0,  
  `last_active` int(11) NOT NULL,
  `ip` varchar(255) collate latin1_general_ci NOT NULL,
  `image_path` varchar(255) collate latin1_general_ci NOT NULL default 'player.png',
  `level` int(11) NOT NULL default '1',
  `stat_points` int(11) NOT NULL default '1',
  `gold` int(11) NOT NULL default '100',
  `hp` int(11) NOT NULL default '50',
  `maxhp` int(11) NOT NULL default '50',
  `exp` int(11) NOT NULL default '0',
  `energy` int(11) NOT NULL default '10',
  `maxenergy` int(11) NOT NULL default '20',
  `attack` int(11) NOT NULL default '10',
  `defence` int(11) NOT NULL default '10',
  `magic` int(11) NOT NULL default '10',
  `resistance` int(11) NOT NULL default '10',
  `agility` int(11) NOT NULL default '10',
  `dexterity` int(11) NOT NULL default '10',
  `power` int(11) NOT NULL default '0',
  `fortitude` int(11) NOT NULL default '0',
  `skills` varchar(255) NOT NULL default '10000, 10010, 10020',
  `attributes` varchar(255) NOT NULL default '',
  `kills` int(11) NOT NULL default '0',
  `deaths` int(11) NOT NULL default '0',
  `atk_adj` int(11) NOT NULL default '100',
  `def_adj` int(11) NOT NULL default '100',
  `mag_adj` int(11) NOT NULL default '100',
  `res_adj` int(11) NOT NULL default '100',
  `agi_adj` int(11) NOT NULL default '100',
  `dex_adj` int(11) NOT NULL default '100',
  `pow_adj` int(11) NOT NULL default '100',
  `for_adj` int(11) NOT NULL default '100',
  `ban_days` int(11) NOT NULL default '0',
  `premium_days` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`)
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Main player database table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//Battle mod (adds some variables to the player enabling turn based card battle)

$sql="ALTER TABLE players ADD (
  `battle_draw_per_turn` int(11) NOT NULL default '2',
  `battle_actions_per_turn` int(11) NOT NULL default '4',
  `battle_energy_reset` int(11) NOT NULL default '3',
  `battle_ai` varchar(255) NOT NULL default 'default'
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Battle additions added to player table";
} else {
    echo "<br>Error adding new columns: " . $con->error;
}



//TRADE SCRIPT

$sql="CREATE TABLE `trade` (
  `id` int(11) NOT NULL auto_increment,
  `name` varchar(255) collate latin1_general_ci NOT NULL,
  `player_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `time` int(11) NOT NULL,
  `status` enum('sold','unsold') collate latin1_general_ci NOT NULL default 'unsold',
  PRIMARY KEY  (`id`)
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Player trade table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//Monster database 
//id is unique and auto-generated
//username is the name of the monster 
//type is to determine if the monster is regular, unique, boss or none. Not currently used thus default option is none
//image_path represents the name of the image file that depicts the monster
//level represents the power level of the monster
//skill is a string that stores the skills that the monster has access to. Default skills are the first 2 skills in the game (Attack and Defend)
//skill_draw is how many skills a monster will pull out per turn. Default is 1 which means the monster will add 1 skill to their queue of actions per turn
//skill_max_actions is the maximum number of  skills a monster can use per turn. Default is 1 skill per turn
//skill_hand_size is how many skills a monster can hold any any turn; set to 0 for unlimited hand size (default option) 
//skill_discard determines if unused skills are discarded at the end of the turn. Default is 0 which means no discard occurs
//skill_discard_reset determines what happens when the draw pile is empty. Default is 0 which means the discard pile is shuffled back into the draw pile
//energy is used when skills are used. By default every turn the monster will gain x energy
//maxenergy is the maximum energy that a monster can have. Set to 0 for no maximum.
//energy_reset determines if unused energy is lost when a turn ends. Set to 0 (default) which means energy is not reset and carried onto the subsequent turns

$sql="CREATE TABLE `monsters` (
`id` INT NOT NULL AUTO_INCREMENT ,
`username` VARCHAR( 25 ) NOT NULL ,
`type` VARCHAR( 25 ) NOT NULL default 'none',
`image_path` varchar(255) NOT NULL default 'default.png',
`level` INT NOT NULL ,
`skills` varchar(255) NOT NULL default '10000, 10010',
`attributes` varchar(255) NOT NULL default '',
`attack` int(11) NOT NULL default '10',
`defence` int(11) NOT NULL default '10',
`magic` int(11) NOT NULL default '10',
`resistance` int(11) NOT NULL default '10',
`agility` int(11) NOT NULL default '10',
`dexterity` int(11) NOT NULL default '10',
`power` int(11) NOT NULL default '0',
`fortitude` int(11) NOT NULL default '0',
`accuracy` int(11) NOT NULL default '3',
`evasion` int(11) NOT NULL default '3',
`critical` int(11) NOT NULL default '10',
`hp` INT NOT NULL ,
`gold` INT NOT NULL default '100',
`item_id` INT NOT NULL ,
`item_chance` INT NOT NULL ,
`equip_id` INT NOT NULL ,
`equip_chance` INT NOT NULL ,
`habitat` INT NOT NULL ,
PRIMARY KEY ( `id` )
)";
if ($con->query($sql) === TRUE) {
    echo "<br>Enemy monsters table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}


//Battle mod (adds some variables to the monsters enabling turn based card battle)
$sql="ALTER TABLE monsters ADD (
  `battle_draw_per_turn` int(11) NOT NULL default '2',
  `battle_actions_per_turn` int(11) NOT NULL default '3',
  `battle_energy_reset` int(11) NOT NULL default '3',
  `battle_discard_reset` int(11) NOT NULL default '0',
  `battle_ai` varchar(255) NOT NULL default 'default'
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Battle additions added to monster table";
} else {
    echo "<br>Error adding new columns: " . $con->error;
}

//Insert a dummy monster into the game
$query = mysqli_query($con, "SELECT * FROM monsters");
if(!mysqli_num_rows($query) == false) {
 echo "<br>Error creating default values for table as values already exist";
} else {
$sql="
INSERT INTO `monsters` (`username`, `level`, `skills`, `hp`, `attack`, `defence`, `magic`, `resistance`, `agility`, `dexterity`, `power`, `fortitude`, `battle_ai`) VALUES 
('Test Dummy', 1, '10000, 10010, 10020', 25, 8, 8, 8, 8, 8, 8, 0, 0, 'default'),
('Competent Combatant', 1, '10000, 10010, 10020, 10030, 10040, 10050, 10060', 50, 16, 12, 10, 4, 12, 12, 0, 4, 'priority');
";
if ($con->query($sql) === TRUE) { echo "<br>Dummy Enemy added"; } else {
    echo "<br>Error creating table: " . $con->error; }
}

//Adds a table that keeps a tab/count on various player actions

$sql="CREATE TABLE `cooldown` (
  `id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `counter` int(11) NOT NULL default '1',
  `time` int(11) NOT NULL,
  PRIMARY KEY ( `id` )
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Player cooldown table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}
//Adds a new table with different explorable places

$sql="CREATE TABLE `explore` (
`id` INT NOT NULL AUTO_INCREMENT ,
`name` VARCHAR( 25 ) NOT NULL ,
`x` INT NOT NULL ,
`y` INT NOT NULL ,
`region_id` INT NOT NULL ,
`places` VARCHAR(255) NOT NULL,
PRIMARY KEY ( `id` )
)";

if ($con->query($sql) === TRUE) {
    echo "<br>Exploration locations table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//Adds a new table with random data to run events

$sql="
    CREATE TABLE events (
    event_id int(11) NOT NULL auto_increment, 
    event_name varchar(255) NOT NULL, 
    rand1 int(11) NOT NULL, 
    rand2 int(11) NOT NULL, 
    rand3 int(11) NOT NULL, 
    rand4 int(11) NOT NULL, 
    PRIMARY KEY (event_id)
    )";

if ($con->query($sql) === TRUE) {
    echo "<br>Game events table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//Adds a table that keeps a player's log

$sql="CREATE TABLE `player_log` (
  `id` int(11) NOT NULL,
  `player_id` int(11) NOT NULL,
  `msg` text collate latin1_general_ci NOT NULL,
  `status` enum('read','unread') collate latin1_general_ci NOT NULL default 'unread',
  `time` int(11) NOT NULL
);";

if ($con->query($sql) === TRUE) {
    echo "<br>Player logs table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//The cron table is used to store when resets happen during the game
//A refresh is a regular update to player's stats
//A reset happens daily 

$sql="
CREATE TABLE `cron` (
  `name` varchar(45) collate latin1_general_ci NOT NULL,
  `value` varchar(45) collate latin1_general_ci NOT NULL,
  PRIMARY KEY  (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;
";
if ($con->query($sql) === TRUE) {
    echo "<br>Daily reset table created successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

$sql="
INSERT INTO `cron` (`name`, `value`) VALUES
('reset_last', '1581168215'),
('reset_time', '86400'),
('refresh_last', '1581168215'),
('refresh_last_time', '1200');
";

if ($con->query($sql) === TRUE) {
    echo "<br>Daily reset values updated successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//Minesweeper data 

$sql="

    CREATE TABLE minesweeper (
   id int(11) NOT NULL auto_increment,
        type varchar(16) NOT NULL,
        player_id int(11) NOT NULL,
        data text NOT NULL,
        data2 text NOT NULL,
         initial_lives tinyint(1) NOT NULL default 0,
         lives tinyint(1) NOT NULL default 0,
        first_active int(11) NOT NULL,
        last_active int(11) NOT NULL,
        PRIMARY KEY  (id)
    )

";

if ($con->query($sql) === TRUE) {
    echo "<br>Minesweeper player table added successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}


//=======================
// Forum
//=======================

// Categories table
/* These data types basically work the same way as the ones in the users table. This table also has a primary key and the name of the category must be an unique one. */

$sql="

    CREATE TABLE forum_categories (
    cat_id          INT(11) NOT NULL AUTO_INCREMENT,  
    cat_name        VARCHAR(255) NOT NULL,  
    cat_description     VARCHAR(255) NOT NULL,  
    UNIQUE INDEX cat_name_unique (cat_name),  
    PRIMARY KEY (cat_id)  
    ) ENGINE =INNODB;

";
if ($con->query($sql) === TRUE) {
    echo "<br>Forum Categories added successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}


// Topics Table
/* This table is almost the same as the other tables, except for the topic_by field. That field refers to the user who created the topic. The topic_cat refers to the category the topic belongs to. We cannot force these relationships by just declaring the field. We have to let the database know this field must contain an existing user_id from the users table, or a valid cat_id from the categories table. We’ll add some relationships after I’ve discussed the posts table. */

$sql="

    CREATE TABLE forum_topics (
    topic_id        INT(8) NOT NULL AUTO_INCREMENT,  
    topic_subject       VARCHAR(255) NOT NULL,  
    topic_date      INT(11) NOT NULL,  
    topic_cat       INT(8) NOT NULL,  
    topic_by        INT(11) NOT NULL,
    topic_lock        ENUM('open', 'locked') NOT NULL default 'open',
    topic_pin        ENUM('pin', 'unpin') NOT NULL default 'unpin',
    PRIMARY KEY (topic_id)  
    ) ENGINE =INNODB;

";
if ($con->query($sql) === TRUE) {
    echo "<br>Forum Topics added successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

// Posts Table
/* This is the same as the rest of the tables; there’s also a field which refers to a user_id here: the post_by field. The post_topic field refers to the topic the post belongs to. */

$sql="

CREATE TABLE forum_posts (
post_id         INT(8) NOT NULL AUTO_INCREMENT,  
post_content        TEXT NOT NULL,  
post_date       INT(11) NOT NULL,  
post_topic      INT(8) NOT NULL,  
post_by     INT(11) NOT NULL,  
post_visible        enum('visible','hidden') NOT NULL default 'visible',
PRIMARY KEY (post_id)  
) ENGINE =INNODB;

";
if ($con->query($sql) === TRUE) {
    echo "<br>Forum Posts added successfully";
} else {
    echo "<br>Error creating table: " . $con->error;
}

//Relationships
/* When a category gets deleted from the database, all the topics will be deleted too. If the cat_id of a category changes, every topic will be updated too. That’s what the ON UPDATE CASCADE part is for. Of course, you can reverse this to protect your data, so that you can’t delete a category as long as it still has topics linked to it. If you would want to do that, you could replace the ‘ON DELETE CASCADE’ part with ‘ON DELETE RESTRICT’. There is also SET NULL and NO ACTION, which speak for themselves. */

$sql="

ALTER TABLE forum_topics ADD FOREIGN KEY(topic_cat) REFERENCES forum_categories(cat_id) ON DELETE CASCADE ON UPDATE CASCADE;

";
if ($con->query($sql) === TRUE) {
    echo "<br>Forum connection between categories and topics added successfully (Delete topics if a category is deleted)";
} else {
    echo "<br>Error creating table: " . $con->error;
}

$sql="

ALTER TABLE forum_posts ADD FOREIGN KEY(post_topic) REFERENCES forum_topics(topic_id) ON DELETE CASCADE ON UPDATE CASCADE;

";
if ($con->query($sql) === TRUE) {
    echo "<br>Forum connection between topics and posts added successfully (Delete a post if a topic is deleted)";
} else {
    echo "<br>Error creating table: " . $con->error;
}


$sql="

ALTER TABLE forum_topics ADD FOREIGN KEY(topic_by) REFERENCES players(id) ON DELETE RESTRICT ON UPDATE CASCADE;


";
if ($con->query($sql) === TRUE) {
    echo "<br>Forum connection between topics and player added successfully (Delete a topic if a player is deleted)";
} else {
    echo "<br>Error creating table: " . $con->error;
}


$sql="

ALTER TABLE forum_posts ADD FOREIGN KEY(post_by) REFERENCES players(id) ON DELETE RESTRICT ON UPDATE CASCADE;

";

if ($con->query($sql) === TRUE) {
    echo "<br>Forum connection between posts and player added successfully (Delete a post if a player is deleted)";
} else {
    echo "<br>Error creating table: " . $con->error;
}

echo "<br><br><b>Delete this php file once installation has been completed</b>";

?>
