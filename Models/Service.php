<?php

namespace App\Models;

use DateTime;

/**
 * @property integer id
 * @property integer ticket_id Заявка
 * @property integer bitrix_id ИД услуги в битриксе
 * @property integer service_type_id Тип услуги
 * @property integer serviceTypeTitle Тип услуги
 * @property string software ПО
 * @property DateTime start Дата начала
 * @property DateTime finish Дата завершения
 * @property string content Примечание
 * @property boolean synced Синхронизирована
 * @property boolean deleted Удалена
 * @property boolean last last
 * @property string version Версия
 * @property string user_id Пользователь
 *
 * @property ServiceType $serviceType
 * @property Ticket $ticket
 */
class Service extends BaseModel
{
    public $timestamps = true;
    protected $guarded = ['serviceTypeTitle'];
    protected $casts = [
        'sync' => 'boolean',
        'deleted' => 'boolean',
//        'sent' => 'boolean',
//        'start'  => 'date:d.m.Y', //'datetime:Y-m-d H:i:s',
//        'finish'  => 'date:d.m.Y', //'datetime:Y-m-d H:i:s',
    ];
    protected $dates = ['start', 'finish'];
    protected $dateFormat = 'Y-m-d';
    protected static $dictionary = [
//        'status_id' => 'status',
//        'applicant_id' => 'applicant',
//        'system_id' => 'system',
//        'source_id' => 'source',
//        'priority_id' => 'priority',
        'user_id' => 'user',
        'service_type_id' => 'service_type',
    ];

    protected static $dataTableColumns = [
//        'id',
//        'ticket_id',
        'bitrix_id' => 'ID',
        'serviceTypeTitle' => 'Услуга',
        'software',
        'start',
        'finish',
        'content',
//        'synced',
//        'deleted',
//        'last',
//        'version',
        'user_id'
    ];

    public static function getForTicketHeaders(){
        return static::getHeaders( [
//            'id',
//            'ticket_id',
            'service_type_id',
            'software',
            'start',
            'finish',
            'comment',
            'sync',
            'deleted',
            'last',
            'version',
            'user_id'
        ]);
    }

    public function serviceType(){
        return $this->hasOne(ServiceType::class, 'id', 'service_type_id');
    }

    public function ticket(){
        return $this->hasOne(Ticket::class, 'id', 'ticket_id');
    }

    public function getServiceTypeTitleAttribute(){
        return !empty($this->serviceType)?$this->serviceType->title:'';
    }
    public function getTicketBitrixIdAttribute(){
        return $this->ticket->bitrix_id;
    }
    public function setTicketBitrixIdAttribute(){

    }

    public function getSoftwareTextAttribute(){
        return $this->ticket->system->title . ' / '.$this->software;
    }

    protected $bitrixDefaults = [
        'PROPERTY_124' => "1", // Количество
        'NAME' => "-",
    ];
    protected $bitrixMap = [
        'PROPERTY_125' => 'ticketBitrixId',  //Событие (инцидент)
        'PROPERTY_107' => 'service_type_id', // Краткое описание оказанной услуги
        'PROPERTY_165' => ['field' => 'start', 'type' => 'date'], //Дата начала оказания услуги
        'PROPERTY_169' => ['field' => 'finish', 'type' => 'date'], // Дата окончания оказания услуги
        'PROPERTY_195' => 'content', // Примечание
        'PROPERTY_167' => 'softwareText'
    ];

    public function sync(){
        $arr = $this->getBitrixArray();
        print_r($arr);
        $api = $this->ticket->system->api();
        $res = $api->addServices([$arr]); print_r($res);
    }
}
