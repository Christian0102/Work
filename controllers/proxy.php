<?php



$defaultDelays = array(
        -1 => array(60,120) // every request will wait randomly from 60 to 120 seconds
    );


    // create a proxy pool with default delays for the proxys
    $proxyPool = new \GoogleUrl\ProxyPool($defaultDelays);

    // creates a proxy with options :
    // - ip     : 20.20.183.183
    // - port   : 80
    // - last use (last time the proxy has been used) : 0 (unix timestamp)
    // - next delay (time to wait before next query) : 0 seconds (we wont wait before next query)  | It is used to know if the proxy can be run since the last run
    // - request count (the number of request that the proxy as already performed) : 0             | It is used to know what delay to apply from the delays array
    // - locked : false     (the proxy mus tbe locked when a request is being performed with it)
    //
    // We dont define any delays for this proxy. Then it is going to use default delays of the ProxyPool
    //
    $proxy1 = new GoogleUrl\Proxy\ProxyObject("20.20.183.183", 82, null, null, 0, 0, 0, false);

    // creates a 2d proxy on the ip 20.20.183.184 port 8080
    $proxy2 = new GoogleUrl\Proxy\ProxyObject("20.20.183.184", 8080, null, null, 0, 0, 0, false);

    // set delays for this 2d proxy. Thus it will use theses delays instead of the one set in the proxyPool
    $proxy2->setDelays(array(

        2   => array(5,10),  // the requests 0->2 with this proxy will wait randomly from 5 to 10 sec (delays are 0 indexed)
        5   => array(15,20), // the requests 3->5 with this proxy will wait randomly from 15 to 20 sec
        10  => array(25,35), // the requests 6->10 with this proxy will wait randomly from 25 to 35 sec
        20  => array(40,55), // the requests 11->20 with this proxy will wait randomly from 40 to 55 sec
        40  => array(60,80), // the requests 21->40 with this proxy will wait randomly from 60 to 80 sec
        100 => array(60,120) // the requests 41->100 with this proxy will wait randomly from 60 to 120 sec
        // then we reset the counter and we go back to 0
        // instead we could have used    -1 => array(60,120)    for no counter reset
        // -1 is a kind of endless counter

    ));


    // add the 2 proxys to the proxy pool
    $proxyPool->setProxy($proxy1);
    $proxyPool->setProxy($proxy2);


    // this is the searcher we will use with a proxy
    $searcher = new GoogleUrl();
    $searcher->setLang('fr')->setNumberResults(10);

    // list of keywords that we want to parse
    $keywords = array("simpson","tshirt simpson","homer");


    // we loop until every keywords are parsed
    do{

        // the keyword to search
        $keyword = current($keywords);


        echo "Searching an available proxy" . PHP_EOL;

        // we search an available proxy (a proxy that is neither locked nor waiting for a delay)
        $proxy = $proxyPool->findAvailableProxy();

        // this case mean that all proxy are locked or under delays, then we are going to wait
        if( !$proxy ){
            echo "No proxy available. Searching for the one with the lowest delay" . PHP_EOL;

            // we search the proxy with the shortest delay for the next use
            // locked proxy are excluded from this search
            $proxy = $proxyPool->findShortestTimeProxy();

            if( !$proxy ){ 
                // there is no available proxy
                throw new \Exception("No proxy available");
            }

            echo "Proxy found : " . $proxy->getIp() . ":" . $proxy->getPort() . PHP_EOL;

            // we find the time that the proxy must wait for the next query
            $time = $proxy->getTimeToAvailability();

            // we lock the proxy for the time of the request
            // I explain latter why this is usefull
            $proxyPool->acquireProxyLock($proxy);
            
            echo "Waiting for $time seconds for the proxy to be available" . PHP_EOL;
            // we sleep to wait for the proxy to be available
            sleep($time);       
            
        }

        try{
            
                

                echo "Querying keyword $keyword with the proxy " . $proxy->getIp() . ":" . $proxy->getPort() . PHP_EOL;

                // start the search
                $searchResult = $searcher->search($keyword,$proxy);

                // unlock the proxy
                $proxyPool->releaseProxyLock($proxy);

                // register the use of the proxy 
                // This increases the count, assign the next delays etc...
                $proxyPool->proxyUsed($proxy);

                echo "Parsing Search resulsts" . PHP_EOL;

                // Do some actions with $searchResult
                $positions = $searchResult->getPositions();
                // ...
                // ...
                // ...
                // ...
                // ...
                // ...
                // ...

                // go to the next keyword
                next($keywords);

        } 

        // the proxy is badly configured
        catch (\GoogleUrl\Exception\ProxyException $ex) {

            echo $ex->getMessage();

            // remove the proxy from the pool
            $proxyPool->removeProxy($proxy);

        } 

        // We have met the google captcha. We will have to update delays
        catch (\GoogleUrl\Exception\CaptachaException $ex){

            echo $ex->getMessage();
            // we may remove the proxy and create an alert
            // unlock the proxy
            $proxyPool->releaseProxyLock($proxy);

        } 

        // there was a network error, maybe the network is down ?
        catch (\GoogleUrl\Exception\CurlException $ex){

            echo $ex->getMessage();
            // we may remove the proxy and create an alert
            // unlock the proxy
            $proxyPool->releaseProxyLock($proxy);

        }

        echo PHP_EOL . PHP_EOL;

    }while($keyword !== false);
	
	?>