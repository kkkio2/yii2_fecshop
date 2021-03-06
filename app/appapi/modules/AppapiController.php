<?php
/**
 * FecShop file.
 *
 * @link http://www.fecshop.com/
 * @copyright Copyright (c) 2016 FecShop Software LLC
 * @license http://www.fecshop.com/license/
 */
namespace fecshop\app\appapi\modules;
use Yii;
use fec\helpers\CConfig;
use yii\rest\ActiveController;
use yii\base\InvalidValueException;
/**
 * @author Terry Zhao <2358269014@qq.com>
 * @since 1.0
 */
class AppapiController extends ActiveController
{
	public $blockNamespace;
	
	 
	/**
	 * get current block 
	 * you can change $this->blockNamespace
	 */
	public function getBlock($blockName=''){
		if(!$blockName){
			$blockName = $this->action->id;
		}
		if(!$this->blockNamespace){
			$this->blockNamespace = Yii::$app->controller->module->blockNamespace;
		}
		if(!$this->blockNamespace){
			throw new \yii\web\HttpException(406,'blockNamespace is empty , you should config it in module->blockNamespace or controller blockNamespace ');
		}
		
		$relativeFile = '\\'.$this->blockNamespace;
		$relativeFile .= '\\'.$this->id.'\\'.ucfirst($blockName);
		return new $relativeFile;
	}
	
	
	
	
	
}
