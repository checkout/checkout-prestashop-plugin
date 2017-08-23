<?php

if (!defined('_PS_VERSION_'))
    exit;

require_once (dirname(__FILE__). '/models/Checkoutapi.php');

class checkoutapipayment  extends models_Checkoutapi
{
    protected $_methodType;
    protected $_methodInstance;

    /**
     * @todo aim_available_currencies to be dynamic in admin
     * @throws Exception
     */

    public function __construct()
    {
        parent::__construct();
    }

    public  function _initCode()
    {
        $this->_code = $this->_methodInstance->getCode();
    }

    public function hookPayment($params)
    {
        $smartyParam = parent::hookPayment($params);
        $smartyParam['local_path'] = $this->local_path;
        $smartyParam['module_dir'] = $this->_path;

        $this->context->smarty->assign($smartyParam);
        
        return  $this->context->smarty->fetch($this->local_path.'views/templates/frontend/hookpayment/payment.tpl');
    }

    public function hookOrderConfirmation(array $params)
    { 
        if ($params['objOrder']->module != $this->name)
            return;

        if ($params['objOrder']->getCurrentState() != Configuration::get('PS_OS_ERROR')){
            $this->context->smarty->assign(array('status' => 'ok', 'id_order' => intval($params['objOrder']->id)));
        } else {
            $this->context->smarty->assign('status', 'failed');
        }

        return $this->display(__FILE__, 'views/templates/frontend/hookconfirmation/orderconfirmation.tpl');

    }

    public static function getIsoCodeById($code)
    {
        $sql = '
        SELECT `iso_code`
        FROM `'._DB_PREFIX_.'country`
        WHERE `id_country` = \''.pSQL($code).'\'';

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);

        return $result['iso_code'];
    }
}