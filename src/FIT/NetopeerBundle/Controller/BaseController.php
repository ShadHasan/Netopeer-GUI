<?php
/**
 * BaseController as parent of  all controllers in this bundle handles all common functions
 * such as assigning template variables, menu structure...
 *
 * @author David Alexa
 */
namespace FIT\NetopeerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session;

// these import the "@Route" and "@Template" annotations
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

/**
 * BaseController - parent of all other controllers in this Bundle.
 *
 * Defines common functions for all controllers, such as assigning template variables etc.
 */
class BaseController extends Controller
{
	/**
	 * @var int  Active section key
	 */
	private $activeSectionKey;
	/**
	 * @var string  url of submenu
	 */
	private $submenuUrl;
	/**
	 * @var array   array of all variables assigned into template
	 */
	private $twigArr;

	/**
	 * Assignees variable to array, which will be send to template
	 * @param  mixed $key   key of the associative array
	 * @param  mixed $value value of the associative array
	 */
	protected function assign($key, $value) {
		$this->twigArr[$key] = $value;
	}

	/**
	 * Prepares variables to template, sort flashes and prepare menu
	 * @return array					array of assigned variables to template
	 */
	protected function getTwigArr() {

		if ( $this->getRequest()->getSession()->get('singleColumnLayout') == null ) {
			$this->getRequest()->getSession()->set('singleColumnLayout', true);
		}

		// if singleColumnLayout is not set, we will set default value
		if ( !array_key_exists('singleColumnLayout', $this->twigArr) ) {
			$this->assign('singleColumnLayout', $this->getRequest()->getSession()->get('singleColumnLayout'));
		}

		$session = $this->getRequest()->getSession();
		$flashes = $session->getFlashes();
		$stateFlashes = $configFlashes = $leftPaneFlashes = $singleFlashes = $allFlashes = array();

		// divide flash messages according to key into categories
		foreach ($flashes as $key => $message) {
			// a little bit tricky - if key contains word state, condition will be pass
			if ( strpos($key, 'tate') ) { // key contains word state
				$stateFlashes[$key] = $message;
			} elseif ( strpos($key, 'onfig') ) { // key contains word config
				$configFlashes[$key] = $message;
			} elseif ( strpos($key, 'eftPane') ) { // key contains word leftPane
				$leftPaneFlashes[$key] = $message;
			} else { // key contains word single
				$singleFlashes[$key] = $message;
			}

			$allFlashes[$key] = $message;
			$session->removeFlash($key);
		}

		$this->assign("stateFlashes", $stateFlashes);
		$this->assign("configFlashes", $configFlashes);
		$this->assign("leftPaneFlashes", $leftPaneFlashes);
		$this->assign("singleFlashes", $singleFlashes);
		$this->assign("allFlashes", $allFlashes);

		$dataClass = $this->get('DataModel');
		$dataClass->buildMenuStructure($this->activeSectionKey);
		$this->assign('topmenu', $dataClass->getModels());
		$this->assign('submenu', $dataClass->getSubmenu($this->submenuUrl));

		try {
			if ($this->getRequest()->get('key') != "") {
				$conn = $session->get('session-connections');
				$conn = unserialize($conn[$this->getRequest()->get('key')]);
				if ($conn !== false) {
					$this->assign('lockedConn', $conn->locked);
					$this->assign('sessionStatus', $conn->sessionStatus);
				}
			}
		} catch (\ErrorException $e) {
			$this->get('logger')->notice('Trying to use foreign session key', array('error' => $e->getMessage()));
			$this->getRequest()->getSession()->setFlash('error', "Trying to use unknown connection. Please, connect to the device.");
		}

		return $this->twigArr;
	}

	/**
	 * constructor, which instantiate empty class variables
	 */
	public function __construct () {
		$this->twigArr = array();	
		$this->activeSectionKey = null;	
	}

	/**
	 * sets current section key
	 *
	 * @param int     $key          key of connected server
	 */
	public function setActiveSectionKey($key) {
		$this->activeSectionKey = $key;
	}

	/**
	 * sets submenu URL.
	 *
	 * @param string $submenuUrl  URL for submenu
	 */
	public function setSubmenuUrl($submenuUrl) {
		$this->submenuUrl = $submenuUrl;
	}

}
