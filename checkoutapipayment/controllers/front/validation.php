<?php

class CheckoutapipaymentValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active)
            Tools::redirect('index.php?controller=order&step=1');

        // Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
        $authorized = false;

        foreach (Module::getPaymentModules() as $module)
            if ($module['name'] == 'checkoutapipayment')
            {
                $authorized = true;
                break;
            }

        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }

        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer))
            Tools::redirect('index.php?controller=order&step=1');

        $this->_placeorder();
    }

    public function _placeorder()
    { 
        $cart = $this->context->cart;
        $total = $cart->getOrderTotal(true, Cart::BOTH);
        $currency = $this->context->currency;
        $customer = new Customer((int)$cart->id_customer);
        //building charge
        $respondCharge = $this->_createCharge();
        $Api = CheckoutApi_Api::getApi(array('mode' => Configuration::get('CHECKOUTAPI_TEST_MODE')));
        $amountCents = $Api->valueToDecimal($total,$currency->iso_code);

        $toValidate = array(
            'currency' => $currency->iso_code,
            'value' => $amountCents,
        );

        $validateRequest = $Api::validateRequest($toValidate,$respondCharge);

        if( $respondCharge->isValid()) {
            if (preg_match('/^1[0-9]+$/', $respondCharge->getResponseCode())) {
              if( $respondCharge->getChargeMode() != 2) {
              $message = 'Your payment was sucessfull with Checkout.com with transaction Id '.$respondCharge->getId();

              if(!$validateRequest['status']){
                  foreach($validateRequest['message'] as $errormessage){
                    $message .= $errormessage . '. ';
                  }
              }

                $order_state =( Configuration::get('CHECKOUTAPI_PAYMENT_ACTION') == 'authorize_capture' &&
                $respondCharge->getCaptured())
                    ? Configuration::get('PS_OS_PAYMENT'):Configuration::get('PS_OS_CHECKOUT');

                $this->module->validateOrder((int)$cart->id, $order_state,
                $total, $this->module->displayName, $message, array
                ('transaction_id'=>$respondCharge->getId()),(int)$currency->id, false, $customer->secure_key);

                $config['authorization'] = Configuration::get('CHECKOUTAPI_SECRET_KEY');
                $config['mode'] = Configuration::get('CHECKOUTAPI_TEST_MODE');
                $Api = CheckoutApi_Api::getApi($config);
                $Api->updateTrackId($respondCharge, $this->module->currentOrder);

              } else {
                  $redirectUrl = $respondCharge->getRedirectUrl();
                  if (!empty($redirectUrl)){
                      Tools::redirectLink($redirectUrl);
                  }else {
                    //$dbLog = models_FactoryInstance::getInstance( 'models_DataLayer' );
                    //$dbLog->logCharge($this->module->currentOrder,'',$respondCharge);
                  }
              }
            } else {

                $this->module->validateOrder((int)$cart->id, Configuration::get('PS_OS_ERROR'),
                    $total, $this->module->displayName, 'An error has occcur while processing this transaction ('.$respondCharge->getResponseMessage().')',
                    array ('transaction_id'=>$respondCharge->getId()), (int)$currency->id, false, $customer->secure_key);
                
            }
            // $dbLog = models_FactoryInstance::getInstance( 'models_DataLayer' );
            // $dbLog->logCharge($this->module->currentOrder,$respondCharge->getId(),$respondCharge);

        } else  {
            $this->module->validateOrder((int)$cart->id, Configuration::get('PS_OS_ERROR'),
                $total, $this->module->displayName, $respondCharge->getExceptionState()->getErrorMessage(), NULL, (int)$currency->id,
                false, $customer->secure_key);

            $dbLog = models_FactoryInstance::getInstance( 'models_DataLayer' );
        }

        Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?key='.$customer->secure_key.'&id_cart='
            .(int)$this->context->cart->id.'&id_module='.(int)$this->module->id.'&id_order='
            .(int)$this->module->currentOrder);

    }

    private function _createCharge()
    {
        $config = array();
        $cart = $this->context->cart;
        $currency = $this->context->currency;
        $customer = new Customer((int)$cart->id_customer);
        $billingAddress = new Address((int)$cart->id_address_invoice);
        $shippingAddress = new Address((int)$cart->id_address_delivery);
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $scretKey =  Configuration::get('CHECKOUTAPI_SECRET_KEY');
        $orderId =(int)$cart->id;
        $Api = CheckoutApi_Api::getApi(array('mode' => Configuration::get('CHECKOUTAPI_TEST_MODE'),'authorization' => $scretKey));
        $amountCents = $Api->valueToDecimal($total, $currency->iso_code);
        $config['authorization'] = $scretKey;
        $config['mode'] = Configuration::get('CHECKOUTAPI_TEST_MODE');
        $config['timeout'] =  Configuration::get('CHECKOUTAPI_GATEWAY_TIMEOUT');
        $chargeModeValue = 1;
        $billPhoneLength = strlen($billingAddress->phone);

        $billingAddressConfig = array(
            'addressLine1' => $billingAddress->address1,
            'addressLine2' => $billingAddress->address2,
            'postcode' => $billingAddress->postcode,
            'country' => checkoutapipayment::getIsoCodeById($billingAddress->id_country),
            'city' => $billingAddress->city,
        );

        if ($billPhoneLength > 6) {
          $bilPhoneArray = array(
              'phone' => array('number' => $billingAddress->phone)
          );
          $billingAddressConfig = array_merge_recursive($billingAddressConfig, $bilPhoneArray);
        }

        $shipPhoneLength = strlen($shippingAddress->phone);
        $shippingAddressConfig = array(
            'addressLine1' => $shippingAddress->address1,
            'addressLine2' => $shippingAddress->address2,
            'postcode' => $shippingAddress->postcode,
            'country' => checkoutapipayment::getIsoCodeById($shippingAddress->id_country),
            'city' => $shippingAddress->city,
        );

        if ($shipPhoneLength > 6) {
          $shipPhoneArray = array(
              'phone' => array('number' => $shippingAddress->phone)
          );
          $shippingAddressConfig = array_merge_recursive($shippingAddressConfig, $shipPhoneArray);
        }

        $products = array();
        foreach ($cart->getProducts() as $item ) {
            $products[] = array (
                'name'          => strip_tags($item['name']),
                'sku'           => strip_tags($item['reference']),
                'price'         => $item['price'],
                'quantity'      => $item['cart_quantity']
            );
        }

        if(Configuration::get('CHECKOUTAPI_IS_3D')) {
            $chargeModeValue = 2;
        }

        $config['postedParam'] = array (
            'email'             => $customer->email ,
            'value'             => $amountCents,
            'currency'          => $currency->iso_code,
            'trackId'           => $orderId,
            'description'       => "Cart Id::$orderId",
            'shippingDetails'   => $shippingAddressConfig,
            'products'          => $products,
            'customerIp'        => $_SERVER['REMOTE_ADDR'],
            'chargeMode'        => $chargeModeValue,
            'card'              => array(
                                    'billingDetails'    => $billingAddressConfig
                                    ),
            'metadata' => array(
                'server'            => _PS_BASE_URL_.__PS_BASE_URI__,
                'order_id'          => $orderId,
                'ps_version'        => _PS_VERSION_,
                'plugin_version'    => $this->module->version,
                'lib_version'       => CheckoutApi_Client_Constant::LIB_VERSION,
                'integration_type'  => Configuration::get('CHECKOUTAPI_INTEGRATION_TYPE'),
                'time'              => date('Y-m-d H:i:s')
            )
        );

       return $this->module->getInstanceMethod()->createCharge($config,$cart);
    }

    private function _captureConfig()
    {
        $to_return = array (
            'autoCapture' => CheckoutApi_Client_Constant::AUTOCAPUTURE_CAPTURE,
            'autoCapTime' => Configuration::get('CHECKOUTAPI_AUTOCAPTURE_DELAY')
        );

        return $to_return;
    }

    private function _authorizeConfig()
    {
        $to_return = array (
            'autoCapture' => CheckoutApi_Client_Constant::AUTOCAPUTURE_AUTH,
            'autoCapTime' => 0
        );

        return $to_return;
    }
}