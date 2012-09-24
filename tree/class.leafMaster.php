<?php
/**
 * Implements a master leaf for the browsetree
 
 * @author 		Marketing Factory <typo3@marketing-factory.de>
 * @maintainer 	Erik Frister <typo3@marketing-factory.de>
 **/
require_once(t3lib_extmgm::extPath('commerce').'tree/class.leaf.php');
require_once(t3lib_extmgm::extPath('commerce').'tree/class.mounts.php');

class leafMaster extends leaf{
	
	protected $mountClass = 'mounts';
	
	// --internal--
	protected $byMounts;	//Flag if the Leafitems shall be read by specific mountpoints
	protected $mounts;		//Mountpoint-Object with the mountpoints of the leaf (if it is a treeleaf)
	
	/**
	 * Initializes the leaf
	 * Passes the Parameters to its child leafs
	 * 
	 * @param $index {int}			Index of this leaf
	 * @param $parentIndices {array}Array with parent indices
	 * @return {void}
	 */
	public function init($index, $parentIndices = array()) {
		if(!is_numeric($index) || !is_array($parentIndices)) {
			if (TYPO3_DLOG) t3lib_div::devLog('init (leaf) gets passed invalid parameters.', COMMERCE_EXTkey, 3);	
			return;	
		}
		
		//Load Mountpoints and init the Position if we want to read the leafs by Mountpoints
		if($this->byMounts) {
			$this->loadMountpoints();
		}
		
		//Initialize the LeafData
		$this->data->init();
		$this->data->initRecords($index, $parentIndices);
		
		parent::init($index, $parentIndices);
	}
	
	/**
	 * Sets the View and the Data of the Leaf
	 * 
	 * @return {void}
	 * @param {object} $view 	LeafView of the Leaf
	 * @param {object} $data 	LeafData of the Leaf
	 */
	public function initBasic(&$view, &$data) {
		if(is_null($view) || is_null($data)) {
			if (TYPO3_DLOG) t3lib_div::devLog('initBasic (leaf) gets passed invalid parameters.', COMMERCE_EXTkey, 3);	
			return;	
		}
		
		parent::initBasic($view, $data);
		
		$this->byMounts 	= false;
		$this->mounts		= null; 
	}
	
	/**
	 * Sets if the leaf should be read by the Mountpoints
	 * 
	 * @return {boolean}
	 * @param $flag {boolean}	Flag
	 */
	public function byMounts($flag = true) {
		$this->byMounts = (bool)$flag;
	}
	
	/**
	 * Pass the Item UID Array with the Mountpoints to the LeafData
	 * 
	 * @return {void}
	 * @param $mountIds {array}	Array with item UIDs that are mountpoints
	 */
	public function setMounts($mountIds) {
		if(!is_array($mountIds)) {
			if (TYPO3_DLOG) t3lib_div::devLog('setMounts (leafMaster) gets passed invalid parameters.', COMMERCE_EXTkey, 3);	
			return;	
		}
		$this->data->setMounts($mountIds);
	}
	
	/**
	 * Loads the leafs Mountpoints and sets their UIDs to the LeafData
	 * 
	 * @return {void}
	 */
	protected function loadMountpoints() {
		$this->mounts = t3lib_div::makeInstance($this->mountClass);
		$this->mounts->init($GLOBALS['BE_USER']->user['uid']);
		
		$this->setMounts($this->mounts->getMountData());
	}
	
	/**
	 * Pass the UID of the Item to recursively build a tree from to the LeafData
	 * 
	 * @return {void}
	 * @param $uid {int}	UID of the Item
	 */
	function setUid($uid) {
		if(!is_numeric($uid)) {
			if (TYPO3_DLOG) t3lib_div::devLog('setUid (leafMaster) gets passed invalid parameters.', COMMERCE_EXTkey, 3);	
			return;	
		}
		$this->data->setUid($uid);
	}
	
	/**
	 * Sets the recursive depth of the tree
	 * 
	 * @return {void}
	 * @param $depth {int}	Recursive Depth
	 */
	function setDepth($depth) {
	
		if(!is_numeric($depth)) {
			if (TYPO3_DLOG) t3lib_div::devLog('setDepth (leafMaster) gets passed invalid parameters.', COMMERCE_EXTkey, 3);	
			return;
		}
		$this->data->setDepth($depth);
	}
	
	/**
	 * Prints the Leaf by its mountpoint
	 * 
	 * @return {string}	HTML Code
	 */
	function printLeafByMounts() {
		$out = '';
		
		
		
#		debug($this->data,'hasRecords',__LINE__,__FILE__);#
#		debug($this->data->getRecords(),'records',__LINE__,__FILE__);
		
		//If we don't have a mount object, return the error message
		if(null == $this->mounts || !$this->mounts->hasMounts() || !$this->data->hasRecords()) return $this->getLL('leaf.noMount');
		
		while(false !== ($mount = $this->mounts->walk())) {
			$out .= $this->printChildleafsByLoop($mount, $mount);
		}
		
		return $out;
	}
	
	/**
	 * Returns whether or not a node is the last in the current subtree
	 * 
	 * @return {boolean}
	 * @param $row {array}		Row Item
	 * @param $pid {int}		Parent UID of the current Row Item
	 * @param $i {int}			Current Index of the Row in the loop
	 * @param $l {int}			Length of the loop
	 */
	public function isLast($row, $pid = 0) {
		if(!is_array($row) || !is_numeric($pid)) {
			if (TYPO3_DLOG) t3lib_div::devLog('isLast (leaf) gets passed invalid parameters.', COMMERCE_EXTkey, 3);	
			return false;
		}
		
		$isLast = parent::isLast($row, $pid);
		
		//In case the row is last, check if it is really the last by seeing if any of its slave leafs have records
		if($isLast) {
			$isLast = !$this->leafsHaveRecords($pid);
		}
		
		return $isLast;
	}
	
	/**
	 * Returns whether or not a node has Children
	 * 
	 * @param $row {array}	Row Item
	 * @return {boolean}
	 */
	public function hasChildren($row)  {
		if(!is_array($row)) {
			if (TYPO3_DLOG) t3lib_div::devLog('hasChildren (leafMaster) gets passed invalid parameters.', COMMERCE_EXTkey, 3);	
			return false;	
		}

		$hasChildren = ($this->data->getChildrenByPid($row['uid']));
	
		//if current item doesn't have subchildren, look in slaveLeafs
		if(!$hasChildren) {
			$hasChildren = parent::hasChildren($row);	
		}
		
		return $hasChildren;
	}
	
	/**
	 * Does the same thing printChildleafsByUid and printChildleafsByPid do in one function without recursion
	 * 
	 * @return {string}		HTML Code
	 * @param $startUid {int}	 UID in which we start
	 * @param $bank	{int}		 Bank UID
	 * @param $treeName {string} Tree Name
	 * @param $pid {int}		 UID of the parent Item - only passed if this function is called by ajax; thus it will only influence functionality if it is numeric
	 */
	function printChildleafsByLoop($startUid, $bank, $pid = false) {
	
		//Check for valid parameters
		if(!is_numeric($startUid) || !is_numeric($bank)) {
			if (TYPO3_DLOG) t3lib_div::devLog('printChildleafsByLoop (leafMaster) gets passed invalid parameters.', COMMERCE_EXTkey, 3);	
			return '';	
		}
		
		//Set the bank
		$this->view->setBank($bank);
		$this->data->setBank($bank);
		
		//Set the TreeName
		$this->view->setTreeName($this->treeName);
		
		//init vars
		$out 			= '';
		$level 			= 0;
		$lastLevel		= 0;
		$crazyStart = $crazyRecursion = 10000;	//Max. number of loops we make
		$tempChildren 	= array();				//temporary child stack
		$tempLevels		= array(0);				//temporary level stack - already filled with a 0 because the starting child is on level 0
		$levelOpener	= array();				//holds which uid openend which level
		
		//get the current item and set it as the starting child to print
		$child 		= $this->data->getChildByUid($startUid);	
		
		//Abort if the starting Category is not found
		if(null == $child) {
			if (TYPO3_DLOG) t3lib_div::devLog('printChildleafsByLoop (leafMaster) cannot find the starting item by its uid.', COMMERCE_EXTkey, 3);	
			return '';
		}
		
		//Process the child and children
		while(!is_null($child) && is_array($child) && $crazyRecursion > 0) {
			$level = @array_pop($tempLevels); //get the current level
			
			//close the parent list if we are on a higher level than the list
			if($level < $lastLevel) {
				for($i = $level; $i < $lastLevel; $i ++) {
					$uid = array_pop($levelOpener);					//get opener uid
			
					$out .= $this->getSlaveElements($uid, $bank);	//print slave elements from the opener
					$out .= '</ul></li>';							//close opener
				}
			}
			
			$lastLevel = $level;
			
			/********************
			 * Printing the Item
			 *******************/
			//Give class 'expanded' if it is
			$exp 		 = $this->data->isExpanded($child['uid']);
			$cssExpanded = ($exp) ? 'expanded' : '';
			
			//Add class 'last' if it is
			
			if($pid !== false) {
				//called by AJAX - to get it's true parent item, we have to pass the pid because otherwise its ambiguous
				$child['item_parent'] 	= $pid;
				$pid 					= false; //all following items are not to be passed the pid because they are not ambiguous
			}

			$isLast 	= $this->isLast($child, $child['item_parent']);
			$cssLast 	= ($isLast) ? ' last' : '';
			
			$cssClass 	= $cssExpanded.' '.$cssLast;
			
			$out .= '<li class="'.$cssClass.'">'; //start the element
			
			$isBank 		= ($child['uid'] == $bank);
			
			$hasChildren 	= $this->hasChildren($child);
			$out .= $this->view->PMicon($child, $isLast, $exp, $isBank, $hasChildren); //pm icon
			
			$out .= (0 == $child['uid']) ? $this->view->getRootIcon($child) : $this->view->getIcon($child); //icon
			$out .= $this->view->wrapTitle($child['title'], $child);	//title
			
			/******************
			 * Done printing
			 *****************/
			
			//read the children
			$childElements = ($exp) ? $this->data->getChildrenByPid($child['uid']) : array();
			$m			   = count($childElements);
			
			//if there are children
			if(0 < $m) {
				$levelOpener[] = $child['uid'];	//add that this record uid opened the last level
				
				//set $child to first child element and store the other in a temp Array, with the second child being on the last position (like a stack)
				$child 		= $childElements[0];
				
				$out  	   .= '<ul>';
				$level ++;
				
				//add all children except the first to the stack
				for($j = $m - 1; $j > 0; $j --) {
					$tempChildren[] = $childElements[$j];
					$tempLevels[] 	= $level;	//Add the levels of the current items to the stack
				}
				
				$tempLevels[]  = $level; //add the level of the next child as well
				
			} else { //else (no children in its own leaf)
				//Print the children from the slave leafs if the current leaf is expanded
				if($exp) {
					
					$out .= '<ul>';
					$out .= $this->getSlaveElements($child['uid'], $bank);
					$out .= '</ul>';
				}
				
				//pop the last element from the temp array and set it as a child; if temp array is empty, break;
				$child 		= @array_pop($tempChildren);
				
				//close the list item
				$out .= '</li>';
			}
	
			$crazyRecursion --;
		}
		
		//DLOG
		if (TYPO3_DLOG) t3lib_div::devLog('printChildLeafsByLoop (leafMaster) did '.($crazyStart - $crazyRecursion).' loops!', COMMERCE_EXTkey, 1);	
		
		//Close the rest of the lists
		for($i = 0; $i < $lastLevel; $i ++) {
			
			$uid = array_pop($levelOpener);					//get opener uid
			
			$out .= $this->getSlaveElements($uid, $bank);	//print slave elements for the opener
			$out .= '</ul></li>';							//close the opener
		}
		
		//Abort if the max. number of loops has been reached
		if($crazyRecursion <= 0) {
			if (TYPO3_DLOG) t3lib_div::devLog('printChildleafsByLoop (leafMaster) was put to hold because there was a danger of endless recursion.', COMMERCE_EXTkey, 3);	
			return $this->getLL('leaf.maxRecursion');
		}
		
		return $out;
	}
	
	/**
	 * Gets the elements of the slave leafs
	 * 
	 * @return  {string} 	HTML Code generated by the slaveleafs
	 */
	protected function getSlaveElements($pid, $bank) {
		$out = '';
		
		for($i = 0; $i < $this->leafcount; $i ++) {
			$out .= $this->leafs[$i]->printChildleafsByParent($pid, $bank);
		}
		
		return $out;
	}
}
?>
