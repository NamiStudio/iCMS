<?php
/**
 * @package iCMS
 * @copyright 2007-2010, iDreamSoft
 * @license http://www.idreamsoft.com iDreamSoft
 * @author coolmoo <idreamsoft@qq.com>
 * @$Id: article.tpl.php 2408 2014-04-30 18:58:23Z coolmoo $
 */
defined('iPHP') OR exit('What are you doing?');
iPHP::appClass("tag",'break');
function article_list($vars){
    if($vars['loop']==="rel" && empty($vars['id'])){
        return false;
    }
    $resource  = array();
    $where_sql = " `status`='1'";
    $hidden    = iCache::get('iCMS/category/hidden');
    $hidden &&  $where_sql.=iPHP::where($hidden,'cid','not');
    $maxperpage=isset($vars['row'])?(int)$vars['row']:10;
    $cacheTime =isset($vars['time'])?(int)$vars['time']:-1;
    isset($vars['userid']) && $where_sql .=" AND `userid`='{$vars['userid']}'";
    isset($vars['author']) && $where_sql .=" AND `author`='{$vars['author']}'";
    isset($vars['top']) && $where_sql    .=" AND `top`='"._int($vars['top'])."'";
    $vars['call']=='user' && $where_sql.=" AND `postype`='0'";
    $vars['call']=='admin' && $where_sql.=" AND `postype`='1'";
    $vars['scid'] && $where_sql   .=" AND `scid`='{$vars['scid']}'";


   $map_type = $map_node = array();

    if(isset($vars['cid!'])){
    	$ncids    = $vars['cid!'];
    	if($vars['sub']){
        	$ncids	= iCMS::get_category_ids($vars['cid!'],true);
        	array_push ($ncids,$vars['cid!']);
        }
        $where_sql.= iPHP::where($ncids,'cid','not');
    }
    if(isset($vars['cid'])){
        // $map_type[] = 1;
        // $map_node   = array_merge($map_node,(array)$vars['cid']);
        // if($vars['sub']){
        //     $cids     = iCMS::get_category_ids($vars['cid'],true);
        //     $map_node = array_merge($map_node,(array)$cids);
        // }
        $cids = $vars['cid'];
        if($vars['sub']){
            $cids  = iCMS::get_category_ids($vars['cid'],true);
            array_push ($cids,$vars['cid']);
        }
        iPHP::import(iPHP_APP_CORE .'/iMAP.class.php');
        map::init('category',iCMS_APP_ARTICLE);
        $where_sql.= map::exists($cids,'`#iCMS@__article`.id'); //map 表大的用exists
    }
    // && $where_sql.= " AND `pid` ='{$vars['pid']}'";
    if(isset($vars['pid'])){
        iPHP::import(iPHP_APP_CORE .'/iMAP.class.php');
        map::init('prop',iCMS_APP_ARTICLE);
        $where_sql.= map::exists($vars['pid'],'`#iCMS@__article`.id'); //map 表大的用exists

        // $map_type[] = 0;
        // $map_node   = array_merge($map_node,(array)$vars['pid']);
    }
    // var_dump($map_node);

    // if($map_node){
    //     iPHP::import(iPHP_APP_CORE .'/iMAP.class.php');
    //     map::$table = 'article';
    //     map::$appid = $map_type;
    //     $where_sql.= map::exists($map_node,'`#iCMS@__article`.id'); //map 表大的用exists
    // }
    $vars['id'] && $where_sql.= iPHP::where($vars['id'],'id');
    $vars['id!'] && $where_sql.= iPHP::where($vars['id!'],'id','not');
    $by=$vars['by']=="ASC"?"ASC":"DESC";
    if($vars['keywords']){
        if(strpos($vars['keywords'],',')===false){
             $vars['keywords']=str_replace(array('%','_'),array('\%','\_'),$vars['keywords']);
            $where_sql.= " AND CONCAT(title,keywords,description) like '%".addslashes($vars['keywords'])."%'";
           }else{
            $kw=explode(',',$vars['keywords']);
            foreach($kw AS $v){
                $keywords.=addslashes($v)."|";
            }
            $keywords=substr($keywords,0,-1);
            $where_sql.= "  And CONCAT(title,keywords,description) REGEXP '$keywords' ";
        }
    }
    isset($vars['pic']) && $where_sql.= " AND `isPic`='1'";
    isset($vars['nopic']) && $where_sql.= " AND `isPic`='0'";
    switch ($vars['orderby']) {
        case "id":          $order_sql =" ORDER BY `id` $by";            break;
        case "hot":         $order_sql =" ORDER BY `hits` $by";          break;
        case "comment":     $order_sql =" ORDER BY `comments` $by";      break;
        case "pubdate":     $order_sql =" ORDER BY `pubdate` $by";       break;
        case "disorder":    $order_sql =" ORDER BY `orderNum` $by";      break;
        case "rand":        $order_sql =" ORDER BY rand() $by";          break;
        case "top":         $order_sql =" ORDER BY `top`,`orderNum` ASC";break;
        default:            $order_sql =" ORDER BY `id` DESC";
    }
    isset($vars['startdate'])    && $where_sql.=" AND `pubdate`>='".strtotime($vars['startdate'])."'";
    isset($vars['enddate'])     && $where_sql.=" AND `pubdate`<='".strtotime($vars['enddate'])."'";
    isset($vars['where'])        && $where_sql.=$vars['where'];
    
    $md5    = md5($where_sql.$order_sql.$maxperpage);
    $offset = 0;
    if($vars['page']){
        $total   = iPHP::total($md5,"SELECT count(*) FROM `#iCMS@__article` WHERE {$where_sql}");
        $pagenav = isset($vars['pagenav'])?$vars['pagenav']:"pagenav";
        $pnstyle = isset($vars['pnstyle'])?$vars['pnstyle']:0;
        $multi   = iCMS::page(array('total'=>$total,'perpage'=>$maxperpage,'unit'=>iPHP::lang('iCMS:page:list'),'nowindex'=>$GLOBALS['page']));
        $offset  = $multi->offset;
        //$where_sql.=' `id` >= (SELECT id FROM table LIMIT 1000000, 1) '
        iPHP::assign("article_list_total",$total);
    }
    if($vars['cache']){
        $cache_name = 'article/'.$md5."/".(int)$GLOBALS['page'];
        $resource   = iCache::get($cache_name);
    }
    if(empty($resource)){
        $resource = iDB::getArray("SELECT * FROM `#iCMS@__article` WHERE {$where_sql} {$order_sql} LIMIT {$offset} , {$maxperpage}");
        iDB::debug(1);
        $resource = article_array($vars,$resource);
        $vars['cache'] && iCache::set($cache_name,$resource,$cacheTime);
    }
    return $resource;
}
function article_search($vars){
    $resource  = array();
    $hidden = iCache::get('iCMS/category/hidden');
    $hidden &&  $where_sql .=iPHP::where($hidden,'cid','not');
    $SPH    = iCMS::sphinx();
    $SPH->init();
    $SPH->SetArrayResult(true);
    if(isset($vars['weights'])){
        //weights='title:100,tags:80,keywords:60,name:50'
        $wa=explode(',',$vars['weights']);
        foreach($wa AS $wk=>$wv){
            $waa=explode(':',$wv);
            $FieldWeights[$waa[0]]=$waa[1];
        }
        $FieldWeights OR $FieldWeights=array("title" => 100,"tags" => 80,"name" => 60,"keywords" => 40);
        $SPH->SetFieldWeights($FieldWeights);
    }
    

    $page        = (int)$_GET['page'];
    $maxperpage    = isset($vars['row'])?(int)$vars['row']:10;
    $start         = ($page && isset($vars['page']))?($page-1)*$maxperpage:0;
    $SPH->SetMatchMode(SPH_MATCH_EXTENDED);
    if($vars['mode']){
        $vars['mode'] =="SPH_MATCH_BOOLEAN" && $SPH->SetMatchMode(SPH_MATCH_BOOLEAN);
        $vars['mode'] =="SPH_MATCH_ANY" && $SPH->SetMatchMode(SPH_MATCH_ANY);
        $vars['mode'] =="SPH_MATCH_PHRASE" && $SPH->SetMatchMode(SPH_MATCH_PHRASE);
        $vars['mode'] =="SPH_MATCH_ALL" && $SPH->SetMatchMode(SPH_MATCH_ALL);
        $vars['mode'] =="SPH_MATCH_EXTENDED" && $SPH->SetMatchMode(SPH_MATCH_EXTENDED);
    }
    
    isset($vars['userid']) && $SPH->SetFilter('userid',array($vars['userid']));
    isset($vars['postype']) && $SPH->SetFilter('postype',array($vars['postype']));
    
    if(isset($vars['cid'])){
        $cids    = $vars['sub']?iCMS::get_category_ids($vars['cid'],true):$vars['cid'];
        $cids OR $cids = (array)$vars['cid'];
        $cids    = array_map("intval", $cids);
        $SPH->SetFilter('cid',$cids);
    }
    if(isset($vars['startdate'])){
        $startime    =strtotime($vars['startdate']);
        $enddate    =empty($vars['enddate'])?time():strtotime($vars['enddate']);
        $SPH->SetFilterRange('pubdate',$startime,$enddate);
    }
    $SPH->SetLimits($start,$maxperpage,10000);
    
    $orderBy    = '@id DESC, @weight DESC';
    $order_sql    = ' order by id DESC';
    
    $vars['orderBy']     && $orderBy    = $vars['orderBy'];
    $vars['order_sql']     && $order_sql= ' order by '.$vars['order_sql'];

    $vars['pic'] && $SPH->SetFilter('isPic',array(1));
    $vars['id!'] && $SPH->SetFilter('@id',array($vars['id!']),true);
    
    $SPH->setSortMode(SPH_SORT_EXTENDED,$orderBy);
    
    $query    = $vars['q'];
    $vars['acc']     &&     $query    = '"'.$vars['q'].'"';
    $vars['@']         &&     $query    = '@('.$vars['@'].') '.$query;

    $res = $SPH->Query($query,iCMS::$config['sphinx']['index']);
    
    if (is_array($res["matches"])){
        foreach ( $res["matches"] as $docinfo ){
            $aid[]=$docinfo['id'];
        }
        $aids=implode(',',(array)$aid);
    }
    if(empty($aids)) return;
    
    $where_sql=" `id` in($aids)";
    $offset    = 0;
    if($vars['page']){
        $total = $res['total'];
        iPHP::assign("article_search_total",$total);
        $pagenav = isset($vars['pagenav'])?$vars['pagenav']:"pagenav";
        $pnstyle = isset($vars['pnstyle'])?$vars['pnstyle']:0;
        $multi   = iCMS::page(array('total'=>$total,'perpage'=>$maxperpage,'unit'=>iPHP::lang('iCMS:page:list'),'nowindex'=>$GLOBALS['page']));
        $offset  = $multi->offset;
    }
    $resource = iDB::getArray("SELECT * FROM `#iCMS@__article` WHERE {$where_sql} {$order_sql} LIMIT {$maxperpage}");
    $resource = article_array($vars,$resource);
    return $resource;
}

function article_array($vars,$variable){
    if($variable)foreach ($variable as $key => $value) {  
        if($vars['page']){
            $value['page']  = $GLOBALS['page']?$GLOBALS['page']:1;
            $value['total'] = $total;
        }
        if(isset($vars['picWidth']) && isset($vars['picHeight']) && $value['pic']){
                $im = bitscale(array("tw"  => $vars['picWidth'],"th" => $vars['picHeight'],"w"  =>$value['picwidth'] ,"h" =>$value['picheight']));
                $value['img']=$im;
        }
        $value['pic'] && $value['pic']=iFS::fp($value['pic'],'+http');

        $category	= iCache::get('iCMS/category/'.$value['cid']);
        $value['category']['name']    = $category['name'];
        $value['category']['subname'] = $category['subname'];
        $value['category']['url']     = $category['iurl']->href;
        $value['category']['link']    = "<a href='{$value['category']['url']}'>{$value['category']['name']}</a>";
        $value['url']                 = iURL::get('article',array($value,$category))->href;
        $value['link']                = "<a href='{$value['url']}'>{$value['title']}</a>";
        $value['commentUrl']          = iCMS::$config['router']['publicURL']."/comment.php?indexId=".$value['id']."&categoryId=".$value['cid'];
        if($vars['user']){
            $value['user']['url']  = "/u/".$value['userid'];
            $value['user']['name'] = $value['author'];
            $value['user']['id']   = $value['userid'];
        }
        // if($vars['urls']){
        //     $value['urls']['url']      = "/u/".$value['userid'];
        //     $value['urls']['url']      = "/u/".$value['userid'];
        //     $value['urls']['url']      = "/u/".$value['userid'];
        // }
		if($vars['meta']){
            $value['metadata'] && $value['metadata'] = unserialize($value['metadata']);
        }
        $value['description'] && $value['description'] = nl2br($value['description']);
        if($vars['tags']){
        	tag::getag('tags',$value,$category);
        }
        $resource[$key] = $value;
    }
    return $resource;
}