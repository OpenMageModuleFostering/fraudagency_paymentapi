<?php
  class Fraudagency_Paymentapi_Adminhtml_PaymentapiController extends Mage_Adminhtml_Controller_Action {
  	public function sendPaymentMethodsAction() {
  		$enablePaymentapi  = Mage::getStoreConfig('paymentapi/general/activate_fraudagency_paymentapi_enable');
  		$enablePaymentapi = intval($enablePaymentapi);
  		$allAvailablePaymentMethods = Mage::getModel('payment/config')->getAllMethods();
  		if($enablePaymentapi) {
  			$store = Mage::app()->getStore();
  			foreach ($allAvailablePaymentMethods as $paymentCode=>$paymentModel) {
  				$active = Mage::getStoreConfigFlag('payment/'.$paymentCode.'/active', $store);
  				if($active) {
  					$status = $active;
  				} else {
  					$status = '0';
  				}
  				$paymentTitle = Mage::getStoreConfig('payment/'.$paymentCode.'/title');
  				$array_with_payment_methods[] = array(
  					'payment_method' => $paymentCode,
  					'payment_method_txt' => $paymentTitle,
  					'status' => $status
  				);
  			}
  			$license_key = Mage::getStoreConfig('paymentapi/general/apikey_fraudagency_paymentapi_apikey');
  			$array = array(
  		    'key' => $license_key,
  		    'query' => 'change_payment_methods', 
  		    'payment_method' => $array_with_payment_methods
  		  );
  			$session = Mage::getSingleton('core/session');
        $response = json_decode(file_get_contents(('https://fraud.agency/api/?q='. urlencode(json_encode($array))), 0, stream_context_create(array('https' => array('timeout' => 5)))), 1);
  			if (empty($response['err_msg']) && $response['call']==1) {
  				$result = 'All payment methods submitted successfully';
  				Mage::app()->getResponse()->setBody($result);
  			} else {
  				$result = 'License key is not valid';
  				Mage::app()->getResponse()->setBody($result);
  			}
  		} else {
  			$result = 'Enable the Payment-API module, then try again';
  			Mage::app()->getResponse()->setBody($result);
  		}
  	}
  }
?>