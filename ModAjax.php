<?php
use Core2\Mod\Proxy;

require_once DOC_ROOT . "core2/inc/ajax.func.php";


/**
 * Class ModAjax
 */
class ModAjax extends ajaxFunc {


    /**
     * @param array $data
     * @return xajaxResponse
     * @throws Zend_Db_Adapter_Exception
     * @throws Exception
     */
    public function axSaveProxy(array $data): xajaxResponse {

        $fields = [
            'address'         => 'req',
            'port'            => 'req',
            'is_ssl_sw'       => 'req',
            'level_anonymity' => 'req',
        ];

        if ($this->ajaxValidate($data, $fields)) {
            return $this->response;
        }



        $this->saveData($data);

        if (empty($this->error)) {
            $this->response->script("CoreUI.notice.create('Сохранено');");
        }


        $this->done($data);
        return $this->response;
    }
}
