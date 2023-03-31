<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property $server_id
 * @property $type_id  // id ServiceTypeGroup
 * @property $title
 * @property $version
 * @property string $type
 * @property string $fullTitle
 */
class Software extends BaseModel
{
    protected $guarded = ['fullTitle'];
    protected static $orderBy = 'title';
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

    public function getFullTitleAttribute(){
        return "{$this->title}" . (empty($this->version)?'':" {$this->version}");
    }

    public static function items($addFields = []): array
    {
        $addFields[] = 'type_id';
        $addFields[] = 'version';
        return parent::items($addFields);
    }

    /**rve
     * @return BelongsToMany
     */
    public function servers(): BelongsToMany {
        return $this->belongsToMany(Server::class);
    }
}
