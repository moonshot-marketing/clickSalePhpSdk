<?php

/*
    Affiliate Project
    generic Affiliate post data script in php

    @created 2017-04-19
    @last modified 2017-06-19
    @copyright moonshot marketing
    @author Itay Zagron
*/


date_default_timezone_set("UTC");

define('AFFILIATE_API_VERSION', "1.0.0");
define('AFFILIATE_API_HOST', "https://api.valuetrackbi.com/sales/");



class AffiliatePostDataHandler {
    function __construct() {
        $this->endpoints = array(
            "sales" => array(
            "CT" => "application/json",
            "URL" => AFFILIATE_API_HOST.AFFILIATE_API_VERSION."/"
            ),
            "error" => array(
            "CT" => "application/json",
            "URL" => AFFILIATE_API_HOST.AFFILIATE_API_VERSION."/error/"
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
                $this->err_handler->handel(401, "unrecognize endpoint.", $dataobj);
            }
        } catch (Exception $e) {
            $this->err_handler->handel(400, $e->getMessage(), $dataobj);
        }
    }
}


class AffiliateErrorHandler {

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
        $post_handler = new AffiliatePostDataHandler;

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


class Affiliate {

    public
        $event,
        $msvtid,
        $provider,
        $revenue;

    function __construct($msvtid = null) {
        $this->msvtid = $msvtid;

        $this->err_handler = new AffiliateErrorHandler;
        $this->post_handler = new AffiliatePostDataHandler;

    }

    private function build_object($provider, $event, $revenue) {
        /*
            function to build final object
            :param provider: {string} provider
            :param event: {string} event
            :param revenue: {float} revenue
            :return: {array}
        */
        try {
            $data_obj = array();

            $data_obj["id"] = $this->msvtid;
            $data_obj["provider"] = $provider;
            $data_obj["event"] = $event;
            $data_obj["revenue"] = $revenue;

            return $data_obj;
        } catch (Exception $e) {
            $this->err_handler->handel(402, $e->getMessage(), $data_obj);
            return null;
        }

    }

    public function set_event( $event ) {
        $this->event = $event;
    }

    public function set_provider( $provider ) {
        $this->provider = $provider;
    }

    public function set_revenue( $revenue ) {
        $this->revenue = $revenue;
    }

    public function execute() {

        if (empty($this->provider)) {
            $this->err_handler->handel(403, "no provider set.", array('id' => $this->msvtid));
            return false;
        } elseif (empty($this->event)) {
            $this->err_handler->handel(404, "no event set.", array('id' => $this->msvtid));
            return false;
        } else {
            if (empty($this->revenue)) {
              $this->revenue = 0.0;
            }
            // build sale object
            $obj = $this->build_object($this->provider, $this->event, $this->revenue);
            if ($obj != null) {
                if ($this->post_handler->post('sales', $obj) != null) {
                    return true;
                }
                return false;
            } else {
                $this->err_handler->handel(405, "object is null", array('id' => $this->msvtid));
                return false;
            }

        }

    }
}

?>
