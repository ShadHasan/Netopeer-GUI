<?php
/**
Copyright (c) 2014, hareko
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this
list of conditions and the following disclaimer.

Redistributions in binary form must reproduce the above copyright notice, this
list of conditions and the following disclaimer in the documentation and/or
other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

 */
namespace FIT\NetopeerBundle\Models;

/**
 * XML merging class
 * Merge multiple XML sources
 *
 * @package     MergeXML
 * @author      Vallo Reima
 * @copyright   (C)2014
 */
class MergeXML {
  private $cln = 'DOMDocument';
  private $dom;       /* result DOM object */
  private $dxp;       /* result xPath object */
  private $nsp;       /* namespaces list */
  private $nsd = '_'; /* default namespace prefix */
  private $stay;      /* overwrite protection */
  private $clon;     /* clone instead of overwrite */
  private $join;      /* joining root name */
  private $updn;      /* update nodes sequentially by name */
  private $count = 0; /* adding counter */
  private $error;     /* error info */
  /**
   * set (default) options, create result object
   * @param array $opts -- stay, join, updn
   */
  public function __construct($opts = array()) {
    if (!isset($opts['stay'])) {
      $this->stay = array('all');
    } else if (is_array($opts['stay'])) {
      $this->stay = $opts['stay'];
    } else {
      $this->stay = (array) $opts['stay'];
    }
    if (!isset($opts['clone'])) {
      $this->clon = array();
    } else if (is_array($opts['clone'])) {
      $this->clon = $opts['clone'];
    } else {
      $this->clon = (array) $opts['clone'];
    }
    $this->join = !isset($opts['join']) ? 'root' : (string) $opts['join'];
    $this->updn = !isset($opts['updn']) ? true : (bool) $opts['updn'];
    $this->error = (object) array('code' => '', 'text' => '');
    if (class_exists($this->cln)) {
      $this->dom = new $this->cln();
      $this->dom->preserveWhiteSpace = false;
      $this->dom->formatOutput = true;
    } else {
      $this->Error('nod');
    }
  }

  /**
   * add XML file
   * @param string $file -- pathed filename
   * @param string|array $stay
   * @return object|false
   */
  public function AddFile($file, $stay = null, $clone = null) {
    if (is_array($stay)) {
      $this->stay = array_merge($this->stay, $stay);
    } else if (!empty($stay)) {
      $this->stay[] = $stay;
    }
    if (is_array($clone)) {
      $this->clon = array_merge($this->clon, $clone);
    } else if (!empty($clone)) {
      $this->clon[] = $clone;
    }
    $data = @file_get_contents($file);
    if ($data === false) {
      $rlt = $this->Error('nof');
    } else if (empty($data)) {
      $rlt = $this->Error('emf');
    } else {
      $rlt = $this->AddSource($data);
    }
    return $rlt;
  }

  /**
   * add XML to result object
   * @param string|object $xml
   * @param string|array $stay
   * @return mixed -- false - bad content
   *                  object - result
   */
  public function AddSource($xml, $stay = null, $clone = null) {
    if (is_array($stay)) {
      $this->stay = array_merge($this->stay, $stay);
    } else if (!empty($stay)) {
      $this->stay[] = $stay;
    }
    if (is_array($clone)) {
      $this->clon = array_merge($this->clon, $clone);
    } else if (!empty($clone)) {
      $this->clon[] = $clone;
    }
    if (is_object($xml)) {
      if (get_class($xml) != $this->cln) {
        $dom = false;
      } else if ($this->dom->hasChildNodes()) {
        $dom = $xml;
      } else {
        $this->dom = $xml;
        $this->dom->formatOutput = true;
        $dom = true;
      }
    } else if ($this->dom->hasChildNodes()) { /* not first */
      $dom = new $this->cln();
      $dom->preserveWhiteSpace = false;
      if (!@$dom->loadXML($xml)) {
        $dom = false;
      }
    } else { /* first slot */
      $dom = @$this->dom->loadXML($xml) ? true : false;
    }
    if ($dom === false) {
      $rlt = $this->Error('inv');
    } else if ($dom === true && $this->NameSpaces()) {
      $this->count = 1;
      $rlt = $this->dom;
    } else if (is_object($dom) && $this->CheckSource($dom)) {
      $this->Merge($dom, '/');  /* add to existing */
      $this->count++;
      $rlt = $this->dom;
    } else {
      $rlt = false;
    }
    return $rlt;
  }

  /**
   * check/modify root and namespaces,
   * @param object $dom source
   * @return {bool}
   */
  private function CheckSource(&$dom) {
    $rlt = true;
    if ($dom->encoding != $this->dom->encoding) {
      $rlt = $this->Error('enc');
    } else if ($dom->documentElement->namespaceURI != $this->dom->documentElement->namespaceURI) { /* $dom->documentElement->lookupnamespaceURI(NULL) */
      $rlt = $this->Error('nse');
    } else if ($dom->documentElement->nodeName != $this->dom->documentElement->nodeName) {
      if (!$this->join) {
        $rlt = $this->Error('dif');
      } else if (is_string($this->join)) {
        $doc = new DOMDocument();
        $doc->encoding = $this->dom->encoding;
        $doc->preserveWhiteSpace = false;
        $doc->formatOutput = true;
        $xml = "<?xml version=\"{$this->dom->xmlVersion}\" encoding=\"{$this->dom->encoding}\"?><$this->join></$this->join>";
        if (@$doc->loadXML($xml)) {
          $tmp = $doc->importNode($this->dom->documentElement, true);
          $doc->documentElement->appendChild($tmp);
          $this->dom = $doc;
          $this->join = true;
        } else {
          $rlt = $this->Error('jne');
          $this->join = null;
        }
      }
    }
    if ($rlt) {
      $doc = simplexml_import_dom($dom);
      $rlt = $this->NameSpaces($doc->getDocNamespaces(true));
    }
    return $rlt;
  }

  /**
   * register namespaces
   * @param array $nsp -- additional namespaces
   * @return bool
   */
  private function NameSpaces($nsp = array()) {
    $doc = simplexml_import_dom($this->dom);
    $nsps = $doc->getDocNamespaces(true);
    foreach ($nsp as $pfx => $url) {
      if (!isset($nsps[$pfx])) {
        $this->dom->createAttributeNS($url, "$pfx:attr");
        $nsps[$pfx] = $url;
      }
    }
    $this->dxp = new \DOMXPath($this->dom);
    $this->nsp = array();
    $rlt = true;
    foreach ($nsps as $pfx => $url) {
      if ($pfx == $this->nsd) {
        $rlt = $this->Error('nse');
        break;
      } else if (empty($pfx)) {
        $pfx = $this->nsd;
      }
      $this->nsp[$pfx] = $url;
      $this->dxp->registerNamespace($pfx, $url);
    }
    return $rlt;
  }

  /**
   * join 2 dom objects
   * @param object $src -- current source node
   * @param object $pth -- current source path
   */
  private function Merge($src, $pth) {
    $i = 0;
    foreach ($src->childNodes as $node) {
      $path = $this->GetNodePath($src->childNodes, $node, $pth, $i);
      $obj = $this->Query($path);
      if ($node->nodeType === XML_ELEMENT_NODE) {
        /* replace existing node by default */
    	  $flg = (array_search($obj->item(0)->tagName, $this->stay) !== false) ? false : true;
        /* not clone existing node by default */
		    $cln = (array_search($obj->item(0)->tagName, $this->clon) === false) ? false : true;
        if ($obj->length == 0 || $obj->item(0)->namespaceURI != $node->namespaceURI || $cln) { /* add node */
          $tmp = $this->dom->importNode($node, true);
          $this->Query($pth)->item(0)->appendChild($tmp);
        } else if ($flg && !$cln){
    			if ($node->hasAttributes()) { /* add/replace attributes */
    				foreach ($node->attributes as $attr) {
    					$obj->item(0)->setAttribute($attr->nodeName, $attr->nodeValue);
    				}
    			}
    			if ($node->hasChildNodes()) {
    				$this->Merge($node, $path); /* recurse to subnodes */
    			}
        }
      } else if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_COMMENT_NODE) { /* leaf node */
        if ($obj->length == 0) {
          if ($node->nodeType === XML_TEXT_NODE) {
            $tmp = $this->dom->createTextNode($node->nodeValue);
          } else {
            $tmp = $this->dom->createComment($node->nodeValue);
          }
          $this->Query($pth)->item(0)->appendChild($tmp);
        } else {
          $obj->item(0)->nodeValue = $node->nodeValue;
        }
      }
      $i++;
    }
  }

  /**
   * form the node xPath expression
   * @param {object} $nodes -- child nodes
   * @param {object} $node -- current child
   * @param {string} $pth -- parent path
   * @param {int} $eln -- element sequence number
   * @return {string} query path
   */
  private function GetNodePath($nodes, $node, $pth, $eln) {
    $j = 0;
    if ($node->nodeType === XML_ELEMENT_NODE) {
      $i = 0;
      foreach ($nodes as $nde) {
        if ($i > $eln) {
          break;
        } else if (($this->updn && $nde->nodeType === $node->nodeType && $nde->nodeName === $node->nodeName && $nde->namespaceURI === $node->namespaceURI) ||
                (!$this->updn && $nde->nodeType !== XML_PI_NODE)) {
          $j++;
        }
        $i++;
      }
      if ($this->updn) {
        if ($node->prefix) {
          $p = $node->prefix . ':';
        } else if (isset($this->nsp[$this->nsd])) {
          $p = $this->nsd . ':';
        } else {
          $p = '';
        }
        $p .= $node->localName;
      } else {
        $p = 'node()';
      }
    } else if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_COMMENT_NODE) {
      $i = 0;
      foreach ($nodes as $nde) {
        if ($i > $eln) {
          break;
        } else if ($nde->nodeType === $node->nodeType) {
          $j++;
        }
        $i++;
      }
      $p = $node->nodeType === XML_TEXT_NODE ? 'text()' : 'comment()';
    } else {
      $p = $pth;
    }
    if ($j > 0) {
      $p = $pth . ($pth === '/' ? '' : '/') . $p . '[' . $j . ']';
    }
    return $p;
  }

  /**
   * xPath query
   * @param string $qry -- query statement
   * @return object
   */
  public function Query($qry) {
    if ($this->join === true) {
      $qry = "/{$this->dom->documentElement->nodeName}" . ($qry === '/' ? '' : $qry);
    }
    $rlt = $this->dxp->query($qry);
    return $rlt;
  }

  /**
   * get result
   * @param {int} flg -- 0 - object
   *                     1 - xml
   *                     2 - html
   * @return {mixed}
   */
  public function Get($flg = 0) {
    if ($flg == 0) {
      $rlt = $this->dom;
    } else {
      $rlt = $this->dom->saveXML();
      if ($flg == 2) {
        $r = str_replace(' ', '&nbsp;', htmlspecialchars($rlt));
        $rlt = str_replace(array("\r\n", "\n", "\r"), '<br />', $r);
      }
    }
    return $rlt;
  }

  /**
   * set error message
   * @param string $err token
   * @return false
   */
  private function Error($err = 'und') {
    $errs = array(
        'nod' => "$this->cln is not supported",
        'nof' => 'File not found',
        'emf' => 'File is empty', /* possible delivery fault */
        'inv' => 'Invalid XML source',
        'enc' => 'Different encoding',
        'dif' => 'Different root nodes',
        'jne' => 'Invalid join parameter',
        'nse' => 'Namespace incompatibility',
        'und' => 'Undefined error');
    $this->error->code = isset($errs[$err]) ? $err : 'und';
    $this->error->text = $errs[$this->error->code];
    return false;
  }

  /**
   * get property value
   * @param string $name
   * @return mixed -- null - missing
   */
  public function __get($name) {
    return isset($this->$name) ? $this->$name : null;
  }

}

?>
