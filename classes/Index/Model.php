<?php
namespace Core2\Mod\Proxy\Index;

/**
 * @property \ModProxyController $modProxy
 */
class Model extends \Common {


    /**
     * @param string $url
     * @return array
     */
    public function parseSimpleList(string $url): array {

        $text       = file_get_contents($url);
        $proxy_list = [];

        if ( ! empty($text)) {
            $lines = explode("\n", $text);

            if ( ! empty($lines)) {
                foreach ($lines as $line) {
                    $line_explode = explode(':', $line);

                    if ( ! empty($line_explode) &&
                         ! empty($line_explode[0]) &&
                         ! empty($line_explode[1]) &&
                        filter_var($line_explode[0], FILTER_VALIDATE_IP) &&
                        is_numeric($line_explode[1])
                    ) {
                        $proxy_list[] = [
                            'address' => $line_explode[0],
                            'port'    => $line_explode[1],
                        ];
                    }
                }
            }
        }

        return $proxy_list;
    }


    /**
     * @param string $url
     * @return array
     */
    public function parsePubProxy(string $url): array {

        $text       = file_get_contents($url);
        $proxy_list = [];

        if ( ! empty($text)) {
            $data_decode = json_decode($text, true);

            if ( ! empty($data_decode['data'])) {
                foreach ($data_decode['data'] as $server) {

                    if ( ! empty($server) &&
                        ! empty($server['ip']) &&
                        ! empty($server['port']) &&
                        filter_var($server['ip'], FILTER_VALIDATE_IP) &&
                        is_numeric($server['port'])
                    ) {
                        switch ($server['proxy_level'] ?? '') {
                            case 'elite':     $level_anonymity = 'elite'; break;
                            case 'anonymous': $level_anonymity = 'anonymous'; break;
                            default:          $level_anonymity = 'non_anonymous';
                        }

                        if (isset($server['support']) &&
                            isset($server['support']['https']) &&
                            $server['support']['https'] == 1
                        ) {
                            $is_ssl_sw = 'Y';
                        } else {
                            $is_ssl_sw = 'N';
                        }

                        $proxy_list[] = [
                            'address'         => $server['ip'],
                            'port'            => $server['port'],
                            'country'         => $server['country'] ?? '',
                            'level_anonymity' => $level_anonymity,
                            'is_ssl_sw'       => $is_ssl_sw,
                        ];
                    }
                }
            }
        }

        return $proxy_list;
    }


    /**
     * @param string $url
     * @return array
     */
    public function parseSpys(string $url): array {

        $text       = file_get_contents($url);
        $proxy_list = [];

        if ( ! empty($text)) {
            $text_explode = explode("\n\n", $text);

            if ( ! empty($text_explode[1])) {
                $lines = explode("\n", $text_explode[1]);

                if ( ! empty($lines)) {
                    foreach ($lines as $line) {
                        $line_explode = explode(' ', $line);

                        if (empty($line_explode[0]) || empty($line_explode[1])) {
                            continue;
                        }

                        $address = explode(':', $line_explode[0]);

                        if (empty($address) ||
                            empty($address[0]) ||
                            empty($address[1]) ||
                            ! filter_var($address[0], FILTER_VALIDATE_IP) ||
                            ! is_numeric($address[1])
                        ) {
                            continue;
                        }

                        $proxy = explode('-', $line_explode[1]);


                        switch ($proxy[1] ?? '') {
                            case 'H': $level_anonymity = 'elite'; break;
                            case 'A': $level_anonymity = 'anonymous'; break;
                            case 'N':
                            default: $level_anonymity = 'non_anonymous';
                        }

                        switch ($proxy[2] ?? '') {
                            case 'S':
                            case 'S!': $is_ssl_sw = 'Y'; break;
                            case '':
                            default: $is_ssl_sw = 'N';
                        }

                        $proxy_list[] = [
                            'address'         => $address[0],
                            'port'            => $address[1],
                            'country'         => $proxy[0] ?? '',
                            'level_anonymity' => $level_anonymity,
                            'is_ssl_sw'       => $is_ssl_sw,
                        ];
                    }
                }
            }
        }

        return $proxy_list;
    }


    /**
     * @param string $url
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\ContentLengthException
     * @throws \PHPHtmlParser\Exceptions\LogicalException
     * @throws \PHPHtmlParser\Exceptions\NotLoadedException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     */
    public function parseHideMeName(string $url): array {

        $user_agents = $this->modProxy->getUserAgents();
        $user_agent  = count($user_agents) > 0
            ? $user_agents[rand(0, count($user_agents) - 1)]
            : '';



        $client = new \GuzzleHttp\Client([
            'timeout'            => 10,
            'connection_timeout' => 10,
            'allow_redirects'    => true,
            'headers'            => [
                'User-agent' => $user_agent
            ],
        ]);
        $response = $client->get($url);

        if ($response->getStatusCode() != 200) {
            return [];
        }

        $body_content = $response->getBody()->getContents();


        $dom = new \PHPHtmlParser\Dom();
        $dom->loadStr($body_content);
        $dom_proxy_rows = $dom->find('.table_block > table > tbody > tr');

        $proxy_list = [];

        if ( ! empty($dom_proxy_rows)) {
            foreach ($dom_proxy_rows as $dom_proxy_row) {
                try {
                    $dom_proxy_ip        = $dom_proxy_row->find('td', 0);
                    $dom_proxy_port      = $dom_proxy_row->find('td', 1);
                    $dom_proxy_country   = $dom_proxy_row->find('td', 2)->find('.country');
                    $dom_proxy_type      = $dom_proxy_row->find('td', 4);
                    $dom_proxy_anonymity = $dom_proxy_row->find('td', 5);


                    if ( ! $dom_proxy_type  || ! in_array($dom_proxy_type->text, ['HTTP', 'HTTPS'])) {
                        continue;
                    }

                    $proxy_address   = trim($dom_proxy_ip->text);
                    $proxy_port      = trim($dom_proxy_port->text);
                    $proxy_country   = trim($dom_proxy_country->text);
                    $is_ssl_sw       = $dom_proxy_type->text == 'HTTPS';

                    if ( ! filter_var($proxy_address, FILTER_VALIDATE_IP)) {
                        continue;
                    }

                    switch ($dom_proxy_anonymity->text) {
                        case 'Высокая':
                        case 'Средняя': $level_anonymity = 'elite'; break;
                        case 'Низкая': $level_anonymity = 'anonymous'; break;
                        default: $level_anonymity = 'non_anonymous';
                    }

                    $proxy_list[] = [
                        'address'         => $proxy_address,
                        'port'            => $proxy_port,
                        'country'         => $proxy_country,
                        'level_anonymity' => $level_anonymity,
                        'is_ssl_sw'       => $is_ssl_sw,
                    ];

                } catch (\Exception $e) {
                    // ignore
                }
            }
        }


        return $proxy_list;
    }


    /**
     * Проверка списка прокси серверов на работоспособность
     * @param \Zend_Db_Select $select
     * @return void
     * @throws \Zend_Config_Exception
     */
    public function testProxy(\Zend_Db_Select $select) {

        $config = $this->getModuleConfig('proxy');

        if ( ! $config->test || ! $config->test->addresses) {
            return;
        }

        $i           = 0;
        $count       = 15;
        $proxies     = $this->modProxy->dataProxy->fetchAll($select);
        $proxies_sets = [];

        foreach ($proxies as $proxy) {
            if (empty($proxies_sets[$i])) {
                $proxies_sets[$i] = [];
            }

            if (count($proxies_sets[$i]) >= $count) {
                $i++;
            }

            $proxies_sets[$i][] = $proxy;
        }


        foreach ($proxies_sets as $proxies_set) {
            $addresses = [];
            foreach ($proxies_set as $proxy) {

                $auth = false;
                if ($proxy->server_login && $proxy->server_password) {
                    $auth = "{$proxy->server_login}:{$proxy->server_password}";
                }

                $addresses["{$proxy->address}:{$proxy->port}"] = $auth;
            }

            $addresses_results = [];

            foreach ($config->test->addresses as $address) {
                $addresses_results[] = $this->multiRequest($addresses, [
                    CURLOPT_URL            => $address,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_TIMEOUT        => 15,
                ]);
            }

            $addresses_state = [];

            foreach ($addresses_results as $addresses_result) {
                foreach ($addresses_result as $address => $result) {

                    if (empty($addresses_state[$address])) {
                        $addresses_state[$address] = [
                            'count_total'      => 0,
                            'count_success'    => 0,
                            'connect_time'     => [],
                            'pretransfer_time' => [],
                        ];
                    }

                    $addresses_state[$address]['count_total']++;

                    if ($result['http_code'] > 0) {
                        $addresses_state[$address]['count_success']++;

                        $addresses_state[$address]['connect_time'][]     = $result['connect_time'];
                        $addresses_state[$address]['pretransfer_time'][] = $result['pretransfer_time'];
                    }
                }
            }


            foreach ($proxies_set as $proxy) {
                if (isset($addresses_state["{$proxy->address}:{$proxy->port}"]) &&
                    $addresses_state["{$proxy->address}:{$proxy->port}"]['count_total'] > 0
                ) {
                    $proxy->date_last_tested = new \Zend_Db_Expr('NOW()');
                    $proxy->is_active_sw     = 'Y';

                    $address_state = $addresses_state["{$proxy->address}:{$proxy->port}"];

                    if ($address_state['count_success'] > 0) {
                        $proxy->connect_time     = array_sum($address_state['connect_time']) / count($address_state['connect_time']);
                        $proxy->pretransfer_time = array_sum($address_state['pretransfer_time']) / count($address_state['pretransfer_time']);

                        if ($proxy->rating < 100) {
                            if ($proxy->rating + $address_state['count_success'] >= 100) {
                                $proxy->rating = 100;
                            } else {
                                $proxy->rating = new \Zend_Db_Expr('rating + ' . $address_state['count_success']);
                            }
                        }


                    } else {
                        if ($proxy->rating > -100) {
                            if ($proxy->rating - $address_state['count_total'] <= -100) {
                                $proxy->rating = -100;
                            } else {
                                $proxy->rating = new \Zend_Db_Expr('rating - ' . $address_state['count_total']);
                            }
                        }

                        $proxy->is_active_sw = 'N';
                    }

                    $proxy->save();
                }
            }
        }
    }


    /**
     * @param array $addresses
     * @param array $options
     * @return array
     */
    private function multiRequest(array $addresses, array $options = []): array {

        $curl_list = [];
        $proxies   = [];
        $mh        = curl_multi_init();

        foreach ($addresses as $address => $auth) {
            $curl = curl_init();
            $curl_list[$address] = $curl;

            curl_setopt($curl, CURLOPT_URL,             $options[CURLOPT_URL] ?? "https://google.com");
            curl_setopt($curl, CURLOPT_HEADER,          $options[CURLOPT_HEADER] ?? 1);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER,  $options[CURLOPT_RETURNTRANSFER] ?? 1);
            curl_setopt($curl, CURLOPT_CONNECTTIMEOUT,  $options[CURLOPT_CONNECTTIMEOUT] ?? 5);
            curl_setopt($curl, CURLOPT_TIMEOUT,         $options[CURLOPT_TIMEOUT] ?? 10);
            curl_setopt($curl, CURLOPT_PROXY,           $address);
            curl_setopt($curl, CURLOPT_PROXYTYPE,       $options[CURLOPT_PROXYTYPE] ?? 0);

            if ($auth) {
                curl_setopt($curl, CURLOPT_PROXYUSERPWD, $auth);
            }

            curl_multi_add_handle($mh, $curl);
        }

        do {
            $status = curl_multi_exec($mh, $active);

            if ($active) {
                curl_multi_select($mh);
            }

            while (false !== ($info_read = curl_multi_info_read($mh))) {
                $address           = array_search($info_read['handle'], $curl_list);
                $proxies[$address] = curl_getinfo($info_read['handle']);

                curl_multi_remove_handle($mh, $info_read['handle']);
            }

        } while ($active && $status == CURLM_OK);

        curl_multi_close($mh);

        return $proxies;
    }
}
