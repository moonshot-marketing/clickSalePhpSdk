## How to implement Moonshot's clicks data - PHP-SDK


##### 1. Upload the valueTrackClick.php:

Upload the valueTrackClick.php file to your server.


##### 2. Implementation:

In Your ppc landing page add the following code:

    ````
        include_once('{path to your file}/valueTrackClick.php');

        // create new ValueTrackClickHandler object
        $vt = new ValueTrackClickHandler();

        // set website - Required
        $vt->setWebsite('{website name as given by us}');

        // set property to catch only google adwords data - Optional
        $vt->addIncludeParameter('gclid');

        // set property to abort action - Optional
        $vt->addExcludeParameter('go');

        // add extra data - Optional
        $vt->addSiteParameter('brandsPosition', array("A", "B", "C"));

        // execute
        $id = $vt->execute();

    ````

##### Conclusion:
    The script will catch the valuetrack data and send it to Moonshot's Api.

    The Api will return a unique id, and store it in 'msvt_cid' Cookie.
