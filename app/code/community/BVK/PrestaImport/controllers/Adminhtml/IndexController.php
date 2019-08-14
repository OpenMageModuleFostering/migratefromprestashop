<?php

require_once 'common.php';

class BVK_PrestaImport_Adminhtml_IndexController extends BVK_PrestaImportCommon
{
    
  public function indexAction()
  {
          $this->loadLayout();
          $this->renderLayout();
          return $this;
  }
  
  public function importAction()
  {   
      $start=time();
        $this->loadImportData();
        
        $setup = new Mage_Eav_Model_Entity_Setup('core_setup');

        $entityTypeId     = $setup->getEntityTypeId('customer');
        $attributeSetId   = $setup->getDefaultAttributeSetId($entityTypeId);
        $attributeGroupId = $setup->getDefaultAttributeGroupId($entityTypeId, $attributeSetId);

        $setup->addAttribute('customer', 'prestashop_pass', array(
            'input'         => 'text',
            'type'          => 'text',
            'label'         => 'Prestashop Password',
            'visible'       => 0,
            'required'      => 0,
            'user_defined' => 1,
        ));

        $setup->addAttributeToGroup(
         $entityTypeId,
         $attributeSetId,
         $attributeGroupId,
         'prestashop_pass',
         '999'  //sort_order
        );

        $oAttribute = Mage::getSingleton('eav/config')->getAttribute('customer', 'prestashop_pass');
        $oAttribute->setData('used_in_forms', array('adminhtml_customer'));
        $oAttribute->save();
      
        $attribute_code = "url_key"; 
        $attribute = Mage::getSingleton("eav/config")->getAttribute('catalog_product',    $attribute_code);
        $attribute->setData('is_global', 0);
        $attribute->save();
        $attribute = Mage::getSingleton("eav/config")->getAttribute('catalog_category',    $attribute_code);
        $attribute->setData('is_global', 0);
        $attribute->save();
        $attribute = $attribute->getData();
      
        Mage::getModel('catalog/category')->getCollection()->delete();
        
        $xmlcategorimages=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/images/categories'));
        foreach($xmlcategorimages->images->image AS $i){
            $this->categoryimages[(int) $i['id']]=true;
        }
        
        $xmlcategories=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/categories'));
        foreach($xmlcategories->categories->category AS $c){
            $this->addCategory($c['id']);
        }
        
//        echo time()-$start;
//        die();
        
//        echo time()-$start;
//        die();
//        Mage::getModel('catalog/manufacturer')->getCollection()->delete();
//        
//        $xmlmanufacturers=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/manufacturers'));
//        foreach($xmlmanufacturers->manufacturers->manufacturer AS $m){
//            $this->addManufacturer($m['id']);
//        }
        
        Mage::getModel('catalog/product')->getCollection()->delete();
        
        $xmlproducts=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/products'));
        foreach($xmlproducts->products->product AS $p){
            $this->addProduct($p['id']);
        }
        
        Mage::getModel('customer/customer')->getCollection()->delete();
        
        $xmlcustomers=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/customers'));
        
        foreach($xmlcustomers->customers->customer AS $c){
            $this->addCustomer($c['id']);
        }
        
        Mage::getModel('customer/address')->getCollection()->delete();
        $xmladdresses=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/addresses'));
        foreach($xmladdresses->addresses->address AS $a){
            $this->addAddress($a['id']);
        }
        
//        Mage::getModel('sales/order')->getCollection()->delete();
//        $xmlorders=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/orders'));
//        foreach($xmlorders->orders->order AS $o){
//            $this->addOrder($o['id']);
//        }
        
        echo time()-$start;
        $this->loadLayout();
        $this->renderLayout();
  }
  
}