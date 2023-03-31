<?php

namespace App\Http\Controllers;

use App\Models\MailServers;
use App\Models\System;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailServersController extends Controller {
    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function create_edit(Request $request): JsonResponse {
        $name = trim($request->input('name'));
        $server = trim($request->input('server'));
        $protocol = trim($request->input('protocol'));
        $port = trim($request->input('port'));
        $password = trim($request->input('password'));
        $from = trim($request->input('from'));
        $to = trim($request->input('to'));
        $theme = trim($request->input('theme'));
        
        try {
            if ($request->input('id') == NULL) {
                //создание нового почтового направления
                if (empty($name) || empty($server) || empty($protocol) || empty($from) || empty($to) || empty($theme) || empty($port)) {
                    return response()->json([
                            'error' => "Все поля обязательны для заполнения",
                        ]);
                }
                
                $mailServer = new MailServers();
                $mailServer->name = $name;
                $mailServer->server = $server;
                $mailServer->protocol = $protocol;
                $mailServer->port = $port;
                $mailServer->password = $password;
                $mailServer->from = $from;
                $mailServer->to = $to;
                $mailServer->theme = $theme;
                $mailServer->save();
                
                return response()->json([
                        'success' => 'Добавлен новое почтовый сервер [' . $name . ']',
                    ]);
                
            }
            else {
                //редактирование существующего
                MailServers::query()->where('id', $request->input('id'))->update([
                        'name'     => $name,
                        'server'   => $server,
                        'protocol' => $protocol,
                        'port'     => $port,
                        'password' => $password,
                        'from'     => $from,
                        'to'       => $to,
                        'theme'    => $theme
                    ]);
                
                $mailServer = (new MailServers)->find($request->input('id'));
                
                if (!is_object($mailServer)) {
                    return response()->json([
                            'error' => 'Ошибка обновления почтового сервера. [' . $name . '] не существует',
                        ]);
                }
                
                return response()->json([
                        'success' => 'Почтовый сервер [' . $name . '] обновлен',
                    ]);
            }
            
        } catch (Exception $e) {
            return response()->json([
                    'error' => $e->getMessage(),
                ]);
        }
    }
    
    /**
     * @param null $id
     * @return JsonResponse
     */
    public function destroy($id = NULL): JsonResponse {
        try {
            if ($id !== NULL) {
                $res = MailServers::query()->where('id', $id)->delete();
                if ($res != 1) {
                    throw new Exception('Удаляемая запись не найдена');
                }
                
                //Удалим данный почтовый сервер из уже привязанных
                System::query()->where('id', $id)->update([
                        'mail_server_id' => 0,
                    ]);
                
                return response()->json([
                        'success' => 'Удалено',
                    ]);
            }
        } catch (Exception $e) {
            return response()->json([
                    'error' => $e->getMessage(),
                ]);
        }
        return response()->json([
                'error' => 'Ошибка удаления',
            ]);
    }
    
    /**
     * @param $mail_system_id
     * @return array
     */
    public function getMailDataByMailSystemId($mail_system_id): array {
        return MailServers::query()->where('id', $mail_system_id)->get()->toArray();
    }
}
