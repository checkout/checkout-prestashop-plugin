<?php

abstract class models_Checkoutapi extends PaymentModule  implements models_InterfacePayment
{
    protected $_code;
    protected $_methodType;
    protected $_methodInstance;

    public function __construct()
    {


        $this->_setInstanceMethod();
        $this->_compatibilityUpgrade();
        $this->_init();

    }

    abstract public function _initCode();

    public function install()
    {
        $processor = models_FactoryInstance::getInstance( 'models_DataLayer' );
        $respond = $processor->installState();
        return parent::install() &&
        $this->registerHook('orderConfirmation') &&
        $this->registerHook('actionOrderStatusPostUpdate') &&
        $this->registerHook('actionOrderStatusUpdate') &&
        $this->registerHook('payment') &&
        $this->registerHook('header') &&
        $this->registerHook('backOfficeHeader') &&
        $this->registerHook('displayAdminOrderContentOrder') &&
        $this->registerHook('orderConfirmation') &&
        Configuration::updateValue('CHECKOUTAPI_TEST_MODE', 'test') &&
        Configuration::updateValue('CHECKOUTAPI_GATEWAY_TIMEOUT', 60) &&
        Configuration::updateValue('CHECKOUTAPI_AUTOCAPTURE_DELAY', 0) ;


    }

    /**
     * @todo deleting all config
     * @return mixed
     */
    public function uninstall()
    {
        Configuration::deleteByName('CHECKOUTAPI_METHODTYPE');
        Configuration::deleteByName('CHECKOUTAPI_TEST_MODE');
        Configuration::deleteByName('CHECKOUTAPI_PUBLIC_KEY');
        Configuration::deleteByName('CHECKOUTAPI_SECRET_KEY');
        Configuration::deleteByName('CHECKOUTAPI_ORDER_STATUS');
        Configuration::deleteByName('CHECKOUTAPI_AUTOCAPTURE_DELAY');
        Configuration::deleteByName('CHECKOUTAPI_GATEWAY_TIMEOUT');
        Configuration::deleteByName('CHECKOUTAPI_PCI_ENABLE');
        Configuration::deleteByName('CHECKOUTAPI_LOCALPAYMENT_ENABLE');
        Configuration::deleteByName('CHECKOUTAPI_PAYMENT_ACTION');
        Configuration::deleteByName('CHECKOUTAPI_HOLD_REVIEW_OS');
        $cards = helper_Card::getCardType($this);
        foreach($cards as $cardInfo) {

            Configuration::deleteByName($cardInfo['id']);
        }
        $processor = models_FactoryInstance::getInstance( 'models_DataLayer' );
        $respond = $processor->unInstallState();
        return parent::uninstall();
    }


    public function getContent()
    {
        $respond = '';
        if(Tools::isSubmit('submitPayment')) {
            $processor = models_FactoryInstance::getInstance( 'models_DataLayer' );
            $respond = $processor->saveAdminSetting($_POST);

        }
        // For "Hold for Review" order status
        $currencies = Currency::getCurrencies(false, true);
        $order_states = OrderState::getOrderStates((int)$this->context->cookie->id_lang);

        $this->context->smarty->assign(array(
            'available_currencies' => $this->aim_available_currencies,
            'currencies'                      =>    $currencies,
            'module_dir'                      =>    $this->_path,
            'order_states'                    =>    $order_states,
            'cardtype'                        =>    $this->getCardType(),
            'transactionType'                 =>    $this->getTransactionType(),
            'respond'                         =>     $respond,

            'CHECKOUTAPI_TEST_MODE'           =>    Tools::getValue('checkoutapi_test_mode',
                                                      Configuration::get('CHECKOUTAPI_TEST_MODE')),
            'CHECKOUTAPI_PUBLIC_KEY'          =>    Tools::getValue('checkoutapi_public_key',
                                                      Configuration::get('CHECKOUTAPI_PUBLIC_KEY')),
            'CHECKOUTAPI_SECRET_KEY'          =>    Tools::getValue('checkoutapi_secret_key',
                                                      Configuration::get('CHECKOUTAPI_SECRET_KEY')),
            'CHECKOUTAPI_ORDER_STATUS'        =>    Tools::getValue('checkoutapi_order_status',
                                                      Configuration::get('CHECKOUTAPI_ORDER_STATUS')),

            'CHECKOUTAPI_AUTOCAPTURE_DELAY'   =>    Tools::getValue('checkoutapi_autocapture_delay',
                                                      Configuration::get('CHECKOUTAPI_AUTOCAPTURE_DELAY')),

            'CHECKOUTAPI_GATEWAY_TIMEOUT'     =>    Tools::getValue('checkoutapi_gateway_timeout',
                                                     Configuration::get('CHECKOUTAPI_GATEWAY_TIMEOUT')),

            'CHECKOUTAPI_PCI_ENABLE'          =>    Tools::getValue('checkoutapi_pci_enable',
                                                     Configuration::get('CHECKOUTAPI_PCI_ENABLE')),

            'CHECKOUTAPI_LOCALPAYMENT_ENABLE'  =>    Tools::getValue('checkoutapi_localpayment_enable',
                                                      Configuration::get('CHECKOUTAPI_LOCALPAYMENT_ENABLE')),
            'CHECKOUTAPI_PAYMENT_ACTION'       =>    Tools::getValue('checkoutapi_payment_action',
                                                      Configuration::get('CHECKOUTAPI_PAYMENT_ACTION')),

            'CHECKOUTAPI_HOLD_REVIEW_OS'      =>    Tools::getValue('checkoutapi_hold_review_os',
                                                      Configuration::get('CHECKOUTAPI_HOLD_REVIEW_OS'))


        ));

        return $this->context->smarty->fetch($this->local_path.'views/templates/admin/configuration.tpl');
    }

    /**
     * @todo make this more centralize
     */
    public  function getTransactionType()
    {
        return array (
            array('value'=>'authorize','label'=>'Authorize only'),
            array('value'=>'authorize_capture','label'=>'Authorize and Capture'),
        );
    }
    public function getCardType()
    {
       return helper_Card::getCardType($this);
    }

    public function hookDisplayAdminOrderContentOrder($param)
    {
      //var_dump($param)  ;
    }

    public function hookActionOrderStatusPostUpdate($param)
    {
        unset($_POST['submitState']);
        $newState = $param['newOrderStatus'];
        $id_order = $param['id_order'];
        if (!is_object($id_order) && is_numeric($id_order))
            $order = new Order((int)$id_order);
        elseif (is_object($id_order))
            $order = $id_order;

        $old_os = $order->getCurrentOrderState();
        $scretKey =  Configuration::get('CHECKOUTAPI_SECRET_KEY');
        $charge = models_FactoryInstance::getInstance( 'models_DataLayer' )->getCharge($id_order);
        $amountCents = (int)$order->total_paid*100;
        $config['authorization'] = $scretKey  ;
        $config['mode'] = Configuration::get('CHECKOUTAPI_TEST_MODE');
        $config['chargeId'] = $charge['charge'] ;
        $config['timeout'] =  Configuration::get('CHECKOUTAPI_GATEWAY_TIMEOUT');

        $config['postedParam'] =array (
            'value'    =>  $amountCents
          );

        $Api = CheckoutApi_Api::getApi(array('mode'=> Configuration::get('CHECKOUTAPI_TEST_MODE')));

            if ($newState->id == Configuration::get('PS_OS_CANCELED') || $newState->id == Configuration::get('PS_OS_REFUND')) {

                $_refundCharge = $Api->refundCharge($config);

                if($_refundCharge->isValid() && $_refundCharge->getRefunded() &&
                    preg_match('/^1[0-9]+$/',$_refundCharge->getResponseCode()) ) {
                    $dbLog = models_FactoryInstance::getInstance( 'models_DataLayer' );
                    $dbLog->logCharge($id_order,$_refundCharge->getId(),$_refundCharge);

                }else {

                    $this->context->controller->errors[] = Tools::displayError('Invalid new order status');
                    $this->adminDisplayWarning('Invalid new order status');


                }
            }


    }
    public function hookOrderConfirmation(array $params)
    {

    }

    public function hookBackOfficeHeader()
    {

        $this->context->controller->addCSS($this->_path . 'skin/css/checkoutapi.css');
        $this->context->controller->addJquery();
        $this->context->controller->addJs($this->_path . 'skin/js/jquery-v1.admin.js');
    }
    public function hookPayment($params)
    {

        return $this->_methodInstance->hookPayment($params);;
    }

    public function hookHeader()
    {
        $this->context->controller->addCSS($this->_path.'skin/css/payment.css');
        $this->context->controller->addJquery();
        $this->context->controller->addJs($this->_path.'skin/js/jquery-v1.front.js');
        $this->context->controller->addJs($this->_path.'skin/js/functions.js');

    }
    public function getCode()
    {
        return $this->_code;
    }

    protected   function _setInstanceMethod()
    {
        $configType = Configuration::get('CHECKOUTAPI_PCI_ENABLE')?'pci':'nopci';

        if($configType ) {
            switch ($configType) {
                case 'pci':
                    $this->_methodType = 'models_methods_creditcardpci';
                    break;
                default:
                    $this->_methodType = 'models_methods_creditcard';
                    break;
            }

        } else {
            throw new Exception('Invalid method type');
            Logger::addLog('Invalid method type ', 4);
            exit;
        }

        if(!$this->_methodInstance) {
            $this->_methodInstance =  models_FactoryInstance::getInstance( $this->_methodType );
        }

        return  $this->_methodInstance;
    }

    public function getInstanceMethod()
    {
        return  $this->_methodInstance;
    }
    protected  function _compatibilityUpgrade()
    {
        /* For 1.4.3 and less compatibility */
        $sql = 'SELECT * FROM '._DB_PREFIX_."order_state WHERE module_name = 'checkoutapipayment'";
        $row = Db::getInstance()->getRow($sql);

        $updateConfig = array(
            'PS_OS_CHEQUE'          => 1,
            'PS_OS_PAYMENT'         => 2,
            'PS_OS_PREPARATION'     => 3,
            'PS_OS_SHIPPING'        => 4,
            'PS_OS_DELIVERED'       => 5,
            'PS_OS_CANCELED'        => 6,
            'PS_OS_REFUND'          => 7,
            'PS_OS_ERROR'           => 8,
            'PS_OS_OUTOFSTOCK'      => 9,
            'PS_OS_BANKWIRE'        => 10,
            'PS_OS_PAYPAL'          => 11,
            'PS_OS_WS_PAYMENT'      => 12,
            'PS_OS_CHECKOUT'        => (int) $row['id_order_state'],
        );

        foreach ($updateConfig as $u => $v)
            if (!Configuration::get($u) || (int)Configuration::get($u) < 1)
            {
                if (defined('_'.$u.'_') && (int)constant('_'.$u.'_') > 0)
                    Configuration::updateValue($u, constant('_'.$u.'_'));
                else
                    Configuration::updateValue($u, $v);
            }

    }


    /**
     * @todo aim_available_currencies to be dynamic in admin
     */
    private function _init()
    {
        $this->name = 'checkoutapipayment';
        $this->tab = 'payments_gateways';
        $this->version = $this->_methodInstance->version;
        $this->author = $this->_methodInstance->author;
        parent::__construct();
        $this->displayName = $this->_methodInstance->displayName;
        $this->description = $this->l($this->_methodInstance->description);
        $this->aim_available_currencies = array('USD','AUD','CAD','EUR','GBP','NZD');

    }


}