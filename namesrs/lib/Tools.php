<?php
use Illuminate\Database\Capsule\Manager as Capsule;

class Tools {
	public static function generateEppCode($nrOfCharacters, $specialCharaters) {
		$code = "";
		$length = strlen($specialCharaters) -1;
		for ($i = 0; $i < $nrOfCharacters; $i++) {
			$code .= substr($specialCharaters,rand(0,$length),1); 
		}
		return $code; 
	} 
	public static function createEmailTemplates() {
		$usedTemplates = array('EPP Code','NameSRS Status');
		$templates = array(
			"EPP Code" => "INSERT INTO `tblemailtemplates` (`id`, `type`, `name`, `subject`, `message`, `attachments`, `fromname`, `fromemail`, `disabled`, `custom`, `language`, `copyto`, `plaintext`) VALUES (NULL, 'domain', 'EPP Code', 'New EPP Code for \{\$domain_name\}', '<p>Dear {\$client_name},</p> <p>A new EPP Code was generated for the domain {\$domain_name}: {\$code}</p> <p>You may transfer away your domain with the new EPP-Code.</p> <p>{\$signature}</p>', '', '', '', '0', '1', '', '', '0');",
			"NameSRS Status" => "INSERT INTO `tblemailtemplates` (`id`, `type`, `name`, `subject`, `message`, `attachments`, `fromname`, `fromemail`, `disabled`, `custom`, `language`, `copyto`, `plaintext`) VALUES (NULL, 'domain', 'NameSRS Status', '{\$orderType} {\$domain_name}: {\$status}', '<p>Dear {\$client_name},</p> <p>we received following status for your domain {\$domain_name} (\{\$orderType}): {\$status}</p> <p>{\$errors}</p> <p> </p> <p>{\$signature}</p>', '', '', '', '0', '1', '', '', '0');"
		);
		$found = 0;
		$command = "getemailtemplates";
 		$adminuser = Tools::getApiUser();
 		$values["type"] = "domain"; 
		$results = localAPI($command,$values,$adminuser);
 		foreach($results["emailtemplates"]["emailtemplate"] as $key => $value) {
 			$existingTemplates[$value["name"]] = true;
 		}
 		foreach($usedTemplates as $key => $name) {
 			if(!$existingTemplates[$name]) {
 				mysql_query($templates[$name]);
 				if(mysql_error()) {
 					echo "Error writing email-templates (".$name."): ". mysql_error()."\n";
 				}				
 			}
 		}
	}
	public static function addNote($domainName,$message) {
		$adminuser = Tools::getApiUser();
		$values["domain"] = $domainName;	
		$command = "getclientsdomains";
		$results = localAPI($command, $values, $adminuser);
		$domains = $results["domains"]["domain"];
		$domain  = $domains[count($domains)-1];
		$adminuser = Tools::getApiUser();

		$command = "updateclientdomain";
		$values["domainId"] = $domain["id"];
		$values["notes"] = $domain["notes"]."\n[".date("Y-m-d H:i:s")."] ". $message;
		return  localAPI($command, $values, $adminuser);
	}
	public static function getDomainId($domain) {
		$query = 'SELECT id FROM  `tbldomains` WHERE domain =  "'.$domain.'" LIMIT 0 , 1';
		$result = mysql_query($query);
		$row = mysql_fetch_assoc($result);		
	    $id = $row["id"];
	    return $id; 
	}	
	public static function setExpireDate($domain) {
		$tmpDate = explode("T", $domain->ExpDate);
		$expirydate = str_replace("-", "", $tmpDate[0]);
		$command 	= "updateclientdomain";
		$adminuser 	= Tools::getApiUser();
		$values["domain"] = $domain->DomainName;
		$values["expirydate"] = $expirydate;
 		$results 	= localAPI($command,$values,$adminuser);
 		return $results;
	}
	public static function getApiUser() {
		global $cachedAdminUser; 
		if($cachedAdminUser) return $cachedAdminUser;
		$result = Capsule::select("select username,notes from tbladmins");
		foreach ($result as $key => $user) {
			if($user->notes=="apiuser") return $user->username;
			$admin = $user->username;
		}	
		$cachedAdminUser = $admin; 
		return $admin;
	}
}

?>