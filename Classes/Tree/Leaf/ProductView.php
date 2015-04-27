<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2008-2014 Erik Frister <typo3@marketing-factory.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Implements the Tx_Commerce_Tree_Leaf_View for Product
 */
class Tx_Commerce_Tree_Leaf_ProductView extends Tx_Commerce_Tree_Leaf_View {
	/**
	 * DB Table
	 *
	 * @var string
	 */
	protected $table = 'tx_commerce_products';

	/**
	 * @var string
	 */
	protected $domIdPrefix = 'txcommerceProduct';

	/**
	 * Wrapping $title in a-tags.
	 *
	 * @param string $title Title string
	 * @param array &$row Item record
	 * @param integer $bank Bank pointer (which mount point number)
	 * @return string
	 */
	public function wrapTitle($title, &$row, $bank = 0) {
		if (!is_array($row) || !is_numeric($bank)) {
			if (TYPO3_DLOG) {
				GeneralUtility::devLog('wrapTitle (productview) gets passed invalid parameters.', COMMERCE_EXTKEY, 3);
			}
			return '';
		}

			// Max. size for Title of 255
		$title = ('' != trim($title)) ? GeneralUtility::fixed_lgd_cs($title, 255) : $this->getLL('leaf.noTitle');

		$aOnClick = 'if(top.content.list_frame){top.content.list_frame.location.href=top.TS.PATH_typo3+\'alt_doc.php?returnUrl=\'+top.rawurlencode(top.content.list_frame.document.location.pathname+top.content.list_frame.document.location.search)+\'&' . $this->getJumpToParam($row) . '\';}';

		$res = '<a href="#" onclick="' . htmlspecialchars($aOnClick) . '">' . $title . '</a>';

		return $res;
	}

	/**
	 * returns the link from the tree used to jump to a destination
	 *
	 * @param array $row - Array with the ID Information
	 * @return string
	 */
	public function getJumpToParam(&$row) {
		if (!is_array($row)) {
			if (TYPO3_DLOG) {
				GeneralUtility::devLog('getJumpToParam (productview) gets passed invalid parameters.', COMMERCE_EXTKEY, 3);
			}
			return '';
		}

		$value = 'edit';

		if ($this->realValues) {
			$value = $this->table . '_' . $row['uid'];
		}

		$res = 'id=' . $row['pid'] . '&edit[' . $this->table . '][' . $row['uid'] . ']=' . $value;
		return $res;
	}
}
