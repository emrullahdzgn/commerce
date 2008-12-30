<?php
/**
 * Implements a browseable AJAX tree
 
 * @author 		Marketing Factory <typo3@marketing-factory.de>
 * @maintainer 	Erik Frister <typo3@marketing-factory.de>
 **/
abstract class browsetree {
	
	/**
	 * Variables
	 */
	protected $treeName;		//Name of the table
	protected $noClickmenu;		//Should the clickmenu be disabled?
	
	// -- internal --
	protected $leafs;			//The Leafs of the tree
	protected $isInit;			//has the tree already been initialized?
	protected $leafcount; 		//Number of leafs in the tree
	protected $renderBy;		//will hold the rendering method of the tree
	protected $startingUid;		//the uid from which to start rendering recursively, if we so chose to
	protected $depth;			//the recursive depth to choose if we chose to render recursively
	
	/**
	 * Constructor - init values
	 * 
	 * @return {void}
	 */
	public function __construct() {
		$this->leafs 	 	= array();
		$this->leafcount 	= 0;
		$this->isInit	 	= false;	
		$this->noClickmenu 	= false;	//enable clickmenu by default
		$this->renderBy		= 'mounts'; // render by mounts is default
		$this->startingUid	= 0;
		
		//NEVER initialize treeName with ''!! it could be overwritten by the extending class
	}
	
	/**
	 * Initializes the Browsetree
	 * 
	 * @return {void}
	 */
	public function init() {
		$this->isInit = true;
	}
	
	/**
	 * Sets the clickmenu flag for the tree
	 * Gets passed along to all leafs, which themselves pass it to their view
	 * Has to be set BEFORE initializing the tree with init()
	 * 
	 * @return {void}
	 * @param $flag {bool}[optional]	Flag
	 */
	public function noClickmenu($flag = true) {
		if(!is_bool($flag)) {
			if (TYPO3_DLOG) t3lib_div::devLog('noClickmenu (browsetree) gets a non-boolean parameter (expected boolean)!', COMMERCE_EXTkey, 2);	
		}
		$this->noClickmenu = $flag;
	}
	
	/**
	 * Adds a leaf to the Tree
	 * 
	 * @param {object} $leaf - Treeleaf Object which holds the LeafData and the LeafView
	 * @return {boolean}
	 */
	function addLeaf(leafMaster &$leaf) {
		if(null == $leaf) {
			if (TYPO3_DLOG) t3lib_div::devLog('addLeaf (browsetree) has an invalid parameter. The leaf was not added.', COMMERCE_EXTkey, 3);	
			return false;
		}
		
		//pass tree vars to the new leaf
		$leaf->setTreeName($this->treeName);
		$leaf->noClickmenu($this->noClickmenu);
	
		//add to collection
		$this->leafs[$this->leafcount ++] = $leaf;
		
		return true;
	}
	
	/**
	 * Returns the leaf object at the given index
	 * 
	 * @return {object} 
	 * @param $index {int}	Leaf index
	 */
	public function getLeaf($index) {
		if(!is_numeric($index) || !isset($this->leafs[$index])) {
			if (TYPO3_DLOG) t3lib_div::devLog('getLeaf (browsetree) has an invalid parameter.', COMMERCE_EXTkey, 3);	
			return null;
		}
		
		return $this->leafs[$index];
	}
	
	/**
	 * Sets the unique tree name
	 * 
	 * @return {void}
	 * @param {string} $table - Name of the Tree
	 */
	function setTreeName($tree = '') {
		$this->treeName = $tree;
	}
	
	/**
	 * Sets the internal rendering method to 'mounts'
	 * Call BEFORE initializing
	 * 
	 * @return {void} 
	 */
	public function readByMounts() {
		
		//set internal var
		$this->renderBy = 'mounts';
	}
	
	/**
	 * Sets the internal rendering method to 'recursively'
	 * Call BEFORE initializing
	 * 
	 * @return {void} 
	 * @param $uid {int}	UID from which the masterleafs should start
	 */
	public function readRecursively($uid, $depth = 100) {
		if(!is_numeric($uid)) {
			if (TYPO3_DLOG) t3lib_div::devLog('readRecursively (browsetree) has an invalid parameter.', COMMERCE_EXTkey, 3);	
			return;	
		}
		
		//set internal vars
		$this->renderBy 	= 'recursively';
		$this->depth		= $depth;
		$this->startingUid 	= $uid;
	}
	
	/**
	 * Returns a browseable Tree
	 * Tree is automatically generated by using the Mountpoints and the User position
	 * 
	 * @param {boolean} $useMountpoints - Will use the Mountpoints and Userposition to enhance the speed instead of recursively searching for Categories
	 * @return {string}
	 */
	function getBrowseableTree() {
		switch($this->renderBy) {
			case 'mounts':
				$this->getTreeByMountpoints();
				return $this->printTreeByMountpoints();
				break;
				
			case 'recursively':
					
				$this->getTree();
				return $this->printTree();
				break;
				
			default:
				if (TYPO3_DLOG) t3lib_div::devLog('The Browseable Tree could not be printed. No rendering method was specified', COMMERCE_EXTkey, 3);
				return '';
				break;
		}
	}
	
	/**
	 * Returns a browseable Tree (only called by AJAX)
	 * Note that so far this is only supported if you work with mountpoints;
	 * 
	 * @todo	Make it possible as well for a recursive tree
	 * 
	 * @return {string}			HTML Code for Tree
	 * @param $PM {array}		Array from PM link
	 */
	function getBrowseableAjaxTree($PM) {
		if(is_null($PM) || !is_array($PM)) {
			if (TYPO3_DLOG) t3lib_div::devLog('The browseable AJAX tree (getBrowseableAjaxTree) was not printed because a parameter was invalid.', COMMERCE_EXTkey, 3);
			return '';	
		}

		//Create the tree by mountpoints
		$this->getTreeByMountpoints();
		
		return $this->printAjaxTree($PM);
	}
	
	/**
	 * Forms the tree based on the mountpoints and the user positions
	 * 
	 * @return {void}
	 */
	function getTreeByMountpoints() {
		//Alternate Approach: Read all open Categories at once
		//Select those whose parent_id is set in the positions-Array
		//and those whose UID is set as the Mountpoint
		
		//Get the current position of the user
		$this->initializePositionSaving();
		
		//Go through the leafs and feed them the ids
		for($i = 0; $i < $this->leafcount; $i ++) {
			$this->leafs[$i]->byMounts();
			$this->leafs[$i]->init($i); //Pass $i as the leaf's index
		}
	}
	
	/**
	 * Forms the tree
	 * 
	 * @return {void}
	 */
	function getTree() {
		$uid 	= $this->startingUid;
		$depth 	= $this->depth;
		
		//Go through the leafs and feed them the id
		for($i = 0; $i < $this->leafcount; $i ++) {
			$this->leafs[$i]->setUid($uid);
			$this->leafs[$i]->setDepth($depth);
			
			$this->leafs[$i]->init($i);
		}
	}
	
	/**
	 * Prints the subtree for AJAX requests only
	 * 
	 * @return {string} HTML Code
	 * @param $PM 	Array from PM link
	 */
	function printAjaxTree($PM) {
		if(is_null($PM) || !is_array($PM)) {
			if (TYPO3_DLOG) t3lib_div::devLog('The AJAX Tree (printAjaxTree) was not printed because the parameter was invalid.', COMMERCE_EXTkey, 3);
			return '';	
		}
		
		$l = count($PM);
		
		$values 	= explode('|', $PM[count($PM)-1]); 	//parent|ID is always the last Item
		
		$id 		= $values[0];			//assign current uid
		$pid 		= $values[1];			//assign item parent
		$bank 		= $PM[2];				//Extract the bank
		$indexFirst = $PM[1];
		
		$out = '';
		
		//Go to the correct leaf and print it
		$leaf = &$this->leafs[$indexFirst];
		
		//i = 4 because if we have childleafs at all, this is where they will stand in PM Array
		//l - 1 because the last entry in PM is the id
		for($i = 4; $i < $l - 1; $i ++) {
			$leaf = &$leaf->getChildLeaf($PM[$i]);
			
			//If we didnt get a leaf, return
			if(null == $leaf) return '';
		}
		
		//$out .= $leaf->printLeafByUid($id, $bank, $this->treeName);
		$out .= $leaf->printChildleafsByLoop($id, $bank, $pid);
		
		return $out;
	}
	
	/**
	 * Prints the Tree starting with the uid
	 * 
	 * @todo Implement this function if it is ever needed. So far it's not. Delete this function if it is never needed.
	 * @return {string}
	 * @param $uid {int} - UID of the Item that will be started with
	 */
	function printTree($uid) {
		die("The function printTree in tx_commerce_browsetree.php is not yet filled. Fill it if you are using it.'.
		Search for this text to find the code.");
	}
	
	/**
	 * Prints the Tree by the Mountpoints of each treeleaf
	 * 
	 * @return {string}		HTML Code for Tree
	 */
	function printTreeByMountpoints() {
		$out 	= '';
		$mount 	= null;

		$out .= '<ul class="tree">';
		
		//Get the Tree for each leaf
		for($i = 0; $i < $this->leafcount; $i ++) {
			$out .= $this->leafs[$i]->printLeafByMounts();
		}
		$out .= '</ul>';
		
		return $out;
	}
	
	/**
	 * Returns the Records in the tree as a array
	 * Records will be sorted to represent the tree in linear order
	 * 
	 * @param {int} $uid - UId of the Item that will act as the root of the tree
	 * @return {array} 
	 */
	function getRecordsAsArray($rootUid) {
		if(!is_numeric($rootUid)) {
			if (TYPO3_DLOG) t3lib_div::devLog('getRecordsAsArray has an invalid $rootUid', COMMERCE_EXTkey, 3);
			return array();	
		}
		
		//Go through the leafs and get sorted array
		$l = count($this->leafs);
		
		$sortedData = array();
		
		//Initialize the categories (and its leafs)
		for($i = 0; $i < $l; $i ++) {
			
			if($this->leafs[$i]->data->hasRecords()) {
				$this->leafs[$i]->sort($rootUid);
				$sortedData = array_merge($sortedData, $this->leafs[$i]->getSortedArray());
			}
		}
		
		return $sortedData;
	}
	
	/**
	 * Returns an array that has as key the depth and as value the category ids on that depth
	 * Sorts the array in the process
	 * 		[0] => '13'
	 * 		[1] => '12, 11, 39, 54'
	 * 
	 * @return {array} 
	 * @param $rootUid Object
	 */
	function &getRecordsPerLevelArray($rootUid) {
		if(!is_numeric($rootUid)) {
			if (TYPO3_DLOG) t3lib_div::devLog('getRecordsPerLevelArray has an invalid parameter.', COMMERCE_EXTkey, 3);
			return array();	
		}
		
		//Go through the leafs and get sorted array
		$l = count($this->leafs);
		
		$sortedData = array();
		
		//Sort and return the sorted array
		for($i = 0; $i < $l; $i ++) {
			$this->leafs[$i]->sort($rootUid);
			$sorted 	= $this->leafs[$i]->getSortedArray();
			#debug($sorted);
			$sortedData = array_merge($sortedData, $sorted);
		}
		
		//Create the depth_catUids array
		$depths = array();
		
		$l = count($sortedData);
		
		for($i = 0; $i < $l; $i ++) {
			if(!is_array($depth[$sortedData[$i]['depth']])) {
				$depth[$sortedData[$i]['depth']] = array($sortedData[$i]['record']['uid']);
			} else {
				$depth[$sortedData[$i]['depth']][] = $sortedData[$i]['record']['uid'];
			}
		}
		
		return $depth;
	}
	
	/**
	 * Will initialize the User Position
	 * Saves it in the Session and gives the Position UIDs to the LeafData
	 * 
	 * @return {void} 
	 */
	protected function initializePositionSaving() {
		// Get stored tree structure:
		$positions = unserialize($GLOBALS['BE_USER']->uc['browseTrees'][$this->treeName]);
		
		//In case the array is not set, initialize it
		if(!is_array($positions) || 0 >= count($positions) || key($positions[0][key($positions[0])]) !== 'items') {
			$positions = array(); // reinitialize damaged array
			$this->savePosition($positions);
			if (TYPO3_DLOG) t3lib_div::devLog('Resetting the Positions of the Browsetree. Were damaged.', COMMERCE_EXTkey, 2);
		}
		
		$PM = t3lib_div::_GP('PM');
		if(($PMpos = strpos($PM, '#')) !== false) { $PM = substr($PM, 0, $PMpos); } //IE takes # as anchor
		$PM = explode('_',$PM);	//0: treeName, 1: leafIndex, 2: Mount, 3: set/clear [4:,5:,.. further leafIndices], 5[+++]: Item UID
		
		//PM has to be at LEAST 5 Items (up to a (theoratically) unlimited count)
		if (count($PM) >= 5 && $PM[0] == $this->treeName)	{
				
				//Get the value - is always the last item
				$value = explode('|', $PM[count($PM) - 1]); //so far this is 'current UID|Parent UID'
				$value = $value[0];							//now it is 'current UID'
				
				//Prepare the Array
				$c 		= count($PM);
				$field  = &$positions[$PM[1]][$PM[2]]; //We get the Mount-Array of the corresponding leaf index
				
				//Move the field forward if necessary
				if($c > 5) {
					$c -= 4;

					//Walk the PM
					$i = 4;

					//Leave out last value of the $PM Array since that is the value and no longer a leaf Index
					while($c > 1) {
						//Mind that we increment $i on the fly on this line
						$field = &$field[$PM[$i++]];
						$c --;
					}
				}
				
				if ($PM[3])	{	// set
					$field['items'][$value]=1;
					$this->savePosition($positions);
				} else {	// clear
					unset($field['items'][$value]);
					$this->savePosition($positions);
				}
		}
	
		//Set the Positions for each leaf
		for($i = 0; $i < $this->leafcount; $i ++) {
			$this->leafs[$i]->setDataPositions($positions);
		}
	}
	
	/**
	 * Saves the content of ->stored (keeps track of expanded positions in the tree)
	 * $this->treeName will be used as key for BE_USER->uc[] to store it in
	 *
	 * @param {array} $positions	Positionsarray
	 * @return	{void}
	 * @access private
	 */
	protected function savePosition(&$positions)	{
		if(is_null($positions) || !is_array($positions)) {
			if (TYPO3_DLOG) t3lib_div::devLog('The Positions were not saved because the parameter was invalid', COMMERCE_EXTkey, 3);
			return;	
		}
		
		$GLOBALS['BE_USER']->uc['browseTrees'][$this->treeName] = serialize($positions);
		$GLOBALS['BE_USER']->writeUC();
	}
}
?>
