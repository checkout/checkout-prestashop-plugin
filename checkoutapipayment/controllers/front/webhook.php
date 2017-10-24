<?php

class CheckoutapipaymentWebhookModuleFrontController extends ModuleFrontController
{
	public $display_column_left = false;
	/**
	 * @see FrontController::initContent()
	 */

	public function initContent()
	{
		$this->display_column_left = false;
		parent::initContent();
		if(isset($_GET['chargeId'])) {
			$stringCharge     =   $this->_process();
		}else {
			$stringCharge     = file_get_contents("php://input");
		}

		if(empty($stringCharge)){
        	return http_response_code(400);
        }

        $data = json_decode($stringCharge);
        $eventType          = $data->eventType;

		$Api    =    CheckoutApi_Api::getApi(array('mode' => Configuration::get('CHECKOUTAPI_TEST_MODE')));
		$objectCharge = $Api->chargeToObj($stringCharge);
		$dbLog = models_FactoryInstance::getInstance( 'models_DataLayer' );
        $transaction = $dbLog->getOrderId($objectCharge->getId());
		$id_order = $objectCharge->getTrackId();

		$order = new Order($id_order);
		$history = new OrderHistory();
		$history->id_order = $id_order;
		$current_order_state = $order->getCurrentOrderState();
		
		if($eventType == 'charge.succeeded'){
			return http_response_code(200);

		} elseif($eventType == 'charge.captured'){
			if(!$order->hasBeenPaid()) {
				$order_state = new OrderState(Configuration::get('PS_OS_PAYMENT'));
				if (!Validate::isLoadedObject($order_state)) {
					echo sprintf(Tools::displayError('Order status #%d cannot be loaded'), Configuration::get('PS_OS_PAYMENT'));
					return http_response_code(200);
				}else {
					$current_order_state = $order->getCurrentOrderState();
					if ($current_order_state->id == $order_state->id ) {
						echo  sprintf ( Tools::displayError ( 'Order #%d has already been captured.' ) , $id_order);
						return http_response_code(200);
					} else {
						$order->setCurrentState(Configuration::get('PS_OS_PAYMENT')); 
						echo  sprintf ( Tools::displayError ( 'Order #%d has  been captured.' ) ,
							$id_order);
						return http_response_code(200);
					}
				}
			} else {
				echo 'Payment was already captured for Transaction ID '.$objectCharge->getId();
				return http_response_code(200);
			}
		} elseif($eventType == 'charge.refunded'){
			$order_state = new OrderState(Configuration::get('PS_OS_REFUND'));

			if ($current_order_state->id == $order_state->id ) {
				echo  sprintf ( Tools::displayError ( 'Order #%d has already been refunded.' ) , $id_order );
				return http_response_code(200);
			}else {
				$history->changeIdOrderState ( Configuration::get ( 'PS_OS_REFUND' ) , (int)$id_order );
				$history->addWithemail ();
				echo  sprintf ( Tools::displayError ( 'Order #%d has  been refunded.' ) , $id_order );
				return http_response_code(200);
			}
		} elseif ($eventType == 'charge.voided' || $eventType == 'invoice.cancelled') {
			$order_state = new OrderState(Configuration::get('PS_OS_CANCELED'));

			if ($current_order_state->id == $order_state->id ) {
				echo  sprintf ( Tools::displayError ( 'Order #%d has already been '.$objectCharge->getStatus() ) , $id_order );
				return http_response_code(200);
			}elseif(!$objectCharge->getAuthorised ()){
				$history->changeIdOrderState ( Configuration::get ( 'PS_OS_CANCELED' ) , (int)$id_order );
				$history->addWithemail ();
				echo  sprintf ( Tools::displayError ( 'Order #%d has  been '.$objectCharge->getStatus() ) , $id_order );
				return http_response_code(200);
			}
		} else { 
			$logger = new FileLogger(0);
			$logger->setFilename(_PS_ROOT_DIR_."/modules/checkoutapipayment/webhook.log");
			$logger->logDebug($data);
			return http_response_code(200);
		}
	}

	private function _process()
	{
		$config['chargeId']    =    $_GET['chargeId'];
		$config['authorization']    =    Configuration::get('CHECKOUTAPI_SECRET_KEY');
		$Api    =    CheckoutApi_Api::getApi(array('mode' => Configuration::get('CHECKOUTAPI_TEST_MODE')));
		$respondBody    =    $Api->getCharge($config);
		$json = $respondBody->getRawOutput();
		return $json;
	}
}