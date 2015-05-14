<?php
if (!defined('_PS_VERSION_'))
    exit;
require_once 'autoload.php';
//require_once 'models/InterfacePayment.php';
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

        if ($params['objOrder']->getCurrentState() != Configuration::get('PS_OS_ERROR'))
            $this->context->smarty->assign(array('status' => 'ok', 'id_order' => intval($params['objOrder']->id)));
        else
            $this->context->smarty->assign('status', 'failed');

        return $this->display(__FILE__, 'views/templates/frontend/hookconfirmation/orderconfirmation.tpl');
    }
}