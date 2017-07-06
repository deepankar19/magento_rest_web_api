<?php 

// add your web api key
define('WEB_API_KEY', 'add_your_web_api_key');
// include mage file
// for run magento external script in php file
require_once('/app/Mage.php');
Mage::app();
$key = Mage::app()->getRequest()->getParam('key');
if ( !isset($key) || $key != WEB_API_KEY ) 
{
	$json_data = array('success' => false, 'code' => 20, 'message' => 'Invalid secret key');
	print_r(json_encode($json_data));
}
elseif( Mage::app()->getRequest()->getParam('route') == "categories" )
{
	// $_GET parameter 
	$parent = Mage::app()->getRequest()->getParam('parent', 0);
	$level = Mage::app()->getRequest()->getParam('level', 1);
	// End $_GET parameter 
	print_r(json_encode(getCategoryTree($parent, $level)));
}
elseif(Mage::app()->getRequest()->getParam('route') == "products")
{
	// $_GET parameter
	$category_id = Mage::app()->getRequest()->getParam('category', 0);
	// End $_GET parameter
	print_r(json_encode(products($category_id)));
}
elseif(Mage::app()->getRequest()->getParam('route') == "product")
{
	// $_GET parameter 
	$product_id = Mage::app()->getRequest()->getParam('id', 0);
	// End $_GET parameter 
	print_r(json_encode(product($product_id)));
}
elseif(Mage::app()->getRequest()->getParam('route') == "random")
{
	// $_GET parameter 
	$limit = Mage::app()->getRequest()->getParam('limit', 4);
	//  End $_GET parameter
	print_r(json_encode(random_products($limit)));
}
//
//	Random Products Items
//http://localhost/magento/web-api.php?route=random&limit=4&key=your_web_api_key
//
function random_products($limit)
{
	// set json_data array 	
	$json_data = array('success' => true);
	$_products = Mage::getModel('catalog/product')->getCollection();
	$_products->addAttributeToSelect(array('name', 'thumbnail', 'price')); 
	//feel free to add any other attribues you need.
	Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($_products);
	Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($_products); 
	$_products->getSelect()->order('RAND()')->limit($limit);
	foreach($_products as $product_val)
	{ 
		$json_data['products'][] = array(
				'id'		=> $product_val->getId(),
				'name'		=> $product_val->getName(),
				'href'		=> $product_val->getProductUrl(),
				'thumb'		=> (string)Mage::helper('catalog/image')->init($product_val, 'thumbnail'),
				'pirce'		=> Mage::helper('core')->currency($product_val->getPrice(), true, false) 
				//." ".$currencyCode,
			);
	}
	return $json_data;
}
//
//	Product Item
//	
//	http://localhost/magento/web-api.php?route=product&id=800&key=your_web_api_key
//
function product($product_id)
{
	// set json_data array 	
	$json_data = array('success' => true);
	$_product = Mage::getModel('catalog/product')->load($product_id);
	$json_data['product'] = array();
	$json_data['product']['id'] = $_product->getId();
	$json_data['product']['name'] = $_product->getName();
	$json_data['product']['price'] = Mage::helper('core')->currency($_product->getPrice(), true, false);
	$json_data['product']['description'] = $_product->getDescription();
	$json_data['product']['image'] = (string)Mage::helper('catalog/image')->init($_product, 'image');
	$mediaGallery = Mage::getModel('catalog/product')->load($_product->getId())->getMediaGalleryImages()->getItems();
	$json_data['product']['images'] = array();
        //loop through the images
        foreach ($mediaGallery as $image){
            $json_data['product']['images'][] = $image['url'];
        }
	return $json_data;
}
//
//	Products in category
//
//	http://localhost/magento/web-api.php?route=products&category=4&key=your_web_api_key
//
function products($_category_id)
{
	// set json_data array 
	$json_data = array('success' => true, 'products' => array());
	$_category = Mage::getModel ('catalog/category')->load($_category_id);
	$_products = Mage::getResourceModel('catalog/product_collection')
		          // ->addAttributeToSelect('*')
		          ->AddAttributeToSelect('name')
		          ->addAttributeToSelect('price')
		          ->addFinalPrice()
		          ->addAttributeToSelect('small_image')
		          ->addAttributeToSelect('image')
		          ->addAttributeToSelect('thumbnail')
		          ->addAttributeToSelect('short_description')
		          ->addUrlRewrite()
		          ->AddCategoryFilter($_category);
	Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($_products);
	$currencyCode = Mage::app()->getStore()->getBaseCurrencyCode();
	foreach($_products as $product)
	{ 
		$json_data['products'][] = array(
				'id'                    => $product->getId(),
				'name'                  => $product->getName(),
				'description'           => $product->getShortDescription(),
				'pirce'                 => Mage::helper('core')->currency($product->getPrice(), true, false), //." ".$currencyCode,
				'href'                  => $product->getProductUrl(),
				'thumb'                 => (string)Mage::helper('catalog/image')->init($product, 'thumbnail')
			);
	}
	return $json_data;
}
//
//	Categories
//
//	http://localhost/magento/web-api.php?route=categories&parent=0&level=2&key=your_web_api_key
//
function getCategoryTree( $parent_val = 0, $recursion_Level = 1 )
{
    if($parent_val == 0)
	{
        $parent_val = Mage::app()->getStore()->getRootCategoryId();
    }
	else
	{
		$parent_val = Mage::getModel('catalog/category')->load($parent_val)->getId();
    }
    $tree_val = Mage::getResourceModel('catalog/category_tree');
    /* @var $tree_val Mage_Catalog_Model_Resource_Category_Tree */
    $nodes = $tree_val->loadNode($parent_val)
        ->loadChildren($recursion_Level)
        ->getChildren();
    $tree_val->addCollectionData(null, false, $parent_val);
    $json_data = array('success' => true);
    $get_result = array();
    foreach ($nodes as $node_val) {
        $get_result[] = array(
			'category_id'   => $node_val->getData('entity_id'),
			'parent_id'     => $parent_val,
			'name'          => $node_val->getName(),
			'categories'    => get_Node_Children_Data($node_val));
    }
    $json_data['categories'] = $get_result;
    return $json_data;
}

function get_Node_Children_Data(Varien_Data_Tree_Node $get_node)
{
    foreach ($get_node->getChildren() as $get_child_Node) {
        $get_result[] = array(
			'category_id'   => $get_child_Node->getData('entity_id'),
			'parent_id'     => $get_node->getData('entity_id'),
			'name'          => $get_child_Node->getData('name'),
			'categories'    => get_Node_Children_Data($get_child_Node));
	 }
    return $get_result;
}
 ?>
