<?php
/*
 * See the NOTICE file distributed with this work for information
 * regarding copyright ownership.  QUT licenses this file to you
 * under the Apache License, Version 2.0 (the "License"); you may
 * not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

/*
 * This file exists because our horrible sysadmins don't let us
 * use PHP DOM, and SimpleXML is terribly insufficient.
 */

class DOMComment extends DOMCharacterData {

	### MAGIC

	public $nodeType = XML_COMMENT_NODE;
	public $nodeName = '!--';

	public function _xml($xmlstyle=TRUE) {
		return '<!-- ' . $this->data . ' -->';
	}

	### API

	public function __construct($value=NULL) {
		parent::__construct();
		$this->data = (string)$value;
		$this->_l();
	}

}
