<!DOCTYPE html>

<html>

    <head>

        <meta charset="utf-8">

        <title>Zebra_Database Example</title>

    </head>

    <body>

    <p>
        Prior to running this example you must first download and install the <a href="http://dev.mysql.com/doc/index-other.html">
        <em>world</em></a> test database from MySQL's website.<br>
        Then, you must edit this file (example.php) and change the settings of <em>host</em>, <em>user name</em>, <em>password</em>
        and <em>database</em> to match your configuration.
    </p>

    <?php

        // THIS EXAMPLE IS VERY BRIEF!
        // CHECK THE DOCUMENTATION TO SEE WHAT METHODS ARE AVAILABLE!

        // include the wrapper class
        require '../Zebra_Database.php';

        // create a new database wrapper object
        $db = new Zebra_Database();

        // connect to the MySQL server and select the database
        $db->connect(
            '',     // host
            '',     // user name
            '',     // password
            ''      // database
        );

        $db->set_charset();

        // let's work with a country
        $country = 'United States';

        // get the country's code
        $country_code = $db->dlookup('Code', 'country', 'Name = ?', array($country));

        // get all the cities for the country code
        $db->query('
            SELECT
                Name
            FROM
                city
            WHERE
                CountryCode = ?
            ORDER BY
                Name
        ', array($country_code));

        // get all the languages spoken for the country code
        $db->query('
            SELECT
                Language,
                IsOfficial,
                Percentage
            FROM
                countrylanguage
            WHERE
                CountryCode = ?
            ORDER BY
                Percentage DESC
        ', array($country_code));

    ?>

    </body>

</html>
