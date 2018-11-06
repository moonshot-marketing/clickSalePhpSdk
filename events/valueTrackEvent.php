
<?php

date_default_timezone_set("UTC");

define('EVENT_API_HOST', "https://vt-production.valuetrackbi.com/api/event");

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
            curl_setopt($curl, CURLOPT_URL, EVENT_API_HOST);

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

class EventData
{
    private $valueTrackId,
    $event,
    $value,
    $extraData,
        $trackerVersion;

    public function __construct(int $valueTrackId, string $event, float $value, array $extraData)
    {
        $this->valueTrackId = $valueTrackId;
        $this->event = $event;
        $this->value = $value;
        $this->trackerVersion = "php/v1.0";
        $this->extraData = $extraData;
    }

    public function buildObject()
    {
        $dataObject = array();

        $dataObject["valuetrack_id"] = $this->valueTrackId;
        $dataObject["event"] = $this->event;
        $dataObject["value"] = $this->value;
        $dataObject["trackerVersion"] = $this->trackerVersion;
        $dataObject["extra"] = $this->extraData;

        return $dataObject;
    }

}

class ValueTrackEventHandler
{

    public $event,
    $valueTrackId,
    $value,
        $extraData;

    public function __construct($valueTrackId)
    {
        $this->valueTrackId = intval($valueTrackId);
        $this->extraData = array();
        $this->value = .0;
        $this->postHandler = new PostDataHandler();

    }

    public function setEventType($event)
    {
        $this->event = $event;
    }

    public function setProvider($provider)
    {
        $this->extraData["provider"] = $provider;
    }

    public function setValue($value)
    {
        $this->value = floatval($value);
    }

    public function addExtraParameter($key, $value)
    {
        $this->extraData[$key] = $value;
    }

    public function execute()
    {

        if (empty($this->event) || !isset($this->event)) {
            die("event type is required");
            return false;
        } else {

            $eventObject = new EventData(
                $this->valueTrackId,
                $this->event,
                $this->value,
                $this->extraData
            );

            $response = json_decode($this->postHandler->exec($eventObject->buildObject()));

            // response
            if (property_exists($response, "success")) {

                if ($response->success) {
                    return true;
                } else {
                    trigger_error($response->reason, E_USER_NOTICE);
                    return false;
                }

            } else {
                trigger_error("Error", E_USER_NOTICE);
                return false;
            }
        }

    }
}