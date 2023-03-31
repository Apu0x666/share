<?php

namespace App\Http\Controllers;
use App\Models\Server;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServerController extends Controller{

    public function create_edit(Request $request): JsonResponse {

        try {
            if ($request->input('id') == NULL) {
                $title = trim($request->input('title'));
                $system_id = trim($request->input('system_id'));
    
                if (empty($title)) {
                    return response()->json(
                        [
                            'error' => "Поле 'Сервер' обязательно для заполнения",
                        ]
                    );
                }
                if (empty($system_id)) {
                    return response()->json(
                        [
                            'error' => "Поле 'АС' обязательно для заполнения",
                        ]
                    );
                }
                
                $software = new Server();
                $software->title = trim($request->input('title'));
                $software->system_id = trim($request->input('system_id'));
                $software->os = trim($request->input('os'));
                $software->ip = trim($request->input('ip'));
                $software->save();

                return response()->json(
                    [
                        'success' => 'Добавлен новый сервер ['. trim($request->input('title')).']',
                    ]
                );

            } else {
                Server::query()
                    ->where('id', $request->input('id'))
                    ->update([
                        'title' => trim($request->input('title')),
                        'system_id' => trim($request->input('system_id')),
                        'os' => trim($request->input('os')),
                        'ip' => trim($request->input('ip')),
                    ]);
    
                $serv = (new Server())->find($request->input('id'));
    
                if (!is_object($serv)) {
                    return response()->json(
                        [
                            'error' => 'Ошибка обновления сервера. ['.trim($request->input('title')).'] не существует',
                        ]
                    );
                }

                return response()->json(
                    [
                        'success' => 'Сервер ['. trim($request->input('title')).'] обновлен',
                    ]
                );
            }

        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    public function destroy($id = null) {
        try {
            if ($id !== NULL) {
                Server::query()
                    ->where('id', $id)
                    ->delete();
                return response()->json(
                    [
                        'success' => 'Удалено',
                    ]
                );
            }
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage(),
                ]
            );
        }
        return response()->json(
            [
                'error' => 'Ошибка удаления',
            ]
        );
    }
}
