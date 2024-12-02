# Boomarian Quest Online

## Introduction

An online collectable card battler game based using PHP, Javascript, JQuery, Bootstrap 4 and MySQL

I created this during the start of lockdown back in 2020 after losing my job.

I updated it December 2024 with some AI generated images for the cards using Imagen and Dall-E

It is an extension of a text-based browser game that I made back in 2013-2014

I was playing a lot of Slay the Spire during this time and tinkering around with my home lab where I installed my old project on my Raspberry Pi 4. 

I then thought to myself, I could probably make a card game based off my existing work. And as the lockdowns dragged on, this project became my new job

## Screenshots

![Demo](https://github.com/boomyville/Boomarian-Quest-Online/blob/main/Screenshots/mobile_battle.gif?raw=true)

![Demo](https://github.com/boomyville/Boomarian-Quest-Online/blob/main/Screenshots/regular_battle.gif?raw=true)

## Technologies used

Bootstrap 4 is used to provide the CSS for our cards. This was the first time I used bootstrap 4. 

I can't believe I crawled through all that documentation to get this to all work without watching YouTube tutorials or use ChatGPT (well generative AI wasn't really available to the masses back then)

JQuery is used sparingly for client side interactivity (mainly for checking if a checkbox is checked)

Lots of PHP is used for the logic of the game and grabbing data from a MySQL database

The MySQL database stores all the information of the game including
- Player stats
- Monster stats
- Skills

![Demo](https://github.com/boomyville/Boomarian-Quest-Online/blob/main/Screenshots/database1.png)
![Demo](https://github.com/boomyville/Boomarian-Quest-Online/blob/main/Screenshots/database2.png)
![Demo](https://github.com/boomyville/Boomarian-Quest-Online/blob/main/Screenshots/database3.png)

There was a plan for players to attack other players but I never got around to implementing that (there is an auto-battle feature that would've been used for this)

![Auto-battle](https://github.com/boomyville/Boomarian-Quest-Online/blob/main/Screenshots/auto_battle.gif?raw=true)

## How to install

There are several PHP files that are required to be edited for this to work

I probably should've combined these into one config.php file but I guess back in the day I just put credentials I needed in whichever file that needed it

editor.php
- I use adminer.php to enable browser-based modification of the database
- Not really needed but was convenient

manager.php
- Tiny File Manager is a PHP-based file manager that works on the browser
- Again, not really needed but made it a bit easier to move files around
- I mean, the proper way would be to use FTP or SSH files into your web server

register_mail.php
- For users to register, they need to confirm their email
- A verification code is sent to their email using PHPMailer (not included)
- To use PHPMailer, I provided a gmail account and via OAUTH2 verfication, I could send emails to users
- This requires a gmail address as well as OAUTH tokens and Gmail secrets
- Follow the PHPMailer instructions

![Demo](https://github.com/boomyville/Boomarian-Quest-Online/blob/main/Screenshots/register.png)

register.php, password_reset.php and verify.php
- Password reset uses Google's recaptcha to make sure bots don't spam new accounts
- We need to provide recaptcha token here for it to work
- User accounts require email validation to be active
- Passwords are secured using AES-256 and secret_key stored in config.php
- Passwords are stored as an encrypted string on the mySQL database 
- If I was to redesign this, I would store the secret key as an environmental variable on the server as well as the password salt

config.php
- This is where we store our server's details including:
- mySQL login
- URL to our server
- Time zone

Run install.php to install the game
- This file has an explanation of all the tables and columns in the database
- Database configuration is required for adding new skills and monsters to the game
