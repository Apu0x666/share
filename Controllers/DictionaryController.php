<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use App\Models\BaseModel;
use App\Models\MailServers;
use App\Models\Priority;
use App\Models\Server;
use App\Models\ServiceType;
use App\Models\ServiceTypeGroup;
use App\Models\Software;
use App\Models\Source;
use App\Models\Status;
use App\Models\System;

class DictionaryController extends Controller
{
    private function aliases(){
        return [
            'applicant' => Applicant::class,
            'priority' => Priority::class,
            'server' => Server::class,
            'service_type' => ServiceType::class,
            'software' => Software::class,
            'source' => Source::class,
            'status' => Status::class,
            'system' => System::class,
            'service_type_group' => ServiceTypeGroup::class,
            'mailServers' => MailServers::class,
            'user' => '',
        ];
    }

    public function index($list = null, $addFields = null){
        $titles = (!is_null($list)) ? $list : request()->post('load', []);

        $aliases = $this->aliases();
        $res = [];
        foreach ($titles as $title) {
            /**
             * @var BaseModel $model
             */
            $model = $aliases[$title] ?? false;
            if(is_callable($model)) {
                $res[$title] = $model();
            } else if(is_array($model)){
                $res[$title] = $model;
            } else if ($title == 'user'){
                $res[$title] = KeyCloakController::getKeyCloakUsers();
            } else {
                switch ($title):
                    case 'system': {
                        if ($model) {
                            if ($addFields) {
                                $defaultGroups = $model::items($addFields);
                            } else {
                                $defaultGroups = $model::items();
                            }
    
                            $getCurrentUserGroups = KeyCloakController::getCurrentUserGroups();
                            $userGroups = [];
                            array_walk_recursive($getCurrentUserGroups, function ($item, $key) use (&$userGroups) {
                                if ($key == 'id') {
                                    $userGroups[] = $item;
                                }
                            });
        
                            $userGroups = array_flip($userGroups);
        
                            foreach (KeyCloakController::getGroups() as $groupItem) {
                                if (array_key_exists($groupItem['id'], $userGroups)) {
                                    $userGroups[$groupItem['id']] = $groupItem['name'];
                                }
                            }
        
                            foreach ($defaultGroups as $key => $defaultGroup) {
                                if (!in_array($defaultGroup['value'], $userGroups)) {
                                    unset($defaultGroups[$key]);
                                }
                            }
        
                            $res[$title] = array_values($defaultGroups);
                        } else {
                            $res[$title] = 'Нет такого словаря';
                        }
                        break;
                    }
                    case 'mailServers': {
                        if ($model) {
                            if ($addFields) {
                                $mailServers = $model::items($addFields);
                            }
                            else {
                                $mailServers = $model::items();
                            }
        
                            $answer = [];
                            foreach ($mailServers as $server) {
                                $answer[] = [
                                    'text' => $server['name'],
                                    'value' => $server['value']
                                ];
                            }
        
                            $res[$title] = $answer;
                        }
                        break;
                    }
                    case 'applicant': {
                        ($addFields) ? $applicants = $model::items($addFields) : $applicants = $model::items();
                        $answer = [];
                        foreach ($applicants as $applicant) {
                            $answer[] = [
                                'text' => $applicant['text'],
                                'value' => intval($applicant['bitrix_id']),
                            ];
                        }
                        $res[$title] = $answer;
                        break;
                    }
                    default: {
                        if ($addFields) {
                            $res[$title] = $model ? $model::items($addFields) : 'Нет такого словаря';
                        } else {
                            $res[$title] = $model ? $model::items() : 'Нет такого словаря';
                        }
                        break;
                    }
                endswitch;
            }
        }
        return (!is_null($list)) ? $res : response()->json($res);
    }
}
