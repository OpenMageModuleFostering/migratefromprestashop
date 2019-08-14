<?php
class BVK_PrestaImport_IndexController extends Mage_Core_Controller_Front_Action
{
   public function indexAction ()
   {
       $this->loadLayout();
       $this->renderLayout();
   }
   
   public function mamethodeAction ()
   {
     echo 'test mymethod';
    }
}
