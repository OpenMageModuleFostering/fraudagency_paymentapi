<?php
  class Fraudagency_Paymentapi_Block_Adminhtml_System_Config_Form_Button extends Mage_Adminhtml_Block_System_Config_Form_Field {
    protected function _construct() {
      parent::_construct();
      $this->setTemplate('paymentapi/system/config/button.phtml');
    } protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element) {
      return $this->_toHtml();
    }
    public function getAjaxCheckUrl() {
      return Mage::helper('adminhtml')->getUrl('adminhtml/adminhtml_paymentapi/sendPaymentMethods');
    }
    public function getButtonHtml() {
      $button = $this->getLayout()->createBlock('adminhtml/widget_button')->setData(
        array(
          'id' => 'paymentapi_button',
          'label' => $this->helper('adminhtml')->__('Submit Payment Methods'),
          'onclick' => 'javascript:check(); return false;',
          'disabled' => '' 
        )
      );
      return $button->toHtml();
    }
  }
?>