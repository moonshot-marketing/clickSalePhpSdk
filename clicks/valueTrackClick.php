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
    private $website,
    $finalUrl,
    $referrerFinalUrl,
    $userAgent,
    $trackerVersion,
        $siteParameters;

    public function __construct(string $website, array $siteParameters)
    {
        $this->website = $website;
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

        $dataObject["website"] = $this->website;
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

    public $website,
        $valueTrackId;

    public function __construct(string $website = null)
    {
        $this->website = $website;
        $this->valueTrackId = 0;

        $this->excludeParameters = array();
        $this->includeParameters = array();
        $this->siteParameters = array();

        $this->postHandler = new PostDataHandler();
        $this->cookieHandler = new CookiesHandler();

        $this->urlQueryParameters = array();
        $urlElements = parse_url(
            (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]"
        );
        if (isset($urlElements) && isset($urlElements["query"])) {
            parse_str($urlElements['query'], $this->urlQueryParameters);
        }

        $this->referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : "unknown";
        $this->ref = $this->detectReferrer($this->referrer);

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

    public function setWebsite($website)
    {
        /*
        set website name
        :param website name: {string} the website name
        :return:
         */
        $this->website = $website;
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

            if (!isset($this->website)) {
                die("website name is missing!");
            }

            $clickObject = new ClickData($this->website, $this->siteParameters);

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

                        $this->cookieHandler->put($this->ref);
                        return $this->ref;
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

                    $this->cookieHandler->put($this->ref);
                    return $this->ref;
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
                    $this->cookieHandler->put($this->ref);
                    return $this->ref;
                }

            } else {
                $this->cookieHandler->put($this->ref);
                return $this->ref;
            }

        } else {

            // if has cookie

            if ($this->cookieHandler->get()) {
                return $this->cookieHandler->get();
            }

            $this->cookieHandler->put($this->ref);
            return $this->ref;
        }
    }
}
