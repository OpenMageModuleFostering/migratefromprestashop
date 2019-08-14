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
        $model = Mage::getModel('eav/entity_attribute')->getCollection()->addFieldToFilter('backend_model', 'catalog/category_attribute_backend_urlkey')->getFirstItem();
        $model->setData('is_global', 0);
        $model->save();
        
        $model = Mage::getModel('eav/entity_attribute')->getCollection()->addFieldToFilter('backend_model', 'catalog/product_attribute_backend_urlkey')->getFirstItem();
        $model->setData('is_global', 0);
        $model->save();
        
        $this->loadImportData();
      
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