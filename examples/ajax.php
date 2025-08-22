<?php

// THIS EXAMPLE IS VERY BRIEF!
// CHECK THE DOCUMENTATION TO SEE WHAT METHODS ARE AVAILABLE!

// include the wrapper class
require '../Zebra_Database.php';

// load the credentials
require 'credentials.php';

// create a new database wrapper object
$db = new Zebra_Database();

// connect to the MySQL server and select the database
$db->connect($credentials['host'], $credentials['username'], $credentials['password'], $credentials['database']);

// let's work with a country
$country = 'United States';

// get the country's code
$country_code = $db->dlookup('Code', 'country', 'Name = ?', array($country));
