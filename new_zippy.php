<?php
	/*
		This file builds the download URL for ZippyShare.
		- It solves the computation challenge without the usage of eval.
		- Resistant against whitespace changes
		- Resistant against function name changes
		- Resistant against operator changes (will not follow order of operands)
	*/
	
	function doCalc($a,$symbol,$b){
		switch($symbol){
			case '+':
				return $a+$b;
			case '-':
				return $a-$b;
			case '*':
				return $a*$b;
			case '/':
				return $a/$b;
			case '%':
				return $a%$b;
		}
		return FALSE;
	}

	function getUrl($data){
		$func=array();
		$regex=array(
			//class attribute
			'CLASS'       =>'#class="(\d+)"#',
			//constant function
			'CONST'       =>'#var\s*(\w+)\s*=\s*function\s*\(\)\s*{\s*return\s*(\d+)\s*;?\s*}#',
			//function with dependency
			'JS_FUNC'     =>'#var\s*(\w+)\s*=\s*function\s*\(\)\s*{\s*return\s*(\w+)\(\)\s*(.)\s*(\d+)\s*;?\s*}#',
			//variable that holds class attribute value
			'CLASS_RESULT'=>'#var\s*(\w+)\s*=\s*document\.getElementById\([\'"]\w+[\'"]\)\.getAttribute\([\'"]class[\'"]\);?#',
			//inline constant calculation
			'INLINE_CALC' =>'#if\s*\(\s*true\s*\)\s*{\s*(\w+)\s*=\s*(\w+)\s*(.)\s*(\d+)\s*;?\s*}#',
			//challenge calculation
			'CHALLENGE'   =>'#\((\d+)\s*(.)\s*(\d+)\s*(.)\s*(\w+)\(?\)?\s*(.)\s*(\w+)\(?\)?\s*(.)\s*(\w+)\(?\)?\s*(.)\s*(\w+)\(?\)?\s*(.)\s*(\d+)(.)(\d+)\)#',
			//file ID part (could also be extracted from URL since the first part is always /d/ as of now
			'FILE_ID'     =>'#(/\w+/\w+/)"#',
			//file name (doesn't uses title attribute which sometimes is missing)
			'FILE_NAME'   =>'#"(/[^+]+)"\s*;#'
			);
	
		//Get the value stored in the class attribute. There is only one class with just digits
		if(preg_match($regex['CLASS'],$data,$matches)){
			$func['class']=+$matches[1];
		}
		else{
			die('Unable to extract class attribute');
		}
		
		//Get functions that return a constant
		if(preg_match_all($regex['CONST'],$data,$matches))
		{
			for($i=0;$i<count($matches[0]);$i++){
				$func[$matches[1][$i]]=+$matches[2][$i];
			}
		}
		else{
			die('No individual JS functions found');
		}
		
		//Get functions that return the result of another function
		if(preg_match_all($regex['JS_FUNC'],$data,$matches))
		{
			for($i=0;$i<count($matches[0]);$i++){
				$result=doCalc(+$func[$matches[2][$i]],$matches[3][$i],+$matches[4][$i]);
				if($result===FALSE){
					die('Invalid Operator in JS function: ' . $matches[3][$i]);
				}
				$func[$matches[1][$i]]=$result;
			}
		}
		else{
			die('No combined JS functions found');
		}
		
		//Get variable that holds the class value
		if(preg_match_all($regex['CLASS_RESULT'],$data,$matches)){
			for($i=0;$i<count($matches[0]);$i++){
				$func[$matches[1][$i]]=+$func['class'];
			}
		}
		else{
			die('Unable to find inline class calculation');
		}
		
		//Get inline calculation
		if(preg_match_all($regex['INLINE_CALC'],$data,$matches)){
			for($i=0;$i<count($matches[0]);$i++){
				$result=doCalc(+$func[$matches[2][$i]],$matches[3][$i],+$matches[4][$i]);
				if($result===FALSE){
					die('Invalid Operator in inline function: ' . $matches[3][$i]);
				}
				$func[$matches[1][$i]]=+$result;
			}
		}
		else{
			die('Unable to find inline calculation');
		}
		
		//Calculate number
		if(preg_match($regex['CHALLENGE'],$data,$matches)){
			//Mod challenge
			$result=doCalc(+$matches[1],$matches[2],+$matches[3]);
			//a
			$result=doCalc($result,$matches[4],+$func[$matches[5]]);
			//b
			$result=doCalc($result,$matches[6],+$func[$matches[7]]);
			//c
			$result=doCalc($result,$matches[8],+$func[$matches[9]]);
			//d
			$result=doCalc($result,$matches[10],+$func[$matches[11]]);
			//const
			$result=doCalc($result,$matches[12],doCalc(+$matches[13],$matches[14],+$matches[15]));
			
			$func['result']=$result;
		}
		
		//Get Id
		if(preg_match($regex['FILE_ID'],$data,$matches)){
			$func['file_id']=$matches[1];
		}
		else{
			die('Unable to extract Id');
		}
		//Get File name
		if(preg_match($regex['FILE_NAME'],$data,$matches)){
			$func['file_name']=$matches[1];
		}
		else{
			die('Unable to extract Id');
		}
		
		//Build url
		$func['file_path']=$func['file_id'] . $func['result'] . $func['file_name'];
		return $func;
	}
	
	function getFullUrl($url){
		if(preg_match('#^https?://([^/]+)/(\w+)/(\w+)/file\.html$#',$url,$matches)){
			$data=file_get_contents($url);
			$result=getUrl($data);
			$result['file_url']='https://' . $matches[1] . $result['file_path'];
			return $result;
		}
		die('Invalid URL');
	}
	
	//$result=getFullUrl('https://www27.zippyshare.com/v/0rvw38E1/file.html');
	//print_r($result);
	