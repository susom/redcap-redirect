<?php

/**
 * REDCap REDIRECT
 * 
 * Author: Andrew Martin (andy123@stanford.edu)
 * Additions by: Tony Jin (Tony.Jin@stonybrookmedicine.edu)
 *              Andy Arenson (aarenson@iu.edu)
 *
 * This script is used in conjunction with an apache mod_rewrite rule to automatically fix outdated REDCap URLs
 *
 * It assumes the use of Apache Server 2.4 or higher and the mod_rewrite package.
 * In my case, I executed: a2enmod rewrite as part of the web server startup
 *
 * The apache configuration can be inserted in a .htaccess file at the root of REDCap or as part of the site's .conf
 * and should look as follows:

     <IfModule mod_rewrite.c>
        RewriteEngine on
        # Check if the requested file does not exist on the server
        RewriteCond %{DOCUMENT_ROOT}/$1 !-f
        RewriteCond %{DOCUMENT_ROOT}/$1 !-d
        # Check that the requested URI looks like a REDCap URI
        RewriteCond %{REQUEST_URI} "^.*\/redcap_v(\d+\.\d+\.\d+)\/.*$"
        # Redirect to this script to handle the version substitution
        RewriteRule "^(.+)$"     "/redcap_redirect.php"   [PT,L,NS]
     </IfModule>

 * Note: if your redcap server is not installed in the DOCUMENT_ROOT, then you may need to modify the RewriteRule line
 *       and add the path to the redcap_redirect.php script, such as: "/redcap/redcap_redirect.php"
 *
 * Note: this redirect only takes place for redcap_vx.y.z urls in an attempt to reduce server load by not instantiating
 *       php when your server might be getting probed or scanned which could adversely affect server performance
 */

define('NOAUTH',true);
require_once("redcap_connect.php");

/* @var string $homepage_contact_email */
/* @var string $redcap_version */
global $homepage_contact_email, $redcap_version;

$requestUri = filter_var($_SERVER['REQUEST_URI'], FILTER_SANITIZE_URL);

// Check the redirectURL for a redcap version - https://regex101.com/r/jisap2/1
$re = '/^(.*\/redcap_v)(\d+\.\d+\.\d+)(\/.*?)(\?.*)*$/';
preg_match($re, $requestUri, $uriMatches);
$uriVersion = empty($uriMatches[2]) ? NULL : $uriMatches[2];

// See if we have a version in the url and it is not current
if (!empty($uriVersion) && ($uriVersion !== $redcap_version)) {

    // Rebuild the new url with the current db version
    $newUrl = $uriMatches[1] . $redcap_version . $uriMatches[3];

    // Build the server path to the url
    $newUrlPath = $_SERVER['DOCUMENT_ROOT'] . $newUrl;

    // Check if path is valid
    if (file_exists($newUrlPath)) {
        // Let's build the redirect uri (which includes the query string)
        $newUri = $newUrl . $uriMatches[4];
        // Plugin::log("Redirecting to new uri: " . $newUri);
        redirect($newUri);
        die();
    } else {
        // Plugin::log("After updating version, file in URL doesn't exist: " . $newUrlPath);
    }
}

// Generate a nice 404 Page
http_response_code(404);
$HtmlPage = new HtmlPage();
$HtmlPage->PrintHeaderExt();
$fullUrl = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['SERVER_NAME'] . $requestUri;
$mailUrl = "mailto:$homepage_contact_email?subject=Invalid-404-Url&body=" .
    rawurlencode(htmlspecialchars_decode( "The following url was not found:\n\n" . $fullUrl . "\n\n"));

?>
    <div class="jumbotron">
        <table>
            <tbody>
                <tr>
                    <td>
                        <img class="pr-4" src="<?php echo APP_PATH_IMAGES . "app_logo.png" ?>"/>
                    </td>
                    <td>
                        <h1 class="d-inline-block display-4">Page Not Found</h1>
                        <p class="d-inline-block lead">The requested URL was not found on this server:</p>
                        <code class="d-inline-block text-secondary"><?php echo $fullUrl ?></code>
                    </td>
                </tr>
            </tbody>
        </table>
        <hr class="my-4">
        <p class="lead">
            <a class="btn btn-danger text-white btn-lg mr-2" href="/" role="button">Return to Home Page</a>
            <a class="btn btn-danger text-white btn-lg" target="_BLANK" href="<?php echo $mailUrl ?>" role="button">Contact Us</a>
        </p>
    </div>
<?php
