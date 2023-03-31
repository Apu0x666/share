<?php

namespace App\Http\Controllers;

use App\Models\Applicant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;

class ApplicantController extends Controller {
    
    public function create_edit(Request $request): JsonResponse {
        try {
            $bitrix_id = trim($request->input('bitrix_id'));
            $fio = trim($request->input('fio'));
            $org = trim($request->input('org'));
            $comment = trim($request->input('comment'));
    
            if (empty($bitrix_id)) {
                return response()->json(
                    [
                        'error' => "Поле 'Bitrix ID' обязательно для заполнения",
                    ]
                );
            }
            if (empty($fio)) {
                return response()->json(
                    [
                        'error' => "Поле 'ФИО' обязательно для заполнения",
                    ]
                );
            }
            
            
            if ($request->input('id') == NULL) {
                $res = Applicant::query()
                    ->where('bitrix_id', $bitrix_id)
                    ->count();
                if ($res > 0) {
                    return response()->json(
                        [
                            'error' => 'Заявитель с таким bitrix_id уже существует',
                        ]
                    );
                }
                
                //создание нового заявителя
                $applicant = new Applicant();
                $applicant->bitrix_id = $bitrix_id;
                $applicant->fio = $fio;
                $applicant->org = $org;
                $applicant->comment = $comment;
                $applicant->save();
                
                return response()->json(
                    [
                        'success' => 'Добавлен новый Заявитель ['. $fio.']',
                    ]
                );
                
            } else {
                //редактирование существующего
                Applicant::query()
                    ->where('id', $request->input('id'))
                    ->update([
                        'bitrix_id' => $bitrix_id,
                        'fio' => $fio,
                        'org' => $org,
                        'comment' => $comment,
                    ]);
                
                $applicant = (new Applicant)->find($request->input('id'));
                
                if (!is_object($applicant)) {
                    return response()->json(
                        [
                            'error' => 'Ошибка обновления заявителя. ['.$fio.'] не существует',
                        ]
                    );
                }
                
                return response()->json(
                    [
                        'success' => 'Заявитель ['.$fio.'] обновлен',
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
                $res = Applicant::query()
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