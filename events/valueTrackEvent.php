
<?php

date_default_timezone_set("UTC");

define('EVENT_API_HOST', "https://vt-production.valuetrackbi.com/api/event");

class EventPostDataHandler
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
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($dataObject, JSON_FORCE_OBJECT));
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
    $provider,
    $extraData,
        $trackerVersion;

    public function __construct(int $valueTrackId, string $event, float $value, string $provider, array $extraData)
    {
        $this->valueTrackId = $valueTrackId;
        $this->event = $event;
        $this->value = $value;
        $this->provider = $provider;
        $this->trackerVersion = "php/v1.0";
        $this->extraData = $extraData;
    }

    public function buildObject()
    {
        $dataObject = array();

        $dataObject["valuetrack_id"] = $this->valueTrackId;
        $dataObject["event"] = $this->event;
        $dataObject["value"] = $this->value;
        $dataObject["provider"] = $this->provider;
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
    $provider,
        $extraData;

    public function __construct($valueTrackId)
    {
        $this->event = null;
        $this->valueTrackId = intval($valueTrackId);
        $this->extraData = array();
        $this->value = .0;
        $this->provider = null;
        $this->postHandler = new EventPostDataHandler();

    }

    public function setEventName($event)
    {
        $this->event = $event;
    }

    public function setProvider($provider)
    {
        $this->provider = $provider;
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
            die("event name is required");
            return false;
        } else {

            $eventObject = new EventData(
                $this->valueTrackId,
                $this->event,
                $this->value,
                $this->provider,
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
