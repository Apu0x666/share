<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Тип услуги
 * @property string $title_id Название типа услуги
 * @property bool $actual
 * @property string $title
 */
class ServiceType extends BaseModel
{
    protected $fillable = ['title'];
    protected $appends = ['title'];
    protected $casts = [
        'actual' => 'boolean'
    ];
    protected static $orderBy = 'service_type_titles.title';
    public function getTitleAttribute(){
        $titleObject = ServiceTypeTitle::find($this->title_id);
        return $titleObject->title ?? $this->title_id;
    }

    public function serviceTypeTitle(){
        return $this->hasOne(ServiceTypeTitle::class, 'id', 'title_id');
    }

    public function setTitleAttribute($value){
        $item = ServiceTypeTitle::query()->where(['title' => trim($value)])->first();
        if(empty($item)){
            $item = new ServiceTypeTitle();
            $item->title = $value;
            $item->save();
        }
        $this->title_id = $item->id;
    }

    public function getGroupIdAttribute(){
        return $this->serviceTypeTitle->group_id;
    }

    protected static function allSortedQuery(): Builder
    {
        return static::query()
            ->select('service_types.*')
            ->where('actual','=','1')
            ->join('service_type_titles', 'service_type_titles.id', '=', 'service_types.title_id');
    }

    public static function items($addFields = []): array
    {
        $addFields[] = 'actual';
        $addFields[] = 'groupId';
        return parent::items($addFields);
    }

    /**
     * Метод для быстрого заполнения
     * @return void
     */
    public static function fillData(){
//        ServiceType::query()->update(['actual' => false]);
        $items = [
//            ['value' => 418722, 'label' => 'Контроль работоспособности аппаратных средств',],
//            ['value' => 418723, 'label' => 'Отключение/подключение и демонтаж/монтаж аппаратных средств',],
//            ['value' => 418725, 'label' => 'Установка и замена комплектующих аппаратных средств',],
//            ['value' => 418726, 'label' => 'Настройка аппаратных средств',],
//            ['value' => 418727, 'label' => 'Подключение/отключение внешних устройств',],
//            ['value' => 418730, 'label' => 'Профилактическое обслуживание аппаратных средств',],
//            ['value' => 418731, 'label' => 'Актуализация данных по обслуживаемым аппаратным средствам',],
//            ['value' => 418732, 'label' => 'Измерение параметров защитного заземления',],
//            ['value' => 418741, 'label' => 'Контроль работоспособности общего программного обеспечения',],
//            ['value' => 418743, 'label' => 'Установка общего программного обеспечения',],
//            ['value' => 418746, 'label' => 'Настройка общего программного обеспечения',],
//            ['value' => 418747, 'label' => 'Восстановление работоспособности общего программного обеспечения',],
//            ['value' => 418748, 'label' => 'Миграция общего программного обеспечения',],
//            ['value' => 418749, 'label' => 'Установка обновлений общего программного обеспечения',],
//            ['value' => 418750, 'label' => 'Контроль выполнения процедуры резервного копирования общего программного обеспечения',],
//            ['value' => 418755, 'label' => 'Контроль работоспособности баз данных',],
//            ['value' => 418757, 'label' => 'Настройка баз данных',],
//            ['value' => 418759, 'label' => 'Создание экземпляра баз данных',],
//            ['value' => 418761, 'label' => 'Восстановление баз данных',],
//            ['value' => 418762, 'label' => 'Контроль выполнения процедуры резервного копирования баз данных',],
//            ['value' => 418768, 'label' => 'Актуализация данных в Системе',],
//            ['value' => 418769, 'label' => 'Загрузка и/или изменение данных в системе',],
//            ['value' => 418770, 'label' => 'Выгрузка данных из системы',],
//            ['value' => 418771, 'label' => 'Внесение данных о навигационно-связном оборудовании в Систему',],
//            ['value' => 418772, 'label' => 'Настройка модели и логики нейронной сети Портала',],
//            ['value' => 418776, 'label' => 'Обновление картографических слоев',],
//            ['value' => 418780, 'label' => 'Контроль работоспособности специального программного обеспечения',],
//            ['value' => 418782, 'label' => 'Настройка специального программного обеспечения',],
//            ['value' => 418783, 'label' => 'Установка специального программного обеспечения',],
//            ['value' => 418784, 'label' => 'Миграция специального программного обеспечения',],
//            ['value' => 418777, 'label' => 'Мониторинг качества услуг по передаче данных от рубежей фотовидеофиксации в Систему',],
//            ['value' => 418786, 'label' => 'Удаленное консультирование пользователей Системы по телефону «горячей линии»',],
//            ['value' => 418793, 'label' => 'Удаленное консультирование пользователей Системы по электронной почте и через электронную форму',],
//            ['value' => 418788, 'label' => 'Удаленное консультирование пользователей Системы по электронной почте и через электронную форму',],
//            ['value' => 418794, 'label' => 'Удаленное консультирование пользователей Системы на площадке магазина приложений Google Play или App Store',],
//            ['value' => 418791, 'label' => 'Консультирование пользователей Системы на рабочем месте',],

        ];
        foreach ($items as $item){
            $type = new self();
            $type->title = $item['label'];
            $type->id = $item['value'];
            $type->save();
        }
    }

    public static function getIdByTitle($text, $default = null){
        $title = ServiceTypeTitle::query()->where('title', 'like', '%'.trim($text).'%')->first();
        $serviceType = empty($title)?null:ServiceType::query()->where('title_id', $title->id)->where('actual', true)->first();
        $res = empty($serviceType)?null:$serviceType->id;
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
