<?php


//GLOBALS
$tweets = array();
$tweetout = '';
$nb_calls_API = 0;


function add_quotes($str) { return '"'.$str.'"'; }



function queryTwitterAPI ( $method, $path, $query = array() ) {

    // Token de l'app "Falcon it"
    // $token = '11392562-DteiIqOzo0ZpmN2DKfb3bOxg38YaImuMA87BNJHlr';
    // $token_secret = 'deHoRLPeXqo58ASbiZxnODmNMbARrUui2aCP17lBUmh0o';
    // $consumer_key = '4Tnj2dlIjPM66OSQp9zg';
    // $consumer_secret = '110NycoFnix6YYmrA79b6IbpNjobHWyPOsxyKcnPY';

    // Token de l'app "Vesper"
    $token = '11392562-ySqin3IHCRjIBJ0tZEjcbizzx0gXo9m3dZItCUUBN';
    $token_secret = 'HePvQJ2fgAddCyOtOpNkGDJzqds6od16wgdMFkKlnx3kh';
    $consumer_key = '43jKV27WSFhvZbtW7Hs3uOJke';
    $consumer_secret = 'XNemydGyGjVHAQa81v2oKpRDGfki6k5j1Iek6KbHX2MquoVKWP';


    $host = 'api.twitter.com';
    //$method = 'GET'; // --> param de la fonction
    //$path = '/1.1/lists/statuses.json'; // api call path // --> param de la fonction
    //$path = '/1.1/application/rate_limit_status.json';


    $oauth = array(
        'oauth_consumer_key' => $consumer_key,
        'oauth_token' => $token,
        'oauth_nonce' => (string)mt_rand(), // a stronger nonce is recommended
        'oauth_timestamp' => time(),
        'oauth_signature_method' => 'HMAC-SHA1',
        'oauth_version' => '1.0'
    );

    $oauth = array_map("rawurlencode", $oauth); // must be encoded before sorting
    $query = array_map("rawurlencode", $query);

    $arr = array_merge($oauth, $query); // combine the values THEN sort

    asort($arr); // secondary sort (value)
    ksort($arr); // primary sort (key)

    // http_build_query automatically encodes, but our parameters
    // are already encoded, and must be by this point, so we undo
    // the encoding step
    $querystring = urldecode(http_build_query($arr, '', '&'));

    $url = 'https://'.$host.$path;

    // mash everything together for the text to hash
    $base_string = $method."&".rawurlencode($url)."&".rawurlencode($querystring);

    // same with the key
    $key = rawurlencode($consumer_secret)."&".rawurlencode($token_secret);

    // generate the hash
    $signature = rawurlencode(base64_encode(hash_hmac('sha1', $base_string, $key, true)));

    // this time we're using a normal GET query, and we're only encoding the query params
    // (without the oauth params)
    $url .= "?".http_build_query($query);    
    $url = str_replace("&amp;","&",$url); //Patch by @Frewuill

    $oauth['oauth_signature'] = $signature; // don't want to abandon all that work!
    ksort($oauth); // probably not necessary, but twitter's demo does it

    // also not necessary, but twitter's demo does this too
    $oauth = array_map("add_quotes", $oauth);

    // this is the full value of the Authorization line
    $auth = "OAuth " . urldecode(http_build_query($oauth, '', ', '));

    // if you're doing post, you need to skip the GET building above
    // and instead supply query parameters to CURLOPT_POSTFIELDS
    $options = array( CURLOPT_HTTPHEADER => array("Authorization: $auth"),
                      //CURLOPT_POSTFIELDS => $postfields,
                      CURLOPT_HEADER => false,
                      CURLOPT_URL => $url,
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_SSL_VERIFYPEER => false);


    
    // do our business
    $feed = curl_init();
    curl_setopt_array($feed, $options);
    $json = curl_exec($feed);
    curl_close($feed);
    //print_r($json);
    //die();


    $data = json_decode($json);
    //var_dump($data);


    // Gestion si erreur
    // object(stdClass)#1 (1) { ["errors"]=> array(1) { [0]=> object(stdClass)#2 (2) { ["message"]=> string(19) "Rate limit exceeded" ["code"]=> int(88) } } }

    //if ( isset($data->errors) || ( is_array($data) && !$data[0]->created_at) ) { // Si erreur ou si ne correspond pas à une liste de tweets
    if ( isset($data->errors) ) { // Si erreur ou si ne correspond pas à une liste de tweets
        die('<p>Problème en vue :/<br><br></p><pre>'.print_r($data, true).'</pre>');
        //echo '<p>Problème en vue :/<br><br></p><pre>'.print_r($data, true).'</pre>';
    }

    return $data;

}


function checkAPIRateLimits ($decoded_json) {
    $date = new DateTime();
    $date->setTimestamp($decoded_json->resources->lists->{'/lists/statuses'}->reset);
    return array(
            'remaining' => $decoded_json->resources->lists->{'/lists/statuses'}->remaining,
            'html' => 'Nb appels restant pour "list/statuses" : '.$decoded_json->resources->lists->{'/lists/statuses'}->remaining.' (reset à '.$date->format('d/m/Y H:i').')'
        );
    // A faire : une boucle pour voir si un autre param est low limit
}

$result = queryTwitterAPI('GET', '/1.1/application/rate_limit_status.json');
//var_dump($result);
$checkAPI = checkAPIRateLimits($result);




function getListTweets ($max_id = null, $since_id = null) {

    global $tweets, $nb_calls_API;


    // Construction de la requête
    $query = array(
            'list_id' => 11961002,
            'slug' => 'irl-com',
            'include_rts' => 1,
            'count' => 800
        );
    if ($max_id) { $query['max_id'] = $max_id; }
    if ($since_id) { $query['since_id'] = $since_id; }

    // Requête
    $decoded_json = queryTwitterAPI('GET', '/1.1/lists/statuses.json', $query);

    $nb_calls_API++;
    if ($nb_calls_API > 5) {
        die('Plus de 5 boucles sur getListTweets(). On a préféré s\'arrêter là.');
    }

    
    $last_tweet = end($decoded_json);
    $last_tweet_id = $last_tweet->id_str;
    $tweets = array_merge($tweets, $decoded_json);

    //echo '<br><br>Count : '.count($tweets).'<br><br>';
    // var_dump($tweets);
    // die();

    $last_tweet_date = new DateTime($last_tweet->created_at);
    $update_time_wanted = new DateTime("now");
    $update_time_wanted->modify('-1 day');
    $update_time_wanted->setTime(18, 00, 00);

    // Si le dernier tweet date d'après "hier 18h", alors on retourne en chercher d'autres
    if ($last_tweet_date > $update_time_wanted || count($tweets) < 300 ) {
        getListTweets( $last_tweet_id );
    }

}


// Taper dans le fichier json mis de côté (plutôt que dans l'API) si on en est dev/debug
if ( isset($_GET['debug']) ) {
  $json_url = 'twitter-api.json';
  $json = file_get_contents($json_url);
  $tweets = json_decode($json);
  //var_dump($tweets);

}
// Vérification de la limite API
elseif ( $checkAPI['remaining'] > 20 || isset($_GET['force_api']) ) {
    getListTweets();
}
else {
    $tweetout = 'Les limites de l\'API sont bientôt atteintes.<br>Ajouter le paramètre <code>?force_api</code> dans l\'URL pour y dire merde.';
}



// Remettre dans l'ordre chronologique
krsort($tweets);






function jc_twitter_format ( $raw_text, $tweet = NULL ) {
    // first set output to the value we received when calling this function
    $output = $raw_text;
    // create xhtml safe text (mostly to be safe of ampersands)
    $output = htmlentities( html_entity_decode( $raw_text, ENT_NOQUOTES, 'UTF-8' ), ENT_NOQUOTES, 'UTF-8' );

    // parse urls
    if ( $tweet == NULL ) {
        // for regular strings, just create <a> tags for each url
        $pattern = '/([A-Za-z]+:\/\/[A-Za-z0-9-_]+\.[A-Za-z0-9-_:%&\?\/.=]+)/i';
        $replacement = 'YO<a href="${1}" rel="external">${1}</a>YO';
        $output = preg_replace( $pattern, $replacement, $output );
    } else {
        // for tweets, let's extract the urls from the entities object
        foreach ( $tweet->entities->urls as $url ) {
            $old_url = $url->url;
            $expanded_url = ( empty( $url->expanded_url ) ) ? $url->url : $url->expanded_url;
            $display_url = ( empty( $url->display_url ) ) ? $url->url : $url->display_url;
            /*
            //
                $mailto_link = 'mailto:charles.guillocher.altima@axa.fr?subject='.rawurlencode($tweet->text).'&body='.rawurlencode("\"".$tweet->text."\"\r\n".$expanded_url);
            //
            $replacement = '<a href="' . $mailto_link . '" rel="external">' . $display_url . '</a>';
            */
            //$replacement = '<a href="' . $expanded_url . '" rel="external">' . $display_url . '</a>';
            $replacement = '<span class="link">' . $display_url . '</span>';
            // Si c'est un quote, on supprime l'URL du tweet quoté
            if ( strpos($url->expanded_url, 'twitter.com/') ) {
                $replacement = '';
            }
            $output = str_replace( $old_url, $replacement, $output );
        }
        // let's extract the hashtags from the entities object
        foreach ( $tweet->entities->hashtags as $hashtags ) {
            $hashtag = '#' . $hashtags->text;
            //$replacement = '<a href="http://twitter.com/search?q=%23' . $hashtags->text . '" rel="external">' . $hashtag . '</a>';
            $replacement = '<span class="link">' . $hashtag . '</span>';
            $output = str_ireplace( $hashtag, $replacement, $output );
        }
        // let's extract the usernames from the entities object
        foreach ( $tweet->entities->user_mentions as $user_mentions ) {
            $username = '@' . $user_mentions->screen_name;
            //$replacement = '<a href="http://twitter.com/' . $user_mentions->screen_name . '" rel="external" title="' . $user_mentions->name . ' on Twitter">' . $username . '</a>';
            $replacement = '<span class="link">' . $username . '</span>';
            $output = str_ireplace( $username, $replacement, $output );
        }
        // if we have media attached, let's extract those from the entities as well
        if ( isset( $tweet->entities->media ) ) {
            foreach ( $tweet->entities->media as $media ) {
                $output = str_replace( $media->url, '', $output );
            }
        }
    }
    return $output;
}



function jc_twitter_format_media ( $raw_text, $tweet = NULL ) {

    $output_media = '';
    
    // if we have media attached, let's extract those from the entities as well
    if ( isset( $tweet->entities->media ) ) {
        foreach ( $tweet->entities->media as $media ) {
            $output_media .= '<img src="'.$media->media_url.'" class="media">';
        }
    }
    // if instagram !
    foreach ( $tweet->entities->urls as $url ) {
        if ( strpos($url->expanded_url, 'instagram.com') ) {
          $output_media .= '<img src="'.$url->expanded_url.'media/?size=m" class="media">';
        }
    }

    return $output_media;
    
}


// Checker si le tweet contient du média
function contains_media ($entities) {
    // Native twitter media
    if ( isset($entities->media) ) {
        return true;
    }
    // Instagram media
    elseif ( isset($entities->urls[0]) && strpos($entities->urls[0]->expanded_url, 'instagram.com') ) {
        return true;
    }
    // No media
    else {
        return false;
    }
}



$nb_tweets = 0;
foreach ($tweets as &$tweet) {

  $nb_tweets++;
  $date = new DateTime($tweet->created_at);


  $expanded_url = '[aucun lien]';
  if ( !empty($tweet->entities->urls[0]->expanded_url) ) {
    $expanded_url = $tweet->entities->urls[0]->expanded_url;
  }



  
  // Gérer les cas des Retweets / Quotes
  $inner_tweet = '';
  // Si c'est un retweet + quote
  if ( isset($tweet->retweeted_status->quoted_status) ) {
      $inner_tweet = $tweet->retweeted_status->quoted_status;
      // --> faire un traitement graphique/html tout particulier pour les retweets
  }
  // Si c'est un simple quote
  elseif ($tweet->is_quote_status) {
      $inner_tweet = $tweet->quoted_status;
  }


  $tweetout .= '<a class="tweet" href="mailto:charles.guillocher.altima@axa.fr?subject='.rawurlencode($tweet->text).'&body='.rawurlencode('"'.$tweet->text."\"\r\n> ".$expanded_url."\r\n\r\n@".$tweet->user->screen_name.' : https://twitter.com/'.$tweet->user->screen_name.'/status/'.$tweet->id_str).'">
      
      <div class="wrap-meta">
          <div class="profpic">
              <img src="'.str_replace('normal', 'bigger', $tweet->user->profile_image_url).'" class="avatar">
          </div>
          <div class="meta">
              <span class="author">@'.$tweet->user->screen_name.'</span> <span class="timestamp">'.$date->format('d/m/Y H:i').'</span>
          </div>
      </div>
      <div class="wrap-text">
          <p>'.jc_twitter_format($tweet->text, $tweet).'</p>

          '.( !empty($inner_tweet) ? '
            <div class="wrap-retweet">
                <div class="wrap-meta">
                    <div class="profpic">
                        <img src="'.str_replace('normal', 'bigger', $inner_tweet->user->profile_image_url).'" class="avatar">
                    </div>
                    <div class="meta">
                        <span class="author">@'.$inner_tweet->user->screen_name.'</span> <span class="timestamp">'.$date->format('d/m/Y H:i').'</span>
                    </div>
                </div>
                <div class="wrap-text">
                    <p>'.jc_twitter_format($inner_tweet->text, $inner_tweet).'</p>
                </div>
            </div>'
              :
            '' ).'

      </div>
      '.( contains_media($tweet->entities) ? '
          <div class="wrap-media">
              '.jc_twitter_format_media($tweet->text, $tweet).'
          </div>'
            :
          '' ).'
      <div class="clearfix"></div>
  </a>
  ';

  //$tweetout .= '';
   
    /*
   foreach ($tweet->entities->urls as $url) {
      echo 'preg_replace("/"'.addslashes($url->url).'"/",
        \'<a href="'.addslashes($url->url).'" target="_blank">'.addslashes($url->display_url).'</a>,
        '.addslashes($tweet->text);

      $tweet->text = preg_replace("/".addslashes($url->url)."/",
        '<a href="'.addslashes($url->url).'" target="_blank">'.addslashes($url->display_url).'</a>',
        $tweet->text
      );
      echo $tweet->text; die();
   }
   $tweetout .= '@'.$tweet->user->screen_name.' ('.$tweet->created_at.') : '.$tweet->text;
   //$tweetout .= preg_replace("/(http:\/\/|(www\.))(([^\s<]{4,68})[^\s<]*)/", '<a href="http://$2$3" target="_blank">$1$2$4</a>', $tweet->text);
   //$tweetout = preg_replace("/@(\w+)/", "<a href=\"http://www.twitter.com/\\1\" target=\"_blank\">@\\1</a>", $tweetout);
   //$tweetout = preg_replace("/#(\w+)/", "<a href=\"http://search.twitter.com/search?q=\\1\" target=\"_blank\">#\\1</a>", $tweetout);
   $tweetout .= '<br><br>';
   */
}

?>
<html>
<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- <meta name="apple-mobile-web-app-capable" content="yes"> -->
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <title>Vespers</title>
  <link rel="stylesheet" type="text/css" href="public/css/base.css">
  <link href='https://fonts.googleapis.com/css?family=Lato:400,100,300,700,900' rel='stylesheet' type='text/css'>
  <link rel="apple-touch-icon" href="public/images/icon.png">
  <link rel="apple-touch-startup-image" href="public/images/startup.png">


</head>
<body>
<div class="page">
  
  <?php echo '
  
    <div class="tweet">
        <div class="wrap-text">
            '.$checkAPI['html'].'<br>Nb de tweets : '.$nb_tweets.' – Nb d\'appels API : '.$nb_calls_API.'
        </div>
    </div>

    '. $tweetout;

  ?>

</div>
</body>
</html>