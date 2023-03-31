<?php

namespace App\Models;

use App\Bitrix24\Bitrix24APIException;
use App\Http\Helpers\Bitrix24APIExtended;
use DateTime;
use Exception;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * @property string $id
 * @property string $title
 * @property string $address
 * @property int $mail_server_id
 * @property Carbon $last_sync
 * @property string $api
 */
class System extends BaseModel
{
    protected static $orderBy = 'title';
    protected $keyType = 'string';
    protected $dates = ['last_sync'];
    protected $dateFormat = 'Y-m-d H:i:s';
    private $apiObject = null;
    protected $fillable = ['last_sync'];

    protected static $dataTableColumns = [
        'id' => 'ID',
        'title' => 'Название',
        'address' => 'Адрес',
        'mail_server_id' => 'Почтовый сервер',
        'last_sync' => 'Синхронизировано',
        'api' => 'Ссылка на api',
    ];

    protected static $dictionary = [
        'status_id' => 'status',
        'applicant_id' => 'applicant',
        'system_id' => 'system',
        'type_id' => 'service_type_group',
        'server_id' => 'server',
        'source_id' => 'source',
        'priority_id' => 'priority',
        'user_id' => 'user',
        'mail_server_id' => 'mailServers',
    ];
    
    public function api(){
        if(empty($this->apiObject)) {
            if (!empty($this->api)) {
                $this->apiObject = new Bitrix24APIExtended($this->api);
                $this->apiObject->http->curlTimeout = 3600;
            } else {
                $this->error("Не указан API для системы");
            }
        }
        return $this->apiObject;
    }

    public function apiReadTickets(){
        $this->log("Получение изменений с битрикса");
        $api = $this->api();
        $filter = [];
        if(!empty($this->last_sync)) {
            $filter['>DATE_MODIFY'] = [$this->last_sync->format(DATE_ATOM)];
        }
        $list = $api->fetchDealList($filter, ['*', 'UF_*']);
        foreach ($list as $chunk){
            foreach ($chunk as $item) {
                $id = $item['ID'];
                /**
                 * @var Ticket $ticket
                 */
                $ticket = Ticket::query()->where(['bitrix_id' => $id, 'last' => true])->first();
                if(empty($ticket)){
                    $ticket = new Ticket(['system_id' => $this->id, 'last' => true]);
                } elseif(!$ticket->synced){
                    $ticket = new Ticket(['system_id' => $this->id, 'last' => false]);
                }
                $ticket->fillFromBitrixArray($item);
                $ticket->synced = true;
                $ticket->modified_at = $item['DATE_MODIFY'];
                try {
                    $ticket->save();
                } catch (QueryException $queryException){

                }
            }
        }
    }

    public static function prepareTicketsForSync($system_ids=[], $ids=[]){
        $q = Ticket::query()->where(['synced' => false, 'last'=> true]);
        if(!empty($ids)){
            $q->whereIn('id', $ids);
        }
        if(!empty($system_ids)){
            $q->whereIn('system_id', $system_ids);
        }
        /**
         * @var Ticket[] $tickets
         */
        $tickets = $q->get();
        $preparedTickets = [];
        if(!empty($system_ids)){
            foreach ($system_ids as $system_id){
                $preparedTickets[$system_id] = ['create' => [], 'update' => [], 'delete' => []];
            }
        }
        if(count($tickets) > 0) {
            foreach ($tickets as $ticket) {
                if(!isset($preparedTickets[$ticket->system_id])) {
                    $preparedTickets[$ticket->system_id] = ['create' => [], 'update' => [], 'delete' => []];
                }
                if ($ticket->deleted) {
                    if (!empty($ticket->bitrix_id)) {
                        $preparedTickets[$ticket->system_id]['delete'][$ticket->id] = $ticket->bitrix_id;
                    } else {
                        $ticket->update(['last' => false, 'synced' => true]);
                    }
                } else {
                    $data = $ticket->getBitrixArray();
                    if (empty($ticket->bitrix_id)) {
                        $preparedTickets[$ticket->system_id]['create'][$ticket->id] = $data;
                    } else {
                        $preparedTickets[$ticket->system_id]['update'][$ticket->id] = $data;
                    }
                }
            }
        }
        return $preparedTickets;
    }

    public function apiDeleteTickets($delete = []){
        $api = $this->api();
        if(!empty($delete) && !empty($api)){
            $this->log("Найдены заявки для удаления в количестве " . count($delete));
            $deleted = [];
            $deletedMessage = [];
            foreach ($delete as $id => $bitrix_id){
                $success = null;
                try {
                    $success = $api->deleteDeal($bitrix_id);
                } catch (Exception $exception){
                    $this->error("Ошибка удаления заявки $id ($bitrix_id)".$exception->getMessage(), $exception->getTrace());
                    Ticket::find($id)->update(['error' => $exception->getMessage()]);
                }
                if(!empty($success)){
                    $deleted[] = $bitrix_id;
                    $deletedMessage[] = "$id ($bitrix_id)";
                }
            }
            if(!empty($deleted)) {
                $dbCount = Ticket::query()
                    ->whereIn('bitrix_id', $deleted)
                    ->where(['last' => true])
                    ->update(['last' => false, 'synced' => true]);
                $bitrixMessage = "Удалены заявки с " . implode(', ', $deletedMessage);
                $dbSuccess = $dbCount === count($deleted);
                $dbMessage = $dbSuccess?"успешно":"не удалась";
                $this->log("$bitrixMessage. Запись в БД $dbMessage", [], $dbSuccess);
            }
        }
    }

    public function apiUpdateTickets($update = []){
        $api = $this->api();
        if(!empty($update) && !empty($api)){
            $this->log("Найдены заявки для обновления в количестве " . count($update));
            $updated = [];
            $updatedMessage = [];
            foreach ($update as $id => $item){
                $success = null;
                $bitrix_id = $item['ID'];
                $this->log("Обновляю заявку $id ($bitrix_id) с параметрами", $item);
                try {
                    $success = $api->updateDeal($bitrix_id, $item);
                } catch (Exception $exception){
                    $this->error("Ошибка обновления заявки $id ($bitrix_id): ". $exception->getMessage(), $exception->getTrace());
                    Ticket::find($id)->update(['error' => $exception->getMessage()]);
                }
                if(!empty($success)){
                    $updated[] = $id;
                    $updatedMessage[] = "$id ($bitrix_id)";
                }
            }
            if(!empty($updated)){
                $dbCount = Ticket::query()->whereIn('id', $updated)->where(['last' => true])->update(['synced' => true]);
                Service::query()->whereIn('ticket_id', $updated)->update(['synced' => false]);
                $bitrixMessage = "Обновлены заявки " . implode(', ', $updatedMessage);
                $dbSuccess = $dbCount === count($updated);
                $dbMessage = $dbSuccess?"успешно":"не удалась";
                $this->log("$bitrixMessage. Запись в БД $dbMessage", [], $dbSuccess);
            }
        }
    }

    public function apiCreateTickets($create = []){
        $api = $this->api();
        if(!empty($create) && !empty($api)){
            $this->log("Найдены заявки для добавления в количестве " . count($create));
            $created = [];
            foreach ($create as $id => $item){
                $this->log("Добавляю заявку $id с параметрами: " . json_encode($item, JSON_UNESCAPED_UNICODE));
                $bitrix_id = null;
                try {
                    $bitrix_id = $api->addDeal($item);
                } catch (Exception $exception){
                    $this->error("Ошибка добавления заявки $id: ". $exception->getMessage(), $exception->getTrace());
                    Ticket::find($id)->update(['error' => $exception->getMessage()]);
                }
                if(!empty($bitrix_id)){
                    $created[$id] = $bitrix_id;
                    $dbSuccess = Ticket::query()->where(['id' => $id])->update(['bitrix_id' => $bitrix_id, 'synced' => true]);
                    $dbMessage = $dbSuccess > 0?"успешно":"не удалась";
                    $this->log("Добавлена заявка с $id ($bitrix_id) Запись в БД: $dbMessage", [], $dbSuccess);
                }
            }
            return $created;
        }
        return [];
    }

    private function getServicesToLoad(){
        $list = $this->api()->fetchDealList(
            ['CLOSED' => 'N'],
//            ['>DATE_MODIFY' => '2022-06-13T15:23:00'],
            ['*', 'UF_*']
        );
        $serviceIds = [];
        foreach ($list as $chunk) {
            foreach ($chunk as $item) {
                $servicesString = $item['UF_SERVICES'] ?? '';
                $servicesArr = explode(',',$servicesString);
                foreach ($servicesArr as $part){
                    $id = trim($part);
                    if(!in_array($id, $serviceIds)){
                        $serviceIds[] = $id;
                    }
                }
            }
        }
        return $serviceIds;
    }

    public function loadServices(){
        $api = $this->api();
        $serviceIds = $this->getServicesToLoad();
        $services = empty($serviceIds)?$api->getServices($serviceIds):[];
        foreach ($services as $chunk){
            foreach ($chunk as $item){
                foreach ($item as $k => $v){
                    if(str_contains($k, 'PROPERTY_')){
                        $item[$k] = reset($v);
                    }
                }
                $id = $item['ID'];
                /**
                 * @var Service $service
                 */
                $service = Service::findByBitrixId($id);
                $ticketBitrixId = $item['PROPERTY_125'];
                $ticket = Ticket::findByBitrixId($ticketBitrixId);
                if($ticket) {
                    if (empty($service)) {
                        $service = new Service(['ticket_id' => $ticket->id, 'last' => true, 'synced' => true]);
                        $service->fillFromBitrixArray($item);
                        $service->save();
                    } else {
                        $old = $service->attributes;
                        $service->fillFromBitrixArray($item);
                        $new = $service->attributes;
                        $diff = array_diff_assoc($old, $new);
                        if (count($diff) > 0) {
                            echo $old['start'] . PHP_EOL;
                            echo $new['start'] . PHP_EOL . PHP_EOL;
                            $synced = $service->synced;
                            if ($synced) {
                                Service::query()->where(['bitrix_id' => $id])->update(['last' => false]);
                            }
                            unset($new['id']);
                            $new['last'] = $synced;
                            $new['synced'] = $synced;
                            $service = new Service($new);
//                        $service->fillFromBitrixArray($item);
                            $service->save();
                        }
                    }
                }
            }
        }
    }

    public static function prepareServicesToSync($system_ids=[], $tickets_ids=[], $ids=[]){
        $q = Service::query()
            ->select('services.*')
            ->where([
                'services.last'=> true, 'services.synced' => false, //'tickets.system_id' => $this->id
            ])
            ->join('tickets', 'tickets.id', '=', 'services.ticket_id');
        if(!empty($system_ids)){
            $q->whereIn('tickets.system_id', $system_ids);
        }
        if(!empty($tickets_ids)){
            $q->whereIn('tickets.id', $tickets_ids);
        }
        if(!empty($ids)){
            $q->whereIn('services.id', $ids);
        }
        $services = $q->get();
        $prepared = [];
        foreach ($services as $service){
            if(!isset($prepared[$service->ticket->system_id])){
                $prepared[$service->ticket->system_id] = [];
            }
            /**
             * @var Service $service
             */
            if ($service->deleted) {
                if (!empty($service->bitrix_id)) {
                    $prepared[$service->ticket->system_id]['delete'][$service->id] = $service->bitrix_id;
                } else {
                    $service->update(['last' => false, 'synced' => true]);
                }
            } else {
                $data = $service->getBitrixArray();
                if (empty($service->bitrix_id)) {
                    $prepared[$service->ticket->system_id]['create'][$service->id] = $data;
                } else {
                    $prepared[$service->ticket->system_id]['update'][$service->id] = $data;
                }
            }
        }
        return $prepared;
    }

    public static function apiProceedServices($system_ids, $tickets_ids=[], $ids = [], $forceLoad = false){
        $prepared = static::prepareServicesToSync($system_ids, $tickets_ids, $ids);
//        print_r($prepared);
        foreach ($prepared as $systemId => $batch) {
            /**
             * @var System $system
             */
            $system = System::find($systemId);
            if(empty($system->api())){
                return;
            }
            $create = $batch['create'] ?? [];
            $update = $batch['update'] ?? [];
            $delete = $batch['delete'] ?? [];
            $secondUpdate = $system->apiCreateServices($create);
            if(!empty($update) || !empty($delete) || $forceLoad){
                $system->loadServices();
            }
//            foreach ($secondUpdate as $k => $v){
//                $update[$k] = $v;
//            }
            $system->apiUpdateServices($update);
            $system->apiDeleteServices($delete);

        }
    }

    public function apiCreateServices($create){
        $api = $this->api();
        $created = [];
        if (!empty($create) && !empty($api)) {
            $this->log("Найдены услуги для добавления в количестве " . count($create));
            foreach ($create as $id => $item){
                $this->log("Добавляю услугу $id с параметрами: " . json_encode($item, JSON_UNESCAPED_UNICODE));
                $bitrix_id = null;
                try {
                    $bitrix_id = $api->addService($item);
                } catch (Exception $exception){
                    $this->error("Ошибка добавления заявки $id: ". $exception->getMessage(), $exception->getTrace());
                    Service::find($id)->update(['error' => $exception->getMessage()]);
                }
                if(!empty($bitrix_id)){
                    $created[$id] = $bitrix_id;
                    $dbSuccess = Service::query()->where(['id' => $id])->update(['bitrix_id' => $bitrix_id, 'synced' => true]);
                    $dbMessage = $dbSuccess > 0?"успешно":"не удалась";
                    $this->log("Добавлена услуга с $id ($bitrix_id) Запись в БД: $dbMessage", [], $dbSuccess);
                }
            }
        }
        return array_keys($created);
    }

    public function apiUpdateServices($update){
        $api = $this->api();
        if (!empty($update) && !empty($api)) {
            $this->log("Найдены услуги для обновления в количестве " . count($update));
            $updated = [];
            $updatedMessage = [];
            foreach ($update as $id => $item){
                $success = null;
                $bitrix_id = $item['ID'];
                $this->log("Обновляю услугу $id ($bitrix_id) с параметрами", $item);
                try {
                    $success = $api->updateService($item);
                } catch (Exception $exception){
                    $this->error("Ошибка обновления услуги $id ($bitrix_id): ". $exception->getMessage(), $exception->getTrace());
                    Service::find($id)->update(['error' => $exception->getMessage()]);
                }
                if(!empty($success)){
                    $updated[] = $id;
                    $updatedMessage[] = "$id ($bitrix_id)";
                }
            }
            if(!empty($updated)){
                $dbCount = Service::query()->whereIn('id', $updated)->where(['last' => true])->update(['synced' => true]);
                $bitrixMessage = "Обновлены услуги " . implode(', ', $updatedMessage);
                $dbSuccess = $dbCount === count($updated);
                $dbMessage = $dbSuccess?"успешно":"не удалась";
                $this->log("$bitrixMessage. Запись в БД $dbMessage", [], $dbSuccess);
            }
        }
    }

    public function apiDeleteServices($delete){
        $api = $this->api();
        if (!empty($delete) && !empty($api)) {
            $this->log("Найдены услуги для удаления в количестве " . count($delete));
            $deleted = [];
            $deletedMessage = [];
            foreach ($delete as $id => $bitrix_id){
                $success = null;
                try {
                    $success = $api->deleteService($bitrix_id);
                } catch (Exception $exception){
                    $this->error("Ошибка удаления услуги $id ($bitrix_id)".$exception->getMessage(), $exception->getTrace());
                    Service::find($id)->update(['error' => $exception->getMessage()]);
                }
                if(!empty($success)){
                    $deleted[] = $bitrix_id;
                    $deletedMessage[] = "$id ($bitrix_id)";
                }
            }
            if(!empty($deleted)) {
                $dbCount = Service::query()
                    ->whereIn('bitrix_id', $deleted)
                    ->where(['last' => true])
                    ->update(['last' => false, 'synced' => true]);
                $bitrixMessage = "Удалены услуги с " . implode(', ', $deletedMessage);
                $dbSuccess = $dbCount === count($deleted);
                $dbMessage = $dbSuccess?"успешно":"не удалась";
                $this->log("$bitrixMessage. Запись в БД $dbMessage", [], $dbSuccess);
            }
        }
    }

    private function log($message, $context = [], $success = true){
        if($success) {
            $message = "[{$this->title}]: $message \n";
            Log::channel('sync')->info($message, $context);
            echo $message;
        } else {
            $this->error($message, $context);
        }
    }
    private function error($message, $context=[]){
        $message = "[{$this->title}]: $message \n";
        Log::channel('sync')->error($message, $context);
        echo $message;
    }

    public static function apiProceedTickets($system_ids=[], $ids=[], $forceLoad = false){
        $prepared = static::prepareTicketsForSync($system_ids, $ids);
        foreach ($prepared as $systemId => $batch){
            /**
             * @var System $system
             */
            $system = System::find($systemId);
            if(empty($system->api())){
                return;
            }
            $create = $batch['create'];
            $update = $batch['update'];
            $delete = $batch['delete'];
            $system->apiCreateTickets($create);
            if(!empty($update) || !empty($delete) || $forceLoad){
                $system->apiReadTickets();
            }
            $system->apiDeleteTickets($delete);
            $system->apiUpdateTickets($update);
        }
    }

    public static function syncAll($systemIds = null, $tickets_ids = [], $services_ids = []){
        set_time_limit(3000);
        ini_set('memory_limit', '1024M');
        $groups = auth()->user()->groups;
        if(empty($systemIds)){
            $systemIds = $groups;
        } else{
            $systemIds = array_filter($systemIds, function ($item) use ($groups){
                return in_array($item, $groups);
            });
        }
        $forceLoad = empty($tickets_ids) && empty($services_ids);
        if(empty($services_ids)) static::apiProceedTickets($systemIds, $tickets_ids, $forceLoad);
        static::apiProceedServices($systemIds, $tickets_ids, $services_ids, $forceLoad);
        foreach ($systemIds as $system_id){
            System::find($system_id)->update(['last_sync' => new DateTime()]);
        }
    }

}
