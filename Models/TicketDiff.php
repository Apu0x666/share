<?php

namespace App\Models;

use Illuminate\Support\Arr;

/**
 * @property $id
 * @property $type_id
 * @property $json
 */
class TicketDiff extends BaseModel
{
    private $types= [
        Ticket::class => 1, Service::class => 2
    ];

    protected $guarded = [];

    public function setTypeAttribute($class){
        $this->type_id = $this->types[$class] ?? null;
    }
}
