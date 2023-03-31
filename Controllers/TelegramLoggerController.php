<?php

namespace App\Http\Controllers;

use App\Http\Helpers\JSONRPC;
use App\Models\LoggerSitePlugin;

class TelegramLoggerController extends Controller
{
    public function send(){
        if(config('app.use_js_logger') === 'true'){
            $message = request()->post('message');
            $url = request()->post('url');
            $line = request()->post('line');

            if ($message !== ''){
                LoggerSitePlugin::sendJSMessage($message, $url, $line);
                return JSONRPC::success('Ошибка отправлена');
            }

            return JSONRPC::error('Текст ошибки отсутствует');
        } else {
            return JSONRPC::error('Логгер не настроен');
        }
    }
}
