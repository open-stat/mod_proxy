<?php


/**
 *
 */
class ProxyDomains extends \Zend_Db_Table_Abstract {

	protected $_name = 'mod_proxy_domains';

    /**
     * @var Zend_Db_Adapter_Abstract
     */
    private $db;

    /**
     *
     */
    public function __construct() {
        parent::__construct();

        $this->db = (new \Core2\Db())->db;
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
     * @param int         $proxy_id
     * @param string      $domain
     * @param string|null $date
     * @return void
     */
    public function incSuccessByProxyIdDomainDate(int $proxy_id, string $domain, string $date = null) {

        $date_used = $date ?? date('Y-m-d');

        $select = $this->select()
            ->where("proxy_id = ?", $proxy_id)
            ->where("domain = ?", $domain)
            ->where("date_used = ?", $date_used);

        $row = $this->fetchRow($select);

        if ($row) {
            $row->count_success++;

        } else {
            $row = $this->createRow([
                'proxy_id'      => $proxy_id,
                'domain'        => $domain,
                'count_success' => 1,
                'count_failed'  => 0,
                'date_used'     => $date_used,
                'date_created'  => new Zend_Db_Expr('NOW()'),
            ]);
        }

        $row->save();
    }


    /**
     * @param int         $proxy_id
     * @param string      $domain
     * @param string|null $date
     * @return void
     */
    public function incFailedByProxyIdDomainDate(int $proxy_id, string $domain, string $date = null) {

        $date_used = $date ?? date('Y-m-d');

        $select = $this->select()
            ->where("proxy_id = ?", $proxy_id)
            ->where("domain = ?", $domain)
            ->where("date_used = ?", $date_used);

        $row = $this->fetchRow($select);

        if ($row) {
            $row->count_failed++;

        } else {
            $row = $this->createRow([
                'proxy_id'      => $proxy_id,
                'domain'        => $domain,
                'count_success' => 0,
                'count_failed'  => 1,
                'date_used'     => $date_used,
                'date_created'  => new Zend_Db_Expr('NOW()'),
            ]);
        }

        $row->save();
    }


    /**
     * @return Zend_Db_Select
     */
    public function getSelect(): Zend_Db_Select {

        return $this->db->select()
            ->from(['pd' => $this->_name], [
                'id',
                'proxy_id',
                'domain',
                'count_success',
                'count_failed',
                'date_used',
                'date_created',
                'date_last_update',
            ]);
    }
}