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
		$log = Mage::helper('updatestockstatus/log');

		// Get the stockitem and load the current product
		$stockItem = $observer->getItem();
		$product = Mage::getModel('catalog/product')->load($stockItem->getProductId());

		if(!$product || !$stockItem) {
			$log->log('Unable to load stock item or product');
		}

		$log->log(sprintf('cataloginventoryStockItemSaveBefore called for product %s with stock quantity %s', $product->getId(), $stockItem['qty']));

		// If the product is a simple product, check its stock quantity and update the stock status accordingly
		if($product->getTypeId() === 'simple') {
			$stockStatus = ($stockItem['qty'] > 0) ? 1 : 0;
			$stockItem->setData('is_in_stock', $stockStatus);

			$log->log(sprintf('Simple product %s stock status updated to %s', $product->getId(), $stockStatus));

			// If the simple product is associated to a parent configurable product, check the stock status of the other simple
			// products associated to the same configurable product and update that configurable product's stock status according
			// to the stock quantities of all the associated simple products
			$parentProductIds = Mage::getResourceSingleton('catalog/product_type_configurable')->getParentIdsByChild($product->getId());
			if($parentProductIds && count($parentProductIds)) {
				$parentProductId = array_values($parentProductIds);
				$parentProductId = array_pop($parentProductIds);
				$parentProduct = Mage::getModel('catalog/product')->load($parentProductId);

				$siblingProducts = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(NULL, $parentProduct);
				if(count($siblingProducts)) {

					// By default, assume that all sibling products are out of stock
					$parentProductStockStatus = 0;

					// Loop through the simple products and check their stock status
					foreach($siblingProducts AS $siblingProduct) {

						// For the product that is currently being saved, we can't retrieve the stock quantity from the product
						// object, as the quantity update has not been saved yet. Instead, get it from the stock item that was
						// retrieved from the observer
						if($siblingProduct->getId() === $product->getId()) {
							$siblingProductStockQuantity = $stockItem['qty'];
						} else {

							// Get the sibling product's stock item
							$siblingProductStockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($siblingProduct->getEntityId());

							// Get the sibling product's stock quantity
							$siblingProductStockQuantity = $siblingProductStockItem['qty'];
						}

						// If the sibling product's stock quantity is greater than 0, save that information
						// and abort the loop
						if($siblingProductStockQuantity > 0) {
							$parentProductStockStatus = 1;

							$log->log(sprintf('Child simple product %s is in stock', $siblingProduct->getId()));

							break;
						} else {
							$log->log(sprintf('Child simple product %s is out of stock', $siblingProduct->getId()));
						}
					}

					$log->log(sprintf('Saving configurable product %s stock status: %s', $parentProduct->getId(), $parentProductStockStatus));

					// Update the parent configurable product's stock status
					Mage::getModel('cataloginventory/stock_item')->loadByProduct($parentProduct->getEntityId())->setData('is_in_stock', $parentProductStockStatus)->save();
				} else {
					$log->log(sprintf('No child simple products found for configurable product %s', $parentProduct->getId()));
				}
			} else {
				$log->log(sprintf('Unable to load parent configurable product for simple product %s', $product->getId()));
			}
		} else {
			$log->log(sprintf('Product %s is not a simple product, not doing anything', $product->getId()));
		}
	}
}