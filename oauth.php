<?php

############
# INCLUDES #
############
include 'oauth-keys.php';

#############
# VARIABLES #
#############
# This probably needs to be elsewhere?
$url = 'http://fantasysports.yahooapis.com/fantasy/v2/league/357.l.103306/teams';
$scope = 'test';

$consumer_key = $consumer_data[$scope]['key'];
$consumer_secret = $consumer_data[$scope]['secret'];

// By default, try to store token information in /tmp folder
$token_file_name = '/tmp/oauth_data_token_storage_' . $consumer_key . '.out';

$access_token = NULL;
$access_secret = NULL;
$access_session = NULL;
$access_verifier = NULL;
$auth_failure = NULL;
$store_access_token_data = false;

// MODIFY: Insert your own consumer key and secret here!
// Moved to the include oauth above so it can be excluded from git

##############
# FUNCTIONS: #
##############

function open_token_file() {

  $invalid_file = false;

  global $access_token;
  global $access_secret;
  global $access_session;
  global $token_file_name;

  if( file_exists( $token_file_name ) && $tok_fh = fopen( $token_file_name, 'r' ) ) {
    #open_token_file($tok_fh); #pass the filehandle
 
    #NOTE: there has *got* to be a better way of reading the contents of the file than this nonsense.

    #Get first line: access token
    $access_token = fgets( $tok_fh );
    if( $access_token ) {
      // Get next line: access secret
      $access_secret = fgets( $tok_fh );
      if( $access_secret ) {
        // Get next line: access session handle
        $access_session = fgets( $tok_fh );
        if( ! $access_session ) {
          $invalid_file = true;
        }
      } else {
        $invalid_file = true;
      }
    } else {
      $invalid_file = true;
    }
  
    if( $invalid_file ) {
      print "File did not seem to be formatted correctly -- needs 3 lines with access token, secret, and session handle.\n";
      $access_token = NULL;
      $access_secret = NULL;
      $access_session = NULL;
    } else {
      print "Got access token information!\n";
  
      $access_token = rtrim( $access_token );
      $access_secret = rtrim( $access_secret );
      $access_session = rtrim( $access_session );
  
      print " Token: ${access_token}\n";
      print " Secret: ${access_secret}\n";
      print " Session Handle: ${access_session}\n\n";
    }
  
    // Done with file, close it up
    fclose( $tok_fh );
  } else {
    print "Couldn't open ${token_file_name}, assuming we need to get a new request token.\n";
  }
}


function refresh_token($oauth_conn) {
  // 2. If we get an auth error, try to refresh the token using the session.

  global $access_token;
  global $access_secret;
  global $access_session;
  global $store_access_token_data;
  #global $token_file_name;

  try {
    $response = $oauth_conn->getAccessToken( 'https://api.login.yahoo.com/oauth/v2/get_token', $access_session, $access_verifier );
  } catch( OAuthException $e ) {
    print 'Error: ' . $e->getMessage() . "\n";
    print 'Response: ' . $e->lastResponse . "\n";

    $response = NULL;
  }

  print_r( $response );

  if( $response ) {
    $access_token = $response['oauth_token'];
    $access_secret = $response['oauth_token_secret'];
    $access_session = $response['oauth_session_handle'];
    $store_access_token_data = true;

    print "Was able to refresh access token:\n";
    print " Token: ${access_token}\n";
    print " Secret: ${access_secret}\n";
    print " Session Handle: ${access_session}\n\n";

  } else {

    $access_token = NULL;
    $access_secret = NULL;
    $access_session = NULL;
    print "Unable to refresh access token, will need to request a new one.\n";
  }
}

function rewrite_token() {

  global $access_token;
  global $access_secret;
  global $access_session;
  global $token_file_name;

  print "Looks like we need to store access token data! Doing that now.\n";

  $tok_fh = fopen( $token_file_name, 'w' );
  if( $tok_fh ) {
    fwrite( $tok_fh, "${access_token}\n" );
    fwrite( $tok_fh, "${access_secret}\n" );
    fwrite( $tok_fh, "${access_session}\n" );

    fclose( $tok_fh );
  } else {
    print "Hm, couldn't open file to write back access token information.\n";
  }

}

function process_request($url) {

  global $access_token;
  global $access_secret;
  #global $access_session;
  #global $token_file_name;
  global $consumer_key;
  global $consumer_secret;
  global $auth_failure;

 
  $oauth_conn = new OAuth( $consumer_key, $consumer_secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI );
  $oauth_conn->enableDebug();


  try {
    $oauth_conn->setToken( $access_token, $access_secret );
    if( $oauth_conn->fetch( $url ) ) {
      print "Got data from API:\n\n";
      #print $oauth_conn->getLastResponse() . "\n\n";
      $xml=simplexml_load_string($oauth_conn->getLastResponse()) or die ("Error: couldn't load XML data.");
      #print $xml->fantasy_content->game->players->player[0]->name->full . "\n\n";
      #var_dump($xml[0]);

      return $xml;

      print "Successful!\n";
    } else {
      print "Couldn'\t fetch\n";
    }
  } catch( OAuthException $e ) {
    print 'Error: ' . $e->getMessage() . "\n";
    print 'Error Code: ' . $e->getCode() . "\n";
    print 'Response: ' . $e->lastResponse . "\n";

    if( $e->getCode() == 401 ) {
      $auth_failure = true;
    }
  }
}

function get_new_token() {

  global $access_token;
  global $access_secret;
  global $access_session;
  global $token_file_name;
  global $consumer_key;
  global $consumer_secret;
  global $auth_failure;
  global $store_access_token_data;

  print "Better try to get a new access token.\n";
  print "Trying: \$oauth_conn = new OAuth( $consumer_key, $consumer_secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI );";
  $oauth_conn = new OAuth( $consumer_key, $consumer_secret, OAUTH_SIG_METHOD_HMACSHA1, OAUTH_AUTH_TYPE_URI );
  print "oauth_conn is now set";
  $oauth_conn->enableDebug();

  $request_token = NULL;

  try {
    $response = $oauth_conn->getRequestToken( "https://api.login.yahoo.com/oauth/v2/get_request_token", 'oob' );
    $request_token = $response['oauth_token'];
    $request_secret = $response['oauth_token_secret'];

    print "Request token: $request_token";
    print "Request secret: $request_secret";

    print "Hey! Go to this URL and tell us the verifier you get at the end.\n";
    print ' ' . $response['xoauth_request_auth_url'] . "\n";

  } catch( OAuthException $e ) {
    print "ERMAGERD\n\n";
    print $e->getMessage() . "\n\n";
  }

  // Wait for input, then try to use it to get a new access token.
  if( $request_token && $request_secret ) {
    print "Type the verifier and hit enter...\n";
    $verifier = fgets( STDIN );
    $verifier = rtrim( $verifier );

    print "Here's the verifier you gave us: ${verifier}\n";

    try {
      $oauth_conn->setToken( $request_token, $request_secret );
      print "Running: \"\$response = \$oauth_conn->getAccessToken( 'https://api.login.yahoo.com/oauth/v2/get_token', NULL, $verifier );\"";
      $response = $oauth_conn->getAccessToken( 'https://api.login.yahoo.com/oauth/v2/get_token', NULL, $verifier );
      
      #var_dump($response);


      print "Got it!\n";
      $access_token = $response['oauth_token'];
      $access_secret = $response['oauth_token_secret'];
      $access_session = $response['oauth_session_handle'];
      $store_access_token_data = true;
      print " Token: ${access_token}\n";
      print " Secret: ${access_secret}\n";
      print " Session Handle: ${access_session}\n\n";

    } catch( OAuthException $e ) {
      print 'Error: ' . $e->getMessage() . "\n";
      print 'Response: ' . $e->lastResponse . "\n";
      print "Shoot, couldn't get the access token. :(\n";
      exit;
    }
  }
}

function oauth_get_xml($url) {

  global $access_token;
  global $store_access_token_data;
  global $auth_failure;


  open_token_file();

  if( $access_token ) {
    
    $output=process_request($url);

    if( $auth_failure ) {
      refresh_token(); 
    }

  }

  if( ! $access_token ) {

    get_new_token();
    $output=process_request($url);
  }
  if( $store_access_token_data ) {
    rewrite_token();
  }
  return $output;
}

###########################
# MAIN PROGRAM START HERE #
###########################

### in place for testing purposes; in theory all you have to do is include 'oauth.php' in another script and call
### oauth_get_xml($url).
#$outxml=oauth_get_xml($url);

#var_dump($outxml);

?>
