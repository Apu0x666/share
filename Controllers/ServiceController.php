<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Ticket;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @param $ticketId
     * @return JsonResponse
     */
    public function store(Request $request, $ticketId): JsonResponse {
        $res = [
            'success' => false
        ];
        try {
            $service = new Service($request->all());
            $service->ticket_id = $ticketId;
            $service->user_id = KeyCloakController::getCurrentUser();
            $success =  $service->save();
            $item = Service::find($service->id);
            if($success){
                Ticket::find($ticketId)->update([
                    'synced' => false,
                ]);
                TicketController::setSoftwareFromServices($ticketId);
            }
            $res = compact('success', 'item');

        } catch (Exception $e){
            $res['error'] = $e->getMessage();
        }
        return response()->json($res);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param $ticket_id
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, $ticket_id, int $id): JsonResponse {
        $res = [
            'success' => false
        ];
        try {
            $service = Service::findOrFail($id);
            $service->synced = false;
            $success = $service->update($request->all());
            if($success) {
                Ticket::find($ticket_id)->update(['synced' => false]);
                TicketController::setSoftwareFromServices($ticket_id);
            }
            $res = compact('success', 'service');
        } catch (Exception $e){
            $res['error'] = $e->getMessage();
        }
        return response()->json($res);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($ticket_id, $id=null)
    {
        $success = true;
        $error = null;
        $trace = null;
        try {
            if (empty($id)) {
                $ids = \request()->input('ids', []);
                if (!empty($ids)) {
                    Service::query()
                        ->whereIn('id', $ids)
                        ->whereNull('bitrix_id')
                        ->delete();
                    Service::query()
                        ->whereIn('id', $ids)
                        ->where(['last' => true])
                        ->update(['deleted' => true, 'synced' => false]);
                }
            } else {
                /**
                 * @var Service $ticket
                 */
                $service = Service::findOrFail($id);
                if(empty($service->bitrix_id)){
                    $service->delete();
                } else {
                    $service->deleted = true;
                    $service->synced = false;
                    $success = $service->save();
                }
                TicketController::setSoftwareFromServices($ticket_id);
            }
        } catch (Exception $e){
            $success = false;
            $error = $e->getMessage();
            $trace = $e->getTraceAsString();
        }
        return response()->json(compact('success', 'error', 'trace'));
    }
}
