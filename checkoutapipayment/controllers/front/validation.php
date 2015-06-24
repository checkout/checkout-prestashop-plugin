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

//        Tools::redirect('index.php?controller=order-confirmation&id_cart='
//            .(int)$cart->id.'&id_module='.(int)$this->module->id.'&id_order='
//            .$this->module->currentOrder.'&key='.$customer->secure_key);
    }

    public function _placeorder()
    {
        $cart = $this->context->cart;
        $total = (float)$cart->getOrderTotal(true, Cart::BOTH);
        $currency = $this->context->currency;
        $customer = new Customer((int)$cart->id_customer);
        //building charge
        $respondCharge = $this->_createCharge();


        if( $respondCharge->isValid()) {

            if (preg_match('/^1[0-9]+$/', $respondCharge->getResponseCode())) {

                $order_state =( Configuration::get('CHECKOUTAPI_PAYMENT_ACTION') == 'authorize_capture' &&
                $respondCharge->getCaptured())
                    ? Configuration::get('PS_OS_PAYMENT'):Configuration::get('PS_OS_CHECKOUT');
                $message = 'Your payment was sucessfull with Checkout.com with transaction Id '.$respondCharge->getId();
                $this->module->validateOrder((int)$cart->id, $order_state,
                    $total, $this->module->displayName, $message, array
                    ('transaction_id'=>$respondCharge->getId()),
                    (int)
                    $currency->id,
                    false, $customer->secure_key);
                      $config['authorization']    =    Configuration::get('CHECKOUTAPI_SECRET_KEY');
                      $config['mode']    =    Configuration::get('CHECKOUTAPI_TEST_MODE');
                       $Api = CheckoutApi_Api::getApi($config);
                       $Api->updateTrackId($respondCharge, $this->module->currentOrder);
            } else {

                $this->module->validateOrder((int)$cart->id, Configuration::get('PS_OS_ERROR'),
                    $total, $this->module->displayName, 'An error has occcur while processing this transaction ('.$respondCharge->getResponseLongMessage().')',
                    array
                    ('transaction_id'=>$respondCharge->getId()), (int)$currency->id,
                    false, $customer->secure_key);

            }

            $dbLog = models_FactoryInstance::getInstance( 'models_DataLayer' );
            $dbLog->logCharge($this->module->currentOrder,$respondCharge->getId(),$respondCharge);



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
        $country = checkoutapipayment::getIsoCodeById($shippingAddress->id_country);
        


        $scretKey =  Configuration::get('CHECKOUTAPI_SECRET_KEY');

        $orderId =(int)$cart->id;
        $amountCents = $total*100;
        $config['authorization'] = $scretKey  ;

        $config['mode'] = Configuration::get('CHECKOUTAPI_TEST_MODE');
        $config['timeout'] =  Configuration::get('CHECKOUTAPI_GATEWAY_TIMEOUT');

        $billingAddressConfig = array(
            'addressLine1'    =>  $billingAddress->address1,
            'addressLine2'    =>  $billingAddress->address2,
            'postcode'        =>  $billingAddress->postcode,
            'country'         =>  $country,
            'city'            =>  $billingAddress->city ,
            'phone'           => array( 'number' => $billingAddress->phone),

        );

        $shippingAddressConfig = array(
            'addressLine1'  =>  $shippingAddress->address1,
            'addressLine2'  =>  $shippingAddress->address1,
            'postcode'      =>  $shippingAddress->postcode,
            'country'       =>  $country,
            'city'          =>  $shippingAddress->city,
            'phone'              => array( 'number' => $shippingAddress->phone)

        );
        $products = array();
        foreach ($cart->getProducts() as $item ) {

            $products[] = array (
                'name'          =>     strip_tags($item['name']),
                'sku'           =>     strip_tags($item['reference']),
                'price'         =>     $item['price']*100,
                'quantity'      =>     $item['cart_quantity']

            );
        }

        $config['postedParam'] = array (
            'email'             =>  $customer->email ,
            'value'             =>  $amountCents,
            'currency'          =>  $currency->iso_code,
            'trackId'           =>  $orderId,
            'description'       =>  "Order number::$orderId",
            'shippingDetails'   =>  $shippingAddressConfig,
            'products'          =>  $products,
            'card'              =>  array (
                'billingDetails'   =>    $billingAddressConfig

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
