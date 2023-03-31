<?php

namespace App\Models;

/**
 * @property string $title
 */
class ServiceTypeTitle extends BaseModel
{
    public static function items($addFields = []): array
    {
        $addFields[] = 'group_id';
        return parent::items($addFields);
    }
}
