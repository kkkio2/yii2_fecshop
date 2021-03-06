<?php
/**
 * FecShop file.
 *
 * @link http://www.fecshop.com/
 * @copyright Copyright (c) 2016 FecShop Software LLC
 * @license http://www.fecshop.com/license/
 */
namespace fecshop\services\product;
use Yii;
use yii\base\InvalidValueException;
use yii\base\InvalidConfigException;
use fecshop\models\mongodb\Product;
/**
 * @author Terry Zhao <2358269014@qq.com>
 * @since 1.0
 */
class ProductMongodb implements ProductInterface
{
	public $numPerPage = 20;
	
	public function getPrimaryKey(){
		return '_id';
	}
	
	public function getByPrimaryKey($primaryKey){
		if($primaryKey){
			return Product::findOne($primaryKey);
		}else{
			return new Product;
		}
	}
	/*
	 * example filter:
	 * [
	 * 		'numPerPage' 	=> 20,  	
	 * 		'pageNum'		=> 1,
	 * 		'orderBy'	=> ['_id' => SORT_DESC, 'sku' => SORT_ASC ],
	 * 		'where'			=> [
	 * 			'price' => [
	 * 				'?gt' => 1,
	 * 				'?lt' => 10,
	 * 			],
	 * 			'sku' => 'uk10001',
	 * 		],
	 * 	'asArray' => true,
	 * ]
	 */
	public function coll($filter=''){
		$query = Product::find();
		$query = Yii::$service->helper->ar->getCollByFilter($query,$filter);
		return [
			'coll' => $query->all(),
			'count'=> $query->count(),
		];
	}
	
	
	public function getCategoryProductIds($product_id_arr,$category_id){
		$id_arr = [];
		if(is_array($product_id_arr) && !empty($product_id_arr)){
			$query = Product::find()->asArray();
			$mongoIds = [];
			foreach($product_id_arr as $id){
				$mongoIds[] = new \MongoId($id);
			}
			//var_dump($mongoIds);
			$query->where(['in',$this->getPrimaryKey(),$mongoIds]);
			$query->andWhere(['category'=>$category_id]);
			$data = $query->all();
			if(is_array($data) && !empty($data) ){
				foreach($data as $one){
					$id_arr[] = $one[$this->getPrimaryKey()];
				}
			}
		}
		//echo '####';
		//var_dump($id_arr);
		//echo '####';
		return $id_arr;
	}
	
	/**
	 * @property $one|Array
	 * save $data to cms model,then,add url rewrite info to system service urlrewrite.                 
	 */
	public function save($one,$originUrlKey){
		//var_dump($one);exit;
		if(!$this->initSave($one)){
			return;
		}
		$currentDateTime = \fec\helpers\CDate::getCurrentDateTime();
		$primaryVal = isset($one[$this->getPrimaryKey()]) ? $one[$this->getPrimaryKey()] : '';
		if($primaryVal){
			$model = Product::findOne($primaryVal);
			if(!$model){
				Yii::$service->helper->errors->add('Product '.$this->getPrimaryKey().' is not exist');
				return;
			}	
			#验证sku 是否重复
			$product_one = Product::find()->asArray()->where([
				'<>',$this->getPrimaryKey(),(new \MongoId($primaryVal))
			])->andWhere([
				'sku' => $one['sku'],
			])->one();
			if($product_one['sku']){
				Yii::$service->helper->errors->add('Product Sku 已经存在，请使用其他的sku');
				return;
			}
		}else{
			$model = new Product;
			$model->created_at = time();
			$model->created_user_id = \fec\helpers\CUser::getCurrentUserId();
			$primaryVal = new \MongoId;
			$model->{$this->getPrimaryKey()} = $primaryVal;
			#验证sku 是否重复
			$product_one = Product::find()->asArray()->where([
				'sku' => $one['sku'],
			])->one();
			if($product_one['sku']){
				Yii::$service->helper->errors->add('Product Sku 已经存在，请使用其他的sku');
				return;
			}
		}
		$model->updated_at = time();
		unset($one['_id']);
		$saveStatus = Yii::$service->helper->ar->save($model,$one);
		$originUrl = $originUrlKey.'?'.$this->getPrimaryKey() .'='. $primaryVal;
		$originUrlKey = isset($one['url_key']) ? $one['url_key'] : '';
		$defaultLangTitle = Yii::$service->fecshoplang->getDefaultLangAttrVal($one['name'],'name');
		$urlKey = Yii::$service->url->saveRewriteUrlKeyByStr($defaultLangTitle,$originUrl,$originUrlKey);
		$model->url_key = $urlKey;
		$model->save();
		return true;
	}
	
	protected function initSave($one){
		if(!isset($one['sku']) || empty($one['sku'])){
			Yii::$service->helper->errors->add(' sku 必须存在 ');
			return false;
		}
		if(!isset($one['spu']) || empty($one['spu'])){
			Yii::$service->helper->errors->add(' spu 必须存在 ');
			return false;
		}
		$defaultLangName = \Yii::$service->fecshoplang->getDefaultLangAttrName('name'); 
		if(!isset($one['name'][$defaultLangName]) || empty($one['name'][$defaultLangName])){
			Yii::$service->helper->errors->add(' name '.$defaultLangName.' 不能为空 ');
			return false;
		}
		$defaultLangDes = \Yii::$service->fecshoplang->getDefaultLangAttrName('description');
		if(!isset($one['description'][$defaultLangDes]) || empty($one['description'][$defaultLangDes])){
			Yii::$service->helper->errors->add(' description '.$defaultLangDes.'不能为空 ');
			return false;
		}
		return true;
	}
	
	/**
	 * remove Product
	 */ 
	public function remove($ids){
		if(empty($ids)){
			Yii::$service->helper->errors->add('remove id is empty');
			return false;
		}
		if(is_array($ids)){
			foreach($ids as $id){
				$model = Product::findOne($id);
				if(isset($model[$this->getPrimaryKey()]) && !empty($model[$this->getPrimaryKey()]) ){
					$url_key =  $model['url_key'];
					Yii::$service->url->removeRewriteUrlKey($url_key);
					$model->delete();
					//$this->removeChildCate($id);
				}else{
					Yii::$service->helper->errors->add("Product Remove Errors:ID:$id is not exist.");
					return false;
				}
			}
		}else{
			$id = $ids;
			$model = Product::findOne($id);
			if(isset($model[$this->getPrimaryKey()]) && !empty($model[$this->getPrimaryKey()]) ){
				$url_key =  $model['url_key'];
				Yii::$service->url->removeRewriteUrlKey($url_key);
				$model->delete();
				//$this->removeChildCate($id);
			}else{
				Yii::$service->helper->errors->add("Product Remove Errors:ID:$id is not exist.");
				return false;
			}
		}
		return true;
	}
	
	//addAndDeleteProductCategory
	public function addAndDeleteProductCategory($category_id,$addCateProductIdArr,$deleteCateProductIdArr){
		# 在 addCategoryIdArr 查看哪些产品，分类id在product中已经存在，
		$idKey = $this->getPrimaryKey();
		//var_dump($addCateProductIdArr);
		if(is_array($addCateProductIdArr) && !empty($addCateProductIdArr) && $category_id){
			
			$addCateProductIdArr = array_unique($addCateProductIdArr);
			foreach($addCateProductIdArr as $product_id){
				if(!$product_id){
					continue;
				}
				$product = Product::findOne($product_id);
				if(!$product[$idKey]){
					continue;
				}
				$category = $product->category;
				$category = ($category && is_array($category)) ? $category : [];
				//echo $category_id;
				if(!in_array($category_id,$category)){
					//echo $category_id;
					$category[] = $category_id;
					$product->category = $category;
					$product->save();
				}
			}
		}
		
		if(is_array($deleteCateProductIdArr) && !empty($deleteCateProductIdArr) && $category_id){
			$deleteCateProductIdArr = array_unique($deleteCateProductIdArr);
			foreach($deleteCateProductIdArr as $product_id){
				if(!$product_id){
					continue;
				}
				$product = Product::findOne($product_id);
				if(!$product[$idKey]){
					continue;
				}
				$category = $product->category;
				if(in_array($category_id,$category)){
					$arr = [];
					foreach($category as $c){
						if($category_id != $c){
							$arr[] = $c;
						}
					}
					$product->category = $arr;
					$product->save();
				}
			}
		}
	}
	
	public function getFrontCategoryProducts($filter){
		$where 			= $filter['where'];
		if(empty($where))
			return [];
		$orderBy 			= $filter['orderBy'];
		$pageNum 		= $filter['pageNum'];
		$numPerPage 	= $filter['numPerPage'];
		$select			= $filter['select'];
		$group['_id'] 	= $filter['group'];
		$project 		= [];
		foreach($select as $column){
			$project[$column] 	= 1;
			$group[$column] 	= ['$first' => '$'.$column];
		}
		$pipelines = [
			[
				'$match' 	=> $where,
			],
			[
				'$sort' => [
					'score' => -1
				]
			],
			[
				'$project' 	=> $project
			],
			[
				'$group'	=> $group,
			],
			[
				'$sort' 	=> $orderBy,
			],
		];

		$product_data = Product::getCollection()->aggregate($pipelines);
		$product_total_count = count($product_data);
		$pageOffset = ($pageNum - 1) * $numPerPage;
		$products = array_slice($product_data, $pageOffset, $numPerPage);
		return [
			'coll' => $products,
			'count' => $product_total_count,
		];
	}
	
}


