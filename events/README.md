## How to implement Moonshot's event data - PHP-SDK


##### 1. Upload the valueTrackEvent.php:

Upload the valueTrackEvent.php file to server.


##### 2. Implementation:

In the page of the event(Sale/Other Conversion),
add the following code:

    ````
        include_once('{path to your file}/valueTrackEvent.php');

        // get the valuetrack id of this session, else an empty string.
        $sessionValueTrackId = isset($_COOKIE['msvt_cid']) ? $_COOKIE['msvt_cid'] : '';

        // check if the vt_cookie value is a number, then execute.
        if(is_numeric($sessionValueTrackId)){

            // create new Sales Object
            $event = new ValueTrackEventHandler((int)$sessionValueTrackId);

            // Set the event name - required
            $event->setEventName('{event}');

            // set the provider name - optional
            $event->setProvider('{your project name}');

            // set revenue - optional
            $event->set_revenue({revenue});

            // add extra prameter - optional
            $event->addExtraParameter("test_var", 1);

            // and another one
            $event->addExtraParameter("test_var2", array("t" => 11, "mv" => "test));

            // execute
            $event->execute();
        }

    ````

##### Conclusion:
    The script will send the data to Moonshot's Api
