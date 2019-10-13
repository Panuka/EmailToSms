<?php

use PhpImap\Mailbox;
use PHPMailer\PHPMailer\PHPMailer;

defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Created by PhpStorm.
 * User: panuka
 * Date: 27.12.15
 * Time: 21:22
 */
class Console extends CI_Controller
{
    /** @var CI_DB|CI_DB_mysql_driver */
    public $db;
    /** @var PHPExcel */
    private $xls;
    private $log_id = null;
    private $car_to_sms;
    private $msg;
    private $current_mail;
    private $user;
    /** @var Ion_auth_model */
    public $ion_auth;
    private $api_private;
    private $api_public;
    private $web = false;
    public $URL_GAREWAY = 'http://atompark.com/api/sms/';
    /** @var ApiSms */
    public $apisms;
    /** @var CI_DB_utility */
    public $dbutil;
    private $current_time;

    function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->current_time = \time();
    }

    private function prepareUser($user)
    {
        $user['report_time_single'] = json_decode($user['report_time_single'], true);
        $user['report_time'] = json_decode($user['report_time'], true);
        $user['parser'] = explode(',', $user['parser']);
        $user['min_balance_milestones'] = json_decode($user['min_balance_milestones'], true);
        $user['report'] = explode(',', $user['report']);
        $user['geo'] = explode(',', $user['geo']);
        $user['opsos'] = json_decode($user['opsos'], true);
        return $user;
    }

    /**
     * @param $msg
     * @return int
     */
    protected function logDebug($msg)
    {
        $file = '/tmp/console-debug/'. date('d-m-Y') .'.log';
        if(!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777);
        }

        return file_put_contents($file, date('Y-m-d H:i:s') . "\t" . $msg . PHP_EOL, FILE_APPEND);
    }

    public function exec()
    {
        $users = $this->getUsers();
        foreach ($users as $user) {
            // Запускаем обработку пользователя
            $this->START_ACTIVITY($user);

            $this->logDebug('Loaded user: ' . var_export(['id' => $this->user['id'], 'username' => $this->user['username'] ], true));

            // проверяем баланс
            if (!$this->checkBalance())
                continue;
            $this->processUser();
            $this->sendMessages();
            $this->writeStats();
            $this->report();
            $this->END_ACTIVITY();
        }
        echo "ok.\n";
    }

    private function processUser()
    {
        $this->msg = array();
        $this->loadCars();
        $offers = $this->checkMail();
        $this->prepareOffers($offers);

        $this->logDebug('Loaded offers: '. var_export($offers, true));

        if (!empty($offers) && $offers!==false) $this->deal($offers); else {
            $this->toLog("Предложений нет");
            $this->logDebug('No offers');
        }
    }

    private function getUsers()
    {
        $_users = $this->db->select('*')->where('active', 1)->where('sys_active', 1)->order_by('id', 'desc')->get('users');
        $users = [];
        foreach ($_users->result_array() as $user) $users[] = $user;
        return $users;
    }

    private function prepareOffers(&$offers)
    {
        $count = count($offers);
        for ($i = 0; $i < $count; $i++) {
            if (!empty($offers[$i]['number'])) $offers[$i]['geo'] = $this->getGeoFromNumber($offers[$i]['number']);
        }
    }

    private function toLog($msg = '')
    {
        echo "$msg\n";
    }

    public function writeStats()
    {
        $date = date('Y-m-d H:i');
        foreach ($this->msg as $data) {
            $this->db->insert('offers', ['unix_timestamp' => $date, 'mark' => $data['car']['model'], 'year' => $data['car']['year'], 'link' => $data['car']['href'], 'price_ad' => $data['car']['price'], 'phone' => $data['reciver'], 'price_offer' => $data['offer'], 'txt' => $data['text'], 'geo' => $data['geo'], 'user' => $this->user['id'], 'kpp_type' => $data['car']['kpp_type']]);
        }
    }

    private function loadCars()
    {
        if (is_null($this->user['parsed_file']) || !isset($this->user['parsed_file'][10])) {
            $file_path = FCPATH . 'uploads/' . $this->user['file'];
            $this->xls = PHPExcel_IOFactory::load($file_path);
            $this->convertToArray();
            $this->db->where('id', $this->user['id']);
            $this->db->update('users', ['parsed_file' => serialize($this->car_to_sms)]);
            $this->toLog('Обновлен кеш файла таблицы!');
        } else
            $this->car_to_sms = unserialize($this->user['parsed_file']);
    }

    public function sendMsg($msg, $phone)
    {
        if ($phone[0] == 8) $phone = substr_replace($phone, '+7', 0, 1);
        $msg = iconv(mb_detect_encoding($msg), 'UTF-8', $msg);
        $this->getSms()->execCommad('sendSMS', array('sender' => 'SMS', 'sms_lifetime' => 0, 'text' => $msg, 'phone' => $phone,));
    }

    public function sendMessages()
    {
        $this->logDebug('Composed messages: ' . var_export($this->msg, true));

        foreach ($this->msg as $i => $msg) {
            // в $msg['geo'] : россия, самарская область, мегафон TODO: разделить это говно
            $msg_geo = strtolower($msg['geo']);
            $this->toLog("Гео продовца: " . $msg_geo);
            // обработка гео
            if (!empty($this->user['geo']) && count($this->user['geo']) > 0) {
                $bad = true;
                foreach ($this->user['geo'] as $geo) {
                    $this->toLog("Сверяем гео: $msg_geo | $geo");
                    if (stripos($msg_geo, $geo) !== false) $bad = false;
                }
                if ($bad) {
                    $this->toLog("Удляем номер, который не подошел под гео: ");
                    $this->toLog($this->msg[$i]['reciver']);
                    $msg = $this->msg;
                    unset($msg[$i]);
                    $this->msg = $msg;
                    $this->toLog("Нет совпадения в гео");
                    continue;
                }
            }
            // обработка оператора
            if (!empty($this->user['opsos']) && count($this->user['opsos']) > 0) {
                $bad = false;
                foreach ($this->user['opsos'] as $geo) {
                    $this->toLog("Сверяем оператора: $msg_geo | $geo");
                    if (stripos($msg_geo, $geo) !== false) $bad = true;
                }
                if ($bad) {
                    $this->toLog("Удляем номер, т.к. оператор есть в стоп-списке: ");
                    $this->toLog($this->msg[$i]['reciver']);
                    $msg = $this->msg;
                    unset($msg[$i]);
                    $this->msg = $msg;
                    $this->toLog("Оператор из стоп-списка...");
                    continue;
                }
            }
            $this->sendMsg($msg['text'], $msg['reciver']);
            $this->toLog("Отправлено сообщение {$msg['reciver']}");
            $this->toLog('== / ===');
            $this->toLog("{$msg['text']}");
        }
    }

    private function getTableOffers($html)
    {
        $xml = simplexml_load_string($html);
        if (!$xml) {
            $this->toLog('001: Произошла ошибка в разборе html!');
            $this->toLog("=====");
            $this->toLog($html);
            $this->toLog("=====");
        }
        $cars = array();
        if (isset($xml->tbody))
            $obj = $xml->tbody->tr;
        else
            $obj = $xml->tr;

        foreach ($obj as $_tr) {
            $cols = $_tr->td;
            if (!is_null($cols[0])) {
                $cars[] = array('model' => (string)$cols[0]->a, 'year' => (int)$cols[1], 'price' => (int)filter_var(str_replace(' ', '', $cols[2]->font), FILTER_SANITIZE_NUMBER_INT) / 1000, 'number' => filter_var(substr($cols[10], 0, 11), FILTER_SANITIZE_NUMBER_INT), 'href' => trim((string)$cols[0]->a['href']), 'geo' => strtolower((string)$cols[7]), 'kpp_type' => (stripos($cols[3], 'А') !== false) // проверяем наличие буквы А - акпп
                );
            }
        }
        return $cars;
    }


    private function checkMail()
    {
        $mailbox = new Mailbox('{imap.mail.ru:143}', $this->user['mail_login'], $this->user['mail_pass'], __DIR__);
        $mailsIds = $mailbox->searchMailBox('ALL');
        sort($mailsIds);
        $mailsIds = array_reverse($mailsIds);
        $COUNT = 5;
        foreach ($mailsIds as $c => $mailId) {
            if ($c >= $COUNT) break;
            $_mail = $this->db->get_where('mails', array('user_id' => $this->user['id'], 'mail_id' => $mailId, 'status' => 0))->row_array();
            if (!is_null($_mail)) continue;
            $mail = $mailbox->getMail($mailId);
            if (strtotime($mail->date) < strtotime($this->user['active_time']))
                continue;
            $this->db->insert('mails', ['user_id' => $this->user['id'], 'mail_id' => $mailId, 'theme' => $mail->subject, 'data' => $mail->textHtml . $mail->textPlain, 'date' => $mail->date,]);
            $this->current_mail = $this->db->insert_id();
            if ($this->inarr($mail->fromAddress, $this->user['parser'])) {
                $this->toLog("Обрабатываем почтовое событие: {$this->current_mail}");
                if ($mail->subject == 's1') $data = $this->processFromCsv($mail->textPlain); else
                    $data = $this->processFromBot($mail->textHtml);
                return $data;
            }
        }
        return false;
    }

    private function processFromCsv($text)
    {
        $offers = array();
        $rows = explode("\n", $text);
        $count = count($rows);
        for ($i = 0; $i < $count; $i++) {
            $car = str_getcsv($rows[$i], ";");
            if (!isset($car[3])) continue;
            $offers[] = array('model' => (string)$car[0], 'kpp_type' => (stripos($car[1], 'А') !== false), // проверяем наличие буквы А - акпп
                'year' => (int)$car[2], 'price' => (int)filter_var(str_replace(' ', '', $car[3]), FILTER_SANITIZE_NUMBER_INT) / 1000, 'number' => filter_var($car[4], FILTER_SANITIZE_NUMBER_INT), 'href' => "Ручная отправка [s1]", 'geo' => "",);
        }
        return $offers;
    }

    private function processFromBot($mail)
    {
        $_t = explode("\n", $mail);
        foreach ($_t as $i => $row) if (strpos($row, "<a href")) if (strpos($row, "</a>") == 0) $_t[$i] = substr($row, 0, strlen($row) - 6) . '</a></td>';
        $mail = implode('', $_t);
        $html = html_entity_decode($mail);
        $s = strpos($html, '<table');
        $f = strpos($html, '</table>') + 8;
        $table = substr($html, $s, $f - $s);
        return $this->getTableOffers(html_entity_decode($table));
    }

    private function convertToArray()
    {
        $data = $this->xls->getActiveSheet()->toArray(null, true, true, true);
        $years = array();
        foreach ($data[1] as $col => $year) if ((int)$year > 0) $years[$col] = (int)$year;
        $len = count($data);
        for ($i = 2; $i <= $len; $i++) {
            $model = $data[$i]['A'];    // название модели
            foreach ($data[$i] as $col => $car) {
                $price = $car;
                if ($price > 0) $this->addCarToSend($model, $price, $years[$col]);
            }
        }
    }

    private function normalizeModel($model)
    {
        return strtolower(str_replace(' ', '', $model));
    }

    private function addCarToSend($model, $price, $year)
    {
        $model = $this->normalizeModel($model);
        if (!isset($this->car_to_sms[$model])) $this->car_to_sms[$model] = array();
        $this->car_to_sms[$model][$year] = explode(";", $price);
    }

    private function inarr($haystack, $needle)
    {
        foreach ($needle as $need) if (strpos($haystack, $need) !== false) return true;
        return false;
    }

    public static function reportSelectQuery($user)
    {
        return "SELECT
				@rank:=@rank+1 AS id, unix_timestamp as 'Время', mark as 'Модель', year as 'Год', link as 'Ссылка',
				price_ad as 'Цена объявления', price_offer as 'Цена предложения',phone as 'Телефон',
				txt as 'SMS', geo as 'Гео', kpp_type as 'Тип КПП'
			FROM
				offers
			WHERE user = $user";
    }

    public function sendReport($theme = "Отчет", $time = null)
    {
        $path = $this->createCsvReport($time);
        $email = $this->createMail();
        $email->Subject = $theme;
        $email->Body = "Отчет о рассылке с парсера";
        $email->AddAddress($this->user['report'][0]);
        for ($i = 1; $i < count($this->user['report']); $i++) $email->AddCC($this->user['report'][$i]);
        $email->AddCC('agentrol@mail.ru');
        $email->AddAttachment($path, 'data.xls');
        $status = $email->Send();

        $this->logDebug('Sent report: ' . var_export(['subject' => $theme, 'to' => $email->getAllRecipientAddresses(), 'status' => $status], true));

        $this->toLog('Отправляем отчет...');
        $this->toLog('Статус:');
        if ($status) $this->toLog("Отправлено"); else
            $this->toLog("Ошибка");
        $this->toLog('-- --');
    }

    private function createMail()
    {
        $email = new PHPMailer();
        $email->isSMTP();
        $email->Host = 'smtp.mail.ru';
        $email->SMTPAuth = true;
        $email->Username = $this->user['mail_login'];
        $email->Password = $this->user['mail_pass'];
        $email->SMTPSecure = 'ssl';
        $email->Port = 465;
        $email->CharSet = 'utf-8';
        $email->From = $this->user['mail_login'];
        $email->FromName = 'SMS-Sender';
        return $email;
    }

    public function checkBalance()
    {
        $d = $this->getSms()->execCommad('getUserBalance', array('currency' => 'RUB'));
        $this->logDebug('getUserBalance:' . var_export($d, true));
        if (!isset($d['result']['balance_currency'])) {
            $this->toLog("Невозможно получить баланс!");
            $this->smsDown($d);
            return false;
        }
        $balance = (int)$d['result']['balance_currency'];
        $this->toLog("Баланс: $balance");
        $status = [$this->user['min_balance_ms1_trigger'], $this->user['min_balance_ms2_trigger'], $this->user['min_balance_ms3_trigger']];
        if ($this->user['min_balance_milestones'][0] > $balance && ($this->user['min_balance_milestones'][1] < $balance) && $status[0]) {
            $this->minBalance($balance, $this->user['min_balance_phone'], 1);
            $status[0] = false;
        } elseif ($this->user['min_balance_milestones'][1] > $balance && ($this->user['min_balance_milestones'][2] < $balance) && $status[1]) {
            $this->minBalance($balance, $this->user['min_balance_phone'], 2);
            $status[1] = false;
        } elseif ($this->user['min_balance_milestones'][2] > $balance && $status[2]) {
            $this->minBalance($balance, $this->user['min_balance_phone'], 3);
            $status[2] = false;
        }
        if ($balance > $this->user['min_balance_milestones'][2]) $status[2] = true;
        if ($balance > $this->user['min_balance_milestones'][1]) $status[1] = true;
        if ($balance > $this->user['min_balance_milestones'][0]) $status[0] = true;
        $this->db->where('id', $this->user['id']);
        $this->db->update('users', ['min_balance_ms1_trigger' => $status[0], 'min_balance_ms2_trigger' => $status[1], 'min_balance_ms3_trigger' => $status[2]]);
        return true;
    }

    private function minBalance($balance, $phone, $letter = 0)
    {
        $this->toLog('Достигнут минимальный баланс! (' . $balance . ')');

        $this->logDebug('Min balance TRIGGER: ['. $letter .'] = '. $balance .',,,, '. $phone);

        $this->sendMsg("Минимальный баланс! $balance", $phone);
    }

    public function createCsvReport($time = null)
    {
        if (!isset($this->user['id'])) {
            $this->load->library(array('ion_auth'));
            if (is_null($this->user = $this->ion_auth->user()->row_array()))
                $this->toLog("Не удалось определить пользователя");
            else
                $this->web = true;
        }
        $this->load->dbutil();
        $this->db->query("SET @rank=0;");
        $query = self::reportSelectQuery($this->user['id']);
        if (!is_null($time)) $query .= " AND unix_timestamp >= (CURRENT_DATE-INTERVAL $time)";
        $query = $this->db->query($query);
        $csv = $this->dbutil->csv_from_result($query);
        $csv = mb_convert_encoding($csv, 'CP1251', 'UTF-8');
        $path = FCPATH . 'uploads/' . $this->user['id'] . '_data.csv';
        file_put_contents($path, $csv);
        /** @var PHPExcel_Reader_CSV $objReader */
        $objReader = \PHPExcel_IOFactory::createReader('CSV');
        $objReader->setDelimiter(",");
        $objReader->setInputEncoding('CP1251');
        $objPHPExcel = $objReader->load($path);
        $d = array('A' => 3, 'B' => 10, 'C' => 25, 'D' => 5, 'E' => 86, 'F' => 15, 'G' => 20, 'H' => 15, 'I' => 48, 'J' => 58);
        foreach ($d as $col => $val) $objPHPExcel->getActiveSheet()->getColumnDimension($col)->setWidth($val);
        $objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
        $objPHPExcel->getActiveSheet()->getStyle('G2:G100')->getNumberFormat()->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_GENERAL);
        $objWriter->save($path);
        if ($this->web) {
            redirect((substr($path, strpos($path, '/uploads/'))));
        }

        return $path;
    }

    private function getGeoFromNumber($number)
    {
        return ' ';

        if (is_null($number)) return false;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array("phone" => $number, "get-phone-info" => "on",));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_URL, 'http://gsm-inform.ru/api/info/');
        $result = curl_exec($ch);
        $_result = json_decode($result);
        $result = $_result->text;
        if (!isset($_result->text)) $this->cantDeterminateRegion($_result);
        $result = mb_strtolower($result, 'UTF-8');
        $this->toLog("Определен регион для номера $number -> $result");
        return $result;
    }

    private function cantDeterminateRegion($data)
    {
        // создаем письмо
        $email = $this->createMail();
        $email->Subject = "СБОЙ СКРИПТА (ГЕО)";
        $email->Body = json_encode($data, JSON_UNESCAPED_UNICODE);
        $email->AddAddress($this->user['pzc']);
        $email->AddCC('agentrol@mail.ru');
        $status = $email->Send();
        // пишем в лог
        $this->toLog('Не удалось определить гео');
        $this->toLog('Подробности на почте ' . $this->user['pzc']);
        $this->toLog('ЭКСТРЕННОЕ ЗАВЕРШЕНИЕ!');
        $this->emailStatus(1);
        $this->END_ACTIVITY();
        die($status);
    }

    private function smsDown($data)
    {
        switch ($data['code']) {
            default:
                // создаем письмо
                $email = $this->createMail();
                $email->Subject = "СБОЙ СКРИПТА (СМС)";
                $email->Body = json_encode($data, JSON_UNESCAPED_UNICODE);
                $email->AddAddress($this->user['pzc']);
                $email->AddCC('agentrol@mail.ru');
                $email->Send();
                $this->toLog("Ошибка в смс, EMAIL-сообщение будет обработано повторно");
                $this->emailStatus(1);
                $this->END_ACTIVITY();
                break;
        }
    }

    private function emailStatus($status)
    {
        $this->db->where('id', $this->current_mail);
        $this->db->update('mails', ['status' => $status]);
    }

    public function END_ACTIVITY()
    {
        echo "== Остановка скрипта ==\n";
        $this->write_log(ob_get_contents());
        ob_flush();
    }

    private function write_log($data, $new = false) {
        if ($new) {
            $this->db->insert('logs', ['log' => $data, 'user_id' => $this->user['id']]);
            $this->log_id = $this->db->insert_id();
        } else {
            $this->db->where('id', $this->log_id);
            $this->db->update('logs', ['log' => $data, 'user_id' => $this->user['id']]);
        }
    }

    private function START_ACTIVITY($user)
    {
        $this->user = $this->prepareUser($user);
        if (!$this->user['sys_active']) return;
        ob_start();
        $date = \date('d/m/y H:i');
        $this->toLog("== << {$this->user['username']} >> ==");
        $this->toLog("== Запуск скрипта [$date] ==");
        $this->write_log("== << {$this->user['username']} >> ==\n== Запуск скрипта [$date] ==\n== В процессе... ==\n", true);
        $this->api_private = $this->user['priv_key'];
        $this->api_public = $this->user['pub_key'];
    }

    /**
     * @return ApiSms
     */
    private function getSms()
    {
        if (isset($this->apisms))
            unset($this->apisms);
        $this->load->library('ApiSms', array('api_private' => $this->api_private, 'api_pub' => $this->api_public, 'url_gateway' => $this->URL_GAREWAY), 'apisms');
        return $this->apisms;
    }

    private function deal($offers)
    {
        $cars = array_keys($this->car_to_sms);
        foreach ($offers as $offer) {
            $model_normalize = $this->normalizeModel($offer['model']);
            if (!isset($this->car_to_sms[$model_normalize])) foreach ($cars as $car) if (strpos($model_normalize, $car) !== false) {
                $model_normalize = $car;
                break;
            }
            $this->toLog("Нормализованная модель из таблицы: $model_normalize");
            if (isset($this->car_to_sms[$model_normalize][$offer['year']])) {
                $this->toLog('У нас есть предложение');
                $our_offer = $this->getOffer($model_normalize, $offer);
                $this->toLog("Цена предложения $our_offer");
                $this->msg[] = $this->getMsg($offer, $our_offer);
            } else {
                $this->toLog("У нас нет предложения ($model_normalize, $offer[year])");
            }
        }
    }

    private function getMsg(&$offer, &$our_offer)
    {
        return array("text" => str_replace(array('%MODEL%', '%SUMM%'), array($offer['model'], $our_offer), $this->user['text']), "reciver" => $offer['number'], "offer" => $our_offer, "car" => $offer, "geo" => $offer['geo'],);
    }

    /**
     * @param $model_normalize
     * @param $offer
     * @return mixed
     */
    private function getOffer(&$model_normalize, &$offer)
    {
        // Если true - автомат, в любом ином случае - механика
        if (is_array($this->car_to_sms[$model_normalize][$offer['year']])) {
            if ($offer['kpp_type'] && isset($this->car_to_sms[$model_normalize][$offer['year']][1])) $our_offer = $this->car_to_sms[$model_normalize][$offer['year']][1]; else
                $our_offer = $this->car_to_sms[$model_normalize][$offer['year']][0];
        } else
            $our_offer = $this->car_to_sms[$model_normalize][$offer['year']];
        if ($our_offer >= $offer['price']) $our_offer = $offer['price'] - 20;
        return $our_offer;
    }

    private function report($dbg = false)
    {
        $h = (int)date('G', $this->current_time);
        $m = (int)date('i', $this->current_time);
        if ($dbg) {
            $h = 21;
            $m = 0;
        }
        if ($m == 30 || $m == 0) {
            $time = "$h:$m";
            foreach ($this->user['report_time'] as $r) if ($time == $r) $this->sendReport(str_replace("%DATE%",
                \date('d/m/Y'), $this->user['mail_theme_1']), "24 HOUR");
            foreach ($this->user['report_time_single'] as $r) if ($time == $r) $this->sendReport(str_replace("%DATE%",
                \date('d/m/Y'), $this->user['mail_theme_2']));
        }
    }

    public function dev() {
        $users = $this->getUsers();
        $this->user = $this->prepareUser($users[1]);
        $this->msg = [
            [
                'text' => 'Готов приехать с деньгами за Вашим автомобилем. 330т.р. 89600479747',
                'reciver' => '0000000',
                'offer' => '330',
                'car' =>
                    array (
                        'model' => 'Hyundai i30',
                        'year' => 2010,
                        'price' => 390,
                        'number' => '0000000',
                        'href' => 'http://www.avito.ru/dzerzhinsk/avtomobili/hyundai_i30_2010_780233797',
                        'geo' => 'татар, mega11',
                        'kpp_type' => false,
                    ),
                'geo' => 'чуваш, mega11',
            ]
        ];
        $this->sendMessages();
    }


}