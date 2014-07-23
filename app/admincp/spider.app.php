<?php

/**
 * iCMS - i Content Management System
 * Copyright (c) 2007-2012 idreamsoft.com iiimon Inc. All rights reserved.
 *
 * @author coolmoo <idreamsoft@qq.com>
 * @site http://www.idreamsoft.com
 * @licence http://www.idreamsoft.com/license.php
 * @version 6.0.0
 * @$Id: spider.app.php 156 2013-03-22 13:40:07Z coolmoo $
 */
//ini_set('memory_limit','512M');
class spiderApp {

    function __construct() {
        $this->cid   = (int) $_GET['cid'];
        $this->rid   = (int) $_GET['rid'];
        $this->pid   = (int) $_GET['pid'];
        $this->sid   = (int) $_GET['sid'];
        $this->poid  = (int) $_GET['poid'];
        $this->title = $_GET['title'];
        $this->url   = $_GET['url'];
    }
    function dobatch(){
        $idArray = (array)$_POST['id'];
        $idArray OR iPHP::alert("请选择要删除的项目");
        $ids     = implode(',',$idArray);
        $batch   = $_POST['batch'];
    	switch($batch){
    		case 'delurl':
				iDB::query("delete from `#iCMS@__spider_url` where `id` IN($ids);");
    		break;
    		case 'delpost':
				iDB::query("delete from `#iCMS@__spider_post` where `id` IN($ids);");
    		break;
    		case 'delproject':
				iDB::query("delete from `#iCMS@__spider_project` where `id` IN($ids);");
    		break;
    		case 'delrule':
 				iDB::query("delete from `#iCMS@__spider_rule` where `id` IN($ids);");
   			break;
		}
		iPHP::OK('全部删除完成!','js:1');
	}
    function dodelspider() {
    	$this->sid OR iPHP::alert("请选择要删除的项目");
        iDB::query("delete from `#iCMS@__spider_url` where `id` = '$this->sid';");
        iPHP::OK('删除完成','js:1');
    }
    
    function domanage($doType = null) {
        $this->category = iPHP::appClass("category",iCMS_APP_ARTICLE);
        $sql = " WHERE 1=1";
        $_GET['keywords'] && $sql.="  AND `title` REGEXP '{$_GET['keywords']}'";
        $doType == "inbox" && $sql.=" AND `publish` ='0'";
        $_GET['pid'] && $sql.=" AND `pid` ='" . (int) $_GET['pid'] . "'";
        $_GET['rid'] && $sql.=" AND `rid` ='" . (int) $_GET['rid'] . "'";
        $cid = $this->cid;
        if ($cid) {
            $cidIN = $this->category->cid($cid) . $cid;
            if (isset($_GET['sub']) && strstr($cidIN, ',')) {
                $sql.=" AND cid IN(" . $cidIN . ")";
            } else {
                $sql.=" AND cid ='$cid'";
            }
        }
        $ruleArray = $this->rule_opt(0, 'array');
        $postArray = $this->post_opt(0, 'array');
        $orderby = $_GET['orderby'] ? $_GET['orderby'] : "id DESC";
        $maxperpage = (int) $_GET['perpage'] > 0 ? $_GET['perpage'] : 20;
        $total = iPHP::total(false, "SELECT count(*) FROM `#iCMS@__spider_url` {$sql}", "G");
        iPHP::pagenav($total, $maxperpage, "个网页");
        $rs = iDB::getArray("SELECT * FROM `#iCMS@__spider_url` {$sql} order by {$orderby} LIMIT " . iPHP::$offset . " , {$maxperpage}");
        $_count = count($rs);
        include iACP::view("spider.manage");
    }

    function doinbox() {
        $this->domanage("inbox");
    }

    function dotestcont() {
        $this->contTest = true;
        $this->spider_content();
    }

    function dotestrule() {
        $this->ruleTest = true;
        $this->spider_url();
    }

    function dolistpub() {
        $this->spider_url('HM');
    }
    function dodropurl() {
    	$this->pid OR iPHP::alert("请选择要删除的项目");

    	$type	= $_GET['type'];
    	if($type=="0"){
    		$sql=" AND `publish`='0'";
    	}
        iDB::query("delete from `#iCMS@__spider_url` where `pid` = '$this->pid'{$sql};");
        iPHP::OK('数据清除完成');
    }
    function dostart() {
        $a	= $this->spider_url();
        $this->dompublish($a);
    }
	function dompublish($pubArray=array()){
		iPHP::$break	= false;
		if($_POST['pub']){
			foreach((array)$_POST['pub'] as $i=>$a){
				list($cid,$pid,$rid,$url,$title)= explode('|',$a);
				$pubArray[]= array('sid'=>0,'url'=>$url,'title'=>$title,'cid'=>$cid,'rid'=>$rid,'pid'=>$pid);
			}
		}
		if(empty($pubArray)){
			iPHP::$break		= true;
			iPHP::$dialogLock	= true;
			iPHP::alert('暂无最新内容',0,30);
		}
		ob_implicit_flush();
		$_count	= count($pubArray);
        foreach((array)$pubArray as $i=>$a){
            $this->sid   = $a['sid'];
            $this->cid   = $a['cid'];
            $this->pid   = $a['pid'];
            $this->rid   = $a['rid'];
            $this->url   = $a['url'];
            $this->title = $a['title'];
            $rs          = $this->multipublish();
            $updateMsg   = $i?true:false;
            $timeout     = ($i++)==$_count?'3':false;
			iPHP::dialog($rs['msg'], 'js:'.$rs['js'],$timeout,0,$updateMsg);
        	ob_end_flush();
		}
		iPHP::dialog('success:#:check:#:采集完成!',0,3,0,true);
	}
	function multipublish(){
		$a		= array();
		$code	= $this->dopublish('multi');
		//var_dump($pubRs,$pubRs==='-1');
		$code==='-1' && $label='<br /><span class="label label-warning">该URL的文章已经发布过!请检查是否重复</span>';
		$code===true && $label='<br /><span class="label label-success">发布成功!</span>';
		if($label){
			$a['msg']	= '标题:'.$this->title.'<br />URL:'.$this->url.$label.'<hr />';
			$a['js']	= 'parent.$("#' . md5($this->url) . '").remove();';
		}
		return $a;
	}
    function dopublish($work = null) {
        $sid = $this->sid;
        if ($sid) {
            $sRs = iDB::getRow("SELECT * FROM `#iCMS@__spider_url` WHERE `id`='$this->sid' LIMIT 1;");
            $this->title = $sRs->title;
            $this->url = $sRs->url;
        }
        $hash	= md5($this->url);
        $sid	= iDB::getValue("SELECT `id` FROM `#iCMS@__spider_url` where `hash` = '$hash' and `publish`='1'");
        $msg	= '该URL的文章已经发布过!请检查是否重复';
        if ($sid) {
            $work===NULL	&& iPHP::alert($msg.' [sid:'.$sid.']', 'js:parent.$("#' . $hash . '").remove();');
            if($work=='multi'){
	            return '-1';
            }
        }elseif($title) {
            if (iDB::getValue("SELECT `id` FROM `#iCMS@__article` where `title` = '$title'")) {
            	if($sid){
                	iDB::query("UPDATE `#iCMS@__spider_url` SET `publish` = '1' WHERE `id` = '$sid';");
                }else{
                	iDB::query("INSERT INTO `#iCMS@__spider_url` (`cid`, `rid`,`pid`, `hash`, `title`, `url`, `status`, `publish`, `addtime`, `pubdate`) VALUES ('$this->cid', '$this->rid','$this->pid','$hash','$this->title', '$this->url', '1', '1', '" . time() . "', '" . time() . "');");
                }
                $work===NULL	&& iPHP::alert($msg, 'js:parent.$("#' . $hash . '").remove();');
	            if($work=='multi'){
					return '-1';
	            }
            }
        }
		return $this->post($work);
    }

    function post($work = null) {
        $_POST        = $this->spider_content();      
        $pid          = $this->pid;
        $project      = $this->project($pid);
        $sleep        = $project['sleep'];
        $poid         = $project['poid'];       
        $_POST['cid'] = $project['cid'];
        $postRs       = iDB::getRow("SELECT * FROM `#iCMS@__spider_post` WHERE `id`='$poid' LIMIT 1;");
        if ($postRs->post) {
            $postArray = explode("\n", $postRs->post);
            $postArray = array_filter($postArray);
            foreach ($postArray AS $key => $pstr) {
                list($pkey, $pval) = explode("=", $pstr);
                $_POST[$pkey] = $pval;
            }
        }
        iS::slashes($_POST);
        $app      = iACP::app($postRs->app);
        $fun      = $postRs->fun;
        $callback = $app->$fun("1001");
        if ($callback['code'] == "1001") {
            if ($this->sid) {
                iDB::query("UPDATE `#iCMS@__spider_url` SET `publish` = '1', `pubdate` = '" . time() . "' WHERE `id` = '$this->sid';");
                $work===NULL	&& iPHP::OK("发布成功!",'js:1');
            } else {
                $hash    = md5($this->url);
                $title   = iS::escapeStr($_POST['title']);
                $url     = iS::escapeStr($_POST['reurl']);
                $indexId = $callback['indexId'];
                iDB::query("INSERT INTO `#iCMS@__spider_url` (`cid`, `rid`,`pid`,`indexId`, `hash`, `title`, `url`, `status`, `publish`, `addtime`, `pubdate`) VALUES ('$this->cid', '$this->rid','$pid','$indexId','$hash','$title', '$url', '1', '1', '" . time() . "', '" . time() . "');");
		        $work===NULL	&& iPHP::OK("发布成功!", 'js:parent.$("#' . $hash . '").remove();');
            }
        }
        if($work=="cmd"||$work=="multi"){
            $callback['work']=$work;
        	return $callback;
        }
    }

    function spider_url($work = NULL) {
        $pid = $this->pid;
        if ($pid) {
            $project = $this->project($pid);
            $cid = $project['cid'];
            $rid = $project['rid'];
            $prule_list_url = $project['list_url'];
        } else {
            $cid = $this->cid;
            $rid = $this->rid;
        }

        $ruleA = $this->rule($rid);
        $rule = $ruleA['rule'];
        $urls = $rule['list_urls'];
        $project['urls'] && $urls = $project['urls'];
        $urlsArray = explode("\n", $urls);
        $urlsArray = array_filter($urlsArray);
        $this->useragent = $rule['user_agent'];
        $this->charset = $rule['charset'];
        empty($urlsArray) && iPHP::alert('采集列表为空!请填写!', 'js:parent.window.iCMS_MODAL.destroy();');

//    	if($this->ruleTest){
//	    	echo "<pre>";
//	    	print_r(iS::escapeStr($project));
//	    	print_r(iS::escapeStr($rule));
//	    	echo "</pre>";
//	    	echo "<hr />";
//		}
        $pubArray = array();
        foreach ($urlsArray AS $key => $url) {
            $url = trim($url);
            if ($this->ruleTest) {
                echo $url . "<br />";
            }
            $html = $this->remote($url);

            $list_area_rule = $this->pregTag($rule['list_area_rule']);
            if ($list_area_rule) {
                preg_match('|' . $list_area_rule . '|is', $html, $matches, $PREG_SET_ORDER);
                $list_area = $matches['content'];
            } else {
                $list_area = $html;
            }
			$html = null;
            unset($html);
            
            if ($this->ruleTest) {
                echo iS::escapeStr($this->pregTag($rule['list_area_rule']));
//    			echo iS::escapeStr($list_area);
                echo "<hr />";
            }
            if ($rule['list_area_format']) {
                $list_area = $this->dataClean($data['list_area_format'], $list_area);
            }
            if ($this->ruleTest) {
                echo iS::escapeStr($this->pregTag($rule['list_area_format']));
//              echo iS::escapeStr($list_area);
                echo "<hr />";
            }
            
            preg_match_all('|' . $this->pregTag($rule['list_url_rule']) . '|is', $list_area, $lists, PREG_SET_ORDER);

			$list_area = null;
            unset($list_area);

            if ($rule['sort'] == "1") {
                //arsort($lists);
            } elseif ($rule['sort'] == "2") {
                asort($lists);
            } elseif ($rule['sort'] == "3") {
                shuffle($lists);
            }

            if ($this->ruleTest) {
                echo iS::escapeStr($this->pregTag($rule['list_url_rule']));
                echo iS::escapeStr($rule['list_url']);
                echo "<hr />";
            }
			if($prule_list_url){
				$rule['list_url']	= $prule_list_url;
			}
            if ($work) {
                $listsArray[$url] = $lists;
            } else {
                foreach ($lists AS $lkey => $row) {
                    $title = $row['title'];
                    $title = preg_replace('/<[\/\!]*?[^<>]*?>/is', '', $title);
                    $url   = str_replace('<%url%>', $row['url'], $rule['list_url']);
                    $hash  = md5($url);

                    if ($this->ruleTest) {
                        echo $title . ' (<a href="' . APP_URI . '&do=testcont&url=' . $url . '&rid=' . $rid . '&pid=' . $pid . '" target="_blank">测试内容规则</a>) <br />';
                        echo $url . "<br />";
                        echo $hash . "<br /><br />";
                    } else {
                        //iDB::query("INSERT INTO `#iCMS@__spider_url` (`cid`, `rid`,`pid`, `hash`, `title`, `url`, `status`, `publish`, `addtime`, `pubdate`) VALUES ('$cid', '$rid','$pid','$hash','$title', '$url', '0', '0', '" . time() . "', '0');");
                        $this->checkurl($hash) OR $pubArray[]	=array('sid'=>iDB::$insert_id,'url'=>$url,'title'=>$title,'cid'=>$cid,'rid'=>$rid,'pid'=>$pid,'hash'=>$hash);
                    }
                }
            }
        }
        if(!$work){
            return $pubArray;
        }
		$lists = null;
        unset($lists);
		gc_collect_cycles();
        if ($work) {
            $sArrayTmp = iDB::getArray("SELECT `hash` FROM `#iCMS@__spider_url` where `pid`='$pid'");
            $_count = count($sArrayTmp);
            for ($i = 0; $i < $_count; $i++) {
                $sArray[$sArrayTmp[$i]['hash']] = 1;
            }
			if($work=="cmd"){
				$urlArray	= array();
				foreach ($listsArray AS $furl => $lists) {
					foreach ($lists AS $lkey => $row) {
						$url 	= str_replace('<%url%>', $row['url'], $rule['list_url']);
						$hash 	= md5($url);
						if(!$sArray[$hash]){
							//$urlArray[]= $url;
                            $this->rid = $rid;
                            $this->url = $url;
                            $callback  = $this->post("cmd");                            
							if ($callback['code'] == "1001") {
								echo "url:".$url."\n";
								if($project['sleep']){
									echo "sleep:".$project['sleep']."s\n";
									unset($lists[$lkey]);
									gc_collect_cycles();
									sleep($project['sleep']);
								}else{
									sleep(1);
								}
							}else{
								die("error");
							}
						}
					}
				}
				return $urlArray;
			}
            include iACP::view("spider.lists");
        }
    }

    function spider_content() {
		ini_get('safe_mode') OR set_time_limit(0); 
        $sid = $this->sid;
        if ($sid) {
            $sRs   = iDB::getRow("SELECT * FROM `#iCMS@__spider_url` WHERE `id`='$sid' LIMIT 1;");
            $title = $sRs->title;
            $cid   = $sRs->cid;
            $pid   = $sRs->pid;
            $url   = $sRs->url;
            $rid   = $sRs->rid;
       } else {
            $rid   = $this->rid;
            $pid   = $this->pid;
            $title = $this->title;
            $url   = $this->url;
        }
		if($pid){
            $project        = $this->project($pid);
            $prule_list_url = $project['list_url'];
		}

        $ruleA           = $this->rule($rid);
        $rule            = $ruleA['rule'];
        $dataArray       = $rule['data'];
        $this->useragent = $rule['user_agent'];
        $this->charset   = $rule['charset'];
        
		if($prule_list_url){
			$rule['list_url']	= $prule_list_url;
		}

        if ($this->contTest) {
            echo "<pre>";
            print_r(iS::escapeStr($ruleA));
            print_r(iS::escapeStr($$project));
            echo "</pre><hr />";
        }

        $responses = array();
        $html = $this->remote($url);
        empty($html) && iPHP::alert('错误:001..采集 ' . $url . ' 文件内容为空!请检查采集规则');
//    	$http	= $this->check_content_code($html);
//    	
//    	if($http['match']==false){
//    		return false;
//    	}
//		$content		= $http['content'];
        $this->allHtml = "";
        $responses['reurl'] = $url;
        $rule['__url__']	= $url;
        foreach ($dataArray AS $key => $data) {
            $responses[$data['name']] = $this->content($html,$data,$rule);
            if($data['name']=='title' && empty($responses['title'])){
            	$responses['title'] = $title;
            }
        }
		$html = null;
        unset($html);
        gc_collect_cycles();
        if ($this->contTest) {
            echo "<pre style='width:99%;word-wrap: break-word;'>";
            print_r(iS::escapeStr($responses));
            echo "</pre><hr />";
        }
        return $responses;
    }

    function content($html,$data,$rule) {
        $name = $data['name'];
        if ($data['page']) {
        	if(empty($rule['page_url'])){
        		$rule['page_url']=$rule['list_url'];
        	}
            if (empty($this->allHtml)) {
		        $page_area_rule = $this->pregTag($rule['page_area_rule']);
		        if ($page_area_rule) {
		            preg_match('|' . $page_area_rule . '|is', $html, $matches, $PREG_SET_ORDER);
		            $page_area = $matches['content'];
		        } else {
		            $page_area = $html;
		        }
            	if($rule['page_url_rule']){
            		$page_url_rule = $this->pregTag($rule['page_url_rule']);
            		preg_match_all('|' .$page_url_rule. '|is', $page_area, $page_url_matches, PREG_SET_ORDER);
            		foreach ($page_url_matches AS $pn => $row) {
            			$page_url_array[$pn] = str_replace('<%url%>', $row['url'], $rule['page_url']);
            			gc_collect_cycles();
            		}
            	}else{
            		$page_url_rule = $this->pregTag($rule['page_url_parse']);
					preg_match('|' . $page_url_rule . '|is', $rule['__url__'], $matches, $PREG_SET_ORDER);
			        $page_url = str_replace('<%url%>', $matches['url'], $rule['page_url']);
			        $page_url_array	= array();
			        for ($pn = $rule['page_no_start']; $pn <= $rule['page_no_end']; $pn = $pn + $rule['page_no_step']) {
			            $page_url_array[$pn] = str_replace('<%step%>', $pn, $page_url);
			            gc_collect_cycles();
			        }
            	}
				unset($page_area);
		        if ($this->contTest) {
		            echo $rule['__url__'] . "<br />";
		            echo $rule['page_url'] . "<br />";
		            echo iS::escapeStr($page_url_rule);
		            echo "<hr />";
		        }
				if($this->contTest){
					echo "<pre>";
					print_r($page_url_array);
					echo "</pre><hr />";
				}
		        $this->content_right_code = $rule['page_url_right'];
		        $this->content_error_code = $rule['page_url_error'];

                $pcontent = '';
                $pcon     = '';
                foreach ($page_url_array AS $pukey => $purl) {
                    usleep(100);
                    $phtml = $this->remote($purl);
                    if ($phtml === false) {
                        break;
                    }
                    $phttp = $this->check_content_code($phtml);

                    if ($phttp['match'] == false) {
                        break;
                    }

                    $pageurl[] = $purl;
                    $pcon.= $phttp['content'];
                }
                $html.= $pcon;

                if ($this->contTest) {
                    echo "<pre>";
                    print_r($pageurl);
                    echo "</pre><hr />";
                }
            }else{
                $html = $this->allHtml;
            }
        }

        $rule = $this->pregTag($data['rule']);
        if ($this->contTest) {
            print_r(iS::escapeStr($rule));
            echo "<hr />";
        }
        if (preg_match('/(<\w+>|\.\*|\.\+|\\\d|\\\w)/i', $rule)) {
            if ($data['multi']) {
                preg_match_all('|' . $rule . '|is', $html, $matches, PREG_SET_ORDER);
                $conArray = array();
                foreach ((array) $matches AS $mkey => $mat) {
                    $conArray[] = $mat['content'];
                }
                $content = implode('#--iCMS.PageBreak--#', $conArray);
            } else {
                preg_match('|' . $rule . '|is', $html, $matches, $PREG_SET_ORDER);
                $content = $matches['content'];
            }
        } else {
            $content = $rule;
        }
		$html = null;
        unset($html);
        
        if ($data['cleanbefor']) {
            $content = $this->dataClean($data['cleanbefor'], $content);
        }
        if ($data['cleanhtml']) {
            $content = preg_replace('/<[\/\!]*?[^<>]*?>/is', '', $content);
        }
        if ($data['format'] && $content) {
            $content = autoformat2($content);
            $content = stripslashes($content);
        }

        $data['trim'] && $content = trim($content);

        if ($data['json_decode']) {
            $content = preg_replace('/&#\d{2,5};/ue', "utf8_entity_decode('\\0')", $content);
            $content = preg_replace(array('/&#x([a-fA-F0-7]{2,8});/ue', '/%u([a-fA-F0-7]{2,8})/ue', '/\\\u([a-fA-F0-7]{2,8})/ue'), "utf8_entity_decode('&#'.hexdec('\\1').';')", $content);
            $content = htmlspecialchars_decode($content);
        }
        if ($data['cleanafter']) {
            $content = $this->dataClean($data['cleanafter'], $content);
        }
        if ($data['mergepage']) {
            $_content = $content;
            preg_match_all("/<img.*?src\s*=[\"|'|\s]*(http:\/\/.*?\.(gif|jpg|jpeg|bmp|png)).*?>/is", $_content, $picArray);
            $pA = array_unique($picArray[1]);
            $pA = array_filter($pA);
            $_pcount = count($pA);
            if ($_pcount < 4) {
                $content = str_replace('#--iCMS.PageBreak--#', "", $content);
            } else {
                $contentA = explode("#--iCMS.PageBreak--#", $_content);
                $newcontent = array();
                $this->checkpage($newcontent, $contentA, 2);
                if (is_array($newcontent)) {
                    $content = array_filter($newcontent);
                    $content = implode('#--iCMS.PageBreak--#', $content);
                    //$content		= addslashes($content);
                } else {
                    //$content		= addslashes($newcontent);
                    $content = $newcontent;
                }
            }
        }

        if ($data['empty'] && empty($content)) {
            iPHP::alert($name . '内容为空!请检查,规则是否正确!!');
        }
        if($data['array']){
        	return	array($content);
        }
        return $content;
    }

    function dataClean($rules, $string) {
        $ruleArray = explode("\n", $rules);
        foreach ($ruleArray AS $key => $rule) {
            list($_pattern, $_replacement) = explode("==", $rule);
            $pattern[$key] = '|' . $this->pregTag($_pattern) . '|is';
            $replacement[$key] = $_replacement;
        }
        return preg_replace($pattern, $replacement, $string);
    }

    function checkurl($hash) {
        $id = iDB::getValue("SELECT `id` FROM `#iCMS@__spider_url` WHERE `hash`='$hash'");
        return $id ? true : false;
    }

    function pregTag($rule) {
        $rule = trim($rule);
        $rule = str_replace("%>", "%>\n", $rule);
        preg_match_all("/<%(.+)%>/i", $rule, $matches);
        $pregArray = array_unique($matches[0]);
        $pregflip = array_flip($pregArray);

        foreach ((array)$pregflip AS $kpreg => $vkey) {
            $pregA[$vkey] = "###iCMS_PREG_" . rand(1, 1000) . '_' . $vkey . '###';
        }
        $rule = str_replace($pregArray, $pregA, $rule);
        $rule = preg_quote($rule, '|');
        $rule = str_replace($pregA, $pregArray, $rule);
        $rule = str_replace("%>\n", "%>", $rule);
        $rule = preg_replace('|<%(\w{3,20})%>|i', '(?<\\1>.*?)', $rule);
        $rule = str_replace(array('<%', '%>'), '', $rule);
        return $rule;
    }

    function rule($id) {
        $rs = iDB::getRow("SELECT * FROM `#iCMS@__spider_rule` WHERE `id`='$id' LIMIT 1;", ARRAY_A);
        $rs['rule'] && $rs['rule'] = stripslashes_deep(unserialize($rs['rule']));
        $rs['user_agent'] OR $rs['user_agent'] = "Mozilla/5.0 (compatible; Baiduspider/2.0; +http://www.baidu.com/search/spider.html)";
        $this->useragent = $rs['user_agent'];
        return $rs;
    }

    function dorule() {
        if ($_GET['keywords']) {
            $sql = " WHERE `keyword` REGEXP '{$_GET['keywords']}'";
        }
        $orderby = $_GET['orderby'] ? $_GET['orderby'] : "id DESC";
        $maxperpage = (int) $_GET['perpage'] > 0 ? $_GET['perpage'] : 20;
        $total = iPHP::total(false, "SELECT count(*) FROM `#iCMS@__spider_rule` {$sql}", "G");
        iPHP::pagenav($total, $maxperpage, "个规则");
        $rs = iDB::getArray("SELECT * FROM `#iCMS@__spider_rule` {$sql} order by {$orderby} LIMIT " . iPHP::$offset . " , {$maxperpage}");
        $_count = count($rs);
        include iACP::view("spider.rule");
    }

    function docopyrule() {
        iDB::query("insert into `#iCMS@__spider_rule` (`name`, `rule`) select `name`, `rule` from `#iCMS@__spider_rule` where id = '$this->rid'");
        $rid = iDB::$insert_id;
        iPHP::OK('复制完成,编辑此规则', 'url:' . APP_URI . '&do=addrule&rid=' . $rid);
    }

    function dodelrule() {
    	$this->rid OR iPHP::alert("请选择要删除的项目");
        iDB::query("delete from `#iCMS@__spider_rule` where `id` = '$this->rid';");
        iPHP::OK('删除完成','js:1');
    }

    function doaddrule() {
        $rs = array();
        $this->rid && $rs = $this->rule($this->rid);
        $rs['rule'] && $rule = $rs['rule'];
        if (empty($rule['data'])) {
            $rule['data'] = array(
                array('name' => 'title', 'trim' => true, 'empty' => true),
                array('name' => 'body', 'trim' => true, 'empty' => true, 'format' => true, 'page' => true, 'multi' => true),
            );
        }
        $rule['sort'] OR $rule['sort'] = 1;
        include iACP::view("spider.addrule");
    }

    function dosaverule() {
        $id = (int) $_POST['id'];
        $name = iS::escapeStr($_POST['name']);
        $rule = $_POST['rule'];

        empty($name) && iPHP::alert('规则名称不能为空！');
        //empty($rule['list_area_rule']) 	&& iPHP::alert('列表区域规则不能为空！');
        empty($rule['list_url_rule']) && iPHP::alert('列表链接规则不能为空！');

        $rule = addslashes(serialize($rule));
        if ($id) {
            iDB::query("UPDATE `#iCMS@__spider_rule` SET `name` = '$name', `rule` = '$rule' WHERE `id` = '$id';");
        } else {
            iDB::query("INSERT INTO `#iCMS@__spider_rule`(`name`, `rule`) VALUES ('$name', '$rule');");
        }
        iPHP::OK('保存成功','js:1');
    }

    function rule_opt($id = 0, $output = null) {
        $rs = iDB::getArray("SELECT * FROM `#iCMS@__spider_rule` order by id desc");
        foreach ((array)$rs AS $rule) {
            $rArray[$rule['id']] = $rule['name'];
            $opt.="<option value='{$rule['id']}'" . ($id == $rule['id'] ? " selected='selected'" : '') . ">{$rule['name']}[id='{$rule['id']}'] </option>";
        }
        if ($output == 'array') {
            return $rArray;
        }
        return $opt;
    }

    function dopost() {
        if ($_GET['keywords']) {
            $sql = " WHERE `keyword` REGEXP '{$_GET['keywords']}'";
        }
        $orderby = $_GET['orderby'] ? $_GET['orderby'] : "id DESC";
        $maxperpage = (int) $_GET['perpage'] > 0 ? $_GET['perpage'] : 20;
        $total = iPHP::total(false, "SELECT count(*) FROM `#iCMS@__spider_post` {$sql}", "G");
        iPHP::pagenav($total, $maxperpage, "个模块");
        $rs = iDB::getArray("SELECT * FROM `#iCMS@__spider_post` {$sql} order by {$orderby} LIMIT " . iPHP::$offset . " , {$maxperpage}");
        $_count = count($rs);
        include iACP::view("spider.post");
    }
    function dodelpost() {
    	$this->poid OR iPHP::alert("请选择要删除的项目");
        iDB::query("delete from `#iCMS@__spider_post` where `id` = '$this->poid';");
        iPHP::OK('删除完成','js:1');
    }
    function doaddpost() {
        $this->poid && $rs = iDB::getRow("SELECT * FROM `#iCMS@__spider_post` WHERE `id`='$this->poid' LIMIT 1;", ARRAY_A);
        include iACP::view("spider.addpost");
    }

    function dosavepost() {
        $id = (int) $_POST['id'];
        $name = trim($_POST['name']);
        $app = iS::escapeStr($_POST['app']);
        $post = trim($_POST['post']);
        $fun = trim($_POST['fun']);

        if ($id) {
            iDB::query("UPDATE `#iCMS@__spider_post` SET `name` = '$name',`app` = '$app',`fun` = '$fun', `post` = '$post' WHERE `id` = '$id';");
        } else {
            iDB::query("INSERT INTO `#iCMS@__spider_post`(`name`,`app`,`fun`, `post`) VALUES ('$name','$app','$fun', '$post');");
        }
        iPHP::OK('保存成功', 'url:' . APP_URI . '&do=post');
    }

    function post_opt($id = 0, $output = null) {
        $rs = iDB::getArray("SELECT * FROM `#iCMS@__spider_post`");
        foreach ((array)$rs AS $post) {
        	$pArray[$post['id']] = $post['name'];
            $opt.="<option value='{$post['id']}'" . ($id == $post['id'] ? " selected='selected'" : '') . ">{$post['name']}:{$post['app']}[id='{$post['id']}'] </option>";
        }
        if ($output == 'array') {
            return $pArray;
        }
        return $opt;
    }

    function project($id) {
        return iDB::getRow("SELECT * FROM `#iCMS@__spider_project` WHERE `id`='$id' LIMIT 1;", ARRAY_A);
    }

    function docopyproject() {
        iDB::query("INSERT INTO `#iCMS@__spider_project` (`name`, `urls`, `cid`, `rid`, `poid`, `sleep`) select `name`, `urls`, `cid`, `rid`, `poid`, `sleep` from `#iCMS@__spider_project` where id = '$this->pid'");
        $pid = iDB::$insert_id;
        iPHP::OK('复制完成,编辑此方案', 'url:' . APP_URI . '&do=addproject&pid=' . $pid.'&copy=1');
    }

    function doproject() {
        $this->category = iPHP::appClass("category",iCMS_APP_ARTICLE);
        $sql = "where 1=1";
        if ($_GET['keywords']) {
            $sql.= " and `keyword` REGEXP '{$_GET['keywords']}'";
        }
        $cid = $this->cid;
        if ($cid) {
            $cidIN = $this->category->cid($cid) . $cid;
            if (isset($_GET['sub']) && strstr($cidIN, ',')) {
                $sql.=" AND cid IN(" . $cidIN . ")";
            } else {
                $sql.=" AND cid ='$cid'";
            }
        }
        if ($_GET['rid']) {
            $sql.=" AND `rid` ='" . (int) $_GET['rid'] . "'";
        }
        if ($_GET['poid']) {
            $sql.=" AND `poid` ='" . (int) $_GET['poid'] . "'";
        }
        $ruleArray = $this->rule_opt(0, 'array');
        $postArray = $this->post_opt(0, 'array');
        $orderby = $_GET['orderby'] ? $_GET['orderby'] : "id DESC";
        $maxperpage = (int) $_GET['perpage'] > 0 ? $_GET['perpage'] : 20;
        $total = iPHP::total(false, "SELECT count(*) FROM `#iCMS@__spider_project` {$sql}", "G");
        iPHP::pagenav($total, $maxperpage, "个方案");
        $rs = iDB::getArray("SELECT * FROM `#iCMS@__spider_project` {$sql} order by {$orderby} LIMIT " . iPHP::$offset . " , {$maxperpage}");
        $_count = count($rs);
        include iACP::view("spider.project");
    }
    function dodelproject() {
    	$this->pid OR iPHP::alert("请选择要删除的项目");
        iDB::query("delete from `#iCMS@__spider_project` where `id` = '$this->pid';");
        iPHP::OK('删除完成');
    }
    function doaddproject() {
        $rs = array();
        $this->pid && $rs = $this->project($this->pid);
        $cid = empty($rs['cid']) ? $this->cid : $rs['cid'];

        $this->category = iPHP::appClass("category",iCMS_APP_ARTICLE);
        $cata_option = $this->category->select($cid, 0, 1, 0);
        $rule_option = $this->rule_opt($rs['rid']);
        $post_option = $this->post_opt($rs['poid']);

        $rs['sleep'] OR $rs['sleep'] = 30;
        include iACP::view("spider.addproject");
    }

    function dosaveproject() {
        $id = (int) $_POST['id'];
        $name = iS::escapeStr($_POST['name']);
        $urls = iS::escapeStr($_POST['urls']);
        $list_url = $_POST['list_url'];
        $cid = iS::escapeStr($_POST['cid']);
        $rid = iS::escapeStr($_POST['rid']);
        $poid = iS::escapeStr($_POST['poid']);
        $sleep = iS::escapeStr($_POST['sleep']);
        $auto = iS::escapeStr($_POST['auto']);

        empty($name) && iPHP::alert('名称不能为空！');
        empty($cid) && iPHP::alert('请选择绑定的栏目');
        empty($rid) && iPHP::alert('请选择采集规则');
        //empty($poid)	&& iPHP::alert('请选择发布规则');
        if ($id) {
            iDB::query("UPDATE `#iCMS@__spider_project` 
SET `name` = '$name', `urls` = '$urls', `list_url` = '$list_url',`cid` = '$cid', `rid` = '$rid', `poid` = '$poid', `sleep` = '$sleep', `auto` = '$auto'
WHERE `id` = '$id';");
        } else {
            iDB::query("INSERT INTO `#iCMS@__spider_project` (`name`, `urls`,`list_url`, `cid`, `rid`, `poid`, `sleep`, `auto`) VALUES ('$name', '$urls','$list_url', '$cid', '$rid', '$poid', '$sleep', '$auto');");
        }
        iPHP::OK('完成', 'url:' . APP_URI . '&do=project');
    }

    function remote($url, $_referer = false, $_count = 0) {
        $uri = parse_url($url);
        $curlopt_referer = $uri['scheme'] . '://' . $uri['host'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_REFERER, $_referer ? $_referer : $curlopt_referer);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->useragent);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_NOSIGNAL, true);
        $responses = curl_exec($ch);
        $info = curl_getinfo($ch);
        if ($this->contTest || $this->ruleTest) {
            echo '<pre>';
            print_r($info);
            echo '</pre><hr />';
            if($_GET['breakinfo']){
            	exit();
            }
        }
        if ($info['http_code'] == 301 || $info['http_code'] == 302) {
            $newurl = $info['redirect_url'];
	        if(empty($newurl)){
		    	curl_setopt($ch, CURLOPT_HEADER, 1);
		    	$header		= curl_exec($ch);
		    	preg_match ('|Location: (.*)|i',$header,$matches);
		    	$newurl 	= ltrim($matches[1],'/');
			    if(empty($newurl)) return false;
			    
		    	if(!strstr($newurl,'http://')){
			    	$host	= $uri['scheme'].'://'.$uri['host'];
		    		$newurl = $host.'/'.$newurl;
		    	}
	        }
	        $newurl	= trim($newurl);
			curl_close($ch);
			unset($responses,$info);
            return $this->remote($url, $_referer, $_count);
        }
        if ($info['http_code'] == 404 || $info['http_code'] == 500) {
			curl_close($ch);
			unset($responses,$info);
            return false;
        }

        if ((empty($responses)||empty($info['http_code'])) && $_count < 5) {
            $_count++;
            if ($this->contTest || $this->ruleTest) {
                echo $url . '<br />';
                echo "获取内容失败,重试第{$_count}次...<br />";
            }
			curl_close($ch);
			unset($responses,$info);
            return $this->remote($url, $_referer, $_count);
        }
        if ($this->charset == "auto") {
            $encode = mb_detect_encoding($responses, array("ASCII","UTF-8","GB2312","GBK","BIG5")); 
            $encode!='UTF-8' && $responses = mb_convert_encoding($responses,"UTF-8",$encode);
        } elseif ($this->charset == "gbk") {
            $responses = mb_convert_encoding($responses, "UTF-8", "gbk");
        }
		curl_close($ch);
		unset($info);
        return $responses;
    }

    function check_content_code($content, $delay = 0) {
        $encode = mb_detect_encoding($content, array("ASCII","UTF-8","GB2312","GBK","BIG5")); 
        $encode!='UTF-8' && $content = mb_convert_encoding($content,"UTF-8",$encode);
//	    $page_right_rule	= $this->pregTag($this->page_right_code);
//	    preg_match('|'.$page_right_rule.'|is', $data, $matches);
        if ($this->content_right_code) {
	        $matches = strpos($content, $this->content_right_code);
	        if (empty($matches)) {
	            $match = false;
	            return false;
	        }
        }
        if ($this->content_error_code) {
            $_matches = strpos($content, $this->content_error_code);
            if ($_matches) {
                $match = false;
                return false;
            }
        }
        $match = true;
        usleep(10);
        return compact('content', 'match');
    }

    function checkpage(&$newbody, $bodyA, $_count = 1, $nbody = "", $i = 0, $k = 0) {
        $ac = count($bodyA);
        $nbody.= $bodyA[$i];
        preg_match_all("/<img.*?src\s*=[\"|'|\s]*(http:\/\/.*?\.(gif|jpg|jpeg|bmp|png)).*?>/is", $nbody, $picArray);
        $pA = array_unique($picArray[1]);
        $pA = array_filter($pA);
        $_pcount = count($pA);
        //	print_r($_pcount);
        //	echo "\n";
        //	print_r('_count:'.$_count);
        //	echo "\n";
        //	var_dump($_pcount>$_count);
        if ($_pcount >= $_count) {
            $newbody[$k] = $nbody;
            $k++;
            $nbody = "";
        }
        $ni = $i + 1;
        if ($ni <= $ac) {
            $this->checkpage($newbody, $bodyA, $_count, $nbody, $ni, $k);
        } else {
            $newbody[$k] = $nbody;
        }
    }

}

function stripslashes_deep($value) {
    $value = is_array($value) ?
            array_map('stripslashes_deep', $value) :
            stripslashes($value);

    return $value;
}

function str_cut($str, $start, $end) {
    $content = strstr($str, $start);
    $content = substr($content, strlen($start), strpos($content, $end) - strlen($start));
    return $content;
}

function utf8_entity_decode($entity) {
    $convmap = array(0x0, 0x10000, 0, 0xfffff);
    return mb_decode_numericentity($entity, $convmap, 'UTF-8');
}