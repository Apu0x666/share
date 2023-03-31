<?php
error_reporting(E_ALL);

class import {
    static $inputPath = __DIR__ .'/data/';
    static $outputPath = __DIR__.'/../data/';

    static $files = [
        'raion' => 'RAION.TXT',
        'prefix' => 'PREFIKS.TXT',
        'address' => 'ADDRESS.TXT',
        'buildings' => 'BUILDINGS.TXT',
        'buildings_hh' => 'BUILDINGS_HH.TXT',
        'town' => 'TOWN.TXT',
        'toponim' => 'TOPONIM.TXT',
        'geonim' => 'GEONIM.TXT',
        'tgeonim' => 'TGEONIM.TXT',
        'area_obj' => 'AREA_OBJ.TXT',
        'tarea_obj' => 'TAREA_OBJ.TXT',
        'subrf' => 'SUBRF.TXT'
    ];

    static $structure = [
        'raion' => ['ID', 'CODE', 'NAME', 'SNAME', 'STATUS', 'ADATE', 'DDATE', 'ADOC', 'DDOC'],
        'prefix' => ['ID', 'SUBRF', 'TOWN', 'TOPONIM', 'AREA_OBJ', 'GEONIM', 'NAME', 'SNAME', 'PNAME', 'FNAME', 'STATUS', 'ADATE', 'DDATE', 'ADOC', 'DDOC'],
        'address' => ['ID_ADDRESS', 'ID_BUILDING', 'ID_RAION', 'ID_OKRUG', 'ID_PREFIX', 'ID_GEONIM', 'HOUSE', 'CORPUS', 'LITER', 'VILLA', 'PARCEL',
            'PADDRESS', 'BASE_ADDR_FLAG', 'SPECIFICATION', 'ADATE', 'ADOC', 'DDATE', 'DDOC', 'STATUS', 'ID_SOURCE', 'CONSTRUCTION', 'BUILD_NUMBER'],
        'buildings' => ['ID_BUILDING', 'ID_ADDRESS', 'TYPE', 'BUILDING_FULLCODE', 'ID_PREFIX', 'ID_GEONIM', 'HOUSE', 'CORPUS', 'LITER', 'VILLA',
            'PARCEL', 'STATUS', 'ID_SOURCE', 'ADATE', 'CHDATE', 'INDOC', 'DDATE', 'ADOC', 'DDOC', 'ID_RAION', 'ID_OKRUG', 'PADDRESS', 'X', 'Y',
            'CONSTRUCTION', 'OAS_NAME', 'ADDRESS_ADD_INFO', 'BUILD_NUMBER'],
        'buildings_hh' => ['ID_BUILDING_EAS_OLD', 'ID_BUILDING_EAS_NEW', 'ADATE', 'ID_OPERATION'],
        'town' => ['ID', 'NAME', 'SNAME', 'PNAME', 'STATUS', 'ADATE', 'DDATE', 'ADOC', 'DDOC'],
        'toponim' => ['ID', 'NAME', 'SNAME', 'PNAME', 'STATUS', 'ADATE', 'DDATE', 'ADOC', 'DDOC'],
        'geonim' => ['ID', 'TYPE', 'NAME', 'SNAME', 'PNAME', 'STATUS', 'ADATE', 'DDATE', 'ADOC', 'DDOC'],
        'tgeonim' => ['ID', 'NAME', 'SNAME', 'PNAME', 'STATUS', 'ADATE', 'DDATE', 'ADOC', 'DDOC'],
        'area_obj' => ['ID', 'TYPE', 'NAME', 'SNAME', 'PNAME', 'STATUS', 'ADATE', 'DDATE', 'ADOC', 'DDOC'],
        'tarea_obj' => ['ID', 'NAME', 'SNAME', 'PNAME', 'STATUS', 'ADATE', 'DDATE', 'ADOC', 'DDOC'],
        'subrf' => ['ID', 'NAME', 'SNAME', 'PNAME', 'ADATE', 'DDATE', 'STATUS', 'ADOC', 'DDOC']
    ];

    static $coordinatesConversionParams = '--вписать строку конвертации--';

    public function __construct() {
        if (!file_exists(self::$outputPath)) {
            mkdir(self::$outputPath);
        }
    }

    public function run() {
        $this->getRAION();
        $this->getEASPrefix();
        $this->getEASAddressBuilding();
        $this->getEAS();
        $this->getSqlite();
        $this->getGeo();
    }

    protected function convertCoords(array &$coordinates) {
        echo 'Converting coordinates...';
        $list = '';
        foreach($coordinates as $point) {
            $list .= str_replace(',','.',sprintf('%.9f %.9f',floatval($point[1])/100,floatval($point[0])/100)).PHP_EOL;
        }
        if (@file_put_contents(self::$outputPath.'tmp.data', $list) === false) {
            throw new Exception('Can\'t create temp file');
        }
        exec('cs2cs -f %.9f '.self::$coordinatesConversionParams.' '.escapeshellarg(self::$outputPath.'tmp.data'), $output, $code);
        @unlink(self::$outputPath.'tmp.data');
        if ($code !== 0) {
            throw new Exception('Coordinates conversion failed');
        }
        if (count($output) !== count($coordinates)) {
            throw new Exception('Wrong coordinate lines count');
        }
        $i = 0;
        foreach($coordinates as &$point) {
            $tmp = explode("\t", $output[$i++]);
            if (count($tmp) < 2) {
                throw new Exception('Wrong amount of coordinates');
            }
            $point = ['lng' => floatval($tmp[0]), 'lat' => floatval($tmp[1])];
            unset($point);
        }
        echo ' Done.'.PHP_EOL;
    }

    /**
     * Reading a file by a generator
     * @param $fileName
     * @return Generator
     */

    static protected function readTheFile($fileName): Generator {
        //генератор для чтения файла
        $handle = fopen(self::$inputPath.self::$files[$fileName], "r");
        while (!feof($handle)) {
            $line = @fgets($handle);
            if ($line !== false) {
                $line = iconv('CP1251', 'UTF-8', trim($line));
                $line = explode('>', $line);
                array_walk($line, function(&$item) { $item = trim($item); });
                $struct = self::$structure[$fileName];
                if (count($line) !== count($struct)) {
                    throw new Exception('Wrong record structure');
                }
                yield array_combine($struct, $line);
            }
        }
        fclose($handle);
    }

    /**
     * Calculation of runtime and memory used
     * @param $timeStart
     * @param $memStart
     * @return void
     */
    static protected function getStatData($timeStart, $memStart) {
        echo round(microtime(TRUE) - $timeStart, 5) . 'cек/' . self::memory(memory_get_usage(), $memStart) . PHP_EOL;
    }

    /**
     * Пересчёт используемой памяти в человеко-понятный вид
     * @param $b
     * @param int $old
     * @param int $precision
     * @return string
     */
    static protected function memory($b, int $old = 0, int $precision = 2): string {
        $units = ["b","kb","mb","gb","tb"];
        $b = $b - $old;
        $bytes = max($b, 0);
        $pow = floor(($bytes ?
                log($bytes) :
                0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, $precision) . $units[$pow];
    }

    /**
     * Console progress bar
     * @param int $done
     * @param $total
     * @param $memory
     * @param null $startTime
     * @param int $barSize
     * @return void
     */
    static protected function showStatus(int $done, $total, $memory, $startTime = NULL, int $barSize = 30) {
        // if we go over our bound, just ignore it
        if ($done > $total)
            return;

        $now = time();

        $percent = (double)($done / $total);

        $bar = floor($percent * $barSize);

        $statusBar = "\r[";
        $statusBar .= str_repeat("=", $bar);
        if ($bar < $barSize) {
            $statusBar .= ">";
            $statusBar .= str_repeat(" ", $barSize - $bar);
        }
        else {
            $statusBar .= "=";
        }

        $disp = number_format($percent * 100, 0);

        $statusBar .= "] $disp%  $done/$total";

        //$rate = ($now-$start_time)/$done;
        //$left = $total - $done;
        //$eta = round($rate * $left, 2);

        $elapsed = $now - $startTime;

        $statusBar .= " " . number_format($elapsed) . "sec/" . $memory;

        echo "$statusBar  ";

        flush();

        // when done, send a newline
        if ($done == $total) {
            echo "\n";
        }
    }

    /**
     * IN: RAION.TXT
     * OUT: raion.json
     * @return void
     */
    protected function getRAION() {
        echo 'create ["raion.json"] ';

        $memStart = memory_get_usage();
        $timeStart = microtime(TRUE);

        $raionIterator = self::readTheFile('raion'); //читаем файл генератором
        $raion = []; //массив с данными для экспорта в будущий файл
        foreach ($raionIterator as $item) {
            if ($item['STATUS'] == 'A') { //Is_Actual
                //Заполняем массив по образу и поодобию
                $raionID = intval($item['ID']); //Raion_ID
                $raionName = $item['NAME']; //Raion_Name
                if ($raionID > 0) {
                    $raion[$raionID] = $raionName;
                }
            }
        }

        ksort($raion, SORT_NUMERIC);
        file_put_contents(self::$outputPath . '/raion.json', json_encode($raion, JSON_UNESCAPED_UNICODE));

        self::getStatData($timeStart, $memStart);
    }

    /**
     * IN: PREFIKS.TXT
     * OUT: tokens_prefixes.json + prefixes.json
     * @return void
     */
    protected function getEASPrefix() {
        echo 'create ["tokens_prefixes.json","prefixes.json"] ';

        $memStart = memory_get_usage();
        $timeStart = microtime(TRUE);

        $prefixIterator = self::readTheFile('prefix'); //читаем файл генератором
        $tokens = [];
        $streets = [];
        foreach ($prefixIterator as $item) {
            $prefixID = intval($item['ID']);
            $prefixPrintName = $item['PNAME'];

            $prefixPrintName = preg_replace('/^\d{6},?(\s+|$)/u', '', $prefixPrintName); //убираем индекс
            $prefixPrintName = trim(preg_replace('/([\s-]|^)(г\.)?Санкт-Петербург,?(\s+|$)/u', '', $prefixPrintName)); //убираем город
            if ($prefixPrintName === '') {
                continue;
            }

            $streets[$prefixID] = $prefixPrintName;

            $prefixPrintName = preg_replace('/(\s|^)В\\.О\\.(\s|$)/u', ' Васильевского острова ', $prefixPrintName);
            $prefixPrintName = preg_replace('/(\s|^)П\\.С\\.(\s|$)/u', ' Петроградской стороны ', $prefixPrintName);
            $prefixPrintName = preg_replace('/(\s|^)(ж\\\\д|ж\.д\.)(\s|$)/u', ' ЖД ', $prefixPrintName);
            $prefixPrintName = mb_strtolower(str_replace('ё', 'е', $prefixPrintName));
            $prefixPrintName = preg_replace('/(\s|^)(\d+)-[яй](\s|$)/u', ' $2 ', $prefixPrintName); //убираем -я
            $prefixPrintName = preg_replace('/[^a-zа-я0-9]/u', ' ', $prefixPrintName); //убираем всё, что не текст
            $prefixPrintName = trim(preg_replace('/\s+/u', ' ', $prefixPrintName)); //убираем все пробелы до одного пробела

            $tmp = explode(' ', $prefixPrintName);

            foreach ($tmp as $token) {
                if (!isset($tokens[$token])) {
                    $tokens[$token] = [$prefixID];
                }
                else {
                    $tokens[$token][] = $prefixID;
                }
            }
        }

        ksort($tokens);
        ksort($streets);
        file_put_contents(self::$outputPath . '/tokens_prefixes.json', json_encode($tokens, JSON_UNESCAPED_UNICODE));
        file_put_contents(self::$outputPath . '/prefixes.json', json_encode($streets, JSON_UNESCAPED_UNICODE));

        self::getStatData($timeStart, $memStart);
    }

    /**
     * Recursive function for finding the current id of the building
     * @param array $buildingsArr
     * @param array $buildingHistoryArr
     * @param int $buildingID
     * @return int|mixed
     */
    static protected function getActualBuildingId(array $buildingsArr, array $buildingHistoryArr, int $buildingID) {
        //если запись в истории есть
        if (isset($buildingHistoryArr[$buildingID]) && $buildingHistoryArr[$buildingID] > 0) {
            //обновляем buildingID на новый
            $newBuildingID = $buildingHistoryArr[$buildingID];

            //если здание найдено и не актуально, то ищем дальше
            if (isset($buildingsArr[$newBuildingID]) && $buildingsArr[$newBuildingID]['STATUS'] !== 'A' && $buildingsArr[$newBuildingID]['STATUS'] !== 1) {
                return self::getActualBuildingId($buildingsArr, $buildingHistoryArr, $newBuildingID);
            }

            return $newBuildingID;
        }
        //записи в истории нет, возвращаем его же
        return $buildingID;
    }

    /**
     * IN: ADDRESS.TXT + BUILDINGS.TXT + BUILDINGS_HH.TXT
     * OUT: tokens_addresses.json + addresses.json + addresses_prefixes.json + addresses_buildings.json + buildings.json
     * @return void
     */
    protected function getEASAddressBuilding()
    {
        echo 'create ["tokens_addresses.json","addresses.json","addresses_prefixes.json","addresses_buildings.json","buildings.json"] ' . PHP_EOL;
        $startTime = time();
        $addresses = [];

        $addressIterator = self::readTheFile('address'); //читаем файл генератором
        foreach ($addressIterator as $item) { //парсим адреса
            if ($item['STATUS'] === 'A') { //только актуальные
                $printAddress = mb_strtolower($item['PADDRESS']);
                if (!isset($addresses[$printAddress]['source_id']) || $addresses[$printAddress]['source_id'] > intval($item['ID_SOURCE'])) {
                    //здесь происходит group by $printAddress + поиск минимального source_id
                    $addresses[$printAddress]['source_id'] = intval($item['ID_SOURCE']);
                }
            }
        }

        $addressIterator = self::readTheFile('address'); //читаем файл генератором
        foreach ($addressIterator as $item) {//ещё раз парсим адреса
            if ($item['STATUS'] === 'A') {
                $printAddress = mb_strtolower($item['PADDRESS']);
                //продолжаем работать по схеме group_by $printAddress, и вставляем в уже существующий массив с прошлого шага MAX($id_address)
                if (
                    (
                        empty($addresses[$printAddress]['id_address'])
                        ||
                        $addresses[$printAddress]['id_address'] < intval($item['ID_ADDRESS'])
                    )
                    &&
                    @$addresses[$printAddress]['source_id'] === intval($item['ID_SOURCE'])
                ) {
                    //в итоге у нас будет массив $addresses['Мой адрес не дом и не улица']['id_address'] = 123123
                    $addresses[$printAddress]['id_address'] = intval($item['ID_ADDRESS']);
                }
            }
        }

        //флип массивов для дальнейшего, удобного поиска по ключу, который будет являться id_address
        $neededIdAddress = array_column($addresses, 'id_address');
        $neededIdAddress = array_flip($neededIdAddress);

        $buildingsArr = [];
        $coords = [];
        $buildingsIterator = self::readTheFile('buildings'); //читаем файл генератором
        foreach ($buildingsIterator as $item) {//парсим buildings
            //собираем массив, с id_building в индексе, и необходимыми значениями
            $row = [
                'STATUS' => $item['STATUS'],
                'building_address' => $item['PADDRESS']
            ];
            if (intval($item['X']) > 0 && intval($item['Y']) > 0) {
                $coords[$item['ID_BUILDING']] = [$item['X'], $item['Y']];
            }
            $buildingsArr[$item['ID_BUILDING']] = $row;
        }

        $this->convertCoords($coords);
        foreach ($coords as $id => $point) {
            $buildingsArr[$id]['lng'] = $point['lng'];
            $buildingsArr[$id]['lat'] = $point['lat'];
        }
        unset($coords);


        $buildingsHistoryParsedArr = [];
        $buildingsHistoryIterator = self::readTheFile('buildings_hh'); //читаем файл генератором
        foreach ($buildingsHistoryIterator as $item) {
            //собираем историю обновлений номера здания
            $buildingsHistoryParsedArr[$item['ID_BUILDING_EAS_OLD']] = $item['ID_BUILDING_EAS_NEW'];//в виде старый = новый
        }

        $addressIterator = self::readTheFile('address'); //читаем файл генератором
        $i = 0;
        $adressesFull = [];
        foreach ($addressIterator as $item) {
            //снова парсим адреса для сбора финального массива перед созданием файлов
            if (isset($neededIdAddress[$item['ID_ADDRESS']])) {
                //здесь проверка что текущий id_address нам нужен
                $actID = self::getActualBuildingId($buildingsArr, $buildingsHistoryParsedArr, $item['ID_BUILDING']);
                //попытка поиска более актуального id здания
                if (!empty($actID) && isset($buildingsArr[$actID])) {
                    //если всё норм, и вообще номер этого здания существует
                    $adressesFull[] = [
                        'id_address' => $item['ID_ADDRESS'],
                        'id_prefix' => $item['ID_PREFIX'],
                        'print_address' => $item['PADDRESS'],
                        'id_building' => $actID,
                        'building_address' => $buildingsArr[$actID]['building_address'],
                        'building_lng' => $buildingsArr[$actID]['lng'] ?? 0.0,
                        'building_lat' => $buildingsArr[$actID]['lat'] ?? 0.0
                    ];
                }
                $i++;
            }
        }

        $tokens = [];
        $addresses = [];
        $buildings = [];
        $addressesBuildings = [];
        $addressesPrefixes = [];
        $total = count($adressesFull);
        foreach ($adressesFull as $i => $row) {
            $id = intval($row['id_address']);
            $str = $row['print_address'];
            $bldId = intval($row['id_building']);
            $prefId = intval($row['id_prefix']);
            $bld = @$row['building_address'];
            $lon = intval($row['building_lng'] * 10000000);
            $lat = intval($row['building_lat'] * 10000000);

            $str = preg_replace('/^\d{6},?(\s+|$)/u', '', $str); //убираем индекс
            $str = preg_replace('/([\s-]|^)(г\.)?Санкт-Петербург,?(\s+|$)/u', '', $str); //убираем город

            $addresses[$id] = $str;
            $addressesPrefixes[$id] = $prefId;

            $bld = preg_replace('/^\d{6},?(\s+|$)/u', '', $bld); //убираем индекс
            $bld = preg_replace('/([\s-]|^)(г\.)?Санкт-Петербург,?(\s+|$)/u', '', $bld); //убираем город

            $tmp = ['a' => $bld];
            if ($lat !== 0 && $lon !== 0) {
                $tmp['t'] = $lat;
                $tmp['n'] = $lon;
            }
            $buildings[$bldId] = $tmp;

            $addressesBuildings[$id] = $bldId;

            $str = preg_replace('/(\s|^)В\\.О\\.(\s|$)/u',' Васильевского острова ', $str);
            $str = preg_replace('/(\s|^)П\\.С\\.(\s|$)/u',' Петроградской стороны ', $str);
            $str = preg_replace('/(\s|^)(ж\\\\д|ж\.д\.)(\s|$)/u',' ЖД ', $str);
            $str = mb_strtolower(str_replace('ё', 'е', $str));
            $str = preg_replace('/(\s|^)(\d+)-[яй](\s|$)/u', ' $2 ', $str); //убираем -я
            $str = preg_replace('/[^a-zа-я0-9]/u', ' ', $str); //убираем всё, что не текст
            $str = trim(preg_replace('/\s+/u', ' ', $str)); //убираем все пробелы до одного пробела

            $tmp = explode(' ', $str);

            foreach($tmp as $token) {
                if (!isset($tokens[$token])) {
                    $tokens[$token] = [$id];
                } else {
                    $tokens[$token][] = $id;
                }
            }

            if ($i % 5000 === 0 && $i > 0) {
                self::showStatus($i+1, $total, self::memory(memory_get_usage()), $startTime, $size=30);
            }
        }
        if ($i % 5000 !== 0) {
            self::showStatus($i+1, $total, self::memory(memory_get_usage()), $startTime, $size=30);
        }

        ksort($tokens);
        ksort($addresses);
        ksort($addressesPrefixes);
        ksort($addressesBuildings);
        ksort($buildings);

        file_put_contents(self::$outputPath . '/tokens_addresses.json', json_encode($tokens, JSON_UNESCAPED_UNICODE));
        file_put_contents(self::$outputPath . '/addresses.json', json_encode($addresses, JSON_UNESCAPED_UNICODE));
        file_put_contents(self::$outputPath . '/addresses_prefixes.json', json_encode($addressesPrefixes, JSON_UNESCAPED_UNICODE));
        file_put_contents(self::$outputPath . '/addresses_buildings.json', json_encode($addressesBuildings, JSON_UNESCAPED_UNICODE));
        file_put_contents(self::$outputPath . '/buildings.json', json_encode($buildings, JSON_UNESCAPED_UNICODE));
    }

    /**
     * IN: TOWN.TXT TOPONIM.TXT GEONIM.TXT TGEONIM.TXT AREA_OBJ.TXT TAREA_OBJ.TXT
     * OUT: eas.json eas_tables.json
     * @return void
     */
    protected function getEAS() {
        echo 'create ["eas.json","eas_tables.json"] ';

        //замеры времени и памяти
        $memStart = memory_get_usage();
        $timeStart = microtime(TRUE);

        $results = []; //массив на финалочку

        //***********
        // ГОРОДА
        //***********
        $townIterator = self::readTheFile('town'); //читаем файл генератором
        $towns = []; //массив с данными для экспорта в будущий файл
        foreach ($townIterator as $item) {
            if ($item['STATUS'] == 'A') { //Is_Actual
                //Заполняем массив по образу и поодобию
                $townID = intval($item['ID']); //Town_ID
                $townPrintName = $item['PNAME']; //Print_Name
                if (!empty($townID) && !empty(trim($townPrintName))) {
                    $towns[] = [
                        'town_id' => $townID,
                        'print_name' => $townPrintName,
                    ];
                }
            }
        }
        foreach ($towns as $row) {
            $n = ' '.trim($row['print_name']).' ';
            $n = str_replace(['Ё','Е'], ['ё','е'], $n);
            //убираем лишнее
            if (mb_strtolower($n) === ' неизвестно ') {
                continue;
            }
            if (mb_strpos(mb_strtolower($n), 'петербург') !== false) {
                continue;
            }
            $i = mb_strpos($n, ',');
            if (($i === false) && (mb_strpos($n, ' район ') !== false)) {
                continue;
            }
            if (mb_strpos($n, ' муниципальный округ ') !== false) {
                continue;
            }
            $region = '';
            if ($i !== false) { //если была запятая, то это указание района
                $region = trim(str_replace('район', '', mb_substr($n, 0, $i)));
                $i = mb_strrpos($n, ','); //если такая ситуация "Лужский район, г Луга, кп Зеленый Бор", г Луга выкидываем
                $n = ' '.trim(mb_substr($n, $i+1)).' ';
            }
            //предварительная очистка, чтобы снизить вероятность ошибок после удаления разделителей
            $n = preg_replace('/\sж\/д\s/ui', ' ', $n);
            $n = preg_replace('/\sст\.\s?/ui', ' ', $n);
            //оставляем только alphanumeric
            $n = preg_replace('/[^\d\w]/u', ' ', $n);
            //определяем типы (в порядке упрощения, если один тип может включать в себя другой)
            $types = [
                '(пос(е|ё)лок\s+городского\s+типа|пгт)' => 'посёлок',
                '(курортный\s+пос(е|ё)лок|к\s+п|кп)' => 'посёлок',
                '((пос(е|ё)лок)|пос|п)' => 'посёлок',
                '(город|г)' => 'город',
                '(деревня|дер|д)' => 'посёлок',
                '(село|сел|с)' => 'посёлок',
                '(пансионат|панс)' => 'пансионат'
            ];
            $nType = '';
            $nPattern = '';
            foreach($types as $pattern => $type) {
                if (preg_match('/\s'.$pattern.'\s/ui', $n)) {
                    $nType = $type;
                    $nPattern = $pattern;
                    break;
                }
            }
            //зачищаем строку
            if (trim($nType) !== '') {
                $n = preg_replace('/\s'.$nPattern.'\s/ui', ' ', $n);
            } else {
                $nType = 'notype';
            }
            $n = trim(preg_replace('/\s+/u', ' ', $n));
            $tmp = [
                'id' => intval($row['town_id']),
                'name' => $n,
                'table' => 'town'
            ];
            if ($region !== '') {
                $tmp['region'] = $region;
            }
            $results[$nType][] = $tmp;
        }

        //***********
        // ТОПОНИМЫ
        //***********
        $toponimIterator = self::readTheFile('toponim'); //читаем файл генератором
        $toponims = []; //массив с данными для экспорта в будущий файл
        foreach ($toponimIterator as $item) {
            if ($item['STATUS'] == 'A') { //Is_Actual
                //Заполняем массив по образу и поодобию
                $toponimID = intval($item['ID']); //Town_ID
                $toponimPrintName = $item['PNAME']; //Print_Name
                if (!empty($toponimID)) {
                    $toponims[] = [
                        'toponim_id' => $toponimID,
                        'toponim_print_name' => $toponimPrintName,
                    ];
                }
            }
        }
        foreach ($toponims as $row) {
            $n = ' '.trim($row['toponim_print_name']).' ';
            $n = str_replace(['Ё','Е'], ['ё','е'], $n);
            //очищаем лишние включения
            $n = preg_replace('/-[йяе]\s/ui', ' ', $n);
            $n = preg_replace('/\s(отд|дер|пос)\./ui', ' ', $n);
            //оставляем только alphanumeric
            $n = preg_replace('/[^\d\w]/u', ' ', $n);
            $n = trim(preg_replace('/\s+/u', ' ', $n));
            if ($n !== 'Неизвестно') {
                $results['notype'][] = [
                    'id' => intval($row['toponim_id']),
                    'name' => $n,
                    'table' => 'toponim'
                ];
            }
        }

        //***********
        // ГЕОНИМЫ
        //***********
        $geonimTypeIterator = self::readTheFile('tgeonim'); //читаем файл генератором
        $geonimTypes = []; //массив с данными для экспорта в будущий файл
        foreach ($geonimTypeIterator as $item) {
            $geonimTypeID = intval($item['ID']); //geonim id
            $geonimTypePrintName = $item['PNAME']; //Print_Name
            if (!empty($geonimTypeID) && trim($geonimTypePrintName) !== '') {
                $geonimTypes[$geonimTypeID] = $geonimTypePrintName;
            }
        }

        $geonimIterator = self::readTheFile('geonim'); //читаем файл генератором
        $geonims = []; //массив с данными для экспорта в будущий файл
        foreach ($geonimIterator as $item) {
            //Заполняем массив по образу и поодобию
            $geonimID = intval($item['ID']); //geonim_id
            $geonimTypeID = intval($item['TYPE']); //geonim_type_id
            $geonimPrintName = $item['PNAME']; //Print_Name
            if ($geonimPrintName === '') {
                continue;
            }
            $geonims[$geonimID] = [
                'geonim_id' => $geonimID,
                'type_name' => $geonimTypes[$geonimTypeID] ?? NULL,
                'geonim_print_name' => $geonimPrintName,
            ];
            /*
            $geonims[$geonim_id]['toponim_id']  //ID
            $geonims[$geonim_id]['geonim_print_name']] //Name
            $geonims[$geonim_id]['type_name']   //'Type'
            */
            $n = ' ' . trim($geonims[$geonimID]['geonim_print_name']) . ' ';
            $n = str_replace(['Ё','Е'], ['ё','е'], $n);
            //убираем тип
            if (!$geonims[$geonimID]['type_name']) { //если типа нет, пытаемся дополнить
                $types = [
                    'поле',
                    'мост',
                    'канал',
                    'коса',
                    'слобода',
                    'кольцо',
                    'проток'
                ];
                foreach ($types as $type) {
                    if (mb_strpos($n, ' ' . $type . ' ') !== FALSE) {
                        $geonims[$geonimID]['type_name'] = $type;
                        break;
                    }
                }
            }
            $n = ' ' . trim(str_replace(' ' . $geonims[$geonimID]['type_name'] . ' ', ' ', $n)) . ' ';
            $n = preg_replace('/-[йяе]\s/ui', ' ', $n);
            $replace = [
                ' реки ' => ' ',
                ' В.О. ' => ' ВО ',
                ' П.С. ' => ' ПС ',
                ' на ' => ' ',
                ' канала ' => ' ',
                ' пруда ' => ' ',
                ' вокзала ' => ' ',
                ' канавки ' => ' ',
                ' ж/д ' => ' ',
                ' направления ' => ' ',
                ' в ' => ' ',
                ' к ' => ' ',
                ' гавань ' => ' '
            ];
            $n = str_replace(' ', '  ', $n); //удваиваем пробелы, чтобы strtr сработал как нужно
            $n = strtr($n, $replace);
            $n = preg_replace('/\s+/u', ' ', $n);
            //добавляем варианты написания
            $alt = [];
            $str = trim($n);
            if (substr_count($str, ' ') === 1) {
                $first = strtok($str, ' ');
                $last = strtok(' ');
                if (!preg_match('/^\d+$/u', $first) && ($last !== 'ПС') && ($last !== 'ВО')) {
                    switch (mb_strtolower($first)) {
                        case 'адмирала':
                            $alt[] = 'Адм ' . $last;
                            break;
                        case 'академика':
                            $alt[] = 'Ак ' . $last;
                            break;
                        case 'профессора':
                            $alt[] = 'Проф ' . $last;
                            break;
                        case 'красного':
                        case 'красных':
                            $alt[] = 'Кр ' . $last;
                            break;
                        case 'новая':
                        case 'новые':
                            $alt[] = 'Нов ' . $last;
                            break;
                        case 'пролетарской':
                            $alt[] = 'Прол ' . $last;
                            break;
                        case 'путешественника':
                            $alt[] = 'Пут ' . $last;
                            break;
                        case 'большая':
                        case 'большой':
                            $alt[] = 'Бол ' . $last;
                            break;
                        case 'малая':
                        case 'малой':
                        case 'малый':
                        case 'малые':
                            $alt[] = 'Мал ' . $last;
                            break;
                        case 'средней':
                        case 'средняя':
                            $alt[] = 'Ср ' . $last;
                            break;
                    }
                    $alt[] = mb_substr($first, 0, 1) . ' ' . $last;
                }
            }
            if (substr_count($str, '-') === 1) {
                $tmp = mb_strtolower(str_replace('-', '', $str));
                $alt[] = mb_strtoupper(mb_substr($tmp, 0, 1)) . mb_substr($tmp, 1);
            }

            $n = preg_replace('/[^\d\w]/u', ' ', $n);
            $n = trim(preg_replace('/\s+/u', ' ', $n));
            $r = [
                'id' => intval($geonims[$geonimID]['geonim_id']),
                'name' => $n,
                'table' => 'geonim'
            ];
            if ($geonims[$geonimID]['type_name']) {
                $results[$geonims[$geonimID]['type_name']][] = $r;
                foreach ($alt as $name) {
                    $r['name'] = $name;
                    $results[$geonims[$geonimID]['type_name']][] = $r;
                }
            }
            else {
                $results['notype'][] = $r;
                foreach ($alt as $name) {
                    $r['name'] = $name;
                    $results['notype'][] = $r;
                }
            }
        }

        //******************
        // ПЛОЩАДНЫЕ ОБЪЕКТЫ
        //******************
        $areaObjectTypeIterator = self::readTheFile('tarea_obj'); //читаем файл генератором
        $areaObjectTypes = []; //массив с данными для экспорта в будущий файл
        foreach ($areaObjectTypeIterator as $item) {
            $areaObjectTypeID = intval($item['ID']); //area_object_type_ID
            $areaObjectTypePrintName = $item['PNAME']; //area_object_type_Print_Name
            if (!empty($areaObjectTypeID) && trim($areaObjectTypePrintName) !== '') {
                $areaObjectTypes[$areaObjectTypeID] = $areaObjectTypePrintName;
            }
        }

        $areaObjectIterator = self::readTheFile('area_obj'); //читаем файл генератором
        $areaObjects = []; //массив с данными для экспорта в будущий файл
        foreach ($areaObjectIterator as $item) {
            $areaObjectID = intval($item['ID']); //area_object_id
            $areaObjectTypeID = intval($item['TYPE']); //area_object_type_id
            $areaObjectPrintName = $item['PNAME']; // area_object_print_name
            if ($areaObjectPrintName === '') {
                continue;
            }
            $areaObjects[$areaObjectID] = [
                'area_object_id' => $areaObjectID,
                'area_object_type_name' => $areaObjectTypes[$areaObjectTypeID] ?? NULL,
                'area_object_print_name' => $areaObjectPrintName,
            ];

            /*
                $area_objects[$area_object_id]['area_object_id']  //ID
                $area_objects[$area_object_id]['area_object_print_name'] //Name
                $area_objects[$area_object_id]['area_object_type_name']   //'Type'
                */
            
            $areaObject = &$areaObjects[$areaObjectID];

            $n = ' ' . trim($areaObject['area_object_print_name']) . ' ';
            $n = str_replace(['Ё','Е'], ['ё','е'], $n);
            $areaObject['area_object_type_name'] = str_replace(['Ё','Е'], ['ё','е'], $areaObject['area_object_type_name']);
            if (!$areaObject['area_object_type_name']) { //если типа нет, пытаемся понять, если возможно
                if (mb_strpos($n, ' садоводство ') !== FALSE) {
                    $areaObject['area_object_type_name'] = 'садоводство';
                }
                if (mb_strpos($n, ' ДКС ') !== FALSE) {
                    $areaObject['area_object_type_name'] = 'ДКС';
                }
                if (mb_strpos($n, ' берег ') !== FALSE) {
                    $areaObject['area_object_type_name'] = 'берег';
                }
            }
            $n = ' ' . trim(str_replace(' ' . $areaObject['area_object_type_name'] . ' ', ' ', $n)) . ' ';
            $n = str_replace('-й км ', ' ', $n);
            $n = preg_replace('/-[йяе]\s/ui', ' ', $n);
            $n = str_replace(['"','-'], ' ', $n);
            $replace = [
                ' В.О. ' => ' ВО ',
                ' П.С. ' => ' ПС ',
                ' II ' => ' 2 ',
                ' реки ' => ' ',
                ' от ст. ' => ' ',
                ' ул.' => ' ',
                ' ст.' => ' ',
                ' пр.' => ' ',
                ' кан.' => ' ',
                ' пос.' => ' ',
                ' дор.' => ' ',
                ' р.' => ' ',
                ' п.' => ' ',
                ' полукольцо ' => ' ',
                ' вокзал ' => ' ',
                ' шоссе ' => ' ',
                ' мост ' => ' ',
                ' участок ж. д. ' => ' ',
                ' и ' => ' ',
                ' на ' => ' ',
                ' в ' => ' '
            ];
            $n = str_replace(' ', '  ', $n); //удваиваем пробелы, чтобы strtr сработал как нужно
            $n = strtr($n, $replace);
            $n = preg_replace('/\s+/u', ' ', $n);
            $n = preg_replace_callback('/\s(\w\.){3,}\s/ui', function($matches) {
                return str_replace('.', '', $matches[0]);
            }, $n);
            $n = preg_replace('/[^\d\w]/u', ' ', $n);
            $n = trim(preg_replace('/\s+/u', ' ', $n));
            //заменяем типы на упрощенные
            $replace = [
                'тер. СНТ' => 'садоводство',
                'тер. НСТ' => 'садоводство',
                'тер. ТСН' => 'садоводство',
                'СНТ' => 'садоводство',
                'огородничество' => 'садоводство',
                'дачный посёлок' => 'посёлок',
                'деревня' => 'посёлок',
                'участок ж.д.' => 'участок ж/д'
            ];
            $areaObject['area_object_type_name'] = strtr($areaObject['area_object_type_name'], $replace);
            if ($areaObject['area_object_type_name']) {
                $results[$areaObject['area_object_type_name']][] = [
                    'id'    => intval($areaObject['area_object_id']),
                    'name'  => $n,
                    'table' => 'area'
                ];
            }
            else {
                $results['notype'][] = [
                    'id'    => intval($areaObject['area_object_id']),
                    'name'  => $n,
                    'table' => 'area'
                ];
            }
            unset($areaObject);
        }

        ksort($results);
        $easTables = array_fill_keys(array_keys($results), []);
        foreach($results as $type => &$list) {
            foreach($list as $res) {
                if (!in_array($res['table'], $easTables[$type])) {
                    $easTables[$type][] = $res['table'];
                }
            }
        }
        file_put_contents(self::$outputPath . '/eas.json',json_encode($results, JSON_UNESCAPED_UNICODE));
        file_put_contents(self::$outputPath . '/eas_tables.json',json_encode($easTables, JSON_UNESCAPED_UNICODE));

        self::getStatData($timeStart, $memStart);
    }

    /**
     * IN: ADDRESS.TXT + BUILDINGS.TXT + BUILDINGS_HH.TXT + PREFIKS.TXT +
     *     + GEONIM.TXT + AREA_OBJ.TXT + TOWN.TXT + TOPONIM.TXT + SUBRF.TXT
     * OUT: eas.db
     * @return void
     */
    protected function getSqlite() {
        $selectStart = time();
        echo PHP_EOL.'select [adresses] for eas.db START'.PHP_EOL;

        $addresses = []; //временный массив из которого потом возьмём колонку id_address

        //выборка ниже практически аналогична getEASAddressBuilding

        $addressIterator = self::readTheFile('address'); //читаем файл генератором
        foreach ($addressIterator as $item) {
            if ($item['STATUS'] == 'A') { //Is_Actual
                $printAddress = mb_strtolower($item['PADDRESS']);
                if (!isset($addresses[$printAddress]['source_id']) || $addresses[$printAddress]['source_id'] > intval($item['ID_SOURCE'])) {
                    $addresses[$printAddress]['source_id'] = intval($item['ID_SOURCE']);  //$addresses[Print_Address]['source_id'] = Source_ID
                }
            }
        }

        $addressIterator = self::readTheFile('address'); //читаем файл генератором
        foreach ($addressIterator as $item) {
            if ($item['STATUS'] == 'A') { //Is_Actual
                $printAddress = mb_strtolower($item['PADDRESS']);
                if (
                    (
                        empty($addresses[$printAddress]['id_address'])
                        ||
                        $addresses[$printAddress]['id_address'] < intval($item['ID_ADDRESS'])
                    )
                    &&
                    $addresses[$printAddress]['source_id'] === intval($item['ID_SOURCE'])
                ) {
                    $addresses[$printAddress]['id_address'] = intval($item['ID_ADDRESS']);
                }
            }
        }

        $neededIdAddress = array_column($addresses, 'id_address');
        $neededIdAddress = array_flip($neededIdAddress);
        unset($addresses);

        $buildingsArr = [];
        $coords = [];
        $buildingsIterator = self::readTheFile('buildings'); //читаем файл генератором
        foreach ($buildingsIterator as $item) {
            $row = [
                'STATUS' => $item['STATUS']
            ];
            if (intval($item['X']) > 0 && intval($item['Y']) > 0) {
                $coords[$item['ID_BUILDING']] = [$item['X'], $item['Y']];
            }
            $buildingsArr[$item['ID_BUILDING']] = $row;
        }

        $this->convertCoords($coords);
        foreach ($coords as $id => $point) {
            $buildingsArr[$id]['lng'] = $point['lng'];
            $buildingsArr[$id]['lat'] = $point['lat'];
        }
        unset($coords);

        $buildingsHistoryArr = [];
        $buildingsHistoryIterator = self::readTheFile('buildings_hh'); //читаем файл генератором
        foreach ($buildingsHistoryIterator as $item) {
            $buildingsHistoryArr[$item['ID_BUILDING_EAS_OLD']] = $item['ID_BUILDING_EAS_NEW'];
        }

        $addressIterator = self::readTheFile('address'); //читаем файл генератором
        $i = 0;
        $adressesFull = [];
        foreach ($addressIterator as $item) {
            if (isset($neededIdAddress[$item['ID_ADDRESS']])) {
                $actID = self::getActualBuildingId($buildingsArr, $buildingsHistoryArr, $item['ID_BUILDING']);
                if (!empty($actID) && isset($buildingsArr[$actID])) {
                    $adressesFull[$item['ID_ADDRESS']] = [
                        'Address_ID' => $item['ID_ADDRESS'],
                        'Source_ID' => $item['ID_SOURCE'] ?? 'NULL',
                        'Print_Address' => $item['PADDRESS'],
                        'House' => $item['HOUSE'],
                        'Corpus' => $item['CORPUS'],
                        'Liter' => $item['LITER'],
                        'Villa' => $item['VILLA'],
                        'Parcel' => $item['PARCEL'],
                        'Construction' => $item['CONSTRUCTION'],
                        'Build_Number' => $item['BUILD_NUMBER'],
                        'Raion_ID' => $item['ID_RAION'],
                        'Building_ID' => $actID,
                        'Actual_Building' => $buildingsArr[$actID]['STATUS'],
                        'Longitude' => $buildingsArr[$actID]['lng'] ?? NULL,
                        'Latitude' => $buildingsArr[$actID]['lat'] ?? NULL,
                        'Prefix_ID' => $item['ID_PREFIX']
                    ];
                }
                $i++;
            }
        }

        $geonimsArr = [];
        $geonims = self::readTheFile('geonim'); //читаем файл генератором
        foreach ($geonims as $item) {
            $geonimsArr[$item['ID']] = trim($item['PNAME']);
        }
        $areaObjArr = [];
        $areaObj = self::readTheFile('area_obj'); //читаем файл генератором
        foreach ($areaObj as $item) {
            $areaObjArr[$item['ID']] = trim($item['PNAME']);
        }
        $townsArr = [];
        $towns = self::readTheFile('town'); //читаем файл генератором
        foreach ($towns as $item) {
            $townsArr[$item['ID']] = trim($item['PNAME']);
        }
        $toponimsArr = [];
        $toponims = self::readTheFile('toponim'); //читаем файл генератором
        foreach ($toponims as $item) {
            $toponimsArr[$item['ID']] = trim($item['PNAME']);
        }

        //дособираем данные по prefix_id
        $prefixes = [];
        $prefixIterator = self::readTheFile('prefix'); //читаем файл генератором
        foreach ($prefixIterator as $item) {
            $prefixes[$item['ID']] = [
                'Geonim_ID' => $item['GEONIM'],
                'Area_Object_ID' => $item['AREA_OBJ'],
                'Town_ID' => $item['TOWN'],
                'Toponim_ID' => $item['TOPONIM'],
                'Prefix_Print_Name' => $item['PNAME'],
                'Geonim_Print_Name' => $geonimsArr[$item['GEONIM']] ?? NULL,
                'Area_Object_Print_Name' => $areaObjArr[$item['AREA_OBJ']] ?? NULL,
                'Town_Print_Name' => $townsArr[$item['TOWN']] ?? NULL,
                'Toponim_Print_Name' => $toponimsArr[$item['TOPONIM']] ?? NULL,
            ];
        }

        //допишем все данные по prefix_id в соот-щий элемент выборки адресов
        foreach ($adressesFull as $item) {
            if (isset($item['Prefix_ID'])) {
                $adressesFull[$item['Address_ID']] += $prefixes[$item['Prefix_ID']] ?? [];
            }
        }
        //сколько потратили на выборку ?
        $time = time() - $selectStart;
        echo 'select [adresses] for eas.db END '.$time.'sec/'. self::memory(memory_get_usage()).PHP_EOL;

        echo 'create eas.db'.PHP_EOL;
        @unlink(self::$outputPath . '/eas.db');
        $lt = new SQLite3(self::$outputPath . '/eas.db');
        //создадим таблицу без auto increment, используем primary key
        $lt->exec('CREATE TABLE addresses (
            Address_ID INT PRIMARY KEY,
            Source_ID INT,
            Building_ID INT,
            Actual_Building INT,
            Print_Address TEXT,
            Longitude REAL,
            Latitude REAL,
            House TEXT,
            Corpus TEXT,
            Liter TEXT,
            Villa TEXT,
            Parcel TEXT,
            Construction TEXT,
            Build_Number TEXT,
            Geonim_ID INT,
            Area_Object_ID INT,
            Town_ID INT,
            Toponim_ID INT,
            Prefix_Print_Name TEXT,
            Geonim_Print_Name TEXT,
            Area_Object_Print_Name TEXT,
            Town_Print_Name TEXT,
            Toponim_Print_Name TEXT,
            Raion_ID INT,
            Prefix_ID INT
        ) WITHOUT ROWID;');

        $i = 0;
        $query = '';
        $total = count($adressesFull);
        $start_time = time();
        foreach($adressesFull as $row) {
            //перебираем выборку, у source_id своя проверка
            $row['Address_ID'] = empty($row['Address_ID']) ? 'NULL' : intval($row['Address_ID']);
            $row['Source_ID'] = ($row['Source_ID'] == 'NULL') ? 'NULL' : (intval($row['Source_ID']) < 1 ? 1 : intval($row['Source_ID'])); //источники менее 1 приравниваем к 1
            $row['Building_ID'] = empty($row['Building_ID']) ? 'NULL' : intval($row['Building_ID']);
            $row['Actual_Building'] = !isset($row['Actual_Building']) ? 'NULL' : intval($row['Actual_Building']);
            $row['Print_Address'] = empty($row['Print_Address']) ? 'NULL' : '\''.SQLite3::escapeString($row['Print_Address']).'\'';

            $row['Longitude'] = empty($row['Longitude']) ? 'NULL' : floatval($row['Longitude']);
            $row['Latitude'] = empty($row['Latitude']) ? 'NULL' : floatval($row['Latitude']);

            $row['House'] = empty($row['House']) ? 'NULL' : '\''.SQLite3::escapeString(mb_strtolower(trim($row['House']))).'\'';
            $row['Corpus'] = empty($row['Corpus']) ? 'NULL' : '\''.SQLite3::escapeString(mb_strtolower(trim($row['Corpus']))).'\'';
            $row['Liter'] = empty($row['Liter']) ? 'NULL' : '\''.SQLite3::escapeString(mb_strtolower(trim($row['Liter']))).'\'';
            $row['Villa'] = empty($row['Villa']) ? 'NULL' : '\''.SQLite3::escapeString(mb_strtolower(trim($row['Villa']))).'\'';
            $row['Parcel'] = empty($row['Parcel']) ? 'NULL' : '\''.SQLite3::escapeString(mb_strtolower(trim($row['Parcel']))).'\'';
            $row['Construction'] = empty($row['Construction']) ? 'NULL' : '\''.SQLite3::escapeString(mb_strtolower(trim($row['Construction']))).'\'';
            $row['Build_Number'] = empty($row['Build_Number']) ? 'NULL' : '\''.SQLite3::escapeString(mb_strtolower(trim($row['Build_Number']))).'\'';
            $row['Geonim_ID'] = empty($row['Geonim_ID']) ? 'NULL' : intval($row['Geonim_ID']);
            $row['Area_Object_ID'] = empty($row['Area_Object_ID']) ? 'NULL' : intval($row['Area_Object_ID']);
            $row['Town_ID'] = empty($row['Town_ID']) ? 'NULL' : intval($row['Town_ID']);
            $row['Toponim_ID'] = empty($row['Toponim_ID']) ? 'NULL' : intval($row['Toponim_ID']);
            $row['Prefix_Print_Name'] = empty($row['Prefix_Print_Name']) ? 'NULL' : '\''.SQLite3::escapeString($row['Prefix_Print_Name']).'\'';
            $row['Geonim_Print_Name'] = empty($row['Geonim_Print_Name']) ? 'NULL' : '\''.SQLite3::escapeString($row['Geonim_Print_Name']).'\'';
            $row['Area_Object_Print_Name'] = empty($row['Area_Object_Print_Name']) ? 'NULL' : '\''.SQLite3::escapeString($row['Area_Object_Print_Name']).'\'';
            $row['Town_Print_Name'] = empty($row['Town_Print_Name']) ? 'NULL' : '\''.SQLite3::escapeString($row['Town_Print_Name']).'\'';
            $row['Toponim_Print_Name'] = empty($row['Toponim_Print_Name']) ? 'NULL' : '\''.SQLite3::escapeString($row['Toponim_Print_Name']).'\'';
            $row['Raion_ID'] = empty($row['Raion_ID']) ? 'NULL' : intval($row['Raion_ID']);
            $row['Prefix_ID'] = empty($row['Prefix_ID']) ? 'NULL' : intval($row['Prefix_ID']);

            $query .= ',('.$row['Address_ID'].', '.$row['Source_ID'].', '.$row['Building_ID'].', '.$row['Actual_Building'].', '.$row['Print_Address'].', '.
                $row['Longitude'].', '.$row['Latitude'].', '.$row['House'].', '.$row['Corpus'].', '.$row['Liter'].', '.$row['Villa'].', '.$row['Parcel'].', '.$row['Construction'].', '.$row['Build_Number'].', '.
                $row['Geonim_ID'].', '.$row['Area_Object_ID'].', '.$row['Town_ID'].', '.$row['Toponim_ID'].', '.
                $row['Prefix_Print_Name'].', '.$row['Geonim_Print_Name'].', '.$row['Area_Object_Print_Name'].', '.$row['Town_Print_Name'].', '.$row['Toponim_Print_Name'].', '.$row['Raion_ID'].', '.$row['Prefix_ID'].')';

            $i++;

            if ($i % 5000 === 0 && $i > 0) {
                $query = 'insert into addresses (Address_ID, Source_ID, Building_ID, Actual_Building, Print_Address, Longitude, Latitude, House, Corpus, Liter, Villa, Parcel, Construction, Build_Number, Geonim_ID, Area_Object_ID, Town_ID, Toponim_ID, Prefix_Print_Name, Geonim_Print_Name, Area_Object_Print_Name, Town_Print_Name, Toponim_Print_Name, Raion_ID, Prefix_ID) values'.substr($query,1);
                $lt->exec($query);
                self::showStatus($i, $total, self::memory(memory_get_usage()), $start_time, $size=30);
                $query = '';
            }
        }
        if ($i % 5000 !== 0) {
            $query = 'insert into addresses (Address_ID, Source_ID, Building_ID, Actual_Building, Print_Address, Longitude, Latitude, House, Corpus, Liter, Villa, Parcel, Construction, Build_Number, Geonim_ID, Area_Object_ID, Town_ID, Toponim_ID, Prefix_Print_Name, Geonim_Print_Name, Area_Object_Print_Name, Town_Print_Name, Toponim_Print_Name, Raion_ID, Prefix_ID) values'.substr($query,1);
            $lt->exec($query);
            //echo $i.PHP_EOL;
            self::showStatus($i, $total, self::memory(memory_get_usage()), $start_time, $size=30);
        }

        echo 'indexing addresses data'.PHP_EOL;
        $lt->exec('
    CREATE INDEX addrPrefix ON addresses(Prefix_ID);
	  CREATE INDEX addrRaion ON addresses(Raion_ID);
	  CREATE INDEX addrHouse ON addresses(House);
	  CREATE INDEX addrCorpus ON addresses(Corpus);
	  CREATE INDEX addrLiter ON addresses(Liter);
	  CREATE INDEX addrVilla ON addresses(Villa);
	  CREATE INDEX addrParcel ON addresses(Parcel);
	  CREATE INDEX addrBuild ON addresses(Build_Number);
	  CREATE INDEX addrConstruction ON addresses(Construction);
  ');
        $memortyPartOne = memory_get_usage();

        //вторая часть, собираем данные для таблицы prefixes
        $subrfArr = [];
        $subrf = self::readTheFile('subrf'); //читаем файл генератором
        foreach ($subrf as $item) {
            if (!empty($items['STATUS']) && $items['STATUS'] === 'A') {
                $subrfArr[] = $items['ID'];
            }
        }
        $subrfArr = array_flip($subrfArr);
        echo PHP_EOL.'select [prefixes] for eas.db'.PHP_EOL;

        $prefixes = [];
        $prefixIterator = self::readTheFile('prefix'); //читаем файл генератором
        foreach ($prefixIterator as $item) {
            if (isset($subrfArr[$item['SUBRF']])) {
                $prefixes[$item['ID']] = [
                    'Prefix_ID' => $items['ID'],
                    'Geonim_ID' => $items['GEONIM'],
                    'Area_Object_ID' => $items['AREA_OBJ'],
                    'Town_ID' => $items['TOWN'],
                    'Toponim_ID' => $items['TOPONIM'],
                    'SubRF_ID' => $items['SUBRF'],
                    'Geonim_Print_Name' => $geonimsArr[$items['GEONIM']],
                    'Area_Object_Print_Name' => $areaObjArr[$items['AREA_OBJ']],
                    'Town_Print_Name' => $townsArr[$items['TOWN']],
                    'Toponim_Print_Name' => $toponimsArr[$items['TOPONIM']],
                ];
            }
        }

        echo 'Writing prefixes data'.PHP_EOL;
        $lt->exec('CREATE TABLE prefixes (
    Prefix_ID INT PRIMARY KEY,
    Geonim_ID INT,
    Area_Object_ID INT,
    Town_ID INT,
    Toponim_ID INT,
    SubRF_ID INT,
    Geonim_Print_Name TEXT,
    Area_Object_Print_Name TEXT,
    Town_Print_Name TEXT,
    Toponim_Print_Name TEXT
	) WITHOUT ROWID;');

        $i = 0;
        $query = '';
        $total = count($prefixes);
        $start_time = time();
        foreach($prefixes as $row) {
            $row['Prefix_ID'] = empty($row['Prefix_ID']) ? 'NULL' : intval($row['Prefix_ID']);
            $row['Geonim_ID'] = empty($row['Geonim_ID']) ? 'NULL' : intval($row['Geonim_ID']);
            $row['Area_Object_ID'] = empty($row['Area_Object_ID']) ? 'NULL' : intval($row['Area_Object_ID']);
            $row['Town_ID'] = empty($row['Town_ID']) ? 'NULL' : intval($row['Town_ID']);
            $row['Toponim_ID'] = empty($row['Toponim_ID']) ? 'NULL' : intval($row['Toponim_ID']);
            $row['SubRF_ID'] = empty($row['SubRF_ID']) ? 'NULL' : intval($row['SubRF_ID']);
            $row['Geonim_Print_Name'] = empty($row['Geonim_Print_Name']) ? 'NULL' : '\''.SQLite3::escapeString($row['Geonim_Print_Name']).'\'';
            $row['Area_Object_Print_Name'] = empty($row['Area_Object_Print_Name']) ? 'NULL' : '\''.SQLite3::escapeString($row['Area_Object_Print_Name']).'\'';
            $row['Town_Print_Name'] = empty($row['Town_Print_Name']) ? 'NULL' : '\''.SQLite3::escapeString($row['Town_Print_Name']).'\'';
            $row['Toponim_Print_Name'] = empty($row['Toponim_Print_Name']) ? 'NULL' : '\''.SQLite3::escapeString($row['Toponim_Print_Name']).'\'';

            $query .= ',('.$row['Prefix_ID'].', '.$row['Geonim_ID'].', '.$row['Area_Object_ID'].', '.$row['Town_ID'].', '.$row['Toponim_ID'].', '.$row['SubRF_ID'].', '.
                $row['Geonim_Print_Name'].', '.$row['Area_Object_Print_Name'].', '.$row['Town_Print_Name'].', '.$row['Toponim_Print_Name'].')';

            $i++;
            if ($i % 500 === 0 && $i > 0) {
                $query = 'insert into prefixes (Prefix_ID, Geonim_ID, Area_Object_ID, Town_ID, Toponim_ID, SubRF_ID, Geonim_Print_Name, Area_Object_Print_Name, Town_Print_Name, Toponim_Print_Name) values'.substr($query,1);
                $lt->exec($query);
                self::showStatus($i, $total, self::memory(memory_get_usage(),$memortyPartOne), $start_time, 30);
                $query = '';
            }
        }
        if ($i % 500 !== 0) {
            $query = 'insert into prefixes (Prefix_ID, Geonim_ID, Area_Object_ID, Town_ID, Toponim_ID, SubRF_ID, Geonim_Print_Name, Area_Object_Print_Name, Town_Print_Name, Toponim_Print_Name) values'.substr($query,1);
            $lt->exec($query);
            self::showStatus($i, $total, self::memory(memory_get_usage(),$memortyPartOne), $start_time, 30);
        }
        unset($query);

        echo 'Indexing prefixes data'.PHP_EOL;
        $lt->exec('CREATE INDEX prefGeonim ON prefixes(Geonim_ID);
						CREATE INDEX prefArea ON prefixes(Area_Object_ID);
						CREATE INDEX prefTown ON prefixes(Town_ID);
						CREATE INDEX prefToponim ON prefixes(Toponim_ID);
						CREATE INDEX prefSub ON prefixes(SubRF_ID);');

        echo PHP_EOL.'Vacuum'.PHP_EOL;
        $lt->exec('VACUUM;');
        $lt->close();
    }

    protected function getGeo() {
        echo PHP_EOL.'Select geometry for PG'.PHP_EOL;
        require_once __DIR__.'/../config.php';

        $pgsql = new PDO(BUILDINGS_DB_CONNECTION_STRING, BUILDINGS_DB_USER, BUILDINGS_DB_PWD);
        $pgsql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $pgsql->exec('TRUNCATE TABLE geocode.buildings_tmp');

        $mongo = new \MongoDB\Driver\Manager(MONGODB_OPENDATA_CONNECTION_STRING);

        $filter = ['classes' => 'ЗданиеКлассификатор', 'attributes.Территория' => ['$ne' => null], 'attributes.http://trinidata(_dot_)ru/archigraph-mdm/archive' => ['$ne' => 'true']];
        $options = [
            'projection' => [
                'attributes.ИдентификаторВЕас' => 1,
                'attributes.Территория' => 1,
                'attributes.Широта' => 1,
                'attributes.Долгота' => 1,
                'attributes.ПолныйАдресЗдание' => 1
            ],
            'collation' => [
                'locale' => 'en'
            ]
        ];
        $q = new MongoDB\Driver\Query($filter, $options);
        $rows = $mongo->executeQuery('apkbg.objects', $q);
        $i=0;
        $query='';
        foreach ($rows as $row) {
            $zone = $row->attributes->{'Территория'};
            if (!$zone) {
                continue;
            }
            $zone=json_decode(html_entity_decode($zone), true);
            if (!isset($zone[0][0])) {
                $zone=array($zone);
            }
            if (!isset($zone[0][0][0])) {
                $zone=array($zone);
            }
            $geo='';
            foreach($zone as $subpoly) {
                $sub='';
                foreach($subpoly as $poly) {
                    $pl='';
                    foreach($poly as $coords) {
                        $pl.=','.$coords['lng'].' '.$coords['lat'];
                    }
                    if ($pl!='') {
                        $pl='('.substr($pl,1).')';
                        $sub.=','.$pl;
                    }
                }
                if ($sub!='') {
                    $sub='('.substr($sub,1).')';
                    $geo.=','.$sub;
                }
            }
            if ($geo!='') {
                $geo='MULTIPOLYGON('.substr($geo,1).')';
            }
            unset($zone);
            $longitude = isset($row->attributes->{'Долгота'}) && $row->attributes->{'Долгота'} != '' ? floatval($row->attributes->{'Долгота'}) : 'NULL';
            $latitude = isset($row->attributes->{'Широта'}) && $row->attributes->{'Широта'} != '' ? floatval($row->attributes->{'Широта'}) : 'NULL';
            $address = isset($row->attributes->{'ПолныйАдресЗдание'}) && $row->attributes->{'ПолныйАдресЗдание'} != '' ? $pgsql->quote($row->attributes->{'ПолныйАдресЗдание'}) : 'NULL';
            $hash = $pgsql->quote(md5($geo.' '.$longitude.' '.$latitude.' '.$address));
            if ($i%500==0) {
                if ($query!='') {
                    $pgsql->exec($query.' ON CONFLICT (eas_building_id) DO NOTHING');
                }
                echo $i."\r";
                $query='INSERT INTO geocode.buildings_tmp (eas_building_id, the_geom, longitude, latitude, address, hash) VALUES('.intval($row->attributes->{'ИдентификаторВЕас'}).', ST_GeomFromText(\''.$geo.'\'), '.$longitude.', '.$latitude.', '.$address.', '.$hash.')';
            } else {
                $query.=', ('.intval($row->attributes->{'ИдентификаторВЕас'}).', ST_GeomFromText(\''.$geo.'\'), '.$longitude.', '.$latitude.', '.$address.', '.$hash.')';
            }
            $i++;
        }
        if ($query!='') {
            $pgsql->exec($query.' ON CONFLICT (eas_building_id) DO NOTHING');
        }
        echo $i.PHP_EOL;

        unset($query);

        $pgsql->exec('INSERT INTO geocode.buildings SELECT eas_building_id, the_geom, latitude, longitude, address, hash FROM geocode.buildings_tmp '.
            'ON CONFLICT (eas_building_id) DO UPDATE SET the_geom = EXCLUDED.the_geom, latitude = EXCLUDED.latitude, longitude = EXCLUDED.longitude, address = EXCLUDED.address, hash = EXCLUDED.hash WHERE geocode.buildings.hash != EXCLUDED.hash');
        $pgsql->exec('TRUNCATE TABLE geocode.buildings_tmp');
        echo 'Done'.PHP_EOL;
    }
}

$all_time = time(); //counting the total time for the entire script

$import = new import();
$import->run();

//Total time / memory
$val = memory_get_peak_usage()/1000000;
$elapsed = time() - $all_time;
echo PHP_EOL;
echo 'Time elapsed: '.$elapsed.' sec.'.PHP_EOL;
echo 'Memory peak usage: '.$val.' MB'.PHP_EOL;