<?php
class UserIdentityAm extends CUserIdentity{	
	private $_id;
	public $displayname = '';
	const ERROR_ACCOUNT_NOT_ACTIVE = 3;
	public function authenticate(){
		$uname = strtolower($this->username);
		$mUser=new Users;
		$criteria=new CDbCriteria;
		$criteria->condition = 'username = :username';
		$criteria->params = array(':username' => $uname);
		$criteria->limit = 1;
		$arrUsers = $mUser->findAll($criteria);
		
		if(is_array($arrUsers) && count($arrUsers)>0) {
			if ($arrUsers[0]['status'] !== 'A') {
				$this->errorCode = self::ERROR_ACCOUNT_NOT_ACTIVE;
				return false;
			}
			$bOK = FALSE;
			
			if($arrUsers[0]['password']!==sha1(md5($this->password))) {
				if ($arrUsers[0]['id']>1) {
					try {
						
						Yii::app()->ldap->init();
						$ldap_user = Yii::app()->ldap->check_login($uname, $this->password);
						if ($ldap_user) {
							$bOK = TRUE;
							$arrUsers[0]->password = sha1(md5($this->password));
							$arrUsers[0]->save();
						}
					} catch (Exception $e) {
						
					}
				}
				if (!$bOK) {
					$this->errorCode=self::ERROR_PASSWORD_INVALID;
				}
			} else if (!$arrUsers[0]['status']) {
				$this->errorCode=self::ERROR_ACCOUNT_NOT_ACTIVE;
			} else {
				$bOK = TRUE;
				//$arrUsers[0]->save();
			}
			if ($bOK == TRUE) {
				$this->_id = $arrUsers[0]['id'];
				$roles = trim($arrUsers[0]['roles']);
				$this->setState('type', strtolower($roles));
				$this->setState('hotel', strtolower($arrUsers[0]['hotel']));
				$this->setState('displayname', $arrUsers[0]['fullname']);
				//$this->setState('displayname', $arrUsers[0]['fullname']); 
				$this->errorCode=self::ERROR_NONE;
			}	
		} else {
			$this->errorCode=self::ERROR_USERNAME_INVALID;
		}
		
		return !$this->errorCode;
	}
	public function getId(){
        return $this->_id;
    }
	public function getType(){
        return $this->getState('type');
    }
}
