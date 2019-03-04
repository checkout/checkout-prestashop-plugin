<?php

class CheckoutapipaymentSuccessModuleFrontController extends ModuleFrontController
{
  public $display_column_left = false;
  /**
   * @see FrontController::initContent()
   */

  public function initContent() {
    $this->display_column_left = false;
    parent::initContent();
    $cart = $this->context->cart;
    $total = (float) $cart->getOrderTotal(true, Cart::BOTH);
    $currency = $this->context->currency;
    $customer = new Customer((int) $cart->id_customer);
    $paymentToken = $_REQUEST['cko-payment-token'];
    $config['authorization'] = Configuration::get('CHECKOUTAPI_SECRET_KEY');
    $config['paymentToken'] = $paymentToken;
    $Api = CheckoutApi_Api::getApi(array('mode' => Configuration::get('CHECKOUTAPI_TEST_MODE')));
    $respondCharge = $Api->verifyChargePaymentToken($config);
    $amountCents = $Api->valueToDecimal($total,$currency->iso_code);

    $toValidate = array(
      'currency' => $currency->iso_code,
      'value' => $amountCents,
    );

    $validateRequest = $Api::validateRequest($toValidate,$respondCharge);

    if (preg_match('/^1[0-9]+$/', $respondCharge->getResponseCode())) {
      $message = 'Your payment was sucessfull with Checkout.com with transaction Id '.$respondCharge->getId();
      if(!$validateRequest['status']){
          foreach($validateRequest['message'] as $errormessage){
            $message .= $errormessage . '. ';
          }
      }

      $order_state = ( Configuration::get('CHECKOUTAPI_PAYMENT_ACTION') == 'authorize_capture' &&
              $respondCharge->getCaptured()) ? Configuration::get('PS_OS_PAYMENT') : Configuration::get('PS_OS_CHECKOUT');

      $this->module->validateOrder((int) $cart->id, $order_state, $total, $this->module->displayName, $message, array
          ('transaction_id' => $respondCharge->getId()), (int)$currency->id, false, $customer->secure_key);

      $config['authorization'] = Configuration::get('CHECKOUTAPI_SECRET_KEY');
      $config['mode'] = Configuration::get('CHECKOUTAPI_TEST_MODE');
      $Api = CheckoutApi_Api::getApi($config);
      $Api->updateTrackId($respondCharge, $this->module->currentOrder);

      if(isset($this->context->cookie->saveCardCheckbox)) {
        if($this->context->cookie->saveCardCheckbox == 1) {
          $saveCardCheck = $this->context->cookie->saveCardCheckbox;
          $this->_saveCard($respondCharge, $customer, $saveCardCheck);
        }

        $this->context->cookie->saveCardCheckbox = '';
      }

    }else {
      $this->module->validateOrder((int) $cart->id, Configuration::get('PS_OS_ERROR'), $total, $this->module->displayName, 'An error has occcur while processing this transaction (' . $respondCharge->getResponseMessage() . ')', array
          ('transaction_id' => $respondCharge->getId()), (int) $currency->id, false, $customer->secure_key);
    }

    $dbLog = models_FactoryInstance::getInstance('models_DataLayer');
    $dbLog->logCharge($this->module->currentOrder, $respondCharge->getId(), $respondCharge);

    Tools::redirectLink(__PS_BASE_URI__ . 'order-confirmation.php?key=' . $customer->secure_key . '&id_cart='
            . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order='
            . (int) $this->module->currentOrder);
  }
}