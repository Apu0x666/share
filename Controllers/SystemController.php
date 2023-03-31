<?php

namespace App\Http\Controllers;
use App\Models\System;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemController extends Controller {

    public function edit(Request $request): JsonResponse {
        try {
                System::query()
                    ->where('id', $request->input('id'))
                    ->update([
                        'title' => trim($request->input('title')),
                        'address' => trim($request->input('address')),
                        'mail_server_id' => intval(trim($request->input('mail_server_id'))),
                        'api' => trim($request->input('api')),
                    ]);

                return response()->json(
                    [
                        'success' => 'Система ['. trim($request->input('title')) . '] обновлена',
                    ]
                );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    public function destroy($id = null) {
    }
    
    public function getSystemNameById($system_id) {
        return System::query()
            ->where('id', $system_id)->value('title');
    }
    
    
    public function getSystemMailServerId($system_id) {
        return (System::query()
            ->where('id', $system_id)->value('mail_server_id'));
    }
    
}
