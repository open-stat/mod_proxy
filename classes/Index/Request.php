<?php
use Core2\Mod\Proxy;


/**
 * @property \ModProxyController $modProxy
 */
class Request extends Common {

    /**
     * @var string
     */
    private $user_agents = '';

    /**
     * @var string
     */
    private $method = '';

    /**
     * @var string[]
     */
    private $addresses = [];

    /**
     * @var array
     */
    private $options = [];

    /**
     * @var array|Zend_Db_Table_Rowset_Abstract
     */
    private $proxy_rows = [];

    /**
     * @var int
     */
    private static $proxy_index = 0;

    /**
     * @var int
     */
    private $max_try = 10;

    /**
     * @var array
     */
    private $debug = [];


    /**
     * @param string $method
     * @param array  $options
     * @throws Exception
     */
    public function __construct(string $method, array $options = []) {

        parent::__construct();

        $this->user_agents = $this->modProxy->getUserAgents();

        $this->method  = $method;
        $this->options = $options;

        if ( ! empty($this->options['max_try']) && $this->options['max_try'] > 0) {
            $this->max_try = $this->options['max_try'];
        }

        if (empty($this->options['request'])) {
            $this->options['request'] = [];
        }
    }


    /**
     * @param array $addresses
     * @return void
     * @throws Exception
     */
    public function setAddresses(array $addresses) {

        foreach ($addresses as $address) {
            if (filter_var($address, FILTER_VALIDATE_URL)) {
                $parse_addresses = parse_url($address);

                $scheme = $parse_addresses['scheme'] ?? 'http';
                $host   = $parse_addresses['host'] ?? '';
                $path   = $parse_addresses['path'] ?? '/';
                $query  = ! empty($parse_addresses['query']) ? "?{$parse_addresses['query']}" : '';

                if (empty($host)) {
                    continue;
                }

                $base_uri = "{$scheme}://{$host}";

                if (empty($this->addresses[$base_uri])) {
                    $this->addresses[$base_uri] = [];
                }

                if (in_array("{$path}{$query}", $this->addresses[$base_uri])) {
                    continue;
                }

                $this->addresses[$base_uri][] = "{$path}{$query}";
            }
        }


        if (empty($this->addresses)) {
            throw new \Exception('Указанные адреса некорректны');
        }
    }


    /**
     * @return array
     * @throws Exception
     */
    public function getResponses(): array {

        if (empty($this->addresses)) {
            throw new \Exception('Не заданы адреса для запросов');
        }

        $limit           = $this->options['limit'] ?? 100;
        $level_anonymity = $this->options['level_anonymity'] ?? ['elite', 'anonymous'];

        $select = $this->modProxy->dataProxy->select()
            ->where("is_active_sw = 'Y'")
            ->where("level_anonymity IN (?)", $level_anonymity)
            ->order("rating DESC")
            ->order(new \Zend_Db_Expr("level_anonymity = 'elite' DESC"))
            ->order(new \Zend_Db_Expr("level_anonymity = 'anonymous' DESC"))
            ->order(new \Zend_Db_Expr("is_ssl_sw = 'Y' DESC"))
            ->limit($limit);

        $this->proxy_rows = $this->modProxy->dataProxy->fetchAll($select);

        if (empty($this->proxy_rows)) {
            throw new \Exception('Не найдены proxy сервера по заданному условию');
        }

        $user_agent = count($this->user_agents) > 0
            ? $this->user_agents[rand(0, count($this->user_agents) - 1)]
            : '';

        $headers = [
            'Accept-Language' => "ru-RU,ru;q=0.9",
            'Accept-Encoding' => "gzip, deflate",
            'Accept'          => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
            'User-Agent'      => $user_agent,
            'Connection'      => "keep-alive",
            'Cache-Control'   => "no-cache",
        ];

        if ( ! empty($this->options['request']['headers'])) {
            $headers = array_merge($headers, $this->options['request']['headers']);
        }


        $responses = [];

        foreach ($this->addresses as $base_uri => $addresses) {

            if ( ! is_array($addresses)) {
                continue;
            }

            $headers['Host'] = parse_url($base_uri, PHP_URL_HOST);

            $client = new \GuzzleHttp\Client([
                'base_uri'           => $base_uri,
                'timeout'            => $this->options['request']['timeout'] ?? 10,
                'connection_timeout' => $this->options['request']['connection_timeout'] ?? 10,
                'allow_redirects'    => $this->options['request']['allow_redirects'] ?? true,
                'headers'            => $headers,
            ]);


            $responses_base = $this->requestProcess($client, $base_uri, $addresses);

            if ( ! empty($responses_base)) {
                foreach ($responses_base as $address => $response) {

                    if ( ! empty($this->options['debug'])) {
                        $response['debug'] = $this->debug[$address] ?? null;

                        if (isset($this->debug[$address])) {
                            unset($this->debug[$address]);
                        }
                    }

                    $responses[] = ['url' => $address] + $response;
                }
            }
        }

        return $responses;
    }


    /**
     * @param \GuzzleHttp\Client $client
     * @param string             $base_uri
     * @param array              $addresses
     * @param int                $try
     * @return array
     */
    private function requestProcess(\GuzzleHttp\Client $client, string $base_uri, array $addresses, int $try = 1): array {

        $promises        = [];
        $addresses_proxy = [];

        foreach ($addresses as $address) {

            if ($try > 1 && $try == $this->max_try) {
                $max_rand  = count($this->proxy_rows) - 1 < 5 ? count($this->proxy_rows) - 1 : 5;
                $proxy_row = $this->proxy_rows[rand(0, $max_rand)];

            } else {
                if (count($this->proxy_rows) <= self::$proxy_index) {
                    self::$proxy_index = 0;
                }
                $proxy_row = $this->proxy_rows[self::$proxy_index];
            }


            $proxy_row->date_last_used = new \Zend_Db_Expr('NOW()');
            $proxy_row->save();

            $addresses_proxy[$address] = $proxy_row;

            if ( ! empty($this->options['debug'])) {
                $this->debug["{$base_uri}{$address}"]["{$proxy_row->address}:{$proxy_row->port}"] = "";
            }

            $promises[$address] = $client->requestAsync($this->method, $address, [
                'proxy' => $proxy_row->server_login
                    ? "http://{$proxy_row->server_login}:{$proxy_row->server_password}@{$proxy_row->address}:{$proxy_row->port}"
                    : "http://{$proxy_row->address}:{$proxy_row->port}",
            ]);

            self::$proxy_index++;
        }

        $responses         = [];
        $address_repeat    = [];
        $promise_responses = \GuzzleHttp\Promise\Utils::settle($promises)->wait();

        foreach ($promise_responses as $address => $response) {

            $rating = 0;

            switch ($response['state']) {
                case 'rejected':
                    $rating--;

                    if (isset($response['reason'])) {
                        if ($response['reason'] instanceof \GuzzleHttp\Exception\ConnectException) {
                            if ( ! empty($this->options['debug'])) {
                                $this->debug("{$base_uri}{$address}", $response['reason']->getHandlerContext()['error']);
                            }

                            if ($try < $this->max_try) {
                                $address_repeat[] = $address;

                            } else {
                                $handler = $response['reason']->getHandlerContext();

                                $responses["{$base_uri}{$address}"] = [
                                    'status'        => 'error',
                                    'error_code'    => $handler['errno'] ?? '',
                                    'error_message' => $handler['error'] ?? '',
                                ];
                            }

                        } elseif ($response['reason'] instanceof \GuzzleHttp\Exception\RequestException) {
                            if ($response['reason']->hasResponse()) {
                                if ( ! empty($this->options['debug'])) {
                                    $this->debug("{$base_uri}{$address}", $response['reason']->getResponse()->getStatusCode());
                                }

                                if ($try < $this->max_try) {
                                    $address_repeat[] = $address;

                                } else {
                                    $reason_response = $response['reason']->getResponse();

                                    $responses["{$base_uri}{$address}"] = [
                                        'status'    => 'error',
                                        'http_code' => $reason_response->getStatusCode(),
                                        'headers'   => $reason_response->getHeaders(),
                                        'content'   => $reason_response->getBody()->getContents(),
                                    ];
                                }

                            } else {
                                if ( ! empty($this->options['debug'])) {
                                    $this->debug("{$base_uri}{$address}", $response['reason']->getMessage());
                                }

                                if ($try < $this->max_try) {
                                    $address_repeat[] = $address;

                                } else {
                                    $responses["{$base_uri}{$address}"] = [
                                        'status'        => 'error',
                                        'error_code'    => $response['reason']->getCode(),
                                        'error_message' => $response['reason']->getMessage(),
                                    ];
                                }
                            }

                        } else {
                            if ( ! empty($this->options['debug'])) {
                                $this->debug("{$base_uri}{$address}", $response['reason']->getMessage());
                            }

                            if ($try < $this->max_try) {
                                $address_repeat[] = $address;

                            } else {
                                $responses["{$base_uri}{$address}"] = [
                                    'status'        => 'error',
                                    'error_code'    => $response['reason']->getCode(),
                                    'error_message' => $response['reason']->getMessage(),
                                ];
                            }
                        }

                    } else {
                        if ( ! empty($this->options['debug'])) {
                            $this->debug("{$base_uri}{$address}", 'no_reason');
                        }

                        if ($try < $this->max_try) {
                            $address_repeat[] = $address;

                        } else {
                            $responses["{$base_uri}{$address}"] = [
                                'status'        => 'error',
                                'error_code'    => 'no_reason',
                                'error_message' => '',
                            ];
                        }
                    }
                    break;

                case 'fulfilled':
                    if ($response['value'] instanceof \GuzzleHttp\Psr7\Response) {
                        if ( ! empty($this->options['debug'])) {
                            $this->debug("{$base_uri}{$address}", $response['value']->getStatusCode());
                        }

                        $rating++;

                        $responses["{$base_uri}{$address}"] = [
                            'status'    => 'success',
                            'http_code' => $response['value']->getStatusCode(),
                            'headers'   => $response['value']->getHeaders(),
                            'content'   => $response['value']->getBody()->getContents(),
                        ];

                    } else {
                        if ( ! empty($this->options['debug'])) {
                            $this->debug("{$base_uri}{$address}", 'fulfilled_no_value');
                        }

                        $rating--;

                        if ($try < $this->max_try) {
                            $address_repeat[] = $address;

                        } else {
                            $responses["{$base_uri}{$address}"] = [
                                'status'        => 'error',
                                'error_code'    => 'fulfilled_no_value',
                                'error_message' => '',
                            ];
                        }
                    }
                    break;

                default:
                    if ( ! empty($this->options['debug'])) {
                        $this->debug("{$base_uri}{$address}", 'state_unknown');
                    }

                    $rating--;

                    if ($try < $this->max_try) {
                        $address_repeat[] = $address;

                    } else {
                        $responses["{$base_uri}{$address}"] = [
                            'status'        => 'error',
                            'error_code'    => 'state_unknown',
                            'error_message' => '',
                        ];
                    }
            }


            if ($rating > 0) {
                if ($addresses_proxy[$address]->rating < 100) {
                    $addresses_proxy[$address]->rating = new Zend_Db_Expr('rating + 1');
                }

                $this->modProxy->dataProxyDomains->incSuccessByProxyIdDomainDate(
                    $addresses_proxy[$address]->id,
                    parse_url($base_uri, PHP_URL_HOST)
                );

            } elseif ($rating < 0) {
                if ($addresses_proxy[$address]->rating > -100) {
                    $addresses_proxy[$address]->rating = new Zend_Db_Expr('rating - 1');
                }

                $this->modProxy->dataProxyDomains->incFailedByProxyIdDomainDate(
                    $addresses_proxy[$address]->id,
                    parse_url($base_uri, PHP_URL_HOST)
                );
            }

            $addresses_proxy[$address]->save();
        }


        if ( ! empty($address_repeat) && $try < $this->max_try) {
            $responses_repeat = $this->requestProcess($client, $base_uri, $address_repeat, $try + 1);

            if ( ! empty($responses_repeat)) {
                foreach ($responses_repeat as $address => $response_repeat) {
                    $responses[$address] = $response_repeat;
                }
            }
        }

        return $responses;
    }


    /**
     * @param $address
     * @param $error
     * @return void
     */
    private function debug($address, $error) {

        $proxy = array_key_last($this->debug[$address]);
        $this->debug[$address][$proxy] = $error;

        if ( ! empty($this->options['debug']) && $this->options['debug'] == 'print') {
            echo "{$address} [{$proxy}] -> {$error}\n";
        }
    }
}