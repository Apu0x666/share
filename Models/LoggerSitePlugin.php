<?php

namespace App\Models;

use SSOLibs\FrtsLogger\FrtsLoggerSitePlugin;
use Illuminate\Support\Facades\Auth;

class LoggerSitePlugin extends FrtsLoggerSitePlugin{

    private static function getUser(){
        $user = null;
        try{
            $user = 'Неизвестно'; // Auth::user()->all();
        } catch (\Exception $e){

        }
        return $user;
    }

    protected static function getUserId(){
        $user = static::getUser();
        return (isset($user['sub']))
            ? $user['sub']
            : '';
    }

    protected static function getUserLogin(){
        $user = static::getUser();
        return (isset($user['name']))
            ? $user['name']
            : '';
    }

    protected static function getUserGroupId(){
        $user = static::getUser();
        $groups = (isset($user['groups']))
            ? $user['groups']
            : null;
        return (is_array($groups))
            ? implode(' | ', $groups)
            : '';
    }

    protected static function checkMessage($err){
        $excludeText = [
            'User cannot be authenticated',
        ];

        $foundExc = false;
        foreach ($excludeText as $exc) {
            if (strpos($err['errstr'], $exc) !== FALSE ){
                $foundExc = true;
                break;
            }
        }

        if ($foundExc){
            return false;
        }

        return parent::checkMessage($err);
    }

}
