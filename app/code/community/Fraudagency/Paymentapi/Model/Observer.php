<?php
  class Fraudagency_Paymentapi_Model_Observer {
    protected $_pmethods = array();
    private $_email = '';
    private $_billingname = '';
    private $_billingaddress = '';
    private $_billingzip = '';
    private $_billingcity = '';
    private $_billingstate = '';
    private $_billingcountry = '';
    public function paymentApi($observer) {
      $action_name = $observer->getEvent()->getControllerAction()->getFullActionName();
      $pos = strrpos($action_name, "_");
      $action = substr($action_name,$pos+1);
      $param_billing = Mage::app()->getFrontController()->getRequest()->getParam('billing', array());
      if( ($action == 'saveBilling' && $param_billing['use_for_shipping'] == 1) || $action == 'saveShipping') {
        $enablePaymentapi  = Mage::getStoreConfig('paymentapi/general/activate_fraudagency_paymentapi_enable');
        $enablePaymentapi = intval($enablePaymentapi);
        if($enablePaymentapi) {
          $license_key = Mage::getStoreConfig('paymentapi/general/apikey_fraudagency_paymentapi_apikey');
          $quote = Mage::getSingleton('checkout/session')->getQuote();
          $quoteid = $quote->getId();
          if($quoteid) {
            $address=$quote->getBillingAddress();
            if($address->getAddressType() == 'billing') {
              $this->_email = $address->getData('email');
              $this->_billingname = $address->getData("firstname").' '.$address->getData("lastname");
               $this->_billingaddress = $address->getData("street").' '.$address->getStreet(2);
              $this->_billingzip = $address->getData("postcode");
              $this->_billingcity = $address->getData("city");
              $this->_billingstate = $address->getRegionCode();
              $this->_billingcountry = $address->getCountry();
            }
            $address=$quote->getShippingAddress();
            if($address->getAddressType()=='shipping') {
              if(Mage::getSingleton('customer/session')->isLoggedIn()) {
                $customerData = Mage::getSingleton('customer/session')->getCustomer();
                $customerID = $customerData->getId();
              }
              $shipname = $address->getData("firstname").' '.$address->getData("lastname");
               $shipaddress = $address->getStreet(1).' '.$address->getStreet(2);
              $shipPostcode = $address->getData("postcode");
              $shipCity = $address->getData("city");
              $shipRegion = $address->getRegionCode();
              $shipCountry = $address->getCountry();
              $shipPhone = $address->getData("telephone");
              $session_id = Mage::getModel("core/session")->getEncryptedSessionId();
              $order_total = Mage::getSingleton('checkout/session')->getQuote()->getTotals();
              $grandtotal = $order_total["grand_total"]->getValue();
              $currency    = Mage::getSingleton('checkout/session')->getQuote()->getQuoteCurrencyCode();
              $array_with_order_information = array(
                'shopsystem' => 'magento',
                'appversion' => '0.3.8',
                'customer' => $customerID,
                'name' => $this->_billingname,
                'address' => $this->_billingaddress,
                'city' => $this->_billingcity,
                'state' => $this->_billingstate,
                'zip' => $this->_billingzip,
                'country' => $this->_billingcountry,
                'shipname' => $shipname,
                'shipaddr' => $shipaddress,
                'shipcity' => $shipCity,
                'shipstate' => $shipRegion,
                'shipzip' => $shipPostcode,
                'shipcountry' => $shipCountry,
                'phone' => $shipPhone,
                'email' => strtolower($this->_email),
                'passwordmd5' => NULL,
                'order_amount' => $grandtotal,
                'order_currency' => $currency,
                'order_id' => $quoteid,
                'cc_num' => NULL,
                'cc_b_name' => NULL,
                'cc_bin_phone' => NULL,
                'cc_avs_result' => NULL,
                'cc_ccv_result' => NULL,
                'sessionid' => $session_id,
                'ip' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'accept_language' => $_SERVER['HTTP_ACCEPT_LANGUAGE'],
                'forwardedip' => ((!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : NULL)
              );
              $allAvailablePaymentMethods = Mage::getModel('payment/config')->getAllMethods();
              $ActivePaymentMethods = Mage::getSingleton('payment/config')->getActiveMethods();
              foreach ($ActivePaymentMethods as $Code=>$payment) {
                $Activemethods[] = $Code;
              }
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
              $array = array(
                'key' => $license_key,
                'query' => 'check_order',
                'payment_method' => $array_with_payment_methods,
                'order' => $array_with_order_information
              );
              $response = json_decode(file_get_contents(('https://api.fraud.agency/?q='. urlencode(json_encode($array))), 0, stream_context_create(array('https' => array('timeout' => 5)))), 1);
              if (empty($response['err_msg']) && $response['call']==1)  {
                foreach ($response['accepted_payment_method'] as $key => $value) {
                  if (in_array($value['payment_method'], $Activemethods))  {
                    $this->_pmethods[] = $value['payment_method'];
                  }
                }
                Mage::getSingleton('core/session')->setPaymentMethods($this->_pmethods);
              }
            }
          }
         }
      } elseif($action == 'saveOrder') {
        Mage::getSingleton('core/session')->setPaymentMethods('');
      }
    }
    public function paymentMethodActive(Varien_Event_Observer $observer) {
      $event = $observer->getEvent();
      $method = $event->getMethodInstance();
      $result = $event->getResult();
      $getPaymentMethods = Mage::getSingleton('core/session')->getPaymentMethods();
      if(count($getPaymentMethods) > 0) {
        if (!in_array($method->getCode(),$getPaymentMethods)) {
          $result->isAvailable = false;
        }
      }
    }
    public function handle_adminSystemConfigChangedSection() {
      $enablePaymentapi  = Mage::getStoreConfig('paymentapi/general/activate_fraudagency_paymentapi_enable');
      $enablePaymentapi = intval($enablePaymentapi);
      $allAvailablePaymentMethods = Mage::getModel('payment/config')->getAllMethods();
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
      if($enablePaymentapi) {
        $license_key = Mage::getStoreConfig('paymentapi/general/apikey_fraudagency_paymentapi_apikey');
        $array = array(
          'key' => $license_key,
          'query' => 'init_license_key',
          'payment_method' => $array_with_payment_methods
        );
        $response = json_decode(file_get_contents(('https://api.fraud.agency/?q='. urlencode(json_encode($array))), 0, stream_context_create(array('https' => array('timeout' => 5)))), 1);
        $session = Mage::getSingleton('core/session');
        if (empty($response['err_msg']) && $response['call']==1) {
          $session->addSuccess('License key is valid and active');
        } else {
          $session->addError('License key is not valid');
          throw new Exception("stop");
        }
      }
    }
  }
?>