<?php

namespace App\Models;

use App\Bitrix24\Bitrix24APIException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;

/**
 * @property $id
 * @property $bitrix_id
 * @property $system_id
 * @property $beginDateTime
 * @property $closeDateTime
 * @property $software
 * @property $source_id
 * @property $priority_id
 * @property $status_id
 * @property $applicant_id
 * @property $user_id
 * @property $synced
 * @property $sent
 * @property $last
 * @property $modified_at
 * @property $comment
 * @property $deleted
 * @property $title
 * @property $content
 * @property $updated_at
 * @property $created_at
 * @property Service[] $services
 * @property System $system
 */
class Ticket extends BaseModel
{
    public $timestamps = true;
    protected $appends = ['address'];
    protected $guarded = ['address', 'services', 'serviceIds'];
    protected static $dictionary = [
        'status_id' => 'status',
        'applicant_id' => 'applicant',
        'system_id' => 'system',
        'source_id' => 'source',
        'priority_id' => 'priority',
        'user_id' => 'user',
    ];
    protected $casts = [
        'synced' => 'boolean',
        'deleted' => 'boolean',
        'sent' => 'boolean',
        'last' => 'boolean',
    ];
//    protected $dateFormat = 'Y-m-d';
    protected $dates = ['beginDateTime', 'closeDateTime', 'modified_at'];

    protected static $dataTableColumns = [
//        'id',
        'bitrix_id',
        'title',
        'status_id',
        'applicant_id',
        'beginDateTime',
        'closeDateTime',
        'address' => 'Адрес',
        'system_id',
        'source_id',
        'priority_id',
        'user_id',
        'software' => 'Используемое ПО'
//        'synced',
//        'deleted',
//        'sent',
//        'last',
//        'version',
//        'comment',
//        'content',
//        'updated_at',
//        'created_at'
    ];

    public function getAddressAttribute(){
      $system = $this->system;
      return $system->address ?? null;
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'ticket_id')->where(['last' => true]);
    }


    public function system(): HasOne
    {
        return $this->hasOne(System::class, 'id', 'system_id');
    }

    public function getServicesAttribute(){
        return $this->services()->get()->append('serviceTypeTitle');
    }

    public function getHardNameAttribute(){
        $res = $this->system->title;
        /* $software = [
             'opo' => [],
             'dbs' => [],
             'spo' => []
         ];
         
         foreach ($this->services as $service){
             if($service->software) {
                 $item = $service->software->fullTitle;
                 if ($service->software->type_id == ServiceTypeGroup::OPO && !in_array($item, $software['opo'])) {
                     $software['opo'][] = $item;
                 }
                 else if (in_array($service->software->type_id, ServiceTypeGroup::BDs) && !in_array($item, $software['dbs'])) {
                     $software['dbs'][] = $item;
                 }
                 else if ($service->software->type_id == ServiceTypeGroup::SPO && !in_array($item, $software['spo'])) {
                     $software['spo'][] = $item;
                 }
             }
         }
         foreach ($software as $item) {
             if (!empty($item)) {
                 $res .= ' / ' . implode(', ', $item);
             }
         }*/
        
        foreach ($this->services as $service){
            if($service->software) {
                $res .= ' / ' . $service->software;
            }
        }
        return $res;
    }

    public function getServiceIdsAttribute(){
        $ids = [];
        foreach ($this->services as $service){
            $ids[] = $service->bitrix_id;
        }
        return implode(', ', $ids);
    }

    protected $bitrixDefaults = [
        "TYPE_ID" => "COMPLEX",
        "OPPORTUNITY" => "0.00",
        "TAX_VALUE" => "0.00",
        "UF_SZI" =>  "75",
        "UF_SYSTEM_ID" => "472475"
    ];

    protected $bitrixMap = [
//        'ID' => 'bitrix_id',
        'TITLE' => 'title',
        'COMMENTS' => 'content',
        'STAGE_ID' => 'status_id',
        'UF_BEGINDATE' => ['field' => 'beginDateTime', 'type' => 'date'],
        'CLOSEDATE' =>  ['field' => 'closeDateTime', 'type' => 'date'],
//        'CLOSEDATE1' => 'closeDateTime',
        'SOURCE_ID' => 'source_id',
//        'UF_SYSTEM_ID' => 'system_id',
        'UF_HARD_ADDRESS' => ['field' => 'address', 'readonly' => true, 'ignoreDifference' => true],
        'UF_HARD_NAME' => ['field' => 'hardName', 'readonly' => true,'ignoreDifference' => true],
        'UF_PRIORITY' => 'priority_id',
//        'UF_REPORT' => 'report',
        'CONTACT_ID' => 'applicant_id',
        'UF_SERVICES' => ['field' => 'serviceIds', 'readonly' => true,'ignoreDifference' => true]
    ];


    public function sync(){
        $api = $this->system->api();
        $deleteIt = false;
        $update = [];
        try {
            if ($this->deleted) {
                if (!empty($this->bitrix_id)) {
                    $deleteIt = $api->deleteDeal($this->bitrix_id);
                } else {
                    $deleteIt = true;
                }
            } else {
                if (empty($this->bitrix_id)) {
                    $dealId = $api->addDeal($this->getBitrixArray());
                    if ($dealId) {
                        $update = ['bitrix_id' => $dealId, 'synced' => true];
                    }
                } else {
                    $success = $api->updateDeal($this->bitrix_id, $this->getBitrixArray());
                    if ($success) {
                        $update = ['synced' => true];
                    }
                }
            }
        } catch (Bitrix24APIException $bitrix24APIException){
            Log::error("Ошибка синхронизации заявки {$this->id} с Bitrix: ".$bitrix24APIException->getMessage());
            return false;
        }
        if($deleteIt){
//            $res = true;
            $res = $this->delete();
        } elseif(!empty($update)){
            $res = $this->update($update);
        } else {
            $res = false;
        }
        return $res;
    }


    public function merge($data){
        $diff = $this->difference($data);
        if(count($diff) > 0){
            $merge = new TicketDiff(['ticket_id' => $this->id, 'json' => json_encode($diff, JSON_UNESCAPED_UNICODE)]);
            $merge->save();
        }
    }
}
