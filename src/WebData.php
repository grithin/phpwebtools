<?
namespace Grithin;
use Grithin\DomTools;
use Grithin\Debug;

///For parsing webdata often retrieved through CURL
class WebData{
	static function findDomColumns($columns,$find){
		foreach($columns as $i=>$v){
			$value = preg_replace('@(^[\s]+)|([\s]+$)@','',strip_tags($v->nodeValue));
			foreach($find as $key=>$column){
				$qColumn = preg_quote($column);
				if(preg_match('@^'.$qColumn.'$@i',$value)){
					$positions[$key] = $i;
					//potentially, multiple columns many:one
					continue;
				}
				if(preg_match('@'.$qColumn.'@i',$value)){
					if(!isset($positions[$key])){
						$positions[$key] = $i;
					}
				}
			}
		}
		return $positions;
	}
	static function getDomHeaderColumnsPositions($xpath,$headerRow,$columns,$query='td | th'){
		$headerColumns = $xpath->query($query,$headerRow);
		$headerColumns = DomTools::removeTextNodes($headerColumns);
		return self::findDomColumns($headerColumns,$columns);
	}
	static function getMappedDomColumns($row,$map){
		$columnValues = array();
		$columns = DomTools::childNodes($row);
		foreach($map as $key=>$position){
			$columnValues[$key] = trim($columns[$position]->nodeValue);
		}
		return $columnValues;
	}
	static function checkLoggedIn($html,$login){
		$matches = array(
				'@log\s?(out|off)@i',
				'@sign\s?(out|off)@i',
				'@Last Month|Last Week@i',
				'@[^a-z]My [a-z]@i',
				'@[ >]Scoop Interactive[< ]@i',
			);

		foreach($matches as $match){
			if(preg_match($match,$html)){
				return true;
			}
		}
		Debug::out('||ERROR||: Could not login');
		Debug::out($login);
		return false;
	}
	///pack post array with asp validation variables based on previously loaded page
	static function aspParseInto($html,$array){
		list($dom,$xpath) = DomTools::loadHtml($html);

		//look for even validation

		$nodes = $xpath->query('//input[@name=\'__VIEWSTATE\']');
		if($nodes->length){
			$array['__VIEWSTATE'] = $nodes->item(0)->getAttribute('value');
		}
		$nodes = $xpath->query('//input[@name=\'__VIEWSTATE1\']');
		if($nodes->length){
			$array['__VIEWSTATE1'] = $nodes->item(0)->getAttribute('value');
		}
		$nodes = $xpath->query('//input[@name=\'__VIEWSTATEGENERATOR\']');
		if($nodes->length){
			$array['__VIEWSTATEGENERATOR'] = $nodes->item(0)->getAttribute('value');
		}
		$nodes = $xpath->query('//input[@name=\'__EVENTVALIDATION\']');
		if($nodes->length){
			$array['__EVENTVALIDATION'] = $nodes->item(0)->getAttribute('value');
		}

		//optional stuff
		$vsKeyInput = $xpath->query('//input[@name=\'__vsKey\']')->item(0);
		if($vsKeyInput){
			$array['__vsKey'] = $vsKeyInput->getAttribute('value');
		}

		return $array;
	}
	/**asp wraps their json responses
	window.S143705548504019160124832()
	*/
	static function aspParseJson($text){
		#preg_match('@\(@i',$text,$match);
		$text = self::depackAspResponse($text);
		return json_decode($text,true);
	}
	static function aspParseXml($text){
		$text = self::depackAspResponse($text);
		return str_replace('\\"','"',$text);
	}
	static function depackAspResponse($text){
		preg_match('@^.*?\(([\s\S]*)\)\s*$@i',$text,$match);
		return $match[1];
	}
	static $conversionRates = array();
	static function getConversionRates(){
		$types = array('EUR','GBP');
		foreach($types as $type){
			$json = @file_get_contents('http://www.google.com/ig/calculator?hl=en&q=1'.$type.'=?USD');
			if($json){
				$json = preg_replace('@([\{:,])([a-z]+):@','$1"$2":',$json);
				$result = json_decode($json);
				preg_match('@[0-9.]+@',$result->rhs,$match);
				self::$conversionRates[$type] = $match[0];
			}else{
				self::$conversionRates[$type] = 1;
			}
		}
	}

	static function convert($amount,$from){
		if(!self::$conversionRates){
			self::getConversionRates();
		}
		return self::$conversionRates[$from] * $amount;
	}
	static function getCurrency($text){
		if(preg_match('#\&euro;#',$text) || preg_match('@€@',$text)){
			$convert = 'EUR';
		}
		if(preg_match('#\&pound;#',$text) || preg_match('@£@',$text)){
			$convert = 'GBP';
		}
		return $convert;
	}
	static function parseCurrency($amount,$convert=null){
		if(!$convert){
			$convert = self::getCurrency($amount);
		}

		$amount = preg_replace('@[^0-9\.]@','',$amount);
		if($convert){
			$amount = self::convert($amount,$convert);
		}
		$amount = $amount ? $amount : 0;
		return $amount;
	}
}