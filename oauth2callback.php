<?php

/**
 * Library Requirements
 *
 * 1. Install composer (https://getcomposer.org)
 * 2. On the command line, change to this directory (api-samples/php)
 * 3. Require the google/apiclient library
 *    $ composer require google/apiclient:~2.0
 */
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    throw new \Exception('please run "composer require google/apiclient:~2.0" in "' . __DIR__ . '"');
}

require_once __DIR__ . '/vendor/autoload.php';
session_start();

/*
 * You can acquire an OAuth 2.0 client ID and client secret from the
 * {{ Google Cloud Console }} <{{ https://cloud.google.com/console }}>
 * For more information about using OAuth 2.0 to access Google APIs, please see:
 * <https://developers.google.com/youtube/v3/guides/authentication>
 * Please ensure that you have enabled the YouTube Data API for your project.
 */
//
// {
//     "web": {
//     "client_id": "977781273552-sgvou5j8c32vel2h17hcospns4gp5ahh.apps.googleusercontent.com",
// 	    "project_id": "nimble-service-176605",
// 	    "auth_uri": "https://accounts.google.com/o/oauth2/auth",
// 	    "token_uri": "https://accounts.google.com/o/oauth2/token",
// 	    "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
// 	    "client_secret": "LmSbka93XgbfKH8AGBt6Kbnr",
// 	    "redirect_uris": [
//         "https://youtube.3ddysj.com/oauth2callback"
//     ],
// 	    "javascript_origins": [
//         "https://youtube.3ddysj.com"
//     ]
// 	  }
// 	}





$OAUTH2_CLIENT_ID = '977781273552-sgvou5j8c32vel2h17hcospns4gp5ahh.apps.googleusercontent.com';
$OAUTH2_CLIENT_SECRET = 'LmSbka93XgbfKH8AGBt6Kbnr';

$client = new Google_Client();
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);
$client->setScopes('https://www.googleapis.com/auth/youtube');
$redirect = filter_var('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
    FILTER_SANITIZE_URL);
$client->setRedirectUri($redirect);

// Define an object that will be used to make all API requests.
$youtube = new Google_Service_YouTube($client);

// Check if an auth token exists for the required scopes
$tokenSessionKey = 'token-' . $client->prepareScopes();

phpLog('进来了1----------------');
phpLog($_GET);
phpLog($_SESSION);

if (isset($_GET['code'])) {

    phpLog('进来了2----------------');

    if ((int)$_SESSION['state'] !== (int)$_GET['state']) {
        die('The session state did not match.');
    }

    phpLog('进来了3----------------');
    phpLog('1111');

    $client->authenticate($_GET['code']);

    phpLog('进来了4----------------');

    $_SESSION[$tokenSessionKey] = $client->getAccessToken();

    phpLog('进来了5----------------');
    header('Location: ' . $redirect);
}

phpLog('进来了6----------------');
phpLog($_SESSION);

if (isset($_SESSION[$tokenSessionKey])) {
    $client->setAccessToken($_SESSION[$tokenSessionKey]);
}

// Check to ensure that the access token was successfully acquired.
if ($client->getAccessToken()) {
    $htmlBody = '';
    try {
        // REPLACE this value with the path to the file you are uploading.
        $videoPath = "/path/to/index.mp4";

        // Create a snippet with title, description, tags and category ID
        // Create an asset resource and set its snippet metadata and type.
        // This example sets the video's title, description, keyword tags, and
        // video category.
        $snippet = new Google_Service_YouTube_VideoSnippet();
        $snippet->setTitle("测试 title");
        $snippet->setDescription("测试 description");
        $snippet->setTags(array("tag1", "tag2"));

        // Numeric video category. See
        // https://developers.google.com/youtube/v3/docs/videoCategories/list
        $snippet->setCategoryId("22");

        // Set the video's status to "public". Valid statuses are "public",
        // "private" and "unlisted".
        $status = new Google_Service_YouTube_VideoStatus();
        $status->privacyStatus = "public";

        // Associate the snippet and status objects with a new video resource.
        $video = new Google_Service_YouTube_Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        // Specify the size of each chunk of data, in bytes. Set a higher value for
        // reliable connection as fewer chunks lead to faster uploads. Set a lower
        // value for better recovery on less reliable connections.
        $chunkSizeBytes = 1 * 1024 * 1024;

        // Setting the defer flag to true tells the client to return a request which can be called
        // with ->execute(); instead of making the API call immediately.
        $client->setDefer(true);

        // Create a request for the API's videos.insert method to create and upload the video.
        $insertRequest = $youtube->videos->insert("status,snippet", $video);

        // Create a MediaFileUpload object for resumable uploads.
        $media = new Google_Http_MediaFileUpload(
            $client,
            $insertRequest,
            'video/*',
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize(filesize($videoPath));


        // Read the media file and upload it chunk by chunk.
        $status = false;
        $handle = fopen($videoPath, "rb");
        while (!$status && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $status = $media->nextChunk($chunk);
        }

        fclose($handle);

        // If you want to make other calls after the file upload, set setDefer back to false
        $client->setDefer(false);


        $htmlBody .= "<h3>Video Uploaded</h3><ul>";
        $htmlBody .= sprintf('<li>%s (%s)</li>',
            $status['snippet']['title'],
            $status['id']);

        $htmlBody .= '</ul>';

    } catch (Google_Service_Exception $e) {
        $htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>',
            htmlspecialchars($e->getMessage()));
    } catch (Google_Exception $e) {
        $htmlBody .= sprintf('<p>An client error occurred: <code>%s</code></p>',
            htmlspecialchars($e->getMessage()));
    }

    $_SESSION[$tokenSessionKey] = $client->getAccessToken();
} else if ($OAUTH2_CLIENT_ID == 'REPLACE_ME') {
    $htmlBody = <<<END
  <h3>Client Credentials Required</h3>
  <p>
    You need to set <code>\$OAUTH2_CLIENT_ID</code> and
    <code>\$OAUTH2_CLIENT_ID</code> before proceeding.
  <p>
END;
} else {
    // If the user hasn't authorized the app, initiate the OAuth flow
    $state = mt_rand();
    $client->setState($state);
    $_SESSION['state'] = $state;

    $authUrl = $client->createAuthUrl();
    $htmlBody = <<<END
  <h3>Authorization Required</h3>
  <p>You need to <a href="$authUrl">authorize access</a> before proceeding.<p>
END;
}



/**
 * 调试时候用来写日志文件的函数
 *
 * @param filename 保存的文件名
 *
 * @author <23585472@qq.com>
 */
function phpLog ($str)
{
    $time = "\n\t" . date('Y-m-d H:i:s', time()) . "------------------------------------------------------------------------------------\n\t";
    if (is_array($str)) {
        $str = var_export($str, true);
    }
    file_put_contents('./log.php', $time . $str, FILE_APPEND);
}

?>

<!doctype html>
<html>
<head>
    <title>Video Uploaded</title>
</head>
<body>
<?= $htmlBody ?>
</body>
</html>
