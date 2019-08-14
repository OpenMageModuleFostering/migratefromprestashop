<?php

/**
 * Description of BVK_PrestaImportCommon
 *
 * @author burhan
 */
class BVK_PrestaImportCommon extends Mage_Adminhtml_Controller_Action{
    
    protected $prestashopurl, $apikey, $categories=array(),  $categoryimages=array(), 
            $slang=array(), $languages=array(), $websiteid, $defaultlang, $ch;
    
    public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = array()){
        parent::__construct($request, $response, $invokeArgs);
        
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, TRUE);
    }
    
    public function __destruct(){
        
        curl_close($this->ch);

    }


    protected function loadURL($url){
      
//        $ch = curl_init();
        curl_setopt($this->ch, CURLOPT_URL, $url);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $result = curl_exec($this->ch);
//        curl_close($ch);
        return $result;
      $context = stream_context_create(array('http' => array('header'=>'Connection: close\r\n')));
      return file_get_contents($url, false, $context);
  }


  protected function loadImportData(){
      
        $websites = Mage::app()->getWebsites();
        $this->websiteid=current($websites)->getId();
        
        $storelist=Mage::app()->getStores();
        foreach($storelist AS $id=>$s){
            $this->slang[$id]=substr(Mage::getStoreConfig('general/locale/code', $id), 0, 2);
            if($id==Mage::app()->getDefaultStoreView()->getId()){
                $this->defaultlang=$id;
            }
        }
        $isodef=$this->slang[$this->defaultlang];
        unset($this->slang[$this->defaultlang]);
        $this->slang[$this->defaultlang]=$isodef;
        
        $this->prestashopurl=Mage::getStoreConfig('prestaimport_options/messages/prestaimport_URL');
        $this->apikey=Mage::getStoreConfig('prestaimport_options/messages/prestaimport_apikey');
        
        $xmllanguages=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/languages'));
        
        foreach($xmllanguages->languages->language AS $l){
            $lang=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/languages/'.$l['id']));
            $this->languages[(String)$lang->language->iso_code]=array(
                'id'=>(String)$lang->language->id,
                'name'=>(String)$lang->language->name
            );
        }
  }
  
  protected function addCategory($id, $image=false){
        $id=(int) $id;
//        if(isset($this->categories[$id])){
//          return;
//        }
        
        $category=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/categories/'.$id))->category;
        
        $id_parent=(int) $category->id_parent;
        $parent=Mage::getModel('catalog/category')->load($id_parent);
        
        if(!($parent->getId()) && $id_parent>0){
            $this->addCategory($id_parent);
            $parent=Mage::getModel('catalog/category')->load($id_parent);
        }
        
//        $this->categories[$id]=($id_parent>0?$parent->getPath().'/':'').$id;
        
        $categorydata=array();
        $categorydata['entity_id']=(String) $category->id;
        $categorydata['path']=($id_parent==0)?"1":$parent->getPath().'/'.$id;
        $categorydata['parent_id']=$id_parent;
        $categorydata['attribute_set_id']=Mage::getModel('catalog/category')->getDefaultAttributeSetId();
        $categorydata['is_active']=(String) $category->active;
        $categorydata['position']=(String) $category->position;
        $categorydata['level']=(String) $category->level_depth;
        $categorydata['display_mode'] = "PRODUCTS";
        
        $newcategory = Mage::getModel('catalog/category');
        
        $imagename='';
        foreach($this->slang AS $ids=>$lang){
            if(isset($this->languages[$lang])){
                unset($categorydata['description']);
                foreach($category->description->language AS $val){
                    if($val['id']==$this->languages[$lang]['id']){
                        $categorydata['description']=(String) $val;
                    }
                }
                
                unset($categorydata['url_key']);
                foreach($category->link_rewrite->language AS $val){
                    if($val['id']==$this->languages[$lang]['id']){
                        $categorydata['url_key']=(String) $val;
                        if($ids==$this->defaultlang){
                            $imagename=(String) $val;
                        }
                    }
                }
                
                unset($categorydata['meta_title']);
                foreach($category->meta_title->language AS $val){
                    if($val['id']==$this->languages[$lang]['id']){
                        $categorydata['meta_title']=(String) $val;
                    }
                }
                
                unset($categorydata['meta_description']);
                foreach($category->meta_description->language AS $val){
                    if($val['id']==$this->languages[$lang]['id']){
                        $categorydata['meta_description']=(String) $val;
                    }
                }
                
                unset($categorydata['meta_keywords']);
                foreach($category->meta_keywords->language AS $val){
                    if($val['id']==$this->languages[$lang]['id']){
                        $categorydata['meta_keywords']=(String) $val;
                    }
                }
                
                $categorydata['name'] = '';
                foreach($category->name->language AS $name){
                    if($name['id']==$this->languages[$lang]['id']){
                        $categorydata['name']=(String) $name;
                        $newcategory->setStoreId($ids);
                        $newcategory->addData($categorydata);
                        $newcategory->save();
                    }
                }
            }
        }
        
        $newcategory->setStoreId(0);
        if(isset($this->categoryimages[$id]) || $image){
            $filepath=Mage::getBaseDir('media').DS.'catalog/category/'.$id.'_'.$imagename.'.jpg';
            file_put_contents($filepath, $this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/images/categories/'.$id));
            $newcategory->setStoreId(0);
            $newcategory->addData(
                    array(
                        'image'=>$id.'_'.$imagename.'.jpg',
                        'thumbnail'=>$id.'_'.$imagename.'.jpg'
                        )
                    );
            $newcategory->save();
        }
        
  }
  
  protected function addProduct($id){
        $id=(int) $id;
//        if($id!=1) return;
//        echo $id."  ";
        $product=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/products/'.$id))->product;
        
        $newproduct = Mage::getModel('catalog/product')->setTypeId('simple');
        
//        
//foreach ($newproduct->getAttributes() as $attribute) {
//    echo $attribute->getAttributeCode().'<br/>';
//}
//        die();
        $productdata=array();
        $productdata['attribute_set_id']=Mage::getModel('catalog/product')->getDefaultAttributeSetId();
        $productdata['entity_id']=(String) $product->id;
        $productdata['path']=(String) $product->id_category_default."/".(String) $product->id;
        $productdata['parent_id']=(String) $product->id_category_default;
        $productdata['is_active']=(String) $product->active;
        $productdata['position']=(String) $product->position;
        $productdata['qty']=(String) $product->quantity;
        $productdata['weight']=(String) $product->weight;
        $productdata['sku']=(String) $product->ean13;
        $productdata['price']=(real) $product->price;
        $productdata['cost']=(real) $product->wholesale_price;
        $productdata['position']=(String) $product->position_in_category;
        
        $categories=array();
        foreach($product->associations->categories->category AS $c){
            $categories[]=(String) $c->id;
        }
        $newproduct->setCategoryIds($categories);
        
        $newproduct->setWebsiteIds(array($this->websiteid));
        $newproduct->addData($productdata);
        $newproduct->save();
        $productdata=array();
        
        $imagename='';
        foreach($this->slang AS $ids=>$lang){
            if(isset($this->languages[$lang])){
                unset($productdata['description']);
                foreach($product->description->language AS $val){
                    if($val['id']==$this->languages[$lang]['id']){
                        $productdata['description']=(String) $val;
                    }
                }
                
                unset($productdata['short_description']);
                foreach($product->description_short->language AS $val){
                    if($val['id']==$this->languages[$lang]['id']){
                        $productdata['short_description']=(String) $val;
                    }
                }
                
                unset($productdata['url_key']);
                foreach($product->link_rewrite->language AS $val){
                    if($val['id']==$this->languages[$lang]['id']){
                        $productdata['url_key']=(String) $val;
                        if($ids==$this->defaultlang){
                            $imagename=(String) $val;
                        }
                    }
                }
                
                unset($productdata['meta_title']);
                foreach($product->meta_title->language AS $val){
                    if($val['id']==$this->languages[$lang]['id']){
                        $productdata['meta_title']=(String) $val;
                    }
                }
                
                unset($productdata['meta_description']);
                foreach($product->meta_description->language AS $val){
                    if($val['id']==$this->languages[$lang]['id']){
                        $productdata['meta_description']=(String) $val;
                    }
                }
                
                unset($productdata['meta_keywords']);
                foreach($product->meta_keywords->language AS $val){
                    if($val['id']==$this->languages[$lang]['id']){
                        $productdata['meta_keywords']=(String) $val;
                    }
                }
                
                $productdata['name'] = '';
                foreach($product->name->language AS $name){
                    if($name['id']==$this->languages[$lang]['id']){
                        $productdata['name']=(String) $name;
                        $newproduct->setStoreId($ids);
                        $newproduct->addData($productdata);
                        $newproduct->save();
                    }
                }
            }
        }
        
        if($product->associations->product_option_values->product_options_values){
            $options=array();
            $i=0;
            foreach($product->associations->product_option_values->product_options_values AS $ov){
                $ovid=(int) $ov->id;
                $ov=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/product_option_values/'.$ovid))->product_option_value;
                if(!isset($options[(int) $ov->id_attribute_group])){
                    $op=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/product_options/'.(int) $ov->id_attribute_group))->product_option;
                    foreach($this->slang AS $ids=>$lang){
                        if(isset($this->languages[$lang])){
                            foreach($op->public_name->language AS $name){
                                if($name['id']==$this->languages[$lang]['id']){
                                    $options[(int) $ov->id_attribute_group][$ids]['data']=array(
                                        'title'=>(String) $name,
                                        'type' => ((String) $op->group_type)=='radio'?'radio':'drop_down',
                                        'is_require' => 1,
                                        'sort_order' => (int) $op->position,
                                        'values' => array()
                                    );
                                }
                            }
                        }
                    }
                }
                foreach($this->slang AS $ids=>$lang){
                    if(isset($this->languages[$lang])){
                        foreach($ov->name->language AS $name){
                            if($name['id']==$this->languages[$lang]['id']){
                                $options[(int) $ov->id_attribute_group][$ids]['values'][$i]=array(
                                    'title'=>(String) $name,
                                    'price' => 0.00,
                                    'price_type' => 'fixed',
                                    'sku' => '',
                                    'sort_order' => (int) $ov->position
                                );
                            }
                        }
                    }
                }
                $i++;
            }
            
            try{
                foreach($options AS $op){
                    $newoption=Mage::getModel('catalog/product_option');
                    $optiondata['attribute_set_id']=Mage::getModel('catalog/product_option')->getDefaultAttributeSetId();
                    $newoption->addData($optiondata);
                    $f=true;
                    $values=array();
                    foreach(array_reverse($op, true) AS $ids=>$option){
                        if($f){
                            $newproduct->setStoreId($ids);
                            $newoption->setProduct($newproduct);
                            $newoption->setOptions(array($option['data']));
                            $newoption->saveOptions();
                            $newproduct->setHasOptions(1);
//                            $newproduct->save();
                            foreach($option['values'] AS $idl=>$value){
                                $newvalue=Mage::getModel('catalog/product_option_value');
                                $value['option_id']=$newoption->getId();
                                $newvalue->setData($value);
                                $newvalue->save();
                                $newoption->addValue($newvalue);
                                $values[$idl]=$newvalue;
                            }
                        }else{
                            $newoption->setData('store_id', $ids);
                            $newoption->addData('product_id', $id);
                            $newoption->addData($option['data']);
                            foreach($option['values'] AS $idl=>$value){
                                $newvalue=$values[$idl];
                                $newvalue->setData('store_id', $ids);
                                $newvalue->addData($value);
                                $newvalue->save();
                            }
                            $newoption->save();
                        }
                        $f=false;
                    }
                    
                    
                }
            }catch (Exception $e) {
                echo $e->getMessage();
            }
        }
        
        if($product->associations->images->image){
            $newproduct->setStoreId(0);
            foreach($product->associations->images->image AS $i){
                $imageid=(String) $i->id;
                $filepath=Mage::getBaseDir('media').DS.'tmp/'.$id.'_'.$imageid.'_'.$imagename.'.jpg';
//                echo $filepath."<br>";
                file_put_contents($filepath, $this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/images/products/'.$id.'/'.$imageid));
                try{
                    $newproduct->addImageToMediaGallery($filepath, ((int) $product->id_default_image == $imageid)?array('image', 'small_image', 'thumbnail'):null, true, false);
                }catch (Exception $e) {
                    echo $e->getMessage();
                }
            }
//            $newproduct->save();
        }
        
        $newproduct->save();
        
//        die();
  }
  
  protected function addCustomer($id){
        $id=(int) $id;
        $customer=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/customers/'.$id))->customer;
        
        $newcustomer = Mage::getModel('customer/customer');
        
//foreach ($newcustomer->getAttributes() as $attribute) {
//    echo $attribute->getAttributeCode().'<br/>';
//}
//        die();
        $customerdata=array();
        $customerdata['attribute_set_id']=Mage::getModel('customer/customer')->getDefaultAttributeSetId();
        $customerdata['entity_id']=(String) $customer->id;
        $customerdata['is_active']=(String) $customer->active;
        $customerdata['firstname']=(String) $customer->firstname;
        $customerdata['lastname']=(String) $customer->lastname;
        $customerdata['email']=(String) $customer->email;
//        $customerdata['password_hash']=(String) $customer->passwd;
        $customerdata['dob']=(String) $customer->birthday;
        $customerdata['website_id']=$this->websiteid;
        $customerdata['store_id']=$this->defaultlang;
        $customerdata['gender']=(String) $customer->id_gender;
        try{
            $newcustomer->addData($customerdata);
            $newcustomer->save();
        }catch (Exception $e) {
            echo $e->getMessage();
        }
  }
  
  protected function addAddress($id){
        $id=(int) $id;
        $address=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/addresses/'.$id))->address;
        
        if((String) $address->id_customer=="0"){
            return;
        }
        
        $newaddress = Mage::getModel('customer/address');
        
//foreach ($newaddress->getAttributes() as $attribute) {
//    echo $attribute->getAttributeCode().'<br/>';
//}
//        die();
        $addressdata=array();
        $addressdata['attribute_set_id']=Mage::getModel('customer/address')->getDefaultAttributeSetId();
        $addressdata['entity_id']=(String) $address->id;
        $addressdata['parent_id']=(String) $address->id_customer;
        $addressdata['firstname']=(String) $address->firstname;
        $addressdata['lastname']=(String) $address->lastname;
        $addressdata['company']=(String) $address->company;
        $addressdata['street']=array(
            0=>(String) $address->address1,
            1=>(String) $address->address2
        );
        $addressdata['postcode']=(String) $address->postcode;
        $addressdata['city']=(String) $address->city;
        $addressdata['telephone']=(String) $address->phone;
        $addressdata['vat_id']=(String) $address->vat_number;
        $addressdata['store_id']=$this->defaultlang;
        
        if(((String) $address->id_country)!="0"){
            $country=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/countries/'.(int) $address->id_country))->country;
            $addressdata['country_id']=(String) $country->iso_code;
//            $addressdata['country_id']=Mage::getModel('directory/country')->loadByCode((String) $country->iso_code)->getId();
        }
        
        if(((String) $address->id_state)!="0"){
            $state=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/states/'.(int) $address->id_state))->state;
            $addressdata['region_id']=Mage::getModel('directory/region')->loadByCode((String) $state->iso_code, (String) $country->iso_code)->getId();
//            die();
        }
        
        try{
            $newaddress->addData($addressdata)
            ->setIsDefaultBilling('1')
            ->setIsDefaultShipping('1')
            ->setSaveInAddressBook('1');
            $newaddress->save();
        }catch (Exception $e) {
            echo $e->getMessage();
        }
        
//        die();
  }
  
  protected function addOrder($id){
        $id=(int) $id;
        $order=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/orders/'.$id))->order;
        
//        if((String) $order->id_customer=="0"){
//            return;
//        }
        
        $neworder = Mage::getModel('sales/order');
//        print_r($neworder);
foreach ($neworder->getAttributes() as $attribute) {
    echo $attribute->getAttributeCode().'<br/>';
}
        die();
        $orderdata=array();
        $orderdata['attribute_set_id']=Mage::getModel('sales/order')->getDefaultAttributeSetId();
        $orderdata['entity_id']=(String) $order->id;
        $orderdata['parent_id']=(String) $order->id_customer;
        $orderdata['firstname']=(String) $order->firstname;
        $orderdata['lastname']=(String) $order->lastname;
        $orderdata['company']=(String) $order->company;
        $orderdata['street']=array(
            0=>(String) $order->order1,
            1=>(String) $order->order2
        );
        $orderdata['postcode']=(String) $order->postcode;
        $orderdata['city']=(String) $order->city;
        $orderdata['telephone']=(String) $order->phone;
        $orderdata['vat_id']=(String) $order->vat_number;
        $orderdata['store_id']=$this->defaultlang;
        
        if(((String) $order->id_country)!="0"){
            $country=simplexml_load_string($this->loadURL('http://'.$this->apikey.'@'.$this->prestashopurl.'/api/countries/'.(int) $order->id_country))->country;
            $orderdata['country_id']=(String) $country->iso_code;
        }
        
        try{
            $neworder->addData($orderdata);
            $neworder->save();
        }catch (Exception $e) {
            echo $e->getMessage();
        }
        
//        die();
  }
}
