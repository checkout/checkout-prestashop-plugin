<?php

namespace CheckoutCom\PrestaShop\Models\Payments;

use Checkout\CheckoutApi;
use Checkout\Models\Response;
use Checkout\Models\Customer;
use Checkout\Models\Payments\Payment;
use Checkout\Models\Payments\ThreeDs;
use Checkout\Models\Payments\IdSource;
use Checkout\Models\Payments\Metadata;
use CheckoutCom\PrestaShop\Helpers\Debug;
use CheckoutCom\PrestaShop\Models\Config;
use Checkout\Models\Payments\BillingDescriptor;
use Checkout\Models\Payments\Method as MethodSource;
use CheckoutCom\PrestaShop\Classes\CheckoutApiHandler;
use Checkout\Library\Exceptions\CheckoutHttpException;

abstract class Method {

	/**
	 * Ignore fields.
	 *
	 * @var        array
	 */
	const IGNORE_FIELDS = array('source', 'isolang', 'id_lang', 'module', 'controller', 'fc');


	/**
	 * Process payment.
	 *
	 * @param      array    $params  The parameters
	 *
	 * @return     Response  ( description_of_the_return_value )
	 */
	abstract public static function pay(array $params);

	/**
	 * Generate payment object.
	 *
	 * @param      \Checkout\Models\Payments\IdSource  $source  The source
	 *
	 * @return     Payment                             ( description_of_the_return_value )
	 */
	public static function makePayment(MethodSource $source) {

		$context = \Context::getContext();

		$payment = new Payment($source, $context->currency->iso_code);
		$payment->amount = (int)('' . ($context->cart->getOrderTotal() * 100)); //@todo: improve this

		$payment->metadata = static::getMetadata($context);
		//$payment->reference = $order->getUniqReferenceOf();

		$cart_id = $context->cart->id;
        $secure_key = $context->customer->secure_key;

        $payment->customer = static::getCustomer($context);

        // Set the payment specifications
        $payment->capture = Config::needsAutoCapture();
        $payment->success_url = $context->link->getModuleLink('checkoutcom', 'confirmation', ['cart_id' => $cart_id, 'secure_key' => $secure_key], true);
        $payment->failure_url = $context->link->getModuleLink('checkoutcom', 'fail', [], true);
        $payment->description = Config::get('PS_SHOP_NAME') . ' Order';
        $payment->payment_type = 'Regular';

        // Security
        $payment->threeDs = new ThreeDs((bool) Config::get('CHECKOUTCOM_CARD_USE_3DS'));
        $payment->threeDs->attempt_n3d = (bool) Config::get('CHECKOUTCOM_CARD_USE_3DS_ATTEMPT_N3D');
        $payment->payment_ip = \Tools::getRemoteAddr();

        if (Config::get('CHECKOUTCOM_DYNAMIC_DESCRIPTOR_ENABLE')) {
        	$payment->billing_descriptor = new BillingDescriptor(Config::get('CHECKOUTCOM_DYNAMIC_DESCRIPTOR_NAME'), Config::get('CHECKOUTCOM_DYNAMIC_DESCRIPTOR_CITY'));
        }

		return $payment;

	}

	/**
	 * Get Meta information.
	 *
	 * @param      \Context  $context  The context
	 *
	 * @return     Metadata  The metadata.
	 */
	protected static function getMetadata(\Context $context) {

		$metadata = new Metadata();

		$module = \Module::getInstanceByName('checkoutcom');
		$metadata->server_url = \Tools::getHttpHost(true);
		$metadata->sdk_data = 'PHP SDK ' . CheckoutApi::VERSION;
		$metadata->integration_data = 'Checkout.com PrestaShop Plugin ' . $module->version;
		$metadata->platform_data = 'PrestaShop ' . _PS_VERSION_;

		return $metadata;

	}

	/**
	 * Get Customer information.
	 *
	 * @param      \Context  $context  The context
	 *
	 * @return     Customer  The metadata.
	 */
	protected static function getCustomer(\Context $context) {

		$customer = new Customer();
		$customer->email = $context->customer->email;
		$customer->name = $context->customer->firstname . ' ' . $context->customer->lastname;
		return $customer;

	}

	/**
	 * Make API request.
	 *
	 * @param      \Checkout\Models\Payments\Payment  $payment  The payment
	 *
	 * @return     <null|Response>
	 */
	protected static function request(Payment $payment) {

		$response = new Response();

		try{
			$response = CheckoutApiHandler::api()->payments()->request($payment);
		} catch(CheckoutHttpException $ex) {
			$response->http_code = $ex->getCode();
			$response->message = $ex->getMessage();
			$response->errors = $ex->getErrors();
			Debug::write($ex->getBody());
		}

		return $response;

	}

	/**
	 * Add extra params to source object.
	 *
	 * @param      \Checkout\Models\Payments\IdSource  $source  The source
	 * @param      array                               $params  The parameters
	 */
	protected static function setSourceAttributes(IdSource $source, array $params) {

		foreach ($params as $key => $value) {
			if(!in_array($key, static::IGNORE_FIELDS)) {
				$source->{$key} = $value;
			}
		}

	}



}