<?php

define(OBJ_S, "//-jshdr-id-");
define(DOCU_S, "/**");
define(DOCU_L, " * ");
define(DOCU_E, "*/");
define(OBJ_E, "//-jshdr-obj-end");
define(C_AT, ord('@'));
define(C_LT, ord('<'));

/**
 * JsDocSerializer
 * Utility for outputting documentation suitable for a number of IDE's and to be
 * used as source for intellisence.
 * Since IDE's evaluate the headerfile as any browser would a .js, serialization
 * takes in account, not to output any overrides of properties and sets relations
 * in classes such that modern intellisence capeable browsers will acknowledge
 * that an instance may inherit from another class - so dijit's should know about
 * set() get() from _WidgetBase etc.. Mixins are not allowed in a proper OOP hierachy
 * and are therefore ignored.
 *
 * Caveats are, that the Parser works in mysterious ways and somehow extends
 * into inline comments in function scopes therefore, such as doublets and
 * invalid variable names are filtered out at a rather greedy pace..
 *
 * A full dojotree serialization however will still result in a 6Mb file with
 * lots and lots of by-the-hand documentation.
 *
 * Some additional variables to pass in by CLI options are added, some may want
 * the opportunity to browse private scopes, since IDE's normally filters these.
 * However, some IDE's filter out '_' prefixed methods and properties
 *
 * --private-ignore   : serializer will not output any private scopes        <<< IMPLEMENT
 * --private-override : even if private doclets will not contain '@private'   << RENAME
 * --modules-only     : serializer will only output instantiable classes and
 * 						      their function scope
 */
class JsDocSerializer extends Serializer {

	private $contents_processed = array();
	protected $header = array('');
	protected $footer = array('');
	private $contentbuffer = array();
	protected $indent = "";
	private $everythingpublic = false;
	private $modulesonly = false;
	private $add_at_method = false; // specific for ScriptDoc, not nescessary

	public function __destruct() {
		var_dump($this->data);
		//		$this->json = array();
		//		function &assert_ns($sub, &$ptr) {
		//			if(!isset($ptr[$sub]))
		//			$ptr[$sub] = array();
		//			return $ptr[$sub];
		//		}
		//		ksort($this->contents_processed);
		//		foreach($this->contents_processed as $ns=>$one) {
		//			echo "process $ns\n";
		//
		//			$prt = explode(".", $ns);
		//			for($lvl = 0; $lvl < count($prt); $lvl++) {
		//				switch($lvl) {
		//					case 0:
		//						if(!isset($this->json[$prt[$lvl]]))
		//						$this->json[$prt[$lvl]] = array();
		//						break;
		//					case 1:
		//						if(!isset($this->json[$prt[0]][$prt[$lvl]]))
		//						$this->json[$prt[0]][$prt[$lvl]] = array();
		//						break;
		//					case 2:
		//						if(!isset($this->json[$prt[0]][$prt[1]][$prt[$lvl]]))
		//						$this->json[$prt[0]][$prt[1]][$prt[$lvl]] = array();
		//						break;
		//					case 3:
		//						if(!isset($this->json[$prt[0]][$prt[1]][$prt[2]][$prt[$lvl]]))
		//						$this->json[$prt[0]][$prt[1]][$prt[2]][$prt[$lvl]] = array();
		//						break;
		//					case 4:
		//						if(!isset($this->json[$prt[0]][$prt[1]][$prt[2]][$prt[3]][$prt[$lvl]]))
		//						$this->json[$prt[0]][$prt[1]][$prt[2]][$prt[3]][$prt[$lvl]] = array();
		//						break;
		//					case 5:
		//						break;
		//					case 6:
		//						break;
		//				}
		//
		//
		//			}
		//
		//		}
		//		foreach($this->json as $top => $data) {
		//			$this->header[] = $top . " = " . json_encode($data) . ";";
		//		}
		//
		parent::__destruct();
	}

	//	public function __construct($directory, $suffix, $filename='api') {
	//		//		$this->nodeLookup = new Freezer("cache", "nodes");
	//		//	public function __construct($directory, $suffix, $filename='api') {
	//		//		global $argv;
	//		//		$argc = count($argv);
	//		//		for ($i = 0; $i < count($argv); $i++) {
	//		//			if ($argv[$i]{0} == '-') {
	//		//				if (preg_match("/^--(private-override)=([^ ]+)$/", $argv[$i], $match)) {
	//		//					$this->everythingpublic = ($match[2] != "no" ? true : false);
	//		//				} else if (preg_match("/^--(modules-only)=([^ ]+)$/", $argv[$i], $match)) {
	//		//					$this->modulesonly = ($match[2] != "no" ? true : false);
	//		//				}
	//		//			}
	//		//		}
	//		//		echo "modulesonly ". var_export($this->modulesonly, true)."\n";
	//		//		echo "everythingpublic ". var_export($this->everythingpublic, true)."\n";
	//		parent::__construct($directory, $suffix, $filename);
	//}

	protected function lineStarts($line) {
		if (preg_match('%^' . OBJ_S . '(.*)%', $line, $match)) {
			return $match[1];
		}
	}

	protected function lineEnds($line) {
		if (preg_match('%^' . OBJ_E . '%', $line, $match)) {
			return true;
		}
	}

	protected function linesToRaw($lines) {
		return "";
	}

	public function toObject($raw, $id=null) {
		return $raw;
	}

	public function toString($raw, $id=null) {
		if (!$id) {
			if (!($id = $raw['id'])) {
				throw new Exception('toString must be passed an ID or raw object must contain and ID');
			}
		}
		// raw is the contentbuffer, related to id, clean it
		//		return OBJ_S . "$propOf\n" . implode("\n", $this->contentbuffer) . OBJ_E;
		$in_example = false;
		$in_comment = false;
		$in_comment_start = false;
		$sz = OBJ_S . "$id\n";
		for ($c = 0; $c < count($raw); $c++) {
			unset($mod_buf);
			switch (substr($raw[$c], 0, 3)) {
				case DOCU_S:
					//				case DOCU_L: // shouldnt be a nescessary check
					$in_comment_start = true;
					$in_example = false;
					break;
				case DOCU_E:
					$in_comment = false;
					break;
				default:
					if (!$in_comment) {
						//validate_var_names
						$varPortion = trim(substr($raw[$c], 0, strpos($raw[$c], "=")));
						$parts = explode(".", $varPortion);
						$ok = true;
						for ($i = 0; $i < count($parts); $i++) {
							if ($this->validateVarName($parts[$i]))
								continue;
							$ok = false;
							echo "INVALID: {$parts[$i]}\n";
							$parts[$i] = "INVALID_IDENT";
						}
						if (!$ok) {
							return OBJ_S . "$id\n" . "//-jsdoc-invalid-entry [" .
									  implode(".", $parts) . "]\n" . OBJ_E . "\n";
						}
					} else {
						if (preg_match("/(\*\/|\/\*)/s", substr($raw[$c], 3))) {
							$mod_buf = preg_replace("%(\*/|/\*)%", "///", $raw[$c]);
						}
					}
			}
			if ($in_example || ( ord($raw[$c]{3}) == C_AT && strstr($raw[$c], "@example"))) {
				//  for optimal performance this control sentence starts with
				//  only bool comparison then typecast+bool comparison and last a string comparison
				// examples will allways be the last tags in doclet and are <code> while visualized
				// hence no <br> tags allowed..
				$in_example = true; // set for following lines, @example's are last, allways
				$nl = "\n";
			} else if ($in_comment &&
					  (!((ord($raw[$c]{3}) == C_AT || ord($raw[$c]{3}) == C_LT)
					  || ($raw[$c + 1] && (ord($raw[$c + 1]{3}) == C_AT))))) {
				// while in comments, lines with @ or < at 4th position will
				// not have a <br> appended nor will a line that is followed
				// by a new jsdoc-tag (@); else:
				$nl = "<br>\n";
			} else {
				$nl = "\n";
			}
			//			echo "\n".$raw[$c]{3}."\n";
			if (isset($mod_buf))
				$sz .= $mod_buf . $nl;
			else
				$sz .= $raw[$c] . $nl;
			if ($in_comment_start) {
				$in_comment = true;
				$in_comment_start = false;
			}
		}

		$sz .= "" . OBJ_E;
		return preg_replace("/\ \ \ \ /s", "\t", $sz) . "\n";
	}

	/**
	 * validates against non-reserved and true-to-JS on variable names
	 * @param string $name
	 */
	private function validateVarName($name) {
		$conformity = preg_match("/^([a-zA-Z\$_][a-zA-Z0-9\$_]*[\.]?)+$/", $name);
		$reserved = !preg_match("/^(var|char|int|bool|boolean|void|null|undefined|if|for|else|while|do|switch|goto|continue|default|break|function|return|try|catch|throw|finally|with|in|debugger|instanceof|new|typeof|class|enum|extends|super|const|export|import)$/", $name);
		return $conformity && $reserved && !empty($name);
	}

	//			DocParse::$outputBuffer .= "@param " .
	//					  ($this->type == "" ? "{mixed}" : "{" . $this->type . "}") .
	//					  ($this->optional ? " [{$this->name}] " : " {$this->name} ") .
	//					  implode("\n * ", explode("\n", $this->desc)) . "\n * ";
	/**
	 * @returns an array of @ see or null if none found
	 * @param string $sz any string where a 'see something' is relevant to extract
	 */
	private function get_see($sz) {
		if (preg_match('/(http|https|ftp|ftps)\:\/\/[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}(\/\S*)?/', $sz, $matches)) {
			return "reference <a href=\"{$matches[0]}\">{$matches[0]}</a>";
		} else if (preg_match("/see\ [`\"']?(dojox|dijit|dojo)\.([a-zA-Z\$_][a-zA-Z0-9\$_]*)+\.([a-zA-Z\$_][a-zA-Z0-9\$_]*)([\(\)]*)?[`\"']?/", $sz, $matches)) {
			array_shift($matches); // shift complete match
			$_see = "";
			$class = (empty($matches[count($matches) - 1]));
			array_pop($matches);
			while ($s = array_shift($matches)) {
				if ($class)
					$_see .= $s . (count($matches) > 0 ? "." : "");
				else
					$_see .= $s . (count($matches) > 1 ? "." : (count($matches) != 0 ? "#" : ""));
			}
			return $_see;
		}
		return null;
	}

	/**
	 * tries to fix namespace due to parse of local
	 * variable names, referencing a global namespace
	 * @param string $mix ['#mixin'][X]
	 * @param string $propertyOf fully qualified prop id
	 */
	private function qualify_mixin($mix, $propertyOf) {
		global $nodes; //////// if generator.php changes this, should be changed accordingly
		//		$mixParts = explode(".", $mix);
		if ($nodes->open($mix, false)) {
			if (!preg_match("/^(dojox|dojo|dijit)/", $mix)) {
				$parentNamespace = array_shift(explode(".", $propertyOf));
				if ($nodes->open($parentNamespace . "." . $mix, false)) {
					return $parentNamespace . "." . $mix;
				}
			} else
				return $mix;
		}
		return false;
	}

	/**
	 * outputs @ example in a loop over any available entry
	 * @param object works on $objectNode[...]['#method'][X]['#examples'][0]['#example']
	 */
	private function documentExamples($object) {

		foreach ($object as $example) {
			//					var_dump($example);
			//		throw new Exception("example parse");
			$this->contentbuffer[] = DOCU_L . "@example";
			$set = explode("\n", $example['content']);
			foreach ($set as $l)
				$this->contentbuffer[] = DOCU_L . htmlentities($l, ENT_NOQUOTES);
		}
	}

	/**
	 * Can receive any of $objectNode[...]['#method'][0], $objectNode
	 * If any #summary this is wrapped in an indented, headlined block.
	 * All descriptions are parsed for URL's and 'see dojo.query' resulting
	 * in references, well suited for htmldocs
	 * @param object Works with any description set, namingly namespaces/classes/methods
	 */
	private function documentDescription($object) {
		$see = array();
		$text = "";
		if ($object['#summary']) {
			// look for @see
			$_see = $this->get_see($object['#summary'][0]['content']);
			if ($_see)
				$see[] = $_see;
			$this->contentbuffer[] = DOCU_L . "<b>Summary:</b><blockquote>";
			foreach (explode("\n", $object['#summary'][0]['content']) as $l) {
				$this->contentbuffer[] = DOCU_L . "    " . htmlentities($l, ENT_NOQUOTES);
			}
			$this->contentbuffer[] = DOCU_L . "</blockquote>";
		}
		if ($object['#description']) {
			// look for @see
			$_see = $this->get_see($object['#description'][0]['content']);
			if ($_see)
				$see[] = $_see;

			foreach (explode("\n", $object['#description'][0]['content']) as $l) {
				$this->contentbuffer[] = DOCU_L . htmlentities($l, ENT_NOQUOTES);
			}
		}

		foreach ($see as $s)
			$this->contentbuffer[] = DOCU_L . "@see $s";
	}

	/**
	 *
	 * outputs @ param and returns the 'arguments' for prototype paranthesis
	 * @param parameters works with ['#methods'][0]['#method'][X]['#parameters'][0]
	 */
	private function documentParameters($parameters) {
		$i = 0;
		$plen = count($parameters);
		$inlineparams = "";
		foreach ($parameters as $param) {
			$line = "";
			$description = null;
			$optional = (isset($param['@usage']) && $param['@usage'] == "optional");
			if (empty($param['@type'])) {
				$param['@type'] = "mixed";
			}
			if (empty($param['@name'])) {
				$param['@name'] = "unknown";
			}
			// very specific IDE scope for this feature
//			if ($param['@type'] != "mixed" || $optional) {
//				$inlineparams .= "/*" . $param['@type'] . ($optional ? " Optional*/" : "*/") .
//						  " " . $param['@name'];
//			} else {
			$inlineparams .= $param['@name'];
//			}
			$inlineparams .= ($i < $plen - 1 ? ", " : "");
			$line .= "@param {" . $param['@type'] . "} " .
					  ($optional ? "[" : "") .
					  $param['@name'] .
					  ($optional ? "] " : " ");
			if ($param['#summary']) {
				$description = explode("\n", $param['#summary'][0]['content']);
				$line .= array_shift($description);
				$this->contentbuffer[] = DOCU_L . $line;
				foreach ($description as $l)
					$this->contentbuffer[] = DOCU_L . $l;
			} else {
				$this->contentbuffer[] = DOCU_L . (empty($line) ? "var of type {$param['@type']}" : $line);
			}
			$i++; // inc comma count
		}
		return $inlineparams;
	}

	/**
	 *
	 * @param string $propOf which location has this property
	 * @param array $object $objectNode['#properties'][0]['#property'][X]
	 * @param boolean [$isContruct] optional, sent from documentConstruct contexts
	 */
	private function documentProperty($propOf, $object, $isContruct = false) {
		if ($this->contents_processed[$propOf . "." . $object['@name']] == 1)
			return;

		if ($object['@type'] == "Object") {
			$this->documentNamespace($propOf . "." . $object['@name'], $object);
		} else if ($object['@type'] != null) {
			//
			if ($property['#summary']) {
				$description = explode("\n", $property['#summary'][0]['content']);
			}
			$this->contentbuffer[] = DOCU_S;
			$this->documentDescription($property);
			//			$this->contentbuffer[] = DOCU_L . "@property {$method['@type']} $propOf";
			$this->contentbuffer[] = DOCU_L . "@memberOf $propOf";
			if (!$this->everythingpublic && $property['@private'])
				$this->contentbuffer[] = DOCU_L . "@private";
			$this->contentbuffer[] = DOCU_E;
			switch ($object['@type']) {
				case 'Array' : $eq = "[];";
					break;
				case 'Number' : $eq = "0;";
					break;
				case 'Object' : $eq = "{};";
					break;
				case 'String' : $eq = "'';";
					break;
				case 'Boolean' : $eq = "false;";
					break;
				default: $eq = "null;";
			}
			$this->contentbuffer[] = $propOf . "." . $object['@name'] . "=$eq";
			$this->contents_processed[$propOf] = 1;
		}
	}

	/**
	 * works with ['#methods'][0]['#method'][X]
	 * outputs a method doclet as @memberOf parent property
	 * has an optional parameter for flowcontrol, which is true when
	 * documenting a method which is a member of a 'classlike'
	 * should modulesonly be set from CLI, this will return without output
	 * if currently processed object aint class
	 */
	private function documentMethod($qualifiedName, $method, $isConstruct = false) {

		if (!$isConstruct && $this->modulesonly)
			return;
		else if ($this->contents_processed[$qualifiedName] == 1)
			return;
		if (!isset($method['@name']))
			return;
		$this->contentbuffer[] = DOCU_S;
		$this->documentDescription($method);

		if ($method['#parameters']) {
			$inlineparams = $this->documentParameters($method['#parameters'][0]['#parameter']);
		}

		if (!$this->everythingpublic && $method['@private'])
			$this->contentbuffer[] = DOCU_L . "@private";

		if ($method['#return-types']) {
			$types = array();
			$description = array();
			foreach ($method['#return-types'][0]['#return-type'] as $retype)
				$types[] = $retype['@type'];
			if ($method['#return-description']) {
				$description = explode("\n", $method['#return-description'][0]['content']);
				$line .= " " . array_shift($description);
				$this->contentbuffer[] = DOCU_L . $l;
			}
			$this->contentbuffer[] = DOCU_L . "@returns {" . implode("|", $types) . "} -" . array_shift($description);
			while (($l = array_shift($description)) != null)
				$this->contentbuffer[] = DOCU_L . $l;
		}
		if ($this->add_at_method)
			$this->contentbuffer[] = DOCU_L . "@method";
		if ($method['#examples'])
			$this->documentExamples($method['#examples'][0]['#example']);

		if ($method['@scope'] && $method['@scope'] == "prototype") {
			$parts = explode(".", $qualifiedName);
			$methodName = ".prototype." . array_pop($parts);
			$methodName = implode(".", $parts) . $methodName;
			$this->contentbuffer[] = DOCU_L . "@memberOf " . implode(".", $parts);
		} else {
			$methodName = $qualifiedName;
		}

		$this->contentbuffer[] = DOCU_E;
		//		if ($this->validateVarName($qualifiedName))
		$this->contentbuffer[] = "{$methodName}=function($inlineparams){};";
		$this->contents_processed[$qualifiedName] = 1;
	}

	private function documentConstructor($propertyOf, $object) {
		//					$method['#method']['@name']
		//					$method['#method']['@scope']
		//					$method['#method']['@private']
		//					$method['#method']['#summary']
		//					$method['#method']['#examples'][]

		$this->contentbuffer[] = DOCU_S;
		$this->documentDescription($object);


//		$this->contentbuffer[] = DOCU_L . "@constructor";
		$this->contentbuffer[] = DOCU_L . "@class";
		if ($object['@superclass']) {
			$mix['@scope'] = array();
			if ($object['#mixins'])
				foreach ($object['#mixins'] as $mix)
					if ($mix['@scope'] && $mix['@scope'] == "instance")
						foreach ($mix['#mixin'] as $inherits)
							$mixins[] = $inherits;
			if (is_array($mixins)) {
				array_unique($mixins);
				foreach ($mixins as $mix) {
					if (($mix = $this->qualify_mixin($mix['@location'], $propertyOf)))
						$this->contentbuffer[] = DOCU_L . "@extends $mix";
				}
			} else {
				if (($mix = $this->qualify_mixin($object['@superclass'], $propertyOf)))
					$this->contentbuffer[] = DOCU_L . "@extends $mix";
			}
		}
		if (!$this->everythingpublic && $object['@private'])
			$this->contentbuffer[] = DOCU_L . "@private";


		// get the parameters from 'constructor' method
		if ($object['#methods']) {
			foreach ($object['#methods'][0]['#method'] as $method) {
				if ($method['@name'] == "constructor" && $method['#parameters']) {
					$inlineparams = $this->documentParameters($method['#parameters'][0]['#parameter']);
				}
			}
		}
		$this->contentbuffer[] = DOCU_L . "@returns {{$propertyOf}} new instance";
		if ($object['#examples'])
			$this->documentExamples($object['#examples'][0]['#example']);
		$this->contentbuffer[] = DOCU_E;
		$this->contentbuffer[] = "{$propertyOf}=function($inlineparams){};";
		$this->contents_processed[$propertyOf] = 1;
		// prototype any methods that are not the constructor

		if ($object['#properties']) {
			foreach ($object['#properties'][0]['#property'] as $property) {
				$this->documentProperty($propertyOf, $property);
			}
		}
		if ($object['#methods']) {
			foreach ($object['#methods'][0]['#method'] as $method) {
				if ($method['@name'] != "constructor") {
					$this->documentMethod($propertyOf . "." . $method['@name'], $method, true);
				}
			}
		}
	}

	private function documentNamespace($propertyOf, $object) {
		if ($this->contents_processed[$propertyOf] == 1)
			return;
		$this->contentbuffer[] = DOCU_S;
		// subspace
		$this->documentDescription($method);
		//		if ($object['#provides']) {
		//			$this->contentbuffer[] = DOCU_L . "@namespace";
		//			foreach ($object['#resources'][0]['#resource'] as $r)
		//				$this->contentbuffer[] = DOCU_L . "@requires " . $r['content'];
		//		} else
		$this->contentbuffer[] = DOCU_L . "@namespace";

		if (isset($object['@private']))
			$this->contentbuffer[] = DOCU_L . "@private";
		$this->contentbuffer[] = DOCU_E;
		$this->contentbuffer[] = "$propertyOf={};";
		$this->contents_processed[$propertyOf] = 1;
	}

	public function document($object) {
		$parts = explode(".", $object['@location']);
		$this->contentbuffer = array();
		if (count($parts) == 1) {
			if (in_array($parts[0], array("dojo", "dijit", "dojox"))) {
				$propOf = $this->currentModule = $parts[0];
			} else {
				$propOf = $this->currentModule . "." . $parts[0];
			}
		} else {
			$propOf = $object['@location'];
		}
		/**
		 *
		 * Evaluate what to do
		 *
		 */
		if (empty($object['@type'])) {
			if ((!empty($object['#methods']) || !empty($object['#properties'])))
				$this->documentNamespace($propOf, $object);
		} else if ($object['@type'] == 'Function') {
			if (isset($object['@classlike'])) {
				$this->documentConstructor($propOf, $object);
				return $this->contentbuffer;
			} else {
				$this->documentMethod($propOf, $object);
			}
		} else if ($object['@type'] == "Object") {
			// is this correct?
			$this->documentProperty(substr($propOf, 0, strrpos($propOf, ".")), $object);
		}

		/**
		 *
		 * Ok, loop methods attached to 'namespace' if a such,
		 * documentConstructor returns above and handles this seperately
		 */
		if (isset($object['#methods'])) {
			foreach ($object['#methods'][0]['#method'] as $method) {
				$this->documentMethod("{$propOf}.{$method['@name']}", $method);
			}
		}
		if (isset($object['#properties'])) {
			foreach ($object['#properties'][0]['#property'] as $property) {
				$parts = explode(".", $object['@location']);
				if (count($parts) == 1) {
					if (in_array($parts[0], array("dojo", "dijit", "dojox"))) {
						$propOf = $this->currentModule = $parts[0];
					} else {
						$propOf = $this->currentModule . "." . $parts[0];
					}
				} else {
					$propOf = $object['@location'];
				}
				$this->documentProperty($propOf, $property);
			}
		}
		return $this->contentbuffer;
	}

	/**
	 * overridden toAbstract method simply to clean up a bit of non-needed entries
	 * @param unknown_type $object
	 * @param unknown_type $id
	 */
	//	public function toAbstract($object, $id) {
	//		// who needs these.. we're last in serialization chain anyways, rid of memory leechers
	//		unset($object['#resources']);
	//		unset($object['#provides']);
	//		return parent::toAbstract($object, $id);
	//	}

	public function convertToRaw($object) {
		return $this->document($object);
//		unset($this->contentbuffer);
//		$this->contentbuffer = array();
	}

}

?>
