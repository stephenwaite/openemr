<?php
/**
* modules/sms_email_reminder/sendnotifications.php Send an SMS using Twilio.
* You can run this file 3 different ways:
*
* - Save it as sendnotifications.php and at the command line, run 
*        php sendnotifications.php
*
* - Upload it to a web host and load mywebhost.com/sendnotifications.php 
*   in a web browser.
* - Download a local server like WAMP, MAMP or XAMPP. Point the web root 
*   directory to the folder containing this file, and load 
*   localhost:8888/sendnotifications.php in a web browser.
*
* Copyright (C) 2013-2014
* 
* LICENSE: This program is free software; you can redistribute it and/or 
* modify it under the terms of the GNU General Public License 
* as published by the Free Software Foundation; either version 3 
* of the License, or (at your option) any later version. 
* This program is distributed in the hope that it will be useful, 
* but WITHOUT ANY WARRANTY; without even the implied warranty of 
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the 
* GNU General Public License for more details. 
* You should have received a copy of the GNU General Public License 
* along with this program. If not, see <http://opensource.org/licenses/gpl-license.php>;. 
* 
* @package OpenEMR 
* @author NetConnexions 
* @author Brady Miller <brady@sparmy.com> 
* @author Stephen Waite <stephen.waite@cmsvt.com>
* @link http://www.open-emr.org 
*/

/** 
* Sends an sms to server admins notifying that server is down
* 
* This function will set AccountSid and AuthToken from twilio.com/user/account
* instantiate a new twilio REST client 
* 
* @param string $AcountSid  	from twilio user account
* @param string $AuthToken  	from twilio user account
* @param string $from 		your twilio number or outgoing caller id
* @param array  $people		server admins like $people = array("9924927267" => "Johnny", "4158675310" => "Helen", "4158675311" => "Virgil",	);

* @param string $body		body of message like $body = "Bad news $name, the server is down and it needs your help";
*/

	require "twilio/Services/Twilio.php";

	function sendtsms($AccountSid,$AuthToken,$from,$people,$body){

        	$client = new Services_Twilio($AccountSid, $AuthToken);

        	foreach ($people as $to => $name) {
	
        		$client->account->sms_messages->create($from, $to, $body);
	        	echo "Sent message to $name";
                }
        }
?>
