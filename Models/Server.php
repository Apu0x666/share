<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property $system_id
 * @property string $title
 * @property string $os
 * @property string $ip
 */
class Server extends BaseModel
{
    protected static $orderBy = 'title';

    protected static $dataTableColumns = [
        'id' => 'ID',
        'system_id' => 'АС',
        'title' => 'Название',
        'os' => 'Операционная система',
        'ip' => 'ip',
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
    ];

    /**
     * @return BelongsToMany
     */
    public function software(): BelongsToMany {
        return $this->belongsToMany(Software::class);
    }

}
