<?php
use Web\LogRead;

// глобальный конфиг
$config = unserialize(file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/data/config'));
$users = $config['users'];
require $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require $_SERVER['DOCUMENT_ROOT'] . '/global_head.php';
global $USER;
if (isset($_GET['user']))
    $USER = $_GET['user'];

$active_user = $USER;

$user_tabs = [];

if ($USER != 'admin')
    $users = array($USER);

$allRecords = [];

foreach ($users as $_u) {
    $csv = $_SERVER['DOCUMENT_ROOT'] . '/data/users/' . $_u . '/log.csv';
    $rows = array();
    if (($handle = fopen($csv, "r")) !== false) {
        while (($data = fgetcsv($handle, 1000, ";")) !== false) {
            if ($data[2] == 'Год' || !isset($data[2]) || !isset($data[ 6 ]) || $data[ 6 ]=='')
                continue;
            $row++;
            $data[0] = "<span class='hidden'>" . intval($data[0]) . "</span>" . date('d/m H:i', intval($data[0]));
            $data[3] = stripos($data[3], 'http') !== false ? "<a href='$data[3]'>" . substr($data[3], 0, 30) . "..
		.</a>" : $data[3];
            $data[4] .= 'т.р.';
            $data[6] .= 'т.р.';
            $rows[] = $data;
        }
    }

    $user_tabs[$_u] = $rows;
    $allRecords = array_merge($allRecords, $rows);
}

if ($USER == 'admin')
$user_tabs['TOTAL'] = $allRecords;

reset($user_tabs);
$first_key = key($user_tabs);
list($user_tabs[$first_key], $user_tabs[$active_user]) = array($user_tabs[$active_user], $user_tabs[$first_key]);

?>

<script src="/js/jquery-1.11.1.min.js"></script>
<div class="row">
    <div id="breadcrumb" class="col-md-12">
        <ol class="breadcrumb">
            <li><a href="/">Главная</a></li>
            <li><a href="/stats/">Статистика</a></li>
        </ol>
    </div>
</div>
<div class="row">
    <div class="col-xs-12">

        <?php $z=0; foreach ($user_tabs as $_u => $rows):

            ?>
            <div class="box">
                <div class="box-header">
                    <div class="box-name">
                        <i class="fa fa-linux"></i>
                        <span>Статистика пользователя <?=$_u?></span>
                    </div>
                    <div class="box-icons">
                        <a class="collapse-link">
                            <i class="fa fa-chevron-up"></i>
                        </a>
                        <a class="expand-link">
                            <i class="fa fa-expand"></i>
                        </a>
                    </div>
                    <div class="no-move"></div>
                </div>

                <div class="box-content no-padding table-responsive">
                    <table class="table table-bordered table-striped table-hover table-heading datatable-2
                    table-datatable"
                           id="datatable-2<?=$z?>">
                        <thead class="ccc">
                        <tr>
                            <th class="sort-desct">Дата<label><input type="text" name="search_rate" placeholder=""
                                                                     class="search_init"
                                        /></label></th>
                            <th>Модель<label><input type="text" name="search_name" placeholder=""
                                                    class="search_init"
                                        /></label></th>
                            <th>Год<label><input type="text" name="search_name" placeholder=""
                                                 class="search_init"
                                        /></label></th>
                            <th>Ссылка<label><input type="text" name="search_votes" placeholder=""
                                                    class="search_init"
                                        /></label></th>
                            <th>Цена А<label><input type="text" name="search_homepage" placeholder=""
                                                    class="search_init"
                                        /></label></th>
                            <th>Телефон<label><input type="text" name="search_version" placeholder=""
                                                     class="search_init"
                                        /></label></th>
                            <th>Цена Б<label><input type="text" name="search_version" placeholder=""
                                                    class="search_init"
                                        /></label></th>
                            <th>Текст <label><input type="text" name="search_version" placeholder=""
                                                    class="search_init"
                                        /></label></th>
                            <th>Гео <label><input type="text" name="search_version" placeholder=""
                                                  class="search_init"
                                        /></label></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php for ($i = 0; $i < 9; $i++): ?>
                                    <td><?= $row[$i] ?></td>
                                <?php endfor; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <th>Дата</th>
                            <th>Модель</th>
                            <th>Год</th>
                            <th>Ссылка</th>
                            <th>Цена продавца</th>
                            <th>Телефон</th>
                            <th>Предложенная цена</th>
                            <th>Текст сообщения</th>
                            <th>Гео</th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        <?php $z++; endforeach; ?>
    </div>
</div>
<script type="text/javascript">
    $(document).ready(function () {
        function AllTables() {
            TestTable1();
            <?php for ($i=0; $i<count($user_tabs); $i++):?>
                TestTable2(<?=$i?>);
            <?php endfor; ?>
            TestTable3();
            LoadSelect2Script(MakeSelect2);
        }

        function MakeSelect2() {
            $('select').select2();
            $('.dataTables_filter').each(function () {
                $(this).find('label input[type=text]').attr('placeholder', 'Search');
            });
        }

        // Load Datatables and run plugin on tables
        LoadDataTablesScripts(AllTables);
        // Add Drag-n-Drop feature
        WinMove();
    });
</script>
