<?php
/**
 * A Method wrapping class, acts as placeholder for any referenced documentation
 * for each method for every namespace/xtype (Ext.define)
 * output case is defined globally for all 'child objects' by setting
 * DocParse::outputFormat($format = "vsdoc|jsdoc")
 */
class DocMethod {

	var $method = "";
	var $desc = "";
	var $params = array();
	var $return = array("type" => "void", "name" => "");
	var $deprecated = false;
	var $scope = "";
	var $examples = "";

	function DocMethod($id, $node) {
		if(empty($id)) return;
		if (isset($node['parameters'])) {
			foreach ($node['parameters'] as $param) {
				$this->addParameter(DocParse::removeUnWanted($param['summary']), $param['name'], $param['type']);
			}
		}
		$return = !empty($node['returns']) ? $node['returns'] : "void";
		$return_s = !empty($node['return_summary']) ? DocParse::removeUnWanted($node['return_summary']) : "";
		$this->return = array("type" => $return, "desc" => $return_s);
		$this->deprecated = isset($node['deprecated']);
		$this->scope = isset($node['private']) ? "private" : "public";
		$this->method = $id; // totoforeach key (!DocParse::validateVarName($id) ? "msDev07" : $id);
		//if(!DocParse::validateVarName($id)) echo $id." FAIL\n";
		if (isset($node['examples'])) {
			$this->examples = DocParse::removeUnWanted(implode("\n||--------------------\n", $node['examples']));
		}
	}

	private function addParameter($desc, $name, $type, $optional = false) {
		array_push($this->params, new DocCfgParamProp("parameter", $desc, $name, $type, $optional));
	}

	/**
	 * 
	 */
	function write($NSRef) {
		if(empty($this->method)) return;
		if (DocParse::$syntax == "jsdoc") {
			$dnl = "\n * ";
			DocParse::$outputBuffer .= "\n/**$dnl" .
					  preg_replace("/.*@(private|static).*/", "", implode("\n * ", explode("\n", $this->desc))) . $dnl .
					  (($this->scope != "public") ? "@{$this->scope}" : "") . "$dnl";
			foreach ($this->params as $p) {
				$p->write();
				DocParse::$outputBuffer .= $dnl;
			}
			if (!empty($this->examples)) {
				DocParse::$outputBuffer .= "@example " .
						  implode($dnl, explode("\n", $this->examples)) . $dnl;
			}
			if ($this->return['type'] != "void") {

				DocParse::$outputBuffer .=
						  "@type " . $this->return['type'] . $dnl .
						  "@returns {" . $this->return['type'] . "} " .
						  (isset($this->return['desc']) ? $this->return['desc'] . $dnl : "");
			}
			DocParse::$outputBuffer .= ($this->deprecated ? "@deprecated$dnl" : "") .
					  "\n*/\n {$this->method} = function(";
			$i = 0;
			foreach ($this->params as $p)
				DocParse::$outputBuffer .= (DocParse::validateVarName ($p->name) ? $p->name : "msc") .
						  ((($i++) + 1) == count($this->params) ? "" : ", ");
			// if constructor, fill in events and parameters inside fkt scope?
			DocParse::$outputBuffer .= ") {}"; // . $this->toString;
			//DocParse::$outputBuffer .= ") { }";
		}
	}

}

/**
 * An Event handle
 * output case is defined globally for all 'child objects' by setting
 * DocParse::outputFormat($format = "vsdoc|jsdoc")
 */
class DocEvent {

	var $name = "";
	var $desc = "";
	var $params = array();

	function DocEvent($id, $node) {

		if (isset($node['parameters'])) {
			foreach ($node['parameters'] as $param) {
				$this->addParameter(DocParse::removeUnWanted($param['summary']), $param['name'], $param['type']);
			}
		}
		$this->name = $id;
		$this->desc = DocParse::removeUnWanted($node['summary']);
	}

	function write($NSref) {
		if (DocParse::$syntax == "jsdoc") {
			$dnl = "\n * ";
			DocParse::$outputBuffer .= "/**$dnl" .
					  "@name " . $NSref->fullNS . "#{$this->name}$dnl" .
					  "@event$dnl";
			foreach ($this->params as $p) {
				$p->write();
			}
			DocParse::$outputBuffer .= "*/";
		}
	}

}

/**
 * A handle for configurable parameters for the Ext element
 * output case is defined globally for all 'child objects' by setting
 * DocParse::outputFormat($format = "vsdoc|jsdoc")
 */
class DocCfgParamProp {

	private $methodParameter = false;
	private $constructConfigurable = false;
	private $classProperty = false;
	var $scope = "public";
	var $name = "";
	var $type = "";
	var $desc = "";
//	static $P_CFG = "/@cfg\ [\ ]*{([^}]*)}[\ ]*([^\ ]*)/";
//	static $P_PROP1 = "/@property\ [\ ]*{([^}]*)}\ [\ ]*([^\ \n]*)/";
//	static $P_PROP2 = "/@property\ [\ ]*{([^}]*)}/";
//	static $P_PROP3 = "/@property\ [\ ]*{([^}]*)}\ [\ ]*([^\ \n]+)\ [\ ]*([^\n]*)/";
//	static $P_DESCTRIM = "/[\ ]+\*/s";

	/**
	 * Given all 3 arguments, construct will simply set variables, if 2nd and 3rd
	 * are left out, $dataOrDesc acts as codeblock placeholder and will be parsed
	 * @param String $dataOrDesc for @cfg, pass codeblock, if to be interpreted as a method description, u must set name and type
	 * @param String $name parameter name, defaults to null
	 * @param String $type parameter type, defaults to null
	 */
	function DocCfgParamProp($variant, $desc, $name, $type, $optional = false) {
		$this->desc = $desc;
		$this->name = $name;
		$this->optional = $optional;
		$this->type = $type;
		switch ($variant) {
			case "parameter" :
				$this->methodParameter = true;
				break;
			case "configurable":
				$this->constructConfigurable = true;
				break;
			case "property":
				$this->classProperty = true;
				if (substr($name, 0, 1) == "_")
					$this->scope = "private";
				break;
		}
	}

//
//	function parseConfigurable($id, $node) {
//		if (preg_match(DocCfgParamProp::$P_CFG, $data, $matches, PREG_OFFSET_CAPTURE)) {
//			$type = $matches[1][0];
//			$name = $matches[2][0];
//			$data = substr($data, $matches[2][1] + strlen($matches[2][0]) + 1);
//			$desc = trim(preg_replace(
//								 DocCfgParamProp::$P_DESCTRIM, "", substr(
//											$data, 0, strpos($data, "*" . "/") - 2)));
//		} else {
//			fb($data, "UNKNOWN");
//		}
//		return array($name, $type, $desc);
//	}
//
//	function parseProperty($id, $node) {
//		$data = trim($data);
//		$prop_b = strpos($data, "@property"); // cannot fail
//		$prop_e = strpos($data, "\n", $prop_b); // cannot fail
//		$propline = substr($data, $prop_b, $prop_e - $prop_b);
//		if ($prop_b == FALSE) { // implement type evaluation?
//			$type = "mixed";
//			$desc_b = 0;
//			$desc_e = strpos($data, "*/");
//			$desc = " " . substr($data, $desc_b, $desc_e - $desc_b);
//		} else if (preg_match(DocCfgParamProp::$P_PROP1, $propline, $matches)) {
//			// has description
//			if ($matches[2] == $name) {
//				// check for inline description
//				if (preg_match(DocCfgParamProp::$P_PROP3, $propline, $matches)) {
//					// description is after {type} {name} ...
//					$desc = $matches[3];
//				} else {
//					// guessing if its before @prop line or after 
//					if ($prop_b < 4) {
//						$desc_b = $prop_e;
//						$desc = " " . substr($data, $desc_b);
//						//$this->classDescription = substr($data, $end);
//					} else {
//						$desc = " " . substr($data, 0, $prop_b);
//					}
//				}
//			} else {
//				// guessing if its before @prop line or after 
//				if ($prop_b < 4) {
//					$desc_b = $prop_e;
//					$desc = " " . substr($data, $desc_b);
//					//$this->classDescription = substr($data, $end);
//				} else {
//					$desc = " " . substr($data, 0, $prop_b);
//				}
//			}
//			$type = $matches[1];
//		} else if (preg_match(DocCfgParamProp::$P_PROP2, $propline, $matches)) {
//			// no description matched inline, guessing if its before @prop line or after 
//			if ($prop_b < 4) {
//				$desc_b = $prop_e;
//				$desc = " " . substr($data, $desc_b);
//				//$this->classDescription = substr($data, $end);
//			} else {
//				$desc = " " . substr($data, 0, $prop_b);
//			}
//			$type = $matches[1];
//		}
//		if ($type == "" && preg_match("/@type\ [\ ]*([^\n\ ]*)/", $data, $matches)) {
//			$type = $matches[1];
//		} else {
//			$type = "mixed";
//		}
//		return array($name, $type, $desc);
//	}

	function isConfig() {
		return $this->constructConfigurable;
	}

	function isProperty() {
		return $this->classProperty;
	}

	function isMethodParameter() {
		return $this->methodParameter;
	}

	function write() {
		if ($this->methodParameter)
			$this->writeAsMethodParam();

		else if ($this->constructConfigurable)
			$this->writeAsConfigurableMember();

		else
			$this->writeAsClassParameter();
	}

	function writeAsMethodParam() {
		if (DocParse::$syntax == "jsdoc") {
			DocParse::$outputBuffer .= "@param " .
					  ($this->type == "" ? "{mixed}" : "{" . $this->type . "}") .
					  ($this->optional ? " [{$this->name}] " : " {$this->name} ") .
					  implode("\n * ", explode("\n", $this->desc)) . "\n * ";
		} else if (DocParse::$syntax == "vsdoc") {
			DocParse::$outputBuffer .= "<param name=\"{$this->name}\" type=\"{$this->type}\">{$this->desc}</param>";
		}
	}

	function writeAsClassParameter() {
		if (DocParse::$syntax == "jsdoc") {
			$dnl = "\n *";
			DocParse::$outputBuffer .= "/**$dnl" .
					  " @property " .
					  ($this->type == "" ? "{mixed}" : "{" . $this->type . "}") .
					  ($this->optional ? " [{$this->name}] " : " {$this->name} ") .
					  implode($dnl, explode("\n", $this->desc)) . $dnl .
					  "/\n" . $this->name . ": null";
		} else if (DocParse::$syntax == "vsdoc") {
			DocParse::$outputBuffer .= "<param name=\"{$this->name}\" type=\"{$this->type}\">{$this->desc}</param>";
		}
	}

	function writeAsConfigurableMember() {
		if (DocParse::$syntax == "jsdoc") {
			$dnl = "\n * ";
			DocParse::$outputBuffer .= "$dnl@param {" . $this->type . "} {$this->name}" .
					  $dnl . implode($dnl, explode("\n", $this->desc)) . "\n";
		} else if (DocParse::$syntax == "vsdoc") {
			DocParse::$outputBuffer .= "<param name=\"{$this->name}\" type=\"{$this->type}\">{$this->desc}</param>";
		}
// vsdoc optional=\"true\"   <para> tags for studio 2010?
	}

}

/**
 * A Namespace handle, basically convoluting a class
 * output case is defined globally for all 'child objects' by setting
 * DocParse::outputFormat($format = "vsdoc|jsdoc")
 */
class DocNamespace {

	/**
	 * Full namespace description, should be same as $class
	 * @var String
	 */
	var $fullNS = "";

	/**
	 * Class name
	 * @var String 
	 */
	var $class = "";

	/**
	 * @var String XType definition for Ext component queries
	 */
	var $xtype = "";

	/**
	 * @var String Parent class extended by this class
	 */
	var $extends = "";

	/**
	 * about the class itself
	 * @var string
	 */
	var $classDescription = "";

	/**
	 * @var Boolean 
	 */
	var $singleton = false;

	/**
	 * Parsed methods, has structure:
	 *   "method" => $function_name,
	 *   "toString" => $toString (function scope),
	 *   "desc" => $function_desc (general description docu),
	 *   "params" => $params (array of parameters (type,name,desc)),
	 *   "scope" => $scope (public|private|ignore),
	 *   "return" => $return (array describing return value (type, desc)),
	 *   "deprecated" => $deprecated
	 * @var Array of class methods 
	 */
	var $methods = array();

	/**
	 * @var Array of configurable (some readonly, some static) class members 
	 */
	var $parameters = array();

	/**
	 * Events of the class, which may fire on use in users code,
	 * each which can be setup as listeners in the container hierachy
	 * @var array events 
	 */
	var $events = array();

	/**
	 * Examples describing how to utilize the class
	 * @var array examples 
	 */
	var $examples = "";
	var $ctor = "";

	/**
	 * Typically blocks of code with no comments
	 * we will ! piece these together with final documentation for intellisence
	 * to be aware of their presence
	 * @var Array of strings with unparsed codeblocks
	 */
	var $outofplace = array();

	function DocNamespace($ns, $isClass = true) {
		if ($isClass)
			$this->class = $ns;
		$this->fullNS = $ns;
//		fb($ns, " NEW ");
	}

	/**
	 * Adds a class method, appending a new DocMethod to the namespace
	 * you should be aware that multiple source files may contain documentation
	 * for the same namespace
	 * @param String $id method identifier (function name)
	 * @param array $data codeblock to parse
	 * @param String $toString the extracted function scope 
	 * @return {DocMethod} reference to appended method object
	 */
	function addMethod($id, $node, $toString = "") {
		$ref = new DocMethod($id, &$node);
		array_push($this->methods, $ref);
		$this->methods[count($this->methods) - 1]->toString = $toString;
	}

	/**
	 * Adds a configurable parameter
	 * we will find these in html-docs by ns1-ns2-ns3-[cfg|parameter] id's
	 * @param String $id method identifier (configuration name)
	 * @param array $data entry describing the construct configurable
	 */
	function addConfigurable($data) {
		array_push($this->parameters, new DocCfgParamProp("configurable", DocParse::removeUnWanted($data['summary']), $data['name'], $data['type']));
	}

//	function DocCfgParamProp($variant, $desc, $name, $type) {

	/**
	 * Adds a class property
	 * we will find these in html-docs by ns1-ns2-ns3-[cfg|parameter] id's
	 * @param String $id method identifier (property name)
	 * @param array $node entry describing the class member variable
	 */
	function addProperty($node) {
		array_push($this->parameters, new DocCfgParamProp("property", DocParse::removeUnWanted($node['summary']), $node['name'], $node['type']), isset($node['optional']));
	}

	/**
	 * Sets documentation for class, most likely to yield some examples of usage
	 * @param String $id method identifier (class name)
	 * @param array $node entry describing the class member variable
	 */
	function addDocu($id, $data) {
		if (!isset($data['summary'])) {
			// simple subnamespace
			return;
		}
		$this->classDescription = DocParse::removeUnWanted($data['summary']);
		if (!empty($this->class)) {
			$this->ctor = "function(";
			if (isset($data['parameters'])) {
				$i = 0;
				$count = count($data['parameters']);
				foreach ($data['parameters'] as $param) {
					if (!isset($param['summary']))
						$param['summary'] = "";
					if (!isset($param['type']))
						$param['type'] = "";
					$this->addConfigurable($param);
					$this->ctor .= $param['name'] .
							  ($i++ < $count - 1 ? "," : "");
				}
			}
			$this->ctor.= ")";
		}
		if (isset($data['examples'])) {
			$this->examples = DocParse::removeUnWanted(implode("\n", $data['examples']));
		}
//		if (preg_match("/@extends[\ ][^A-Z]*([^\n]*)/", $data, $matches, PREG_OFFSET_CAPTURE)) {
//			$this->extends = $matches[1][0];
//			if ($end < $matches[1][1])
//				$end = $matches[1][1];
//		}
	}

	/**
	 * Adds another observable event for the class
	 * implementation for the intellisence generation is a little corny for these
	 * since Ext uses Ext.addEvents inside the constructor method
	 * We define a method if it does not allready exists in class scope and mark
	 * this method as event, e.g.
	 * /-- Function fires at...
	 *  - @event
	 *  - param {type} Abc
	 * -/
	 * methodName : function(Abc) { }
	 * 
	 * @param String $data codeblock to parse
	 */
	function addEvent($data) {
		array_push($this->events, new DocEvent($data));
	}

	function write() {
		$this->writeDescription();
		$this->writeOpenClass();
		$this->writeCloseClass();
		$this->writeMethods();
	}

	function writeDescription() {
		if (DocParse::$syntax == "jsdoc") {
			$dnl = "\n * ";
			DocParse::$outputBuffer .= "\n/**$dnl";
			if (!empty($this->classDescription))
				DocParse::$outputBuffer .=
						  implode($dnl, explode("\n", $this->classDescription)) . "$dnl$dnl";
			DocParse::$outputBuffer .= ($this->class != "" ? "@constructor {$this->class}$dnl" : "@namespace$dnl"); // .
//					  ($this->extends != "" ? "@extends {$this->extends}$dnl" : "") .
//					  ($this->xtype != "" ? "@xtype {$this->xtype}$dnl" : "") .
//					  ($this->singleton ? "@singleton$dnl" : "");

			if ($this->writeConfigs())
				DocParse::$outputBuffer .= "*$dnl";

			if (!empty($this->examples)) {
				DocParse::$outputBuffer .= "@example " .
						  implode($dnl, explode("\n", $this->examples));
			}
			DocParse::$outputBuffer .= "\n*/\n";
		}
	}

	function count($type) {
		$c = 0;
		switch ($type) {
			case "properties":
				foreach ($this->parameters as $p)
					if ($p instanceof DocCfgParamProp && $p->isProperty())
						$c++;
				break;
			case "config":
				foreach ($this->parameters as $p)
					if ($p instanceof DocCfgParamProp && $p->isConfig())
						$c++;
				break;
			case "events":
				$c = count($this->events);
				break;
			case "methods":
				$c = count($this->methods);
				break;
		}
		return $c;
	}

	function writeConfigs() {
		$count = 0;
		foreach ($this->parameters as $p) {
			if ($p instanceof DocCfgParamProp && $p->isConfig()) {
				$p->write();
				$count++;
			}
		}
		return $count > 0;
	}

	function writeProperties() {
		$count = $this->count("properties");
		$i = 0;
		foreach ($this->parameters as $p) {
			if ($p instanceof DocCfgParamProp && $p->isProperty()) {
				$p->write();
				DocParse::$outputBuffer .= ($i++ < $count - 1 ? ",\n" : "\n");
			}
		}
		return $count > 0;
	}

	function writeEvents() {
		$count = 0;
		foreach ($this->events as $ev) {
			$ev->write(&$this);
			DocParse::$outputBuffer .= "\n";
			$count++;
		}
		return $count > 0;
	}

	function writeOpenClass() {

		DocParse::$outputBuffer .= $this->fullNS . " = " . $this->ctor . "{ \n";
		if ($this->count("properties") > 0) {
//			DocParse::$outputBuffer .= "/*\n";
			$this->writeProperties();
//			DocParse::$outputBuffer .= "*/\n";
		}
		$this->writeEvents();
		/*
		  if (count($this->methods) == 0) {

		  $extra = trim(implode("\n", $this->outofplace));
		  $trail = strrpos($extra, ",");
		  if ($trail > strlen($extra) - 4) {
		  $extra = substr($extra, 0, $trail);
		  }
		  DocParse::$outputBuffer .= $extra . "\n";
		  } else
		  DocParse::$outputBuffer .=implode("\n", $this->outofplace);
		 *
		 */
	}

	function writeCloseClass() {
		DocParse::$outputBuffer .= "};\n";
	}

	function writeMethods() {
		$i = 0;
		foreach ($this->methods as $method) {
			if ($method->method != "constructor") {
				$method->write(&$this);
				DocParse::$outputBuffer .=
						  ($i++ < count($this->methods) - 1) ? ";\n" : "\n\n";
			}
		}
	}

}

class DocParse {

	var $max_iterations = 111800; // debugs
	var $root = array();
	var $basedir = "";
	var $curNS = "";
	var $files = array();
	private $resources = null;
	private $nodes = null;
	static $outputBuffer = "";
	static $syntax = "jsdoc";
	static $P_CODEBLOCK_NODE = "pre";
	static $P_PARAMETER = "[\ ]?:/"; // postfix on /$membername
	static $P_OBJECT_FUNCTION = "[\ ]*:[\ ]*function/"; // postfix on /$membername

	function DocParse() {
		$this->nodes = new Freezer("void", "nodes");
		$this->resources = new Freezer("void", "resources");

		$this->assertNS("dojo", false);
		$this->assertNS("dijit", false);
		$this->assertNS("dojox", false);
	}

	static function outputFormat($format) {
		DocParse::$syntax = $format;
	}

	function iterateResources() {
		$i = 0;
		foreach ($this->resources->ids() as $file) {
			if ($this->max_iterations < $i++)
				break;
			$this->parseResource($file);
		}
	}

	function iterateNodes() {
		$i = 0;
		foreach ($this->nodes->ids() as $node) {
//
			if ($this->max_iterations < $i++)
				break;
			$this->parseNode($node);
		}
	}

	function iterate_all() {
		$this->iterateResources();
		$this->iterateNodes();
	}

	function parseNode($node_id) {
		if (empty($node_id))
			return;
		foreach (explode(".", $node_id) as $sz) {
			if (!DocParse::validateVarName($sz))
				return;
		}
		$node = $this->nodes->open($node_id, false);
		$baseNamespace = $node['#namespaces'][0];
		// test if its 'classlike' node
		$NS = $this->getNS($node_id);
		if ($NS) {
//			fb($node, "CONSTRUCTOR $node_id skip");
			return;
		} else if (!isset($node['type'])
				  || ($node['type'] != "Function" || ($node['type'] == "Object" && !isset($node['classlike'])))) {
//			fb($node, "WEIRD $node_id skip");
			return;
		}
		// else generate a method or parameter addition to basenamespace
		$NS = $this->getNS($baseNamespace);
		if ($node['type'] == "Function")
			$NS->addMethod($node_id, $node);
		else
			$NS->addProperty($node);
//		fb($node, "PARSE NODE $node_id");
	}

	static function validateVarName($name) {
		$conformity = preg_match("/^[a-zA-Z\$_][a-zA-Z0-9\$_]*$/", $name);
		$reserved = !preg_match("/^(var|char|int|bool|boolean|void|null|undefined|if|for|else|while|do|switch|goto|continue|default|break|function|return|try|catch|throw|finally|with|in|debugger|instanceof|new|typeof|class|enum|extends|super|const|export|import)$/", $name);
		return $conformity && $reserved && !empty($name);
	}

	static function removeUnWanted($txt, $incomment = true) {
		if (!$incomment)
			return trim($txt); // nothing done here, just yet




			
// rid of /*  and */
		$txt = preg_replace("/(\*\/|\/\*)/s", "///", $txt);
		return $txt;
	}

	function parseResource($file_id) {
//		global $db_password;
//		global $db_user;
//		global $db_name;
//		global $db_host;

		$NS = null;
		$mtime = $this->resources->open($file_id, 1000000);
		$parts = explode("%", $file_id);
		$top = $parts[0]; // namespace suffix

		$parts[1] = explode("/", $parts[1]);
		$end = array_pop($parts[1]); // get last entry and clear from array
		$end = substr($end, 0, strrpos($end, ".")); // take off .js
		$mid = implode(".", $parts[1]); // join middle from bits in array
		$baseNamespace = "$top" . (!empty($mid) ? "." : "") . "$mid.$end";
		// validate id as assuring its renderable by JS engine
		if (!DocParse::validateVarName($top) || !DocParse::validateVarName($end))
			return;
		foreach ($parts[1] as $bit)
			if (!DocParse::validateVarName($bit))
				return;
		$node = $this->nodes->open($baseNamespace, false);
//		fb($node, "parsed $file_id");
//		fb($node, "Resource $file_id, dbid: $baseNamespace");
		// skip where we dont want to go
		if (!$node) {
			return;
//		} else if (!isset($node['type'])) {
//			// most likely mixin lets continue if not marked private
//			if (isset($node['private']))
//				return;
		} else if (!empty($node['private']) || !empty($node['private_parent'])) {
//			if (!preg_match("/^dojo\._base/", $baseNamespace)) {
//			fb("PRIVATE $baseNamespace: skip");
			return;
//			}
		} else if ($node['type'] != "Object"
				  && ($node['type'] == "Function" && empty($node['classlike']) && empty($node['returns']))) {
			// instantiable, lets look for methods ?
//			fb("NOT OBJECT $baseNamespace: skip");
			return;
		}

		/*
		  // assert a docns structs so no deep class/function will suffer if any
		  // of its parent directoris has not delivered a resource
		  foreach ($node['#namespaces'] as $ns)
		  $this->assertNS($ns);

		  // do provides ns', allthough this is hardly nescessary as its resources will follow
		  foreach ($node['#provides'] as $provide) {
		  if (!preg_match("/\._/", $provide))
		  $this->assertNS($provide);
		  }
		 */
		// get currently processing namespace
		$NS = &$this->assertNS($baseNamespace, isset($node['classlike']));
		////////// sooooooo, whats next
		if (!empty($node['type'])) {
			if ($node['type'] == "Object") {
				// add class descriptions, if simply a subnamespace nothing will be added here
				$NS->addDocu($baseNamespace, $node);
			} else if ($node['type'] == "Function" && !isset($node['classlike'])) {
				// we have a resource supplying helper-function, librarylike
				// add the method as members to correct (parent) namespace
				if (!empty($mid) && !empty($top)) {
					$baseNamespace = $top . (!empty($mid) ? ".$mid" : "");
					$NS = &$this->assertNS($baseNamespace);
					$NS->addMethod($id, $node);
					return;
				}
			} else if ($node['type'] == "Function" && isset($node['classlike'])) {
				$NS->addDocu($baseNamespace, $node);
			}
		}
		return;
	}

	function prepareWrite() {
		if (!ksort($this->root))
			throw Exception("namespace sort failed");
		foreach ($this->root as $ns) {
			$ns->write();
		}
	}

	function flush($prettyprint = false) {
		if ($prettyprint) {
			$lines = explode("\n", DocParse::$outputBuffer);
			$nest = 0;
			$thisIsIncrement = -1;
			for ($l = 0; $l < count($lines); $l++) {
				$line = $lines[$l];
				for ($i = 0; $i < strlen($line); $i++) {
					if ($line[$i] == "{") {
						$thisIsIncrement++;
						$nest++;
					} else if ($line[$i] == "}") {
						$thisIsIncrement--;
						$nest--;
					}
				}
				$indent = "";
				for ($i = 0; $i < $nest - ($thisIsIncrement >= 0 ? 1 : 0); $i++)
					$indent .= "\t";
				$lines[$l] = $indent . trim($lines[$l]);
				$thisIsIncrement = -1;
			}
//			foreach ($lines as $line)
//				if (trim($line) != "")
//					echo "$line\n";
			echo implode("\n", $lines);
		} else {
			foreach (explode("\n", DocParse::$outputBuffer) as $line)
				if (trim($line) != "")
					echo "$line\n";
			echo DocParse::$outputBuffer;
		}
		DocParse::$outputBuffer = "";
	}

	/**
	 * traverses known namespaces and returns findings
	 * @param type $namespace
	 * @return DocNamespace requested namespace, null if not found
	 */
	function getNS($ns) {
		if (isset($this->root[$ns]))
			return $this->root[$ns];
		$i = 0;
		$n = count($this->root);
		reset($this->root);
		while ($i++ < $n && ($cur = next($this->root)) !== FALSE)
			if ($cur->fullNS == $ns)
				return $cur;
		return null;
	}

	/**
	 * Asserts that given namespace exists
	 * @param String $namespace
	 * @return DocNamespace for convenience 
	 */
	function assertNS($ns, $isClass = true) {
		if (($cur = $this->getNS($ns)) == null)
			$this->root[$ns] = $cur = new DocNamespace($ns, $isClass);
		return $cur;
	}

}

ob_start();
error_reporting(E_ALL ^ E_NOTICE);
header("Content-Type: text/plain");
$db_password = "mysqld";
$db_user = "root";
$db_name = "generate";
$db_host = "localhost";
$dp = new DocParse();
$dp->iterateResources();
$dp->iterateNodes();
//// write without sorting
//foreach ($dp->root as $ns) {
//	$ns->write();
//}
// sort and write all ns'
$dp->prepareWrite();
//$ns = $dp->getNS("Ext.panel.AbstractPanel");
//foreach ($ns->methods as $m) {
//	fb($m);
//}
DocParse::flush(true);
//var_dump($dp->root);
?>
