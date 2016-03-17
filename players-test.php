<?php

include 'oauth.php';

$url = 'http://fantasysports.yahooapis.com/fantasy/v2/league/357.l.103306/players';

$xmlout = oauth_get_xml($url);

var_dump($xmlout);

?>
