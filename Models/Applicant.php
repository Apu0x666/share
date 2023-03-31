<?php

namespace App\Models;

/**
 * @property string $bitrix_id
 * @property string $fio
 * @property string $org
 * @property string $comment
 */
class Applicant extends BaseModel
{
    protected static $orderBy = 'fio';
    protected $guarded = [];
    
    protected static $dataTableColumns = [
        'bitrix_id' => 'Bitrix ID',
        'fio' => 'ФИО Заявителя',
        'org' => 'Организация',
        'comment' => 'Комментарий',
    ];

    public function getTitleAttribute(): string {
        return "{$this->fio} ($this->org)";
    }
    
    public static function items($addFields = []): array
    {
        $addFields[] = 'bitrix_id';
        return parent::items($addFields);
    }
}
