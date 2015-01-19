<?php

class MobWeb_UpdateStockStatus_Model_Stock_Item extends Mage_CatalogInventory_Model_Stock_Item
{
    protected function _beforeSave()
    {
        parent::_beforeSave();
        // Add our custom observer
        Mage::dispatchEvent('cataloginventory_stock_item_save_before', array('item' => $this));
        return $this;
    }
}
