<?php

class MobWeb_UpdateStockStatus_Model_Observer
{
	/*
	 *
	 * Before saving a stock item, check if the stock is > 0. If yes, set the product's
	 * "Is in Stock" attribute to 1. Also check the same for the parent configurable product
	 *
	 */
	public function cataloginventoryStockItemSaveBefore($observer)
	{
		$stockItem = $observer->getItem();
		$product = Mage::getModel('catalog/product')->load($stockItem->getProductId());
		$log = Mage::helper('updatestockstatus/log');

		if(!$product || !$stockItem) {
			$log->log('Unable to load stock item or product');
		}

		$log->log(sprintf('cataloginventoryStockItemSaveBefore called for product: %s, qty: %s', $product->getId(), $stockItem['qty']));

		// Update the stock status if the quantity is above 0
		if($stockItem['qty'] > 0) {
			if($stockItem->getData('is_in_stock') == '0') {
				// Update the product's "Is in Stock" attribute
				// Important: Do NOT save the stock item here, because that automatically happens as we are in
				// the "save before" observer!
				$stockItem->setData('is_in_stock', 1);

				$log->log('Re-activated stock for product: ' . $product->getId());
			} else {
				$log->log('Product already in stock: ' . $product->getId());
			}

			// If the product is a simple product, check if it is assigned to any configurable products.
			// If yes, update the parent product's stock status as well
			$parentProductIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
			if($parentProductIds && count($parentProductIds)) {
				$parentProductId = array_pop(array_values($parentProductIds));
				$parentProduct = Mage::getModel('catalog/product')->load($parentProductId);

				if($parentProduct) {
					$log->log('Loaded parent: ' . $parentProduct->getId());

					$stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($parentProduct->getEntityId());

					if($stockItem->getData('is_in_stock') == '0') {
						// Update the product's "Is in Stock" attribute, and save the product
						$stockItem->setData('is_in_stock', 1);
						$stockItem->save();

						$log->log('Re-activated stock for parent product: ' . $parentProduct->getId());
					} else {
						$log->log('Parent product already in stock:' . $parentProduct->getId());
					}
				} else {
					$log->log('Unable to load parent product for product: ' . $product->getId());
				}
			} else {
				$log->log('No parent produt found for product: ' . $product->getId());
			}
		} else {
			$log->log('Not doing anything because the product QTY is 0: ' . $product->getId());
		}
	}
}