<?php

namespace App\Http\Controllers;
use App\Models\Software;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SoftwareController extends Controller{

    public function create_edit(Request $request): JsonResponse {

        try {
            if ($request->input('id') == NULL) {

                $title = trim($request->input('title'));
                $type_id = trim($request->input('type_id'));
                
                if (empty($title)) {
                    return response()->json(
                        [
                            'error' => "Поле 'Название' обязательно для заполнения",
                        ]
                    );
                }
                if (empty($type_id)) {
                    return response()->json(
                        [
                            'error' => "Поле 'Тип' обязательно для заполнения",
                        ]
                    );
                }
                
                $software = new Software();
                $software->title = trim($request->input('title'));
                $software->type_id = trim($request->input('type_id'));
                
                $software->version = trim($request->input('version'));
                $software->save();

                $software->servers()->sync($request->input('server_id'));

                return response()->json(
                    [
                        'success' => 'Добавлен новое ПО ['. trim($request->input('title')).']',
                    ]
                );

            } else {
                Software::query()
                    ->where('id', $request->input('id'))
                    ->update([
                        'title' => trim($request->input('title')),
                        'type_id' => trim($request->input('type_id')),
                        'version' => trim($request->input('version')),
                    ]);

                $soft = (new Software)->find($request->input('id'));
                
                if (!is_object($soft)) {
                    return response()->json(
                        [
                            'error' => 'Ошибка обновления ПО. ['.trim($request->input('title')).'] не существует',
                        ]
                    );
                }
                
                $soft->servers()->sync($request->input('server_id'));
                
                return response()->json(
                    [
                        'success' => 'ПО ['.trim($request->input('title')).'] обновлено',
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

    public function destroy($id = null): JsonResponse {
        try {
            if ($id !== NULL) {
                $res = Software::query()
                        ->where('id', $id)
                        ->delete();
                if ($res != 1) {
                    throw new Exception('Удаляемая запись не найдена');
                }
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
