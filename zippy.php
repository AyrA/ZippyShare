<?php
	//Constants for debugging
	//These are usually defined by the downloader but we need them too for standalone debugging.
	//The values of most constants actually matches what Synology would use as of Sept 2018
	if (!defined('DOWNLOAD_STATION_USER_AGENT')) {
		define('DOWNLOAD_STATION_USER_AGENT', 'Mozilla/5.0 (Windows NT 6.1; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/68.0.3440.106 Safari/537.36');
	}
	if(!defined('DOWNLOAD_ISPARALLELDOWNLOAD')){
		define('DOWNLOAD_ISPARALLELDOWNLOAD','isparalleldownload');
	}
	if(!defined('DOWNLOAD_ERROR')){
		define('DOWNLOAD_ERROR','error');
	}
	if(!defined('DOWNLOAD_URL')){
		define('DOWNLOAD_URL','downloadurl');
	}
	if(!defined('DOWNLOAD_FILENAME')){
		define('DOWNLOAD_FILENAME','filename');
	}
	//Error codes
	if(!defined('ERR_NOT_SUPPORT_TYPE')){
		define('ERR_NOT_SUPPORT_TYPE',116);
	}
	if(!defined('ERR_FILE_NO_EXIST')){
		define('ERR_FILE_NO_EXIST',114);
	}
	if(!defined('ERR_TRY_IT_LATER')){
		define('ERR_TRY_IT_LATER',125);
	}
	if(!defined('ERR_INVALID_FILEHOST')){
		define('ERR_INVALID_FILEHOST',3);
	}
	if(!defined('ERR_UNKNOWN')){
		define('ERR_UNKNOWN',1);
	}
	if(!defined('USER_IS_FREE')){
		define('USER_IS_FREE',5);
	}

	//Debug features. Set to TRUE to allow running this from console
	define('ZIPPY_ALLOW_DEBUG',TRUE);
	//Set to TRUE to log function calls to /tmp/zippyerr
	define('ZIPPY_PRINT_DEBUG',FALSE);
	//Regex to capture the initial file URL
	define('REGEX_URL','#(www\d+).zippyshare.com/v/([^/]+)/file.html#i');
	//Regex to extract the file name from JS source code
	define('REGEX_FILENAME','#[^=]"/([^?</"]+)"#i');
	define('REGEX_FILENAME_NEW','#title"\s+content="([^?</"]+)"#i');
	//Regex to extract the mod challenge
	define('REGEX_MOD','#\((-?\d+)\s*%\s*(-?\d+)\s*\+\s*(-?\d+)\s*%\s*(-?\d+)\)#');
	//Regex to check for the "file not found" text
	define('REGEX_NOTFOUND','#>File does not exist on this server<#i');
	//The regex below gets the file name from the title.
	//This sometimes failed in the past so we no longer use it,
	//but it's still here in case the other method fails and we need it.
	//define('REGEX_FILENAME','#<title>ZippyShare.com - ([^<]+)#i');
	//This allows you to use a custom CA set for curl in case it can't read the system one
	define('CUSTOM_CA',PHP_OS==='WINNT'?'C:/Bat/curl/curl-ca-bundle.crt':FALSE);

	class ZippyHost {
		private $Url;
		private $Username;
		private $Password;
		private $HostInfo;

		public function __construct($Url, $Username, $Password, $HostInfo) {
			ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"__construct('$Url', ... , ... , ...)\n",FILE_APPEND);
			$this->Url = $Url;
			//Since ZippyShare is free user mode only, we only care about the URL
			//We still store the parameters for now
			$this->Username = $Username;
			$this->Password = $Password;
			$this->HostInfo = $HostInfo;
		}

		//Verifies the current user.
		//This is hardcoded to a free account and will never delete the cookie since we don't need one.
		public function Verify($ClearCookie){
			ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"Verify('$ClearCookie')\n",FILE_APPEND);
			return USER_IS_FREE;
		}

		//Called by the downloader to get the download information
		public function GetDownloadInfo() {
			ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getDownloadInfo()\n",FILE_APPEND);
			//prefer new info method over old ones
			$data=NULL;

			if(preg_match(REGEX_URL,$this->Url)){
				$zippy=$this->getHTML($this->Url);
				ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getDownloadInfo(): " . strlen($zippy) . " bytes HTML\n",FILE_APPEND);
				ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getDownloadInfo(): Try version 7\n",FILE_APPEND);
				$data=$this->getInfo7($this->Url,$zippy);
				if(isset($data['err'])){
					ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getDownloadInfo(): Try version 6\n",FILE_APPEND);
					$data=$this->getInfo6($this->Url,$zippy);
					if(isset($data['err'])){
						ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getDownloadInfo(): Try version 5\n",FILE_APPEND);
						$data=$this->getInfo5($this->Url,$zippy);
						if(isset($data['err'])){
							ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getDownloadInfo(): Try version 4\n",FILE_APPEND);
							$data=$this->getInfo4($this->Url,$zippy);
							if(isset($data['err'])){
								ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getDownloadInfo(): Try version 3\n",FILE_APPEND);
								$data=$this->getInfo3($this->Url,$zippy);
								if(isset($data['err'])){
									ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getDownloadInfo(): Try version 2\n",FILE_APPEND);
									$data=$this->getInfo2($this->Url,$zippy);
									if(isset($data['err'])){
										ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getDownloadInfo(): Try version 1\n",FILE_APPEND);
										$data=$this->getInfo1($this->Url,$zippy);
										if(isset($data['err'])){
											ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getDownloadInfo(): All modes failed\n",FILE_APPEND);
										}
									}
								}
							}
						}
					}
				}
			}
			else{
				ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getDownloadInfo(): Invalid URL\n",FILE_APPEND);
			}

			if($data){
				//Return error code if it was set
				if(isset($data['err'])){
					return array(DOWNLOAD_ERROR=>$data['err']);
				}
				//Return reformatted data structure for downloader
				return array(
					DOWNLOAD_URL=>$data['url'],
					DOWNLOAD_FILENAME=>$data['filename'],
					//ZippyShare supports parallel downloads
					DOWNLOAD_ISPARALLELDOWNLOAD=>TRUE
				);
			}
			//unknown error
			return array(DOWNLOAD_ERROR=>ERR_UNKNOWN);
		}

		//Downloads a file using wget (for testing)
		public function DownloadFile($overwrite){
			ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"DownloadFile($overwrite)\n",FILE_APPEND);
			$data = $this->GetDownloadInfo();
			if(isset($data[DOWNLOAD_URL]) && isset($data[DOWNLOAD_FILENAME])){
				if($overwrite || !file_exists($data[DOWNLOAD_FILENAME])){
					echo 'Downloading ' . $data[DOWNLOAD_FILENAME] . "\n";
					$fp=fopen($data[DOWNLOAD_FILENAME], 'wb+');
					$ch=curl_init($data[DOWNLOAD_URL]);
					curl_setopt($ch,CURLOPT_USERAGENT,DOWNLOAD_STATION_USER_AGENT);
					curl_setopt($ch,CURLOPT_TIMEOUT,50);
					curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
					curl_setopt($ch,CURLOPT_FILE,$fp);
					if(CUSTOM_CA){
						curl_setopt($ch,CURLOPT_CAINFO,CUSTOM_CA);
					}
					curl_exec($ch);
					curl_close($ch);
					fclose($fp);
					//$url=escapeshellarg($data[DOWNLOAD_URL]);
					//$fn=escapeshellarg($data[DOWNLOAD_FILENAME]);
					//echo "Executing: wget $url -O $fn\n";
					//exec("wget $url -O $fn");
				}
				else{
					echo 'Skipping over existing file: ' . $data[DOWNLOAD_FILENAME] . "\n";
				}
				return TRUE;
			}
			return FALSE;
		}

		//A more robust way to extract the file name
		private function getFileName($zippy){
			$regexURL='#dlbutton[\'"]\).href[^;]+"([^"]+)";#';
			if(preg_match(REGEX_FILENAME_NEW,$zippy,$names)){
				$name=trim($names[1]);
				if(strtolower($name)!=='private file'){
					return $name;
				}
			}
			if(preg_match($regexURL,$zippy,$names)){
				if(preg_match('#[^/]+$#',$names[1],$filename)){
					return trim($filename[0]);
				}
			}
			return NULL;
		}

		//Safely calculates a value
		private function doCalc($a,$symbol,$b){
			ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"doCalc($a,'$symbol',$b)\n",FILE_APPEND);
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

		//Extracts the URL from ZippyShare Source
		private function getNewUrl($data){
			ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getNewUrl(data length: " . strlen($data) . ")\n",FILE_APPEND);
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
				return 'Unable to extract class attribute';
			}

			//Get functions that return a constant
			if(preg_match_all($regex['CONST'],$data,$matches))
			{
				for($i=0;$i<count($matches[0]);$i++){
					$func[$matches[1][$i]]=+$matches[2][$i];
				}
			}
			else{
				return 'No individual JS functions found';
			}

			//Get functions that return the result of another function
			if(preg_match_all($regex['JS_FUNC'],$data,$matches))
			{
				for($i=0;$i<count($matches[0]);$i++){
					$result=$this->doCalc(+$func[$matches[2][$i]],$matches[3][$i],+$matches[4][$i]);
					if($result===FALSE){
						return 'Invalid Operator in JS function: ' . $matches[3][$i];
					}
					$func[$matches[1][$i]]=$result;
				}
			}
			else{
				return 'No combined JS functions found';
			}

			//Get variable that holds the class value
			if(preg_match_all($regex['CLASS_RESULT'],$data,$matches)){
				for($i=0;$i<count($matches[0]);$i++){
					$func[$matches[1][$i]]=+$func['class'];
				}
			}
			else{
				return 'Unable to find inline class calculation';
			}

			//Get inline calculation
			if(preg_match_all($regex['INLINE_CALC'],$data,$matches)){
				for($i=0;$i<count($matches[0]);$i++){
					$result=$this->doCalc(+$func[$matches[2][$i]],$matches[3][$i],+$matches[4][$i]);
					if($result===FALSE){
						return 'Invalid Operator in inline function: ' . $matches[3][$i];
					}
					$func[$matches[1][$i]]=+$result;
				}
			}
			else{
				return 'Unable to find inline calculation';
			}

			//Calculate number
			if(preg_match($regex['CHALLENGE'],$data,$matches)){
				//Mod challenge
				$result=$this->doCalc(+$matches[1],$matches[2],+$matches[3]);
				//a
				$result=$this->doCalc($result,$matches[4],+$func[$matches[5]]);
				//b
				$result=$this->doCalc($result,$matches[6],+$func[$matches[7]]);
				//c
				$result=$this->doCalc($result,$matches[8],+$func[$matches[9]]);
				//d
				$result=$this->doCalc($result,$matches[10],+$func[$matches[11]]);
				//const
				$result=$this->doCalc($result,$matches[12],$this->doCalc(+$matches[13],$matches[14],+$matches[15]));

				$func['result']=$result;
			}

			//Get Id
			if(preg_match($regex['FILE_ID'],$data,$matches)){
				$func['file_id']=$matches[1];
			}
			else{
				return 'Unable to extract Id';
			}
			//Get File name
			if(preg_match($regex['FILE_NAME'],$data,$matches)){
				$func['file_name']=$matches[1];
			}
			else{
				return 'Unable to extract Id';
			}

			//Build url
			$func['file_path']=$func['file_id'] . $func['result'] . $func['file_name'];
			return $func;
		}

		private function getHTML($url){
			$ch=curl_init($url);
			curl_setopt($ch,CURLOPT_USERAGENT,DOWNLOAD_STATION_USER_AGENT);
			curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
			if(CUSTOM_CA){
				curl_setopt($ch,CURLOPT_CAINFO,CUSTOM_CA);
			}
			$zippy=curl_exec($ch);
			if($errno=curl_errno($ch)){
				$errstr=curl_error($ch);
				ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getHTML('$url') error[$errno]: $errstr\n",FILE_APPEND);
			}
			curl_close($ch);
			return $zippy;
		}

		private function getInfo7($url,$zippy){
			ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo7('$url')\n",FILE_APPEND);
			$regex1='#var\s+(\w+)\s?=\s?(-?[\d.]+)\s*;\s*var\s+(\w+)\s?=\s?(-?[\d.]+)\s*;#';
			$regex2='#\(\s*(\w+)\s*/\s*(\d+)\s*\)#';
			$regex3='#\(\s*(\w+)\s*([+\-*/%])\s*(\d+)\s*([+\-*/%])\s*(\w+)\s*\)#';
			if(preg_match(REGEX_URL,$url,$matches)){
				$server=$matches[1];
				$id=$matches[2];
				if($fname=$this->getFileName($zippy)){
					ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo7('$url'): server=$server;id=$id;file=$fname\n",FILE_APPEND);
					if(preg_match($regex1,$zippy,$segments)){
						//Extract the two variables with initial values
						$vals=array();
						$vals[$segments[1]]=+$segments[2];
						$vals[$segments[3]]=+$segments[4];

						ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo7('$url'): " . json_encode($vals) . "\n",FILE_APPEND);

						//Extract the first calculation
						//It's currently always "a = Math.floor(a/3);" but we allow changing of variable names.
						if(preg_match($regex2,$zippy,$segments)){
							//Perform the first calculation
							ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo7('$url') calculation: " .
							$segments[1] . '=floor(' . $segments[1] . '/' . $segments[2] . ');' .
							PHP_EOL,FILE_APPEND);
							$vals[$segments[1]]=floor($vals[$segments[1]]/+$segments[2]);

							//Extract the next calculation.
							//It's currently always "a + const%b"
							//The constant seems to always be the initial value of "a"
							if(preg_match($regex3,$zippy,$segments)){
								$calc=$this->doCalc($vals[$segments[1]],$segments[2],$this->doCalc(+$segments[3],$segments[4],$vals[$segments[5]]));
								ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo7('$url') calculation: $calc\n",FILE_APPEND);
								return array(
									'url'=>"https://$server.zippyshare.com/d/$id/$calc/$fname",
									'filename'=>$fname
								);
							}
						}
						return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Unable to decode calculations');
					}
					return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Unable to decode variable names and values');
				}
				return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Can\'t decode file name');
			}
			return FALSE;
		}

		private function getInfo6($url,$zippy){
			ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo6('$url')\n",FILE_APPEND);
			$regex1='#var\s+(\w+)\s?=\s?(-?[\d.]+)\s*;\s*var\s+(\w+)\s?=\s?(-?[\d.]+)\s*;#';
			$regex2='#(\w+\s*[+\-*/]\s*\w+\s*[+\-*/]\s*\w+\s*[+\-*/]\s*\w+)\s*\)\s*\+\s*"(\d+)#';
			if(preg_match(REGEX_URL,$url,$matches)){
				$server=$matches[1];
				$id=$matches[2];
				if($fname=$this->getFileName($zippy)){
					ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo3(): server=$server;id=$id;file=$fname\n",FILE_APPEND);
					if(preg_match($regex1,$zippy,$segments)){
						//Extract the two variables
						$var1=$segments[1];
						$val1=+$segments[2];
						$var2=$segments[3];
						$val2=+$segments[4];
						//Extract the calculation
						if(preg_match($regex2,$zippy,$segments)){
							$calc=str_replace($var1,$val1,$segments[1]);
							$calc=str_replace($var2,$val2,$calc);
							//Eval is usually not safe, but our regex doesn't allows parenthesis,
							//which should avoid calling of functions by malicious code.
							ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo5('$url') calculation: $calc\n",FILE_APPEND);
							$calc=eval("return $calc;") . $segments[2];
							return array(
								'url'=>"https://$server.zippyshare.com/d/$id/$calc/$fname",
								'filename'=>$fname
							);
						}
						return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Unable to decode calculation');
					}
					return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Unable to decode variable names and values');
				}
				return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Can\'t decode file name');
			}
			return FALSE;
		}

		private function getInfo5($url,$zippy){
			ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo5('$url')\n",FILE_APPEND);
			$regex1='#var\s+a\s*=\s*(-?\d+)%(\d+);\s+var\s+b\s*=\s*(-?\d+)%(\d+);#';
			$regex2='#var\s+c\s*=\s*(-?\d+);#';
			$regex3='#var\s+d\s*=\s*(-?\d+)%(\d+);#';
			if(preg_match(REGEX_URL,$url,$matches)){
				$server=$matches[1];
				$id=$matches[2];
				if(preg_match(REGEX_FILENAME,$zippy,$names)){
					$fname=$names[1];
					ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo3(): server=$server;id=$id;file=$fname\n",FILE_APPEND);
					if(preg_match($regex1,$zippy,$segments)){
						$a=+$segments[1]%+$segments[2];
						$b=+$segments[3]%+$segments[4];
						if(preg_match($regex2,$zippy,$segments)){
							$c=+$segments[1];
							if(preg_match($regex3,$zippy,$segments)){
								$d=+$segments[1]%+$segments[2];
								$mod=$a*$b+$c+$d;
								return array(
									'url'=>"https://$server.zippyshare.com/d/$id/$mod/$fname",
									'filename'=>$fname
								);
							}
							else{
								return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Unable to decode variable "d"');
							}
						}
						else{
							return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Unable to decode variable "c"');
						}
					}
					else{
						return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Unable to decode variables "a" and "b"');
					}
				}
				else{
					return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Can\'t decode file name');
				}

			}
			return FALSE;
		}

		private function getInfo4($url,$zippy){
			ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo4('$url')\n",FILE_APPEND);
			$regex='#var\s+a\s*=\s*(-?\d+);\s+var\s+b\s*=\s*(-?\d+);#';
			if(preg_match(REGEX_URL,$url,$matches)){
				$server=$matches[1];
				$id=$matches[2];
				if(preg_match(REGEX_FILENAME,$zippy,$names)){
					$fname=$names[1];
					if(preg_match($regex,$zippy,$segments)){
						$a=+$segments[1];
						$b=+$segments[2];
						$mod=floor($a/3)+($a%$b);
						return array(
							'url'=>"https://$server.zippyshare.com/d/$id/$mod/$fname",
							'filename'=>$fname
						);
					}
					else{
						return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Can\'t decode variables');
					}
				}
				else{
					return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Can\'t decode file name');
				}
			}
			return FALSE;
		}

		private function getInfo3($url,$zippy){
			ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo3('$url')\n",FILE_APPEND);
			$regex='#var\s+a\s=\s(\d+);[^=]+=\s"[^"]+"\.substr\(\d+,\s(\d+)\);[^/]+/(\w+)/\w+/"\+\(Math\.pow\((\w+),\s(\w+)\)(.)(\w+)#';

			if(preg_match(REGEX_URL,$url,$matches)){
				$server=$matches[1];
				$id=$matches[2];
				if(preg_match(REGEX_FILENAME,$zippy,$names))
				{
					$fname=$names[1];
					if(preg_match($regex,$zippy,$segments)){
						$a=+$segments[1];
						$b=+$segments[2];
						$url1=$segments[3];
						$pow1=$segments[4];
						$pow2=$segments[5];
						$operator=$segments[6];
						$operand=$segments[7];

						if($pow1==='a'){
							$pow1=$a;
						}
						elseif($pow1==='b'){
							$pow1=$b;
						}
						else{
							$pow1=+$pow1;
						}

						if($pow2==='a'){
							$pow2=$a;
						}
						elseif($pow2==='b'){
							$pow2=$b;
						}
						else{
							$pow2=+$pow2;
						}

						if($operand==='a'){
							$operand=$a;
						}
						elseif($operand==='b'){
							$operand=$b;
						}
						else{
							$operand=+$operand;
						}

						$mod=$this->doCalc(pow($pow1,$pow2),$operator,$operand);
						return array(
							'url'=>"https://$server.zippyshare.com/$url1/$id/$mod/$fname",
							'filename'=>$fname
						);
					}
					else{
						return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Can\'t decode variables');
					}
				}
				else{
					return array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>'Can\'t decode file name');
				}
			}
			return FALSE;
		}

		//Extracts file information from ZippyShare using their new mod challenge
		private function getInfo2($url,$zippy){
			ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo2('$url')\n",FILE_APPEND);
			$ret=FALSE;
			//Check URL before even attempting to connect
			if(preg_match(REGEX_URL,$url,$matches)){
				$server=$matches[1];
				$id=$matches[2];
				if($zippy){
					$data=$this->getNewUrl($zippy);
					if(is_array($data)){
						$data['file_url']="https://$server.zippyshare.com" . $data['file_path'];
						$ret=array(
							'filename'=>substr($data['file_name'],1),
							'url'=>$data['file_url']
						);
					}
					else {
						//Unable to extract filename even though we should be able to
						//This is a sign of a missing file
						$ret=array('err'=>ERR_NOT_SUPPORT_TYPE,'message'=>$data);
					}
				}
				else {
					//Unable to load ZippyShare main page
					$ret=array('err'=>ERR_TRY_IT_LATER);
				}
			}
			else {
				//Unable to parse the URL as ZippyShare
				$ret=array('err'=>ERR_INVALID_FILEHOST);
			}
			//Success
			return $ret;		}

		//Extracts file information from ZippyShare
		private function getInfo1($url,$zippy){
			ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr',"getInfo1('$url')\n",FILE_APPEND);
			$ret=FALSE;
			//Check URL before even attempting to connect
			if(preg_match(REGEX_URL,$url,$matches)){
				if($zippy){
					$server=$matches[1];
					$id=$matches[2];
					//Extract file name
					if(preg_match(REGEX_FILENAME,$zippy,$matches)){
						$name=$matches[1];
						$ret['filename']=$name;
						//Extract mod Challenge
						if(preg_match(REGEX_MOD,$zippy,$matches)){
							//Calculate mod Challenge without using eval
							$segment = (+$matches[1]%+$matches[2])+(+$matches[3]%+$matches[4]);
							//$server:   www server id
							//$id:       Id from initial URL
							//$segment:  Result of the mod challenge
							//$name:     File name
							$ret['url']="https://$server.zippyshare.com/d/$id/$segment/$name";
						}
						else {
							if(preg_match(REGEX_NOTFOUND,$zippy)){
								//File not found
								$ret=array('err'=>ERR_FILE_NO_EXIST);
							}
							else{
								//This type of challenge is not supported
								$ret=array('err'=>ERR_NOT_SUPPORT_TYPE);
							}
						}
					}
					else {
						//Unable to extract filename even though we should be able to
						//This is a sign of a missing file
						$ret=array('err'=>ERR_FILE_NO_EXIST);
					}
				}
				else {
					//Unable to load ZippyShare main page
					$ret=array('err'=>ERR_TRY_IT_LATER);
				}
			}
			else {
				//Unable to parse the URL as ZippyShare
				$ret=array('err'=>ERR_INVALID_FILEHOST);
			}
			//Success
			return $ret;
		}
	}

	//Testing
	if(ZIPPY_ALLOW_DEBUG){

		//Make sure the arguments are present (even if empty), because they are not there if register_argc_argv isn't enabled
		if(!isset($argc) || !isset($argv)){
			$argc=0;
			$argv=array();
		}

		//Run "php zippy.php info <url>" to obtain information
		if($argc>2 && $argv[1]==='info'){
			$x=new ZippyHost($argv[2], NULL,NULL,NULL);
			$data=$x->GetDownloadInfo();
			if(isset($data[DOWNLOAD_ERROR])){
				echo "Error: {$data[DOWNLOAD_ERROR]}\n";
			}
			else{
				echo json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . "\n";
			}
		}
		//Run "php zippy.php get <url> [-f]" to download the file
		if($argc>2 && $argv[1]==='get'){
			$x=new ZippyHost($argv[2], NULL,NULL,NULL);
			if($x->DownloadFile(in_array('-f',$argv))){
				echo "OK\n";
			}
			else{
				echo "Error downloading File\n";
			}
		}

	}
	ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr','PHP Version: ' . phpversion() . "\n",FILE_APPEND);
	ZIPPY_PRINT_DEBUG && file_put_contents('/tmp/zippyerr','Time: ' . time() . "\n",FILE_APPEND);
?>