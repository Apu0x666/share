<?php

use App\Http\Controllers\ApplicantController;
use App\Http\Controllers\DictionaryController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\KeyCloakController;
use App\Http\Controllers\MailServersController;
use App\Http\Controllers\PHPMailerController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SoftwareController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\TelegramLoggerController;
use App\Http\Controllers\TicketController;
use App\Http\Helpers\VuetifyTablePaginationHelper;
use App\Models\Applicant;
use App\Models\MailServers;
use App\Models\Server;
use App\Models\Service;
use App\Models\Software;
use App\Models\System;
use App\Models\Ticket;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/
Route::post('/telegram-logger', [TelegramLoggerController::class, 'send']);
Route::middleware('auth:web')->group(function() {
    Route::any('/', function () {
        return inertia('Tickets', [
            'data' => Inertia::lazy(function () {
                $tablePageSort = request()->input('tablePageSort', []);
                $filter = request()->input('filter', []);
                $groups = auth()->user()->groups;
                if (!empty($filter['system_id'])) {
                    $filter['system_id'] = array_filter($filter['system_id'] , function ($item) use ($groups){
                        return in_array($item, $groups);
                    });
                } else {
                    $filter['system_id'] = $groups;
                }

                return VuetifyTablePaginationHelper::proceed(Ticket::query()->where(['last' => true]), $tablePageSort, $filter);
            }),
            'headers' => function () {
                return Ticket::getHeaders();
            },
            'serviceHeaders' => function () {
                return Service::getHeaders();
            }
        ]);
    });

    Route::post('/load-services', function (){
        $ticketIds = request()->input('ticketIds', []);
        $res = [];
        if(!empty($ticketIds) ) {
            $res = Service::query()
                ->whereIn('ticket_id', $ticketIds)
                ->where(['last' => true])
                ->whereRaw('NOT(deleted=1 && synced=1)')
                ->get()->append('serviceTypeTitle')->groupBy('ticket_id')->toArray();
            foreach ($ticketIds as $ticketId) {
                if (!isset($res[$ticketId])) $res[$ticketId] = [];
            }
        }
        return $res;
    });

    Route::any('/servers', function () {
        return inertia('configServers', [
            'data' => Inertia::lazy(function () {
                $tablePageSort = request()->input('tablePageSort', []);
                $filter = request()->input('filter', []);

                $servers = (new DictionaryController)->index(['server'], ['system_id','os', 'ip']);
                $groups = auth()->user()->groups;
                /* groups
                 * Array
                (
                    [0] => as004
                    [1] => ksomb
                    [2] => pppur
                    [3] => tiod
                )*/
                $availableServers = array_values(array_filter($servers['server'], function ($item) use ($groups){
                    //фильтруем все наши сервера, и отдаем только те, которые доступны пользователю из KeyCloak (на основе групп)
                    return in_array($item['system_id'], $groups);
                }));
                if (!empty($filter['system_id'])) {
                    //если запрос пришёл с фильтром, требуется отсеять недоступные пользователю сервера
                    $filter['system_id'] = array_filter($filter['system_id'] , function ($item) use ($availableServers, $groups){
                        return in_array($item, array_column($availableServers,'system_id'));
                    });
                } else {
                    //если без фильтра, то пользователь получает ровно те сервера, которые ему доступны на основе групп KeyCloak
                    $filter['system_id'] = $groups;
                }
                return VuetifyTablePaginationHelper::proceed(Server::query(), $tablePageSort, $filter);
            }),
            'headers' => function () {
                return Server::getHeaders();
            },
        ]);
    });

    Route::any('/applicants', function () {
        return inertia('Applicants', [
            'data' => function () {
                $tablePageSort = request()->input('tablePageSort', []);
                $filter = request()->input('filter', []);
                return VuetifyTablePaginationHelper::proceed(Applicant::query(), $tablePageSort, $filter);
            },
            'headers' => function () {
                return Applicant::getHeaders();
            },
        ]);
    });

    Route::any('/systems', function () {
        return inertia('configSystems', [
            'data' => Inertia::lazy(function () {
                $tablePageSort = request()->input('tablePageSort', []);
                $filter = request()->input('filter', []);

                $systems = (new DictionaryController)->index(['system'], ['address','last_sync', 'api', 'mail_server_id']);
                $groups = auth()->user()->groups;
                $availableSystems = array_values(array_filter($systems['system'], function ($item) use ($groups){
                    return in_array($item['value'], $groups);
                }));

                if (!empty($filter['id'])) {
                    $filter['id'] = array_filter($filter['id'] , function ($item) use ($availableSystems, $groups){
                        return in_array($item, $availableSystems);
                    });
                } else {
                    $filter['id'] = $groups;
                }

                return VuetifyTablePaginationHelper::proceed(System::query(), $tablePageSort, $filter);
            }),
            'headers' => function () {
                return System::getHeaders();
            },
        ]);
    });

    Route::any('/software', function () {
        return inertia('configSoftware', [
            'data' => Inertia::lazy(function () {
                $tablePageSort = request()->input('tablePageSort', []);
                $filter = request()->input('filter', []);

                $servers = (new DictionaryController)->index(['server'], ['system_id']);
                $groups = auth()->user()->groups;
                /*
                    [0] => as004
                    [1] => ksomb
                    [2] => pppur
                    [3] => tiod
                */
                $availableServers = array_values(array_filter($servers['server'], function ($item) use ($groups){
                    return in_array($item['system_id'], $groups);
                }));
    
                if (!empty($filter['server_id'])) {
                    $filter['server_software.server_id'] = array_filter($filter['server_id'] , function ($item) use ($availableServers){
                        return in_array($item, array_column($availableServers, 'value'));
                    });
                } else {
                    $filter['server_software.server_id'] = array_column($availableServers, 'value');
                    $filter['server_software.server_id'][] = null; //включаем null как возможный вариант
                }

                $q = Software::query()
                    ->leftJoin('server_software', 'software.id', '=', 'server_software.software_id');
                
                unset($filter['server_id']);
                
                return VuetifyTablePaginationHelper::proceed($q, $tablePageSort, $filter);
            }),
            'servers' => function () {
                $servers = (new DictionaryController)->index(['server'], ['system_id','os', 'ip']);
                $groups = auth()->user()->groups;

                return array_values(array_filter($servers['server'], function ($item) use ($groups){
                    return in_array($item['system_id'], $groups);
                }));
            },
            'headers' => function () {
                return Software::getHeaders();
            },
        ]);
    });

    Route::any('/mail_servers', function () {
        return inertia('configMail', [
            'data' => Inertia::lazy(function () {
                $tablePageSort = request()->input('tablePageSort', []);
                $filter = request()->input('filter', []);
    
                return VuetifyTablePaginationHelper::proceed(MailServers::query(), $tablePageSort, $filter);
            }),
            'headers' => function () {
                return MailServers::getHeaders();
            },
        ]);
    });

    Route::any('/sync', function () {
        $systemIds = request()->post('system_id', []);
        $tickets_ids = request()->post('tickets_ids', []);
        $services_ids = request()->post('services_ids', []);
        System::syncAll($systemIds, $tickets_ids, $services_ids);
    });
    Route::post('/dictionary', [DictionaryController::class, 'index']);
    Route::resources([
        'tickets' => TicketController::class,
        'software_resource' => SoftwareController::class,
        'system_resource' => SystemController::class,
        'server_resource' => ServerController::class,
        'applicants_resource' => ApplicantController::class,
        'mail_servers_resource' => MailServersController::class,
        'tickets.services' => ServiceController::class,
    ]);

    Route::post('/softwareSave', [SoftwareController::class, 'create_edit']);
    Route::post('/systemSave', [SystemController::class, 'edit']);
    Route::post('/serverSave', [ServerController::class, 'create_edit']);
    Route::post('/applicantSave', [ApplicantController::class, 'create_edit']);
    Route::post('/mailServersSave', [MailServersController::class, 'create_edit']);
    
    Route::post('/updateSoft', [TicketController::class, 'setSoftwareFromServices']);
    
    Route::get('logs', [LogViewerController::class, 'index']);
    Route::get('/user_data', [KeyCloakController::class, 'getCurrentUserData']);
    Route::post('/import', [ImportController::class, 'import']);
    
    Route::post("/send-email", [PHPMailerController::class, "composeEmail"]);
    
    Route::post("/setWonStatusForTicket", [TicketController::class, "setWonStatusForTicket"]);

    Route::get('/md/{title?}', function ($title = null){
        if(is_null($title)){
            $files = scandir(base_path("md"));
            $page = "# Справка\n";
            foreach ($files as $file){
                $split = explode('.', $file);
                if( last($split) === 'md') {
                    $title = $split[0];
                    $page .= "- [$title](/md/$title)\n";
                }
            }
        } else {
            $path = base_path("md/$title.md");
            $page = "# Страница не найдена";
            if (file_exists($path)) {
                $page = file_get_contents($path);
            }
        }
        return view('md', [
            'title' => $title,
            'content' => Str::markdown($page, [])
        ]);
    });
    
    Route::post('/logout', function(){
    
    });
});
