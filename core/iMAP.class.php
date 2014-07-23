<?php
/**
 * @package iCMS
 * @copyright 2007-2010, iDreamSoft
 * @license http://www.idreamsoft.com iDreamSoft
 * @author coolmoo <idreamsoft@qq.com>
 */
class map {
	public static $table = 'prop';
	public static $field = 'node';
	public static $appid = '1';

	function init($table = 'prop',$appid='1',$field = 'node'){
		self::$table = $table;
		self::$field = $field;
		self::$appid = $appid;
		return self;
	}
	function table(){
		return'#iCMS@__'.self::$table.'_map';
	}
	public function add($nodes,$iid="0") {
		$_array   = explode(',',$nodes);
		$_count   = count($_array);
		$varArray = array();
	    for($i=0;$i<$_count;$i++) {
	        $varArray[$i] = self::addnew($_array[$i],$iid);
	    }
	    return json_encode($varArray);
	}
	public function addnew($node,$iid="0") {
		$has = iDB::getValue("SELECT `id` FROM `".self::table()."` WHERE `".self::$field."`='$node' AND `iid`='$iid' AND `appid`='".self::$appid."' LIMIT 1");
	    if(!$has) {
	        iDB::query("INSERT INTO `".self::table()."` (`".self::$field."`,`iid`, `appid`) VALUES ('$node','$iid','".self::$appid."')");
	    }
	    //return array($vars,$tid,$cid,$tcid);
	}
	function diff($Nnodes,$Onodes,$iid="0") {
		$N        = explode(',', $Nnodes);
		$O        = explode(',', $Onodes);
		$diff     = array_diff_values($N,$O);
		$varsArray = array();
	    foreach((array)$N AS $i=>$_node) {//新增
            $varsArray[$i] = self::addnew($_node,$iid);
		}
	    foreach((array)$diff['-'] AS $_node) {//减少
	        iDB::query("DELETE FROM `".self::table()."` WHERE `".self::$field."`='$_node' AND `iid`='$iid' AND `appid`='".self::$appid."'");
	   }
	   return json_encode($varsArray);
	}
	public function ids($nodes=0){
		$sql      = self::sql($nodes);
		$rs       = iDB::getArray($sql);
		$resource = array();
		iDB::debug(1);
		foreach((array)$rs AS $_vars) {
			$resource[] = $_vars['iid'];
		}
		if($resource){
			$resource = array_unique ($resource);
			$resource = implode(',',$resource);
			return $resource;
		}
		return false;
	}
	public function sql($nodes=0){
		if(!is_array($nodes) && strstr($nodes, ',')){
			$nodes = explode(',', $nodes);
		}
		$where_sql = iPHP::where(self::$appid,'appid',false,true);
		$where_sql.= iPHP::where($nodes,self::$field);
		return "SELECT `iid` FROM ".self::table()." WHERE {$where_sql}";
	}
	public function exists($nodes=0,$iid=''){
		$sql = self::sql($nodes)." AND iid =".$iid;
		return ' AND exists ('.$sql.')';		
	}
	public function multi($nodes=0,$iid=''){
		
	}
}