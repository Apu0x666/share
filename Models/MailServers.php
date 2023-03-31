<?php

namespace App\Models;

/**
 * @property integer $id
 * @property string $name
 * @property string $server
 * @property string $protocol
 * @property integer $port
 * @property integer $password
 * @property string $from
 * @property string $to
 * @property string theme
 */
class MailServers extends BaseModel {
    protected static $orderBy = 'name';
    protected $guarded = [];
    protected $table = 'mail_servers';
    
    protected static $dataTableColumns = [
        'name'     => 'Название',
        'server'   => 'Сервер',
        'protocol' => 'Протокол',
        'port'     => 'Порт',
        'from'     => 'Отправитель',
        'to'       => 'Получатели',
        'theme'    => 'Тема',
    ];
    
    public static function items($addFields = []): array {
        $addFields[] = 'name';
        return parent::items($addFields);
    }
    
}
