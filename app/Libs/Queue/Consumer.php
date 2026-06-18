<?php
namespace App\Libs\Queue;

use App\Libs\Remote;

/**
 * 
 * @author giangdn
 *
 */
class Consumer
{
    /**
     * @var Remote
     */
    protected $remote = null;
    
    /**
     * @param Message $mes
     * @param callable $callback
     */
    public function exec($mes, $callback = null)
    {
        
    }
}
