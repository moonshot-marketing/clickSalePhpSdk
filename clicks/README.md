## How to implement Moonshot's Click Data - PHP-SDK


##### 1. Upload the msvt.php:

Upload the msvt.php file to your server.


##### 2. Implementation:

In Your ppc landing page add the following code:

    ````
        include_once('{path to your file}/msvt.php');

        // create new ValueTrack object
        $vt = new ValueTrack();

        // set the Project name
        $vt->set_project_name('{your project name as given by us}');

        // set property to catch only google adwords data
        $vt->add_include_param('gclid');

        // execute
        $id = $vt->execute();

    ````

##### Conclusion:
    The script will catch the valuetrack data and send it to Moonshot's Api.

    The Api will return a unique id, and store it in 'msvt_cid' Cookie.
