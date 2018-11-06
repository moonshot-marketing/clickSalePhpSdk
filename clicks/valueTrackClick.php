<?php

date_default_timezone_set("UTC");

define('API_HOST', "https://vt-production.valuetrackbi.com/api/click");
define('MSVT_COOKIE_NAME', "msvt_cid");
define('MSVT_COOKIE_TTL', strtotime('+30 days')); // one month cookie

class CookiesHandler
{

    public function get()
    {
        /*
        get specific cookie
        :param cookieName: {string} the cookie name
        :return: {string} cookie value
         */
        try {

            if (!isset($_COOKIE[MSVT_COOKIE_NAME])) {
                return null;
            } else {
                return $_COOKIE[MSVT_COOKIE_NAME];
            }
        } catch (Exception $e) {
            //
        }
    }

    public function put($cookieValue)
    {
        /*
        put new cookie
        :param cookieValue: {string} the cookie value
        :return:
         */
        try {
            setcookie(MSVT_COOKIE_NAME, $cookieValue, MSVT_COOKIE_TTL, "/");
        } catch (Exception $e) {
            //
        }
    }

    public function delete()
    {
        /*
        remove old cookie
        :return:
         */
        try {
            $this->put(MSVT_COOKIE_NAME, "", time() - 3600, "/");
        } catch (Exception $e) {
            //
        }
    }

    public function isCookiesEnabled()
    {
        /*
        check if cookies are enabled
        :return: {boolean}
         */
        if (count($_COOKIE) > 0) {
            return true;
        } else {
            return false;
        }
    }
}

class PostDataHandler
{

    public function exec($dataObject)
    {
        /*
        send post request
        :param dataObject: {string} data object to send
        :return: respost {array}
         */
        try {
            $curl = curl_init();
            curl_setopt($curl, CURLOPT_URL, API_HOST);

            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-type: application/json',
            ));
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($dataObject));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            $response = curl_exec($curl);
            curl_close($curl);

            return $response;
        } catch (Exception $e) {
            //
        }
    }
}

class ClickData
{
    private $projectId,
    $finalUrl,
    $referrerFinalUrl,
    $userAgent,
    $trackerVersion,
        $siteParameters;

    public function __construct(string $projectId, array $siteParameters)
    {
        $this->projectId = $projectId;
        $this->finalUrl = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $this->referrerFinalUrl = $this->getReferrer();
        $this->userAgent = $this->getUserAgent();
        $this->trackerVersion = "php/v1.0";
        $this->siteParameters = $siteParameters;
    }

    private function getReferrer()
    {
        /*
        get referrer from url
        :return: {string} or null
         */
        if (isset($_SERVER['HTTP_REFERER'])) {
            return $_SERVER['HTTP_REFERER'];
        }

        return null;
    }

    private function getUserAgent()
    {
        /*
        get user angent
        :return: {string} or null
         */
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            return $_SERVER['HTTP_USER_AGENT'];
        }

        return null;
    }

    public function buildObject()
    {
        $dataObject = array();

        $dataObject["projectId"] = $this->projectId;
        $dataObject["finalUrl"] = $this->finalUrl;
        $dataObject["referrerFinalUrl"] = $this->referrerFinalUrl;
        $dataObject["userAgent"] = $this->userAgent;
        $dataObject["trackerVersion"] = $this->trackerVersion;
        $dataObject["siteParameters"] = $this->siteParameters;

        return $dataObject;
    }

}

class ValueTrackClickHandler
{

    public $projectName,
        $valueTrackId;

    public function __construct(string $projectName = null)
    {
        $this->projectName = $projectName;
        $this->valueTrackId = 0;

        $this->excludeParameters = array();
        $this->includeParameters = array();
        $this->siteParameters = array();

        $this->postHandler = new PostDataHandler();
        $this->cookieHandler = new CookiesHandler();

        $this->urlQueryParameters = array();
        parse_str(parse_url(
            (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"
        )['query'], $this->urlQueryParameters);
    }

    private function hasQueryParameters()
    {
        /*
        check if url as query params
        :return: {boolean}
         */

        if (isset($this->urlQueryParameters) && (count($this->urlQueryParameters) > 0)) {
            return true;
        }

        return false;
    }

    public function setProjectName($projectName)
    {
        /*
        set project name
        :param project name: {string} the project name
        :return:
         */
        $this->projectName = $projectName;
    }

    public function addExcludeParameter($key)
    {
        /*
        added exclude parameter
        :param key: {string} parameter to exclude
        :return:
         */
        array_push($this->excludeParameters, $key);
    }

    public function addIncludeParameter($key)
    {
        /*
        added include parameter
        :param key: {string} parameter to include
        :return:
         */
        array_push($this->includeParameters, $key);
    }

    public function addSiteParameter($key, $val)
    {
        /*
        added site parameter
        :param key: {string} key of site parameter
        :param val: {string} value of site parameter
        :return:
         */

        $this->siteParameters[$key] = $val;
    }

    private function detectReferrer($referrer)
    {
        /*
        function to detect user referrer
        :param referrer: {string} referrer
        :return ref: {string}
         */
        $ref;
        if (isset($referrer)) {
            if (strpos($referrer, '.google.') !== false) {
                $ref = 'g_organic';
            } elseif (strpos($referrer, '.bing.')) {
                $ref = 'b_organic';
            } elseif (strpos($referrer, '.yahoo.')) {
                $ref = 'y_organic';
            } else {
                $ref = 'direct';
            }
        } else {
            $ref = 'direct';
        }

        return $ref;
    }

    public function execute()
    {

        // first check if has query params
        if ($this->hasQueryParameters()) {

            if (!isset($this->projectName)) {
                die("Project name is missing!");
            }

            $clickObject = new ClickData($this->projectName, $this->siteParameters);

            // check include parameters
            // this script will continue only if user has one of the include parameters

            if (count($this->includeParameters) > 0) {

                foreach ($this->includeParameters as $k => $v) {

                    if (array_key_exists($v, $this->urlQueryParameters)) {
                        continue;
                    } else {
                        if ($this->cookieHandler->get()) {
                            return $this->cookieHandler->get();
                        }

                        $ref = $this->detectReferrer($clickObject["referrerFinalUrl"]);
                        $this->cookieHandler->put($ref);
                        return $ref;
                    }
                }
            }

            // check if query params has exclude params, abort.

            foreach ($this->urlQueryParameters as $k => $v) {
                if (in_array($k, $this->excludeParameters)) {
                    // has a cookie
                    if ($this->cookie_handler->get()) {
                        return $this->cookie_handler->get();
                    }

                    $ref = $this->detectReferrer($clickObject["referrerFinalUrl"]);
                    $this->cookieHandler->put($ref);
                    return $ref;
                }
            }

            // clear old cookie
            $this->cookieHandler->delete();

            // send data
            $response = json_decode($this->postHandler->exec($clickObject->buildObject()));

            // response
            if (property_exists($response, "success")) {

                if ($response->success) {

                    $id = $response->id;
                    $this->cookieHandler->put($id);
                    return $id;

                } else {

                    $ref = $this->detectReferrer($clickObject["referrerFinalUrl"]);
                    $this->cookieHandler->put($ref);
                    return $ref;

                }

            } else {
                $ref = $this->detectReferrer($clickObject["referrerFinalUrl"]);
                $this->cookieHandler->put($ref);
                return $ref;
            }

            $ref = $this->detectReferrer($clickObject["referrerFinalUrl"]);
            $this->cookieHandler->put($ref);
            return $ref;

        } else {

            // if has cookie

            if ($this->cookieHandler->get()) {
                return $this->cookieHandler->get();
            }

            $ref = $this->detectReferrer($clickObject["referrerFinalUrl"]);
            $this->cookieHandler->put($ref);
            return $ref;
        }
    }
}
