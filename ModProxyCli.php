<?php
use Core2\Mod\Proxy;

require_once DOC_ROOT . 'core2/inc/classes/Common.php';

require_once "classes/Index/Model.php";


/**
 * @property \ModProxyController $modProxy
 */
class ModProxyCli extends Common {

    /**
     * Получение proxy адресов
     * @throws Exception
     */
    public function getProxy() {

        $config = $this->getModuleConfig('proxy');

        if ($config->source) {
            $model = new Proxy\Index\Model();

            foreach ($config->source as $method => $links) {

                if ( ! empty($links)) {
                    foreach ($links as $link) {

                        $proxy_list = [];

                        switch ($method) {
                            case 'simpleList': $proxy_list = $model->parseSimpleList($link); break;
                            case 'pubProxy':   $proxy_list = $model->parsePubProxy($link); break;
                            case 'spys':       $proxy_list = $model->parseSpys($link); break;
                            case 'hideMeName': $proxy_list = $model->parseHideMeName($link); break;
                        }

                        if ( ! empty($proxy_list)) {
                            foreach ($proxy_list as $proxy) {
                                if ( ! empty($proxy['address']) && ! empty($proxy['port'])) {
                                    $proxy_row = $this->modProxy->dataProxy->getRowByAddressPort($proxy['address'], (int)$proxy['port']);

                                    if ($proxy_row) {
                                        if ( ! empty($proxy['is_ssl_sw'])) {       $proxy_row->is_ssl_sw       = $proxy['is_ssl_sw']; }
                                        if ( ! empty($proxy['level_anonymity'])) { $proxy_row->level_anonymity = $proxy['level_anonymity']; }
                                        if ( ! empty($proxy['country'])) {         $proxy_row->country         = $proxy['country']; }
                                        if ( ! empty($proxy['server_login'])) {    $proxy_row->server_login    = $proxy['server_login']; }
                                        if ( ! empty($proxy['server_password'])) { $proxy_row->server_password = $proxy['server_password']; }

                                        $proxy_row->source_name  = $link;

                                    } else {
                                        $data = [
                                            'address'     => $proxy['address'],
                                            'port'        => $proxy['port'],
                                            'source_name' => $link,
                                        ];

                                        if ( ! empty($proxy['is_ssl_sw'])) {       $data['is_ssl_sw']       = $proxy['is_ssl_sw']; }
                                        if ( ! empty($proxy['level_anonymity'])) { $data['level_anonymity'] = $proxy['level_anonymity']; }
                                        if ( ! empty($proxy['country'])) {         $data['country']         = $proxy['country']; }
                                        if ( ! empty($proxy['server_login'])) {    $data['server_login']    = $proxy['server_login']; }
                                        if ( ! empty($proxy['server_password'])) { $data['server_password'] = $proxy['server_password']; }

                                        $proxy_row = $this->modProxy->dataProxy->createRow($data);
                                    }

                                    $proxy_row->save();
                                }
                            }
                        }
                    }
                }
            }
        }
    }


    /**
     * Тестирование новых и активных proxy адресов
     * @throws Zend_Config_Exception
     */
    public function testProxyActive() {

        $select = $this->modProxy->dataProxy->select()
            ->orWhere("is_active_sw = 'N' AND date_last_tested IS NULL")
            ->orWhere("is_active_sw = 'Y'")
            ->order(new Zend_Db_Expr('date_last_tested IS NULL DESC'))
            ->order("date_last_used DESC")
            ->limit(2000);

        $model = new Proxy\Index\Model();
        $model->testProxy($select);
    }


    /**
     * Тестирование выключенных прокси
     * @throws Zend_Config_Exception
     */
    public function testProxyOld() {

        $select = $this->modProxy->dataProxy->select()
            ->where("is_active_sw = 'N'")
            ->order("date_last_used DESC")
            ->limit(2000);

        $model = new Proxy\Index\Model();
        $model->testProxy($select);
    }


    /**
     * Тестирование анонимности
     * @return void
     * @throws Zend_Config_Exception|\GuzzleHttp\Exception\GuzzleException
     */
    public function testProxyAnonymity() {

        $config = $this->getModuleConfig('proxy');

        if ( ! $config->test ||
             ! $config->test->anonymity ||
             ! $config->test->anonymity->base_url ||
             ! $config->test->anonymity->uri
        ) {
            return;
        }

        $select = $this->modProxy->dataProxy->select()
            ->where("is_active_sw = 'Y'")
            ->order(new Zend_Db_Expr("level_anonymity = 'elite' DESC"))
            ->order(new Zend_Db_Expr("level_anonymity = 'anonymous' DESC"))
            ->order("rating DESC")
            ->limit(2000);

        $proxies = $this->modProxy->dataProxy->fetchAll($select);
        $client  = new \GuzzleHttp\Client([
            'base_uri'           => $config->test->anonymity->base_url,
            'timeout'            => 10,
            'connection_timeout' => 10,
        ]);


        foreach ($proxies as $proxy) {

            try {
                $response = $client->request('get', $config->test->anonymity->uri, [
                    'proxy' => "http://{$proxy->address}:{$proxy->port}",
                ]);

                $response_data = @json_decode($response->getBody()->getContents(), true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }

                if (empty($response_data['server'])) {
                    continue;
                }


                if (isset($response_data['server']['HTTP_X_FORWARDED_FOR'])) {
                    $proxy->level_annonymity = 'non_anonymous';
                    $proxy->date_last_tested = new \Zend_Db_Expr('NOW()');

                    if ($proxy->rating < 100) {
                        $proxy->rating         = new \Zend_Db_Expr('rating +1');
                    }

                    $proxy->save();

                } elseif (isset($response_data['server']['HTTP_VIA']) ||
                          isset($response_data['server']['HTTP_X_PROXY_ID'])
                ) {
                    $proxy->level_annonymity = 'anonymous';
                    $proxy->date_last_tested = new \Zend_Db_Expr('NOW()');

                    if ($proxy->rating < 100) {
                        $proxy->rating = new \Zend_Db_Expr('rating +1');
                    }

                    $proxy->save();

                } elseif ($response_data['server']['REMOTE_ADDR'] == $proxy->address) {
                    $proxy->level_annonymity = 'elite';
                    $proxy->date_last_tested = new \Zend_Db_Expr('NOW()');

                    if ($proxy->rating < 100) {
                        $proxy->rating = new \Zend_Db_Expr('rating +1');
                    }

                    $proxy->save();
                }


            } catch (\Exception $e) {
                // ignore
            }
        }
    }
}
