<?php
namespace Core2\Mod\Proxy\Index;
use Core2\Classes\Table;


require_once DOC_ROOT . "core2/inc/classes/Common.php";
require_once DOC_ROOT . "core2/inc/classes/Alert.php";
require_once DOC_ROOT . "core2/inc/classes/class.edit.php";
require_once DOC_ROOT . "core2/inc/classes/Table/Db.php";


/**
 * @property \ModProxyController $modProxy
 */
class View extends \Common {


    /**
     * @param string $base_url
     * @return false|string
     * @throws \Zend_Db_Select_Exception
     * @throws \Exception
     */
    public function getTableActive(string $base_url): string {

        $table = new Table\Db($this->resId . 'xxx_active');
        $table->setTable("mod_proxy");
        $table->setPrimaryKey('id');
        $table->showColumnManage();

        $select = $this->modProxy->dataProxy->getSelect()
            ->joinLeft(['pd' => 'mod_proxy_domains'], "pd.proxy_id = p.id AND date_used = DATE_FORMAT(NOW(), '%Y-%m-%d')", [
                'domains' => "GROUP_CONCAT(CONCAT_WS(',', pd.domain, pd.count_success, pd.count_failed) SEPARATOR '|')",
            ])
            ->where("p.is_active_sw = 'Y'")
            ->group("p.id")
            ->order("p.date_created DESC");
        $table->setData($select);


        $table->addFilter('p.level_anonymity', $table::FILTER_CHECKBOX, $this->_("Уровень анонимности"))
            ->setData($this->modProxy->dataProxy->getLevelsAnonymity());

        $table->addSearch($this->_("Адрес"),               'p.address');
        $table->addSearch($this->_("Порт"),                'p.port');
        $table->addSearch($this->_("SSL"),                 'p.is_ssl_sw', $table::SEARCH_SELECT)->setData(['Y' => 'Да', 'N' => 'Нет']);
        $table->addSearch($this->_("Страна"),              'p.country');
        $table->addSearch($this->_("Источник"),            'p.source_name');
        $table->addSearch($this->_("Дата тестирования"),   'p.date_last_tested', $table::SEARCH_DATE);
        $table->addSearch($this->_("Дата использования"),  'p.date_last_used', $table::SEARCH_DATE);


        $table->addColumn($this->_("Адрес"),                'address',          $table::COLUMN_TEXT, 130);
        $table->addColumn($this->_('Порт'),                 'port',             $table::COLUMN_TEXT, 80);
        $table->addColumn($this->_("SSL"),                  'is_ssl_sw',        $table::COLUMN_HTML, 80);
        $table->addColumn($this->_("Уровень анонимности"),  'level_anonymity',  $table::COLUMN_HTML, 160);
        $table->addColumn($this->_("Рейтинг"),              'rating',           $table::COLUMN_HTML, 80);
        $table->addColumn($this->_("Домены"),               'domains',          $table::COLUMN_HTML);
        $table->addColumn($this->_("Логин"),                'server_login',     $table::COLUMN_HTML, 110)->hide();
        $table->addColumn($this->_("Пароль"),               'server_password',  $table::COLUMN_HTML, 110)->hide();
        $table->addColumn($this->_("Подключение"),          'connect_time',     $table::COLUMN_HTML, 110)->hide();
        $table->addColumn($this->_("Пред передача"),        'pretransfer_time', $table::COLUMN_HTML, 115)->hide();
        $table->addColumn($this->_("Страна"),               'country',          $table::COLUMN_TEXT, 70);
        $table->addColumn($this->_("Источник"),             'source_name',      $table::COLUMN_TEXT)->hide();
        $table->addColumn($this->_("Дата тестирования"),    'date_last_tested', $table::COLUMN_DATETIME, 150);
        $table->addColumn($this->_("Дата использования"),   'date_last_used',   $table::COLUMN_DATETIME, 150);
        $table->addColumn($this->_(""),                     'is_active_sw',     $table::COLUMN_SWITCH, 1);



        $table->setAddUrl("{$base_url}&edit=0");
        $table->setEditUrl("{$base_url}&edit=TCOL_ID");
        $table->showDelete();

        $rows = $table->fetchRows();
        if ( ! empty($rows)) {
            foreach ($rows as $row) {

                $domains     = [];
                $domains_raw = explode('|', $row->domains);

                foreach ($domains_raw as $domain_raw) {
                    if ($domain_raw) {
                        $domain_explode = explode(',', $domain_raw);
                        $domains[]      = "{$domain_explode[0]} <b class=\"text-success\">{$domain_explode[1]}</b> / <b class=\"text-danger\">{$domain_explode[2]}</b>";
                    }
                }

                $row->domains = implode(', ', $domains);


                // Цены
//                if ($row->prices->getValue() > 0) {
//                    $url = "index.php?module=products&action=index&page=table_prices&product_id={$row->id}";
//
//                    $row->prices->setAttr('onclick', "event.cancelBubble = true;");
//                    $row->prices = "
//                        <button type=\"button\" class=\"btn btn-xs btn-default\"
//                                onclick=\"CoreUI.table.toggleExpandColumn('[RESOURCE]', '{$key}', '{$url}')\">
//                            {$row->prices} <i class='fa fa-chevron-down'></i>
//                        </button>
//                    ";
//
//                } else {
//                    $row->prices = 0;
//                }
//

                $title = $this->modProxy->dataProxy->getLevelAnonymity((string)$row->level_anonymity);
                switch ($row->level_anonymity->getValue()) {
                    case 'elite':         $row->level_anonymity = '<span class="label label-success">' . $title . '</span>'; break;
                    case 'anonymous':     $row->level_anonymity = '<span class="label label-primary">' . $title . '</span>'; break;
                    case 'non_anonymous': $row->level_anonymity = '<span class="label label-danger">' . $title . '</span>'; break;
                }

                switch ($row->is_ssl_sw->getValue()) {
                    case 'Y': $row->is_ssl_sw = '<span class="label label-success">Да</span>'; break;
                    case 'N': $row->is_ssl_sw = '<span class="label label-warning">Нет</span>'; break;
                }


                if ($row->rating->getValue() <= -10) {
                    $rating_status = 'danger';

                } elseif ($row->rating->getValue() > -10 && $row->rating->getValue() < 0) {
                    $rating_status = 'warning';

                } elseif ($row->rating->getValue() == 0) {
                    $rating_status = 'info';

                } elseif ($row->rating->getValue() > 0 && $row->rating->getValue() < 5) {
                    $rating_status = 'primary';

                } elseif ($row->rating->getValue() >= 5){
                    $rating_status = 'success';
                }

                $row->rating = "<span class=\"label label-{$rating_status}\">" . $row->rating->getValue() . '</span>';

                if ($row->connect_time->getValue() > 0) {
                    $row->connect_time = $row->connect_time->getValue() . ' <small class="text-muted">сек</small>';
                }


                if ($row->pretransfer_time->getValue() > 0) {
                    $row->pretransfer_time = $row->pretransfer_time->getValue() . ' <small class="text-muted">сек</small>';
                }

                if (mb_strlen($row->source_name->getValue()) > 100) {
                    $row->source_name = mb_substr($row->source_name->getValue(), 0, 100) . '...';
                }
            }
        }
        return $table->render();
    }


    /**
     * @param string $base_url
     * @return false|string
     * @throws \Zend_Db_Select_Exception
     * @throws \Exception
     */
    public function getTableInactive(string $base_url): string {

        $table = new Table\Db($this->resId . 'xxx_inactive');
        $table->setTable("mod_proxy");
        $table->setPrimaryKey('id');
        $table->showColumnManage();

        $select = $this->modProxy->dataProxy->getSelect()
            ->where("is_active_sw = 'N'")
            ->order("date_created DESC");
        $table->setData($select);

        $sw = ['Y' => 'Да', 'N' => 'Нет'];

        $table->addSearch($this->_("Адрес"),               'address');
        $table->addSearch($this->_("Порт"),                'port');
        $table->addSearch($this->_("SSL"),                 'is_ssl_sw', $table::SEARCH_SELECT)->setData($sw);
        $table->addSearch($this->_("Уровень анонимности"), 'level_anonymity', $table::SEARCH_CHECKBOX)->setData($this->modProxy->dataProxy->getLevelsAnonymity());
        $table->addSearch($this->_("Страна"),              'country');
        $table->addSearch($this->_("Источник"),            'source_name');
        $table->addSearch($this->_("Дата тестирования"),   'date_last_tested', $table::SEARCH_DATE);
        $table->addSearch($this->_("Дата использования"),  'date_last_used', $table::SEARCH_DATE);



        $table->addColumn($this->_("Адрес"),                'address',          $table::COLUMN_TEXT, 130);
        $table->addColumn($this->_('Порт'),                 'port',             $table::COLUMN_TEXT, 80);
        $table->addColumn($this->_("SSL"),                  'is_ssl_sw',        $table::COLUMN_HTML, 80);
        $table->addColumn($this->_("Рейтинг"),              'rating',           $table::COLUMN_HTML, 160);
        $table->addColumn($this->_("Подключение"),          'connect_time',     $table::COLUMN_HTML, 110);
        $table->addColumn($this->_("Пред передача"),        'pretransfer_time', $table::COLUMN_HTML, 115);
        $table->addColumn($this->_("Страна"),               'country',          $table::COLUMN_TEXT, 70);
        $table->addColumn($this->_("Источник"),             'source_name',      $table::COLUMN_TEXT);
        $table->addColumn($this->_("Дата тестирования"),    'date_last_tested', $table::COLUMN_DATETIME, 150);
        $table->addColumn($this->_("Дата использования"),   'date_last_used',   $table::COLUMN_DATETIME, 150);
        $table->addColumn($this->_(""),                     'is_active_sw',   $table::COLUMN_SWITCH, 1);


        $table->setAddUrl("{$base_url}&edit=0");
        $table->setEditUrl("{$base_url}&edit=TCOL_ID");
        $table->showDelete();

        $rows = $table->fetchRows();
        if ( ! empty($rows)) {
            foreach ($rows as $row) {

                $title = $this->modProxy->dataProxy->getLevelAnonymity((string)$row->level_anonymity);
                switch ($row->level_anonymity->getValue()) {
                    case 'elite':         $row->level_anonymity = '<span class="label label-success">' . $title . '</span>'; break;
                    case 'anonymous':     $row->level_anonymity = '<span class="label label-primary">' . $title . '</span>'; break;
                    case 'non_anonymous': $row->level_anonymity = '<span class="label label-danger">' . $title . '</span>'; break;
                }

                switch ($row->is_ssl_sw->getValue()) {
                    case 'Y': $row->is_ssl_sw = '<span class="label label-success">Да</span>'; break;
                    case 'N': $row->is_ssl_sw = '<span class="label label-warning">Нет</span>'; break;
                }

                if ($row->rating->getValue() <= -10) {
                    $rating_status = 'danger';

                } elseif ($row->rating->getValue() > -10 && $row->rating->getValue() < 0) {
                    $rating_status = 'warning';

                } elseif ($row->rating->getValue() == 0) {
                    $rating_status = 'info';

                } elseif ($row->rating->getValue() > 0 && $row->rating->getValue() < 5) {
                    $rating_status = 'primary';

                } elseif ($row->rating->getValue() >= 5){
                    $rating_status = 'success';
                }

                $row->rating = "<span class=\"label label-{$rating_status}\">" . $row->rating->getValue() . '</span>';

                if ($row->connect_time->getValue() > 0) {
                    $row->connect_time = $row->connect_time->getValue() . ' <small class="text-muted">сек</small>';
                }


                if ($row->pretransfer_time->getValue() > 0) {
                    $row->pretransfer_time = $row->pretransfer_time->getValue() . ' <small class="text-muted">сек</small>';
                }

                if (mb_strlen($row->source_name->getValue()) > 100) {
                    $row->source_name = mb_substr($row->source_name->getValue(), 0, 100) . '...';
                }
            }
        }
        return $table->render();
    }


    /**
     * @param string                           $base_url
     * @param \Zend_Db_Table_Row_Abstract|null $proxy
     * @return string
     * @throws \Zend_Db_Adapter_Exception
     * @throws \Zend_Exception
     */
    public function getEdit(string $base_url, \Zend_Db_Table_Row_Abstract $proxy = null): string {

        $edit = new \editTable($this->resId);
        $edit->table = 'mod_proxy';

        $edit->SQL = [
            [
                'id'              => $proxy->id ?? null,
                'address'         => $proxy->address ?? null,
                'port'            => $proxy->port ?? null,
                'is_ssl_sw'       => $proxy->is_ssl_sw ?? null,
                'level_anonymity' => $proxy->level_anonymity ?? null,
                'server_login'    => $proxy->server_login ?? null,
                'server_password' => $proxy->server_password ?? null,
                'country'         => $proxy->country ?? null,
                'is_active_sw'    => $proxy->is_active_sw ?? null,
            ],
        ];


        $ssl = [
            ''  => '-',
            'Y' => 'Да',
            'N' => 'Нет',
        ];
        $levels = ['' => '-'] + $this->modProxy->dataProxy->getLevelsAnonymity();

        $edit->addControl('Адрес',               "TEXT", 'style="width:200px;"', '', '', true);
        $edit->addControl('Порт',                "TEXT", 'style="width:200px;"', '', '', true);
        $edit->addControl('SSL',                 "LIST", 'style="width:200px;"', '', '', true); $edit->selectSQL[] = $ssl;
        $edit->addControl('Уровень анонимности', "LIST", 'style="width:200px;"', '', '', true); $edit->selectSQL[] = $levels;
        $edit->addControl('Логин',               "TEXT", 'style="width:200px;"');
        $edit->addControl('Пароль',              "TEXT", 'style="width:200px;"');
        $edit->addControl('Страна',              "TEXT", 'style="width:200px;"');


        $edit->addButtonSwitch('is_active_sw', isset($proxy->is_active_sw) ? $proxy->is_active_sw == 'Y' : true);

        $edit->firstColWidth     = "200px";
        $edit->back              = $base_url;
        $edit->save("xajax_saveProxy(xajax.getFormValues(this.id))");

        return $edit->render();
    }
}
