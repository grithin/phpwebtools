<?
namespace Grithin;
use Grithin\Debug;

class DomTools{
	///takes a node and makes a dom document out of it
	static function isolateNode($node){
		$dom = new \DOMDocument;
		if(get_class($node) == 'DOMDocument'){//DOMDocument is not a node, so get primary element in document
			$node = $node->documentElement;
		}
		$dom->appendChild($dom->importNode($node,true));
		return $dom;
	}
	///get string of child nodes
	static function nodeInnerXml($node){
		$children = $node->childNodes;
		return self::nodesHtml($children);
    }
    ///in string of either an array of nodes or a nodeList
		/**
		@param	wrap	<string><<if present, will wrap resulting nodes in a node of this string>>
		*/
    static function nodesHtml($nodes,$wrap=''){
		foreach($nodes as $node){
			$html .= trim(self::nodeHtml($node));
		}
		if($wrap){
			$html =  '<'.$wrap.'>'.$html.'</'.$wrap.'>';	}
		return $html;
	}
	///get string of not html
	static function nodeHtml($node){
		$dom = self::isolateNode($node);
		return $dom->saveHTML();
	}
	static function loadHtml($html){
		$dom = new \DOMDocument;
		@$dom->loadHTML($html);
		$xpath = new \DomXPath($dom);
		return array($dom,$xpath);
	}
	static function nodeXml($node){
		$dom = self::isolateNode($node);
		return $dom->saveXML();
	}
	static function loadXml($xml,$nsPrefix='d'){
		$dom = new \DOMDocument;
		@$dom->loadXML($xml);
		$xpath = new \DomXPath($dom);

		$rootNamespace = $dom->lookupNamespaceUri($dom->namespaceURI);
		if($rootNamespace){
			if($dom->documentElement->getAttribute('xmlns:d')){
				Debug::toss('Namespace prefix "'.$nsPrefix.'" taken');
			}
			$xpath->registerNamespace($nsPrefix, $rootNamespace);
			$nsPrefix .= ':';
		}else{
			$nsPrefix = '';
		}
		return array($dom,$xpath,$nsPrefix);
	}
	static function removeTextNodes($nodeList){
		for($i = 0; $i < $nodeList->length; $i++){
			if($nodeList->item($i)->nodeName != '#text'){
				$nonTextNodes[] = $nodeList->item($i);
			}
		}
		return $nonTextNodes;
	}


	///get array of child nodes exluding #text nodes
	static function childNodes($node){
		$childNodes = $node->childNodes;
		$nonTextChildNodes = array();
		for($i = 0; $i < $childNodes->length; $i++){
			if(substr($childNodes->item($i)->nodeName,0,1) != '#'){
				$nonTextChildNodes[] = $childNodes->item($i);
			}
		}
		return $nonTextChildNodes;
	}
	///Get child node at index excluding #text nodes
	static function childNode($node,$index){
		$childNodes = $node->childNodes;
		$nonTextChildNodes = array();
		$current = 0;
		for($i = 0; $i < $childNodes->length; $i++){
			if(substr($childNodes->item($i)->nodeName,0,1) != '#'){
				if($current == $index){
					return $childNodes->item($i);
				}
				$current ++;
			}
		}
		return false;
	}

	///from an array of nodes, find matchin texts
	/**
	@param	nodes	[<<node>>,...]
	@param	find	{<<key>> : <string><<text to match>>,...}
	*/
	static function matchMap($nodes,$find){
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
	///apply matchMap return to array of nodes to get mapped array
	/**
	@param	parent	<node><<parent node>>
	@param	map	<matchMap return>
	*/
	static function mapNodes($parent,$map){
		$columnValues = array();
		$columns = self::childNodes($row);
		foreach($map as $key=>$position){
			$columnValues[$key] = trim($columns[$position]->nodeValue);
		}
		return $columnValues;
	}
}
