<?php

namespace App\Libs\Monolog;

use Monolog\Formatter\LineFormatter;

class ExtraLineFormatter extends LineFormatter
{
    public function format(array $record)
    {
        
        $record = parent::format($record);
        
        //Get file call
        $fileName = $this->getFileNameBacktrace();
        
        if (false !== strpos($record, '%filecall%') && !empty($fileName)) {
            $record = str_replace('%filecall%', $this->stringify($fileName), $record);
        }
        return $record;
    }

    public function getFileNameBacktrace()
    {
        $backtracks = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);

        foreach ($backtracks as $backtrack) {
            if (
                isset($backtrack['function']) && $backtrack['function'] == '__callStatic'
                    && isset($backtrack['class']) && $backtrack['class'] == 'Illuminate\Support\Facades\Facade'
            ) {
                $file = explode('/', $backtrack['file'] ?? '');
                $fileName = array_pop($file);

                return ($fileName ?? '') . ':' . ($backtrack['line'] ?? '');
                
            }
        }
        return 'SystemErrorFile.php:0';
    } 
}