<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redirect;
use Inertia\ResponseFactory;

class TicketController extends Controller
{

    /**
     * Show the form for creating a new resource.
     *
     * @return Response|\Inertia\Response|ResponseFactory
     */
    public function create()
    {
        return inertia('TicketEditor', [
            'item' => [],
            'data' => (new DictionaryController)->index(['software','applicant']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse {
        $ticket  = new Ticket($request->all());
        $ticket->user_id = KeyCloakController::getCurrentUser();

        $ticket->save();
        return Redirect::to('/');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param int $id
     * @return \Inertia\Response|ResponseFactory
     */
    public function edit(int $id)
    {
        $ticket = Ticket::findOrFail($id);
        if (is_object($ticket)) {
            $ticket->applicant_id = intval($ticket->applicant_id);
        }
        return inertia('TicketEditor', [
            'item' => $ticket,
            'data' => (new DictionaryController)->index(['software','applicant']),
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param  int  $id
     * @return RedirectResponse
     */
    public function update(Request $request, $id)
    {
        /**
         * @var Ticket $ticket
         */
        $ticket = Ticket::findOrFail($id);
        $ticket->synced = false;
        $ticket->update($request->all());

        
        //авто-апдейт софта при сохранении заявки
        //TicketController::setSoftwareFromServices($id);

        return Redirect::to("/#ticket-$id");
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return RedirectResponse
     */
    public function destroy($id=null)
    {
        if(empty($id)){
            $ids = \request()->input('ids', []);
            if(!empty($ids)){
                Ticket::query()
                    ->whereIn('id', $ids)
                    ->whereNull('bitrix_id')
                    ->delete();
                Ticket::query()
                    ->whereIn('id', $ids)
                    ->where(['last' => true])
                    ->update(['deleted' => true, 'synced' => false]);
                Service::query()
                    ->whereIn('ticket_id', $ids)
                    ->where(['last' => true])
                    ->update(['deleted' => true, 'synced' => false]);
                Service::query()
                    ->where(['deleted' => true])->whereNull('bitrix_id')->delete();
            }
        } else {
            /**
             * @var Ticket $ticket
             */
            $ticket = Ticket::findOrFail($id);
            if(empty($ticket->bitrix_id)){
                $ticket->delete();
                Service::query()
                    ->where(['ticket_id' => $id])
                    ->delete();
            } else {
                $ticket->deleted = true;
                $ticket->synced = false;
                if ($ticket->save()) {
                    Service::query()
                        ->where(['ticket_id' => $id, 'last' => true])
                        ->update(['deleted' => true, 'synced' => false]);
                }
            }
        }
        return Redirect::to('/');
    }
    
    public function setWonStatusForTicket($ticketId = null): JsonResponse {
        /**
         * @var Ticket $ticket
         */
        if(empty($ticketId)) {
            $id = \request()->input('id', []);
            if (!empty($id)) {
                $ticketId = $id;
            }
        }
        $ticket = Ticket::findOrFail($ticketId);
        $ticket->status_id = "WON";
        $ticket->update();
    
        return response()->json(
            [
                'success' => 'Заявка обновлена',
            ]
        );
        
    }

    public static function setSoftwareFromServices($ticketId = null) {
        /**
         * @var Ticket $ticket
         */
        $json = \request()->input('json', []);
        
        if(empty($ticketId)) {
            $id = \request()->input('id', []);
            if (!empty($id)) {
                $ticketId = $id;
            }
        }
        
        $ticket = Ticket::findOrFail($ticketId);

        $attachedServices = Service::query()
            ->select('software','ticket_id')
            ->where('ticket_id', $ticketId)
            //->where(['last' => true])
            //->whereRaw('NOT(deleted=1 && synced=1)')
            ->whereRaw('NOT(deleted=1)')
            ->get()->toArray();
        if (count($attachedServices) > 0) {
            $queriedSoftware = array_column($attachedServices, 'software');
            $usedSoftware = [];
            foreach ($queriedSoftware as $item) {
                $usedSoftware = array_merge($usedSoftware, explode(',', $item));
            }
            $softwareForTicket = implode(', ', array_unique($usedSoftware));
            $ticket->software = $softwareForTicket;
            $ticket->update();
            if ($json) {
                return response()->json(
                    [
                        'success' => 'ПО обновлено',
                        'software' => $softwareForTicket,
                    ]
                );
            } else {
                return $softwareForTicket;
            }
        } else {
            $ticket->software = '';
            $ticket->update();
    
            if ($json) {
                return response()->json(
                    [
                        'success' => 'Заявка была обновлена',
                    ]
                );
            } else {
                return false;
            }
        }
    }

    public function sync(){
        $ids = \request()->post('ids', []);
    }
}
