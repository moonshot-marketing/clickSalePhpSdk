<?php

/*
    ValueTrack Project
    generic valuetrack post data script in php

    @created 2016-12-07
    @last modified 2017-06-19
    @copyright moonshot marketing
    @author Itay Zagron
*/


date_default_timezone_set("UTC");

define('API_VERSION', "4.0.0");
define('API_HOST', "https://api.valuetrackbi.com/");
define('MSVT_COOKIE_PREFIX', "msvt");
define('MSVT_COOKIE_TTL', strtotime( '+30 days' )); // one month cookie
define('MSVT_COOKIE_NAME', "cid");
define('MSVT_ID_PARAM_NAME', "mvt");


class CookiesHandler {

    function __construct(string $full_url = null) {
        $this->full_url = $full_url;
    }

    public function get($cookie_name){
        /*
            get specific cookie
            :param cookie_name: {string} the cookie name
            :return: {string} cookie value
        */
        try {
            $cookie_name = MSVT_COOKIE_PREFIX . "_" . $cookie_name;

            if (!isset($_COOKIE[$cookie_name])) {
                return null;
            } else {
                return $_COOKIE[$cookie_name];
            }
        } catch (Exception $e) {
            $this->err_handler->handel(202, $e->getMessage(), $this->full_url);
        }
    }

    public function put($cookie_name, $cookie_value, $cookie_ttl) {
        /*
            put new cookie
            :param cookie_name: {string} the cookie name
            :param cookie_value: {string} the cookie value
            :param cookie_ttl: {time} the cookie time to leave
            :return:
        */
        try {
            $cookie_name = MSVT_COOKIE_PREFIX . "_" . $cookie_name;
            setcookie($cookie_name, $cookie_value, $cookie_ttl, "/");
        } catch (Exception $e) {
            $this->err_handler->handel(201, $e->getMessage(), $this->full_url);
        }
    }

    public function delete_all() {
        /*
            remove all namespace cookies
            :return:
        */
        try {
            foreach ($_COOKIE as $k => $c) {
                if (strpos($k, MSVT_COOKIE_PREFIX) !== false) {
                    $this->put($k, "", time() - 3600, "/");
                }
            }
        } catch (Exception $e) {
            $this->err_handler->handel(203, $e->getMessage(), $this->full_url);
        }
    }

    public function cookies_enabled() {
        /*
            check if cookies are enabled
            :return: {boolean}
        */
        if(count($_COOKIE) > 0) {
            return true;
        } else {
            return false;
        }
    }
}


class PostDataHandler {
    function __construct() {
        $this->endpoints = array(
            "valuetrack" => array(
            "CT" => "application/json",
            "URL" => API_HOST.API_VERSION."/"
            ),
            "error" => array(
            "CT" => "application/json",
            "URL" => API_HOST.API_VERSION."/error/"
            )
        );
    }

    public function post($endpoint, $dataobj) {
        /*
            send post request
            :param endpoint: {string} name of the endpoint
            :param dataobj: {string} data object to send
            :return: respost {array}
        */
        try {
            if (array_key_exists($endpoint, $this->endpoints)) {

                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $this->endpoints[$endpoint]["URL"]);

                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                    'Content-type:' . $this->endpoints[$endpoint]["CT"]
                ));
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($dataobj));
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

                $response = curl_exec($curl);
                curl_close($curl);

                return $response;

            } else {
                $this->err_handler->handel(301, "unrecognize endpoint.", $dataobj);
            }
        } catch (Exception $e) {
            $this->err_handler->handel(300, $e->getMessage(), $dataobj);
        }
    }
}


class ErrorHandler {

    public $error_counter;

    function __construct() {
        $this->error_counter = 0;
    }

    private function to_server($error) {
        /*
            try to post error to remote server
            :param error: {string} error
            :return: boolean
        */

        // initial new post handler
        $post_handler = new PostDataHandler;

        $response = json_decode($post_handler->post("error", $error), true);

        if (array_key_exists("success", $response)) {
            return true;
        }

        return false;
    }

    public function handel($errid, $err, $data) {
        /*
            handel error, first try to send to remote, if there is a problem
            send to local
            :param errid: the error id
            :param err: the error
            :param data: data object
            :return:
        */


        $error = array(
            "error_id" => $errid,
            "msg" => $err,
            "data" => $data
        );

        $this->to_server($error);
        $this->error_counter++;
    }
}


class ValueTrack {

    public
        $project_name,
        $vt_id;

    function __construct(string $project_name = null) {
        $this->project_name = $project_name;
        $this->vt_id = 0;

        $this->full_url = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $this->exclude_params = array();
        $this->include_params = array();
        $this->site_params = array();
        $this->data_obj = array();

        $this->err_handler = new ErrorHandler($this->full_url);
        $this->post_handler = new PostDataHandler;
        $this->cookie_handler = new CookiesHandler();

        register_shutdown_function(function() {
            $this->shutDownFunction();
        });
    }

    private function shutDownFunction() {
        /*
            handel shutdown
            :return:
        */
        $error = error_get_last();

        if ($error["type"] === E_ERROR) {
            $this->err_handler->handel(0, implode(",", $error));
        }

    }

    private function parse_url($url) {
        /*
            parse url component
            :param url: {string} full url
            :return: array
        */
        return parse_url($url);
    }

    private function query_to_array($query_params) {
        /*
            parse query parameters
            :param query_params: {string} url query parameters
            :return: array
        */

        $parse_array = array();
        parse_str($query_params, $parse_array);
        return $parse_array;
    }

    private function build_object($id) {
        /*
            function to build final object
            :param id: {string} custom id
            :return: {array}
        */
        try {
            $parse_url = $this->parse_url($this->full_url);
            $query_params = $this->query_to_array($parse_url["query"]);
            $data_obj = array();

            $data_obj["id"] = $id;
            $data_obj["source"] = $this->detect_source($query_params, $this->get_referrer());
            $data_obj["slug"] = $parse_url["scheme"] . "://" . $parse_url["host"] . $parse_url["path"];
            $data_obj["final_url"] = $this->full_url;
            $data_obj["referrer_host"] = $this->get_referrer();
            $data_obj["url_params"] = $query_params;
            $data_obj["site_params"] = $this->site_params;

            return $data_obj;
        } catch (Exception $e) {
            $this->err_handler->handel(102, $e->getMessage(), $this->full_url);
            return null;
        }

    }

    private function detect_source($query_params, $referrer) {
        /*
            function to detect user source
            :param query_params: {array} all query parameters
            :return source: {string}
        */

        $source;

        if (array_key_exists("gclid", $query_params) ||
            $referrer == 'g_organic') {
                $source = "google";
        } elseif (array_key_exists("utm_source", $query_params)) {
            $source = $query_params["utm_source"];
        } else {
            $source  = "unknownSource";
        }

        return $source;
    }

    private function detect_referrer($referrer) {
        /*
            function to detect user referrer
            :param referrer: {string} referrer
            :return ref: {string}
        */

        $ref;

        if(isset($referrer)) {
			if(strpos($referrer, '.google.') !== false) {
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

    private function to_base64($custom_id) {
        /*
            convert string to base 64
            :param custom_id: {string} string to convert
            :return: string
        */
        return base64_encode($custom_id);
    }

    private function utc_timestamp() {
        /*
            create utc timestamp
            :return: int
        */
        return time();
    }

    private function rnd_number($min, $max) {
        /*
            create random number
            :param min: {int} min number
            :param max: {int} max number
            :return: int
        */
        return mt_rand((int)$min, (int)$max);
    }

    private function build_custom_id() {
        /*
            generate custom id
            :return: string
        */

        try {
            // check if project_name defined
            if ($this->project_name == "") {
                $this->project_name = "unknownApp";
            }

            // concat parameters
            $custom_id = $this->project_name
                . "--"
                . $this->utc_timestamp()
                . "--"
                . $this->rnd_number(0, 20000000);

            // return base64 concatenate string
            return $this->to_base64($custom_id);
        } catch (Exception $e) {
            $this->err_handler->handel(101, $e->getMessage(), $this->full_url);
            return null;
        }

    }

    private function has_query_params() {
        /*
            check if url as query params
            :return: {boolean}
        */
        if(isset($this->parse_url($this->full_url)["query"])) {
            if (count($this->parse_url($this->full_url)["query"]) > 0) {
                return true;
            }
        }

        return false;
    }

    public function get_referrer() {
        /*
            get referrer from url
            :return: {string} or null
        */
        if(isset($_SERVER['HTTP_REFERER'])) {
            return parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
        }

        return null;
    }

    public function set_project_name($project_name) {
        /*
            set project name
            :param project name: {string} the project name
            :return:
        */
        $this->project_name = $project_name;
    }

    public function add_exclude_param($param) {
        /*
            added exclude param
            :param exclude_params: {string} param to add to exclusion
            :return:
        */
        array_push($this->exclude_params, $param);
    }

    public function add_include_param($param) {
        /*
            added include param
            :param include_params: {string} param to add to include
            :return:
        */
        array_push($this->include_params, $param);
    }

    public function add_site_param($key, $val) {
        /*
            added site param
            :param key: {string} key of site param
            :param val: {string} value of site param
            :return:
        */

        $this->site_params[$key] = $val;
    }

    public function execute() {

        // first check if has query params
        if ($this->has_query_params()) {

            // generate id, and data object
            $id = $this->build_custom_id();
            if ($id == null) {
                $ref = $this->detect_referrer($this->get_referrer());
                $this->cookie_handler->put(MSVT_COOKIE_NAME, $ref, MSVT_COOKIE_TTL);
                return $ref;
            }

            $obj = $this->build_object($id);
            if ($obj == null) {
                $ref = $this->detect_referrer($this->get_referrer());
                $this->cookie_handler->put(MSVT_COOKIE_NAME, $ref, MSVT_COOKIE_TTL);
                return $ref;
            }

            // check include params
            // this script will continue only if user has one of the include params

            if (count($this->include_params) > 0) {

                foreach($this->include_params as $k => $v) {

                    if (array_key_exists($v, $obj["url_params"])) {
                        continue;
                    } else {
                        if ($this->cookie_handler->get(MSVT_COOKIE_NAME)) {
                            return $this->cookie_handler->get(MSVT_COOKIE_NAME);
                        }

                        $ref = $this->detect_referrer($this->get_referrer());
                        $this->cookie_handler->put(MSVT_COOKIE_NAME, $ref, MSVT_COOKIE_TTL);
                        return $ref;
                    }

                }

            }

            // check if query params has exclude params, abort.
            foreach ($obj["url_params"] as $k => $v) {
                if (in_array($k, $this->exclude_params)) {

                    // if has cookie
                    if ($this->cookie_handler->get(MSVT_COOKIE_NAME)) {
                        return $this->cookie_handler->get(MSVT_COOKIE_NAME);
                    }

                    $ref = $this->detect_referrer($this->get_referrer());
                    $this->cookie_handler->put(MSVT_COOKIE_NAME, $ref, MSVT_COOKIE_TTL);
                    return $ref;
                }
            }

            // check if custom id param in query, return value from query param;
            if (array_key_exists(MSVT_ID_PARAM_NAME, $obj["url_params"])) {
                return $obj["url_params"][MSVT_ID_PARAM_NAME];
            }

            // clear old cookie
            $this->cookie_handler->delete_all();

            // send data
            $response = json_decode($this->post_handler->post("valuetrack", $obj), true);

            // handel response
            if (array_key_exists("success", $response)) {

                if ($response["success"]) {

                    $id = $response["id"];
                    $this->cookie_handler->put(MSVT_COOKIE_NAME, $id, MSVT_COOKIE_TTL);
                    return $id;

                } else {

                    $this->err_handler->handel(303, $response, $obj);
                    $ref = $this->detect_referrer($this->get_referrer());
                    $this->cookie_handler->put(MSVT_COOKIE_NAME, $ref, MSVT_COOKIE_TTL);
                    return $ref;

                }
            } else {

                $this->err_handler->handel(302, $response, $obj);
                $ref = $this->detect_referrer($this->get_referrer());
                $this->cookie_handler->put(MSVT_COOKIE_NAME, $ref, MSVT_COOKIE_TTL);
                return $ref;

            }

            $ref = $this->detect_referrer($this->get_referrer());
            $this->cookie_handler->put(MSVT_COOKIE_NAME, $ref, MSVT_COOKIE_TTL);
            return $ref;

        } else {

            // if has cookie

            if ($this->cookie_handler->get(MSVT_COOKIE_NAME)) {
                return $this->cookie_handler->get(MSVT_COOKIE_NAME);
            }

            $ref = $this->detect_referrer($this->get_referrer());
            $this->cookie_handler->put(MSVT_COOKIE_NAME, $ref, MSVT_COOKIE_TTL);
            return $ref;
        }
    }
}

?>
