## How to implement Moonshot's Sales Data - PHP-SDK


##### 1. Upload the mssd.php:

Upload the mssd.php file to server.


##### 2. Implementation:

In the page of the event(Sale/Other Conversion),
add the following code:

    ````
        include_once('{path to your file}/mssd.php');

        // get the valuetrack id of this session, else an empty string.
        $vt_cookie = isset($_COOKIE['msvt_cid']) ? $_COOKIE['msvt_cid'] : '';

        // check if the vt_cookie value is a number, then execute.
        if(is_numeric($vt_cookie)){

            // create new Sales Object
            $aff = new Affiliate((int)$vt_cookie);

            // Set the event type
            $aff->set_event('{event}');

            // set the provider name
            $aff->set_provider('{your project name}');

            // set revenue - OPTIONAL
            $aff->set_revenue({revenue});

            // execute
            $aff->execute();
        }

    ````

##### Conclusion:
    The script will send the data to Moonshot's Api
