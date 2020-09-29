<?php
/* Remove the below line on production */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
/* Remove the above line on production */

// Connected to the database
function connecttoDb () {   
    // Database credentials
    $servername = "localhost";
    $username   = "root";
    $password   = "root";
    $dbname     = "driscblq_web_traffic";
    // Connect to the databse
    $connection = mysqli_connect($servername, $username, $password, $dbname);    
    // Check connection
    if (!$connection) {
        die("Connection failed: " . mysqli_connect_error());
    }
    return $connection;
}    

// Show 404 error notice
function four_o_four() {
    header("HTTP/1.0 404 Not Found");
    echo "<h1>404 Not Found</h1>";
    echo "The page that you have requested could not be found.";        
    exit();
}

// List of block countries
function blockCountries () {
    return [
        'in' => 'india',
        'pk' => 'pakistan',
        'bd' => 'bangladesh',
        'np' => 'nepal',
        'lk' => 'srilanka',           
    ];
}

// Check if it's block countries
function isBlockedCounty( $callApi ) {
	// Check if the country ISO code is exist in the list of block countries array
    if( array_key_exists( $callApi['country_en'], blockCountries() ) ) {
        return true;       
    }
    return false;
}

// Check block countries as well as If the user is using VPN, Proxy, Tor or Hosting Provider
function isBlock ( $callApi ) {    	
   
    if( array_key_exists( $callApi['country_en'], blockCountries() ) ) {
        return true;       
    }

    if( $callApi['isAanonymous'] ) {
        return true;
    }

    if( $callApi['isAnonymousProxy'] ) {
        return true;
    }

    if( $callApi['isAnonymousVpn'] ) {
        return true;
    }

    if( $callApi['isHostingProvider'] ) {
        return true;
    }

    if( $callApi['isTorExitNode'] ) {
        return true;
    }

    return false;        
}

// Call actual api to get details
function callApi () {

    // Hold the api results
    $result = [];
    // Maxmind account details
    $accountId  = ' Your Account ID';
    $licenseKey = 'Your License Key';
    // Get ip address
    $ipAddress = getUserIp();
    // Call curl
    $ch = curl_init();  
    curl_setopt($ch, CURLOPT_URL, "https://geoip.maxmind.com/geoip/v2.1/insights/{$ipAddress}"); 
    curl_setopt($ch, CURLOPT_USERPWD, $accountId . ":" . $licenseKey);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); 
    curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
    $record = curl_exec($ch);  
    curl_close($ch);
    // Convert json to array
    $record = json_decode( $record, true );

    $result['ipAddress']         = isset( $record['traits']['ip_address'] ) ? $record['traits']['ip_address'] : '';
    $result['country']           = isset( $record['country']['names']['en'] ) ? strtolower( $record['country']['names']['en'] ) : '';
    $result['country_en']        = isset( $record['country']['iso_code'] ) ? strtolower( $record['country']['iso_code'] ) : '';
    $result['city']              = isset( $record['city']['names']['en'] ) ? strtolower( $record['city']['names']['en'] ) : '';
    $result['userType']          = isset( $record['traits']['user_type'] ) ? $record['traits']['user_type'] : '';	        
    $result['isp']               = isset( $record['traits']['isp'] ) ? $record['traits']['isp'] : '';
    $result['organization']      = isset( $record['traits']['organization'] ) ? $record['traits']['organization'] : '';
    $result['network']           = isset( $record['traits']['network'] ) ? $record['traits']['network'] : '';	 

    $result['isAanonymous']      = isset( $record['traits']['is_anonymous'] ) ? $record['traits']['is_anonymous'] : 0;
    $result['isAnonymousProxy']  = isset( $record['traits']['is_anonymous_proxy'] ) ? $record['traits']['is_anonymous_proxy'] : 0;
    $result['isAnonymousVpn']    = isset( $record['traits']['is_anonymous_vpn'] ) ? $record['traits']['is_anonymous_vpn'] : 0;
    $result['isHostingProvider'] = isset( $record['traits']['is_hosting_provider'] ) ? $record['traits']['is_hosting_provider'] : 0;
    $result['isTorExitNode']     = isset( $record['traits']['is_tor_exit_node'] ) ? $record['traits']['is_tor_exit_node'] : 0;

    $result['referer']           = isset( $_SERVER['HTTP_REFERER'] ) ? $_SERVER['HTTP_REFERER']: ' ';
    $result['userAgent']         = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';

    return $result;	
}

 // Get the user IP address
function getUserIp() {
    // Real visitor ip address
    if (isset($_SERVER["HTTP_CF_CONNECTING_IP"])) {
        $_SERVER['REMOTE_ADDR'] = $_SERVER["HTTP_CF_CONNECTING_IP"];
    }
    $ip = $_SERVER['REMOTE_ADDR'];
    return $ip; 
}

// Check if the IP is alredy in our databse
function checkExistingIp () {
    // Db connection
    $connection = connecttoDb();
    // Get user IP
    $userIp     = getUserIp();
    // Get Ip from database table
    $sql        = "SELECT ip_address FROM BlockVpnProxy WHERE ip_address = '$userIp'";
    $result     = mysqli_query($connection, $sql);

    if (mysqli_num_rows($result) > 0) {
        $row      = mysqli_fetch_assoc($result);
        $dbUserIp = $row['ip_address'];
        return $dbUserIp == $userIp;
    } 
    return false;
}   

 // Check the status of the IP Address
function checkStatus () {
    // Database connection
    $connection = connecttoDb();
    // Get user IP
    $userIp     = getUserIp();
    // Check status of the IP address
    $sql        = "SELECT user_status FROM BlockVpnProxy WHERE ip_address = '$userIp' ";
    $result     = mysqli_query($connection, $sql);   
   
    if (mysqli_num_rows($result) > 0) {
        $row    = $result = mysqli_fetch_assoc( $result );
        $status = $row['user_status'];   
        return $status;
    }
    return false;  
}

 // Insert data to database table
function insertData( $status = null ) {
    // Database connection
    $connection = connecttoDb();
    // Get data from the api call
    $callApi = callApi();
    // Extract the array which is return by callApi() function
    extract( callApi() );

    $userStatus = 0;

    if( $status ) {
        $userStatus = $status;
    } 

    $ipAddress  = getUserIp();            
    $loggedTime = date('Y-m-d h:i:s');
    $visitedUrl = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

    /* Preparing column and value for insert */
    $columns  =  [ 'ip_address', 'country', 'city', 'user_type', 'isp', 'organization', 'network', 'is_anonymous', 'is_anonymous_proxy', 'is_anonymous_vpn', 'is_hosting_provider', 'is_tor_exit_node', 'referer', 'user_agent', 'logged_time', 'user_status', 'visited_url' ];  

    $columnsValue = [ $ipAddress, $country, $city, $userType, $isp, $organization, $network, $isAanonymous, $isAnonymousProxy, $isAnonymousVpn, $isHostingProvider, $isTorExitNode, $referer, $userAgent, $loggedTime, $userStatus, $visitedUrl ];

    if( isBlockedCounty( $callApi ) ) {
        $columns[] = 'blocked_country';
        $columnsValue[] = 1;
    }

    $data = [];
    foreach( $columnsValue as $keys => $values ) {
        $data[] = "'$values'";         
    }
  
    $implodeColumns      = implode( ', ', $columns );
    $implodeColumnsValue = implode( ', ', $data );
    /* Preparing column and value for insert */

    // Insert data to table
    $sql = "INSERT INTO BlockVpnProxy ( $implodeColumns ) VALUES ( $implodeColumnsValue )";

    if ($connection->query($sql) === FALSE ) {
        die( "Error: " . $sql . "<br>" . $connection->error );
    }

    if( 1 == $status ) {
        // Return 404 page
       four_o_four();
    } 
}

 // Log data to database
 function loggedData () {    	
    // If IP address already exist        
    if( checkExistingIp() ) {
        // If status is not blocked       
        if( ! checkStatus() ) {         	
            $userIp     = getUserIp();
            $connection = connecttoDb();
            $sql        = "UPDATE BlockVpnProxy SET user_status = 0 WHERE ip_address = '$userIp' ";

            if ($connection->query($sql) === FALSE) {
               die( "Error updating record: " . $connection->error );
            }       
        } elseif( 1 == checkStatus() ) {           
            // If used blocked but again try to access the site then return 404 page
            four_o_four();
        } elseif( 2 == checkStatus() ) {
            // Only requred if the admin manually updated user_status column to 2
            // Because admin want to get the data again 
            $connection = connecttoDb();  
            // Get data from api   
            $callApi = callApi();       
		    extract( $callApi );

		    $userIp     = getUserIp();        	        
		    $loggedTime = date('Y-m-d h:i:s');
		    $visitedUrl = $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

		    $userStatus = 2;

		    if( isBlock( $callApi ) ) {            	
	            $userStatus = 1;
            } else {
            	$userStatus = 0;
            } 

		    $sql = "UPDATE BlockVpnProxy SET ip_address = '$userIp', country = '$country', city = '$city', user_type = '$userType', isp = '$isp', organization = '$organization', network = '$network', is_anonymous = '$isAanonymous', is_anonymous_proxy = '$isAnonymousProxy', is_anonymous_vpn = '$isAnonymousVpn', is_hosting_provider = '$isHostingProvider', is_tor_exit_node = '$isTorExitNode', referer = '$referer', user_agent = '$userAgent', logged_time = '$loggedTime', visited_url = '$visitedUrl', user_status = '$userStatus' WHERE ip_address = '$userIp' ";

            if ($connection->query($sql) === FALSE) {
               die( "Error updating record: " . $connection->error );
            }   

            if( isBlock( $callApi ) ) {            	
	            four_o_four();
            } 
        }
    } elseif ( isBlock( $callApi ) ) { 
        // User visited first time from blocked countries
       insertData( 1 );                      
    } else {
        // User visited first time from unBlocked countries
        insertData( 0 );  
    }
}
// Start the Engine :) 
loggedData();