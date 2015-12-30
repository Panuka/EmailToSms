<?php
use Web\LogRead;

// глобальный конфиг
$config = unserialize(file_get_contents($_SERVER[ 'DOCUMENT_ROOT' ] . '/data/config'));
require $_SERVER[ 'DOCUMENT_ROOT' ] . '/vendor/autoload.php';
require $_SERVER['DOCUMENT_ROOT'].'/global_head.php';
if ($_SERVER[ 'REQUEST_METHOD' ] == 'POST') {
	if ($_FILES[ 'file' ][ 'size' ] > 0) {
		move_uploaded_file($_FILES[ 'file' ][ 'tmp_name' ], $_SERVER[ 'DOCUMENT_ROOT' ] . "/data/users/$USER/" .
			$_FILES[ 'file' ][ 'name' ]);
		$config[ 'file' ] = $_FILES[ 'file' ][ 'name' ];
	}
	if (!is_array($config))
		$config = array();
	$parsers = explode(',', $_POST[ 'parser' ]);
	foreach ($parsers as $i => $p)
		$parsers[ $i ] = trim($p);
	$report = explode(',', $_POST[ 'report' ]);
	foreach ($report as $i => $p)
		$report[ $i ] = trim($p);
	$geos = explode(',', $_POST[ 'geo' ]);
	foreach ($geos as $i => $p)
		if (empty($geos[$i]))
			unset($geos[$i]);
		else
			$geos[ $i ] = mb_strtolower(trim($p), 'UTF-8');
	foreach ($_POST[ 'report_time_single' ] as $i=>$t)
		$_POST[ 'report_time_single' ][$i] = explode(':', $t);
	foreach ($_POST[ 'report_time' ] as $i=>$t)
		$_POST[ 'report_time' ][$i] = explode(':', $t);
	$config[ 'text' ] = $_POST[ 'text' ];
	$config[ 'report_time_single' ] = $_POST[ 'report_time_single' ];
	$config[ 'report_time' ] = $_POST[ 'report_time' ];
	$config[ 'parser' ] = $parsers;
	$config[ 'pub_key' ] = $_POST[ 'pub_key' ];
	$config[ 'priv_key' ] = $_POST[ 'priv_key' ];
	$config[ 'geo' ] = $geos;
	$config[ 'maillog' ] = $_POST[ 'maillog' ];
	$config[ 'mail_theme_1' ] = $_POST[ 'mail_theme_1' ];
	$config[ 'mail_theme_2' ] = $_POST[ 'mail_theme_2' ];
	$config[ 'pzc' ] = $_POST[ 'pzc' ];
	$config[ 'min_balance' ] = $_POST[ 'min_balance' ];
	$config[ 'mailpass' ] = $_POST[ 'mailpass' ];
	$config[ 'httpauth' ][ 0 ] = $_POST[ 'httplog' ];
	$config[ 'httpauth' ][ 1 ] = $_POST[ 'httppass' ];
	$config[ 'httpauth' ][ 2 ] = $_POST[ 'statpass' ];
	$config[ 'report' ] = $report;
	$config[ 'report_time' ] = $_POST[ 'report_time' ];
	$config[ 'on' ] = isset($_POST[ 'on' ]) ? true : false;
	file_put_contents($_SERVER[ 'DOCUMENT_ROOT' ] . "/data/users/$USER/config", serialize($config));
//	header('Location: /');
}

$log = new LogRead("data/users/$USER/log");
error_reporting(E_ERROR | E_PARSE);
if (is_array($config[ 'parser' ]))
	$config[ 'parser' ] = implode(',', $config[ 'parser' ]);
if (is_array($config[ 'report' ]))
	$config[ 'report' ] = implode(',', $config[ 'report' ]);
if (is_array($config[ 'geo' ]))
	$config[ 'geo' ] = implode(',', $config[ 'geo' ]);
foreach ($config[ 'report_time_single' ] as $i=>$t)
	$config[ 'report_time_single' ][$i] = implode(':', $t);
foreach ($config[ 'report_time' ] as $i=>$t)
	$config[ 'report_time' ][$i] = implode(':', $t);
include $_SERVER['DOCUMENT_ROOT'].'/header.php'; ?>



	<section>
		<div class="tp-banner-container" style="">
			<div class="tp-banner">
				<form method="POST" enctype="multipart/form-data">
					<div class="row">
						<div class="container">
							<table cellspacing="0" border="0" class="table table-responsive" width="634px">
								<tr class="form-group">
									<td><label for="text">Текст сообщения</label><br>
										%MODEL% - модель автомобиля;
										%SUMM% - сумма из шаблона.
									</td>
									<td><textarea class="form-control" id="text" name="text" rows="3"
									              cols="40"><?=$config[ 'text' ]?></textarea></td>
								</tr>
								<tr class="form-group">
									<td><label for="file">.xls / .xlsx</label></td>
									<td>
										<div class="form-group">
											<label>Текущий файл: [<?=$config[ 'file' ]?>]</label>
											<input  type="file" id="file" name="file">
										</div>
									</td>
								</tr>
								<tr class="form-group">
									<td><label for="on">Включить/выключить</label></td>
									<td>

										<div class="checkbox">
											<label>
												<input class="form-control" type="checkbox" id="on"
												       name="on" <?=$config[ 'on' ] ? 'checked' : ''?> style="width: auto;">
											</label>
										</div>

									</td>
								</tr>
								<tr class="form-group">
									<td><label for="parser">Email парсера, с которого приходят сообщения</label></td>
									<td><input class="form-control" type="text" id="parser" name="parser"
									           value="<?=$config[ 'parser' ]?>"
									           placeholder="parser@mail.ru"></td>
								</tr>
								<tr class="form-group">
									<td><label for="geo">Гео-таргетинг</label><br>
										Фильтрует сообщения по местоположению.
										Пусто - не фильтрует.
									</td>
									<td><input class="form-control" type="text" id="geo" name="geo"
									           value="<?=$config[ 'geo' ]?>"></td>
								</tr>
								<tr class="form-group">
									<td><label for="report">Email для отчетов</label><br>
										Для множественной рассылки - через запятую.
									</td>
									<td><input class="form-control" type="text" id="report" name="report"
									           value="<?=$config[ 'report' ]?>" placeholder="mail@mail.ru"></td>
								</tr>
								<tr class="form-group">
									<td><label for="mail_theme_1">Тема письма (сутки)</label><br>
									%DATE% - дата
									</td>
									<td><input class="form-control" type="text" id="mail_theme_1"
									name="mail_theme_1"
									           value="<?=$config[ 'mail_theme_1' ]?>"
									           placeholder="Тема письма"></td>
								</tr>
								<tr class="form-group">
									<td><label for="report_time">Отчет за сутки </label></td>
									<td>
										<select class="form-control" id="report_time" name="report_time[]"  multiple="multiple">
											<option value="false">Выключить</option>
											<?php for($i=0; $i<24; $i++): ?>
												<option value="<?="$i:00"?>" <?=in_array("$i:00", $config[ 'report_time' ])
												?'selected':''?>><?php printf('%02d:00', $i)?></option>
												<option value="<?="$i:30"?>" <?=in_array("$i:30", $config[ 'report_time' ])
												?'selected':''?>><?php printf('%02d:30', $i)?></option>
											<?php endfor; ?>
										</select>
									</td>
								</tr>
								<tr class="form-group">
									<td><label for="mail_theme_2">Тема письма (полный отчет)</label></td>
									<td><input class="form-control" type="text" id="mail_theme_2"
									name="mail_theme_2"
									           value="<?=$config[ 'mail_theme_2' ]?>"
									           placeholder="Тема письма"></td>
								</tr>
								<tr class="form-group">
									<td><label for="report_time_single">Полная выгрузка </label></td>
									<td>
										<select class="form-control" id="report_time_single"
										name="report_time_single[]"   multiple="multiple">
											<option value="false">Выключить</option>
											<?php for($i=0; $i<24; $i++): ?>
												<option value="<?="$i:00"?>" <?=in_array("$i:00", $config[
												'report_time_single' ])
												?'selected':''?>><?php printf('%02d:00', $i)?></option>
												<option value="<?="$i:30"?>" <?=in_array("$i:30", $config[
												'report_time_single' ])
												?'selected':''?>><?php printf('%02d:30', $i)?></option>
											<?php endfor; ?>
										</select>
									</td>
								</tr>
								<tr class="form-group">
									<td><label for="min_balance1">Минимальный баланс 1</label></td>
									<td><input class="form-control" type="text" id="min_balance1" name="min_balance[0]"
									           value="<?=$config[ 'min_balance' ][0]?>"
									           placeholder="100"></td>
								</tr>
								<tr class="form-group">
									<td><label for="min_balance2">Минимальный баланс 2</label></td>
									<td><input class="form-control" type="text" id="min_balance2" name="min_balance[1]"
									           value="<?=$config[ 'min_balance' ][1]?>"
									           placeholder="50"></td>
								</tr>
								<tr class="form-group">
									<td><label for="min_balance3">Минимальный баланс 3</label></td>
									<td><input class="form-control" type="text" id="min_balance3" name="min_balance[2]"
									           value="<?=$config[ 'min_balance' ][2]?>"
									           placeholder="25"></td>
								</tr>
								<tr class="form-group">
									<td><label for="min_balance">Номер для уведомления</label></td>
									<td><input class="form-control" type="text" id="min_balance"
									name="min_balance[phone]"
									           value="<?=$config[ 'min_balance' ]['phone']?>"
									           placeholder="100"></td>
								</tr>
								<tr class="form-group">
									<td><label for="pzc">Почта для экстренных оповещаний</label></td>
									<td><input class="form-control" type="text" id="pzc"
									name="pzc"
									           value="<?=$config[ 'pzc' ]?>"></td>
								</tr>
								<tr class="form-group">
									<td><label for="maillog">Логин почты</label></td>
									<td><input class="form-control" type="email" id="maillog" name="maillog"
									           value="<?=$config[ 'maillog' ]?>"
									           placeholder="name@mail.ru"></td>
								</tr>
								<tr class="form-group">
									<td><label for="mailpass">Пароль почты</label></td>
									<td><input class="form-control" type="password" id="mailpass" name="mailpass"
									           value="<?=$config[ 'mailpass' ]?>"
									           placeholder="password"></td>
								</tr>
								<tr class="form-group">
									<td><label for="httppass">Пароль админки</label></td>
									<td><input class="form-control" type="password" id="httppass" name="httppass"
									           value="<?=$config[ 'httpauth' ][ 1 ]?>"></td>
								</tr>
								<tr class="form-group">
									<td><label for="statpass">Пароль статистики (опционально)</label></td>
									<td><input class="form-control" type="password" id="statpass" name="statpass"
									           value="<?=$config[ 'httpauth' ][ 2 ]?>"></td>
								</tr>

								<tr class="form-group">
									<td><label for="pub_key">Public SMS-key</label></td>
									<td><input class="form-control" type="text" id="pub_key" name="pub_key"
									           value="<?=$config[ 'pub_key' ]?>"></td>
								</tr>
								<tr class="form-group">
									<td><label for="priv_key">Private SMS-key</label></td>
									<td><input class="form-control" type="text" id="priv_key" name="priv_key"
									           value="<?=$config[ 'priv_key' ]?>"></td>
								</tr>
								<tr class="form-group">
									<td></td>
									<td align="right"><input type="submit" value="Сохранить"
									class="btn btn-primary"></td>
								</tr>
							</table>
						</div>
					</div>
				</form>
			</div>
		</div>
	</section>
</header>


<div class="wrapper">
        <section id="about" style="background: url('../img/freeze/bk-freeze.jpg');">
            <div class="container">

                <div class="section-heading scrollpoint sp-effect3">
                    <h1>Log</h1>
                    <div class="divider"></div>
                    <p></p>
                </div>

                <div class="log">
                    <pre><?=$log->tail(50)?></pre>
                </div>
            </div>
        </section>

<?php include $_SERVER['DOCUMENT_ROOT'].'/footer.php'; ?>