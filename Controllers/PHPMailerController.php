<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use function request;

class PHPMailerController extends Controller {
    
    public function composeEmail($ticketId = null): JsonResponse {
        /**
         * @var Ticket $ticket
         */
    
        if(empty($ticketId)) {
            $id = request()->input('id', []);
            if (!empty($id)) {
                $ticketId = $id;
            }
        }
    
        $ticket = Ticket::findOrFail($ticketId);
        $system_title = (new SystemController)->getSystemNameById($ticket->system_id);
    
        $system_mail_server_id = (new SystemController)->getSystemMailServerId($ticket->system_id);
    
        if ($system_mail_server_id == 0) {
            return response()->json(
                [
                    'error' => 'Шлюз для отправки писем не настроен для данной системы',
                ]
            );
        }
        
        /**
           ticket
           
            [id] => 2581
            [bitrix_id] =>
            [system_id] => as004
            [beginDateTime] => 2023-01-19
            [closeDateTime] => 2023-01-19
            [software] => 123 213,  Docker CE 19.03.13,  ImageMagick 6,  PostGIS, 000 0,  231312132,  PostgreSQL 9,  tqweqwe1
            [source_id] => RC_GENERATOR
            [priority_id] => 919
            [status_id] => EXECUTING
            [applicant_id] => 6
            [user_id] => bdfb4063-3448-4feb-8f00-f4dbea3d2791
            [synced] => 0
            [sent] => 0
            [last] => 1
            [report] =>
            [deleted] => 0
            [title] => Тест заявителя
            [content] =>
            [updated_at] => 2023-01-30 10:58:30
            [created_at] => 2023-01-19 11:36:10
            [comment] =>
            [modified_at] =>
            [error] =>
         */
    
        $attachedServices = Service::query()
            ->select()
            ->where('ticket_id', $ticketId)
            //->where(['last' => true])
            //->whereRaw('NOT(deleted=1 && synced=1)')
            ->whereRaw('NOT(deleted=1)')
            ->get()->toArray();
        /**
           service
           
            [id] => 20350
            [ticket_id] => 2581
            [service_type_id] => 418747
            [software] => 123 213, Docker CE 19.03.13, ImageMagick 6, PostGIS
            [start] => 30.01.2023
            [finish] => 30.01.2023
            [content] => Описание услуги 1
            [synced] => 0
            [deleted] =>
            [last] => 1
            [user_id] => bdfb4063-3448-4feb-8f00-f4dbea3d2791
            [bitrix_id] =>
            [updated_at] => 30.01.2023
            [created_at] => 30.01.2023
            [error] =>
         */
    
        $services = '';
        if (count($attachedServices) > 0) {
            $services = '<div>Для устранения инцидента оказаны услуги:</div>';
            $services .= '<ul>';
            foreach ($attachedServices as $service) {
                $services .= '<li>'.$service['content'] . '</li>';
            }
            $services .= '</ul>';
        }
    
        $smtp_data = (new MailServersController)->getMailDataByMailSystemId($system_mail_server_id)[0];
        /*
            [id] => 1
            [name] => Имя
            [server] => smtp.mail.ru
            [protocol] => smtp
            [port] => 1212
            [from] => ...
            [to] => ... , ...
            [theme] => Subject
        */
        
        
        $recipients = explode(',', str_replace(' ','',$smtp_data['to']));
        
        
        $mail = new PHPMailer(true);     // Passing `true` enables exceptions
        
        try {
            // Email server settings
            $mail->SMTPDebug = 0;
            $mail->isSMTP();
            $mail->Host     = $smtp_data['server'];
            $mail->SMTPAuth = true;
            $mail->Username = $smtp_data['from'];
            $mail->Password = $smtp_data['password'];
            $mail->SMTPSecure = 'ssl';
            $mail->CharSet = "UTF-8";
            $mail->Port     = $smtp_data['port'];
            
            $mail->setFrom($smtp_data['from'], $smtp_data['name']);
            if ($recipients > 0) {
                foreach ($recipients as $recipient) {
                    $mail->addAddress($recipient);
                }
            }
            //$mail->addCC($request->emailCc);
            //$mail->addBCC($request->emailBcc);
            //$mail->addReplyTo('sender@example.com', 'SenderReplyName'); // sender email, sender name
            /*if(isset($_FILES['emailAttachments'])) {
                for ($i=0; $i < count($_FILES['emailAttachments']['tmp_name']); $i++) {
                    $mail->addAttachment($_FILES['emailAttachments']['tmp_name'][$i], $_FILES['emailAttachments']['name'][$i]);
                }
            }*/
            
            $mail->isHTML(true);
            
            $mail->Subject = $smtp_data['theme'];
            $mail->Body =
                '<div style="margin-bottom: 1em;">
                      <span style="font-size:24px;padding-right: 15px;">АС '.$system_title.'</span>
                      <span style="font-size:0.8em; color: gray;">'.$ticket->created_at.'<span>
                 </div>
                 
                 <div style="font-size: 20px;">'.$ticket->title.'</div>
                 <div>Зарегистрирован инцидент https://support.uits.spb.ru/crm/deal/details/'.$ticket->bitrix_id.'/</div>
                 '.$services.'
            <div>Инцидент устранен '.$ticket->updated_at.'</div>
            <div>Рекомендации для улучшения функционирования: отсутствуют.</div>';
            
            // $mail->AltBody = plain text version of email body;
            
            if( !$mail->send() ) {
                return response()->json(
                    [
                        'error' => $mail->ErrorInfo,
                    ]
                );
            }
            else {
                return response()->json(
                    [
                        'success' => 'Отчеты были отправлены',
                    ]
                );
            }
        } catch (Exception $e) {
            return response()->json(
                [
                    'error' => 'Отчеты не могут быть отправлены',
                    'text' => $e->errorMessage()
                ]
            );
        }
    }
}