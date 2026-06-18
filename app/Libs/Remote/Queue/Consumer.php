<?php
namespace App\Libs\Remote\Queue;


use App\Libs\Remote\Remote;

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