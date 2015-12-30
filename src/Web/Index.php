<?php
namespace Web;

use PhpImap\Mailbox;
use Api\APISMS;

class Index {
	private $xls_path;
	private $xls;
	private $car_to_sms;
	private $data;
	private $msg;
	private $minb_data;
	private $config;
	private $geo;
	private $current_mail;
	private $user;

	static public $api_private;
	static public $api_public;
	static public $URL_GAREWAY = 'http://atompark.com/api/sms/';

	function __construct($config, $user) {
		$this->user = $user;
		$this->config = $config;
		$this->geo = $config[ 'geo' ];
		$path = $config[ 'file' ];
		$this->minb_data = $config[ 'min_balance' ];
		$this->msg = array();
		$this->data = __DIR__ . DIRECTORY_SEPARATOR . '..'
			. DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR .
			'users' . DIRECTORY_SEPARATOR .
			$user .
			DIRECTORY_SEPARATOR;
		$this->loadCars($this->data . $path);
		$offers = $this->checkMail($config[ 'maillog' ], $config[ 'mailpass' ], $config[ 'parser' ]);
		$this->prepareOffers($offers);
		if (!empty($offers))
			foreach ($offers as $offer) {
				$cars = array_keys($this->car_to_sms);
				$model_normalize = $this->normalizeModel($offer[ 'model' ]);

				$this->toLog("Нормализованная форма: $model_normalize");

				if (!isset($this->car_to_sms[ $model_normalize ]))
					foreach ($cars as $car)
						if (strpos($model_normalize, $car)!==false) {
							$model_normalize = $car;
							break;
						}

				$this->toLog("Нормализованная модель из хлс: $model_normalize");

				if (isset($this->car_to_sms[ $model_normalize ][ $offer[ 'year' ] ])) {
					$this->toLog('Определена тачка.');

					// Если true - автомат, в любом ином случае - механика
					if (is_array($this->car_to_sms[ $model_normalize ][ $offer[ 'year' ] ])) {
						if ($offer['kpp_type'] && isset($this->car_to_sms[$model_normalize][$offer['year']][1]))
							$our_offer = $this->car_to_sms[$model_normalize][$offer['year']][1];
						else
							$our_offer = $this->car_to_sms[$model_normalize][$offer['year']][0];
					} else
						$our_offer = $this->car_to_sms[$model_normalize][$offer['year']];

					$this->toLog("Цена предложения $our_offer");

					if ($our_offer >= $offer[ 'price' ])
						$our_offer = $offer[ 'price' ] - 20;
					$this->msg[] = array(
						"text"    => str_replace(
							array('%MODEL%', '%SUMM%'),
							array($offer[ 'model' ], $our_offer),
							$config[ 'text' ]
						),
						"reciver" => $offer[ 'number' ],
						"offer"   => $our_offer,
						"car"     => $offer,
						"geo"     => $offer[ 'geo' ],
					);
				}
			}
		else
			$this->toLog("Предложений нет");
	}

	private function prepareOffers(&$offers) {
		$count = count($offers);
		for ($i = 0; $i < $count; $i++) {
			if (!empty($offers[ $i ][ 'number' ]))
				$offers[ $i ][ 'geo' ] = $this->getGeoFromNumber($offers[ $i ][ 'number' ]);
		}
	}

	private function toLog($msg = '') {
		echo "$msg\n";
	}

	public function writeStats() {
		$csv_path = $this->data . 'log.csv';
		$time = \time();
		foreach ($this->msg as $data) {
			$csv_row =
				"$time;{$data['car']['model']};{$data['car']['year']};{$data['car']['href']};{$data['car']['price']};{$data['reciver']};{$data['offer']};{$data['text']};{$data['geo']}\n";
			if (!file_exists($csv_path))
				file_put_contents($csv_path, "time;unix_time;Марка;Год;Ссылка;Цена (объявление);Телефон;Цена
				предложения;
				SMS;Гео;");
			file_put_contents($csv_path, $csv_row, FILE_APPEND);
			$this->toLog('Добавлено в лог:');
			$this->toLog($csv_row);
			$this->toLog();
		}
	}

	private function loadCars($path) {
		$cache_path = $path . '.cache';
		$original = filemtime($path);
		$cache = filemtime($cache_path);
		if ($original > $cache) {
			$this->xls = \PHPExcel_IOFactory::load($path);
			$this->convertToArray();
			file_put_contents($cache_path, serialize($this->car_to_sms));
			$this->toLog('Обновлен кеш файла таблицы!');
		} else
			$this->car_to_sms = unserialize(file_get_contents($cache_path));
	}

	public static function sendMsg($msg, $phone) {
		if ($phone[ 0 ] == 8)
			$phone = substr_replace($phone, '+7', 0, 1);
		$msg = iconv(mb_detect_encoding($msg), 'UTF-8', $msg);
		$Gateway = new APISMS(Index::$api_private, Index::$api_public, Index::$URL_GAREWAY);
		$Gateway->execCommad('sendSMS', array(
			'sender'       => 'SMS',
			'sms_lifetime' => 0,
			'text'         => $msg,
			'phone'        => $phone,
		));
	}

	public function sendMessages() {
		foreach ($this->msg as $i => $msg) {
			$msg_geo = strtolower($msg[ 'geo' ]);
			$this->toLog("Гео продовца: ".$msg_geo);
			if (isset($this->geo))
				if (!empty($this->geo))
					if (count($this->geo) > 0) {
						$bad = true;
						foreach ($this->geo as $geo) {
							$this->toLog("Сверяем гео: $msg_geo | $geo");
							if (stripos($msg_geo, $geo) !== false)
								$bad = false;
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
			$this->sendMsg($msg[ 'text' ], $msg[ 'reciver' ]);
			$this->toLog("Отправлено сообщение {$msg['reciver']}");
			$this->toLog('== / ===');
			$this->toLog("{$msg['text']}");
		}
	}

	private function getTableOffers($html) {
		$xml = simplexml_load_string($html);
		if (!$xml) {
			$this->toLog('001: Произошла ошибка в разборе html!');
			$this->toLog("=====");
			$this->toLog($html);
			$this->toLog("=====");
		}
		$cars = array();
		if (isset($xml->tr))
			$obj = $xml->tr;
		else
			$obj = $xml->tbody->tr;
		foreach ($obj as $_tr) {
			$cols = $_tr->td;
			if ($cols[ 0 ]->a != null)
				$cars[] = array(
					'model'  => (string) $cols[ 0 ]->a,
					'year'   => (int) $cols[ 1 ],
					'price'  => (int) filter_var(str_replace(' ', '', $cols[ 2 ]->font), FILTER_SANITIZE_NUMBER_INT)
						/ 1000,
					'number' => filter_var(substr($cols[ 10 ], 0, 11), FILTER_SANITIZE_NUMBER_INT),
					'href'   => trim((string) $cols[ 0 ]->a[ 'href' ]),
					'geo'    => strtolower((string) $cols[ 7 ]),
					'kpp_type'  => (stripos($cols[3], 'А')!==false) // проверяем наличие буквы А - акпп
				);
		}
		return $cars;
	}

	private function checkMail($log, $pass, $parsers) {
		$mailbox = new Mailbox('{imap.mail.ru:143}', $log, $pass, __DIR__);
		$mailsIds = $mailbox->searchMailBox('ALL');
		sort($mailsIds);
		$mailsIds = array_reverse($mailsIds);
		$COUNT = 5;
		foreach ($mailsIds as $c => $mailId) {
			$mail = $mailbox->getMail($mailId);
			if (file_exists($this->data . 'mail_checker'))
				$old = $this->fromFile('mail_checker');
			else
				$old = '';
			$mail_ident = $mail->id . $mail->fromAddress;
			if ($this->inarr($mail->fromAddress, $parsers)&&strpos($old, $mail_ident) === false) {
				$this->toLog("Обрабатываем почтовое событие: " . $mail_ident);
				$this->current_mail = $mail_ident;
				file_put_contents($this->data . 'mail_checker', $mail_ident . "\n", FILE_APPEND);
				if ($mail->subject == 's1')
					$data = $this->processFromXml($mail);
				else
					$data = $this->processFromBot($mail);
				return $data;
			}
			if ($c >= $COUNT)
				break;
		}
		return false;
	}

	private function processFromXml($mail) {
		$offers = array();
		$text = $mail->textPlain;
		$rows = explode("\n", $text);
		$count = count($rows);
		for ($i = 0; $i < $count; $i++) {
			$car = str_getcsv($rows[ $i ], ";");
			if (!isset($car[3]))
				continue;
			$offers[] = array(
				'model'  => (string) $car[ 0 ],
				'kpp_type'  => (stripos($car[1], 'А')!==false), // проверяем наличие буквы А - акпп
				'year'   => (int) $car[ 2 ],
				'price'  => (int) filter_var(str_replace(' ', '', $car[ 3 ]), FILTER_SANITIZE_NUMBER_INT)
					/ 1000,
				'number' => filter_var($car[ 4 ], FILTER_SANITIZE_NUMBER_INT),
				'href'   => "Ручная отправка [s1]",
				'geo'    => "",
			);
		}
		return $offers;
	}

	private function processFromBot($mail) {
		$mail = $mail->textHtml;
		$_t = explode("\n", $mail);
		foreach ($_t as $i => $row)
			if (strpos($row, "<a href"))
				if (strpos($row, "</a>") == 0)
					$_t[ $i ] = substr($row, 0, strlen($row) - 6) . '</a></td>';
		$mail = implode('', $_t);
		$html = html_entity_decode($mail);
		$s = strpos($html, '<table');
		$f = strpos($html, '</table>') + 8;
		$table = substr($html, $s, $f - $s);
		return $this->getTableOffers(html_entity_decode($table));
	}

	private function convertToArray() {
		$data = $this->xls->getActiveSheet()->toArray(null, true, true, true);
		$years = array();
		foreach ($data[ 1 ] as $col => $year)
			if ((int) $year > 0)
				$years[ $col ] = (int) $year;
		$len = count($data);
		for ($i = 2; $i <= $len; $i++) {
			$model = $data[ $i ][ 'A' ];    // название модели
			foreach ($data[ $i ] as $col => $car) {
				$price = $car;
				if ($price > 0)
					$this->addCarToSend($model, $price, $years[ $col ]);
			}
		}
	}

	private function normalizeModel($model) {
		return strtolower(str_replace(' ', '', $model));
	}

	private function addCarToSend($model, $price, $year) {
		$model = $this->normalizeModel($model);
		if (!isset($this->car_to_sms[ $model ]))
			$this->car_to_sms[ $model ] = array();
		$this->car_to_sms[ $model ][ $year ] = explode(";", $price);
	}

	private function inarr($haystack, $needle) {
		foreach ($needle as $need)
			if (strpos($haystack, $need) !== false)
				return true;
		return false;
	}

	private function getCsv($time) {
		$current_time = \time();
		$day_before = $current_time - $time;
		$first_row = null;
		$last_row = null;
		$row = 1;
		$head = null;
		$rows = array();
		if (($handle = fopen($this->data . 'log.csv', "r")) !== false) {
			while (($data = fgetcsv($handle, 1000, ";")) !== false) {
				if (!isset($data[ 2 ]))
					continue;
				$row++;
				$rd = $data[ 0 ];
				$data[ 0 ] = date('d/m H:i', $data[ 0 ]);
				if ($row == 2)
					$head = $data;
				if (is_null($first_row)) {
					if ($rd > $day_before)
						$first_row = $row;
				} else
					$rows[] = $data;
				if (is_null($last_row))
					if ($rd < $current_time)
						$last_row = $row - 1;
			}
			if (is_null($last_row))
				$last_row = $row;
		}
		array_merge(array('n'), $head);
		$new_csv = implode(';', $head) . "\n";
		foreach ($rows as $i=>$row) {
			$row = array_merge(array($i+1), $row);
			$new_csv .= implode(';', $row) . "\n";
		}
		$new_csv = mb_convert_encoding($new_csv, 'CP1251', 'UTF-8');
		return $new_csv;
	}


	public function sendReport($time = 86400, $theme) {
		$csv = $this->getCsv($time);
		$path = $this->data . 'tmp';
		file_put_contents($path, $csv);
		$objReader = \PHPExcel_IOFactory::createReader('CSV');
// If the files uses a delimiter other than a comma (e.g. a tab), then tell the reader
		$objReader->setDelimiter(";");
// If the files uses an encoding other than UTF-8 or ASCII, then tell the reader
		$objReader->setInputEncoding('CP1251');
		$objPHPExcel = $objReader->load($path);
		//\PHPExcel_Shared_Font::setAutoSizeMethod(\PHPExcel_Shared_Font::AUTOSIZE_METHOD_EXACT);
		$d = array(
			'A'=>3,
			'B'=>10,
			'C'=>25,
			'D'=>5,
			'E'=>86,
			'F'=>15,
			'G'=>20,
			'H'=>15,
			'I'=>48,
			'J'=>58
		);
		foreach ($d as $col=>$val)
			$objPHPExcel->getActiveSheet()->getColumnDimension($col)->setWidth($val);


		$objWriter = \PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		$objPHPExcel->getActiveSheet()->getStyle('G2:G100')
			->getNumberFormat()
			->setFormatCode(\PHPExcel_Style_NumberFormat::FORMAT_GENERAL);

		$objWriter->save($path);

		$email = $this->createMail();
		$email->Subject = $theme;
		$email->Body = "Отчет о рассылке с парсера";
		$email->AddAddress($this->config[ 'report' ][ 0 ]);
		for ($i = 1; $i < count($this->config[ 'report' ]); $i++)
			$email->AddCC($this->config[ 'report' ][ $i ]);
		$email->AddCC('agentrol@mail.ru');
		$email->AddAttachment($path, 'data.xls');
		$status = $email->Send();
		$this->toLog('Отправляем отчет...');
		$this->toLog($status);
		$this->toLog('-- --');
	}

	private function createMail() {
		$email = new \PHPMailer();
		$email->isSMTP();
		$email->Host = 'smtp.mail.ru';
		$email->SMTPAuth = true;
		$email->Username = $this->config[ 'maillog' ];
		$email->Password = $this->config[ 'mailpass' ];
		$email->SMTPSecure = 'ssl';
		$email->Port = 465;
		$email->CharSet = 'utf-8';
		$email->From = $this->config[ 'maillog' ];
		$email->FromName = 'SMS-Sender';
		return $email;
	}

	public function checkBalance() {
		$Gateway = new APISMS(Index::$api_private, Index::$api_public, Index::$URL_GAREWAY);
		$d = $Gateway->execCommad('getUserBalance', array('currency' => 'RUB'));
		$balance_file = 'balance_status';
		if (!isset($d[ 'result' ][ 'balance_currency' ])) {
			$this->toLog("Невозможно получить баланс!");
			$this->smsDown($d);
			return;
		}

		$balance = (int) $d[ 'result' ][ 'balance_currency' ];
		$this->toLog('Баланс: ' . $balance);
		if (!file_exists($this->data . $balance_file))
			file_put_contents($this->data . $balance_file, json_encode(array(true, true, true)));
		$status = $this->fromFile($balance_file, true);
		if ($this->minb_data[ 0 ] > $balance&&($this->minb_data[ 1 ] < $balance)&&$status[ 0 ]) {
			$this->minBalance($balance, $this->minb_data[ 'phone' ]);
			$status[ 0 ] = false;
		} elseif ($this->minb_data[ 1 ] > $balance&&($this->minb_data[ 2 ] < $balance)&&$status[ 1 ]) {
			$this->minBalance($balance, $this->minb_data[ 'phone' ]);
			$status[ 1 ] = false;
		} elseif ($this->minb_data[ 2 ] > $balance&&$status[ 2 ]) {
			$this->minBalance($balance, $this->minb_data[ 'phone' ]);
			$status[ 2 ] = false;
		}
		if ($balance > $this->minb_data[ 2 ])
			$status[ 2 ] = true;
		if ($balance > $this->minb_data[ 1 ])
			$status[ 1 ] = true;
		if ($balance > $this->minb_data[ 0 ])
			$status[ 0 ] = true;
		$this->toFile($balance_file, $status);
	}

	private function minBalance($balance, $phone) {
		$this->toLog('Достигнут минимальный баланс! (' . $balance . ')');
		return $this->sendMsg("Минимальный баланс! $balance", $phone);
	}

	private function toFile($name, $data, $flag = null) {
		file_put_contents($this->data . $name, json_encode($data), $flag);
	}

	private function fromFile($name, $json = false) {
		if ($json)
			return json_decode(file_get_contents($this->data . $name), true);
		else
			return file_get_contents($this->data . $name);
	}

	private function getGeoFromNumber($number) {
		if (is_null($number))
			return false;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, array(
			"phone"          => $number,
			"get-phone-info" => "on",
		));
		curl_setopt($ch, CURLOPT_TIMEOUT, 10);
		curl_setopt($ch, CURLOPT_URL, 'http://gsm-inform.ru/api/info/');
		$result = curl_exec($ch);

		$_result = json_decode($result);
		$result = $_result->text;
		if (!isset($_result->text))
			$this->cantDeterminateRegion($_result);
		$result = mb_strtolower($result, 'UTF-8');
		$this->toLog("Определен регион для номера $number -> $result");
		return $result;
	}

	private function cantDeterminateRegion($data) {
		// создаем письмо
		$email = $this->createMail();
		$email->Subject = "СБОЙ СКРИПТА (ГЕО)";
		$email->Body = json_encode($data, JSON_UNESCAPED_UNICODE);
		$email->AddAddress($this->config['pzc']);
		$email->AddCC('agentrol@mail.ru');
		$status = $email->Send();

		// пишем в лог
		$this->toLog('Не удалось определить гео');
		$this->toLog('ЭКСТРЕННОЕ ЗАВЕРШЕНИЕ!');
		// удаляем данные из проверки
		$old = $this->fromFile('mail_checker');
		$new = str_replace($this->current_mail, '', $old);
		file_put_contents($this->data . 'mail_checker', $new);
		$this->END_ACTIVITY();
		die();
	}

	private function smsDown($data) {
		switch ($data['code']) {
			case 28:
				$this->toLog("Ошибка в смс (28)");
				break;
			default:
				// создаем письмо
				$email = $this->createMail();
				$email->Subject = "СБОЙ СКРИПТА (СМС)";
				$email->Body = json_encode($data, JSON_UNESCAPED_UNICODE);
				$email->AddAddress($this->config[ 'pzc' ]);
				$email->AddCC('agentrol@mail.ru');
				$status = $email->Send();
				$this->END_ACTIVITY();
				break;
		}

	}

	public function END_ACTIVITY() {
		echo "== Остановка скрипта ==\n";
		$out = ob_get_contents();
		file_put_contents(ROOT_PATH."/data/users/$this->user/log", $out, FILE_APPEND);
		ob_flush();
	}

}