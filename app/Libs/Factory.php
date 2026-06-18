<?php

namespace App\Libs;


use  App\Libs\Remote\Remote;
use  App\Libs\Remote\Queue\Queue;


/**
 *
 * @author Giangdn
 *
 */
class Factory
{
    



	/**
	 * @return App\Libs\Remote\Queue\Queue
	 */
	static function getQueue()
	{
	    return Queue::getInstance();
	}
	
	/**
	 * @return App\Libs\Remote\Remote
	 */
	static function getRemote()
	{
	    return Remote::getInstance();
	}
	static function getApp(){
	    if (self::$instanceApp == null) {
	        self::$instanceApp = new App();
	    }
	    
	    return self::$instanceApp;
	}
	static $instanceApp = null;
    
}
