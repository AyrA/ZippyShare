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
	//Regex to capture the initial file URL
	define('REGEX_URL','/(www\d+).zippyshare.com\/v\/([^\/]+)\/file.html/i');
	//Regex to extract the file name from JS source code
	define('REGEX_FILENAME','/[^=]"\/([^?<\/"]+)"/i');
	//Regex to extract the mod challenge
	define('REGEX_MOD','/\((-?\d+)\s*%\s*(-?\d+)\s*\+\s*(-?\d+)\s*%\s*(-?\d+)\)/');
	//Regex to check for the "file not found" text
	define('REGEX_NOTFOUND','/>File does not exist on this server</i');
	//The regex below gets the file name from the title.
	//This sometimes failed in the past so we no longer use it,
	//but it's still here in case the other method fails and we need it.
	//define('REGEX_FILENAME','/<title>ZippyShare.com - ([^<]+)<\/title>/i');
	
	class ZippyHost {
		private $Url;
		private $Username;
		private $Password;
		private $HostInfo;
		
		public function __construct($Url, $Username, $Password, $HostInfo) {
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
			return USER_IS_FREE;
		}
		
		//Called by the downloader to get the download information
		public function GetDownloadInfo() {
			$data=$this->getInfo($this->Url);
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
		public function DownloadFile(){
			$data = $this->GetDownloadInfo();
			if(isset($data[DOWNLOAD_URL]) && isset($data[DOWNLOAD_FILENAME])){
				$url=escapeshellarg($data[DOWNLOAD_URL]);
				$fn=escapeshellarg($data[DOWNLOAD_FILENAME]);
				echo "Executing: wget $url -O $fn\n";
				exec("wget $url -O $fn");
				return TRUE;
			}
			return FALSE;
		}
		
		//Extracts file information from ZippyShare
		private function getInfo($url){
			$ret=FALSE;
			$opts = array(
				'http'=>array(
					'method'=>'GET',
					'header'=>
						//HTTP Headers to make this request look more like it comes from a browser
						//Note that the last header should end in \r\n too, this is no mistake
						implode("\r\n",array(
							'DNT: 1',
							'User-Agent: ' . DOWNLOAD_STATION_USER_AGENT,
							'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
							'Accept-Language: en-US,en;q=0.9,de;q=0.8'
						)) . "\r\n"
				)
			);
			//Check URL before even attempting to connect
			if(preg_match(REGEX_URL,$url,$matches)){
				$ctx=stream_context_create($opts);
				$zippy=file_get_contents($url,FALSE,$ctx);
				$segments=null;
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
		//Run "php zippy.php test <url>" to obtain information
		if($argc>2 && $argv[1]==='test'){
			$x=new ZippyHost($argv[2], NULL,NULL,NULL);
			$data=$x->GetDownloadInfo();
			if(isset($data[DOWNLOAD_ERROR])){
				echo "Error: {$data[DOWNLOAD_ERROR]}";
			}
			else{
				echo json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
			}
		}
		//Run "php zippy.php get <url>" to download the file
		if($argc>2 && $argv[1]==='get'){
			$x=new ZippyHost($argv[2], NULL,NULL,NULL);
			if($x->DownloadFile()){
				echo 'OK';
			}
			else{
				echo 'Error downloading File';
			}
		}
	}
