<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Базовая модель с вспомогательными функциями
 * @method self find($id) Получение объекта по ID
 */
class BaseModel extends Model
{
    use HasFactory;
    public $timestamps = false;

    /**
     * @var string Поле, по которому сортируют вспомогательные функции класса
     */
    protected static $orderBy = '';

    protected static $dictionary = [];
    protected static $dataTableColumns = [];
    protected $bitrixDefaults = [];
    protected $bitrixMap = [];

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->format('d.m.Y');
    }

    /**
     * Получение массива для items vuetify компонентов
     * @return array
     */
    public static function items($addFields = []): array
    {
        $res = [];
        foreach (static::allSorted() as $item){
            $resItem = [
                'value' => $item->id,
                'text' => $item->title,
            ];
            foreach ($addFields as $field){
                $resItem[$field] = ctype_digit($item->$field) ? intval($item->$field) : $item->$field;
            }
            $res[] = $resItem;
        }
        return $res;
    }

    protected static function allSortedQuery(): Builder
    {
        return static::query();
    }

    /**
     * Получение всех элементов, отсортированных по полю $orderBy или self::$orderBy класса
     * @param string $orderBy Поле сортировки, если не указано, то берется поле из self::$orderBy
     * @return Builder[]|Collection
     */
    public static function allSorted(string $orderBy = ''){
        $query = static::allSortedQuery();
        if(empty($orderBy)) $orderBy = static::$orderBy;
        if(!empty($orderBy)){
            $query->orderBy($orderBy)->get();
        }
        return $query->get();
    }


    public static function getColumnsInfo(){
        $ticket = new static();
        $table = $ticket->getTable();
        return DB::select("SHOW FULL COLUMNS FROM $table");

    }

    public static function getHeaders($columnsNames = []){
        $tableColumnInfos = static::getColumnsInfo();
        $columns = [];
        foreach ($tableColumnInfos as $tableColumnInfo){
            $value = $tableColumnInfo->Field;
            $text = $tableColumnInfo->Comment;
            $item = compact('value', 'text');
            if(isset(static::$dictionary[$value])) $item['dictionary'] = static::$dictionary[$value];
            $columns[$value] = $item;
        }
        if(empty($columnsNames)){
            $columnsNames = static::$dataTableColumns;
        }
        if(empty($columnsNames)){
            $res = array_values($columns);
        } else {
            $res = [];
            foreach ($columnsNames as $n => $columnName){
                if(is_string($n)){
                    if (!empty($columns[$n]['dictionary'])) {
                        $res[] = ['value' => $n, 'text' => $columnName, 'dictionary' => $columns[$n]['dictionary']];
                    } else {
                        $res[] = ['value' => $n, 'text' => $columnName];
                    }
                } else {
                    $res[] = $columns[$columnName] ?? ['value' => $columnName];
                }
            }
        }
        return $res;
    }



    protected function transformFieldToBitrix($field){
        $fieldName = $field['field'] ?? $field;
        $type = $field['type'] ?? 'default';
        $value = $this->$fieldName;
        if($type === 'date'){
            $value = (new \DateTime($value))->setTime(3,0)->format(DATE_ATOM);
        }
        return $value;
    }

    public function getBitrixArray(){
        $res = $this->bitrixDefaults;
        foreach ($this->bitrixMap as $bitrixField => $field){
            $res[$bitrixField] = $this->transformFieldToBitrix($field);
        }
        if(!empty($this->bitrix_id)){
            $res['ID'] = $this->bitrix_id;
        }
        return $res;
    }

    public function fillFromBitrixArray($data){
        foreach ($this->bitrixMap as $bitrixField => $field){
            $readonly = $field['readonly'] ?? false;
            if(!$readonly) {
                $fieldName = $field['field'] ?? $field;
                $type = $field['type'] ?? 'default';
                $value = $data[$bitrixField] ?? null;
                if($type === 'date'){
                    $value = (new \DateTime($value))->format('Y-m-d');
                }
                $this->$fieldName = $value;
            }
        }
        $this->bitrix_id = $data['ID'] ?? '';
    }

    public function difference($data){
        $difference = [];
        foreach ($this->bitrixMap as $bitrixField => $field){
            $ignore = $field['ignoreDifference'] ?? false;
            if(!$ignore) {
                $fieldValue = $this->transformFieldToBitrix($field);
                if ($data[$bitrixField] !== $fieldValue) {
                    $fieldName = $field['field'] ?? $field;
                    $difference[$fieldName] = [$data[$bitrixField], $fieldValue];
                }
            }
        }
        return $difference;
    }

    public static function findByBitrixId($id){
        return static::query()->where(['bitrix_id' => $id, 'last' => true])->first();
    }

    public static function writeBitrixErrors($errors){
        if(!empty($errors)){
            foreach ($errors as $id => $error){
                static::query()->where(['bitrix_id' => $id, 'last' => true])->update(['error' => $error]);
            }
        }
    }


    public static function getIdByTitle($text, $default=null){
        $item = static::query()->where('title', 'like', '%'.trim($text).'%')->first();
        $res = empty($item)?null:$item->id;
        if(empty($res)){
            if(empty($default)) {
                throw new \Exception("Нет такого значения в БД '$text'");
            } else {
                $res = $default;
            }
        }
        return $res;
    }
}
