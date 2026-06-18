<?php
namespace App\Libs\Logger;

use Illuminate\Support\Facades\Log;

class TelegramLogger
{
    public static function send(string $message): void
    {
        $url    = 'https://api.telegram.org/bot' . config('constants.log.telegram.token') . '/sendMessage';
        $chatId = config('constants.log.telegram.chat_id');
        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $url,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'chat_id'    => $chatId,
                    'text'       => $message,
                    'parse_mode' => 'HTML',
                ]),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            Log::error('TelegramLogger failed: ' . $e->getMessage());
        }
    }
}