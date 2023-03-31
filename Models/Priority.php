<?php

namespace App\Models;

/**
 * @property string $title
 * @property int $pos
 */
class Priority extends BaseModel {
    protected $keyType = 'string';
    protected static $orderBy = 'pos';
}
