<?php

namespace App\Http\Controllers;

use App\Models\ServiceType;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Models\Ticket;
use App\Models\Service;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Reader\Exception;

class ImportController extends Controller {

    protected static $keycloakUserId;

    /**
     * @throws Exception
     */
    public function read_and_import($name): array {
        $reader = IOFactory::createReader('Xlsx');
        $spreadsheet = $reader->load($name);
        $reader->setReadDataOnly(TRUE);

        $data = $spreadsheet->getActiveSheet()->toArray();
        $tickets = [];
        $error_rows = []; //массив с номерами строк, которые не импортированы
        /*
         * array sample
         * required 0,1,5,6
         * ['заявка 1', 'ПППУР','зявка 1 описание', 'Новый', 'Высокий', 'Восстановление баз данных',  'услуга 1 Описание', 'MongoDB']
         * ['заявка 1','ПППУР,				                            'Выгрузка данных из системы', 'услуга 2 Описание','PHP']
         * */

        //крайний элемент, заявка, с заполненным title и ac
        $last_filled_ticket = [];

        foreach ($data as $index => $item) {
            //проверка если строка полностью пустые, к примеру последняя
            $check_empty = array_diff(array_map("trim", $item), array('', NULL, false));
            if (count($check_empty) == 0) {
                continue;
            }

            if (!empty($item[0]) && !empty($item[1])) {
                //не пустой title + ac
                //запоминаем, на следующие строки, если у них не заполнено
                $last_filled_ticket = $item;
            }

            if (empty($item[0]) || empty($item[1]) || empty($item[5]) || empty($item[6])) {
                //если одно из обязательных полей пустое, разбираем какое
                if ((empty($item[0]) || empty($item[1])) && !empty($last_filled_ticket[0]) && !empty($last_filled_ticket[1])) {
                    //в случае, когда не заполнен title или ac, проверяем если в одной из предыдущих итераций бэти параметры
                    //были заполнены, считаем текущую строку услугой к ней, и проставляем title + ac
                    $item[0] = $last_filled_ticket[0];
                    $item[1] = $last_filled_ticket[1];
                } else {
                    //иначе считаем строку ошибкой
                    $error_rows[] = $index+1;
                    continue;
                }
            }

            if (!empty(trim($item[3])) && empty($tickets[trim($item[0])])) {
                //если строка является первой строкой заявки, идентифицируем по наличию параметра Статуса
                //иначе это строка с услугой
                $tickets[trim($item[0])] = [
                    'title' => trim($item[0]),
                    'system_id' => trim($item[1]),
                    'content' => trim($item[2]),
                    'status_id' => trim($item[3]),
                    'priority_id' => trim($item[4]),
                    'beginDateTime' => date('Y-m-d'),
                    'closeDateTime' => date('Y-m-d'),
                    'row_num' => $index, //индекс строки, для дальнейшего вывода её номера, в случае ошибки сохранения, а не пустых полей
                ];
            }

            if (!empty(trim($item[5]))) {
                try {
                    $serviceTypeId = ServiceType::getIdByTitle(trim($item[5]));
                    $software = trim($item[7]);
                } catch (\Exception $e){
                    return  ['fail_rows'=>[
                        ($index+1) . ': '. $e->getMessage()
                    ]];
                }
                //услуга
                $tickets[trim($item[0])]['services'][] = [
                    //ticket_id
                    'ticket_id'          => '',
                    'service_type_id'    => $serviceTypeId,
                    'content'            => trim($item[6]),
                    'software'           => $software,
                    'start'              => date('Y-m-d'),
                    'finish'             => date('Y-m-d'),
                    'row_num'            => $index,
                ];

            }
        }

        //необходимые словари для замены значений с текстовых на хранимые
        $list = [
            //tickets
            'priority',  //priority_id
            'status',   //status_id
            'system',   //system_id

            //services
            'service_type', //service_type_id
            'software',  //software
            'source',
            'user',
        ];

        if (count($error_rows)>0) {
            return ['fail_rows'=>$error_rows];
        }

        $ticket_headers = Ticket::getHeaders();

        $dictionaries = (new DictionaryController)->index($list);

        $services_count = 0;
        $tickets_count = 0;

        foreach ($tickets as $new_ticket) {

            $ticket_replaced = $this->sort_replace($new_ticket, $ticket_headers, $dictionaries);

            $ticket  = new Ticket();
            $ticket->title = $ticket_replaced['title'];
            $ticket->system_id = $ticket_replaced['system_id'];
            $ticket->status_id = $ticket_replaced['status_id'];
            $ticket->content = $ticket_replaced['content'];
            $ticket->priority_id = $ticket_replaced['priority_id'];
//            $ticket->source_id = 'RC_GENERATOR';
            $ticket->user_id = self::getKeycloakUserId();
            $ticket->beginDateTime = $ticket_replaced['beginDateTime'];
            $ticket->closeDateTime = $ticket_replaced['closeDateTime'];
            if ($ticket->save()) {
                $ticket_id = $ticket->id;
                $tickets_count++;
                foreach ($ticket_replaced['services'] as $new_service) {

                    $service = new Service();
                    $service->ticket_id = $ticket_id;
                    $service->user_id = self::getKeycloakUserId();
                    $service->content =  $new_service['content'];
                    $service->start =  $new_service['start'];
                    $service->finish =  $new_service['finish'];
                    $service->software =  $new_service['software'];

                    $service->service_type_id = $new_service['service_type_id'];

                    if ($service->save()) {
                        $services_count++;
                    } else {
                        $error_rows[] = $ticket_replaced['row_num']+1;
                    }
                }
    
                TicketController::setSoftwareFromServices($ticket->id);
                
            } else {
                $error_rows[] = $ticket_replaced['row_num']+1;
            }
        }
        return  (count($error_rows)>0)?['fail_rows'=>$error_rows]:['tickets'=>$tickets_count,'services'=>$services_count];
    }

    public static function getKeycloakUserId(){
        return KeyCloakController::getCurrentUser();
    }


    /**
     * Перебор текстовых значений массива с заменой на их значения, для хранения в БД, по переданным залоговкам/словарям
     * т.е. статус Новый = New
     *
     * @param $in //входящий массив
     * @param $headers //заголовки (маппинг) Заявок/Услуг
     * @param $dictionaries //словари со значениями для замены
     * @return array //ответ в виде того же массива, с измененными значениями
     */
    private function sort_replace($in, $headers, $dictionaries): array {
        $in_array = $in;
        foreach($in_array as $service_key => $value) {
            $index = array_search($service_key, array_column($headers, 'value'));
            if (array_key_exists('dictionary',$headers[$index])) {
                //если словарь указан, пробуем получить значение
                $i = array_search($value, array_column($dictionaries[$headers[$index]['dictionary']], 'text'));
                $in_array[$service_key] = $dictionaries[$headers[$index]['dictionary']][$i]['value'];
            }
        }
        return $in_array;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse {
        try {
    
    
            $a = [
                0.001 => 'a',
                .6    => 'c'
            ];
            var_dump($a);
    
            return response()->json(
                [
                    'success' => '',
                ]
            );
    
    
            $file_name = $request->file->getClientOriginalName();
            $response = $this->read_and_import($request->file->getRealPath());

            if (isset($response['fail_rows']) && count($response['fail_rows']) > 0) {
                throw new Exception('Строки с ошибками: '.implode(', ', $response['fail_rows']));
            }

            return response()->json(
                [
                    'success' => 'Файл "' . $file_name . '" успешно загружен. <br> Импортировано '.$response['tickets'].' заявок, '.$response['services'].' услуг.',
                ]
            );
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => $e->getMessage(),
                ]
            );
        }

    }

}
