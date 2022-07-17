<?php
use \Core2\Mod\Proxy;

require_once DOC_ROOT . "core2/inc/classes/Common.php";
require_once DOC_ROOT . "core2/inc/classes/Panel.php";

require_once 'classes/Index/View.php';
require_once 'classes/Index/Request.php';


/**
 * @property \Proxy        $dataProxy
 * @property \ProxyDomains $dataProxyDomains
 */
class ModProxyController extends Common {

    /**
     * @return string
     * @throws Exception
     */
    public function action_index(): string {

        $base_url = 'index.php?module=proxy';
        $panel    = new Panel('tab');
        $content  = [];

        $view = new Proxy\Index\View();


        if (isset($_GET['edit'])) {
            if ( ! empty($_GET['edit'])) {
                $proxy = $this->dataProxy->getRowById((int)$_GET['edit']);

                if (empty($proxy)) {
                    throw new Exception('Указанный Proxy не найден');
                }

                $panel->setTitle("Редактирование proxy", '', $base_url);

            } else {
                $panel->setTitle("Добавление proxy", '', $base_url);
            }

            $content[] = $view->getEdit($base_url, $proxy ?? null);

        } else {
            $panel->addTab('Активные',    'active',   $base_url);
            $panel->addTab('Не активные', 'inactive', $base_url);

            $base_url .= "&tab=" . $panel->getActiveTab();
            switch ($panel->getActiveTab()) {
                case 'active':   $content[] = $view->getTableActive($base_url); break;
                case 'inactive': $content[] = $view->getTableInactive($base_url); break;
            }
        }


        $panel->setContent(implode('', $content));
        return $panel->render();
    }


    /**
     * Список User агентов браузеров
     * @return array
     */
    public function getUserAgents(): array {

        $content = file_get_contents(__DIR__ . '/assets/user_agents.txt');
        $agents  = explode("\n", $content);

        return $agents ?: [];
    }


    /**
     * Запрос с использованием proxy серверов
     * @param string   $method
     * @param string[] $addresses
     * @param array    $options
     * @return void
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function request(string $method, array $addresses, array $options = []): array {

        $request = new Request($method, $options);
        $request->setAddresses($addresses);
        return $request->getResponses();
    }
}