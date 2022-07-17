<?php


/**
 *
 */
class Proxy extends \Zend_Db_Table_Abstract {

	protected $_name = 'mod_proxy';

    /**
     * @var Zend_Db_Adapter_Abstract
     */
    private $db;

    private $level_annonimity = [
        'elite'         => 'Полный',
        'anonymous'     => 'Приемлемый',
        'non_anonymous' => 'Не анонимный',
    ];


    /**
     *
     */
    public function __construct() {
        parent::__construct();

        $this->db = (new \Core2\Db())->db;
    }


    /**
     * @param string $name
     * @return string
     */
    public function getLevelAnonymity(string $name):? string {

        return $this->level_annonimity[$name] ?? null;
    }


    /**
     * @return string[]
     */
    public function getLevelsAnonymity(): array {

        return $this->level_annonimity;
    }


    /**
     * @param int $id
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function getRowById(int $id):? Zend_Db_Table_Row_Abstract {

        $select = $this->select()->where("id = ?", $id);

        return $this->fetchRow($select);
    }


    /**
     * @param string $address
     * @param int    $port
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function getRowByAddressPort(string $address, int $port): ?Zend_Db_Table_Row_Abstract {

        $select = $this->select()
            ->where("address = ?", $address)
            ->where("port = ?", $port);

        return $this->fetchRow($select);
    }



    /**
     * @return Zend_Db_Select
     */
    public function getSelect(): Zend_Db_Select {

        return $this->db->select()
            ->from(['p' => $this->_name], [
                'id',
                'address',
                'port',
                'is_ssl_sw',
                'level_anonymity',
                'country',
                'server_login',
                'server_password',
                'source_name',
                'connect_time',
                'pretransfer_time',
                'rating',
                'is_active_sw',
                'date_last_tested',
                'date_last_used',
                'date_created',
                'date_last_update',
            ]);
    }
}